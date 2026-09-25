<?php

namespace App\Seo\Services;

use App\Seo\Playbook;

/**
 * Playbook-driven rules applied while importing a CSV of ranking data:
 *   - header aliases ("Märksõna" → keyword) so SEO-monitor exports import as-is
 *   - skip rules (position range, excluded domains) → qualification = skip
 */
class SerpLeadFilter
{
    /**
     * @param string[] $aliasLines  e.g. ["märksõna = keyword", "url = website"]
     */
    public function __construct(
        private readonly int $positionMin = 0,
        private readonly int $positionMax = 0,
        private readonly array $excludes = [],
        private readonly array $aliasLines = [],
    ) {}

    public static function fromPlaybook(): self
    {
        return new self(
            Playbook::int('filter.position_min'),
            Playbook::int('filter.position_max'),
            Playbook::lines('filter.exclude_domains'),
            Playbook::lines('filter.column_aliases'),
        );
    }

    /**
     * Rename headers per the alias list. Headers are expected lowercased and
     * trimmed. A header that already is a system field name is left alone.
     *
     * @param  string[] $headers
     * @return string[]
     */
    public function mapHeaders(array $headers): array
    {
        $aliases = [];
        foreach ($this->aliasLines as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$from, $to] = array_map(fn ($s) => mb_strtolower(trim($s)), explode('=', $line, 2));
            if ($from !== '' && $to !== '') {
                $aliases[$from] = $to;
            }
        }

        $mapped = array_map(fn ($h) => $aliases[$h] ?? $h, $headers);

        // Two columns mapping to the same field: the leftmost one wins.
        $seen = [];
        foreach ($mapped as $i => $h) {
            if (isset($seen[$h])) {
                $mapped[$i] = '';
            }
            $seen[$h] = true;
        }

        return $mapped;
    }

    /** Reason the row should be skipped, or null to keep it. */
    public function skipReason(?int $position, ?string $website, string $email): ?string
    {
        if ($position !== null) {
            if ($this->positionMin > 0 && $position < $this->positionMin) {
                return "positsioon {$position} < {$this->positionMin}";
            }
            if ($this->positionMax > 0 && $position > $this->positionMax) {
                return "positsioon {$position} > {$this->positionMax}";
            }
        }

        $haystack = mb_strtolower(($website ?? '') . ' ' . $email);
        foreach ($this->excludes as $needle) {
            $needle = mb_strtolower(trim($needle));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return "välistatud: {$needle}";
            }
        }

        return null;
    }
}
