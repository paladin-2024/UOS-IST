<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
require_once dirname(__DIR__) . '/../models/Universite.php';

$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Présence par QR Code | <?= htmlspecialchars($configUniversite['nom'] ?? 'E-GESTION') ?></title>

    <!-- Favicons -->
    <?php if (!empty($configUniversite['logo'])): ?>
        <link href="../<?= htmlspecialchars($configUniversite['logo']) ?>" rel="icon">
    <?php endif; ?>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Animate.css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --success-color: #4CAF50;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
            --header-height: 70px;
        }

        /* Image de fond avec flou */
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../uploads/inbtp-student.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(2px);
            opacity: 0.2;
            z-index: -1;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 80px;
            position: relative;
            padding-top: calc(var(--header-height) + 10px);
            background: linear-gradient(135deg, rgba(245, 247, 250, 0.9) 0%, rgba(228, 233, 242, 0.9) 100%);
        }

        .header {
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            height: var(--header-height);
            display: flex;
            align-items: center;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
        }

        .logo {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .logo img {
            max-height: 35px;
            margin-right: 10px;
        }

        .main {
            padding: 20px 0 50px 0;
        }

        .footer {
            background-color: #fff;
            padding: 20px 0;
            font-size: 14px;
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }

        .copyright {
            text-align: center;
        }

        .card {
            border: none;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.12);
            border-radius: 15px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            margin-bottom: 30px;
        }

        .card-body {
            padding: 2.5rem;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #ced4da;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
        }



        .matricule-verification-section {
            max-width: 600px;
            margin: 30px auto 0;
        }

        .verification-card {
            padding: 40px;
            text-align: center;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(0);
            transition: all 0.5s;
        }

        .verification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .verification-card .icon {
            font-size: 70px;
            color: var(--primary-color);
            margin-bottom: 25px;
            display: inline-block;
        }

        .verification-card h2 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 700;
        }

        .verification-card p {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .qr-scanner-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
        }

        #qr-video {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 15px;
            box-shadow: 0 0 0 1000px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }

        .scanner-laser {
            position: absolute;
            top: 50%;
            width: 100%;
            height: 2px;
            background: var(--accent-color);
            box-shadow: 0 0 8px var(--accent-color);
            animation: scan 2s infinite;
        }

        @keyframes scan {
            0% {
                transform: translateY(-100px);
            }

            50% {
                transform: translateY(100px);
            }

            100% {
                transform: translateY(-100px);
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-height: 60px;
            }

            .header .container {
                padding: 0 15px;
            }

            .logo {
                font-size: 1.2rem;
            }

            .logo img {
                max-height: 30px;
            }

            .verification-card {
                padding: 25px;
            }

            .card-body {
                padding: 1.5rem;
            }


        }

        .qr-scanner-container {
            width: 100%;
            max-width: 500px;
            height: 300px;
            margin: 0 auto 20px auto;
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background-color: #000;
        }

        #qr-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .scanner-laser {
            position: absolute;
            top: 50%;
            width: 100%;
            height: 2px;
            background: var(--accent-color);
            box-shadow: 0 0 8px var(--accent-color);
            animation: scan 2s infinite;
            z-index: 3;
        }

        /* Empêcher le défilement pendant le scan */
        body.scanning {
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100%;
        }
    </style>
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>

</head>

