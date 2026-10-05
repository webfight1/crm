<?php

namespace App\Chats\Services;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Turns one WuzAPI webhook payload (JSON format, type "Message") into a
 * ChatThread + ChatMessage. Read-only mirror of the personal WhatsApp.
 *
 * Payload shape (whatsmeow events.Message marshalled by WuzAPI):
 *   { type: "Message", event: { Info: { Chat, Sender, IsFromMe, IsGroup,
 *     SenderAlt, RecipientAlt, ID, PushName, Timestamp }, Message: {...} } }
 *
 * Message bodies are stored only for monitored threads; for the others we
 * only touch name / last activity so the thread can be switched on later.
 */
class WhatsAppIngest
{
    private const MEDIA = [
        'imageMessage'    => ['image', '📷 Pilt'],
        'videoMessage'    => ['video', '🎬 Video'],
        'audioMessage'    => ['audio', '🎤 Häälsõnum'],
        'documentMessage' => ['document', '📎 Dokument'],
        'stickerMessage'  => ['sticker', 'Kleeps'],
        'locationMessage' => ['other', '📍 Asukoht'],
        'contactMessage'  => ['other', '👤 Kontakt'],
    ];

    /** @return ChatMessage|null the stored message, null when skipped */
    public function handle(array $payload): ?ChatMessage
    {
        if (($payload['type'] ?? null) !== 'Message') {
            return null;
        }

        $info = (array) data_get($payload, 'event.Info', []);
        $msg  = (array) data_get($payload, 'event.Message', []);
        $chat = (string) ($info['Chat'] ?? '');

        // Status updates, channels and broadcast lists are not conversations.
        if ($chat === '' || str_ends_with($chat, '@broadcast') || str_ends_with($chat, '@newsletter')) {
            return null;
        }

        [$type, $body] = $this->content($msg);
        if ($type === null) {
            return null; // reaction, receipt, key distribution, …
        }

        $fromMe  = (bool) ($info['IsFromMe'] ?? false);
        $isGroup = (bool) ($info['IsGroup'] ?? false);
        $key     = $isGroup ? $chat : $this->directChatKey($info, $fromMe);
        $sentAt  = isset($info['Timestamp']) ? Carbon::parse($info['Timestamp']) : now();

        $thread = ChatThread::firstOrNew(['network' => 'whatsapp', 'external_id' => $key]);

        if (! $thread->exists) {
            $thread->is_group = $isGroup;
            $thread->phone    = $isGroup ? null : $this->phoneFromJid($key);
            $this->autoLink($thread);
        }

        // PushName is the sender's own name — for our outgoing messages it is ours.
        if (! $fromMe && ! $isGroup && filled($info['PushName'] ?? null)) {
            $thread->name = $info['PushName'];
        }
        if (! $thread->last_message_at || $sentAt->gt($thread->last_message_at)) {
            $thread->last_message_at = $sentAt;
        }
        $thread->save();

        if (! $thread->is_monitored) {
            return null;
        }

        return ChatMessage::firstOrCreate(
            ['chat_thread_id' => $thread->id, 'external_id' => (string) ($info['ID'] ?? md5(json_encode($info)))],
            [
                'direction'   => $fromMe ? 'out' : 'in',
                'sender_name' => $fromMe ? null : ($info['PushName'] ?? null),
                'type'        => $type,
                'body'        => $body,
                'sent_at'     => $sentAt,
                'read_at'     => $fromMe ? now() : null,
            ]
        );
    }

    /** @return array{0: ?string, 1: ?string} [type, body]; type null = skip */
    private function content(array $msg): array
    {
        $text = $msg['conversation'] ?? data_get($msg, 'extendedTextMessage.text');
        if (filled($text)) {
            return ['text', $text];
        }

        foreach (self::MEDIA as $field => [$type, $label]) {
            if (isset($msg[$field])) {
                $extra = data_get($msg, "$field.caption") ?? data_get($msg, "$field.fileName");
                return [$type, $label . ($extra ? ': ' . $extra : '')];
            }
        }

        return [null, null];
    }

    /**
     * Newer WhatsApp addresses people by LID (…@lid); the phone JID then sits
     * in SenderAlt / RecipientAlt. Prefer the phone JID so one person keeps
     * one thread and can be matched to a customer by phone.
     */
    private function directChatKey(array $info, bool $fromMe): string
    {
        $candidates = [$info['Chat'] ?? '', $fromMe ? ($info['RecipientAlt'] ?? '') : ($info['SenderAlt'] ?? '')];
        if (! $fromMe) {
            $candidates[] = $info['Sender'] ?? '';
        }

        foreach ($candidates as $jid) {
            if (str_ends_with((string) $jid, '@s.whatsapp.net')) {
                return $this->bareJid($jid);
            }
        }

        return $this->bareJid($info['Chat']);
    }

    /** "37255512345:12@s.whatsapp.net" → "37255512345@s.whatsapp.net" */
    private function bareJid(string $jid): string
    {
        [$user, $server] = array_pad(explode('@', $jid, 2), 2, '');
        return explode(':', $user)[0] . '@' . $server;
    }

    private function phoneFromJid(string $jid): ?string
    {
        return str_ends_with($jid, '@s.whatsapp.net') ? strstr($jid, '@', true) : null;
    }

    /** Link a new thread to a contact/customer with the same phone and start monitoring it. */
    private function autoLink(ChatThread $thread): void
    {
        if (! $thread->phone) {
            return;
        }

        $contact = Contact::whereNotNull('phone')->get(['id', 'phone', 'customer_id'])
            ->first(fn ($c) => self::normalizePhone($c->phone) === $thread->phone);
        $customer = $contact ? null : Customer::whereNotNull('phone')->get(['id', 'phone'])
            ->first(fn ($c) => self::normalizePhone($c->phone) === $thread->phone);

        if ($contact || $customer) {
            $thread->contact_id   = $contact?->id;
            $thread->customer_id  = $contact?->customer_id ?? $customer?->id;
            $thread->is_monitored = true;
        }
    }

    /** "+372 5551 2345" / "5551 2345" / "00372…" → "37255512345" (Estonian default). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) <= 8) {
            $digits = '372' . $digits;
        }

        return $digits;
    }
}
