<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Outreach\Jobs\CheckOutreachRepliesJob;
use App\Outreach\Jobs\ProcessOutreachLeadsJob;
use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachCampaignStep;
use App\Models\Contact;
use App\Models\Customer;
use App\Outreach\Models\OutreachArchivedThread;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachReplyTemplate;
use App\Outreach\Models\OutreachSendLog;
use App\Outreach\Models\OutreachWatchedEmail;
use App\Outreach\Services\OutreachCsvImportService;
use App\Outreach\Services\ReplyDetectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * OutreachController
 *
 * Thin controller — keeps business logic in services/models.
 * Provides CRUD for all outreach entities and manual trigger endpoints.
 */
class OutreachController extends Controller
{
    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $stats = [
            'campaigns'      => OutreachCampaign::count(),
            'active_leads'   => OutreachLead::where('status', 'active')->count(),
            'replied'        => OutreachLead::where('replied', true)->count(),
            'completed'      => OutreachLead::where('status', 'completed')->count(),
            'sent_today'     => OutreachSendLog::where('status', 'sent')
                                  ->whereDate('sent_at', today())->count(),
            'failed_today'   => OutreachSendLog::where('status', 'failed')
                                  ->whereDate('created_at', today())->count(),
            'unread_replies' => OutreachMessage::where('direction', OutreachMessage::DIRECTION_INBOUND)
                                  ->whereNull('read_at')->count(),
        ];

