<?php
session_start();
set_time_limit(0);

// E-maili saatmise seaded
$smtp_server = 'mail.zone.eu';
$smtp_port = 25;
$from_email = 'veiko@webfight.ee';
$max_emails = 5000;

// Funktsioon e-maili saatmiseks
function sendEmail($to, $subject, $message, $from) {
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

if ($_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
    $csvFile = $_FILES['csvFile']['tmp_name'];
    $emailColumn = $_POST['emailColumn'];
    $nameColumn = isset($_POST['nameColumn']) ? $_POST['nameColumn'] : '';
    $subject = $_POST['subject'];
    $subjectRu = isset($_POST['subjectRu']) ? $_POST['subjectRu'] : '';
    $message = $_POST['message'];
    $messageRu = isset($_POST['messageRu']) ? $_POST['messageRu'] : '';
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        $emailColumnIndex = array_search($emailColumn, $headers);
        $nameColumnIndex = false;
        
        if ($emailColumnIndex === false) {
            $_SESSION['mailer_error'] = "Viga: E-maili veergu '$emailColumn' ei leitud!";
            exit;
        }
        
        // Find name column index if specified
        if (!empty($nameColumn)) {
            $nameColumnIndex = array_search($nameColumn, $headers);
        }
        
        // Loe välistatud e-mailid
        $excludedEmails = [];
        $excludedFile = __DIR__ . '/excluded_emails.csv';
        if (file_exists($excludedFile) && ($excludeHandle = fopen($excludedFile, 'r')) !== FALSE) {
            // Skip header
            fgetcsv($excludeHandle);
            while (($data = fgetcsv($excludeHandle)) !== FALSE) {
                $excludedEmail = trim($data[0]);
                if (!empty($excludedEmail)) {
                    $excludedEmails[] = strtolower($excludedEmail);
                }
            }
            fclose($excludeHandle);
        }

        // Loe kõik e-mailid ja nimed massiivi
        $emailData = [];
        while (($data = fgetcsv($handle)) !== FALSE) {
            $email = trim($data[$emailColumnIndex]);
            $emailLower = strtolower($email);
            $companyName = '';
            
            // Get company name if name column exists
            if ($nameColumnIndex !== false && isset($data[$nameColumnIndex])) {
                $companyName = trim($data[$nameColumnIndex]);
            }
            
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Skip Gmail, Hotmail addresses and emails in excluded list
                if (stripos($email, '@gmail.com') === false && 
                    stripos($email, '@hotmail.') === false &&
                    stripos($email, '@hot.ee') === false &&
                    stripos($email, '@mail.ee') === false &&
                    !in_array($emailLower, $excludedEmails)) {
                    $emailData[] = [
                        'email' => $email,
                        'company_name' => $companyName
                    ];
                }
            }
        }
        fclose($handle);
        
        $totalEmails = count($emailData);
        if ($totalEmails > $max_emails) {
            $_SESSION['mailer_error'] = "Viga: CSV failis on $totalEmails e-maili. Maksimaalne lubatud arv on $max_emails.";
            exit;
        }
        
        // Salvesta info sessiooni
        $_SESSION['mailer'] = [
            'emailData' => $emailData,
            'total' => $totalEmails,
            'current' => 0,
            'success' => 0,
            'errors' => 0,
            'subject' => $subject,
            'subjectRu' => $subjectRu,
            'message' => $message,
            'messageRu' => $messageRu,
            'from' => $from_email,
            'status' => 'running',
            'last_message' => '',
            'is_error' => false
        ];
        
        echo "OK"; // Näita, et saatmine algas
        
    } else {
        $_SESSION['mailer_error'] = "Viga: CSV faili ei õnnestunud avada!";
    }
} else {
    $_SESSION['mailer_error'] = "Viga: CSV faili üleslaadimisel tekkis viga!";
}
