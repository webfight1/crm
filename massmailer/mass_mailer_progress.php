<?php
// Vea logimise lubamine
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Seansi algus
session_start();
set_time_limit(0); // Eemalda ajalimiit

// Initsialiseeri vaikimisi väärtused
if (!isset($_SESSION['mailer']) || !is_array($_SESSION['mailer'])) {
    // Vabasta seanss enne väljumist
    session_write_close();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error', 
        'message' => 'Saatmine pole alanud või seanss on aegunud. Palun alusta uuesti.'
    ]);
    exit;
}

// Võta viide seansi andmetele
$mailer = &$_SESSION['mailer'];

// Kui seanss on lõppenud, tagasta vastav teade
if (isset($mailer['status']) && $mailer['status'] === 'completed') {
    $response = [
        'status' => 'completed',
        'current' => $mailer['current'] ?? 0,
        'total' => $mailer['total'] ?? 0,
        'success' => $mailer['success'] ?? 0,
        'errors' => $mailer['errors'] ?? 0,
        'message' => $mailer['last_message'] ?? 'Saatmine on juba lõpetatud',
        'isError' => false
    ];
    session_write_close();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Kui on viga, tagasta see
if (isset($_SESSION['mailer_error'])) {
    echo json_encode([
        'status' => 'error',
        'message' => $_SESSION['mailer_error']
    ]);
    unset($_SESSION['mailer_error']);
    exit;
}

// Kontrolli viimast saatmise aega
if (!isset($_SESSION['last_send_time'])) {
    $_SESSION['last_send_time'] = 0;
}

$current_time = time();
$time_since_last_send = $current_time - $_SESSION['last_send_time'];

// Kui saatmine on pooleli
if ($mailer['status'] === 'running' && $mailer['current'] < $mailer['total']) {
    // Oota kuni 7 sekundit on möödunud
    if ($time_since_last_send < 7) {
        $sleep_time = 7 - $time_since_last_send;
        sleep($sleep_time);
        $current_time = time();
    }
    
    $emailInfo = $mailer['emailData'][$mailer['current']];
    $email = $emailInfo['email'];
    $companyName = $emailInfo['company_name'];
    
    // Vali õige sõnum ja teema (.ru e-mailidele vene keel)
    $messageToSend = $mailer['message'];
    $subjectToSend = $mailer['subject'];
    
    if (!empty($mailer['messageRu']) && strtolower(substr($email, -3)) === '.ru') {
        $messageToSend = $mailer['messageRu'];
    }
    
    if (!empty($mailer['subjectRu']) && strtolower(substr($email, -3)) === '.ru') {
        $subjectToSend = $mailer['subjectRu'];
    }
    
    // Replace {company_name} variable with actual company name
    $messageToSend = str_replace('{company_name}', $companyName, $messageToSend);
    $subjectToSend = str_replace('{company_name}', $companyName, $subjectToSend);
    
    // Saada e-mail
    if (sendEmail($email, $subjectToSend, $messageToSend, $mailer['from'])) {
        $mailer['success']++;
        $mailer['last_message'] = "E-mail saadetud: $email";
        $mailer['is_error'] = false;
        
        // Save sent message to file
        saveSentMessage($email, $subjectToSend, $messageToSend, $companyName);
    } else {
        $mailer['errors']++;
        $mailer['last_message'] = "Viga saatmisel: $email";
        $mailer['is_error'] = true;
    }
    
    $mailer['current']++;
    $_SESSION['last_send_time'] = $current_time;
    
    // Kui kõik kirjad on saadetud
    if ($mailer['current'] >= $mailer['total']) {
        $mailer['status'] = 'completed';
        $mailer['last_message'] = "Saatmine lõpetatud! Edukalt: {$mailer['success']}, Ebaõnnestunud: {$mailer['errors']}";
    }
}

// Vabasta seanss enne väljundit
session_write_close();

// Tagasta progress ja järgmise kirja saatmiseni jäänud aeg
$next_send_time = max(0, 7 - (time() - $_SESSION['last_send_time']));

// Koosta vastus
$response = [
    'status' => $mailer['status'] ?? 'unknown',
    'current' => $mailer['current'] ?? 0,
    'total' => $mailer['total'] ?? 0,
    'success' => $mailer['success'] ?? 0,
    'errors' => $mailer['errors'] ?? 0,
    'message' => $mailer['last_message'] ?? 'Teade puudub',
    'isError' => $mailer['is_error'] ?? false,
    'nextSendIn' => $next_send_time
];

// Saada vastus
header('Content-Type: application/json');
echo json_encode($response);

// Funktsioon e-maili saatmiseks
function sendEmail($to, $subject, $message, $from) {
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Funktsioon saadetud sõnumi salvestamiseks
function saveSentMessage($email, $subject, $message, $companyName) {
    $sentDir = __DIR__ . '/sent/';
    
    // Create sent directory if it doesn't exist
    if (!file_exists($sentDir)) {
        mkdir($sentDir, 0777, true);
    }
    
    // Create filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $timestamp . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email) . '.html';
    $filepath = $sentDir . $filename;
    
    // Create HTML content
    $htmlContent = "<!DOCTYPE html>\n";
    $htmlContent .= "<html lang=\"et\">\n";
    $htmlContent .= "<head>\n";
    $htmlContent .= "    <meta charset=\"UTF-8\">\n";
    $htmlContent .= "    <title>Saadetud e-mail</title>\n";
    $htmlContent .= "    <style>\n";
    $htmlContent .= "        body { font-family: Arial, sans-serif; margin: 20px; }\n";
    $htmlContent .= "        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }\n";
    $htmlContent .= "        .content { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }\n";
    $htmlContent .= "    </style>\n";
    $htmlContent .= "</head>\n";
    $htmlContent .= "<body>\n";
    $htmlContent .= "    <div class=\"header\">\n";
    $htmlContent .= "        <h2>Saadetud E-mail</h2>\n";
    $htmlContent .= "        <p><strong>Saaja:</strong> " . htmlspecialchars($email) . "</p>\n";
    $htmlContent .= "        <p><strong>Ettevõte:</strong> " . htmlspecialchars($companyName) . "</p>\n";
    $htmlContent .= "        <p><strong>Teema:</strong> " . htmlspecialchars($subject) . "</p>\n";
    $htmlContent .= "        <p><strong>Saadetud:</strong> " . date('d.m.Y H:i:s') . "</p>\n";
    $htmlContent .= "    </div>\n";
    $htmlContent .= "    <div class=\"content\">\n";
    $htmlContent .= "        <h3>Sõnumi sisu:</h3>\n";
    $htmlContent .= "        " . $message . "\n";
    $htmlContent .= "    </div>\n";
    $htmlContent .= "</body>\n";
    $htmlContent .= "</html>";
    
    // Save to file
    file_put_contents($filepath, $htmlContent);
}
