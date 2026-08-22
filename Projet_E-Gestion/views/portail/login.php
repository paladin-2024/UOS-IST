<?php
// Récupérer la configuration de l'universitéess
$universite = new Universite();
$config = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ScienceHub Mobile</title>
    <?php if (!empty($config['logo'])): ?>
        <!-- Favicons --> 
        <link href="../<?= htmlspecialchars($config['logo']) ?>" rel="icon">
        <link href="../<?= htmlspecialchars($config['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        :root {
            --primary-color: #004494;
            --primary-light: rgba(0, 68, 148, 0.1);
            --secondary-color: #ffc107;
            --text-color: #333;
            --text-muted: #6c757d;
            --border-color: #e0e0e0;
            --success-color: #28a745;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-color);
            background: linear-gradient(135deg, rgba(245, 247, 250, 0.9) 0%, rgba(228, 233, 242, 0.9) 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Image de fond avec flou - exactement comme dans profil_etudiant.php */
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../uploads/inbtp-student.png'); /* Même chemin que dans profil_etudiant.php */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(2px); /* Même niveau de flou */
            opacity: 0.2; /* Même opacité */
            z-index: -1; /* Derrière tous les éléments */
        }

        .login-container {
            display: flex;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.12);
            border-radius: 15px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            position: relative;
            z-index: 1;
        }

        .login-image {
            flex: 1;
            background: linear-gradient(rgba(0, 68, 148, 0.8), rgba(0, 68, 148, 0.9)), 
                        url('../uploads/login_student.png');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .login-image h2 {
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .login-image p {
            font-weight: 300;
            line-height: 1.6;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .login-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: auto;
            height: 100px;
            margin: 0 auto;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), #0066cc);
            border-radius: 3px;
        }

        .form-control {
            height: 50px;
            border-radius: 10px;
            border: 1px solid #ced4da;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        .input-group-text {
            background-color: transparent;
            border-left: none;
            cursor: pointer;
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

        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #3f37c9;
            border-color: #3f37c9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-size: 14px;
            margin-top: 20px;
        }

        .back-link:hover {
            color: var(--primary-color);
            text-decoration: none;
            transform: translateX(-5px);
        }

        .back-link i {
            margin-right: 5px;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        /* ===== MOBILE STYLES ===== */
        @media (max-width: 768px) {
            body {
                padding: 0;
                margin: 0;
                min-height: 100vh;
                align-items: stretch;
                background: linear-gradient(135deg, rgba(245, 247, 250, 0.95) 0%, rgba(228, 233, 242, 0.95) 100%);
            }

            .background-image {
                opacity: 0.12;
                filter: blur(3px);
            }

            .login-container {
                flex-direction: column;
                max-width: 100%;
                width: 100%;
                min-height: 100vh;
                border-radius: 0;
                box-shadow: none;
                background: transparent;
                backdrop-filter: none;
            }
            
            .login-image {
                display: none;
            }
            
            .login-form {
                padding: 0 24px;
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                min-height: 100vh;
            }

            .logo-container {
                margin-bottom: 10px;
            }

            .logo {
                height: 80px;
            }

            /* Titre université sous le logo */
            .mobile-university-name {
                text-align: center;
                margin-bottom: 5px;
            }

            .mobile-university-name h5 {
                font-size: 16px;
                font-weight: 700;
                color: var(--primary-color);
                letter-spacing: 1px;
                margin: 0;
            }

            .mobile-welcome-text {
                text-align: center;
                margin-bottom: 24px;
            }

            .mobile-welcome-text h4 {
                font-size: 18px;
                font-weight: 600;
                color: var(--text-color);
                margin-bottom: 4px;
            }

            .mobile-welcome-text p {
                font-size: 13px;
                color: var(--text-muted);
                margin: 0;
            }

            .form-title {
                display: none;
            }

            /* Rounded card-like inputs */
            .login-form .mb-4 .input-group {
                background: #fff;
                border-radius: 12px;
                border: 1.5px solid var(--border-color);
                box-shadow: 0 1px 4px rgba(0,0,0,0.04);
                padding: 2px 4px;
                transition: border-color 0.3s;
            }

            .login-form .mb-4 .input-group:focus-within {
                border-color: var(--primary-color);
                box-shadow: 0 2px 8px rgba(0, 68, 148, 0.12);
            }

            .login-form .mb-4 .input-group .form-control {
                border: none !important;
                box-shadow: none !important;
                background: transparent;
                height: 46px;
                font-size: 14px;
            }

            .login-form .mb-4 .input-group .input-group-text {
                border: none;
                background: transparent;
                padding: 0 10px;
            }

            .login-form .form-label {
                font-size: 13px;
                font-weight: 500;
                color: #495057;
                margin-bottom: 6px;
            }

            /* Info alert - hide on mobile, replaced by tooltip button */
            .login-form .alert-info.desktop-info {
                display: none !important;
            }

            /* Info tooltip button for mobile */
            .mobile-pwd-info-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: var(--primary-color);
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                border: none;
                cursor: pointer;
                margin-left: 6px;
                vertical-align: middle;
                padding: 0;
                line-height: 1;
                transition: background 0.2s;
            }

            .mobile-pwd-info-btn:hover,
            .mobile-pwd-info-btn:active {
                background: #0055a4;
            }

            .mobile-pwd-info-popup {
                display: none;
                background: #fff;
                border: 1.5px solid var(--primary-color);
                border-radius: 10px;
                padding: 10px 14px;
                margin-top: 8px;
                font-size: 12px;
                color: var(--text-color);
                box-shadow: 0 2px 10px rgba(0,68,148,0.12);
                animation: fadeSlideDown 0.25s ease;
            }

            .mobile-pwd-info-popup.show {
                display: block;
            }

            @keyframes fadeSlideDown {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Remember me & forgot password */
            .login-form .d-flex.justify-content-between {
                font-size: 13px;
            }

            .login-form .form-check-label {
                font-size: 13px;
            }

            .login-form .text-primary {
                font-size: 13px;
            }

            /* Big submit button */
            .login-form .btn-primary {
                border-radius: 12px;
                height: 48px;
                font-size: 15px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Footer copyright */
            .mobile-footer {
                text-align: center;
                padding: 16px 0 20px;
                font-size: 11px;
                color: var(--text-muted);
            }

            /* Back link */
            .login-form .back-link {
                font-size: 13px;
            }
        }

        /* Hide mobile-only elements on desktop */
        @media (min-width: 769px) {
            .mobile-university-name,
            .mobile-welcome-text,
            .mobile-pwd-info-btn,
            .mobile-pwd-info-popup,
            .mobile-footer {
                display: none !important;
            }
        }
        
        /* Styles pour le preloader */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.8s ease-in-out, visibility 0.8s ease-in-out;
            overflow: hidden;
        }

        .preloader-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, #0055a4 50%, #002244 100%);
            z-index: -1;
        }

        .preloader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            max-width: 90%;
            width: 400px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: scaleIn 0.5s ease-out forwards;
        }

        .logo-preloader {
            animation: pulse 2s infinite;
            margin-bottom: 10px;
            position: relative;
        }

        .logo-preloader:after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0) 70%);
            opacity: 0;
            animation: glow 2s infinite alternate;
        }

        .loading-bar {
            width: 100%;
            height: 6px;
            background-color: rgba(0, 51, 102, 0.1);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }

        .loading-progress {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 3px;
            box-shadow: 0 0 10px rgba(255, 170, 0, 0.5);
            transition: width 0.3s ease-out;
        }

        .loading-percentage {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .loading-text {
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            text-align: center;
            transition: opacity 0.2s ease;
        }

        /* Éléments décoratifs */
        .preloader-decoration {
            position: absolute;
            border-radius: 50%;
            opacity: 0.6;
            z-index: 1;
        }

        .preloader-decoration-1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--secondary-color) 0%, rgba(255, 170, 0, 0) 70%);
            top: 10%;
            right: 10%;
            animation: float 8s ease-in-out infinite;
        }

        .preloader-decoration-2 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--primary-light) 0%, rgba(0, 85, 164, 0) 70%);
            bottom: 15%;
            left: 15%;
            animation: float 6s ease-in-out infinite reverse;
        }

        .preloader-decoration-3 {
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, #002244 0%, rgba(0, 34, 68, 0) 70%);
            top: 40%;
            left: 25%;
            animation: float 10s ease-in-out infinite 1s;
        }

        /* Styles pour le canvas de particules */
        #preloader-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes float {
            0% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-20px) translateX(10px); }
            100% { transform: translateY(0) translateX(0); }
        }

        @keyframes glow {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }

        @keyframes scaleIn {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes scaleOut {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.1); opacity: 0; }
        }

        /* Classe pour masquer le preloader */
        #preloader.loaded {
            opacity: 0;
            visibility: hidden;
        }

        /* Empêcher le défilement pendant le chargement */
        body.loading {
            overflow: hidden;
        }
    </style>
