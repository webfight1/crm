# Zone Mail Relay — Setup Guide

This folder ships the standalone PHP file (`mail-relay.php`) that you upload to your hosting (e.g. **zone.ee** for `webfight.ee`). It receives HMAC-authenticated JSON POST requests from the CRM and dispatches outbound mail via local PHP `mail()`.

## Why this exists

zone.ee shared hosting blocks outbound SMTP ports (25, 465, 587) for connections coming from **outside** their infrastructure — including your VPS where the CRM runs. The only way to send "from veiko@webfight.ee" is to do the actual sending **on a webserver hosted at zone.ee**. That's what this file does.

The CRM still:
- Receives replies via IMAP (zone.ee allows IMAP from anywhere, no change needed)
- Generates the Message-ID, In-Reply-To, and References headers (so Gmail threading works)
- Records every outbound message in `outreach_messages` for the inbox view

## Setup steps

### 1. Edit the secret
Open `mail-relay.php`, change the `RELAY_SECRET` constant to a long random string (at least 32 chars). Generate one with:

```bash
openssl rand -hex 32
```

Keep this value **identical** to what you'll enter on the CRM email-account form.

### 2. Verify allowed domains
The `ALLOWED_FROM_DOMAINS` constant whitelists which `from_email` addresses the relay accepts. Default is `['webfight.ee']`. Adjust if needed.

### 3. Upload to your hosting
Use FTP / SFTP / Zone's file manager to upload `mail-relay.php` to a location accessible via HTTPS. Suggested:

```
https://webfight.ee/internal/mail-relay.php
```

A few tips:
- **Use a non-obvious folder/filename.** Obscurity is a thin extra layer; HMAC is the real defense, but no point announcing the endpoint to scanners.
- **Make sure the file does NOT live next to a public index.** Don't put it inside a folder that's listable.
- **Verify HTTPS is enforced** by zone.ee at the domain level (it usually is).

### 4. Configure on the CRM
Go to **Outreach → Postkastid → Lisa postkast** (or muuda existing veiko@webfight.ee account) and:

- **Teenusepakkuja:** Zone Relay (HTTP fail veebiserveris)
- **Relay URL:** `https://webfight.ee/internal/mail-relay.php` (your URL from step 3)
- **Shared secret:** the value from step 1
- IMAP fields: fill normally (zone IMAP, port 993, SSL, your account password)
- SMTP fields: leave empty
- Märgi **"Põhipostkast vastusteks"** ✅

Save.

### 5. Test
- Open any conversation in `/outreach/inbox`
- Reply form should now appear with veiko@webfight.ee as the sender
- Send a test reply to your own personal email
- Verify: arrives in inbox, From shows your domain, threading works (try replying back and check it lands in IMAP)

## Troubleshooting

**`Bad signature`** — secret doesn't match. Regenerate, paste into both places.

**`Timestamp out of tolerance`** — clock drift > 5 min between VPS and zone server. Sync NTP on your VPS.

**`mail() returned false`** — local sendmail issue at zone. Check zone control panel for mail logs. Possibly hit the rate limit (1 mail / 5 sec on shared hosting).

**Replies don't thread back** — confirm the relay sets `In-Reply-To` and `References` (it does, but verify with View Source on a sent email in your sent folder). Some clients still split threads if the Subject changes drastically.

## Updating the secret later

If you ever rotate the secret:
1. Change `RELAY_SECRET` in `mail-relay.php`, re-upload
2. Update **Shared secret** field on the CRM email account
3. Both must change in the same window — there's no overlap support in this minimal relay.

## Removing the relay

To shut it down: delete the file from zone, or rename it. The CRM will start failing reply sends with HTTP 404 — which is fine, just means you can't reply from CRM until it's restored.
