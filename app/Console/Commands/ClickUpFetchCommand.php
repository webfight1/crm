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
 *   php artisan clickup:fetch https://app.clickup.com/9015331367/v/cn/8cnp2h7-1595
 *   php artisan clickup:fetch 8cnp2h7-1595
 *   php artisan clickup:fetch 901234567 --list
 *   php artisan clickup:fetch <id> --contacts --csv=storage/app/clickup.csv
 *   php artisan clickup:fetch <id> --import=3          # → outreach campaign #3
 *   php artisan clickup:fetch <id> --fields            # what columns does this list have?
 *   php artisan clickup:fetch --teams                  # token smoke test
 *
 * The ClickUp URL segment after /v/<type>/ is the view id; /v/li/<id> is a list.
 * Pass the whole URL and the command figures out which one it got.
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
                $this->error('Anna ClickUp view/list id või URL. Näide: php artisan clickup:fetch 8cnp2h7-1595');
                return self::FAILURE;
            }

            [$id, $isList] = $this->resolveSource($source);

            $this->line(sprintf('<comment>Küsin ClickUpist %s id=%s …</comment>', $isList ? 'list' : 'view', $id));

            $tasks = $isList ? $clickup->tasksFromList($id) : $clickup->tasksFromView($id);

            if ($tasks === []) {
                $this->warn('ClickUp tagastas 0 rida. Kontrolli, kas id on õige ja token näeb seda workspace’i.');
                return self::SUCCESS;
            }

            if ($this->option('raw')) {
                $this->line(json_encode($tasks[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                return self::SUCCESS;
            }

            if ($this->option('fields')) {
                return $this->showFields($tasks[0]);
            }

            $rows = collect($tasks)
                ->flatMap(fn (array $task) => $this->option('contacts')
                    ? $clickup->extractRows($task)
                    : [$clickup->extractRow($task)])
                ->when(! $this->option('with-empty'), fn ($rows) => $rows->filter(fn ($r) => filled($r['email'])))
                ->values();

            $this->info(sprintf(
                '%d taski → %d rida%s.',
                count($tasks),
                $rows->count(),
                $this->option('with-empty') ? '' : ' (emailita read välja filtreeritud)'
            ));

            if ($campaignId = $this->option('import')) {
                return $this->import($rows->all(), (int) $campaignId, $importer);
            }

            if ($path = $this->option('csv')) {
                return $this->writeCsv($rows->all(), $path);
            }

            $this->table(
                ['Firma', 'Kontakt', 'Email', 'Veeb', 'Staatus'],
                $rows->map(fn ($r) => [
                    Str::limit($r['company'], 40),
                    trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—',
                    $r['email'] ?? '—',
                    Str::limit((string) ($r['website'] ?? '—'), 40),
                    $r['status'] ?? '—',
                ])->all()
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Accepts a bare id or a full ClickUp URL and works out whether we are
     * looking at a list (/v/li/<id>) or a view (/v/cn/<id>, /v/l/<id>, …).
     *
     * @return array{0: string, 1: bool}  [id, isList]
     */
    private function resolveSource(string $source): array
    {
        $isList = (bool) $this->option('list');

        if (str_contains($source, 'clickup.com')) {
            if (preg_match('~/v/(li|l|b|cn|g|t|gr|em|f|doc)/([^/?\#]+)~', $source, $m)) {
                // /v/li/<id> is a plain list; every other view type is a view id.
                return [$m[2], $isList || $m[1] === 'li'];
            }

            if (preg_match('~clickup\.com/\d+/v/([^/?\#]+)~', $source, $m)) {
                return [$m[1], $isList];
            }

            throw new \InvalidArgumentException("URList ei õnnestunud id-d leida: {$source}");
        }

        return [$source, $isList];
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

    private function showFields(array $task): int
    {
        $rows = [];

        foreach ($task['custom_fields'] ?? [] as $field) {
            $value = $field['value'] ?? null;
            $rows[] = [
                $field['name'] ?? '',
                $field['type'] ?? '',
                mb_substr(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value, 0, 60),
            ];
        }

        $this->line('Task name: ' . ($task['name'] ?? ''));
        $this->table(['Custom field', 'Tüüp', 'Näidisväärtus'], $rows);

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeCsv(array $rows, string $path): int
    {
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        @mkdir(dirname($path), 0775, true);

        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Ei saa faili kirjutada: {$path}");
            return self::FAILURE;
        }

        // Header names match OutreachCsvImportService's supported columns so
        // the same file can be fed straight back through the CSV importer.
        fputcsv($handle, ['company', 'email', 'first_name', 'last_name', 'website', 'industry', 'notes']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['company'],
                $row['email'],
                $row['first_name'] ?? null,
                $row['last_name'] ?? null,
                $row['website'],
                $row['industry'] ?? null,
                trim(sprintf(
                    'ClickUp: %s %s %s',
                    $row['status'] ?? '',
                    $row['job_title'] ?? '',
                    $row['url'] ?? ''
                )),
            ]);
        }

        fclose($handle);

        $this->info("CSV kirjutatud: {$path}");

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function import(array $rows, int $campaignId, OutreachCsvImportService $importer): int
    {
        $campaign = OutreachCampaign::find($campaignId);

        if (! $campaign) {
            $this->error("Kampaaniat #{$campaignId} ei leitud.");
            return self::FAILURE;
        }

        // Reuse the CSV importer so ClickUp rows go through exactly the same
        // dedupe/validation path as a manual upload.
        $tmp = tempnam(sys_get_temp_dir(), 'clickup_') . '.csv';

        if ($this->writeCsv($rows, $tmp) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $count = $importer->import($tmp, $campaignId);

        @unlink($tmp);

        $this->info("Imporditud {$count} rida kampaaniasse „{$campaign->name}” (#{$campaignId}). Duplikaadid jäeti vahele.");

        return self::SUCCESS;
    }
}
