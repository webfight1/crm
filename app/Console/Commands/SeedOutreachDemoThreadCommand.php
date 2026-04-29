<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachCampaignStep;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachSendLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds (or removes) a single demo conversation so the operator can see how
 * the unified inbox renders before any real client replies have arrived.
 *
 * Idempotent: re-running the seed deletes the previous demo lead first so a
 * second run leaves the same single demo thread, not duplicates.
 *
 * Usage:
 *   php artisan outreach:seed-demo-thread          # create / re-seed
 *   php artisan outreach:seed-demo-thread --clean  # remove demo data only
 */
class SeedOutreachDemoThreadCommand extends Command
{
    protected $signature = 'outreach:seed-demo-thread
                            {--clean : Remove the demo lead and its messages instead of seeding}';

    protected $description = 'Seed (or clean) a single demo Outreach conversation for UI preview';

    private const DEMO_EMAIL = 'mari.maasikas.demo@naide.ee';

    public function handle(): int
    {
        if ($this->option('clean')) {
            return $this->clean();
        }

        return $this->seed();
    }

    private function clean(): int
    {
        $deleted = OutreachLead::whereRaw('LOWER(email) = ?', [self::DEMO_EMAIL])->get();

        if ($deleted->isEmpty()) {
            $this->info('No demo lead found — nothing to clean.');
            return self::SUCCESS;
        }

        // Cascading deletes on outreach_send_logs and outreach_messages
        // (set up in the original migrations) handle the related rows.
        foreach ($deleted as $lead) {
            $lead->delete();
        }

        $this->info('Cleaned ' . $deleted->count() . ' demo lead(s).');
        return self::SUCCESS;
    }