<body>
    <div class="background-image"></div>

    <header id="header" class="header">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="presence_qrcode" class="logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="../<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <span><?= htmlspecialchars($configUniversite['sigle'] . ' - NUMERIQUE' ?? 'E-GESTION') ?></span>
            </a>
        </div>
    </header>

    <main id="main" class="main">
        <div class="container">
            <div class="matricule-verification-section" id="matriculeVerification" data-aos="fade-up" data-aos-delay="100">
                <div class="card verification-card">
                    <div class="card-body">
                        <div class="icon animate__animated animate__pulse animate__infinite">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <h2 class="card-title">Présence par QR Code</h2>
                        <p class="card-text">Veuillez saisir votre numéro matricule pour continuer</p>

                        <form id="matriculeForm" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <input type="text" class="form-control form-control-lg" id="matricule" placeholder="Votre matricule" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir votre matricule.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-search me-2"></i> Vérifier mon matricule
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div id="qrScannerSection" style="display: none;">


                <div class="card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Scanner le QR Code</h5>

                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Positionnez le QR code face à la caméra. Le scan se fera automatiquement.
                        </div>

                        <div class="qr-scanner-container mb-4">
                            <video id="qr-video" playsinline></video>
                            <div class="scanner-overlay">
                                <div class="scanner-laser"></div>
                            </div>
                        </div>

                        <div class="text-center mb-4">
                            <button id="toggleCameraBtn" class="btn btn-outline-primary">
                                <i class="bi bi-camera-video me-2"></i> Changer de caméra
                            </button>
                            <button id="pauseResumeBtn" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-pause-fill me-2"></i> Pause
                            </button>
                        </div>

                        <div id="qrResult" class="alert alert-success" style="display: none;">
                            <i class="bi bi-check-circle me-2"></i>
                            <span id="qrResultText">QR Code détecté!</span>
                        </div>

                        <div id="seanceInfo" class="mb-4" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Séance:</strong> <span id="seanceTitre"></span></p>
                                    <p><strong>Date:</strong> <span id="seanceDate"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Heure:</strong> <span id="seanceHeure"></span></p>
                                    <p><strong>Locale:</strong> <span id="seanceLabo"></span></p>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2" id="confirmationButtons" style="display: none;">
                            <button id="confirmPresenceBtn" class="btn btn-success btn-lg">
                                <i class="bi bi-check-circle me-2"></i> Confirmer ma présence
                            </button>
                            <a href="presence_qrcode" class="btn btn-outline-primary btn-lg mt-2">
                                <i class="bi bi-arrow-repeat me-2"></i> Scanner un autre QR code
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer id="footer" class="footer">
        <div class="container">
            <div class="copyright">
                &copy; <?= date('Y') ?> <strong><span><?= htmlspecialchars($configUniversite['nom'] ?? 'E-GESTION') ?></span></strong>. Tous droits réservés
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <!-- jsQR pour la détection de QR code -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser les animations AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true
            });

            // Éléments DOM
            const matriculeForm = document.getElementById('matriculeForm');
            const matriculeVerification = document.getElementById('matriculeVerification');
            const qrScannerSection = document.getElementById('qrScannerSection');
            //const studentNameElement = document.getElementById('studentName');
            const seanceInfo = document.getElementById('seanceInfo');
            const confirmationButtons = document.getElementById('confirmationButtons');
            const qrResult = document.getElementById('qrResult');

            // Variables globales
            let videoStream = null;
            let studentData = null;
            let seanceData = null;
            let isPaused = false;
            let scannerActive = false;

            let userLocation = null;
            const MAX_DISTANCE_METERS = 50; // Distance maximale autorisée en mètres

            // Ajouter cette fonction pour calculer la distance entre deux points géographiques
            function calculateDistance(lat1, lon1, lat2, lon2) {
                // Formule de Haversine pour calculer la distance entre deux points sur la Terre
                const R = 6371e3; // Rayon de la Terre en mètres
                const φ1 = lat1 * Math.PI / 180;
                const φ2 = lat2 * Math.PI / 180;
                const Δφ = (lat2 - lat1) * Math.PI / 180;
                const Δλ = (lon2 - lon1) * Math.PI / 180;

                const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                        Math.cos(φ1) * Math.cos(φ2) *
                        Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                const distance = R * c;
                
                return distance; // Distance en mètres
            }

            // Ajouter cette fonction pour obtenir la géolocalisation de l'utilisateur
            function getUserLocation() {
                return new Promise((resolve, reject) => {
                    if (!navigator.geolocation) {
                        reject(new Error('La géolocalisation n\'est pas prise en charge par votre navigateur'));
                        return;
                    }
                    
                    const options = {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    };
                    
                    navigator.geolocation.getCurrentPosition(
                        position => {
                            userLocation = {
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude,
                                accuracy: position.coords.accuracy
                            };
                            resolve(userLocation);
                        },
                        error => {
                            reject(error);
                        },
                        options
                    );
                });
            }


            // Vérification du matricule
            matriculeForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const matricule = document.getElementById('matricule').value;
                if (!matricule) {
                    document.getElementById('matricule').classList.add('is-invalid');
                    return;
                }

                verifyMatricule(matricule);
            });

            // Modifier la fonction verifyMatricule pour demander la géolocalisation dès le début
            // Modifier la fonction verifyMatricule pour ne plus demander la géolocalisation
