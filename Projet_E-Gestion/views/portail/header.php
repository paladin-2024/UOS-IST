<?php
// Récupérer la configuration de l'université
$universite = new Universite();
$config = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScienceHub - Plateforme de Gestion des Travaux Scientifiques</title>

    <?php if (!empty($config['logo'])): ?>
        <!-- Favicons --> 
	<link href="../<?= htmlspecialchars($config['logo']) ?>" rel="icon">
	<link href="../<?= htmlspecialchars($config['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <style>
        :root {
            --primary-color: #004494;
            --secondary-color: #f5f5f5;
            --accent-color: #FFD700;
            --text-color: #333;
            --light-text: #fff;
            --border-radius: 8px;
            --box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            color: var(--text-color);
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            background-color: #fcfcfc;
        }
        
        /* Preloader */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #fff;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease-out;
        }
        
        .loader {
            width: 80px;
            height: 80px;
            border: 5px solid rgba(0, 68, 148, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* EU Banner */
        .eu-banner {
            background-color: #333;
            color: white;
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .eu-flag {
            width: 20px;
            height: 15px;
            margin-right: 10px;
            background-color: #003399;
            position: relative;
            overflow: hidden;
            display: inline-block;
            border-radius: 2px;
        }
        
        /* Header */
        .custom-navbar {
            background-color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            padding: 15px 0;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .navbar-brand img {
            height: 50px;
            transition: var(--transition);
        }
        
        .navbar-brand img:hover {
            transform: scale(1.05);
        }
        
        /* Main Banner */
        .main-banner {
            background: linear-gradient(135deg, var(--primary-color) 0%, #002b5e 100%);
            color: var(--light-text);
            padding: 3rem 0;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .main-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(150px, -150px);
        }
        
        .main-banner h1 {
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .main-banner .lead {
            font-weight: 300;
            font-size: 1.2rem;
            max-width: 80%;
        }
        
        /* Navigation */
        .main-nav {
            background-color: var(--primary-color);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .main-nav .nav-link {
            color: var(--light-text);
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
            position: relative;
        }
        
        .main-nav .nav-item:first-child .nav-link {
            border-left: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .main-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        .main-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background-color: var(--accent-color);
            transition: var(--transition);
            transform: translateX(-50%);
        }
        
        .main-nav .nav-link:hover::after {
            width: 70%;
        }
        
        /* Search Bar */
        .search-bar {
            background: linear-gradient(to right, #f9f9f9, #f5f5f5);
            padding: 2.5rem 0;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .search-bar .form-control {
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            border: 2px solid #e9e9e9;
            border-right: none;
            padding: 0.8rem 1.2rem;
            font-size: 1rem;
            box-shadow: none;
            transition: var(--transition);
        }
        
        .search-bar .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 68, 148, 0.15);
        }
        
        .search-bar .btn-primary {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: var(--transition);
        }
        
        .search-bar .btn-primary:hover {
            background-color: #003a7e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .search-bar .form-check-label {
            font-size: 0.9rem;
            color: #555;
        }
        
        /* Featured Section */
        .featured-section {
            padding: 5rem 0;
            background-color: #fff;
        }
        
        .featured-section h2 {
            font-weight: 600;
            margin-bottom: 2.5rem;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .featured-section h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .featured-card {
            height: 100%;
            transition: var(--transition);
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .featured-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .featured-img-container {
            height: 180px;
            font-size: 2.5rem;
            font-weight: bold;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .featured-card:hover .featured-img-container {
            height: 190px;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .card-text {
            color: #666;
            line-height: 1.6;
        }
        
        .card-footer {
            padding: 1rem 1.5rem;
        }
        
        /* Categories */
        .categories-section {
            background: linear-gradient(to right, #f5f5f5, #f9f9f9);
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .categories-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: rgba(0,68,148,0.05);
            border-radius: 50%;
        }
        
        .category-item {
            background-color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            height: 100%;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        
        .category-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: linear-gradient(to bottom, rgba(0,68,148,0.05), transparent);
            transition: var(--transition);
            z-index: -1;
        }
        
        .category-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .category-item:hover::before {
            height: 100%;
        }
        
        .category-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .category-item:hover .category-icon {
            transform: scale(1.1);
        }
        
        .category-item h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            min-height: 2.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .category-item .btn {
            transition: var(--transition);
            font-weight: 500;
        }
        
        .category-item .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
        
        /* Statistics */
        .stats-section {
            padding: 4rem 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, #002b5e 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stats-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .stat-item {
            text-align: center;
            padding: 2rem;
            border-radius: var(--border-radius);
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
            transition: var(--transition);
            margin: 0 10px;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.15);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.9;
        }
        
        /* App Download Section */
        .app-download-section {
            padding: 5rem 0;
            background-color: #f9f9f9;
            position: relative;
            overflow: hidden;
        }
        
        .app-download-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(to left, rgba(0,68,148,0.03), transparent);
            z-index: 0;
        }
        
        .qr-code {
            border: 1px solid #eee;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        /* Footer */
        .footer {
            background: linear-gradient(to right, #222, #333);
            color: white;
            padding: 4rem 0 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        }
        
        .footer-heading {
            color: var(--accent-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background-color: var(--accent-color);
        }
        
        .footer-link {
            color: #ddd;
            text-decoration: none;
            display: block;
            margin-bottom: 0.8rem;
            transition: var(--transition);
            position: relative;
            padding-left: 15px;
        }
        
        .footer-link::before {
            content: '→';
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            transition: var(--transition);
        }
        
        .footer-link:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-link:hover::before {
            opacity: 1;
        }
        
        .social-icon {
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: var(--transition);
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .social-icon:hover {
            transform: translateY(-5px);
            background: var(--primary-color);
            color: white;
        }
        
        /* Nouvelles classes pour les types de documents */
        .bg-book {
            background-color: #9c27b0; /* Violet pour les livres */
        }

        .bg-course {
            background-color: #795548; /* Marron pour les cours */
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .animate-fade-in {
            animation: fadeIn 1s ease forwards;
        }
        
        .animate-slide-up {
            animation: slideUp 0.8s ease forwards;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .main-nav .nav-link {
                border: none;
                padding: 0.75rem 1rem;
            }
            
            .main-banner .lead {
                max-width: 100%;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .featured-img-container {
                height: 160px;
            }
            
            .stat-item {
                margin-bottom: 1rem;
            }
            
            .main-banner {
                padding: 2rem 0;
            }
            
            .main-banner h1 {
                font-size: 2rem;
            }
            
            .main-banner .lead {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .search-bar .form-control,
            .search-bar .btn-primary {
                border-radius: var(--border-radius);
            }
            
            .search-bar .input-group {
                flex-direction: column;
            }
            
            .search-bar .btn-primary {
                margin-top: 10px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div id="preloader">
        <div class="loader"></div>
    </div>

    <!-- EU Banner -->
    <div class="eu-banner d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <div class="eu-flag"></div>
            <span>Une plateforme universitaire</span>
        </div>
        <div class="d-flex align-items-center" role="button">
            <span class="me-2">Comment le vérifier?</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
    
    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <?php if (!empty($config['logo'])): ?>
                <img src="../<?= htmlspecialchars($config['logo']) ?>" alt="<?= htmlspecialchars($config['sigle']) ?> Logo" class="me-2">
            <?php endif; ?>
            <div>
                <span class="d-block">ScienceHub</span>
                <small class="text-muted">Plateforme de Recherche</small>
            </div>
        </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-primary" href="login">
                            <i class="fas fa-user-circle me-1"></i> Connexion
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-primary" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe me-1"></i> Français
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">English</a></li>
                            <li><a class="dropdown-item" href="#">Español</a></li>
                            <li><a class="dropdown-item" href="#">Deutsch</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Banner -->
    <div class="main-banner">
        <div class="container">
            <div class="row">
                <div class="col-lg-8" data-aos="fade-right" data-aos-duration="1000">
                    <h1 class="display-5 fw-bold"><?= htmlspecialchars($config['sigle']) ?> ScienceHub</h1>
                    <p class="lead">Découvrez, consultez et partagez des projets tutorés, mémoires, thèses, articles scientifiques, livres et cours</p>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="main-nav">
        <div class="container">
            <ul class="nav">
                <li class="nav-item">
                    <a class="nav-link" href="index">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projet">Projets tutorés</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="memoire">Mémoires</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="these">Thèses</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="article">Articles</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="livre">Livres</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cours">Cours</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="statistique">Statistiques</a>
                </li>
            </ul>
        </div>
    </nav>

