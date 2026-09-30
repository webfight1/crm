<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Enrich a CSV of websites/domains with e-mails from the external company
 * register (`ettevotted` DB: companies, company_www, company_emails,
 * company_phones).
 *
 * A domain is matched two ways, in this order:
 *   1. company_www — the company lists the site as its homepage;
 *   2. company_emails — the company has an address @that-domain.
 * The second catches firms whose www row is missing or stale.
 *
 * The input file is written back unchanged, with lookup columns appended.
 */
class DomainEmailLookupService
{
    public const EXTRA_HEADERS = ['Email', 'Kõik emailid', 'Ettevõte', 'Registrikood', 'Telefon', 'Leitud'];

    /** Header names (lower-case) that hold the domain, best first. */
    private const DOMAIN_HEADERS = ['domeen', 'domain', 'koduleht', 'veebileht', 'website', 'veeb', 'www', 'url', 'kodulehekülg'];

    private const CHUNK = 50;

    public function __construct(private readonly string $connection = 'external_companies')
    {
    }

    /**
     * Read $inPath, write the enriched CSV to $outPath. With $missingPath the
     * rows without an e-mail go there instead, so they can be searched further.
     *
     * @return array{rows: int, found: int, domains: int}
     */
    public function enrichFile(string $inPath, string $outPath, ?string $missingPath = null): array
    {
        [$header, $rows, $delimiter] = $this->readCsv($inPath);

        $col = $this->domainColumn($header, $rows);
        if ($col === null) {
            throw new \RuntimeException('CSV-st ei leitud domeeni veergu (nt "Domeen", "Koduleht" või "Website").');
        }

        $domains = [];
        foreach ($rows as $row) {
            $d = self::normalizeDomain($row[$col] ?? '');
            if ($d !== null) {
                $domains[$d] = true;
            }
        }

        $matches = $this->lookup(array_keys($domains));

        $out = $this->openCsv($outPath, $header, $delimiter);
        $missing = $missingPath ? $this->openCsv($missingPath, $header, $delimiter) : $out;

        $found = 0;
        foreach ($rows as $row) {
            $row = array_pad($row, count($header), '');
            $m = $matches[self::normalizeDomain($row[$col] ?? '') ?? ''] ?? null;
            $hasEmail = $m && $m['emails'];

            if ($hasEmail) {
                $found++;
            }

            fputcsv($hasEmail ? $out : $missing, array_merge($row, [
                $m['emails'][0] ?? '',
                implode(', ', $m['emails'] ?? []),
                $m['name'] ?? '',
                $m['regcode'] ?? '',
                implode(', ', $m['phones'] ?? []),
                $m['via'] ?? '',
            ]), $delimiter, '"', '\\');
        }

        fclose($out);
        if ($missingPath) {
            fclose($missing);
        }

        return ['rows' => count($rows), 'found' => $found, 'domains' => count($domains)];
    }

    /** @return resource */
    private function openCsv(string $path, array $header, string $delimiter)
    {
        $fh = fopen($path, 'w');
        // Excel on Windows needs the BOM to read UTF-8 correctly.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_merge($header, self::EXTRA_HEADERS), $delimiter, '"', '\\');

        return $fh;
    }

