<?php

namespace App\Seo\Services;

use App\Seo\Playbook;

/**
 * Where are the site's files? Picks the access instructions for paid work
 * (AccessRequestService). WHOIS doesn't help here (.ee WHOIS shows the
 * registrar, not the host), so:
 *
 *   1. site builders from the homepage HTML (Voog, Wix, Shopify …) — no SSH
 *      there; Voog even runs on Zone's IPs, so this must come first
 *   2. the site's IP → reverse DNS name + network owner (ASN, via Team Cymru's
 *      free DNS service) + the IP itself (own servers), matched against
 *      Playbook access.providers
 *   3. behind Cloudflare the IP says nothing → nameservers, marked as a guess
 */
class HostingDetector
{
    private const PLATFORMS = [
        'Voog'        => ['voog.com', 'edicy'],
        'Wix'         => ['wixstatic.com', 'wix.com'],
        'Shopify'     => ['cdn.shopify.com'],
        'Squarespace' => ['squarespace.com'],
        'Webflow'     => ['webflow.com'],
        'Mozello'     => ['mozello.com'],
    ];

    public function __construct(private readonly SiteCrawler $site) {}

    /**
     * @return array{provider:?string, kind:string, ip:?string, ptr:?string, network:?string, ns:string[], note:string}
     *   kind: platform (site builder, no SSH) | host | cloudflare | unknown
     */
    public function detect(string $siteUrl): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        $host = $origin ? (string) parse_url($origin, PHP_URL_HOST) : '';
        $out = ['provider' => null, 'kind' => 'unknown', 'ip' => null, 'ptr' => null, 'network' => null, 'ns' => [], 'note' => ''];
        if ($host === '') {
            return ['note' => 'Vigane aadress.'] + $out;
        }

        $html = strtolower((string) $this->site->home($origin));
        foreach (self::PLATFORMS as $name => $needles) {
            foreach ($needles as $n) {
                if ($html !== '' && str_contains($html, $n)) {
                    return ['provider' => $name, 'kind' => 'platform',
                            'note' => "{$name} on kodulehe platvorm — SSH-d pole, küsi haldurikasutajat."] + $out;
                }
            }
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return ['note' => 'Domeeni IP-aadressi ei leitud.'] + $out;
        }
        $out['ip'] = $ip;
        $ptr = @gethostbyaddr($ip);
        $out['ptr'] = $ptr && $ptr !== $ip ? $ptr : null;
        $out['network'] = $this->network($ip);
        $out['ns'] = $this->nameservers($host);

        // IP included, so our own servers can be listed by address.
        $haystack = strtolower($ip . ' ' . $out['ptr'] . ' ' . $out['network']);
        if (str_contains($haystack, 'cloudflare')) {
            $guess = $this->match(implode(' ', $out['ns']));
            return array_merge($out, [
                'provider' => $guess,
                'kind'     => 'cloudflare',
                'note'     => 'Leht on Cloudflare\'i taga, päris majutust IP järgi ei näe.'
                    . ($guess ? " Nimeserverid: {$guess} — tõenäoliselt ka majutus." : ' Küsi kliendilt.'),
            ]);
        }

        $provider = $this->match($haystack);

        return array_merge($out, [
            'provider' => $provider,
            'kind'     => $provider ? 'host' : 'unknown',
            'note'     => $provider
                ? "Majutaja: {$provider} (IP {$ip}" . ($out['ptr'] ? ", {$out['ptr']}" : '') . ').'
                : 'Majutajat ei tuntud ära' . ($out['network'] ? " — võrk: {$out['network']}" : '') . '. Lisa see Playbookis majutajate nimekirja.',
        ]);
    }

    /** Playbook access.providers: "Name | pattern pattern …" — first match wins. */
    private function match(string $haystack): ?string
    {
        $haystack = strtolower($haystack);
        foreach (Playbook::lines('access.providers') as $line) {
            [$name, $patterns] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            foreach (preg_split('/[\s,]+/', strtolower($patterns), -1, PREG_SPLIT_NO_EMPTY) as $p) {
                if (str_contains($haystack, $p)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /** "AS49604 ZONE - Zone Media OU, EE" via Team Cymru's IP-to-ASN DNS service. */
    private function network(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }
        $rev = implode('.', array_reverse(explode('.', $ip)));
        $origin = @dns_get_record("{$rev}.origin.asn.cymru.com", DNS_TXT)[0]['txt'] ?? '';
        $asn = explode(' ', trim(explode('|', $origin)[0]))[0] ?? '';
        if (! ctype_digit($asn)) {
            return null;
        }
        $desc = @dns_get_record("AS{$asn}.asn.cymru.com", DNS_TXT)[0]['txt'] ?? '';

        return trim("AS{$asn} " . trim(explode('|', $desc)[4] ?? ''));
    }

    /** @return string[] */
    private function nameservers(string $host): array
    {
        // NS records sit on the registered domain, not on "www."
        $parts = explode('.', preg_replace('/^www\./', '', $host));
        $domain = implode('.', array_slice($parts, -2));

        return array_column(@dns_get_record($domain, DNS_NS) ?: [], 'target');
    }
}
