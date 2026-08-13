<?php

namespace App\Http\Controllers\Meriti;

use App\Http\Controllers\Controller;
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
            'enabled'          => 'nullable|boolean',
            'min_overdue_days' => 'required|integer|min:0|max:365',
            'min_days_between' => 'required|integer|min:0|max:365',
            'send_hour'        => 'required|integer|min:0|max:23',
            'from_name'        => 'nullable|string|max:255',
            'from_email'       => 'nullable|email|max:255',
            'attach_pdfs'      => 'nullable|boolean',
            'max_attachments'  => 'required|integer|min:1|max:50',
            'test_recipient'   => 'nullable|email|max:255',

            'step1_enabled' => 'nullable|boolean',
            'step1_days'    => 'required|integer|min:0|max:365',
            'step1_subject' => 'nullable|string|max:500',
            'step1_body'    => 'nullable|string',

            'step2_enabled' => 'nullable|boolean',
            'step2_days'    => 'required|integer|min:0|max:365',
            'step2_subject' => 'nullable|string|max:500',
            'step2_body'    => 'nullable|string',

            'step3_enabled' => 'nullable|boolean',
            'step3_days'    => 'required|integer|min:0|max:365',
            'step3_subject' => 'nullable|string|max:500',
            'step3_body'    => 'nullable|string',
        ]);

        // Märkeruudud: puuduv väärtus = false.
        foreach (['enabled', 'step1_enabled', 'step2_enabled', 'step3_enabled', 'attach_pdfs'] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

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
            'Saadetud: :sent, vahele jäetud: :skipped, ebaõnnestus: :failed.',
            ['sent' => $result['sent'], 'skipped' => $result['skipped'], 'failed' => $result['failed']]
        ));
    }

    public function logs()
    {
        $logs = MeritReminderLog::latest('id')->paginate(50);

        return view('meriti.logs', compact('logs'));
    }
}
