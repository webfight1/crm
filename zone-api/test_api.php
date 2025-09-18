<?php
/**
 * Zone.eu Email API testimise skript
 * Kasuta seda, et testida kas API töötab
 */

// API seaded
$apiUrl = 'https://your-zone-domain.ee/api/email_sender_api.php'; // Muuda see õigeks URL-iks
$apiToken = 'your-secure-api-token-here-change-this'; // Muuda see samaks mis API failis

// Test andmed
$testData = [
    'api_token' => $apiToken,
    'recipient_email' => 'test@example.com', // Muuda see oma e-maili aadressiks
    'subject' => 'Test kiri Zone API-st',
    'message' => '<h1>Tere!</h1><p>See on test kiri Zone.eu API-st.</p><p>Ettevõte: {company_name}</p>',
    'company_name' => 'Test Ettevõte OÜ',
    'recipient_name' => 'Test Kasutaja'
];

echo "Testimine Zone.eu Email API...\n";
echo "API URL: $apiUrl\n";
echo "Saaja: " . $testData['recipient_email'] . "\n\n";

// Saada päring
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: Zone API Test Script'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false, // Testimiseks, tootmises peaks olema true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP kood: $httpCode\n";

if ($error) {
    echo "cURL viga: $error\n";
    exit(1);
}

echo "Vastus:\n";
echo $response . "\n\n";

// Dekodeeri JSON vastus
$decoded = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON dekodeerimise viga: " . json_last_error_msg() . "\n";
    exit(1);
}

if (isset($decoded['success']) && $decoded['success']) {
    echo "✅ EDUKAS! E-mail saadetud edukalt.\n";
    echo "Saaja: " . $decoded['recipient'] . "\n";
    echo "Aeg: " . $decoded['timestamp'] . "\n";
} else {
    echo "❌ EBAÕNNESTUS!\n";
    echo "Viga: " . ($decoded['error'] ?? 'Tundmatu viga') . "\n";
}
?>
