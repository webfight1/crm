<?php
set_time_limit(0); // Eemalda ajalimiit pikaks protsessiks

// E-maili saatmise seaded
$smtp_server = 'mail.zone.eu';
$smtp_port = 25;
$from_email = 'veiko@webfight.ee';
$max_emails = 500; // Maksimaalne kirjade arv

// Funktsioon e-maili saatmiseks
function sendEmail($to, $subject, $message, $from) {
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// HTML vorm
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="et">
    <head>
        
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mass E-mailide Saatja</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 20px auto;
                padding: 20px;
            }
            .container {
                background-color: #f5f5f5;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .form-group {
                margin-bottom: 15px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            input[type="file"],
            input[type="text"],
            textarea {
                width: 100%;
                padding: 8px;
                margin-bottom: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            button {
                background-color: #4CAF50;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            button:hover {
                background-color: #45a049;
            }
            .warning {
                color: #856404;
                background-color: #fff3cd;
                border: 1px solid #ffeeba;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 4px;
            }
            #progress {
                display: none;
                margin-top: 20px;
                padding: 20px;
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .progress-bar {
                height: 20px;
                background: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            .progress-bar-fill {
                height: 100%;
                background: #4CAF50;
                width: 0%;
                transition: width 0.5s ease;
            }
            .log {
                max-height: 300px;
                overflow-y: auto;
                background: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #ddd;
                margin-top: 10px;
            }
            .success {
                color: #155724;
            }
            .error {
                color: #721c24;
            }
            .timer {
                font-size: 0.9em;
                color: #666;
                margin-top: 5px;
            }
        </style>
    </head>

    <body>

        <div class="container">
            <h1>Mass E-mailide Saatja</h1>
            <div class="warning">
                Märkus: Maksimaalne kirjade arv on <?php echo $max_emails; ?>
            </div>
            <form id="emailForm" action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csvFile">CSV fail e-maili aadressidega:</label>
                    <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
                </div>
                <div class="form-group">
                    <label for="emailColumn">E-maili aadressi veeru nimi CSV failis:</label>
                    <input type="text" id="emailColumn" name="emailColumn" required>
                </div>
                <div class="form-group">
                    <label for="subject">E-maili teema:</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="message">E-maili sisu (HTML lubatud):</label>
                    <textarea id="message" name="message" rows="10" required></textarea>
                </div>
                <button type="submit">Alusta saatmist</button>
            </form>
            
            <div id="progress">
                <h3>Saatmise progress</h3>
                <div class="progress-bar">
                    <div class="progress-bar-fill"></div>
                </div>
                <div class="progress-info">
                    <span id="progress-text">0/0 kirja saadetud</span>
                    <div class="timer">
                        Järgmine kiri: <span id="countdown">7</span> sekundi pärast
                    </div>
                </div>
                <div class="log" id="log"></div>
            </div>
        </div>

        <script>
        document.getElementById('emailForm').onsubmit = function() {
            document.getElementById('progress').style.display = 'block';
        };

        let logCount = 0;
        
        function updateProgress(current, total, message, isError = false) {
            const progressBar = document.querySelector('.progress-bar-fill');
            const progressText = document.getElementById('progress-text');
            const log = document.getElementById('log');
            
            // Uuenda progressiriba
            const percentage = (current / total) * 100;
            progressBar.style.width = percentage + '%';
            
            // Uuenda progressiteksti
            progressText.textContent = `${current}/${total} kirja saadetud (${percentage.toFixed(1)}%)`;
            
            // Lisa logi
            const logEntry = document.createElement('div');
            logEntry.className = isError ? 'error' : 'success';
            logEntry.textContent = `[${++logCount}] ${message}`;
            log.insertBefore(logEntry, log.firstChild);
            
            // Käivita countdown timer
            startCountdown();
        }

        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            let timeLeft = 7;
            
            const timer = setInterval(() => {
                timeLeft--;
                countdownElement.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Kirjade saatmise loogika
if ($_FILES['csvFile']['error'] === UPLOAD_ERR_OK) {
    // Väljasta HTML päis
    ?>
    <!DOCTYPE html>
    <html lang="et">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>E-mailide saatmine...</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .success { color: #155724; }
            .error { color: #721c24; }
        </style>
    </head>
    <body>
        <div id="progress"></div>
        <script>
            function updateStatus(current, total, message, isError = false) {
                parent.updateProgress(current, total, message, isError);
            }
        </script>
    <?php

    $csvFile = $_FILES['csvFile']['tmp_name'];
    $emailColumn = $_POST['emailColumn'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle);
        $emailColumnIndex = array_search($emailColumn, $headers);
        
        if ($emailColumnIndex === false) {
            die("Viga: E-maili veergu '$emailColumn' ei leitud!");
        }
        
        $emails = [];
        while (($data = fgetcsv($handle)) !== FALSE) {
            $email = trim($data[$emailColumnIndex]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        fclose($handle);
        
        $totalEmails = count($emails);
        if ($totalEmails > $max_emails) {
            die("Viga: CSV failis on $totalEmails e-maili. Maksimaalne lubatud arv on $max_emails.");
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($emails as $email) {
            if (sendEmail($email, $subject, $message, $from_email)) {
                $successCount++;
                $message = "E-mail saadetud: $email";
                echo "<script>updateStatus($successCount, $totalEmails, '$message');</script>";
            } else {
                $errorCount++;
                $message = "Viga saatmisel: $email";
                echo "<script>updateStatus($successCount, $totalEmails, '$message', true);</script>";
            }
            
            ob_flush();
            flush();
            sleep(7);
        }
        
        echo "<h2>Kokkuvõte:</h2>";
        echo "Kokku e-maile: $totalEmails<br>";
        echo "Edukalt saadetud: $successCount<br>";
        echo "Ebaõnnestunud: $errorCount<br>";
        echo "<br><a href='".$_SERVER['PHP_SELF']."'>← Tagasi vormi juurde</a>";
        
    } else {
        die("Viga: CSV faili ei õnnestunud avada!");
    }
    
    echo "</body></html>";
    
} else {
    die("Viga: CSV faili üleslaadimisel tekkis viga!");
}
