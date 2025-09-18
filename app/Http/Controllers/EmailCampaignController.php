<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignBatch;
use App\Models\Customer;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EmailCampaignController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $batches = EmailCampaignBatch::forUser(Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total_sent' => EmailCampaign::forUser(Auth::id())->successful()->count(),
            'total_failed' => EmailCampaign::forUser(Auth::id())->failed()->count(),
            'pending' => EmailCampaign::forUser(Auth::id())->where('status', 'pending')->count(),
            'recent_batches' => EmailCampaignBatch::forUser(Auth::id())
                ->whereDate('created_at', today())
                ->count(),
        ];

        return view('email-campaigns.index', compact('batches', 'stats'));
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
            'campaign_name' => 'required|string|max:255',
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
        
        if (!$csvFile) {
            return redirect()->back()->withErrors(['csv_file' => 'CSV file is required']);
        }
        
        if (!$csvFile->isValid()) {
            return redirect()->back()->withErrors(['csv_file' => 'CSV file upload failed']);
        }
        
        $filename = time() . '_' . $csvFile->getClientOriginalName();
        
        Log::info('Attempting to store CSV file', [
            'original_name' => $csvFile->getClientOriginalName(),
            'filename' => $filename,
            'size' => $csvFile->getSize(),
            'mime' => $csvFile->getMimeType()
        ]);
        
        $csvPath = $csvFile->storeAs('email-campaigns', $filename, 'local');
        
        Log::info('CSV storage result', [
            'csvPath' => $csvPath,
            'success' => $csvPath !== false
        ]);
        
        if (!$csvPath) {
            Log::error('Failed to store CSV file', [
                'filename' => $filename,
                'error' => 'storeAs returned false'
            ]);
            return redirect()->back()->withErrors(['csv_file' => 'Failed to store CSV file']);
        }
        
        // Check both possible locations
        $fullPath = storage_path('app/' . $csvPath);
        $privatePath = storage_path('app/private/' . $csvPath);
        
        if (file_exists($privatePath)) {
            $fullPath = $privatePath;
        } elseif (!file_exists($fullPath)) {
            // Try to find the file anywhere in storage
            $searchPath = storage_path('app');
            $foundFiles = glob($searchPath . '/**/' . basename($csvPath), GLOB_BRACE);
            if (!empty($foundFiles)) {
                $fullPath = $foundFiles[0];
            }
        }
        
        if (!file_exists($fullPath)) {
            Log::error('CSV file not found after storage', ['path' => $fullPath, 'csvPath' => $csvPath]);
            return redirect()->back()->withErrors(['csv_file' => 'CSV file not found after upload']);
        }

        // Process CSV file
        $csvData = $this->processCsvFile($fullPath, $request->email_column, $request->name_column);

        // Limit to 5000 emails as per original script
        if (count($csvData) > 5000) {
            $csvData = array_slice($csvData, 0, 5000);
        }

        // Create batch first
        $batch = EmailCampaignBatch::create([
            'user_id' => Auth::id(),
            'name' => $request->campaign_name,
            'csv_filename' => $filename,
            'subject' => $request->subject,
            'subject_ru' => $request->subject_ru,
            'message' => $request->message,
            'message_ru' => $request->message_ru,
            'total_emails' => count($csvData),
            'status' => 'pending',
        ]);

        $campaignIds = [];

        // Create campaign records for each email
        foreach ($csvData as $row) {
            $campaign = EmailCampaign::create([
                'user_id' => Auth::id(),
                'batch_id' => $batch->id,
                'subject' => $request->subject,
                'subject_ru' => $request->subject_ru,
                'message' => $request->message,
                'message_ru' => $request->message_ru,
                'recipient_email' => $row['email'],
                'recipient_name' => $row['name'] ?? null,
                'company_name' => $row['name'] ?? null,
                'csv_filename' => $filename,
                'status' => 'pending',
                'csv_id' => $row['csv_id'] ?? null,
                'csv_company_id' => $row['csv_company_id'] ?? null,
                'sector' => $row['sector'] ?? null,
                'emtak' => $row['emtak'] ?? null,
                'phone' => $row['phone'] ?? null,
                'website' => $row['website'] ?? null,
            ]);

            $campaignIds[] = $campaign->id;
        }

        // Log successful creation
        Log::info('Email batch created', [
            'user_id' => Auth::id(),
            'batch_id' => $batch->id,
            'count' => count($campaignIds),
            'filename' => $filename
        ]);

        return redirect()->route('email-campaigns.index')
            ->with('success', 'Email kampaania loodud! ' . count($csvData) . ' kirja ootab saatmist.');
    }

    public function show(EmailCampaign $emailCampaign)
    {
        // Simple check instead of authorize for now
        if ($emailCampaign->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return view('email-campaigns.show', compact('emailCampaign'));
    }

    public function showBatch(EmailCampaignBatch $batch)
    {
        if ($batch->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $campaigns = $batch->campaigns()->paginate(50);
        
        return view('email-campaigns.batch-show', compact('batch', 'campaigns'));
    }

    public function startSending()
    {
        $userId = Auth::id();
        $pendingCampaigns = EmailCampaign::forUser($userId)
            ->where('status', 'pending')
            ->pluck('id')
            ->toArray();

        if (empty($pendingCampaigns)) {
            return redirect()->route('email-campaigns.index')
                ->with('error', 'Pole ühtegi ootel kampaaniat saatmiseks.');
        }

        // Update batch status to 'sending'
        $batches = EmailCampaignBatch::forUser($userId)->where('status', 'pending')->get();
        foreach ($batches as $batch) {
            $batch->update([
                'status' => 'sending',
                'started_at' => now(),
            ]);
        }

        // Start background process using exec (non-blocking)
        $command = "cd " . base_path() . " && php artisan email:send-campaigns > /dev/null 2>&1 &";
        exec($command);

        return redirect()->route('email-campaigns.index')
            ->with('success', 'Email saatmine alustatud! ' . count($pendingCampaigns) . ' kirja saadetakse taustal.');
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
            
            // Find indexes for all CSV columns
            $idIndex = array_search('id', $header);
            $companyIdIndex = array_search('company_id', $header);
            $sectorIndex = array_search('sector', $header);
            $emtakIndex = array_search('emtak', $header);
            $phoneIndex = array_search('phone', $header);
            $wwwIndex = array_search('www', $header);
            
            if ($emailIndex === false) {
                throw new \Exception("Email column '{$emailColumn}' not found in CSV");
            }
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (isset($row[$emailIndex]) && filter_var($row[$emailIndex], FILTER_VALIDATE_EMAIL)) {
                    $data[] = [
                        'email' => $row[$emailIndex],
                        'name' => ($nameIndex !== false && isset($row[$nameIndex])) ? $row[$nameIndex] : null,
                        'csv_id' => ($idIndex !== false && isset($row[$idIndex])) ? $row[$idIndex] : null,
                        'csv_company_id' => ($companyIdIndex !== false && isset($row[$companyIdIndex])) ? $row[$companyIdIndex] : null,
                        'sector' => ($sectorIndex !== false && isset($row[$sectorIndex])) ? $row[$sectorIndex] : null,
                        'emtak' => ($emtakIndex !== false && isset($row[$emtakIndex])) ? $row[$emtakIndex] : null,
                        'phone' => ($phoneIndex !== false && isset($row[$phoneIndex])) ? $row[$phoneIndex] : null,
                        'website' => ($wwwIndex !== false && isset($row[$wwwIndex])) ? $row[$wwwIndex] : null,
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
                    
                    // Update batch progress
                    if ($campaign->batch) {
                        $campaign->batch->updateProgress();
                    }
                    
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
                    
                    // Update batch progress
                    if ($campaign->batch) {
                        $campaign->batch->updateProgress();
                    }
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
