<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailLogController extends Controller
{
    public function index()
    {
        $logs = EmailLog::where('user_id', Auth::id())
            ->with('emailCampaign')
            ->orderBy('sent_at', 'desc')
            ->paginate(50);

        $cooldownEmails = EmailLog::getEmailsInCooldown(Auth::id());
        
        return view('email-logs.index', compact('logs', 'cooldownEmails'));
    }

    public function cooldownStatus(Request $request)
    {
        $email = $request->get('email');
        
        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        $isInCooldown = EmailLog::isInCooldown($email, Auth::id());
        $cooldownDays = env('EMAIL_COOLDOWN_DAYS', 14);
        
        return response()->json([
            'email' => $email,
            'in_cooldown' => $isInCooldown,
            'cooldown_days' => $cooldownDays,
            'message' => $isInCooldown 
                ? "E-mail on cooldown perioodis ({$cooldownDays} päeva)"
                : "E-mail on saatmiseks valmis"
        ]);
    }
}
