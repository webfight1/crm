<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\Customer;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailCampaignController extends Controller
{
    public function index()
    {
        $campaigns = EmailCampaign::forUser(Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total_sent' => EmailCampaign::forUser(Auth::id())->successful()->count(),
            'total_failed' => EmailCampaign::forUser(Auth::id())->failed()->count(),
            'recent_campaigns' => EmailCampaign::forUser(Auth::id())
                ->whereDate('created_at', today())
                ->count(),
        ];

        return view('email-campaigns.index', compact('campaigns', 'stats'));
    }

    public function create()
    {
        $customers = Customer::where('user_id', Auth::id())->get();
        $companies = Company::where('user_id', Auth::id())->get();
        
        return view('email-campaigns.create', compact('customers', 'companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'email_column' => 'required|string',
            'name_column' => 'nullable|string',
            'subject' => 'required|string|max:255',
            'subject_ru' => 'nullable|string|max:255',
            'message' => 'required|string',
            'message_ru' => 'nullable|string',
        ]);

        // Store CSV file
        $csvFile = $request->file('csv_file');
        $filename = time() . '_' . $csvFile->getClientOriginalName();
        $csvPath = $csvFile->storeAs('email-campaigns', $filename, 'local');

        // Process CSV file
        $csvData = $this->processCsvFile(storage_path('app/' . $csvPath), $request->email_column, $request->name_column);

        // Limit to 5000 emails as per original script
        if (count($csvData) > 5000) {
            $csvData = array_slice($csvData, 0, 5000);
        }

        $campaignIds = [];

        // Create campaign records for each email
        foreach ($csvData as $row) {
            $campaign = EmailCampaign::create([
                'user_id' => Auth::id(),
                'subject' => $request->subject,
                'subject_ru' => $request->subject_ru,
                'message' => $request->message,
                'message_ru' => $request->message_ru,
                'recipient_email' => $row['email'],
                'recipient_name' => $row['name'] ?? null,
                'company_name' => $row['name'] ?? null,
                'csv_filename' => $filename,
                'status' => 'pending',
            ]);

            $campaignIds[] = $campaign->id;
        }

        // Start background email sending process
        $this->startEmailSending($campaignIds);

        return redirect()->route('email-campaigns.index')
            ->with('success', 'Email kampaania alustatud! Saadetakse ' . count($csvData) . ' kirja.');
    }

    public function show(EmailCampaign $emailCampaign)
    {
        $this->authorize('view', $emailCampaign);

        return view('email-campaigns.show', compact('emailCampaign'));
    }

    public function progress()
    {
        $userId = Auth::id();
        
        $total = EmailCampaign::forUser($userId)->count();
        $sent = EmailCampaign::forUser($userId)->where('status', 'sent')->count();
        $failed = EmailCampaign::forUser($userId)->where('status', 'failed')->count();
        $pending = EmailCampaign::forUser($userId)->where('status', 'pending')->count();

        $status = $pending > 0 ? 'running' : 'completed';

        return response()->json([
            'status' => $status,
            'total' => $total,
            'current' => $sent + $failed,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'message' => $pending > 0 ? "Saatmine käib... {$sent} saadetud, {$failed} ebaõnnestunud" : "Saatmine lõpetatud",
            'nextSendIn' => $pending > 0 ? 7 : 0, // 7 second delay as in original
        ]);
    }

    private function processCsvFile($filePath, $emailColumn, $nameColumn = null)
    {
        $data = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            
            $emailIndex = array_search($emailColumn, $header);
            $nameIndex = $nameColumn ? array_search($nameColumn, $header) : false;
            
            if ($emailIndex === false) {
                throw new \Exception("Email column '{$emailColumn}' not found in CSV");
            }
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (isset($row[$emailIndex]) && filter_var($row[$emailIndex], FILTER_VALIDATE_EMAIL)) {
                    $data[] = [
                        'email' => $row[$emailIndex],
                        'name' => ($nameIndex !== false && isset($row[$nameIndex])) ? $row[$nameIndex] : null,
                    ];
                }
            }
            fclose($handle);
        }
        
        return $data;
    }

    private function startEmailSending($campaignIds)
    {
        // In a real application, you would dispatch this to a queue
        // For now, we'll use a simple approach similar to the original script
        
        foreach ($campaignIds as $campaignId) {
            try {
                $campaign = EmailCampaign::find($campaignId);
                
                if (!$campaign || $campaign->status !== 'pending') {
                    continue;
                }

                // Determine subject and message based on email domain
                $isRussian = str_ends_with($campaign->recipient_email, '.ru');
                $subject = $isRussian && $campaign->subject_ru ? $campaign->subject_ru : $campaign->subject;
                $message = $isRussian && $campaign->message_ru ? $campaign->message_ru : $campaign->message;

                // Replace placeholders
                $message = str_replace('{company_name}', $campaign->company_name ?? '', $message);

                // Send email (you'll need to configure mail settings)
                Mail::raw($message, function ($mail) use ($campaign, $subject) {
                    $mail->to($campaign->recipient_email)
                         ->subject($subject);
                });

                // Update campaign status
                $campaign->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                // Add delay between emails (7 seconds as in original)
                sleep(7);

            } catch (\Exception $e) {
                // Log error and mark as failed
                Log::error('Email sending failed', [
                    'campaign_id' => $campaignId,
                    'error' => $e->getMessage()
                ]);

                if (isset($campaign)) {
                    $campaign->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