    /**
     * @param  string[]  $domains  normalized domains
     * @return array<string, array{emails: string[], name: string, regcode: string, phones: string[], via: string}>
     */
    public function lookup(array $domains): array
    {
        $db = DB::connection($this->connection);

        // domain => [company_id => via]
        $companyIds = [];

        foreach (array_chunk($domains, self::CHUNK) as $chunk) {
            $q = $db->table('company_www')->select('company_id', 'www');
            $q->where(function ($w) use ($chunk) {
                foreach ($chunk as $d) {
                    foreach (['', 'www.', 'http://', 'https://', 'http://www.', 'https://www.'] as $prefix) {
                        $w->orWhere('www', 'like', $prefix . $d . '%');
                    }
                }
            });

            foreach ($q->get() as $r) {
                $d = self::normalizeDomain($r->www);
                if ($d !== null && in_array($d, $chunk, true)) {
                    $companyIds[$d][$r->company_id] = 'www';
                }
            }
        }

        $missing = array_values(array_diff($domains, array_keys($companyIds)));
        foreach (array_chunk($missing, self::CHUNK) as $chunk) {
            $q = $db->table('company_emails')->select('company_id', 'email');
            $q->where(function ($w) use ($chunk) {
                foreach ($chunk as $d) {
                    $w->orWhere('email', 'like', '%@' . $d);
                }
            });

            foreach ($q->get() as $r) {
                $d = strtolower(trim(substr(strrchr((string) $r->email, '@') ?: '', 1)));
                if (in_array($d, $chunk, true)) {
                    $companyIds[$d][$r->company_id] ??= 'e-posti domeen';
                }
            }
        }

        $allIds = array_unique(array_merge(...array_map('array_keys', array_values($companyIds ?: [[]]))));
        if (!$allIds) {
            return [];
        }

        $companies = collect();
        $emails = collect();
        $phones = collect();
        foreach (array_chunk($allIds, 1000) as $ids) {
            $companies = $companies->union($db->table('companies')->whereIn('id', $ids)->get(['id', 'name', 'regcode', 'ended'])->keyBy('id'));
            $emails = $emails->concat($db->table('company_emails')->whereIn('company_id', $ids)->get(['company_id', 'email']));
            $phones = $phones->concat($db->table('company_phones')->whereIn('company_id', $ids)->get(['company_id', 'phone']));
        }
        $emails = $emails->groupBy('company_id');
        $phones = $phones->groupBy('company_id');

        $result = [];
        foreach ($companyIds as $domain => $ids) {
            // Active companies first — a liquidated firm may still list the old site.
            $ordered = collect(array_keys($ids))
                ->sortBy(fn ($id) => filled($companies[$id]->ended ?? null) ? 1 : 0)
                ->values();

            $mail = $ordered
                ->flatMap(fn ($id) => ($emails[$id] ?? collect())->pluck('email'))
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->filter()
                ->unique()
                // Addresses on the site's own domain are the likeliest right inbox.
                ->sortBy(fn ($e) => str_ends_with($e, '@' . $domain) ? 0 : 1)
                ->values()
                ->all();

            $first = $companies[$ordered[0]] ?? null;

            $result[$domain] = [
                'emails'  => $mail,
                'name'    => $ordered->map(fn ($id) => $companies[$id]->name ?? null)->filter()->unique()->implode(' / '),
                'regcode' => (string) ($first->regcode ?? ''),
                'phones'  => $ordered->flatMap(fn ($id) => ($phones[$id] ?? collect())->pluck('phone'))->filter()->unique()->values()->all(),
                'via'     => $ids[$ordered[0]],
            ];
        }

        return $result;
    }

    /** "https://www.Foo.ee/kontakt" → "foo.ee"; null when it is not a domain. */
    public static function normalizeDomain(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return null;
        }

        $v = preg_replace('#^[a-z]+://#', '', $v);
        $v = preg_replace('#^www\d?\.#', '', $v);
        $v = preg_split('#[/?\#:\s]#', $v)[0];
        $v = trim($v, '.');

        return preg_match('/^[a-z0-9\-]+(\.[a-z0-9\-]+)+$/', $v) ? $v : null;
    }

    /** @return array{0: string[], 1: array<int, string[]>, 2: string} */
    private function readCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new \RuntimeException('CSV fail on tühi.');
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1257');
        }

        $firstLine = strtok($raw, "\n");
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $header = array_map('trim', fgetcsv($fh, 0, $delimiter, '"', '\\') ?: []);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if ($r !== [null]) {
                $rows[] = $r;
            }
        }
        fclose($fh);

        return [$header, $rows, $delimiter];
    }

    private function domainColumn(array $header, array $rows): ?int
    {
        $lower = array_map(fn ($h) => mb_strtolower(trim($h)), $header);
        foreach (self::DOMAIN_HEADERS as $name) {
            $i = array_search($name, $lower, true);
            if ($i !== false) {
                return $i;
            }
        }

        // No known header — take the first column whose values look like domains.
        $sample = array_slice($rows, 0, 20);
        foreach (array_keys($header) as $i) {
            $hits = count(array_filter($sample, fn ($r) => self::normalizeDomain($r[$i] ?? '') !== null
                && !str_contains((string) ($r[$i] ?? ''), '@')));
            if ($sample && $hits >= count($sample) / 2) {
                return $i;
            }
        }

        return null;
    }
}
