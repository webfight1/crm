<?php

namespace App\Http\Controllers\Meriti;

use App\Http\Controllers\Controller;
use App\Models\MeritCustomerEmail;
use App\Models\MeritReminderLog;
use App\Models\MeritReminderSetting;
use App\Services\Merit\MeritClient;
use App\Services\Merit\OverdueReminderService;
use Illuminate\Http\Request;

/**
 * Meriti võlgnike meeldetuletuste moodul — töölaud, seaded, ajalugu ja
 * käsitsi saatmine. Nähtav ka outreach-only (KIND) režiimis.
 */
class MeritReminderController extends Controller
{
    public function index(OverdueReminderService $service, MeritClient $client)
    {
        $settings = MeritReminderSetting::getSettings();
        $connection = $client->testConnection();

        $plan = collect();
        $error = null;

        if ($connection['ok']) {
            try {
                $plan = $service->previewPlan($settings);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('meriti.index', compact('settings', 'connection', 'plan', 'error'));
    }

    public function settings()
    {
        $settings = MeritReminderSetting::getSettings();

        return view('meriti.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $settings = MeritReminderSetting::getSettings();

        $validated = $request->validate([
            'enabled'           => 'nullable|boolean',
            'company_name'      => 'nullable|string|max:255',
            'handoff_recipient' => 'nullable|email|max:255',
            'send_hour'         => 'required|integer|min:0|max:23',
            'notify_step'       => 'required|integer|min:1|max:4',
            'attach_from_step'  => 'required|integer|min:1|max:5',
            'from_name'         => 'nullable|string|max:255',
            'from_email'        => 'nullable|email|max:255',
            'test_recipient'    => 'nullable|email|max:255',

            'step1_days' => 'required|integer|min:0|max:365',
            'step2_days' => 'required|integer|min:0|max:365',
            'step3_days' => 'required|integer|min:0|max:365',
            'step4_days' => 'required|integer|min:0|max:365',

            'step1_subject' => 'nullable|string|max:500', 'step1_body' => 'nullable|string',
            'step2_subject' => 'nullable|string|max:500', 'step2_body' => 'nullable|string',
            'step3_subject' => 'nullable|string|max:500', 'step3_body' => 'nullable|string',
            'step4_subject' => 'nullable|string|max:500', 'step4_body' => 'nullable|string',
        ]);

        $validated['enabled'] = $request->boolean('enabled');

        $settings->update($validated);

        return redirect()->route('meriti.settings')->with('success', __('Seaded on salvestatud!'));
    }

    public function sendNow(OverdueReminderService $service)
    {
        try {
            $result = $service->sendReminders(false);
        } catch (\Throwable $e) {
            return redirect()->route('meriti.index')->with('error', __('Saatmine ebaõnnestus: ') . $e->getMessage());
        }

        return redirect()->route('meriti.index')->with('success', __(
            'Saadetud: :sent, vahele jäetud: :skipped, ebaõnnestus: :failed, teavitusi Mariusele: :notified.',
            ['sent' => $result['sent'], 'skipped' => $result['skipped'], 'failed' => $result['failed'], 'notified' => $result['notified'] ?? 0]
        ));
    }

    public function logs()
    {
        $logs = MeritReminderLog::latest('id')->paginate(50);

        return view('meriti.logs', compact('logs'));
    }

    /** Kliendi e-postide haldus — käsitsi lisamine, kui Meritis puudub. */
    public function emails(OverdueReminderService $service, MeritClient $client)
    {
        $connection = $client->testConnection();
        $debtors = collect();
        $error = null;

        if ($connection['ok']) {
            try {
                $debtors = $service->collectDebtors()
                    ->sortByDesc(fn ($d) => $d->totalUnpaid)
                    ->values();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $overrides = MeritCustomerEmail::pluck('email', 'merit_customer_id');

        return view('meriti.emails', compact('debtors', 'overrides', 'connection', 'error'));
    }

    public function emailsStore(Request $request)
    {
        $request->validate([
            'emails'   => 'array',
            'emails.*' => 'nullable|email|max:255',
        ]);

        foreach ((array) $request->input('emails', []) as $cid => $email) {
            $email = trim((string) $email);
            $cid = (string) $cid;
            if ($cid === '') {
                continue;
            }

            if ($email === '') {
                MeritCustomerEmail::where('merit_customer_id', $cid)->delete();
            } else {
                MeritCustomerEmail::updateOrCreate(
                    ['merit_customer_id' => $cid],
                    ['email' => $email, 'customer_name' => $request->input("names.$cid")]
                );
            }
        }

        return redirect()->route('meriti.emails')->with('success', __('E-postid on salvestatud!'));
    }
}
