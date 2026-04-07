<?php

// Mark reply as UNSEEN so reply detection can find it
$email = 'info@webfight.eu';
$password = 'bbixbposqdmxsqfi';
$imapHost = '{imap.gmail.com:993/imap/ssl}INBOX';

$inbox = @imap_open($imapHost, $email, $password);

if (!$inbox) {
    die("IMAP connection failed: " . imap_last_error() . "\n");
}

// Search for the reply from veiko.teekel@gmail.com
$emails = imap_search($inbox, 'FROM "veiko.teekel@gmail.com"', SE_UID);

if (!$emails) {
    echo "No emails found from veiko.teekel@gmail.com\n";
    imap_close($inbox);
    exit;
}

// Get the most recent one
$uid = end($emails);
$msgno = imap_msgno($inbox, $uid);

echo "Found email from veiko.teekel@gmail.com (UID: $uid, MsgNo: $msgno)\n";

// Mark as UNSEEN
imap_clearflag_full($inbox, (string)$uid, '\\Seen', ST_UID);

echo "Marked as UNSEEN\n";

imap_close($inbox);
echo "Done.\n";