        return view('outreach.dashboard', compact('stats'));
    }

    // ─── Email Accounts ──────────────────────────────────────────────────────

    public function accountsIndex(): View
    {
        $accounts = OutreachEmailAccount::orderBy('name')->paginate(20);
        return view('outreach.accounts.index', compact('accounts'));
    }

    public function accountsCreate(): View
    {
        return view('outreach.accounts.create');
    }

    public function accountsStore(Request $request): RedirectResponse
    {
        $request->merge([
            'is_primary_reply_account' => $request->boolean('is_primary_reply_account'),
        ]);

        $data = $request->validate([
            'name'                     => 'required|string|max:100',
            'email'                    => 'required|email|unique:outreach_email_accounts,email',
            'provider'                 => 'required|in:gmail,smtp,outlook,zone_relay',
            'smtp_host'                => 'required_unless:provider,zone_relay|nullable|string',
            'smtp_port'                => 'required_unless:provider,zone_relay|nullable|integer|between:1,65535',
            'smtp_username'            => 'required_unless:provider,zone_relay|nullable|string',
            'smtp_password'            => 'required_unless:provider,zone_relay|nullable|string',
            'smtp_encryption'          => 'required_unless:provider,zone_relay|nullable|in:tls,ssl,none',
            'imap_host'                => 'nullable|string',
            'imap_port'                => 'nullable|integer|between:1,65535',
            'imap_username'            => 'nullable|string',
            'imap_password'            => 'nullable|string',
            'imap_encryption'          => 'nullable|in:ssl,tls,none',
            'relay_url'                => 'required_if:provider,zone_relay|nullable|url',
            'relay_secret'             => 'required_if:provider,zone_relay|nullable|string|min:16',
            'daily_limit'              => 'required|integer|min:1|max:500',
            'is_active'                => 'boolean',
            'is_primary_reply_account' => 'boolean',
        ]);

        $account = \DB::transaction(function () use ($data) {
            if (! empty($data['is_primary_reply_account'])) {
                // Single-primary invariant: clear any existing primary flag
                // before setting the new one.
                OutreachEmailAccount::where('is_primary_reply_account', true)
                    ->update(['is_primary_reply_account' => false]);
            }
            return OutreachEmailAccount::create($data);
        });

        return redirect()->route('outreach.accounts.index')
                         ->with('success', 'Email account added.');
    }

    public function accountsEdit(OutreachEmailAccount $account): View
    {
        return view('outreach.accounts.edit', compact('account'));
    }

    public function accountsUpdate(Request $request, OutreachEmailAccount $account): RedirectResponse
    {
        $request->merge([
            'is_active'                => $request->has('is_active'),
            'is_primary_reply_account' => $request->boolean('is_primary_reply_account'),
        ]);

        $data = $request->validate([
            'name'                     => 'required|string|max:100',
            'signature_html'           => 'nullable|string|max:5000',
            'provider'                 => 'nullable|in:gmail,smtp,outlook,zone_relay',
            'smtp_host'                => 'nullable|string',
            'smtp_port'                => 'nullable|integer|between:1,65535',
            'smtp_username'            => 'nullable|string',
            'smtp_password'            => 'nullable|string',   // Optional — leave blank to keep current
            'smtp_encryption'          => 'nullable|in:tls,ssl,none',
            'imap_host'                => 'nullable|string',
            'imap_port'                => 'nullable|integer|between:1,65535',
            'imap_username'            => 'nullable|string',
            'imap_password'            => 'nullable|string',
            'relay_url'                => 'nullable|url',
            'relay_secret'             => 'nullable|string|min:16',
            'daily_limit'              => 'required|integer|min:1|max:500',
            'is_active'                => 'boolean',
            'is_primary_reply_account' => 'boolean',
        ]);

        // Don't overwrite encrypted passwords with null when field is left blank
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }
        if (empty($data['imap_password'])) {
            unset($data['imap_password']);
        }
        if (empty($data['relay_secret'])) {
            unset($data['relay_secret']);
        }

        \DB::transaction(function () use ($data, $account) {
            if (! empty($data['is_primary_reply_account'])) {
                // Single-primary invariant: clear flag from any other account.
                OutreachEmailAccount::where('is_primary_reply_account', true)
                    ->where('id', '!=', $account->id)
                    ->update(['is_primary_reply_account' => false]);
            }
            $account->update($data);
        });

        return redirect()->route('outreach.accounts.index')
                         ->with('success', 'Account updated.');
    }

    public function accountsDestroy(OutreachEmailAccount $account): RedirectResponse
    {
        $account->delete();
        return redirect()->route('outreach.accounts.index')
                         ->with('success', 'Account removed.');
    }

    // ─── Campaigns ───────────────────────────────────────────────────────────

    public function campaignsIndex(): View
    {
        $campaigns = OutreachCampaign::withCount('leads')->orderByDesc('created_at')->paginate(20);
        return view('outreach.campaigns.index', compact('campaigns'));
    }

    public function campaignsCreate(): View
    {
        return view('outreach.campaigns.create');
    }

    public function campaignsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
            'ai_prompt'          => 'nullable|string',
            'daily_limit'        => 'nullable|integer|min:1',
            'reply_stop_enabled' => 'boolean',
            'use_ai_line'        => 'boolean',
            'is_active'          => 'boolean',
        ]);

        $campaign = OutreachCampaign::create($data);

        return redirect()->route('outreach.campaigns.show', $campaign)
                         ->with('success', 'Campaign created.');
    }

    public function campaignsShow(OutreachCampaign $campaign): View
    {
        $campaign->loadMissing(['steps', 'leads']);
        $recentLogs = OutreachSendLog::where('campaign_id', $campaign->id)
            ->with('lead')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('outreach.campaigns.show', compact('campaign', 'recentLogs'));
    }

    public function campaignsUpdate(Request $request, OutreachCampaign $campaign): RedirectResponse
    {
        // Unchecked HTML checkboxes send no value at all, so missing fields must
        // be coerced to false before validation — otherwise validate() omits them
        // from $data and update() leaves the existing DB value untouched.
        $request->merge([
            'reply_stop_enabled' => $request->boolean('reply_stop_enabled'),
            'use_ai_line'        => $request->boolean('use_ai_line'),
            'is_active'          => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
            'ai_prompt'          => 'nullable|string',
            'daily_limit'        => 'nullable|integer|min:1',
            'reply_stop_enabled' => 'boolean',
            'use_ai_line'        => 'boolean',
            'is_active'          => 'boolean',
        ]);

        $campaign->update($data);

        return back()->with('success', 'Campaign updated.');
    }

    public function campaignsDestroy(OutreachCampaign $campaign): RedirectResponse
    {
        $campaign->delete();
        return redirect()->route('outreach.campaigns.index')
                         ->with('success', 'Campaign deleted.');
    }

    // ─── Campaign Steps ───────────────────────────────────────────────────────

    public function stepsStore(Request $request, OutreachCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'step_order'    => 'required|integer|min:1',
            'day_offset'    => 'required|integer|min:0',
            'subject'       => 'required|string|max:500',
            'body_template' => 'required|string',
        ]);

        $campaign->steps()->create($data);

        return back()->with('success', 'Step added.');
    }

    public function stepsUpdate(Request $request, OutreachCampaign $campaign, OutreachCampaignStep $step): RedirectResponse
    {
        $data = $request->validate([
            'step_order'    => 'required|integer|min:1',
            'day_offset'    => 'required|integer|min:0',
            'subject'       => 'required|string|max:500',
            'body_template' => 'required|string',
        ]);

        $step->update($data);

        return back()->with('success', 'Step updated.');
    }

    public function stepsDestroy(OutreachCampaign $campaign, OutreachCampaignStep $step): RedirectResponse
    {
        $step->delete();
        return back()->with('success', 'Step removed.');
    }

    /**
     * Send a test email for a given step to the supplied address.
     * Uses an in-memory lead populated with sample data so templates render
     * identically to production. Sends via the first active email account.
     */
    public function stepsTestSend(
        Request $request,
        OutreachCampaign $campaign,
        OutreachCampaignStep $step,
        \App\Outreach\Services\OutreachMailer $mailer,
    ): RedirectResponse {
        $data = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $account = OutreachEmailAccount::where('is_active', true)->first();

        if (! $account) {
            return back()->with('error', 'Aktiivset e-posti kontot ei leitud. Lisa/aktiveeri konto.');
        }

        // Populate an unsaved lead with sample data for realistic rendering.
        $sampleLead = new OutreachLead([
            'first_name'        => 'Mari',
            'last_name'         => 'Maasikas',
            'email'             => $data['test_email'],
            'company'           => 'Näidis OÜ',
            'website'           => 'https://naide.ee',
            'industry'          => 'E-kaubandus',
            'lcp_mobile'        => '2.8s',
            'performance_score' => 45,
            'ai_line'           => 'Märkasin, et teie avaleht laeb mobiilis aeglaselt.',
        ]);

        $subject = '[TEST] ' . $step->renderSubject($sampleLead);
        $body    = $step->renderBody($sampleLead);

        try {
            $mailer->send(
                $account,
                $data['test_email'],
                'Test Recipient',
                $subject,
                $body,
            );
        } catch (\Throwable $e) {
            \Log::error('[Outreach] Test send failed', [
                'step_id' => $step->id,
                'to'      => $data['test_email'],
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'Testkirja saatmine ebaõnnestus: ' . $e->getMessage());
        }

        return back()->with('success', "Testkiri saadetud: {$data['test_email']}");
    }

    // ─── Leads ───────────────────────────────────────────────────────────────

    public function leadsIndex(Request $request, OutreachCampaign $campaign): View
    {
        $q = trim((string) $request->get('q', ''));

        $leads = $campaign->leads()
            ->with('assignedEmailAccount')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('email', 'like', $like)
                      ->orWhere('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere('company', 'like', $like)
                      ->orWhere('website', 'like', $like);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('outreach.leads.index', compact('campaign', 'leads', 'q'));
    }

    public function leadsStore(Request $request, OutreachCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'required|email',
            'company'    => 'nullable|string|max:200',
            'website'    => 'nullable|url',
        ]);

        // First step is step_order = 1
        $firstStep = $campaign->getStepAt(1);

        $campaign->leads()->create([
            ...$data,
            'status'       => OutreachLead::STATUS_ACTIVE,
            'current_step' => 0,
            'enrolled_at'  => now(),
            'next_send_at' => $firstStep
                ? now()->addDays($firstStep->day_offset)
                : null,
        ]);

        return back()->with('success', 'Lead added.');
    }

    public function leadsUpdate(Request $request, OutreachCampaign $campaign, OutreachLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status'       => 'required|in:active,paused,completed,bounced,unsubscribed',
            'next_send_at' => 'nullable|date',
        ]);

        $lead->update($data);

        return back()->with('success', 'Lead updated.');
    }

    public function leadsDestroy(OutreachCampaign $campaign, OutreachLead $lead): RedirectResponse
    {
        $lead->delete();
        return back()->with('success', 'Lead removed.');
    }

    // ─── Manual Triggers (Admin) ──────────────────────────────────────────────

    /**
     * Manually trigger the lead processor (useful for testing without cron).
     */
    public function triggerProcess(Request $request): RedirectResponse
    {
        ProcessOutreachLeadsJob::dispatch()->onQueue('outreach');
        return back()->with('success', 'Processing job dispatched.');
    }

    /**
     * Manually trigger reply detection.
     */
    public function triggerReplyCheck(Request $request): RedirectResponse
    {
        CheckOutreachRepliesJob::dispatch()->onQueue('outreach');
        return back()->with('success', 'Reply check job dispatched.');
    }

    // ─── CSV Import ───────────────────────────────────────────────────────────

    /**
     * Handle CSV lead import.
     *
     * Accepts a .csv file and a campaign_id, stores the upload to a temp path,
     * delegates parsing to OutreachCsvImportService, then redirects with a
     * count of newly inserted leads.
     */
    public function importCsv(Request $request, OutreachCsvImportService $importer): RedirectResponse
    {
        $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:outreach_campaigns,id'],
            'csv_file'    => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5 MB
        ]);

        $campaign = OutreachCampaign::findOrFail($request->integer('campaign_id'));

        $path = $request->file('csv_file')->store('outreach/imports', 'local');

        try {
            // Use the disk path helper so the configured root (storage/app/private
            // in Laravel 11+) is honoured instead of guessing storage/app/*.
            $count = $importer->import(Storage::disk('local')->path($path), $campaign->id);
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['csv_file' => $e->getMessage()]);
        } finally {
            // Always clean up the temp file
            Storage::disk('local')->delete($path);
        }

        return redirect()
            ->route('outreach.campaigns.leads.index', $campaign)
            ->with('success', "Imporditi {$count} leadi.");
    }

    /**
     * Download a sample CSV template with headers and one example row.
     * Streams directly so no file is written to disk.
     */
    public function csvTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'email',
            'first_name',
            'last_name',
            'company',
            'website',
            'industry',
            'lcp_mobile',
            'performance_score',
            'notes',
            'qualification',
            'custom_line',
        ];

        $example = [
            'firma@naide.ee',
            'Mari',
            'Maasikas',
            'Näidis OÜ',
            'https://naide.ee',
            'E-kaubandus',
            '2.8s',
            '45',
            'Aeglane mobiilis',
            'lead',
            'Märkasin, et teie avaleht laeb mobiilis aeglaselt.',
        ];

        $callback = function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the file with correct encoding
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, $example);
            fclose($out);
        };

        return response()->streamDownload($callback, 'outreach-leads-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─── Send Logs ────────────────────────────────────────────────────────────

    public function logsIndex(OutreachCampaign $campaign): View
    {
        $logs = OutreachSendLog::where('campaign_id', $campaign->id)
            ->with(['lead', 'emailAccount'])
            ->orderByDesc('created_at')
            ->paginate(100);

        return view('outreach.logs.index', compact('campaign', 'logs'));
    }

    // ─── Inbox (Unified replies across mailboxes) ────────────────────────────

    /**
     * Inbox index — one row per unique reply-sender email.
     *
     * Groups OutreachMessage rows by from_email (lowercased) so that a single
     * person who replied across multiple campaigns appears once. Each group
     * surfaces the latest reply timestamp, total reply count, and the set of
     * campaigns they're associated with via their lead records.
     */
    public function inboxIndex(Request $request): View
    {
        return view('outreach.inbox.index', $this->buildInboxViewData($request, null));
    }

    /**
     * Build the data structure shared by both the inbox index (no thread
     * selected) and the inbox thread view. Returns:
     *   - threads: paginated list of inbox rows (one per unique sender email)
     *   - search:  current search query string
     *   - filter:  current filter chip ('all' | 'unanswered' | 'recent')
     *   - selectedEmail: lowercased email of the currently open thread, or null
     *   - timeline, leads, crmLink: only populated when a thread is selected
     */
    private function buildInboxViewData(Request $request, ?string $selectedEmail): array
    {
        $search = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'unanswered', 'recent', 'lead', 'customer', 'watched', 'archived'], true)) {
            $filter = 'all';
        }

        // Archive scope: every filter except 'archived' hides archived threads.
        // The 'archived' filter shows ONLY archived threads.
        $archivedEmails = OutreachArchivedThread::pluck('email_lower')->all();

        $query = OutreachMessage::query()
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->selectRaw('LOWER(from_email) as group_email')
            ->selectRaw('MAX(received_at) as last_received_at')
            ->selectRaw('COUNT(*) as reply_count')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
            ->selectRaw('MAX(from_name) as display_name')
            ->selectRaw('MAX(subject) as latest_subject')
            ->selectRaw('MAX(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as has_lead')
            ->selectRaw('MAX(CASE WHEN customer_id IS NOT NULL OR contact_id IS NOT NULL THEN 1 ELSE 0 END) as has_customer')
            ->groupBy('group_email')
            ->orderByDesc('last_received_at');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('from_email', 'like', $like)
                  ->orWhere('from_name', 'like', $like)
                  ->orWhere('subject', 'like', $like);
            });
        }

        if ($filter === 'recent') {
            $query->having('last_received_at', '>=', now()->subDays(7));
        }
        if ($filter === 'lead') {
            $query->having('has_lead', '=', 1);
        }
        if ($filter === 'customer') {
            $query->having('has_customer', '=', 1);
        }
        if ($filter === 'watched') {
            // Match against the (lowercased) watched-email list at the SQL
            // level so pagination + counts work over the filtered set.
            $watchedEmails = OutreachWatchedEmail::pluck('email')->all();
            if (empty($watchedEmails)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn(\DB::raw('LOWER(from_email)'), $watchedEmails);
            }
        }

        if ($filter === 'archived') {
            // Show only archived threads.
            if (empty($archivedEmails)) {
                $query->whereRaw('1 = 0'); // nothing
            } else {
                $query->whereIn(\DB::raw('LOWER(from_email)'), $archivedEmails);
            }
        } else {
            // All other filters hide archived threads.
            if (! empty($archivedEmails)) {
                $query->whereNotIn(\DB::raw('LOWER(from_email)'), $archivedEmails);
            }
        }

        $threads = $query->paginate(30)->withQueryString();

        $emails = $threads->getCollection()->pluck('group_email')->all();

        // ── Lead-side metadata (name / company / campaigns) for each row ────
        $leadIndex = OutreachLead::with('campaign')
            ->whereIn(\DB::raw('LOWER(email)'), $emails)
            ->get()
            ->groupBy(fn($l) => strtolower($l->email));

        // ── Customer / Contact records for the same emails (Layer 4 cross-link)
        // Used to enrich the row with display_name / company when no Lead exists
        // (e.g. a long-standing customer who was never part of an outreach campaign).
        $customerIndex = Customer::whereIn(\DB::raw('LOWER(email)'), $emails)
            ->get()
            ->keyBy(fn($c) => strtolower($c->email));
        $contactIndex = Contact::whereIn(\DB::raw('LOWER(email)'), $emails)
            ->get()
            ->keyBy(fn($c) => strtolower($c->email));

        // Manually-watched addresses (operator allowlist). Keyed identically
        // so we can mark threads with the "Jälgitav" chip in the inbox UI.
        $watchedIndex = OutreachWatchedEmail::whereIn('email', $emails)
            ->get()
            ->keyBy('email');

        // ── "Last outbound" lookup per email so we can compute is_unanswered.
        // We compare each row's last_received_at against the most recent
        // outbound activity for the same lead(s): either an outreach_send_log
        // (campaign step we sent) or an outreach_messages outbound row
        // (manual reply we sent from the CRM).
        $allLeadIds = $leadIndex->flatten(1)->pluck('id')->all();

        $sendByLead = $allLeadIds
            ? OutreachSendLog::whereIn('lead_id', $allLeadIds)
                ->where('status', OutreachSendLog::STATUS_SENT)
                ->selectRaw('lead_id, MAX(sent_at) as last_sent_at')
                ->groupBy('lead_id')
                ->pluck('last_sent_at', 'lead_id')
            : collect();

        $outboundByLead = $allLeadIds
            ? OutreachMessage::whereIn('lead_id', $allLeadIds)
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->selectRaw('lead_id, MAX(received_at) as last_outbound_at')
                ->groupBy('lead_id')
                ->pluck('last_outbound_at', 'lead_id')
            : collect();

        // Customer / Contact-keyed outbound timestamps too — needed for
        // is_unanswered when the thread has no lead at all (Strategy C path).
        $allCustomerIds = $customerIndex->pluck('id')->all();
        $allContactIds  = $contactIndex->pluck('id')->all();

        $outboundByCustomer = $allCustomerIds
            ? OutreachMessage::whereIn('customer_id', $allCustomerIds)
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->selectRaw('customer_id, MAX(received_at) as last_outbound_at')
                ->groupBy('customer_id')
                ->pluck('last_outbound_at', 'customer_id')
            : collect();

        $outboundByContact = $allContactIds
            ? OutreachMessage::whereIn('contact_id', $allContactIds)
                ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
                ->selectRaw('contact_id, MAX(received_at) as last_outbound_at')
                ->groupBy('contact_id')
                ->pluck('last_outbound_at', 'contact_id')
            : collect();

        $threads->getCollection()->transform(function ($row) use ($leadIndex, $customerIndex, $contactIndex, $watchedIndex, $sendByLead, $outboundByLead, $outboundByCustomer, $outboundByContact) {
            $leads    = $leadIndex->get($row->group_email, collect());
            $customer = $customerIndex->get($row->group_email);
            $contact  = $contactIndex->get($row->group_email);
            $watched  = $watchedIndex->get($row->group_email);
            $first    = $leads->first();

            // Display metadata cascades: lead → customer → contact, whichever
            // exists first wins. Both could be set (lead converted to customer)
            // — the lead's stored info is usually most recent so we prefer it.
            $row->lead_first_name = $first?->first_name ?? $customer?->first_name ?? $contact?->first_name;
            $row->lead_last_name  = $first?->last_name  ?? $customer?->last_name  ?? $contact?->last_name;
            $row->lead_company    = $first?->company
                                    ?? $customer?->company?->name
                                    ?? $contact?->company?->name;
            $row->campaigns       = $leads->pluck('campaign.name')->filter()->unique()->values();
            $row->lead_count      = $leads->count();
            $row->is_customer     = (bool) ($customer || $contact);
            $row->is_lead         = $leads->isNotEmpty();
            $row->is_watched      = (bool) $watched;
            $row->watched_label   = $watched?->label;

            // Latest outbound timestamp across all attribution channels for
            // this email — leads, customer, contact. Whichever fired most
            // recently wins.
            $lastOutbound = null;
            foreach ($leads as $l) {
                foreach ([$sendByLead->get($l->id), $outboundByLead->get($l->id)] as $ts) {
                    if ($ts && (! $lastOutbound || $ts > $lastOutbound)) {
                        $lastOutbound = $ts;
                    }
                }
            }
            if ($customer && ($ts = $outboundByCustomer->get($customer->id)) && (! $lastOutbound || $ts > $lastOutbound)) {
                $lastOutbound = $ts;
            }
            if ($contact && ($ts = $outboundByContact->get($contact->id)) && (! $lastOutbound || $ts > $lastOutbound)) {
                $lastOutbound = $ts;
            }
            $row->last_outbound_at = $lastOutbound;
            $row->is_unanswered    = $lastOutbound === null
                || $row->last_received_at > $lastOutbound;

            // Response-time urgency for unanswered threads:
            //   - green   (< 4h)   fresh, no rush
            //   - yellow  (4–24h)  reply soon
            //   - red     (> 24h)  overdue, risk of losing the lead
            // Answered threads have urgency=null (nothing waiting).
            $row->urgency = null;
            if ($row->is_unanswered && $row->last_received_at) {
                $hours = \Carbon\Carbon::parse($row->last_received_at)->diffInHours(now());
                $row->urgency = $hours < 4 ? 'green' : ($hours < 24 ? 'yellow' : 'red');
                $row->urgency_hours = $hours;
            }

            return $row;
        });

        // Apply unanswered filter post-query (the predicate depends on a
        // join+aggregate that's awkward in a single SQL grouping).
        if ($filter === 'unanswered') {
            $threads->setCollection(
                $threads->getCollection()->filter(fn($r) => $r->is_unanswered)->values()
            );
        }

        // Full watched list (independent of pagination) for the inbox sidebar
        // panel — the operator manages the allowlist from the same screen.
        $watchedAll = OutreachWatchedEmail::orderBy('email')->get();

        $data = [
            'threads'        => $threads,
            'search'         => $search,
            'filter'         => $filter,
            'selectedEmail'  => $selectedEmail !== null ? strtolower($selectedEmail) : null,
            'timeline'       => null,
            'leads'          => null,
            'crmLink'        => null,
            'watchedAll'     => $watchedAll,
        ];

        return $data;
    }

    /**
     * Inbox thread view — full conversation history with one client.
     *
     * The {emailEncoded} segment is base64url-encoded by the index view so the
     * route doesn't have to deal with '@' / '.' escaping. We aggregate every
     * lead with this email (across campaigns) and merge sent (OutreachSendLog)
     * + received (OutreachMessage) entries into a single chronological timeline.
     */
    public function inboxThread(Request $request, string $emailEncoded, \App\Outreach\Services\OutreachActivityLookup $lookup): View
    {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        $emailLower = strtolower($email);

        // A thread can hang off any combination of: outreach leads, a
        // Customer record, a Contact record, a Watched-email entry, OR
        // simply existing inbound messages with this from_email. We need
        // at least one of those for the URL to resolve — otherwise an
        // unknown email 404s.
        $leads = OutreachLead::with(['campaign', 'assignedEmailAccount'])
            ->whereRaw('LOWER(email) = ?', [$emailLower])
            ->get();
        $customer = Customer::whereRaw('LOWER(email) = ?', [$emailLower])->first();
        $contact  = Contact::whereRaw('LOWER(email) = ?', [$emailLower])->first();
        $watched  = OutreachWatchedEmail::where('email', $emailLower)->first();

        // Even without any of the above, the thread is still legitimate if
        // we have at least one inbound message stored for this address —
        // e.g. a watched entry was added, mail was imported, then the
        // watched row was deleted. Don't strand the imported history.
        $hasStoredMessages = OutreachMessage::whereRaw('LOWER(from_email) = ?', [$emailLower])->exists();

        abort_if(
            $leads->isEmpty() && ! $customer && ! $contact && ! $watched && ! $hasStoredMessages,
            404
        );

        $crmLink = ['customer' => $customer, 'contact' => $contact];

        $leadIds = $leads->pluck('id');

        // Derive additional attribution from inbound messages with this
        // from_email. Covers the case where a reply arrives from a different
        // address than the lead's primary (e.g. forwarded mail) — the lead
        // is then linked via OutreachMessage.lead_id rather than the email
        // string. Without this, outbound replies persisted with that lead_id
        // wouldn't surface in the thread view.
        $msgAttribution = OutreachMessage::whereRaw('LOWER(from_email) = ?', [$emailLower])
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->selectRaw('DISTINCT lead_id, customer_id, contact_id')
            ->get();

        $extraLeadIds = $msgAttribution->pluck('lead_id')->filter()->unique();
        if ($extraLeadIds->isNotEmpty()) {
            $leadIds = $leadIds->concat($extraLeadIds)->unique()->values();
            // Make sure the eager-loaded leads collection covers these too,
            // so any downstream rendering can read campaign/inbox metadata.
            $missingIds = $extraLeadIds->diff($leads->pluck('id'));
            if ($missingIds->isNotEmpty()) {
                $leads = $leads->concat(
                    OutreachLead::with(['campaign', 'assignedEmailAccount'])->whereIn('id', $missingIds)->get()
                );
            }
        }
        if (! $customer && ($cid = $msgAttribution->pluck('customer_id')->filter()->first())) {
            $customer = Customer::find($cid);
            $crmLink['customer'] = $customer;
        }
        if (! $contact && ($coid = $msgAttribution->pluck('contact_id')->filter()->first())) {
            $contact = Contact::find($coid);
            $crmLink['contact'] = $contact;
        }

        // Mark inbound messages read across every attribution channel — a
        // single thread may span lead-replies AND customer-direct mail.
        // The from_email branch catches watched-only messages where all
        // attribution FKs are null.
        OutreachMessage::query()
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->whereNull('read_at')
            ->where(function ($q) use ($leadIds, $customer, $contact, $emailLower) {
                if ($leadIds->isNotEmpty()) {
                    $q->orWhereIn('lead_id', $leadIds);
                }
                if ($customer) {
                    $q->orWhere('customer_id', $customer->id);
                }
                if ($contact) {
                    $q->orWhere('contact_id', $contact->id);
                }
                $q->orWhereRaw('LOWER(from_email) = ?', [$emailLower]);
            })
            ->update(['read_at' => now()]);

        $sends = $leadIds->isNotEmpty()
            ? OutreachSendLog::where('status', OutreachSendLog::STATUS_SENT)
                ->whereIn('lead_id', $leadIds)
                ->with(['emailAccount', 'campaign', 'campaignStep'])
                ->get()
            : collect();

        $messages = OutreachMessage::query()
            ->with(['emailAccount', 'lead.campaign'])
            ->where(function ($q) use ($leadIds, $customer, $contact, $emailLower) {
                if ($leadIds->isNotEmpty()) {
                    $q->orWhereIn('lead_id', $leadIds);
                }
                if ($customer) {
                    $q->orWhere('customer_id', $customer->id);
                }
                if ($contact) {
                    $q->orWhere('contact_id', $contact->id);
                }
                // Always include from_email matches so watched-only and
                // historical zero-attribution inbound rows surface.
                $q->orWhereRaw('LOWER(from_email) = ?', [$emailLower]);
            })
            ->get();

        // Tag each entry with a 'kind' so the view can render without
        // instanceof checks, and a unified 'occurred_at' for sorting.
        $timeline = collect()
            ->merge($sends->map(fn($s) => (object) [
                'kind'         => 'sent',
                'occurred_at'  => $s->sent_at ?? $s->created_at,
                'subject'      => $s->subject,
                // OutreachSendLog::$body is the rendered HTML message
                // (OutreachMailer dispatches it via Email::html()), so route
                // it through the HTML branch — the text branch escapes tags.
                'body_html'    => $s->body,
                'body_text'    => null,
                'from_email'   => $s->from_email,
                'from_name'    => $s->emailAccount?->name,
                'to_email'     => $s->to_email,
                'mailbox_name' => $s->emailAccount?->name ?? $s->emailAccount?->email,
                'campaign'     => $s->campaign?->name,
                'step_order'   => $s->step_order,
                'has_attachments' => false,
                'message_id'   => $s->message_id,
            ]))
            ->merge($messages->map(function ($m) use ($email) {
                $isOutbound = $m->direction === \App\Outreach\Models\OutreachMessage::DIRECTION_OUTBOUND;
                return (object) [
                    // 'received'  = client replied to us (inbound)
                    // 'crm_reply' = we sent a manual reply from the CRM (outbound)
                    'kind'         => $isOutbound ? 'crm_reply' : 'received',
                    'occurred_at'  => $m->received_at,
                    'subject'      => $m->subject,
                    'body_html'    => $m->body_html,
                    'body_text'    => $m->body_text,
                    'from_email'   => $m->from_email,
                    'from_name'    => $m->from_name,
                    // For outbound CRM replies the lead's email is the recipient,
                    // not the inbox we sent through.
                    'to_email'     => $isOutbound ? $email : $m->emailAccount?->email,
                    'mailbox_name' => $m->emailAccount?->name ?? $m->emailAccount?->email,
                    'campaign'     => $m->lead?->campaign?->name,
                    'step_order'   => null,
                    'has_attachments' => $m->has_attachments,
                    'message_id'   => $m->message_id,
                ];
            }))
            // Newest first — operator scans the most recent reply at the top
            // of the thread; older history scrolls below.
            ->sortByDesc(fn($e) => $e->occurred_at?->timestamp ?? 0)
            ->values();

        // Reuse the index data builder for the left rail, then overlay the
        // thread payload so the same Blade can render both panels.
        $shared = $this->buildInboxViewData($request, $email);

        $isArchived = OutreachArchivedThread::where('email_lower', strtolower($email))->exists();

        // Saved reply templates for the current operator — populates the
        // dropdown above the reply form so common responses can be picked
        // with one click instead of retyping.
        $replyTemplates = OutreachReplyTemplate::where('user_id', auth()->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'subject', 'body']);

        return view('outreach.inbox.thread', array_merge($shared, [
            'email'          => $email,
            'leads'          => $leads,
            'timeline'       => $timeline,
            'crmLink'        => $crmLink,
            'isArchived'     => $isArchived,
            'replyTemplates' => $replyTemplates,
        ]));
    }

    /**
     * Send a reply from the CRM (Variant A handoff).
     *
     * From: defaults to the primary reply account (e.g. veiko@webfight.ee). If
     * none has been configured the operator is asked to set one; we never
     * silently fall back to the cold-sending mailbox because that would
     * defeat the entire point of the handoff.
     *
     * Threading: In-Reply-To is the most recent inbound message's Message-ID
     * if available; otherwise the most recent outbound (CRM-sent) reply or
     * the original cold send. References is the prior thread's References
     * chain plus the In-Reply-To value, so Gmail keeps the conversation
     * stitched on both sides.
     */
    public function inboxReply(
        Request $request,
        string $emailEncoded,
        \App\Outreach\Services\OutreachMailer $mailer,
    ): RedirectResponse {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        $data = $request->validate([
            'subject' => 'required|string|max:500',
            'body'    => 'required|string',
        ]);

        $primary = OutreachEmailAccount::primaryReplyAccount();
        if (! $primary) {
            return back()->with('error', 'Lisa enne põhi-postkast (Postkastid → muuda → "Põhipostkast vastusteks").');
        }
        if (! $primary->is_active) {
            return back()->with('error', 'Põhipostkast on välja lülitatud — aktiveeri see enne vastamist.');
        }

        $emailLower = strtolower($email);

        // Look up every attribution channel for this email. The thread can
        // exist on a Lead, a Customer, or a Contact (or any combination).
        // We attribute the outbound message to as many of them as we find,
        // so it appears in the thread regardless of which lens is used.
        $lead = OutreachLead::whereRaw('LOWER(email) = ?', [$emailLower])
            ->orderByDesc('updated_at')
            ->first();
        $customer = Customer::whereRaw('LOWER(email) = ?', [$emailLower])->first();
        $contact  = Contact::whereRaw('LOWER(email) = ?', [$emailLower])->first();

        // Fallback: if direct email-based lookup found nothing, look at any
        // inbound OutreachMessage row whose from_email matches. This covers
        // forwarded replies (sender address ≠ lead's original address) and
        // watched-only threads where attribution was set on a message even
        // though the email isn't the lead's primary one.
        if (! $lead && ! $customer && ! $contact) {
            $derived = OutreachMessage::whereRaw('LOWER(from_email) = ?', [$emailLower])
                ->where('direction', OutreachMessage::DIRECTION_INBOUND)
                ->whereNotNull('id')
                ->orderByDesc('received_at')
                ->first();
            if ($derived) {
                if ($derived->lead_id)     $lead     = OutreachLead::find($derived->lead_id);
                if ($derived->customer_id) $customer = Customer::find($derived->customer_id);
                if ($derived->contact_id)  $contact  = Contact::find($derived->contact_id);
            }
        }

        // Allow the reply to proceed even when no CRM attribution exists at
        // all (pure watched-email thread). The OutreachMessage row keyed by
        // from_email still anchors the thread; abort only when there's no
        // inbound history whatsoever for this address.
        $hasInboundHistory = OutreachMessage::whereRaw('LOWER(from_email) = ?', [$emailLower])
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->exists();
        abort_if(! $lead && ! $customer && ! $contact && ! $hasInboundHistory, 404);

        // Build the In-Reply-To / References chain across all attribution
        // channels. Pick the newest prior message (lead inbound, lead outbound,
        // lead send log, customer inbound, customer outbound, contact inbound,
        // contact outbound) so threading stitches into the right conversation.
        $candidates = collect();

        $pushCandidate = function ($ts, $id, $refs) use ($candidates) {
            if ($id) {
                $candidates->push(['ts' => $ts, 'id' => $id, 'refs' => $refs]);
            }
        };

        if ($lead) {
            if ($m = OutreachMessage::where('lead_id', $lead->id)->where('direction', OutreachMessage::DIRECTION_INBOUND)->whereNotNull('message_id')->orderByDesc('received_at')->first()) {
                $pushCandidate($m->received_at, $m->message_id, $m->references_header);
            }
            if ($m = OutreachMessage::where('lead_id', $lead->id)->where('direction', OutreachMessage::DIRECTION_OUTBOUND)->whereNotNull('message_id')->orderByDesc('received_at')->first()) {
                $pushCandidate($m->received_at, $m->message_id, $m->references_header);
            }
            if ($s = OutreachSendLog::where('lead_id', $lead->id)->where('status', OutreachSendLog::STATUS_SENT)->whereNotNull('message_id')->orderByDesc('sent_at')->first()) {
                $pushCandidate($s->sent_at, $s->message_id, null);
            }
        }
        if ($customer) {
            if ($m = OutreachMessage::where('customer_id', $customer->id)->whereNotNull('message_id')->orderByDesc('received_at')->first()) {
                $pushCandidate($m->received_at, $m->message_id, $m->references_header);
            }
        }
        if ($contact) {
            if ($m = OutreachMessage::where('contact_id', $contact->id)->whereNotNull('message_id')->orderByDesc('received_at')->first()) {
                $pushCandidate($m->received_at, $m->message_id, $m->references_header);
            }
        }

        // from_email fallback for threading: if the prior lookups produced no
        // message_id (e.g. the inbound was forwarded from an address that has
        // no Lead/Customer/Contact match), anchor on the latest inbound that
        // matches the thread's address directly.
        if ($candidates->isEmpty()) {
            if ($m = OutreachMessage::whereRaw('LOWER(from_email) = ?', [$emailLower])
                ->where('direction', OutreachMessage::DIRECTION_INBOUND)
                ->whereNotNull('message_id')
                ->orderByDesc('received_at')
                ->first()
            ) {
                $pushCandidate($m->received_at, $m->message_id, $m->references_header);
            }
        }

        $best = $candidates->sortByDesc('ts')->first();
        $inReplyTo  = $best['id']   ?? null;
        $priorRefs  = $best['refs'] ?? null;
        $references = trim(($priorRefs ?? '') . ' ' . ($inReplyTo ? '<' . trim($inReplyTo, '<>') . '>' : ''));

        // Display name precedence: lead > customer > contact
        $contactName = trim(
            ($lead?->first_name ?? $customer?->first_name ?? $contact?->first_name ?? '') . ' ' .
            ($lead?->last_name  ?? $customer?->last_name  ?? $contact?->last_name  ?? '')
        );

        try {
            $sentMessageId = $mailer->send(
                account:    $primary,
                toEmail:    $email,
                toName:     $contactName !== '' ? $contactName : $email,
                subject:    $data['subject'],
                htmlBody:   nl2br(e($data['body'])),
                inReplyTo:  $inReplyTo,
                references: $references !== '' ? $references : null,
            );
        } catch (\Throwable $e) {
            \Log::error('[Outreach] CRM reply send failed', [
                'lead_id'     => $lead?->id,
                'customer_id' => $customer?->id,
                'contact_id'  => $contact?->id,
                'to'          => $email,
                'error'       => $e->getMessage(),
            ]);
            return back()->with('error', 'Saatmine ebaõnnestus: ' . $e->getMessage());
        }

        // Persist outbound message with every attribution channel set so
        // the message surfaces in the thread regardless of which lens.
        OutreachMessage::create([
            'lead_id'           => $lead?->id,
            'customer_id'       => $customer?->id,
            'contact_id'        => $contact?->id,
            'email_account_id'  => $primary->id,
            'direction'         => OutreachMessage::DIRECTION_OUTBOUND,
            'message_id'        => $sentMessageId,
            'in_reply_to'       => $inReplyTo,
            'references_header' => $references !== '' ? $references : null,
            'from_email'        => $primary->email,
            'from_name'         => $primary->name,
            'subject'           => $data['subject'],
            'body_text'         => $data['body'],
            'body_html'         => null,
            'has_attachments'   => false,
            'received_at'       => now(),
            'imap_uid'          => null,
        ]);

        return redirect()
            ->route('outreach.inbox.thread', $emailEncoded)
            ->with('success', 'Vastus saadetud.');
    }

    /**
     * Update display name (and lead-side company) for every record linked
     * to a given inbox email. The same change is mirrored to every Lead with
     * this email, plus the matching Customer and Contact records, so the
     * inbox, campaign view, and customer profile all show the same name.
     *
     * Why this lives in the inbox controller: many leads start out with a
     * placeholder name like "Friend" because the operator doesn't yet know
     * who replied. Once a real reply arrives, the inbox is the natural
     * place to fix the name without context-switching to the campaign UI.
     *
     * Customer/Contact company_id is an FK to Companies and is intentionally
     * NOT edited here — that needs a company picker. Lead.company is a free
     * text field so we update it inline.
     */
    public function inboxUpdateContact(Request $request, string $emailEncoded): RedirectResponse
    {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        $data = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'company'    => 'nullable|string|max:255',
        ]);

        $emailLower = strtolower($email);

        // Update every Lead that shares this email — usually one, but some
        // contacts are enrolled across multiple campaigns under the same address.
        $leadFields = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name']  ?? null,
            'company'    => $data['company']    ?? null,
        ], fn($v) => $v !== null);

        if (! empty($leadFields)) {
            OutreachLead::whereRaw('LOWER(email) = ?', [$emailLower])->update($leadFields);
        }

        // Mirror name fields to Customer / Contact when they exist. Skip
        // the company field — that lives behind an FK and is edited on the
        // customer/contact's own page.
        $crmFields = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name']  ?? null,
        ], fn($v) => $v !== null);

        if (! empty($crmFields)) {
            Customer::whereRaw('LOWER(email) = ?', [$emailLower])->update($crmFields);
            Contact::whereRaw('LOWER(email) = ?', [$emailLower])->update($crmFields);
        }

        return redirect()
            ->route('outreach.inbox.thread', $emailEncoded)
            ->with('success', 'Kontakti andmed uuendatud.');
    }

    /**
     * Archive an inbox thread (per from-email). Hides the thread from the
     * default inbox views; user can find it back via the "Arhiveeritud"
     * filter. A new inbound message auto-unarchives the thread (handled in
     * ReplyDetectionService::persistMessage).
     */
    public function inboxArchive(Request $request, string $emailEncoded): RedirectResponse
    {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        OutreachArchivedThread::updateOrCreate(
            ['email_lower' => strtolower($email)],
            [
                'archived_at'         => now(),
                'archived_by_user_id' => auth()->id(),
            ]
        );

        return redirect()
            ->route('outreach.inbox.index')
            ->with('success', 'Vestlus arhiveeritud.');
    }

    /**
     * Restore a previously-archived inbox thread to the regular inbox.
     */
    public function inboxUnarchive(Request $request, string $emailEncoded): RedirectResponse
    {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        OutreachArchivedThread::where('email_lower', strtolower($email))->delete();

        return redirect()
            ->route('outreach.inbox.thread', $emailEncoded)
            ->with('success', 'Vestlus tagasi inbox-i.');
    }

    // ─── Watched emails (manual inbox allowlist) ────────────────────────────

    /**
     * Add an email address to the inbox watch list. The address is then
     * picked up by ReplyDetectionService::detectCrmContacts() on every
     * subsequent poll. A 30-day backfill scan runs synchronously here so
     * the operator sees existing mail immediately.
     *
     * Idempotent: a duplicate POST quietly re-uses the existing row.
     */
    public function watchedStore(Request $request, ReplyDetectionService $detector): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'label' => 'nullable|string|max:200',
        ]);

        $email = strtolower(trim($data['email']));

        $watched = OutreachWatchedEmail::firstOrCreate(
            ['email' => $email],
            [
                'label'              => $data['label'] ?? null,
                'created_by_user_id' => auth()->id(),
            ],
        );

        // Allow updating the label on a re-add without forcing the user to
        // delete + recreate.
        if (! $watched->wasRecentlyCreated && array_key_exists('label', $data)) {
            $watched->update(['label' => $data['label']]);
        }

        // Backfill is best-effort — IMAP can be flaky and we don't want to
        // present an error page to the user just because one mailbox timed
        // out. Errors are already logged inside the service.
        $detected = 0;
        try {
            $detected = $detector->scanSingleEmail($email);
        } catch (\Throwable $e) {
            report($e);
        }

        $msg = $watched->wasRecentlyCreated
            ? "Aadress lisatud jälgimisele." . ($detected > 0 ? " Leitud {$detected} olemasolevat kirja." : "")
            : "Aadress oli juba jälgimisel — silt uuendatud.";

        return redirect()
            ->route('outreach.inbox.index', ['filter' => 'watched'])
            ->with('success', $msg);
    }

    /**
     * Remove an email address from the watch list. Already-imported
     * messages for that address are intentionally preserved — the inbox
     * thread remains accessible via the regular thread URL and can be
     * archived if no longer wanted.
     */
    public function watchedDestroy(OutreachWatchedEmail $watched): RedirectResponse
    {
        $watched->delete();

        return redirect()
            ->route('outreach.inbox.index')
            ->with('success', 'Aadress eemaldatud jälgimisest.');
    }

    // ─── Reply templates (saved snippets) ───────────────────────────────────

    /**
     * Manage saved reply snippets. Per-user list — each operator sees and
     * edits only their own. The thread page reads from the same set when
     * building the "Vali mall" dropdown above the reply form.
     */
    public function replyTemplatesIndex(): View
    {
        $templates = OutreachReplyTemplate::where('user_id', auth()->id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('outreach.reply-templates.index', compact('templates'));
    }

    public function replyTemplatesStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'subject'    => 'nullable|string|max:500',
            'body'       => 'required|string|max:10000',
            'sort_order' => 'nullable|integer',
        ]);

        OutreachReplyTemplate::create([
            'user_id'    => auth()->id(),
            'name'       => $data['name'],
            'subject'    => $data['subject'] ?? null,
            'body'       => $data['body'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()->route('outreach.reply-templates.index')
            ->with('success', 'Mall salvestatud.');
    }

    public function replyTemplatesUpdate(Request $request, OutreachReplyTemplate $template): RedirectResponse
    {
        abort_if($template->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'subject'    => 'nullable|string|max:500',
            'body'       => 'required|string|max:10000',
            'sort_order' => 'nullable|integer',
        ]);

        $template->update([
            'name'       => $data['name'],
            'subject'    => $data['subject'] ?? null,
            'body'       => $data['body'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()->route('outreach.reply-templates.index')
            ->with('success', 'Mall uuendatud.');
    }

    public function replyTemplatesDestroy(OutreachReplyTemplate $template): RedirectResponse
    {
        abort_if($template->user_id !== auth()->id(), 403);
        $template->delete();

        return redirect()->route('outreach.reply-templates.index')
            ->with('success', 'Mall kustutatud.');
    }

    /**
     * URL-safe email decoding shared with the index view's encoder.
     * Returns null if the input is not a valid email address.
     */
    private function decodeEmail(string $encoded): ?string
    {
        $padding = strlen($encoded) % 4;
        if ($padding) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false || ! filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $decoded;
    }
}
