<?php
/**
 * Outreach Mail Relay — runs on the host that owns the From: domain
 * (e.g. webfight.ee at zone.ee shared hosting).
 *
 * Receives an HMAC-authenticated JSON POST from the CRM (VPS) and dispatches
 * the email via local PHP mail(). This sidesteps Zone.ee's outbound SMTP
 * block on remote IPs — only the host's own webserver is allowed to send,
 * which is exactly where this script runs.
 *
 * ── Setup ─────────────────────────────────────────────────────────────────
 *  1. Edit RELAY_SECRET below (≥ 32 random chars). Keep it identical to the
 *     value you store on the CRM's OutreachEmailAccount.relay_secret field.
 *  2. Edit ALLOWED_FROM_DOMAINS to your own domain(s).
 *  3. Upload this file to a non-obvious URL on your hosting account, e.g.
 *     https://webfight.ee/internal/mail-relay-<random>.php
 *     The HMAC + timestamp window are the real defenses; URL obscurity is
 *     only a thin extra layer against scanners.
 *  4. Verify HTTPS is enforced at the host level (zone.ee provides this).
 *  5. Enter the URL + secret on the CRM email account form (provider =
 *     "Zone Relay").
 *
 * ── Protocol ──────────────────────────────────────────────────────────────
 *  Headers:
 *    X-Timestamp: <unix-seconds>
 *    X-Signature: <hex(hmac_sha256(timestamp + raw_body, RELAY_SECRET))>
 *  Body (JSON):
 *    { from_email, from_name, to_email, to_name, subject, html_body,
 *      message_id, in_reply_to?, references? }
 *  Response:
 *    HTTP 200 + { ok: true,  message_id: "..." }   on success
 *    HTTP 4xx + { ok: false, error: "..." }        on auth / validation error
 *    HTTP 500 + { ok: false, error: "..." }        on send failure
 */

// ─── CONFIG ──────────────────────────────────────────────────────────────
// CHANGE THIS to match the value stored on your CRM's email account.
const RELAY_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_STRING_AT_LEAST_32_CHARS';

// Only allow sending from these domains. Reject any from_email outside.
const ALLOWED_FROM_DOMAINS = ['webfight.ee'];

// Maximum allowed clock skew between client and server, in seconds.
// 300s = 5 min, balances NTP drift tolerance against replay-attack window.
const TIMESTAMP_TOLERANCE_SECONDS = 300;

// ─── ENTRY POINT ─────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

if (RELAY_SECRET === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING_AT_LEAST_32_CHARS') {
    respond(500, ['ok' => false, 'error' => 'Relay not configured (secret unchanged)']);
}

$timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$rawBody   = file_get_contents('php://input') ?: '';

// ── Auth: timestamp window ──────────────────────────────────────────────
if (! ctype_digit($timestamp)) {
    respond(401, ['ok' => false, 'error' => 'Missing or invalid timestamp']);
}
$age = abs(time() - (int) $timestamp);
if ($age > TIMESTAMP_TOLERANCE_SECONDS) {
    respond(401, ['ok' => false, 'error' => 'Timestamp out of tolerance']);
}

// ── Auth: HMAC signature ────────────────────────────────────────────────
if ($signature === '') {
    respond(401, ['ok' => false, 'error' => 'Missing signature']);
}
$expected = hash_hmac('sha256', $timestamp . $rawBody, RELAY_SECRET);
if (! hash_equals($expected, $signature)) {
    respond(401, ['ok' => false, 'error' => 'Bad signature']);
}

// ── Parse + validate body ───────────────────────────────────────────────
$payload = json_decode($rawBody, true);
if (! is_array($payload)) {
    respond(400, ['ok' => false, 'error' => 'Invalid JSON body']);
}

foreach (['from_email', 'to_email', 'subject', 'html_body', 'message_id'] as $required) {
    if (empty($payload[$required])) {
        respond(400, ['ok' => false, 'error' => "Missing required field: $required"]);
    }
}

$fromEmail = trim((string) $payload['from_email']);
$fromName  = trim((string) ($payload['from_name'] ?? ''));
$toEmail   = trim((string) $payload['to_email']);
$toName    = trim((string) ($payload['to_name'] ?? ''));
$subject   = (string) $payload['subject'];
$htmlBody  = (string) $payload['html_body'];
$messageId = trim((string) $payload['message_id'], '<>');
$inReplyTo = isset($payload['in_reply_to']) ? trim((string) $payload['in_reply_to'], '<>') : '';
$refs      = isset($payload['references'])  ? trim((string) $payload['references'])         : '';

if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['ok' => false, 'error' => 'Invalid email address']);
}

// ── Domain allowlist (defense in depth) ─────────────────────────────────
$fromDomain = strtolower(substr(strrchr($fromEmail, '@') ?: '', 1));
if (! in_array($fromDomain, array_map('strtolower', ALLOWED_FROM_DOMAINS), true)) {
    respond(403, ['ok' => false, 'error' => 'Sender domain not allowed']);
}

// ── Build headers (RFC 5322) ────────────────────────────────────────────
$headers = [];

// From: with optional encoded display name (RFC 2047 / 'B' = base64)
$encodedFromName = $fromName !== '' ? mimeEncode($fromName) : '';
$headers[] = 'From: ' . ($encodedFromName !== ''
    ? "$encodedFromName <$fromEmail>"
    : $fromEmail);

$headers[] = 'Reply-To: ' . $fromEmail;
$headers[] = 'Message-ID: <' . $messageId . '>';

if ($inReplyTo !== '') {
    $headers[] = 'In-Reply-To: <' . $inReplyTo . '>';
}
if ($refs !== '') {
    // References header may be a space-separated chain. Pass through as-is.
    $headers[] = 'References: ' . $refs;
}

$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$headers[] = 'X-Mailer: outreach-relay/1.0';

// To: header value with optional display name
$encodedToName = $toName !== '' ? mimeEncode($toName) : '';
$toHeaderValue = $encodedToName !== ''
    ? "$encodedToName <$toEmail>"
    : $toEmail;

// Subject (also RFC 2047 encoded if non-ASCII)
$encodedSubject = mimeEncode($subject);

// ── Send ────────────────────────────────────────────────────────────────
// PHP's mail() has a quirk: passing $to with a display name works on most
// MTAs but a few sendmail variants prefer a bare address there and the
// display name in headers. Zone.ee uses standard sendmail where the
// "Name <addr>" form on $to is accepted.
$ok = @mail(
    $toHeaderValue,
    $encodedSubject,
    $htmlBody,
    implode("\r\n", $headers),
    '-f ' . $fromEmail   // envelope sender (return path) for bounce routing
);

if (! $ok) {
    respond(500, [
        'ok'    => false,
        'error' => 'mail() returned false; check server mail log',
    ]);
}

respond(200, [
    'ok'         => true,
    'message_id' => $messageId,
]);

// ─── HELPERS ─────────────────────────────────────────────────────────────

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * RFC 2047 encoded-word for headers containing non-ASCII characters.
 * Returns the input unchanged if it's already pure ASCII.
 */
function mimeEncode(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value; // already ASCII-safe
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
