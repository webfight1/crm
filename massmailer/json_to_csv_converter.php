<?php
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Check if this is a multi-file conversion from selected files
    if (isset($_POST['selected_files']) && !empty($_POST['selected_files'])) {
        // Handle multiple file conversion
        $selectedFiles = $_POST['selected_files'];
        $mergeOption = $_POST['merge_option'] ?? 'merge';
        $valdkonnitiDir = '/Applications/MAMP/htdocs/webfight_turundus/valdkonniti/';
        
        if ($mergeOption === 'merge') {
            // Merge all files into one CSV
            $csvFilename = 'merged_' . time() . '.csv';
            $csvPath = $uploadDir . $csvFilename;
            $fp = fopen($csvPath, 'w');
            
            if (!$fp) {
                die("Viga CSV faili loomisel.");
            }
            
            $headerWritten = false;
            $allData = [];
            
            foreach ($selectedFiles as $filename) {
                $filePath = $valdkonnitiDir . $filename;
                if (file_exists($filePath)) {
                    $jsonString = file_get_contents($filePath);
                    $data = json_decode($jsonString, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                        foreach ($data as $row) {
                            if (is_array($row)) {
                                $row['source_file'] = $filename;
                                $allData[] = $row;
                            }
                        }
                    }
                }
            }
            
            if (!empty($allData)) {
                // Write header
                fputcsv($fp, array_keys(reset($allData)));
                // Write data
                foreach ($allData as $row) {
                    fputcsv($fp, $row);
                }
            }
            
            fclose($fp);
            
            echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
            echo "<p style='color: green;'>Konverteerimine õnnestus! " . count($selectedFiles) . " faili ühendatud.</p>";
            echo "<p>Sinu CSV fail on valmis: <a href='uploads/{$csvFilename}' download>Lae alla {$csvFilename}</a></p>";
            echo "<p><a href='json_converter.php'>← Tagasi konverteri juurde</a></p>";
            echo "</div>";
            exit;
            
        } else {
            // Create individual CSV files
            $createdFiles = [];
            
            foreach ($selectedFiles as $filename) {
                $filePath = $valdkonnitiDir . $filename;
                if (file_exists($filePath)) {
                    $jsonString = file_get_contents($filePath);
                    $data = json_decode($jsonString, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
                        $csvFilename = pathinfo($filename, PATHINFO_FILENAME) . '_' . time() . '.csv';
                        $csvPath = $uploadDir . $csvFilename;
                        $fp = fopen($csvPath, 'w');
                        
                        if ($fp) {
                            if (is_array(reset($data))) {
                                fputcsv($fp, array_keys(reset($data)));
                                foreach ($data as $row) {
                                    fputcsv($fp, $row);
                                }
                            }
                            fclose($fp);
                            $createdFiles[] = $csvFilename;
                        }
                    }
                }
            }
            
            echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
            echo "<p style='color: green;'>Konverteerimine õnnestus! " . count($createdFiles) . " CSV faili loodud.</p>";
            foreach ($createdFiles as $file) {
                echo "<p><a href='uploads/{$file}' download>Lae alla {$file}</a></p>";
            }
            echo "<p><a href='json_converter.php'>← Tagasi konverteri juurde</a></p>";
            echo "</div>";
            exit;
        }
    }

    // Check if single file was uploaded
    if (!isset($_FILES['jsonFile']) || $_FILES['jsonFile']['error'] === UPLOAD_ERR_NO_FILE) {
        die("Palun vali JSON fail ja proovi uuesti.");
    }

    $jsonFile = $_FILES['jsonFile'];
    
    // Check for upload errors
    if ($jsonFile['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "Fail on liiga suur (ületab server.ini max_file_size).",
            UPLOAD_ERR_FORM_SIZE => "Fail on liiga suur (ületab vormi MAX_FILE_SIZE).",
            UPLOAD_ERR_PARTIAL => "Fail laaditi üles ainult osaliselt.",
            UPLOAD_ERR_NO_TMP_DIR => "Ajutine kaust puudub.",
            UPLOAD_ERR_CANT_WRITE => "Faili kirjutamine kettale ebaõnnestus.",
            UPLOAD_ERR_EXTENSION => "PHP laiendus peatas faili üleslaadimise."
        ];
        
        $errorMsg = isset($errorMessages[$jsonFile['error']]) 
            ? $errorMessages[$jsonFile['error']] 
            : "Tundmatu viga faili üleslaadimisel.";
            
        die("Faili üleslaadimisel tekkis viga: " . $errorMsg);
    }

    // Check if file is empty
    if ($jsonFile['size'] === 0) {
        die("Valitud fail on tühi. Palun vali korrektne JSON fail.");
    }

    // Validate file type
    $fileType = mime_content_type($jsonFile['tmp_name']);
    if ($fileType !== 'application/json' && !strpos($jsonFile['name'], '.json')) {
        die("Palun lae üles ainult JSON fail.");
    }

    // Read and decode JSON
    $jsonString = file_get_contents($jsonFile['tmp_name']);
    $data = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Vigane JSON fail - " . json_last_error_msg());
    }

    // Generate unique filename for CSV
    $csvFilename = 'converted_' . time() . '.csv';
    $csvPath = $uploadDir . $csvFilename;

    // Open CSV file for writing
    $fp = fopen($csvPath, 'w');
    if (!$fp) {
        die("Viga CSV faili loomisel.");
    }

    // Write headers if the data is not empty
    if (!empty($data)) {
        if (is_array(reset($data))) {
            // If data is array of arrays/objects
            fputcsv($fp, array_keys(reset($data)));
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        } else {
            // If data is a single object/array
            fputcsv($fp, array_keys($data));
            fputcsv($fp, $data);
        }
    }

    fclose($fp);

    // Return success message with download link
    echo "<div style='font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<p style='color: green;'>Konverteerimine õnnestus!</p>";
    echo "<p>Sinu CSV fail on valmis: <a href='uploads/{$csvFilename}' download>Lae alla {$csvFilename}</a></p>";
    echo "<p><a href='json_converter.html'>← Tagasi konverteri juurde</a></p>";
    echo "</div>";
    exit;
}

// If accessed directly without POST request
header('Location: json_converter.html');
