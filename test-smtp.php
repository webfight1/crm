<?php

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

require __DIR__.'/vendor/autoload.php';

// Gmail SMTP settings
$smtpHost = 'smtp.gmail.com';
$smtpPort = 587;
$smtpUsername = 'info@webfight.eu';
$smtpPassword = 'bbixbposqdmxsqfi'; // App Password (ilma tühikuteta)
$fromEmail = 'info@webfight.eu';
$fromName = 'Webfight CRM';
$toEmail = 'veiko@webfight.eu'; // Test recipient

echo "Testing Gmail SMTP connection...\n";
echo "Host: $smtpHost:$smtpPort\n";
echo "From: $fromEmail\n";
echo "To: $toEmail\n\n";

try {
    // Create SMTP transport with STARTTLS
    $transport = new EsmtpTransport($smtpHost, $smtpPort, false); // false = STARTTLS for port 587
    $transport->setUsername($smtpUsername);
    $transport->setPassword($smtpPassword);
    
    // Create mailer
    $mailer = new Mailer($transport);
    
    // Create email
    $email = (new Email())
        ->from(new Address($fromEmail, $fromName))
        ->to(new Address($toEmail))
        ->subject('Test email from CRM Outreach System')
        ->html('<p>This is a test email sent via Gmail SMTP.</p><p>If you receive this, SMTP is working correctly!</p>');
    
    // Send email
    $mailer->send($email);
    
    echo "✅ SUCCESS! Email sent successfully.\n";
    echo "Check inbox: $toEmail\n";
    
} catch (Exception $e) {
    echo "❌ FAILED! SMTP error:\n";
    echo $e->getMessage() . "\n";
}

echo "\nDone.\n";