    private function seed(): int
    {
        $account = OutreachEmailAccount::orderBy('id')->first();
        if (! $account) {
            $this->error('No OutreachEmailAccount found. Add at least one mailbox before seeding.');
            return self::FAILURE;
        }

        // Re-seed cleanly: remove any prior demo lead so we don't accumulate.
        OutreachLead::whereRaw('LOWER(email) = ?', [self::DEMO_EMAIL])->each(fn($l) => $l->delete());

        $campaign = OutreachCampaign::firstOrCreate(
            ['name' => 'Demo kampaania (UI preview)'],
            [
                'description'        => 'Demo andmed Inbox vaate testimiseks. Kustuta käsuga: php artisan outreach:seed-demo-thread --clean',
                'daily_limit'        => 50,
                'reply_stop_enabled' => true,
                'use_ai_line'        => false,
                'is_active'          => false, // do NOT actually send
            ]
        );

        // Make sure the campaign has at least one step (FK requirement on send logs).
        $step = $campaign->steps()->first() ?? OutreachCampaignStep::create([
            'campaign_id'   => $campaign->id,
            'step_order'    => 1,
            'day_offset'    => 0,
            'subject'       => 'Demo step',
            'body_template' => 'Demo body',
        ]);

        return DB::transaction(function () use ($account, $campaign, $step) {
            $lead = OutreachLead::create([
                'campaign_id'                => $campaign->id,
                'assigned_email_account_id'  => $account->id,
                'first_name'                 => 'Mari',
                'last_name'                  => 'Maasikas',
                'email'                      => self::DEMO_EMAIL,
                'company'                    => 'Näidis OÜ',
                'website'                    => 'https://naide.ee',
                'industry'                   => 'E-kaubandus',
                'lcp_mobile'                 => '4.2s',
                'performance_score'          => 38,
                'status'                     => OutreachLead::STATUS_COMPLETED,
                'qualification'              => OutreachLead::QUALIFICATION_LEAD,
                'current_step'               => 3,
                'enrolled_at'                => now()->subDays(8),
                'next_send_at'               => null,
                'last_sent_at'               => now()->subDays(3),
                'replied'                    => true,
                'replied_at'                 => now()->subDays(2),
            ]);

            // Sent kirjad meie poolt (us → Mari)
            $sends = [
                [
                    'days_ago' => 8,
                    'subject'  => 'Märkasin teie e-poodi — kiire mõte',
                    'body'     => "<p>Tere Mari,</p><p>Märkasin et teie e-poe avalehe LCP mobiilis on ~4.2s — see tähendab et iga 4. külastaja lahkub enne kui leht jõuab täis laadida. Sealne 38/100 perf score viitab samale.</p><p>Meie spetsialiseerume just sellele probleemile. Kas oleks huvi 15-min kõneks järgmisel nädalal?</p><p>Tervitustega,<br>Veiko</p>",
                ],
                [
                    'days_ago' => 5,
                    'subject'  => 'Re: Märkasin teie e-poodi — kiire mõte',
                    'body'     => "<p>Tere uuesti Mari,</p><p>Kas eelmise kirja sõnum jõudis kohale? Leidsin meie viimase kliendi puhul, et sama tüüpi optimeerimine tõstis konversiooni 18%.</p><p>Kas teil oleks huvi numbreid näha?</p><p>Veiko</p>",
                ],
                [
                    'days_ago' => 3,
                    'subject'  => 'Re: Märkasin teie e-poodi — kiire mõte',
                    'body'     => "<p>Tere,</p><p>Viimane kord — kui pole huvi, siis ma annan rahu :) Kui aga on, siis kasvõi 10-min kõne ütleks juba palju.</p><p>Veiko</p>",
                ],
            ];

            $sentMessageIds = [];
            foreach ($sends as $i => $s) {
                $msgId = sprintf('demo-%s-%d@%s', uniqid(), $i, parse_url($account->smtp_host ?? 'demo.local', PHP_URL_HOST) ?? 'demo.local');
                $sentMessageIds[] = $msgId;

                OutreachSendLog::create([
                    'lead_id'          => $lead->id,
                    'campaign_id'      => $campaign->id,
                    'email_account_id' => $account->id,
                    'campaign_step_id' => $step->id,
                    'step_order'       => $i + 1,
                    'to_email'         => $lead->email,
                    'from_email'       => $account->email,
                    'subject'          => $s['subject'],
                    'body'             => $s['body'],
                    'status'           => OutreachSendLog::STATUS_SENT,
                    'message_id'       => $msgId,
                    'idempotency_key'  => 'demo-' . uniqid() . '-' . $i,
                    'sent_at'          => now()->subDays($s['days_ago']),
                ]);
            }

            // Vastused Marilt (lead → us)
            $replies = [
                [
                    'days_ago'    => 6,
                    'subject'     => 'Re: Märkasin teie e-poodi — kiire mõte',
                    'in_reply_to' => $sentMessageIds[0],
                    'body'        => "Tere Veiko,\n\nJah, oleme tegelikult ise ka mure peale mõelnud — eriti pärast jõulukampaaniat oli näha et mobiili pealt tuli oluliselt vähem oste kui prognoosisime.\n\nKas saaksite saata mõned näited oma eelnevatest klientidest? Eriti huvitaks, kui tehnilist tööd see meie poolt nõuaks.\n\nTervitustega,\nMari Maasikas\nNäidis OÜ",
                ],
                [
                    'days_ago'    => 2,
                    'subject'     => 'Re: Märkasin teie e-poodi — kiire mõte',
                    'in_reply_to' => $sentMessageIds[2],
                    'body'        => "Tere uuesti,\n\nVabandust hiljaks vastamise pärast. Olen mõelnud ja teie pakkumine kõlab huvitavalt. Kas saame järgmisel nädalal kõnele? Teisipäev või kolmapäev pärastlõuna sobiks.\n\nMari",
                ],
            ];

            $referencesChain = '';
            foreach ($replies as $i => $r) {
                $msgId = sprintf('reply-%s-%d@naide.ee', uniqid(), $i);
                $inReplyToBracketed = '<' . $r['in_reply_to'] . '>';
                $referencesChain = trim($referencesChain . ' ' . $inReplyToBracketed);

                OutreachMessage::create([
                    'lead_id'           => $lead->id,
                    'email_account_id'  => $account->id,
                    'direction'         => OutreachMessage::DIRECTION_INBOUND,
                    'message_id'        => $msgId,
                    'in_reply_to'       => $r['in_reply_to'],
                    'references_header' => $referencesChain,
                    'from_email'        => $lead->email,
                    'from_name'         => trim($lead->first_name . ' ' . $lead->last_name),
                    'subject'           => $r['subject'],
                    'body_text'         => $r['body'],
                    'body_html'         => null,
                    'has_attachments'   => false,
                    'received_at'       => now()->subDays($r['days_ago']),
                    'imap_uid'          => null,
                ]);
            }

            $this->info('Demo conversation seeded:');
            $this->line("  Email:    {$lead->email}");
            $this->line("  Campaign: {$campaign->name}");
            $this->line("  Mailbox:  {$account->email}");
            $this->line('  Sent: ' . count($sends) . ', Received: ' . count($replies));
            $this->line('');
            $this->line('  Open: /outreach/inbox');
            $this->line('  Cleanup: php artisan outreach:seed-demo-thread --clean');

            return self::SUCCESS;
        });
    }
}
