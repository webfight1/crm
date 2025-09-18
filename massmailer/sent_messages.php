<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saadetud Sõnumid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .message-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s;
        }
        .message-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .message-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            border-radius: 8px 8px 0 0;
        }
        .message-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .message-details {
            flex: 1;
        }
        .message-actions {
            margin-left: 15px;
        }
        .file-size {
            color: #6c757d;
            font-size: 0.9em;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .no-messages {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-envelope-open"></i> Saadetud Sõnumid</h1>
                    <a href="mass_mailer_form.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Tagasi
                    </a>
                </div>

                <div class="search-box">
                    <input type="text" id="searchInput" class="form-control" placeholder="Otsi e-maili aadressi või ettevõtte nime järgi...">
                </div>

                <div id="messagesList">
                    <?php
                    $sentDir = __DIR__ . '/sent/';
                    
                    if (!file_exists($sentDir)) {
                        echo '<div class="no-messages">';
                        echo '<i class="fas fa-inbox fa-3x mb-3"></i>';
                        echo '<h3>Saadetud sõnumeid ei leitud</h3>';
                        echo '<p>Kui saadate e-maile, ilmuvad need siia.</p>';
                        echo '</div>';
                    } else {
                        $files = glob($sentDir . '*.html');
                        
                        if (empty($files)) {
                            echo '<div class="no-messages">';
                            echo '<i class="fas fa-inbox fa-3x mb-3"></i>';
                            echo '<h3>Saadetud sõnumeid ei leitud</h3>';
                            echo '<p>Kui saadate e-maile, ilmuvad need siia.</p>';
                            echo '</div>';
                        } else {
                            // Sort files by modification time (newest first)
                            usort($files, function($a, $b) {
                                return filemtime($b) - filemtime($a);
                            });
                            
                            foreach ($files as $file) {
                                $filename = basename($file);
                                $filesize = filesize($file);
                                $modtime = filemtime($file);
                                
                                // Parse filename to extract info
                                $parts = explode('_', $filename);
                                $timestamp = $parts[0] . '_' . $parts[1];
                                $email = str_replace('.html', '', implode('_', array_slice($parts, 2)));
                                $email = str_replace('_', '.', $email);
                                $email = str_replace('..', '@', $email);
                                
                                // Read file content to extract subject and company
                                $content = file_get_contents($file);
                                preg_match('/<strong>Ettevõte:<\/strong>\s*([^<]+)/', $content, $companyMatches);
                                preg_match('/<strong>Teema:<\/strong>\s*([^<]+)/', $content, $subjectMatches);
                                
                                $company = isset($companyMatches[1]) ? trim($companyMatches[1]) : 'N/A';
                                $subject = isset($subjectMatches[1]) ? trim($subjectMatches[1]) : 'N/A';
                                
                                echo '<div class="message-card" data-email="' . htmlspecialchars($email) . '" data-company="' . htmlspecialchars($company) . '">';
                                echo '<div class="message-header">';
                                echo '<div class="message-info">';
                                echo '<div class="message-details">';
                                echo '<h5 class="mb-1"><i class="fas fa-envelope"></i> ' . htmlspecialchars($email) . '</h5>';
                                echo '<p class="mb-1"><strong>Ettevõte:</strong> ' . htmlspecialchars($company) . '</p>';
                                echo '<p class="mb-1"><strong>Teema:</strong> ' . htmlspecialchars($subject) . '</p>';
                                echo '<small class="text-muted">';
                                echo '<i class="fas fa-clock"></i> ' . date('d.m.Y H:i:s', $modtime);
                                echo ' | <span class="file-size">' . number_format($filesize / 1024, 1) . ' KB</span>';
                                echo '</small>';
                                echo '</div>';
                                echo '<div class="message-actions">';
                                echo '<a href="sent/' . $filename . '" target="_blank" class="btn btn-sm btn-outline-primary me-2">';
                                echo '<i class="fas fa-eye"></i> Vaata';
                                echo '</a>';
                                echo '<a href="sent/' . $filename . '" download class="btn btn-sm btn-outline-secondary">';
                                echo '<i class="fas fa-download"></i> Laadi';
                                echo '</a>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const messageCards = document.querySelectorAll('.message-card');
            
            messageCards.forEach(function(card) {
                const email = card.getAttribute('data-email').toLowerCase();
                const company = card.getAttribute('data-company').toLowerCase();
                
                if (email.includes(searchTerm) || company.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
