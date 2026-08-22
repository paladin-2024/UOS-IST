<?php
/**
 * Page publique - Consultation des résultats de soutenance
 * Accessible sans authentification via: index.php?view=resultat_soutenance
 */

require_once 'config/config.php';
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de Soutenance - <?php echo htmlspecialchars($configUniversite['nom_application'] ?? 'E-GESTION'); ?></title>
    
    <?php if (!empty($configUniversite['logo'])): ?>
    <link href="./<?= htmlspecialchars($configUniversite['logo']) ?>" rel="icon">
    <link href="./<?= htmlspecialchars($configUniversite['logo']) ?>" rel="apple-touch-icon">
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

        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('uploads/inbtp-student.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: blur(2px);
            opacity: 0.2;
            z-index: -1;
        }

        .main-container {
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

        .side-image {
            flex: 1;
            background: linear-gradient(rgba(0, 68, 148, 0.8), rgba(0, 68, 148, 0.9)), 
                        url('uploads/login_student.png');
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

        .side-image h2 {
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .side-image p {
            font-weight: 300;
            line-height: 1.6;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .side-image i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .form-section {
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
            box-shadow: 0 0 0 0.25rem rgba(0, 68, 148, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
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
            background-color: #003377;
            border-color: #003377;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 68, 148, 0.3);
        }

        .btn-primary:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
            transform: none;
            box-shadow: none;
        }

        /* Résultat */
        .result-card {
            display: none;
            margin-top: 25px;
            border-radius: 12px;
            overflow: hidden;
            animation: slideUp 0.5s ease;
            border: 1px solid var(--border-color);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result-header {
            padding: 15px 20px;
            text-align: center;
            color: white;
            background: var(--primary-color);
        }

        .result-header h5 {
            margin-bottom: 3px;
            font-weight: 600;
        }

        .result-header p {
            margin-bottom: 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .result-body {
            background: white;
            padding: 20px;
        }

        .student-info {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px dashed var(--border-color);
        }

        .student-info h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .student-info p {
            color: var(--text-muted);
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .note-display {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .note-value {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }

        .note-max {
            font-size: 1.2rem;
            color: #95a5a6;
            font-weight: 400;
        }

        .mention-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            margin-top: 10px;
        }

        .mention-ajourne { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .mention-satisfaction { background: linear-gradient(135deg, #f39c12 0%, #d68910 100%); }
        .mention-distinction { background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); }
        .mention-grande-distinction { background: linear-gradient(135deg, #3498db 0%, #2471a3 100%); }
        .mention-plus-grande-distinction { background: linear-gradient(135deg, #9b59b6 0%, #7d3c98 100%); }

        .note-ajourne { color: #e74c3c; }
        .note-satisfaction { color: #f39c12; }
        .note-distinction { color: #27ae60; }
        .note-grande-distinction { color: #3498db; }
        .note-plus-grande-distinction { color: #9b59b6; }

        .result-header.ajourne { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .result-header.satisfaction { background: linear-gradient(135deg, #f39c12 0%, #d68910 100%); }
        .result-header.distinction { background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); }
        .result-header.grande-distinction { background: linear-gradient(135deg, #3498db 0%, #2471a3 100%); }
        .result-header.plus-grande-distinction { background: linear-gradient(135deg, #9b59b6 0%, #7d3c98 100%); }

        .thesis-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .thesis-info h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .thesis-info p {
            margin-bottom: 5px;
        }

        .error-message {
            display: none;
            text-align: center;
            padding: 20px;
            background: #fff5f5;
            border-radius: 10px;
            border: 1px solid #ffe0e0;
            margin-top: 20px;
        }

        .error-message i {
            font-size: 2.5rem;
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .error-message h5 {
            color: #e74c3c;
            margin-bottom: 5px;
        }

        .loading-spinner {
            display: none;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-size: 14px;
            margin-top: 20px;
            text-decoration: none;
        }

        .back-link:hover {
            color: var(--primary-color);
            text-decoration: none;
            transform: translateX(-5px);
        }

        .back-link i {
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
                margin: 10px;
            }
            
            .side-image {
                display: none;
            }
            
            .form-section {
                padding: 30px 20px;
            }

            .note-value {
                font-size: 2.5rem;
            }
        }

        /* Preloader */
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
            background: linear-gradient(135deg, #004494 0%, #002255 100%);
        }

        #preloader-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .preloader-content {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .preloader-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 30px;
            animation: pulse 1.5s ease-in-out infinite;
        }

        .preloader-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .loading-bar-container {
            width: 250px;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .loading-progress {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #ffc107, #ffdb58);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .loading-percentage {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
        }

        .loading-text {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            transition: opacity 0.3s ease;
        }

        #preloader.loaded {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        body.loading {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div id="preloader">
        <div class="preloader-background"></div>
        <canvas id="preloader-canvas"></canvas>
        <div class="preloader-content">
            <div class="preloader-logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="./<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-graduation-cap" style="font-size: 80px; color: white;"></i>
                <?php endif; ?>
            </div>
            <div class="loading-percentage">0%</div>
            <div class="loading-bar-container">
                <div class="loading-progress"></div>
            </div>
            <div class="loading-text">Initialisation...</div>
        </div>
    </div>

    <!-- Background Image -->
    <div class="background-image"></div>

    <div class="main-container">
        <!-- Side Image -->
        <div class="side-image">
            <i class="fas fa-award"></i>
            <h2>Résultats de Soutenance</h2>
            <p>Consultez vos résultats de soutenance de mémoire et TFC en toute simplicité et sécurité.</p>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <div class="logo-container">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="./<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo" class="logo">
                <?php endif; ?>
            </div>
            
            <h3 class="form-title">Consulter mon résultat</h3>
            
            <form id="searchForm">
                <div class="mb-4">
                    <label for="matricule" class="form-label">
                        <i class="fas fa-id-card me-2"></i>Votre Matricule
                    </label>
                    <input type="text" class="form-control" id="matricule" name="matricule" 
                           placeholder="Entrez votre matricule" required autocomplete="off">
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="btnSearch">
                    <span class="btn-text">
                        <i class="fas fa-search me-2"></i>Rechercher
                    </span>
                    <span class="loading-spinner">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Recherche en cours...
                    </span>
                </button>
            </form>
            
            <!-- Message d'erreur -->
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i>
                <h5>Résultat non trouvé</h5>
                <p class="text-muted" id="errorText">Aucune soutenance trouvée pour ce matricule.</p>
            </div>
            
            <!-- Résultat -->
            <div class="result-card" id="resultCard">
                <div class="result-header" id="resultHeader">
                    <h5><i class="fas fa-check-circle me-2"></i>Résultat de Soutenance</h5>
                    <p id="resultDate"></p>
                </div>
                <div class="result-body">
                    <div class="student-info">
                        <h6 id="studentName"></h6>
                        <p id="studentMatricule"></p>
                    </div>
                    
                    <div class="note-display">
                        <div class="note-value" id="noteValue">
                            <span id="noteNumber">--</span><span class="note-max">/20</span>
                        </div>
                        <div class="mention-badge" id="mentionBadge">--</div>
                    </div>
                    
                    <div class="thesis-info">
                        <h6><i class="fas fa-book me-2"></i>Informations du Mémoire</h6>
                        <p><strong>Titre:</strong> <span id="thesisTitle"></span></p>
                        <p><strong>Directeur:</strong> <span id="thesisDirector"></span></p>
                        <p><strong>Spécialisation:</strong> <span id="thesisSpec"></span></p>
                    </div>
                </div>
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
            
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);
            
            const particlesArray = [];
            const numberOfParticles = 100;
            
            const colors = [
                'rgba(0, 68, 148, 0.7)',
                'rgba(0, 85, 164, 0.7)',
                'rgba(0, 34, 68, 0.7)',
                'rgba(255, 193, 7, 0.7)',
                'rgba(255, 187, 51, 0.7)',
                'rgba(255, 136, 0, 0.7)'
            ];
            
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
                    
                    if (this.x > canvas.width || this.x < 0) this.speedX = -this.speedX;
                    if (this.y > canvas.height || this.y < 0) this.speedY = -this.speedY;
                }
                
                draw() {
                    ctx.fillStyle = this.color;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fill();
                }
            }
            
            function init() {
                for (let i = 0; i < numberOfParticles; i++) {
                    particlesArray.push(new Particle());
                }
            }
            
            init();
            
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
            
            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                for (let i = 0; i < particlesArray.length; i++) {
                    particlesArray[i].update();
                    particlesArray[i].draw();
                }
                
                connect();
                
                if (!document.getElementById('preloader').classList.contains('loaded')) {
                    requestAnimationFrame(animate);
                }
            }
            
            animate();
        });
        
        // Preloader
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loading');
            
            function hidePreloader() {
                const preloader = document.getElementById('preloader');
                if (preloader) {
                    preloader.classList.add('loaded');
                    document.body.classList.remove('loading');
                    setTimeout(() => preloader.remove(), 800);
                }
            }
            
            const progressBar = document.querySelector('.loading-progress');
            const percentageText = document.querySelector('.loading-percentage');
            const loadingText = document.querySelector('.loading-text');
            
            const loadingMessages = [
                "Initialisation...",
                "Chargement des ressources...",
                "Préparation...",
                "Prêt !"
            ];
            
            let width = 0;
            let messageIndex = 0;
            
            function updateProgress(newWidth) {
                if (progressBar) progressBar.style.width = newWidth + '%';
                if (percentageText) percentageText.textContent = Math.round(newWidth) + '%';
                
                if (loadingText) {
                    const newMessageIndex = Math.floor(newWidth / (100 / (loadingMessages.length - 1)));
                    if (newMessageIndex > messageIndex) {
                        messageIndex = newMessageIndex;
                        loadingText.style.opacity = 0;
                        setTimeout(() => {
                            loadingText.textContent = loadingMessages[messageIndex];
                            loadingText.style.opacity = 1;
                        }, 200);
                    }
                }
            }
            
            const interval = setInterval(function() {
                const increment = Math.max(1, Math.floor(Math.random() * 8));
                width += increment;
                
                if (width >= 100) {
                    width = 100;
                    clearInterval(interval);
                    updateProgress(width);
                    setTimeout(hidePreloader, 500);
                } else {
                    updateProgress(width);
                }
            }, 80);
            
            setTimeout(() => {
                if (width < 100) {
                    clearInterval(interval);
                    width = 100;
                    updateProgress(width);
                    setTimeout(hidePreloader, 300);
                }
            }, 4000);
            
            window.addEventListener('load', function() {
                clearInterval(interval);
                const finishLoading = setInterval(() => {
                    width += 10;
                    if (width >= 100) {
                        width = 100;
                        clearInterval(finishLoading);
                        updateProgress(width);
                        setTimeout(hidePreloader, 300);
                    } else {
                        updateProgress(width);
                    }
                }, 30);
            });
        });

        // Form animation
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

        // Search form
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const matricule = document.getElementById('matricule').value.trim();
            if (!matricule) return;
            
            const btn = document.getElementById('btnSearch');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.loading-spinner');
            
            document.getElementById('resultCard').style.display = 'none';
            document.getElementById('errorMessage').style.display = 'none';
            
            btnText.style.display = 'none';
            spinner.style.display = 'inline';
            btn.disabled = true;
            
            fetch('controller/get_resultat_soutenance.php?matricule=' + encodeURIComponent(matricule))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        afficherResultat(data.data);
                    } else {
                        afficherErreur(data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    afficherErreur('Erreur de connexion au serveur');
                })
                .finally(() => {
                    btnText.style.display = 'inline';
                    spinner.style.display = 'none';
                    btn.disabled = false;
                });
        });
        
        function afficherResultat(data) {
            document.getElementById('studentName').textContent = data.etudiant_nom;
            document.getElementById('studentMatricule').textContent = 'Matricule: ' + data.matricule;
            
            const note = parseFloat(data.note_finale);
            document.getElementById('noteNumber').textContent = note.toFixed(2);
            
            const mentionInfo = calculerMention(note);
            
            const noteValue = document.getElementById('noteValue');
            const mentionBadge = document.getElementById('mentionBadge');
            const resultHeader = document.getElementById('resultHeader');
            
            noteValue.className = 'note-value';
            mentionBadge.className = 'mention-badge';
            resultHeader.className = 'result-header';
            
            noteValue.classList.add('note-' + mentionInfo.class);
            mentionBadge.classList.add('mention-' + mentionInfo.class);
            resultHeader.classList.add(mentionInfo.class);
            
            mentionBadge.textContent = mentionInfo.mention;
            
            if (data.date_soutenance) {
                const date = new Date(data.date_soutenance);
                document.getElementById('resultDate').textContent = 
                    'Soutenue le ' + date.toLocaleDateString('fr-FR', { 
                        day: 'numeric', month: 'long', year: 'numeric' 
                    });
            } else {
                document.getElementById('resultDate').textContent = '';
            }
            
            document.getElementById('thesisTitle').textContent = data.titre_memoire || '-';
            document.getElementById('thesisDirector').textContent = data.directeur_nom || '-';
            document.getElementById('thesisSpec').textContent = data.specialisation || '-';
            
            document.getElementById('resultCard').style.display = 'block';
        }
        
        function calculerMention(note) {
            const pourcentage = (note / 20) * 100;
            
            if (pourcentage < 50) {
                return { mention: 'Ajourné', class: 'ajourne' };
            } else if (pourcentage >= 50 && pourcentage <= 69) {
                return { mention: 'Satisfaction', class: 'satisfaction' };
            } else if (pourcentage >= 70 && pourcentage <= 79) {
                return { mention: 'Distinction', class: 'distinction' };
            } else if (pourcentage >= 80 && pourcentage <= 89) {
                return { mention: 'Grande Distinction', class: 'grande-distinction' };
            } else {
                return { mention: 'Plus Grande Distinction', class: 'plus-grande-distinction' };
            }
        }
        
        function afficherErreur(message) {
            document.getElementById('errorText').textContent = message;
            document.getElementById('errorMessage').style.display = 'block';
        }
        
        document.getElementById('matricule').focus();
    </script>
</body>
</html>
