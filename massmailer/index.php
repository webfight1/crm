<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isemajandav - Andmete Töötlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .hero-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 60px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #28a745;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">Isemajandav</h1>
                    <p class="lead mb-4">Andmete töötluse ja analüüsi tööriistad</p>
                    <p class="mb-0">Valige allpool sobiv funktsioon:</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row g-4">
            <!-- JSON to CSV Converter -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-arrow-left-right feature-icon mb-3"></i>
                        <h5 class="card-title">JSON to CSV Converter</h5>
                        <p class="card-text">Konverteeri JSON failid CSV formaati. Lihtne ja kiire andmete teisendamine.</p>
                        <a href="json_converter.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left-right me-2"></i>Ava konverter
                        </a>
                    </div>
                </div>
            </div>

            <!-- Website Analyzer -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-globe feature-icon mb-3"></i>
                        <h5 class="card-title">Veebilehtede Analüsaator</h5>
                        <p class="card-text">Python skript veebilehtede analüüsimiseks ja andmete kogumiseks.</p>
                        <a href="website_analyzer.py" class="btn btn-success" target="_blank">
                            <i class="bi bi-code-slash me-2"></i>Vaata koodi
                        </a>
                    </div>
                </div>
            </div>

            <!-- Uploaded Files -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-folder feature-icon mb-3"></i>
                        <h5 class="card-title">Üleslaetud Failid</h5>
                        <p class="card-text">Vaata ja halda üleslaetud CSV faile ja nende analüüse.</p>
                        <a href="uploads/" class="btn btn-warning">
                            <i class="bi bi-folder me-2"></i>Ava kaust
                        </a>
                    </div>
                </div>
            </div>

            <!-- Email Management -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-envelope feature-icon mb-3"></i>
                        <h5 class="card-title">E-mailide Haldus</h5>
                        <p class="card-text">Vaata välistatud e-mailide nimekirja ja halda kontakte.</p>
                        <a href="excluded_emails.csv" class="btn btn-info" target="_blank">
                            <i class="bi bi-envelope me-2"></i>Vaata nimekirja
                        </a>
                    </div>
                </div>
            </div>

            <!-- Requirements -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-list-check feature-icon mb-3"></i>
                        <h5 class="card-title">Nõuded</h5>
                        <p class="card-text">Vaata projekti sõltuvusi ja nõudeid Python keskkonna jaoks.</p>
                        <a href="requirements.txt" class="btn btn-outline-primary" target="_blank">
                            <i class="bi bi-file-text me-2"></i>requirements.txt
                        </a>
                    </div>
                </div>
            </div>

            <!-- Back to Main -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-house feature-icon mb-3"></i>
                        <h5 class="card-title">Tagasi Pealeheküljele</h5>
                        <p class="card-text">Mine tagasi Webfight Turunduse pealeheküljele.</p>
                        <a href="../webfight_turundus/" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Webfight Turundus
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>Süsteemi Info</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <strong>Projektikaust:</strong> isemajandav<br>
                                    <strong>Server:</strong> localhost (MAMP)
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <strong>PHP versioon:</strong> <?php echo phpversion(); ?><br>
                                    <strong>Viimati uuendatud:</strong> <?php echo date('d.m.Y H:i'); ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <strong>Failide arv:</strong> 
                                    <?php 
                                    $files = glob('*.{php,html,py,csv,txt}', GLOB_BRACE);
                                    echo count($files);
                                    ?><br>
                                    <strong>Uploads kaust:</strong>
                                    <?php 
                                    $uploads = glob('uploads/*.csv');
                                    echo count($uploads) . ' CSV faili';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2024 Isemajandav - Andmete Töötluse Süsteem</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