function verifyMatricule(matricule) {
    // Afficher un indicateur de chargement
    Swal.fire({
        title: 'Vérification...',
        text: 'Nous vérifions votre matricule',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Vérifier le matricule directement sans géolocalisation
    fetch('../controller/verify_matricule_tempon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `matricule=${encodeURIComponent(matricule)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Matricule valide, afficher le message de succès
            Swal.fire({
                icon: 'success',
                title: 'Matricule validé!',
                text: `Bienvenue ${data.student.noms}! Vous pouvez maintenant scanner le QR code.`,
                confirmButtonText: 'Continuer',
                confirmButtonColor: '#4361ee'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Stocker les données de l'étudiant
                    studentData = data.student;
                    
                    // Masquer la section de vérification et afficher le scanner QR
                    matriculeVerification.style.display = 'none';
                    qrScannerSection.style.display = 'block';
                    
                    // Initialiser le scanner QR
                    prepareScanner();
                    
                    // Réinitialiser AOS pour animer les nouveaux éléments
                    setTimeout(() => {
                        AOS.refresh();
                    }, 100);
                }
            });
        } else {
            // Matricule invalide, afficher un message d'erreur
            Swal.fire({
                icon: 'error',
                title: 'Matricule non reconnu',
                text: 'Le matricule saisi n\'est pas reconnu dans notre système. Veuillez vérifier et réessayer.',
                confirmButtonText: 'Réessayer',
                confirmButtonColor: '#4361ee'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Erreur de connexion',
            text: 'Une erreur est survenue. Veuillez réessayer plus tard.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#4361ee'
        });
    });
}

// Supprimer ou commenter les fonctions liées à la géolocalisation
// function calculateDistance(lat1, lon1, lat2, lon2) { ... }
// function getUserLocation() { ... }

