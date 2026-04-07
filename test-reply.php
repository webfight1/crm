<?php

// Test reply detection
$email = 'info@webfight.eu';
$password = 'bbixbposqdmxsqfi';
$imapHost = '{imap.gmail.com:993/imap/ssl}INBOX';

// Expected Message-ID from sent email
$expectedMessageId = '20260407204000.joMXCiytDdfo@smtp.gmail.com';

echo "Searching for replies to Message-ID: $expectedMessageId\n\n";

$inbox = @imap_open($imapHost, $email, $password);

if (!$inbox) {
    die("IMAP connection failed: " . imap_last_error() . "\n");
}

// Search for recent emails (last 10)
$emails = imap_search($inbox, 'ALL', SE_UID);

if (!$emails) {
    echo "No emails found in inbox.\n";
    imap_close($inbox);
    exit;
}

// Get last 10 emails
$emails = array_slice(array_reverse($emails), 0, 10);

echo "Checking last " . count($emails) . " emails...\n\n";

foreach ($emails as $uid) {
    $header = imap_headerinfo($inbox, imap_msgno($inbox, $uid));
    $structure = imap_fetchstructure($inbox, imap_msgno($inbox, $uid));
    
    echo "---\n";
    echo "From: " . ($header->from[0]->mailbox ?? '') . "@" . ($header->from[0]->host ?? '') . "\n";
    echo "Subject: " . ($header->subject ?? 'N/A') . "\n";
    echo "Date: " . ($header->date ?? 'N/A') . "\n";
    
    // Get In-Reply-To and References headers
    $fullHeader = imap_fetchheader($inbox, imap_msgno($inbox, $uid));
    
    if (preg_match('/In-Reply-To:\s*<([^>]+)>/i', $fullHeader, $matches)) {
        echo "In-Reply-To: " . $matches[1] . "\n";
        
        if (strpos($matches[1], $expectedMessageId) !== false) {
            echo "✅ MATCH! This is a reply to our email!\n";
        }
    }
    
    if (preg_match('/References:\s*(.+)/i', $fullHeader, $matches)) {
        echo "References: " . trim($matches[1]) . "\n";
        
        if (strpos($matches[1], $expectedMessageId) !== false) {
            echo "✅ MATCH! This is a reply to our email!\n";
        }
    }
}

imap_close($inbox);
echo "\nDone.\n";
