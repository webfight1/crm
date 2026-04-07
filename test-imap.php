<?php

// Gmail IMAP test
$email = 'info@webfight.eu';
$password = 'bbixbposqdmxsqfi'; // App Password
$imapHost = '{imap.gmail.com:993/imap/ssl}INBOX';

echo "Testing Gmail IMAP connection...\n";
echo "Email: $email\n";
echo "Host: $imapHost\n\n";

// Try to connect
$inbox = @imap_open($imapHost, $email, $password);

if ($inbox) {
    echo "✅ SUCCESS! IMAP connection established.\n";
    
    // Get mailbox info
    $check = imap_check($inbox);
    echo "Messages in inbox: " . $check->Nmsgs . "\n";
    
    // Close connection
    imap_close($inbox);
} else {
    echo "❌ FAILED! IMAP connection error:\n";
    echo imap_last_error() . "\n";
}

echo "\nDone.\n";
