<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Services\OutreachCsvImportService;
use App\Services\ClickUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Pulls rows (company name + email) out of a ClickUp list or view.
 *
 * Usage:
 *   php artisan clickup:fetch https://app.clickup.com/9015331367/v/l/li/901523799837
 *   php artisan clickup:fetch 901523799837 --list
 *   php artisan clickup:fetch <id> --contacts --csv=storage/app/clickup.csv
 *   php artisan clickup:fetch <id> --import=3          # → outreach campaign #3
 *   php artisan clickup:fetch <id> --fields            # what columns does this list have?
 *   php artisan clickup:fetch --teams                  # token smoke test
 *
 * The same fetch is available in the UI at /outreach/clickup, which shares
 * ClickUpService with this command — keep behaviour changes in the service.
 */
class ClickUpFetchCommand extends Command
{
    protected $signature = 'clickup:fetch
                            {source? : ClickUp view id, list id, or a full app.clickup.com URL}
                            {--list : Treat the source as a list id instead of a view id}
                            {--teams : Just list the workspaces the token can see, then exit}
                            {--fields : Show the custom-field names found on the first task}
                            {--raw : Dump the raw JSON of the first task (field discovery)}
                            {--contacts : One row per contact (Email/Email2/Email3) instead of per company}
                            {--with-empty : Keep rows that have no email}
                            {--csv= : Write the rows to this CSV path instead of a table}
                            {--import= : Import the rows into this outreach campaign id}';

    protected $description = 'Fetch company name + email from a ClickUp list or view';

    public function handle(ClickUpService $clickup, OutreachCsvImportService $importer): int
    {
        try {
            if ($this->option('teams')) {
                return $this->showTeams($clickup);
            }

            $source = (string) $this->argument('source');

            if ($source === '') {
                $this->error('Anna ClickUp view/list id või URL. Näide: php artisan clickup:fetch 901523799837 --list');
                return self::FAILURE;
            }

            // --fields / --raw need the untouched task payload, not our rows.
            if ($this->option('fields') || $this->option('raw')) {
                return $this->inspect($clickup, $source);
            }

            [$id, $isList] = $clickup->resolveSource($source, (bool) $this->option('list'));
            $this->line(sprintf('<comment>Küsin ClickUpist %s id=%s …</comment>', $isList ? 'list' : 'view', $id));

            $result = $clickup->fetchRows(
                $source,
                perContact: (bool) $this->option('contacts'),
                withEmpty: (bool) $this->option('with-empty'),
                forceList: (bool) $this->option('list'),
            );

            $rows = $result['rows'];

            if ($result['tasks'] === 0) {
                $this->warn('ClickUp tagastas 0 rida. Kontrolli, kas id on õige ja token näeb seda workspace’i.');
                return self::SUCCESS;
            }

            $this->info(sprintf(
                '%d taski → %d rida%s.',
                $result['tasks'],
                count($rows),
                $this->option('with-empty') ? '' : ' (emailita read välja filtreeritud)'
            ));

            if ($campaignId = $this->option('import')) {
                return $this->import($clickup, $rows, (int) $campaignId, $importer);
            }

            if ($path = $this->option('csv')) {
                $path = str_starts_with($path, '/') ? $path : base_path($path);
                $clickup->writeCsv($rows, $path);
                $this->info("CSV kirjutatud: {$path}");

                return self::SUCCESS;
            }

            $this->table(
                ['Firma', 'Kontakt', 'Email', 'Veeb', 'Staatus'],
                array_map(fn ($r) => [
                    Str::limit((string) $r['company'], 40),
                    trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—',
                    $r['email'] ?? '—',
                    Str::limit((string) ($r['website'] ?? '—'), 40),
                    $r['status'] ?? '—',
                ], $rows)
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /** --fields / --raw: show what the source's first task actually looks like. */
    private function inspect(ClickUpService $clickup, string $source): int
    {
        [$id, $isList] = $clickup->resolveSource($source, (bool) $this->option('list'));

        $tasks = $isList ? $clickup->tasksFromList($id) : $clickup->tasksFromView($id);

        if ($tasks === []) {
            $this->warn('ClickUp tagastas 0 taski.');
            return self::SUCCESS;
        }

        if ($this->option('raw')) {
            $this->line(json_encode($tasks[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($tasks[0]['custom_fields'] ?? [] as $field) {
            $value = $field['value'] ?? null;
            $rows[] = [
                $field['name'] ?? '',
                $field['type'] ?? '',
                mb_substr(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value, 0, 60),
            ];
        }

        $this->line('Task name: ' . ($tasks[0]['name'] ?? ''));
        $this->table(['Custom field', 'Tüüp', 'Näidisväärtus'], $rows);

        return self::SUCCESS;
    }

    private function showTeams(ClickUpService $clickup): int
    {
        $teams = $clickup->teams();

        if ($teams === []) {
            $this->warn('Token töötab, aga ühtegi workspace’i ei näe.');
            return self::SUCCESS;
        }

        $this->table(['ID', 'Nimi'], array_map(fn ($t) => [$t['id'] ?? '', $t['name'] ?? ''], $teams));

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function import(ClickUpService $clickup, array $rows, int $campaignId, OutreachCsvImportService $importer): int
    {
        $campaign = OutreachCampaign::find($campaignId);

        if (! $campaign) {
            $this->error("Kampaaniat #{$campaignId} ei leitud.");
            return self::FAILURE;
        }

        // Reuse the CSV importer so ClickUp rows go through exactly the same
        // dedupe/validation path as a manual upload.
        $tmp = tempnam(sys_get_temp_dir(), 'clickup_') . '.csv';
        $clickup->writeCsv($rows, $tmp);

        $count = $importer->import($tmp, $campaignId);

        @unlink($tmp);

        $this->info("Imporditud {$count} rida kampaaniasse „{$campaign->name}” (#{$campaignId}). Duplikaadid jäeti vahele.");

        return self::SUCCESS;
    }
}
