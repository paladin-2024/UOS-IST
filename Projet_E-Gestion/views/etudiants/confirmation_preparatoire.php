<?php
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation | <?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></title>
    
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
        
        .confirmation-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
            padding: 50px 30px;
            max-width: 700px;
            margin: 50px auto;
        }
        
        .confirmation-icon {
            font-size: 80px;
            color: var(--success-color);
            margin-bottom: 30px;
        }
        
        .confirmation-title {
            font-size: 2.2rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .confirmation-text {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-lg {
            padding: 15px 30px;
            font-size: 1.1rem;
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
            
            .confirmation-card {
                padding: 30px 20px;
                margin: 30px 15px;
            }
            
            .confirmation-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-image"></div>
    
    <header id="header" class="header">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="choix_preparatoire" class="logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="../<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <span><?= htmlspecialchars($configUniversite['sigle'].' - NUMERIQUE' ?? 'E-GESTION') ?></span>
            </a>
        </div>
    </header>

    <main id="main" class="main">
        <div class="container">
            <div class="confirmation-card" data-aos="zoom-in">
                <div class="confirmation-icon animate__animated animate__bounceIn">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h1 class="confirmation-title">Inscription Complétée!</h1>
                <p class="confirmation-text">
                    Félicitations! Votre choix de classe préparatoire a été enregistré avec succès. 
                    <br>Vos informations ont été mises à jour dans notre système.
                    <br><br>
                    Vous recevrez bientôt un email de confirmation avec les détails de votre inscription.
                    <br>
                    N'oubliez pas de consulter régulièrement votre boîte mail pour les informations importantes concernant la rentrée.
                </p>
                <div class="mt-4">
                    <a href="choix_preparatoire" class="btn btn-primary btn-lg">
                        <i class="bi bi-house-door me-2"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer id="footer" class="footer">
        <div class="container">
            <div class="copyright">
                © <?= date('Y') ?> <strong><span><?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></span></strong>. Tous droits réservés
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser les animations AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true
            });
        });
    </script>
</body>
</html>
