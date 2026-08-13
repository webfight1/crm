<?php

namespace App\Services\Merit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * MeritClient — Merit Aktiva (aktiva.merit.ee) API v1 klient.
 *
 * Autentimine (iga päring):
 *   timestamp = UTC yyyyMMddHHmmss
 *   body      = kompaktne JSON
 *   signature = urlencode( base64( HMAC_SHA256(key = ApiKey, data = ApiId + timestamp + body) ) )
 *   POST {base}/{endpoint}?apiId=..&timestamp=..&signature=..   keha = seesama JSON.
 *
 * NB! Sama JSON-string peab minema nii allkirjastamisse kui HTTP-kehasse
 * (baidi-täpne), muidu server tagastab "Invalid signature".
 *
 * Kasutatud endpointid:
 *   getcustdebtrep — võlaraport (üle tähtaja arved)
 *   getcustomers   — kliendi andmed (nimi, kontakt, e-post)
 */
class MeritClient
{
    public function __construct(
        private readonly ?string $apiId = null,
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
    ) {
    }

    private function apiId(): string
    {
        return $this->apiId ?? (string) config('services.merit.api_id');
    }

    private function apiKey(): string
    {
        return $this->apiKey ?? (string) config('services.merit.api_key');
    }

    private function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? (string) config('services.merit.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiId() !== '' && $this->apiKey() !== '';
    }

    /**
     * Võlaraport — kõik (või konkreetse kliendi) üle tähtaja tasumata dokumendid.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOverdueDebts(int $overdueDays = 0, ?string $debtDate = null): array
    {
        $payload = ['CustName' => '', 'OverDueDays' => $overdueDays];
        if ($debtDate !== null) {
            $payload['DebtDate'] = $debtDate; // yyyyMMdd
        }

        // Lühike cache, et sagedased lehelaadimised Meriti API-t üle ei koormaks (429).
        $result = Cache::remember(
            $this->cacheKey('debts', $payload),
            now()->addMinutes(3),
            fn () => $this->request('getcustdebtrep', $payload)
        );

        return is_array($result) ? array_values(array_filter($result, 'is_array')) : [];
    }

    /**
     * Kliendi andmed Meriti PartnerId (= CustomerId) järgi.
     *
     * @return array<string, mixed>|null
     */
    public function getCustomer(string $custId): ?array
    {
        // Kliendi andmed (nimi, e-post) muutuvad harva → pikem cache, vähem API-kutseid.
        return Cache::remember(
            $this->cacheKey('cust', ['Id' => $custId]),
            now()->addHour(),
            function () use ($custId): ?array {
                $result = $this->request('getcustomers', ['Id' => $custId]);

                if (isset($result['CustomerId']) || isset($result['Name'])) {
                    return $result; // üksik klient
                }
                if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                    return $result[0]; // nimekiri — võta esimene
                }

                return null;
            }
        );
    }

    /** Cache-võti, mis arvestab API kontot (apiId), et kontod ei põrkaks. */
    private function cacheKey(string $kind, array $payload): string
    {
        return 'merit:' . $kind . ':' . substr(md5($this->apiId()), 0, 8) . ':' . md5(json_encode($payload));
    }

    /**
     * Leia arve sisemine Id (SIHId) arve numbri järgi (getinvoices2, v2).
     * Vajalik PDF-i pärimiseks, sest võlaraport annab ainult arve numbri.
     */
    public function getInvoiceId(string $invNo, ?string $custId = null): ?string
    {
        $payload = ['InvNo' => $invNo];
        if ($custId) {
            $payload['CustId'] = $custId;
        }

        $result = $this->request('getinvoices2', $payload, 2);
        if (! is_array($result)) {
            return null;
        }

        // Eelista täpset arve numbri vastet.
        foreach ($result as $inv) {
            if (is_array($inv) && (string) ($inv['InvoiceNo'] ?? '') === $invNo && ! empty($inv['SIHId'])) {
                return (string) $inv['SIHId'];
            }
        }
        foreach ($result as $inv) {
            if (is_array($inv) && ! empty($inv['SIHId'])) {
                return (string) $inv['SIHId'];
            }
        }

        return null;
    }

    /**
     * Tõmba müügiarve PDF (getsalesinvpdf, v2). Tagastab [name, content(base64)].
     *
     * @return array{name: string, content: string}|null
     */
    public function getInvoicePdf(string $sihId): ?array
    {
        $result = $this->request('getsalesinvpdf', ['Id' => $sihId, 'DelivNote' => false], 2);

        if (is_array($result) && ! empty($result['FileContent'])) {
            return [
                'name'    => (string) ($result['FileName'] ?? ('arve-' . $sihId . '.pdf')),
                'content' => (string) $result['FileContent'],
            ];
        }

        return null;
    }

    /**
     * Kerge ühenduse test UI staatuse jaoks. Tagastab [ok, message].
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'API võtmed on seadistamata (MERIT_API_ID / MERIT_API_KEY).'];
        }

        try {
            // OverDueDays väga suur → tõenäoliselt tühi vastus, aga autentimine käib läbi.
            $this->request('getcustdebtrep', ['CustName' => '', 'OverDueDays' => 100000]);

            return ['ok' => true, 'message' => 'Ühendus Meritiga töötab.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Allkirjastatud päring Meriti API-le.
     *
     * @param  array<string, mixed>  $payload
     * @param  int|null  $version  Kui antud, kasutab /vN endpointi (nt v2), muidu seadistatud baasi.
     * @return mixed  Dekodeeritud JSON-vastus (array).
     */
    private function request(string $endpoint, array $payload, ?int $version = null): mixed
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Merit API võtmed on seadistamata.');
        }

        $timestamp = gmdate('YmdHis');
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Meriti päringu keha kodeerimine ebaõnnestus.');
        }

        $signature = base64_encode(
            hash_hmac('sha256', $this->apiId() . $timestamp . $json, $this->apiKey(), true)
        );

        $base = $this->baseUrl();
        if ($version !== null) {
            // Asenda baasi versioonisegment (nt .../api/v1 → .../api/v2).
            $base = preg_replace('#/v\d+$#', '/v' . $version, $base, 1) ?? $base;
        }
        $url = $base . '/' . ltrim($endpoint, '/');

        $query = http_build_query([
            'apiId'     => $this->apiId(),
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        // Väike kordus 429 (Too many requests) korral.
        $attempt = 0;
        do {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->withBody($json, 'application/json')
                ->post($url . '?' . $query);

            if ($response->status() !== 429 || $attempt >= 2) {
                break;
            }
            $attempt++;
            usleep(1500000); // 1,5 s
        } while (true);

        if ($response->failed()) {
            Log::warning('[Merit] API päring ebaõnnestus', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException(
                "Merit API viga ({$endpoint}): HTTP {$response->status()} — " . mb_substr($response->body(), 0, 200)
            );
        }

        $decoded = $response->json();

        // Merit tagastab edu korral massiivi/objekti; veateate võib anda ka 200-ga.
        if (is_array($decoded) && isset($decoded['Message']) && count($decoded) === 1) {
            throw new RuntimeException("Merit API teade ({$endpoint}): " . $decoded['Message']);
        }

        return $decoded ?? [];
    }
}
