<?php

namespace App\Outreach\Support;

/**
 * Decodes RFC 2047 encoded words ("=?utf-8?Q?...?=") in mail headers.
 *
 * iconv_mime_decode() gives up when a client (Outlook, Gmail) splits one
 * multi-byte character — typically an emoji — across two encoded words,
 * and then the raw "=?utf-8?Q?…" text ended up in the inbox and in our
 * "Re:" replies. Here adjacent words of the same charset are joined as
 * bytes first and converted to UTF-8 only after that.
 */
class MimeHeader
{
    private const WORD = '/=\?([^?\s]+)\?([QqBb])\?([^?\s]*)\?=/';

    public static function decode(?string $value): string
    {
        $value = (string) $value;
        if (! str_contains($value, '=?')) {
            return $value;
        }

        // Folded headers: CRLF + whitespace is just whitespace.
        $value = preg_replace('/\r?\n[ \t]+/', ' ', $value);

        preg_match_all(self::WORD, $value, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        if (! $m) {
            return $value;
        }

        $out = '';
        $pos = 0;
        $charset = null;
        $bytes = '';

        foreach ($m as $word) {
            [$full, $offset] = $word[0];
            $between = substr($value, $pos, $offset - $pos);
            $cs = strtolower(explode('*', $word[1][0])[0]); // drop RFC 2231 language
            $adjacent = $charset !== null && trim($between) === '';

            if ($adjacent && $cs === $charset) {
                // Whitespace between encoded words is dropped; bytes continue.
            } else {
                $out .= self::flush($bytes, $charset);
                $bytes = '';
                if (! $adjacent) {
                    $out .= $between;
                }
            }

            $charset = $cs;
            $text = $word[3][0];
            $bytes .= strtoupper($word[2][0]) === 'B'
                ? (string) base64_decode($text)
                : quoted_printable_decode(str_replace('_', ' ', $text));
            $pos = $offset + strlen($full);
        }

        $out .= self::flush($bytes, $charset).substr($value, $pos);

        return $out;
    }

    private static function flush(string $bytes, ?string $charset): string
    {
        if ($bytes === '' || $charset === null) {
            return $bytes;
        }
        if (in_array($charset, ['utf-8', 'utf8', 'us-ascii'], true)) {
            return mb_scrub($bytes, 'UTF-8');
        }

        try {
            $converted = mb_convert_encoding($bytes, 'UTF-8', $charset);
        } catch (\ValueError) {
            $converted = false; // charset mbstring doesn't know
        }
        if ($converted === false) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $bytes);
        }

        return $converted === false ? mb_scrub($bytes, 'UTF-8') : $converted;
    }
}
