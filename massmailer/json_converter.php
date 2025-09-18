<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON to CSV Konverter</title>
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
        input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
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
        #result {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
        .file-links {
            margin-top: 30px;
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .file-links h3 {
            color: #007bff;
            margin-bottom: 15px;
        }
        .file-link {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        .file-link:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }
        .file-size {
            font-size: 10px;
            opacity: 0.8;
        }
        .file-item {
            display: block;
            margin: 5px 0;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .file-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .file-item label {
            display: inline;
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }
        .convert-selected {
            margin-top: 15px;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .convert-selected:hover {
            background-color: #218838;
        }
        .select-all {
            margin-bottom: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>JSON to CSV Konverter</h1>
        <form action="json_to_csv_converter.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="jsonFile">Vali JSON fail:</label>
                <input type="file" id="jsonFile" name="jsonFile" accept=".json" required>
            </div>
            <button type="submit">Konverdi CSV-ks</button>
        </form>
        <div id="result"></div>
        
        <div class="file-links">
            <h3>Valdkonniti JSON failid</h3>
            <p>Vali failid ja konverdi need CSV formaati:</p>
            
            <form id="convertForm" action="json_to_csv_converter.php" method="post" enctype="multipart/form-data">
                <div class="select-all">
                    <label>
                        <input type="checkbox" id="selectAll"> Vali kõik failid
                    </label>
                </div>
                
                <div style="margin: 15px 0; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">
                    <label style="font-weight: bold; margin-bottom: 10px; display: block;">Konverteerimise viis:</label>
                    <label style="display: block; margin: 5px 0; font-weight: normal;">
                        <input type="radio" name="merge_option" value="merge" checked> 
                        Ühenda kõik failid ühte CSV faili (lisab source_file veeru)
                    </label>
                    <label style="display: block; margin: 5px 0; font-weight: normal;">
                        <input type="radio" name="merge_option" value="individual"> 
                        Loo igast failist eraldi CSV fail
                    </label>
                </div>

             
                
                <?php
                $valdkonniti_dir = '/Applications/MAMP/htdocs/webfight_turundus/valdkonniti/';
                $base_url = 'http://localhost/webfight_turundus/valdkonniti/';
                
                // Function to format file size
                function formatBytes($size, $precision = 0) {
                    $units = array('B', 'KB', 'MB', 'GB');
                    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
                        $size /= 1024;
                    }
                    return round($size, $precision) . ' ' . $units[$i];
                }
                
                if (is_dir($valdkonniti_dir)) {
                    $files = glob($valdkonniti_dir . '*.json');
                    
                    if (!empty($files)) {
                        // Create array with file info for sorting
                        $file_info = array();
                        foreach ($files as $file) {
                            $file_info[] = array(
                                'path' => $file,
                                'name' => basename($file),
                                'size' => filesize($file),
                                'mtime' => filemtime($file)
                            );
                        }
                        
                        // Sort by modification time (newest first)
                        usort($file_info, function($a, $b) {
                            return $b['mtime'] - $a['mtime'];
                        });
                        
                        foreach ($file_info as $file) {
                            $filename = $file['name'];
                            $filesize = $file['size'];
                            $formatted_size = formatBytes($filesize);
                            $file_date = date('d.m.Y H:i', $file['mtime']);
                            $file_url = $base_url . $filename;
                            
                            echo '<div class="file-item">';
                            echo '<label>';
                            echo '<input type="checkbox" name="selected_files[]" value="' . htmlspecialchars($filename) . '">';
                            echo htmlspecialchars($filename) . ' <span class="file-size">(' . $formatted_size . ' - ' . $file_date . ')</span>';
                            echo '</label>';
                            echo ' <a href="' . htmlspecialchars($file_url) . '" class="file-link" target="_blank" style="margin-left: 10px; font-size: 10px;">Vaata</a>';
                            echo '</div>' . "\n                ";
                        }
                    } else {
                        echo '<p>Valdkonniti kaustas JSON faile ei leitud.</p>';
                    }
                } else {
                    echo '<p>Valdkonniti kausta ei leitud.</p>';
                }
                ?>
                
                <button type="submit" class="convert-selected" id="convertBtn" disabled>
                    Konverdi valitud failid CSV-ks
                </button>
            </form>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const selectAllCheckbox = document.getElementById('selectAll');
                    const fileCheckboxes = document.querySelectorAll('input[name="selected_files[]"]');
                    const convertBtn = document.getElementById('convertBtn');
                    
                    // Select/deselect all functionality
                    selectAllCheckbox.addEventListener('change', function() {
                        fileCheckboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                        updateConvertButton();
                    });
                    
                    // Individual checkbox change
                    fileCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            updateSelectAllState();
                            updateConvertButton();
                        });
                    });
                    
                    function updateSelectAllState() {
                        const checkedCount = document.querySelectorAll('input[name="selected_files[]"]:checked').length;
                        selectAllCheckbox.checked = checkedCount === fileCheckboxes.length;
                        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < fileCheckboxes.length;
                    }
                    
                    function updateConvertButton() {
                        const checkedCount = document.querySelectorAll('input[name="selected_files[]"]:checked').length;
                        convertBtn.disabled = checkedCount === 0;
                        convertBtn.textContent = checkedCount > 0 ? 
                            `Konverdi ${checkedCount} faili CSV-ks` : 
                            'Konverdi valitud failid CSV-ks';
                    }
                });
            </script>
        </div>
    </div>
</body>
</html>
