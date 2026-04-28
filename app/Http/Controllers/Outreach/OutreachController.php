<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Outreach\Jobs\CheckOutreachRepliesJob;
use App\Outreach\Jobs\ProcessOutreachLeadsJob;
use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachCampaignStep;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachSendLog;
use App\Outreach\Services\OutreachCsvImportService;
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
            'campaigns'     => OutreachCampaign::count(),
            'active_leads'  => OutreachLead::where('status', 'active')->count(),
            'replied'       => OutreachLead::where('replied', true)->count(),
            'completed'     => OutreachLead::where('status', 'completed')->count(),
            'sent_today'    => OutreachSendLog::where('status', 'sent')
                                  ->whereDate('sent_at', today())->count(),
            'failed_today'  => OutreachSendLog::where('status', 'failed')
                                  ->whereDate('created_at', today())->count(),
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
            'provider'                 => 'required|in:gmail,smtp,outlook',
            'smtp_host'                => 'required|string',
            'smtp_port'                => 'required|integer|between:1,65535',
            'smtp_username'            => 'required|string',
            'smtp_password'            => 'required|string',
            'smtp_encryption'          => 'required|in:tls,ssl,none',
            'imap_host'                => 'nullable|string',
            'imap_port'                => 'nullable|integer|between:1,65535',
            'imap_username'            => 'nullable|string',
            'imap_password'            => 'nullable|string',
            'imap_encryption'          => 'nullable|in:ssl,tls,none',
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
            'smtp_host'                => 'required|string',
            'smtp_port'                => 'required|integer|between:1,65535',
            'smtp_username'            => 'required|string',
            'smtp_password'            => 'nullable|string',   // Optional — leave blank to keep current
            'smtp_encryption'          => 'required|in:tls,ssl,none',
            'imap_host'                => 'nullable|string',
            'imap_port'                => 'nullable|integer|between:1,65535',
            'imap_username'            => 'nullable|string',
            'imap_password'            => 'nullable|string',
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

    public function leadsIndex(OutreachCampaign $campaign): View
    {
        $leads = $campaign->leads()
            ->with('assignedEmailAccount')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('outreach.leads.index', compact('campaign', 'leads'));
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
        $search = trim((string) $request->query('q', ''));

        $query = OutreachMessage::query()
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->selectRaw('LOWER(from_email) as group_email')
            ->selectRaw('MAX(received_at) as last_received_at')
            ->selectRaw('COUNT(*) as reply_count')
            ->selectRaw('MAX(from_name) as display_name')
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

        $threads = $query->paginate(30)->withQueryString();

        // Annotate each row with related-lead summary so the index can show
        // company, name, and campaign badges without N+1 per render.
        $emails = $threads->getCollection()->pluck('group_email')->all();

        $leadIndex = OutreachLead::with('campaign')
            ->whereIn(\DB::raw('LOWER(email)'), $emails)
            ->get()
            ->groupBy(fn($l) => strtolower($l->email));

        $threads->getCollection()->transform(function ($row) use ($leadIndex) {
            $leads = $leadIndex->get($row->group_email, collect());
            $first = $leads->first();
            $row->lead_first_name = $first?->first_name;
            $row->lead_last_name  = $first?->last_name;
            $row->lead_company    = $first?->company;
            $row->campaigns       = $leads->pluck('campaign.name')->filter()->unique()->values();
            $row->lead_count      = $leads->count();
            return $row;
        });

        return view('outreach.inbox.index', [
            'threads' => $threads,
            'search'  => $search,
        ]);
    }

    /**
     * Inbox thread view — full conversation history with one client.
     *
     * The {emailEncoded} segment is base64url-encoded by the index view so the
     * route doesn't have to deal with '@' / '.' escaping. We aggregate every
     * lead with this email (across campaigns) and merge sent (OutreachSendLog)
     * + received (OutreachMessage) entries into a single chronological timeline.
     */
    public function inboxThread(string $emailEncoded): View
    {
        $email = $this->decodeEmail($emailEncoded);
        abort_if($email === null, 404);

        $leads = OutreachLead::with(['campaign', 'assignedEmailAccount'])
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->get();

        abort_if($leads->isEmpty(), 404);

        $leadIds = $leads->pluck('id');

        $sends = OutreachSendLog::where('status', OutreachSendLog::STATUS_SENT)
            ->whereIn('lead_id', $leadIds)
            ->with(['emailAccount', 'campaign', 'campaignStep'])
            ->get();

        $messages = OutreachMessage::whereIn('lead_id', $leadIds)
            ->with(['emailAccount', 'lead.campaign'])
            ->get();

        // Tag each entry with a 'kind' so the view can render without
        // instanceof checks, and a unified 'occurred_at' for sorting.
        $timeline = collect()
            ->merge($sends->map(fn($s) => (object) [
                'kind'         => 'sent',
                'occurred_at'  => $s->sent_at ?? $s->created_at,
                'subject'      => $s->subject,
                'body_html'    => null,
                'body_text'    => $s->body,
                'from_email'   => $s->from_email,
                'from_name'    => $s->emailAccount?->name,
                'to_email'     => $s->to_email,
                'mailbox_name' => $s->emailAccount?->name ?? $s->emailAccount?->email,
                'campaign'     => $s->campaign?->name,
                'step_order'   => $s->step_order,
                'has_attachments' => false,
                'message_id'   => $s->message_id,
            ]))
            ->merge($messages->map(fn($m) => (object) [
                'kind'         => 'received',
                'occurred_at'  => $m->received_at,
                'subject'      => $m->subject,
                'body_html'    => $m->body_html,
                'body_text'    => $m->body_text,
                'from_email'   => $m->from_email,
                'from_name'    => $m->from_name,
                'to_email'     => $m->emailAccount?->email,
                'mailbox_name' => $m->emailAccount?->name ?? $m->emailAccount?->email,
                'campaign'     => $m->lead?->campaign?->name,
                'step_order'   => null,
                'has_attachments' => $m->has_attachments,
                'message_id'   => $m->message_id,
            ]))
            ->sortBy(fn($e) => $e->occurred_at?->timestamp ?? 0)
            ->values();

        return view('outreach.inbox.thread', [
            'email'    => $email,
            'leads'    => $leads,
            'timeline' => $timeline,
        ]);
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

        // Pick a representative lead for this contact so we can attribute the
        // outbound message and audit-log it. If the same email exists across
        // multiple campaigns, we pick the most recently active one so the
        // thread reads as a continuation of the latest conversation.
        $lead = OutreachLead::whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->orderByDesc('updated_at')
            ->first();
        abort_if(! $lead, 404);

        // Build the In-Reply-To / References chain from the existing thread.
        // Pull the most recent inbound message and the most recent outbound
        // send log; whichever is newer becomes In-Reply-To.
        $lastInbound = OutreachMessage::where('lead_id', $lead->id)
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->whereNotNull('message_id')
            ->orderByDesc('received_at')
            ->first();

        $lastSend = OutreachSendLog::where('lead_id', $lead->id)
            ->where('status', OutreachSendLog::STATUS_SENT)
            ->whereNotNull('message_id')
            ->orderByDesc('sent_at')
            ->first();

        $lastOutboundReply = OutreachMessage::where('lead_id', $lead->id)
            ->where('direction', OutreachMessage::DIRECTION_OUTBOUND)
            ->whereNotNull('message_id')
            ->orderByDesc('received_at')
            ->first();

        // Pick the most recent prior message (any direction) as In-Reply-To
        $candidates = collect([
            $lastInbound  ? ['ts' => $lastInbound->received_at,  'id' => $lastInbound->message_id,         'refs' => $lastInbound->references_header] : null,
            $lastOutboundReply ? ['ts' => $lastOutboundReply->received_at, 'id' => $lastOutboundReply->message_id, 'refs' => $lastOutboundReply->references_header] : null,
            $lastSend     ? ['ts' => $lastSend->sent_at,         'id' => $lastSend->message_id,            'refs' => null] : null,
        ])->filter()->sortByDesc('ts')->values();

        $inReplyTo = $candidates->first()['id'] ?? null;
        // References = prior chain + the message we are replying to.
        // If no prior References header, References = just the In-Reply-To.
        $priorRefs = $candidates->first()['refs'] ?? null;
        $references = trim(($priorRefs ?? '') . ' ' . ($inReplyTo ? '<' . trim($inReplyTo, '<>') . '>' : ''));

        $contactName = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));

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
                'lead_id' => $lead->id,
                'to'      => $email,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'Saatmine ebaõnnestus: ' . $e->getMessage());
        }

        // Persist our outbound message so it shows up in the thread timeline
        // and so future client replies can match against this Message-ID.
        OutreachMessage::create([
            'lead_id'           => $lead->id,
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
