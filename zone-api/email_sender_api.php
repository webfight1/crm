<?php
/**
 * Zone.eu Email Sender API
 * Võtab vastu JSON päringuid Laravel CRM-ist ja saadab e-maile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Zone.eu SMTP seaded
$smtp_server = 'mail.zone.eu';
$smtp_port = 25;
$from_email = 'veiko@webfight.ee';
$from_name = 'CRM System';

// API turvalisuse token
$api_token = 'YLLsJS0QmkvsJQwQNb_jHGR6aeQ1DCaRT53CYQH3qRVwfG4CMi0eVdUZ-JcSOb1J';

// Logi funktsioon
function logMessage($message) {
    $logFile = __DIR__ . '/email_api.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Funktsioon e-maili saatmiseks
function sendEmail($to, $subject, $message, $from_email, $from_name) {
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Zone CRM API\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Kontrolli meetodit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method allowed'
    ]);
    exit;
}

// Loe JSON andmed
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON data'
    ]);
    logMessage('ERROR: Invalid JSON data received');
    exit;
}

// Kontrolli API tokenit
$provided_token = $data['api_token'] ?? '';
if ($provided_token !== $api_token) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid API token'
    ]);
    logMessage('ERROR: Invalid API token provided');
    exit;
}

// Kontrolli nõutavaid välju
$required_fields = ['recipient_email', 'subject', 'message'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        logMessage("ERROR: Missing required field: $field");
        exit;
    }
}

// Võta andmed
$recipient_email = filter_var($data['recipient_email'], FILTER_VALIDATE_EMAIL);
$subject = $data['subject'];
$message = $data['message'];
$company_name = $data['company_name'] ?? '';
$recipient_name = $data['recipient_name'] ?? '';

// Kontrolli e-maili aadressi
if (!$recipient_email) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid email address'
    ]);
    logMessage("ERROR: Invalid email address: " . $data['recipient_email']);
    exit;
}

// Asenda muutujad sõnumis
if (!empty($company_name)) {
    $message = str_replace('{company_name}', $company_name, $message);
    $subject = str_replace('{company_name}', $company_name, $subject);
}

if (!empty($recipient_name)) {
    $message = str_replace('{recipient_name}', $recipient_name, $message);
    $subject = str_replace('{recipient_name}', $recipient_name, $subject);
}

// Logi saatmise katse
logMessage("Attempting to send email to: $recipient_email, Subject: $subject");

// Saada e-mail
try {
    $result = sendEmail($recipient_email, $subject, $message, $from_email, $from_name);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully',
            'recipient' => $recipient_email,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        logMessage("SUCCESS: Email sent to $recipient_email");
    } else {
        throw new Exception('Mail function returned false');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send email: ' . $e->getMessage(),
        'recipient' => $recipient_email
    ]);
    logMessage("ERROR: Failed to send email to $recipient_email - " . $e->getMessage());
}

// Logi kõik päringud (debug jaoks)
$debug_log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'recipient' => $recipient_email,
    'subject' => $subject,
    'company' => $company_name
];

file_put_contents(__DIR__ . '/api_requests.log', json_encode($debug_log) . "\n", FILE_APPEND | LOCK_EX);
?>
