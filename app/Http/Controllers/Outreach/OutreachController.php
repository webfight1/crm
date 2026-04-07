<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Outreach\Jobs\CheckOutreachRepliesJob;
use App\Outreach\Jobs\ProcessOutreachLeadsJob;
use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachCampaignStep;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachSendLog;
use App\Outreach\Services\OutreachCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'email'           => 'required|email|unique:outreach_email_accounts,email',
            'provider'        => 'required|in:gmail,smtp,outlook',
            'smtp_host'       => 'required|string',
            'smtp_port'       => 'required|integer|between:1,65535',
            'smtp_username'   => 'required|string',
            'smtp_password'   => 'required|string',
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'imap_host'       => 'nullable|string',
            'imap_port'       => 'nullable|integer|between:1,65535',
            'imap_username'   => 'nullable|string',
            'imap_password'   => 'nullable|string',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'daily_limit'     => 'required|integer|min:1|max:500',
            'is_active'       => 'boolean',
        ]);

        OutreachEmailAccount::create($data);

        return redirect()->route('outreach.accounts.index')
                         ->with('success', 'Email account added.');
    }

    public function accountsEdit(OutreachEmailAccount $account): View
    {
        return view('outreach.accounts.edit', compact('account'));
    }

    public function accountsUpdate(Request $request, OutreachEmailAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'smtp_host'       => 'required|string',
            'smtp_port'       => 'required|integer|between:1,65535',
            'smtp_username'   => 'required|string',
            'smtp_password'   => 'nullable|string',   // Optional — leave blank to keep current
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'imap_host'       => 'nullable|string',
            'imap_port'       => 'nullable|integer|between:1,65535',
            'imap_username'   => 'nullable|string',
            'imap_password'   => 'nullable|string',
            'daily_limit'     => 'required|integer|min:1|max:500',
            'is_active'       => 'boolean',
        ]);

        // Don't overwrite encrypted passwords with null when field is left blank
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }
        if (empty($data['imap_password'])) {
            unset($data['imap_password']);
        }

        $account->update($data);

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
        $data = $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
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
            $count = $importer->import(storage_path("app/{$path}"), $campaign->id);
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['csv_file' => $e->getMessage()]);
        } finally {
            // Always clean up the temp file
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        }

        return redirect()
            ->route('outreach.campaigns.leads.index', $campaign)
            ->with('success', "Imporditi {$count} leadi.");
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
}
