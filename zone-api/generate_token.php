<?php
/**
 * API Tokeni Generaator
 * Genereerib turvalise API tokeni Zone.eu API jaoks
 */

echo "=== Zone.eu API Token Generator ===\n\n";

// Meetod 1: Juhuslik string (soovitatud)
function generateSecureToken($length = 64) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
    $token = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[random_int(0, $max)];
    }
    
    return $token;
}

// Meetod 2: Base64 encoded random bytes
function generateBase64Token($bytes = 48) {
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

// Meetod 3: Hash põhine
function generateHashToken() {
    return hash('sha256', random_bytes(32) . time() . uniqid());
}

// Genereeri erinevaid tokene
echo "1. Juhuslik string token (64 tähemärki):\n";
$token1 = generateSecureToken(64);
echo $token1 . "\n\n";

echo "2. Base64 token (64 tähemärki):\n";
$token2 = generateBase64Token(48);
echo $token2 . "\n\n";

echo "3. SHA256 hash token (64 tähemärki):\n";
$token3 = generateHashToken();
echo $token3 . "\n\n";

echo "=== SOOVITUS ===\n";
echo "Kasuta tokenit #1 (juhuslik string):\n";
echo "TOKEN: " . $token1 . "\n\n";

echo "=== JÄRGMISED SAMMUD ===\n";
echo "1. Kopeeri üks token ülalt\n";
echo "2. Lisa see email_sender_api.php faili:\n";
echo "   \$api_token = '$token1';\n\n";
echo "3. Lisa sama token test_api.php faili:\n";
echo "   \$apiToken = '$token1';\n\n";
echo "4. Lisa sama token Docker Compose faili:\n";
echo "   ZONE_EMAIL_API_TOKEN: \"$token1\"\n\n";

// Salvesta token faili (valikuline)
$tokenFile = __DIR__ . '/generated_token.txt';
file_put_contents($tokenFile, $token1);
echo "Token salvestatud faili: $tokenFile\n";
echo "HOIATUS: Kustuta see fail pärast kasutamist!\n\n";

echo "=== TURVALISUSE NÕUANDED ===\n";
echo "- Ära jaga tokenit avalikult\n";
echo "- Ära pane tokenit Git repositooriumisse\n";
echo "- Muuda tokenit regulaarselt\n";
echo "- Kasuta HTTPS-i API päringute jaoks\n";
?>
