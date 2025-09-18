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
        // Zone.eu API seaded
        $zoneApiUrl = env('ZONE_EMAIL_API_URL', 'https://your-zone-domain.ee/api/email_sender_api.php');
        $zoneApiToken = env('ZONE_EMAIL_API_TOKEN', 'your-secure-api-token-here-change-this');
        
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

                // Prepare data for Zone API
                $apiData = [
                    'api_token' => $zoneApiToken,
                    'recipient_email' => $campaign->recipient_email,
                    'subject' => $subject,
                    'message' => $message,
                    'company_name' => $campaign->company_name,
                    'recipient_name' => $campaign->recipient_name,
                ];

                // Send request to Zone.eu API
                $response = $this->sendToZoneApi($zoneApiUrl, $apiData);

                if ($response && $response['success']) {
                    // Update campaign status as sent
                    $campaign->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    
                    Log::info('Email sent successfully via Zone API', [
                        'campaign_id' => $campaignId,
                        'recipient' => $campaign->recipient_email
                    ]);
                } else {
                    throw new \Exception($response['error'] ?? 'Unknown API error');
                }

                // Add delay between emails (7 seconds as in original)
                sleep(7);

            } catch (\Exception $e) {
                // Log error and mark as failed
                Log::error('Email sending failed via Zone API', [
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

    private function sendToZoneApi($apiUrl, $data)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Laravel CRM System'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: $httpCode");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from Zone API");
        }
        
        return $decoded;
    }
}