</head>
<body class="loading">
    <!-- Preloader -->
    <div id="preloader">
        <div class="preloader-background"></div>
        <canvas id="preloader-canvas"></canvas>
        <div class="preloader-decoration preloader-decoration-1"></div>
        <div class="preloader-decoration preloader-decoration-2"></div>
        <div class="preloader-decoration preloader-decoration-3"></div>
        <div class="preloader-content">
            <div class="logo-preloader">
            <img src="../<?= htmlspecialchars($config['logo']) ?>" alt="<?= htmlspecialchars($config['sigle']) ?> Logo" height="80px">
            </div>
            <div class="loading-bar">
                <div class="loading-progress"></div>
            </div>
            <div class="loading-percentage">0%</div>
            <div class="loading-text">Initialisation...</div>
        </div>
    </div>

    <!-- Image de fond avec flou - exactement comme dans profil_etudiant.php -->
    <div class="background-image"></div>

    <div class="login-container">
        <div class="login-image d-none d-md-flex">
            <h2><?= htmlspecialchars($config['sigle'] ?? 'ScienceHub') ?></h2>
            <p>Bienvenue sur la plateforme de gestion académique. Connectez-vous pour accéder à votre espace étudiant et suivre votre parcours universitaire.</p>
        </div>
        <div class="login-form">
            <div class="logo-container">
                <img src="../<?= htmlspecialchars($config['logo']) ?>" alt="<?= htmlspecialchars($config['sigle']) ?> Logo" class="logo">
            </div>

            <!-- Mobile only: university name + welcome text -->
            <div class="mobile-university-name">
                <h5><?= htmlspecialchars($config['sigle'] ?? 'E-Gestion') ?></h5>
            </div>
            <div class="mobile-welcome-text">
                <h4>Bienvenue sur MY <?= htmlspecialchars($config['sigle'] ?? 'E-Gestion') ?></h4>
                <p>Connectez-vous pour accéder à votre espace</p>
            </div>
            
            <h4 class="form-title">Connexion Étudiant</h4>
            
            <form id="loginForm" method="POST" action="../controller/login_student.php">
                <div class="mb-4">
                    <label for="matricule" class="form-label"><i class="fas fa-id-badge me-1 d-md-none"></i>Matricule</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="matricule" name="matricule" placeholder="Entrez votre matricule" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1 d-md-none"></i>Mot de passe
                        <button type="button" class="mobile-pwd-info-btn" id="mobileInfoBtn" title="Info mot de passe">
                            <i class="fas fa-info" style="font-size:10px"></i>
                        </button>
                    </label>
                    <div class="mobile-pwd-info-popup" id="mobileInfoPopup">
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        <strong>Première connexion ?</strong> Utilisez le mot de passe par défaut : <strong>12345678</strong>
                        <br><small>Vous devrez le changer après votre première connexion.</small>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Entrez votre mot de passe" required>
                        <button class="input-group-text bg-light border-start-0" type="button" id="togglePassword">
                            <i class="fas fa-eye text-muted"></i>
                        </button>
                    </div>
                    <!-- Desktop only: info alert -->
                    <div class="alert alert-info mt-2 d-flex align-items-center desktop-info" style="font-size: 0.85rem; padding: 10px;">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>
                            <strong>Première connexion ?</strong> Utilisez le mot de passe par défaut : <strong>12345678</strong>
                            <br><small>Vous devrez le changer après votre première connexion.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="#" class="text-primary text-decoration-none">Mot de passe oublié?</a>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>SE CONNECTER
                </button>
                
                <div class="text-center">
                    <a href="index" class="back-link">
                        <i class="fas fa-arrow-left"></i> Retour au portail
                    </a>
                </div>
            </form>

            <!-- Mobile only: footer -->
            <div class="mobile-footer">
                &copy; <?= date('d-m-Y H:i:s') ?> <?= htmlspecialchars($config['sigle'] ?? 'E-Gestion') ?> DRC
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation de particules pour le preloader
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('preloader-canvas');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            // Redimensionner le canvas pour qu'il occupe tout l'écran
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            
            // Configuration des particules
            const particlesArray = [];
            const numberOfParticles = 100;
            
            // Couleurs des particules basées sur la charte graphique
            const colors = [
                'rgba(0, 68, 148, 0.7)',    // primary-color
                'rgba(0, 85, 164, 0.7)',    // primary-light
                'rgba(0, 34, 68, 0.7)',     // primary-dark
                'rgba(255, 193, 7, 0.7)',   // secondary-color
                'rgba(255, 187, 51, 0.7)',  // secondary-light
                'rgba(255, 136, 0, 0.7)'    // secondary-dark
            ];
            
            // Classe Particule
            class Particle {
                constructor() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 5 + 1;
                    this.speedX = Math.random() * 1 - 0.5;
                    this.speedY = Math.random() * 1 - 0.5;
                    this.color = colors[Math.floor(Math.random() * colors.length)];
                }
                
                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;
                    
                    // Rebondir sur les bords
                    if (this.x > canvas.width || this.x < 0) {
                        this.speedX = -this.speedX;
                    }
                    if (this.y > canvas.height || this.y < 0) {
                        this.speedY = -this.speedY;
                    }
                }
                
                draw() {
                    ctx.fillStyle = this.color;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fill();
                }
            }
            
            // Initialiser les particules
            function init() {
                for (let i = 0; i < numberOfParticles; i++) {
                    particlesArray.push(new Particle());
                }
            }
            
            init();
            
            // Connecter les particules proches
            function connect() {
                const maxDistance = 100;
                for (let a = 0; a < particlesArray.length; a++) {
                    for (let b = a; b < particlesArray.length; b++) {
                        const dx = particlesArray[a].x - particlesArray[b].x;
                        const dy = particlesArray[a].y - particlesArray[b].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        if (distance < maxDistance) {
                            const opacity = 1 - (distance / maxDistance);
                            ctx.strokeStyle = `rgba(0, 68, 148, ${opacity * 0.5})`;
                            ctx.lineWidth = 1;
                            ctx.beginPath();
                            ctx.moveTo(particlesArray[a].x, particlesArray[a].y);
                            ctx.lineTo(particlesArray[b].x, particlesArray[b].y);
                            ctx.stroke();
                        }
                    }
                }
            }
            
            // Animer les particules
            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                for (let i = 0; i < particlesArray.length; i++) {
                    particlesArray[i].update();
                    particlesArray[i].draw();
                }
                
                connect();
                
                // Continuer l'animation seulement si le preloader est visible
                if (!document.getElementById('preloader').classList.contains('loaded')) {
                    requestAnimationFrame(animate);
                }
            }
            
            animate();
        });
        
        // Système de preloader avancé
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la classe loading au body
            document.body.classList.add('loading');
            
            // Fonction pour masquer le preloader avec animation
            function hidePreloader() {
                const preloader = document.getElementById('preloader');
                if (preloader) {
                    preloader.classList.add('loaded');
                    // Permettre le défilement après le chargement
                    document.body.classList.remove('loading');
                    
                    // Supprimer complètement le preloader après l'animation
                    setTimeout(() => {
                        preloader.remove();
                    }, 800); // Correspond à la durée de transition
                }
            }
            
            // Simuler un chargement progressif avec pourcentage
            const progressBar = document.querySelector('.loading-progress');
            const percentageText = document.querySelector('.loading-percentage');
            const loadingText = document.querySelector('.loading-text');
            
            // Messages de chargement dynamiques
            const loadingMessages = [
                "Initialisation...",
                "Chargement des ressources...",
                "Préparation des données...",
                "Configuration de l'interface...",
                "Optimisation...",
                "Finalisation...",
                "Prêt !"
            ];
            
            let width = 0;
            let messageIndex = 0;
            
            // Fonction pour mettre à jour le pourcentage et l'animation
            function updateProgress(newWidth) {
                if (progressBar) {
                    progressBar.style.width = newWidth + '%';
                }
                
                if (percentageText) {
                    percentageText.textContent = Math.round(newWidth) + '%';
                }
                
                // Changer le message en fonction du pourcentage
                if (loadingText) {
                    const newMessageIndex = Math.floor(newWidth / (100 / (loadingMessages.length - 1)));
                    if (newMessageIndex > messageIndex) {
                        messageIndex = newMessageIndex;
                        // Animation de changement de texte
                        loadingText.style.opacity = 0;
                        setTimeout(() => {
                            loadingText.textContent = loadingMessages[messageIndex];
                            loadingText.style.opacity = 1;
                        }, 200);
                    }
                }
            }
            
            // Simuler le chargement des ressources
            const interval = setInterval(function() {
                // Augmentation plus naturelle
                const increment = Math.max(1, Math.floor(Math.random() * 5));
                width += increment;
                
                if (width >= 100) {
                    width = 100;
                    clearInterval(interval);
                    updateProgress(width);
                    
                    // Attendre un peu avant de masquer le preloader
                    setTimeout(hidePreloader, 800);
                } else {
                    updateProgress(width);
                }
            }, 100);
            
            // Masquer le preloader si le chargement prend trop de temps
            setTimeout(() => {
                if (width < 100) {
                    clearInterval(interval);
                    width = 100;
                    updateProgress(width);
                    setTimeout(hidePreloader, 500);
                }
            }, 8000);
            
            // Masquer le preloader quand la page est complètement chargée
            window.addEventListener('load', function() {
                // Accélérer la progression jusqu'à 100%
                clearInterval(interval);
                
                const finishLoading = setInterval(() => {
                    width += 5;
                    if (width >= 100) {
                        width = 100;
                        clearInterval(finishLoading);
                        updateProgress(width);
                        setTimeout(hidePreloader, 500);
                    } else {
                        updateProgress(width);
                    }
                }, 30);
            });
        });

        // Mobile info button toggle
        const mobileInfoBtn = document.getElementById('mobileInfoBtn');
        const mobileInfoPopup = document.getElementById('mobileInfoPopup');
        if (mobileInfoBtn && mobileInfoPopup) {
            mobileInfoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                mobileInfoPopup.classList.toggle('show');
            });
            // Close popup when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileInfoBtn.contains(e.target) && !mobileInfoPopup.contains(e.target)) {
                    mobileInfoPopup.classList.remove('show');
                }
            });
        }

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Animation des champs de formulaire
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-3px)';
                this.parentElement.style.transition = 'all 0.3s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
