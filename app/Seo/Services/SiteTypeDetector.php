<?php

namespace App\Seo\Services;

/**
 * E-shop or a regular (service) website? Decides which audit checks apply
 * (seo_audit_checks.applies_to) and whether the e-shop analysis runs.
 *
 * Scored from signals on the homepage and in the sitemap; ≥ 4 points = e-shop.
 * The operator can override per audit (manual audit form).
 */
class SiteTypeDetector
{
    public const ESHOP   = 'eshop';
    public const SERVICE = 'service';

    public const LABELS = [
        self::ESHOP   => 'E-pood',
        self::SERVICE => 'Koduleht (teenused)',
    ];

    private const THRESHOLD = 4;

    public function __construct(private readonly SiteCrawler $site) {}

    /** @return array{type:string, note:string} */
    public function detect(string $siteUrl): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        $html = $origin ? $this->site->home($origin) : null;
        if ($html === null) {
            return ['type' => self::SERVICE, 'note' => 'Avalehte ei saanud lugeda — eeldatud koduleht.'];
        }

        $h = strtolower($html);
        $signals = [];

        foreach ([
            'WooCommerce' => ['woocommerce'],
            'Shopify'     => ['cdn.shopify.com', 'shopify.theme'],
            'Magento'     => ['mage/cookies', 'magento'],
            'PrestaShop'  => ['prestashop'],
            'OpenCart'    => ['route=product', 'route=checkout'],
            'Mozello/Voog e-pood' => ['data-product-id', 'voog-ecommerce'],
        ] as $platform => $needles) {
            foreach ($needles as $n) {
                if (str_contains($h, $n)) {
                    $signals[$platform] = 3;
                    break;
                }
            }
        }
        if (preg_match('/add[-_]to[-_]cart|lisa ostukorvi|lisa korvi|osta kohe/u', $h)) {
            $signals['ostukorvi nupud'] = 2;
        }
        if (preg_match('~href="[^"]*/(cart|ostukorv|checkout|kassa)[/"]~', $h)) {
            $signals['ostukorvi link'] = 2;
        }
        if (preg_match('/"@type"\s*:\s*"(product|offer|aggregateoffer)"|schema\.org\/product/', $h)) {
            $signals['Product schema'] = 2;
        }
        if (preg_match_all('/\d[\d\s]*[.,]\d{2}\s*(€|&euro;|eur\b)/u', $h) >= 5) {
            $signals['palju hindu'] = 1;
        }

        $files = strtolower(implode(' ', $this->site->sitemapFiles($origin)));
        if (preg_match('/product|toode|tooted/', $files)) {
            $signals['toodete sitemap'] = 2;
        }
        $paths = strtolower(implode(' ', array_slice($this->site->sitemapUrls($origin), 0, 800)));
        if (preg_match_all('~/(product|products|toode|tooted|pood|shop|product-category|tootekategooria|collections)/~', $paths) >= 5) {
            $signals['tooteaadressid'] = 2;
        }

        $score = array_sum($signals);
        $type = $score >= self::THRESHOLD ? self::ESHOP : self::SERVICE;

        return [
            'type' => $type,
            'note' => $signals
                ? 'Tunnused: ' . implode(', ', array_keys($signals)) . " (skoor {$score}, e-pood alates " . self::THRESHOLD . ')'
                : 'E-poe tunnuseid ei leitud.',
        ];
    }
}
