<?php

namespace App\Outreach\Services;

/**
 * MxCheckService
 *
 * "Does this email address's domain accept mail?" — resolved by looking
 * up the MX records for the domain (with A/AAAA as an implicit fallback,
 * per RFC 5321 §5.1). If neither exists, the domain literally cannot
 * receive mail and any send would be a guaranteed hard bounce.
 *
 * Cheap: one DNS query per unique domain. Cached in-process so scanning
 * a whole campaign's lead list only pays the DNS cost once per domain.
 */
class MxCheckService
{
    /** @var array<string, bool> Per-request domain → mx-ok cache. */
    private array $cache = [];

    /**
     * True if the address's domain has an MX record OR at least an A/AAAA
     * record (which SMTP treats as an implicit MX). False when neither
     * exists — mail to that domain will bounce.
     */
    public function hasMx(string $email): bool
    {
        $domain = $this->domainOf($email);
        if ($domain === null) {
            return false;
        }

        if (array_key_exists($domain, $this->cache)) {
            return $this->cache[$domain];
        }

        // Suppress warnings from misbehaving DNS servers; getmxrr()
        // returns false when nothing is found, which we treat as "no MX".
        $hosts = [];
        $hasMx = @getmxrr($domain, $hosts) && ! empty($hosts);

        if (! $hasMx) {
            // RFC 5321 fallback: if a domain has no MX but does have an
            // A record, that address IS the mail exchanger. Very rare
            // in 2020s but still valid.
            $hasMx = (bool) @checkdnsrr($domain, 'A');
        }

        return $this->cache[$domain] = $hasMx;
    }

    private function domainOf(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $domain = strtolower(substr($email, $at + 1));
        return $domain !== '' ? $domain : null;
    }
}
