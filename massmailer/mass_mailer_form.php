<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        button  {
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
        .success { color: #155724; }
        .error { color: #721c24; }
        .timer {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .preview-container {
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
        }
        .preview-header {
            background: #f8f9fa;
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-content {
            padding: 15px;
            min-height: 100px;
            max-height: 300px;
            overflow-y: auto;
        }
        .preview-toggle {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .preview-toggle:hover {
            background: #e9ecef;
        }
        .preview-container.collapsed .preview-content {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mass E-mailide Saatja</h1>
        <div class="warning">
            Märkus: Maksimaalne kirjade arv on 5000
        </div>
        <div class="row">

            <div class="col-md-6">
                <a href="sent_messages.php" class="btn btn-success btn-lg  btn-block my-2 float-right">
                    <i class="fas fa-envelope-open"></i> Saadetud Sõnumid
                </a>
            </div>
        </div>
        
        <form id="emailForm">
            <div class="form-group">
                <label for="csvFile">CSV fail e-maili aadressidega:</label>
                <input type="file" id="csvFile" name="csvFile" accept=".csv" required>
            </div>
            <div class="form-group">
                <label for="emailColumn">E-maili aadressi veeru nimi CSV failis:</label>
                <input type="text" id="emailColumn" name="emailColumn" required>
            </div>
            <div class="form-group">
                <label for="nameColumn">Ettevõtte nime veeru nimi CSV failis (valikuline):</label>
                <input type="text" id="nameColumn" name="nameColumn" placeholder="name" value="name">
                <small style="color: #666; font-size: 12px;">Kasuta {company_name} muutujat e-maili sisus, et lisada ettevõtte nimi</small>
            </div>
            <div class="form-group">
                <label for="subject">E-maili teema:</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="subjectRu">Vene keele teema (.ru e-mailidele):</label>
                <input type="text" id="subjectRu" name="subjectRu" placeholder="Sisesta vene keele teema .ru lõpuga e-maili aadressidele...">
            </div>
            <div class="form-group">
                <label for="message">E-maili sisu (HTML lubatud):</label>
                <textarea id="message" name="message" rows="10" required></textarea>
                <div class="preview-container" id="previewContainer">
                    <div class="preview-header">
                        <span>HTML Eelvaade</span>
                        <button type="button" class="preview-toggle" onclick="togglePreview('previewContainer')">Peida</button>
                    </div>
                    <div class="preview-content" id="previewContent">
                        <em>Sisesta HTML sisu üleval, et näha eelvaadet...</em>
                    </div>
                </div>

            </div>
            <div class="form-group">
                <label for="messageRu">Vene keele tõlge (.ru e-mailidele):</label>
                <textarea id="messageRu" name="messageRu" rows="10" placeholder="Sisesta vene keele tõlge .ru lõpuga e-maili aadressidele saatmiseks..."></textarea>
                <div class="preview-container" id="previewContainerRu">
                    <div class="preview-header">
                        <span>HTML Eelvaade (Vene keel)</span>
                        <button type="button" class="preview-toggle" onclick="togglePreview('previewContainerRu')">Peida</button>
                    </div>
                    <div class="preview-content" id="previewContentRu">
                        <em>Sisesta vene keele HTML sisu üleval, et näha eelvaadet...</em>
                    </div>
                </div>
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
    document.getElementById('emailForm').onsubmit = function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        document.getElementById('progress').style.display = 'block';
        
        // Alusta saatmist
        fetch('mass_mailer_send.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Saatmine algas, alusta progressi jälgimist
            checkProgress();
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    let logCount = 0;
    let countdownTimer = null;
    
    function checkProgress() {
        fetch('mass_mailer_progress.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'running' || data.status === 'completed') {
                updateProgress(data.current, data.total, data.message, data.isError);
                
                // Uuenda countdown timer
                if (data.nextSendIn > 0) {
                    startCountdown(data.nextSendIn);
                }
                
                if (data.status === 'running') {
                    setTimeout(checkProgress, 1000);
                }
            }
        });
    }

    function updateProgress(current, total, message, isError = false) {
        const progressBar = document.querySelector('.progress-bar-fill');
        const progressText = document.getElementById('progress-text');
        const log = document.getElementById('log');
        
        // Uuenda progressiriba
        const percentage = (current / total) * 100;
        progressBar.style.width = percentage + '%';
        
        // Uuenda progressiteksti
        progressText.textContent = `${current}/${total} kirja saadetud (${percentage.toFixed(1)}%)`;
        
        // Lisa logi kui on uus sõnum
        if (message) {
            const logEntry = document.createElement('div');
            logEntry.className = isError ? 'error' : 'success';
            logEntry.textContent = `[${++logCount}] ${message}`;
            log.insertBefore(logEntry, log.firstChild);
        }
    }

    function startCountdown(seconds) {
        const countdownElement = document.getElementById('countdown');
        
        // Tühista eelmine taimer kui see eksisteerib
        if (countdownTimer) {
            clearInterval(countdownTimer);
        }
        
        countdownElement.textContent = seconds;
        
        countdownTimer = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownTimer);
            }
        }, 1000);
    }
    
    // HTML preview functionality
    function togglePreview(containerId) {
        const container = document.getElementById(containerId);
        const button = container.querySelector('.preview-toggle');
        
        container.classList.toggle('collapsed');
        button.textContent = container.classList.contains('collapsed') ? 'Näita' : 'Peida';
    }
    
    function updatePreview(textareaId, previewId) {
        const textarea = document.getElementById(textareaId);
        const preview = document.getElementById(previewId);
        
        if (textarea.value.trim() === '') {
            preview.innerHTML = '<em>Sisesta HTML sisu üleval, et näha eelvaadet...</em>';
        } else {
            preview.innerHTML = textarea.value;
        }
    }
    
    // Add event listeners for real-time preview updates
    document.addEventListener('DOMContentLoaded', function() {
        const messageTextarea = document.getElementById('message');
        const messageRuTextarea = document.getElementById('messageRu');
        
        // Update preview on input
        messageTextarea.addEventListener('input', function() {
            updatePreview('message', 'previewContent');
        });
        
        messageRuTextarea.addEventListener('input', function() {
            updatePreview('messageRu', 'previewContentRu');
        });
        
        // Update preview on paste
        messageTextarea.addEventListener('paste', function() {
            setTimeout(() => updatePreview('message', 'previewContent'), 10);
        });
        
        messageRuTextarea.addEventListener('paste', function() {
            setTimeout(() => updatePreview('messageRu', 'previewContentRu'), 10);
        });
    });
    </script>
</body>
</html>
