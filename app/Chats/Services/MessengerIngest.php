<?php

namespace App\Chats\Services;

use App\Chats\Models\ChatMessage;
use App\Chats\Models\ChatThread;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Turns Matrix room events pushed by tuwunel (appservice transaction) into
 * Messenger threads/messages. Every room on that private homeserver is a
 * mautrix-meta portal; people are ghosts "@facebook_<fbid>:wf.local" and
 * our own phone-sent messages come from our own ghost.
 *
 * Same storage rule as WhatsApp: content only for monitored threads.
 */
class MessengerIngest
{
    private const MEDIA = [
        'm.image'    => ['image', '📷 Pilt'],
        'm.video'    => ['video', '🎬 Video'],
        'm.audio'    => ['audio', '🎤 Häälsõnum'],
        'm.file'     => ['document', '📎 Fail'],
        'm.location' => ['other', '📍 Asukoht'],
        'm.sticker'  => ['sticker', 'Kleeps'],
    ];

    public function __construct(private MessengerBridge $bridge) {}

    public function handle(array $events): int
    {
        $stored = 0;
        foreach ($events as $event) {
            $stored += match ($event['type'] ?? null) {
                'm.room.name'    => $this->roomName($event),
                'm.room.member'  => $this->member($event),
                'm.room.message', 'm.sticker' => $this->message($event),
                default          => 0,
            };
        }

        return $stored;
    }

    private function roomName(array $e): int
    {
        $name = trim((string) data_get($e, 'content.name'));
        if ($name === '') {
            return 0;
        }

        $thread = $this->thread($e['room_id']);
        $thread->name = $name;
        $this->autoLink($thread);
        $thread->save();

        return 0;
    }

    /** Remember ghost display names (sender names; DM name fallback; group detection). */
    private function member(array $e): int
    {
        $user = (string) ($e['state_key'] ?? '');
        if (! $this->isGhost($user) || data_get($e, 'content.membership') !== 'join') {
            return 0;
        }

        $name = data_get($e, 'content.displayname');
        if ($name) {
            Cache::put('chats.mx.name.' . $user, $name, now()->addDays(30));
        }
        if ($user === $this->selfGhost()) {
            return 0;
        }

        $thread = $this->thread($e['room_id']);
        $others = Cache::get($key = 'chats.mx.members.' . $e['room_id'], []);
        $others[$user] = true;
        Cache::put($key, $others, now()->addDays(30));

        $thread->is_group = count($others) > 1;
        if (! $thread->name && $name) {
            $thread->name = $name;
        }
        $this->autoLink($thread);
        $thread->save();

        return 0;
    }

    private function message(array $e): int
    {
        $sender = (string) ($e['sender'] ?? '');
        if (! $this->isGhost($sender)) {
            return 0; // bridge bot notices, our own Matrix user, …
        }

        [$type, $body] = $this->content((array) ($e['content'] ?? []), $e['type']);
        if ($type === null) {
            return 0;
        }

        $sentAt = isset($e['origin_server_ts'])
            ? Carbon::createFromTimestampMs($e['origin_server_ts'])->setTimezone(config('app.timezone'))
            : now();
        $fromMe = $sender === $this->selfGhost();

        $thread = $this->thread($e['room_id']);
        if (! $thread->last_message_at || $sentAt->gt($thread->last_message_at)) {
            $thread->last_message_at = $sentAt;
        }
        $thread->save();

        if (! $thread->is_monitored) {
            return 0;
        }

        $message = ChatMessage::firstOrCreate(
            ['chat_thread_id' => $thread->id, 'external_id' => (string) $e['event_id']],
            [
                'direction'   => $fromMe ? 'out' : 'in',
                'sender_name' => $fromMe ? null : Cache::get('chats.mx.name.' . $sender),
                'type'        => $type,
                'body'        => $body,
                'sent_at'     => $sentAt,
                'read_at'     => $fromMe ? now() : null,
            ]
        );

        return (int) $message->wasRecentlyCreated;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function content(array $c, string $eventType): array
    {
        if ($eventType === 'm.sticker') {
            return ['sticker', 'Kleeps'];
        }
        if (isset($c['m.new_content'])) {
            return ['text', '✏️ ' . data_get($c, 'm.new_content.body')];
        }

        $msgtype = $c['msgtype'] ?? null;
        $body = (string) ($c['body'] ?? '');

        if (in_array($msgtype, ['m.text', 'm.notice', 'm.emote'], true)) {
            return $body === '' ? [null, null] : ['text', $body];
        }
        if (isset(self::MEDIA[$msgtype])) {
            [$type, $label] = self::MEDIA[$msgtype];
            // For media the body is the file name or caption.
            return [$type, $label . ($body !== '' && $msgtype !== 'm.location' ? ': ' . $body : '')];
        }

        return [null, null];
    }

    private function thread(string $roomId): ChatThread
    {
        return ChatThread::firstOrNew(['network' => 'messenger', 'external_id' => $roomId]);
    }

    private function isGhost(string $userId): bool
    {
        return str_starts_with($userId, config('services.messenger.ghost_prefix'));
    }

    private function selfGhost(): ?string
    {
        $id = $this->bridge->selfId();

        return $id ? config('services.messenger.ghost_prefix') . $id . ':' . explode(':', (string) config('services.messenger.matrix_user'), 2)[1] : null;
    }

    /**
     * Messenger has no phone number — link by exact full name and start
     * monitoring. Only while the portal is being set up (name/members arrive
     * in the first minutes), so a later manual unlink is never undone.
     */
    private function autoLink(ChatThread $thread): void
    {
        $settingUp = ! $thread->exists || $thread->created_at?->gt(now()->subMinutes(10));
        if (! $settingUp || $thread->contact_id || $thread->customer_id || $thread->is_monitored) {
            return;
        }
        $name = mb_strtolower(trim((string) $thread->name));
        if ($name === '' || $thread->is_group) {
            return;
        }

        $match = fn ($p) => mb_strtolower(trim($p->first_name . ' ' . $p->last_name)) === $name;
        $contact = Contact::get(['id', 'first_name', 'last_name', 'customer_id'])->first($match);
        $customer = $contact ? null : Customer::get(['id', 'first_name', 'last_name'])->first($match);

        if ($contact || $customer) {
            $thread->contact_id   = $contact?->id;
            $thread->customer_id  = $contact?->customer_id ?? $customer?->id;
            $thread->is_monitored = true;
        }
    }
}
