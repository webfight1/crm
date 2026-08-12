<?php

namespace App\Console\Commands;

use App\Models\MeritReminderSetting;
use App\Services\Merit\OverdueReminderService;
use Illuminate\Console\Command;

/**
 * Saadab Meriti võlgnikele astmelised meeldetuletused.
 * Jookseb ajastatult (routes/console.php) ja käsitsi ("saada kohe" nupp / CLI).
 */
class SendMeritOverdueReminders extends Command
{
    protected $signature = 'merit:send-overdue-reminders
                            {--dry-run : Ainult arvuta ja näita, ära saada ega logi}
                            {--force : Saada ka siis, kui seaded on väljas}';

    protected $description = 'Küsib Meritist üle tähtaja tasumata arved ja saadab võlgnikele meeldetuletused';

    public function handle(OverdueReminderService $service): int
    {
        $settings = MeritReminderSetting::getSettings();
        $dryRun = (bool) $this->option('dry-run');

        if (! $settings->enabled && ! $dryRun && ! $this->option('force')) {
            $this->warn('Meriti meeldetuletused on seadetes välja lülitatud. Kasuta --force või lülita sisse.');

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Meriti võlgnike EELVAADE (dry-run)…' : 'Meriti meeldetuletuste saatmine…');

        try {
            $result = $service->sendReminders($dryRun);
        } catch (\Throwable $e) {
            $this->error('Viga: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! empty($result['planned'])) {
            $this->table(
                ['Klient', 'E-post', 'Aste', 'Päevi üle', 'Summa', 'Tulemus'],
                collect($result['planned'])->map(fn (array $p) => [
                    $p['name'],
                    $p['email'] ?: '—',
                    $p['level'],
                    $p['overdue_days'],
                    $p['total'],
                    $p['result'],
                ])->all()
            );
        }

        $this->info(sprintf(
            '%s: %d, vahele jäetud: %d, ebaõnnestus: %d, episood lõpetatud: %d',
            $dryRun ? 'Saadaks' : 'Saadetud',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
            $result['cleared'],
        ));

        return self::SUCCESS;
    }
}
