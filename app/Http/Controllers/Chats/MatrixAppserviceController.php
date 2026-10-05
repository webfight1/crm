<?php

namespace App\Http\Controllers\Chats;

use App\Chats\Services\MessengerIngest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The CRM as a read-only Matrix appservice on the private Messenger
 * homeserver (docker/messenger). tuwunel pushes room events here.
 * Auth: hs_token as Bearer header (or legacy ?access_token).
 */
class MatrixAppserviceController extends Controller
{
    public function transaction(Request $request, string $txnId, MessengerIngest $ingest): JsonResponse
    {
        $this->authorizeHomeserver($request);

        try {
            $ingest->handle((array) $request->json('events', []));
        } catch (Throwable $e) {
            // Answer 200 anyway: a failing event must not block the queue forever.
            Log::warning('[Chats] Matrix transaction failed', ['txn' => $txnId, 'error' => $e->getMessage()]);
        }

        return response()->json((object) []);
    }

    /** User / room queries and ping: we own no users or aliases. */
    public function query(Request $request): JsonResponse
    {
        $this->authorizeHomeserver($request);

        return $request->is('*/ping')
            ? response()->json((object) [])
            : response()->json(['errcode' => 'M_NOT_FOUND'], 404);
    }

    private function authorizeHomeserver(Request $request): void
    {
        $expected = (string) config('services.messenger.hs_token');
        $given = (string) ($request->bearerToken() ?? $request->query('access_token'));

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(response()->json(['errcode' => 'M_FORBIDDEN'], 403));
        }
    }
}
