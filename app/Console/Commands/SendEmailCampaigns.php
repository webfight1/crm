<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignBatch;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;

class SendEmailCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send-campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pending email campaigns via Zone.eu API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email campaign sending...');
        
        $pendingCampaigns = EmailCampaign::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingCampaigns->isEmpty()) {
            $this->info('No pending campaigns found.');
            return;
        }

        $this->info('Found ' . $pendingCampaigns->count() . ' pending campaigns');

        foreach ($pendingCampaigns as $campaign) {
            $this->info("Checking: {$campaign->recipient_email}");
            
            try {
                // Check cooldown period (14 days by default)
                $cooldownDays = env('EMAIL_COOLDOWN_DAYS', 14);
                if (EmailLog::isInCooldown($campaign->recipient_email, $campaign->user_id, $cooldownDays)) {
                    $this->warn("⏸ Skipping {$campaign->recipient_email} - in cooldown period ({$cooldownDays} days)");
                    
                    // Mark as skipped (we'll use 'sent' status to avoid resending)
                    $campaign->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'error_message' => "Skipped - in cooldown period ({$cooldownDays} days)",
                    ]);
                    
                    // Update batch progress
                    if ($campaign->batch) {
                        $campaign->batch->updateProgress();
                    }
                    
                    continue;
                }
                
                $this->info("Sending to: {$campaign->recipient_email}");
                
                // Check for Russian email domains and use appropriate language
                $isRussian = $this->isRussianEmail($campaign->recipient_email);
                $subject = $isRussian && $campaign->subject_ru ? $campaign->subject_ru : $campaign->subject;
                $message = $isRussian && $campaign->message_ru ? $campaign->message_ru : $campaign->message;

                // Prepare data for Zone API
                $apiData = [
                    'api_token' => env('ZONE_EMAIL_API_TOKEN'),
                    'recipient_email' => $campaign->recipient_email,
                    'subject' => $subject,
                    'message' => $message,
                    'company_name' => $campaign->company_name,
                    'recipient_name' => $campaign->recipient_name,
                ];

                // Send request to Zone.eu API
                $response = $this->sendToZoneApi(env('ZONE_EMAIL_API_URL'), $apiData);
                $responseData = json_decode($response, true);

                if ($responseData && $responseData['success']) {
                    // Update campaign status as sent
                    $campaign->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    
                    // Log successful email send for cooldown tracking
                    EmailLog::logSent(
                        $campaign->user_id,
                        $campaign->recipient_email,
                        $subject,
                        $campaign->id,
                        $response
                    );
                    
                    // Update batch progress
                    if ($campaign->batch) {
                        $campaign->batch->updateProgress();
                    }
                    
                    $this->info("✓ Sent successfully");
                } else {
                    throw new \Exception($responseData['error'] ?? 'Unknown API error');
                }

                // Add delay between emails
                $delaySeconds = env('EMAIL_SEND_DELAY', 15);
                $this->info("Waiting {$delaySeconds} seconds...");
                sleep($delaySeconds);

            } catch (\Exception $e) {
                $this->error("✗ Failed to send to {$campaign->recipient_email}: " . $e->getMessage());
                
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

        $this->info('Email campaign sending completed!');
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
        
        return $response;
    }

    /**
     * Check if email domain suggests Russian language preference
     */
    private function isRussianEmail($email)
    {
        $russianDomains = ['.ru', '.by', '.kz', '.ua', 'mail.ru', 'yandex.ru', 'yandex.com'];
        
        foreach ($russianDomains as $domain) {
            if (strpos($email, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
