<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailLogController extends Controller
{
    public function index()
    {
        $logs = EmailLog::with('emailCampaign')
            ->orderBy('sent_at', 'desc')
            ->paginate(50);

        $cooldownEmails = EmailLog::getEmailsInCooldown();
        
        return view('email-logs.index', compact('logs', 'cooldownEmails'));
    }

    public function cooldownStatus(Request $request)
    {
        $email = $request->get('email');
        
        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        $cooldownDays = env('EMAIL_COOLDOWN_DAYS', 14);
        
        // Check if any emails have been sent at all
        if (EmailLog::count() === 0) {
            return response()->json([
                'email' => $email,
                'in_cooldown' => false,
                'cooldown_days' => $cooldownDays,
                'message' => 'E-mail on saatmiseks valmis (pole ühtegi saatmist veel)',
                'has_logs' => false
            ]);
        }

        $isInCooldown = EmailLog::isInCooldown($email);
        
        return response()->json([
            'email' => $email,
            'in_cooldown' => $isInCooldown,
            'cooldown_days' => $cooldownDays,
            'message' => $isInCooldown 
                ? "E-mail on cooldown perioodis ({$cooldownDays} päeva)"
                : "E-mail on saatmiseks valmis",
            'has_logs' => true
        ]);
    }
}
