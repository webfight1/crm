<?php

namespace App\Services\Merit;

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

        $result = $this->request('getcustdebtrep', $payload);

        return is_array($result) ? array_values(array_filter($result, 'is_array')) : [];
    }

    /**
     * Kliendi andmed Meriti PartnerId (= CustomerId) järgi.
     *
     * @return array<string, mixed>|null
     */
    public function getCustomer(string $custId): ?array
    {
        $result = $this->request('getcustomers', ['Id' => $custId]);

        if (isset($result['CustomerId']) || isset($result['Name'])) {
            return $result; // üksik klient
        }
        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            return $result[0]; // nimekiri — võta esimene
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
     * @return mixed  Dekodeeritud JSON-vastus (array).
     */
    private function request(string $endpoint, array $payload): mixed
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

        $url = $this->baseUrl() . '/' . ltrim($endpoint, '/');

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->withBody($json, 'application/json')
            ->post($url . '?' . http_build_query([
                'apiId'     => $this->apiId(),
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]));

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
