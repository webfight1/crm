<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends short alert messages to a Telegram chat via a bot.
 *
 * Does nothing when TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set, so
 * local and test environments stay silent without extra checks.
 */
class Telegram
{
    public static function enabled(): bool
    {
        return filled(config('services.telegram.token'))
            && filled(config('services.telegram.chat_id'));
    }

    public static function send(string $text): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            return Http::timeout(5)->post(
                'https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage',
                [
                    'chat_id' => config('services.telegram.chat_id'),
                    'text'    => mb_substr($text, 0, 4000), // Telegram hard limit is 4096
                    'disable_web_page_preview' => true,
                ]
            )->successful();
        } catch (Throwable) {
            // Swallow on purpose: an alert failure must never break the app,
            // and logging it would re-trigger the error-log listener.
            return false;
        }
    }

    /**
     * Send at most once per $minutes for the same $key, so a recurring
     * failure (e.g. IMAP down, polled every 5 min) doesn't flood the chat.
     */
    public static function sendThrottled(string $key, string $text, int $minutes = 60): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            $first = Cache::add('telegram-alert:' . md5($key), true, now()->addMinutes($minutes));
        } catch (Throwable) {
            $first = true; // cache unavailable — better a duplicate than a missed alert
        }

        if ($first) {
            self::send($text);
        }
    }
}