// Modifier la fonction savePresence pour ne plus utiliser la géolocalisation
function savePresence() {
    // Préparer les données à envoyer sans coordonnées géographiques
    const presenceData = {
        student_id: studentData.idetudiant,
        matricule: studentData.matricule,
        seance_id: seanceData.seance_id,
        type: seanceData.type,
        code: seanceData.code,
        latitude: 0,  // Valeur par défaut
        longitude: 0, // Valeur par défaut
        timestamp: new Date().toISOString(),
        client_datetime: new Date().toISOString() // Ajouter l'heure locale du client
    };
    
    // Afficher un indicateur de chargement
    Swal.fire({
        title: 'Enregistrement...',
        text: 'Nous enregistrons votre présence',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Envoyer les données au serveur
    fetch('../controller/save_presence.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(presenceData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Présence enregistrée avec succès
            Swal.fire({
                icon: 'success',
                title: 'Présence confirmée!',
                text: 'Votre présence a été enregistrée avec succès.',
                confirmButtonText: 'Terminer',
                confirmButtonColor: '#4CAF50'
            }).then(() => {
                // Rediriger vers la page de confirmation
                window.location.href = 'confirmation_presence';
            });
        } else {
            // Erreur lors de l'enregistrement
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'enregistrement de votre présence.',
                confirmButtonText: 'Réessayer',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                // Réinitialiser l'interface pour réessayer
                resetScannerInterface();
            });
        }
    })
    .catch(error => {
        console.error('Erreur lors de l\'enregistrement:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur. Veuillez réessayer plus tard.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#4361ee'
        }).then(() => {
            // Réinitialiser l'interface pour réessayer
            resetScannerInterface();
        });
    });
}


            // Fonction pour vérifier si la caméra est disponible
            function checkCameraAvailability() {
                return new Promise((resolve, reject) => {
                    // Vérifier si mediaDevices est supporté
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        reject(new Error('Votre navigateur ne supporte pas l\'accès à la caméra'));
                        return;
                    }

                    // Vérifier si la caméra est accessible
                    navigator.mediaDevices.getUserMedia({
                            video: true
                        })
                        .then(stream => {
                            // Arrêter immédiatement le flux pour libérer la caméra
                            stream.getTracks().forEach(track => track.stop());
                            resolve(true);
                        })
                        .catch(error => {
                            reject(error);
                        });
                });
            }

            // Utiliser cette fonction avant d'initialiser le scanner
            function prepareScanner() {
                Swal.fire({
                    title: 'Préparation...',
                    text: 'Nous préparons l\'accès à votre caméra',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                checkCameraAvailability()
                    .then(() => {
                        Swal.close();
                        initQRScanner();
                    })
                    .catch(error => {
                        console.error('Erreur de caméra:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Accès à la caméra impossible',
                            text: 'Veuillez autoriser l\'accès à la caméra dans les paramètres de votre navigateur.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4361ee'
                        });
                    });
            }

            // Initialiser le scanner QR avec l'API MediaDevices native
            function initQRScanner() {
                // Empêcher le défilement pendant le scan
                document.body.classList.add('scanning');

                // Masquer les éléments de résultat et confirmation
                qrResult.style.display = 'none';
                seanceInfo.style.display = 'none';
                confirmationButtons.style.display = 'none';

                // Créer les éléments nécessaires
                const qrContainer = document.querySelector('.qr-scanner-container');

                // Vider le conteneur avant d'ajouter de nouveaux éléments
                qrContainer.innerHTML = '';

                const videoElement = document.createElement('video');
                videoElement.id = 'qr-video';
                videoElement.playsinline = true; // Important pour iOS
                videoElement.autoplay = true;
                videoElement.setAttribute('playsinline', ''); // Doublement important pour iOS
                videoElement.setAttribute('muted', '');
                videoElement.setAttribute('autoplay', '');

                qrContainer.appendChild(videoElement);

                // Ajouter l'overlay du scanner
                const overlayElement = document.createElement('div');
                overlayElement.className = 'scanner-overlay';
                const laserElement = document.createElement('div');
                laserElement.className = 'scanner-laser';
                overlayElement.appendChild(laserElement);
                qrContainer.appendChild(overlayElement);

                // Créer un canvas pour l'analyse des images
                const canvasElement = document.createElement('canvas');
                canvasElement.style.display = 'none';
                qrContainer.appendChild(canvasElement);
                const canvas = canvasElement.getContext('2d');

                // Fixer la taille du conteneur pour éviter les problèmes de redimensionnement
                qrContainer.style.height = '300px';
                qrContainer.style.position = 'relative';
                qrContainer.style.overflow = 'hidden';

                // Demander l'accès à la caméra avec des contraintes spécifiques
                const constraints = {
                    video: {
                        facingMode: 'environment', // Utiliser la caméra arrière par défaut
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        },
                        aspectRatio: {
                            ideal: 1.777778
                        },
                        frameRate: {
                            ideal: 30,
                            max: 60
                        }
                    },
                    audio: false // Désactiver l'audio explicitement
                };

                // Fonction pour gérer les erreurs de caméra
                function handleCameraError(error) {
                    console.error('Erreur lors de l\'accès à la caméra:', error);

                    // Si la caméra arrière échoue, essayer la caméra avant
                    if (constraints.video.facingMode === 'environment') {
                        console.log('Tentative avec la caméra avant...');
                        constraints.video.facingMode = 'user';
                        navigator.mediaDevices.getUserMedia(constraints)
                            .then(handleCameraSuccess)
                            .catch(function(err) {
                                // Si les deux échouent, montrer une erreur
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur d\'accès à la caméra',
                                    text: 'Nous n\'avons pas pu accéder à votre caméra. Veuillez vérifier les autorisations et réessayer.',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#4361ee'
                                });
                            });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur d\'accès à la caméra',
                            text: 'Nous n\'avons pas pu accéder à votre caméra. Veuillez vérifier les autorisations et réessayer.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                }

                // Fonction pour gérer le succès de la caméra
                function handleCameraSuccess(stream) {
                    videoStream = stream;

                    // Fixer la vidéo dans le conteneur
                    videoElement.style.position = 'absolute';
                    videoElement.style.top = '0';
                    videoElement.style.left = '0';
                    videoElement.style.width = '100%';
                    videoElement.style.height = '100%';
                    videoElement.style.objectFit = 'cover';

                    // Attribuer le flux à l'élément vidéo
                    try {
                        videoElement.srcObject = stream;
                    } catch (error) {
                        // Fallback pour les anciens navigateurs
                        videoElement.src = window.URL.createObjectURL(stream);
                    }

                    // S'assurer que la vidéo est chargée avant de commencer l'analyse
                    videoElement.onloadedmetadata = function() {
                        videoElement.play()
                            .then(() => {
                                scannerActive = true;
                                // Commencer l'analyse des images
                                requestAnimationFrame(scanQRCode);
                            })
                            .catch(error => {
                                console.error('Erreur lors de la lecture de la vidéo:', error);
                            });
                    };
                }

                // Demander l'accès à la caméra
                navigator.mediaDevices.getUserMedia(constraints)
                    .then(handleCameraSuccess)
                    .catch(handleCameraError);

                // Fonction pour scanner le QR code
                function scanQRCode() {
                    if (!scannerActive) return;

                    if (videoElement.readyState === videoElement.HAVE_ENOUGH_DATA && !isPaused) {
                        // Ajuster la taille du canvas à la vidéo
                        canvasElement.height = videoElement.videoHeight;
                        canvasElement.width = videoElement.videoWidth;

                        // Dessiner l'image de la vidéo sur le canvas
                        canvas.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);

                        // Obtenir les données de l'image
                        const imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);

                        // Analyser l'image avec jsQR
                        try {
                            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                                inversionAttempts: "dontInvert",
                            });

                            if (code) {
                                // QR code détecté
                                onQRCodeSuccess(code.data);
                                return;
                            }
                        } catch (error) {
                            console.error('Erreur lors de l\'analyse du QR code:', error);
                        }
                    }

                    // Continuer l'analyse
                    requestAnimationFrame(scanQRCode);
                }

                // Bouton pour changer de caméra
                document.getElementById('toggleCameraBtn').addEventListener('click', function() {
                    if (videoStream) {
                        // Arrêter le flux vidéo actuel
                        videoStream.getTracks().forEach(track => track.stop());

                        // Déterminer le mode de caméra à utiliser
                        const currentFacingMode = videoStream.getVideoTracks()[0].getSettings().facingMode;
                        const newFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';

                        // Demander l'accès à la nouvelle caméra
                        navigator.mediaDevices.getUserMedia({
                                video: {
                                    facingMode: newFacingMode,
                                    width: {
                                        ideal: 1280
                                    },
                                    height: {
                                        ideal: 720
                                    }
                                }
                            })
                            .then(function(stream) {
                                videoStream = stream;
                                videoElement.srcObject = stream;
                            })
                            .catch(function(err) {
                                console.error('Erreur lors du changement de caméra:', err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur de caméra',
                                    text: 'Impossible de changer de caméra. Veuillez réessayer.',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#4361ee'
                                });
                            });
                    }
                });

                // Bouton pour mettre en pause/reprendre le scanner
                document.getElementById('pauseResumeBtn').addEventListener('click', function() {
                    if (isPaused) {
                        this.innerHTML = '<i class="bi bi-pause-fill me-2"></i> Pause';
                        isPaused = false;
                        requestAnimationFrame(scanQRCode);
                    } else {
                        this.innerHTML = '<i class="bi bi-play-fill me-2"></i> Reprendre';
                        isPaused = true;
                    }
                });
            }

            // Ajouter cette fonction pour fermer proprement la caméra
            // Ajouter cette fonction pour fermer proprement la caméra
            function stopCamera() {
                if (videoStream) {
                    videoStream.getTracks().forEach(track => track.stop());
                    videoStream = null;
                }
                scannerActive = false;
            }

            // Modifier la fonction onQRCodeSuccess
            function onQRCodeSuccess(decodedText) {
                try {
                    // Mettre en pause le scanner
                    isPaused = true;
                    document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-play-fill me-2"></i> Reprendre';

                    // Essayer de parser le contenu du QR code
                    const qrData = JSON.parse(decodedText);

                    // Vérifier si c'est un QR code de présence valide
                    if (qrData.type === 'presence_labo' || qrData.type === 'presence_cours') {
                        // Arrêter la caméra complètement
                        stopCamera();

                        // Stocker les données de la séance
                        seanceData = qrData;

                        // Afficher les informations de la séance
                        document.getElementById('seanceTitre').textContent = qrData.titre || 'Non spécifié';
                        document.getElementById('seanceDate').textContent = formatDate(qrData.date);
                        document.getElementById('seanceHeure').textContent = `${qrData.heure_debut || ''} - ${qrData.heure_fin || ''}`;
                        document.getElementById('seanceLabo').textContent = qrData.labo_nom || qrData.salle || 'Non spécifié';

                        // Afficher le résultat du scan et les informations de séance
                        qrResult.style.display = 'block';
                        qrResultText.textContent = 'QR Code validé! Veuillez confirmer votre présence.';
                        seanceInfo.style.display = 'block';
                        confirmationButtons.style.display = 'block';

                        // Masquer complètement le conteneur de la caméra
                        const qrContainer = document.querySelector('.qr-scanner-container');
                        if (qrContainer) qrContainer.style.display = 'none';

                        // Masquer les boutons de contrôle de la caméra
                        document.getElementById('toggleCameraBtn').style.display = 'none';
                        document.getElementById('pauseResumeBtn').style.display = 'none';

                        // Masquer également l'alerte d'information sur le scan
                        const scanInfoAlert = document.querySelector('.alert-info');
                        if (scanInfoAlert) scanInfoAlert.style.display = 'none';

                        // Réactiver le défilement
                        document.body.classList.remove('scanning');

                    } else {
                        // QR code non valide pour la présence
                        Swal.fire({
                            icon: 'error',
                            title: 'QR Code non valide',
                            text: 'Ce QR code n\'est pas valide pour l\'enregistrement de présence.',
                            confirmButtonText: 'Réessayer',
                            confirmButtonColor: '#4361ee'
                        }).then(() => {
                            // Redémarrer le scanner
                            isPaused = false;
                            document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-pause-fill me-2"></i> Pause';
                            requestAnimationFrame(scanQRCode);
                        });
                    }
                } catch (error) {
                    console.error('Erreur lors du traitement du QR code:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'QR Code non reconnu',
                        text: 'Le format du QR code n\'est pas reconnu. Veuillez scanner un QR code valide.',
                        confirmButtonText: 'Réessayer',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        // Redémarrer le scanner
                        isPaused = false;
                        document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-pause-fill me-2"></i> Pause';
                        requestAnimationFrame(scanQRCode);
                    });
                }
            }

            // Modifier la fonction resetScannerInterface
            function resetScannerInterface() {
                // Masquer les résultats et informations
                qrResult.style.display = 'none';
                seanceInfo.style.display = 'none';
                confirmationButtons.style.display = 'none';

                // Réinitialiser l'état du scanner
                isPaused = false;

                // Réactiver le défilement
                document.body.classList.remove('scanning');

                // Arrêter la caméra si elle est active
                if (videoStream) {
                    stopCamera();
                }

                // Trouver le parent du conteneur de la caméra
                const cardBody = document.querySelector('.card-body');

                // Trouver l'alerte d'information
                const alertInfo = document.querySelector('.alert-info');

                // Recréer complètement le conteneur de la caméra
                // D'abord, supprimer l'ancien conteneur s'il existe
                const oldContainer = document.querySelector('.qr-scanner-container');
                if (oldContainer) {
                    oldContainer.remove();
                }

                // Créer un nouveau conteneur
                const newContainer = document.createElement('div');
                newContainer.className = 'qr-scanner-container mb-4';

                // Insérer le nouveau conteneur après l'alerte d'information
                if (alertInfo && cardBody) {
                    alertInfo.after(newContainer);
                }

                // Réafficher les boutons de contrôle de la caméra
                document.getElementById('toggleCameraBtn').style.display = 'inline-block';
                document.getElementById('toggleCameraBtn').disabled = false;

                document.getElementById('pauseResumeBtn').style.display = 'inline-block';
                document.getElementById('pauseResumeBtn').disabled = false;
                document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-pause-fill me-2"></i> Pause';

                // Réafficher l'alerte d'information sur le scan
                if (alertInfo) {
                    alertInfo.style.display = 'block';
                }

                // Utiliser un délai pour s'assurer que le DOM est mis à jour avant d'initialiser le scanner
                setTimeout(() => {
                    // Redémarrer le scanner
                    initQRScanner();
                }, 500); // Délai plus long pour s'assurer que tout est prêt
            }




          


            // Modifier la fonction resetScannerInterface pour réinitialiser correctement
            function resetScannerInterface() {
                // Masquer les résultats et informations
                qrResult.style.display = 'none';
                seanceInfo.style.display = 'none';
                confirmationButtons.style.display = 'none';

                // Réactiver les boutons de contrôle de la caméra
                document.getElementById('toggleCameraBtn').disabled = false;
                document.getElementById('pauseResumeBtn').disabled = false;

                // Réinitialiser l'état du scanner
                isPaused = false;
                document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-pause-fill me-2"></i> Pause';

                // Réinitialiser la caméra
                if (videoStream) {
                    stopCamera();
                }

                // Réactiver le défilement
                document.body.classList.remove('scanning');

                // Redémarrer le scanner
                initQRScanner();
            }

            // Fonction utilitaire pour formater une date
            function formatDate(dateString) {
                if (!dateString) return 'Non spécifiée';

                const options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };

                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('fr-FR', options);
                } catch (e) {
                    return dateString;
                }
            }

            // Fonction utilitaire pour formater une heure
            function formatTime(timeString) {
                if (!timeString) return '';

                // Si le format est déjà HH:MM, le retourner tel quel
                if (/^\d{2}:\d{2}(:\d{2})?$/.test(timeString)) {
                    return timeString.substring(0, 5); // Retourner seulement HH:MM
                }

                try {
                    const date = new Date(timeString);
                    return date.toLocaleTimeString('fr-FR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    return timeString;
                }
            }

            // Bouton pour confirmer la présence
            document.getElementById('confirmPresenceBtn').addEventListener('click', function() {
                savePresence();
            });

            // Bouton pour rescanner un QR code
            document.getElementById('rescanBtn').addEventListener('click', function() {
                resetScannerInterface();
            });

            // Gestion des erreurs globales
            window.addEventListener('error', function(e) {
                console.error('Erreur JavaScript:', e.message);

                // Afficher une notification d'erreur seulement si elle est liée au scanner
                if (e.message.includes('jsQR') || e.message.includes('video') || e.message.includes('camera')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur technique',
                        text: 'Une erreur est survenue avec le scanner. Veuillez rafraîchir la page et réessayer.',
                        confirmButtonText: 'Rafraîchir',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });

            // Gestion des erreurs non capturées dans les promesses
            window.addEventListener('unhandledrejection', function(e) {
                console.error('Promesse rejetée non gérée:', e.reason);

                // Afficher une notification d'erreur seulement si elle est liée au scanner
                if (e.reason && (e.reason.toString().includes('camera') || e.reason.toString().includes('video'))) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur d\'accès à la caméra',
                        text: 'Impossible d\'accéder à la caméra. Veuillez vérifier vos autorisations et rafraîchir la page.',
                        confirmButtonText: 'Rafraîchir',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });

            // Gestion de la visibilité de la page
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    // La page est redevenue visible, vérifier si le scanner doit être redémarré
                    if (qrScannerSection.style.display === 'block' && !scannerActive && !isPaused) {
                        scannerActive = true;
                        initQRScanner();
                    }
                } else {
                    // La page n'est plus visible, mettre en pause le scanner
                    if (scannerActive && !isPaused) {
                        isPaused = true;
                        document.getElementById('pauseResumeBtn').innerHTML = '<i class="bi bi-play-fill me-2"></i> Reprendre';
                    }
                }
            });

            // Gestion du redimensionnement de la fenêtre
            window.addEventListener('resize', function() {
                // Redimensionner le scanner si nécessaire
                if (scannerActive) {
                    // Réinitialiser le scanner après un court délai
                    clearTimeout(window.resizeTimer);
                    window.resizeTimer = setTimeout(function() {
                        initQRScanner();
                    }, 250);
                }
            });

            // Gestion du changement d'orientation
            window.addEventListener('orientationchange', function() {
                // Attendre que l'orientation soit complètement changée
                setTimeout(function() {
                    if (scannerActive) {
                        // Réinitialiser le scanner après changement d'orientation
                        stopCamera();
                        initQRScanner();
                    }
                }, 200);
            });
        });
    </script>

</body>

</html>