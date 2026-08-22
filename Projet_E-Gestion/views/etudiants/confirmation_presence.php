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
    <title>Présence confirmée | <?= htmlspecialchars($configUniversite['nom'] ?? 'E-GESTION') ?></title>
    
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
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.12);
            border-radius: 15px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            margin-bottom: 30px;
            text-align: center;
            padding: 40px;
            max-width: 600px;
            margin: 50px auto;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success-color);
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .confirmation-title {
            color: var(--success-color);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .confirmation-text {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .btn-home {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-home:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
            color: white;
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
                padding: 25px;
                margin: 30px 15px;
            }
            
            .success-icon {
                font-size: 60px;
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
            <a href="presence_qrcode" class="logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="../<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <span><?= htmlspecialchars($configUniversite['sigle'].' - NUMERIQUE' ?? 'E-GESTION') ?></span>
            </a>
        </div>
    </header>

    <main id="main" class="main">
        <div class="container">
            <div class="confirmation-card animate__animated animate__fadeIn">
                <div class="success-icon animate__animated animate__bounceIn">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h2 class="confirmation-title">Présence confirmée!</h2>
                <p class="confirmation-text">Votre présence a été enregistrée avec succès. Merci de votre participation.</p>
                <div class="text-center">
                    <a href="presence_qrcode" class="btn-home">
                        <i class="bi bi-house-door me-2"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer id="footer" class="footer">
        <div class="container">
            <div class="copyright">
                © <?= date('Y') ?> <strong><span><?= htmlspecialchars($configUniversite['nom'] ?? 'E-GESTION') ?></span></strong>. Tous droits réservés
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
