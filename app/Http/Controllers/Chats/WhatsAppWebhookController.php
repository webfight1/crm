<?php

namespace App\Http\Controllers\Chats;

use App\Chats\Services\WhatsAppIngest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives WuzAPI events. Auth = secret path segment (WuzAPI blocks private
 * IPs for webhooks, so it calls the public HTTPS URL). Always answers 200 for
 * a valid secret — a failing message must not make WuzAPI retry forever.
 */
class WhatsAppWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, WhatsAppIngest $ingest): JsonResponse
    {
        $expected = (string) config('services.wuzapi.webhook_secret');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        try {
            $ingest->handle($request->json()->all());
        } catch (Throwable $e) {
            Log::warning('[Chats] WhatsApp webhook failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
