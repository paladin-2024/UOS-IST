<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISTM BENI - Institut Supérieur des Techniques Médicales de BENI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Ajouter Bootstrap CSS pour le formulaire -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>

    <link rel="icon" href="uploads/logo.png" type="image/x-icon">
    <!-- Préchargement des ressources critiques -->
    <link rel="preload" href="uploads/logo.png" as="image">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" as="style">

    <!-- Bootstrap 4.6.2 JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap');

        :root {
            --primary-color: #003366;
            --primary-light: #0055a4;
            --primary-dark: #002244;
            --secondary-color: #ffaa00;
            --secondary-light: #ffbb33;
            --secondary-dark: #ff8800;
            --light-bg: #e6f0ff;
            --dark-bg: #f0f4f8;
            --text-color: #333;
            --text-light: #666;
            --white: #fff;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        /* Style pour masquer une étape complète */
.hidden-step {
    display: none !important;
}

/* Ajustement de la barre de progression lorsqu'une étape est masquée */
.progress-step.hidden-step + .progress-step:not(.hidden-step)::after {
    content: '';
    position: absolute;
    top: 20px;
    left: -100%;
    width: 100%;
    height: 3px;
    background-color: #dee2e6;
    z-index: 1;
}

.progress-step.active:not(.hidden-step) + .progress-step.hidden-step + .progress-step:not(.hidden-step)::after {
    background-color: var(--secondary-color);
}




        /* Supprimer toutes les flèches des menus */
        .nav-link i,
        .dropdown-btn i,
        .fas.fa-chevron-down {
            display: none !important;
            /* Utilisation de !important pour s'assurer que ça s'applique */
        }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }


        body {
            background-color: #f5f5f5;
            color: var(--text-color);
            line-height: 1.6;
            padding-top: 0 !important;
            /* Supprimer complètement le padding-top */
            margin: 0;
            overflow-x: hidden;
        }

        /* Top Bar - changement de fixed à sticky */
        .top-bar {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 10px 0;
            font-size: 14px;
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }


        /* Style pour les selects dans la top bar */
        .select-wrapper {
            position: relative;
            margin: 0 10px;
        }

        .top-bar-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: rgb(99, 112, 141);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 30px 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            width: auto;
            font-size: 14px;
        }

        .top-bar-select:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .top-bar-select:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        /* Ajouter une flèche custom */
        .select-wrapper::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--white);
            pointer-events: none;
        }

        /* Style pour les options */
        .top-bar-select option {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 10px;
        }


        /* Logo and Navigation - changement de fixed à sticky */
        .logo-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            background-color: var(--white);
            position: sticky;
            top: 43px;
            /* Hauteur approximative de la top-bar */
            left: 0;
            width: 100%;
            z-index: 999;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }






        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .top-bar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Ajoutez ce code après la définition de .top-bar-content */
        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        /* Dropdown Styling - Improved */
        .dropdown {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }

        /* Modifiez le style du dropdown pour s'adapter au contenu */
        .dropdown-btn {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
            width: auto;
        }

        /* Supprimer les icônes de flèche */
        .nav-link i,
        .dropdown-btn i {
            display: none;
        }

        .dropdown-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .dropdown-content {
            position: absolute;
            background-color: var(--white);
            min-width: 250px;
            box-shadow: var(--box-shadow);
            z-index: 10;
            border-radius: var(--border-radius);
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            overflow: hidden;
        }

        .dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
            font-weight: 400;
        }

        .dropdown-content a i {
            margin-right: 10px;
            color: var(--primary-color);
            display: inline-block;
        }

        .dropdown-content a:hover {
            background-color: var(--light-bg);
            padding-left: 20px;
        }

        .dropdown-content a:last-child {
            border-bottom: none;
        }

        .dropdown:hover .dropdown-content {
            display: block;
            opacity: 1;
            transform: translateY(0);
            animation: fadeInDown 0.3s ease forwards;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }



        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .logo-square {
            width: 36px;
            height: 36px;
            background-color: var(--primary-color);
            position: relative;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 2px 2px 0 var(--secondary-color);
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            letter-spacing: 1px;
        }

        .logo-icon {
            margin-left: 5px;
            color: var(--secondary-color);
            font-size: 14px;
        }

        /* Main Navigation - Style UCLouvain */
        .nav-container {
            display: flex;
            align-items: center;
            width: 100%;
        }

        nav {
            margin-left: 50px;
            flex-grow: 1;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        nav li {
            position: static;
            /* Important pour que le dropdown prenne toute la largeur */
        }

        nav a {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 500;
            padding: 8px 0;
            position: relative;
            transition: var(--transition);
        }

        nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--secondary-color);
            transition: var(--transition);
        }

        nav a:hover {
            color: var(--secondary-dark);
        }

        nav a:hover::after {
            width: 100%;
        }

        /* Nouvelle structure pour les dropdowns */
        .dropdown-menu {
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            width: 100%;
            max-height: 0;
            overflow: hidden;
            background-color: #f8f8f8;
            transition: max-height 0.5s ease-in-out, opacity 0.4s ease-in-out;
            z-index: 990;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            opacity: 0;
            display: block;
            /* Toujours affiché mais avec max-height:0 et opacity:0 */
            transform: none;
            animation: none;
        }

        nav li:hover .dropdown-menu {
            max-height: 500px;
            opacity: 1;
        }

        .dropdown-container {
            display: flex;
            padding: 30px 0;
            max-width: 1200px;
            margin: 0 auto;
            width: 90%;
        }

        .dropdown-column {
            flex: 1;
            padding: 0 20px;
        }

        .dropdown-column-title {
            font-size: 16px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--secondary-color);
        }

        .dropdown-link {
            display: block;
            padding: 10px 0;
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .dropdown-link i {
            display: inline-block;
            /* Afficher les icônes dans les liens */
            margin-right: 10px;
            color: var(--secondary-color);
        }

        .dropdown-link:hover {
            color: var(--primary-light);
            padding-left: 5px;
        }

        /* Search Box */
        .search-box {
            display: flex;
            align-items: center;
            position: relative;
            margin-left: auto;
        }

        .search-input {
            padding: 10px 18px;
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 30px;
            outline: none;
            transition: var(--transition);
            width: 200px;
            font-size: 14px;
        }

        .search-input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
            width: 220px;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            color: var(--primary-color);
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 40px 0;
            position: relative;
            margin-top: -20px;
            /* Ajuster si nécessaire pour éviter l'espace blanc */
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--secondary-color) 0%, rgba(255, 170, 0, 0) 70%);
            opacity: 0.2;
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .hero-content {
            padding: 40px 0;
            max-width: 60%;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 20px;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            position: relative;
            display: inline-block;
        }

        .hero h1::after {
            content: '';
            position: absolute;
            width: 70px;
            height: 4px;
            background-color: var(--secondary-color);
            bottom: -10px;
            left: 0;
            border-radius: 2px;
        }

        .hero p {
            font-size: 18px;
            max-width: 700px;
            margin-bottom: 30px;
            line-height: 1.8;
            font-weight: 300;
        }

        .hero-btns {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .hero-btn {
            background-color: var(--white);
            color: var(--primary-color);
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .hero-btn:hover {
            background-color: var(--secondary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* User Menu */
        .user-menu {
            position: absolute;
            right: 5%;
            top: 50%;
            transform: translateY(-50%);
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            width: 300px;
            box-shadow: var(--box-shadow);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }

        .user-menu h3 {
            margin-bottom: 20px;
            font-size: 20px;
            color: var(--primary-color);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .user-menu h3::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 3px;
            background-color: var(--secondary-color);
            bottom: 0;
            left: 0;
            border-radius: 1.5px;
        }

        .user-option {
            display: flex;
            align-items: center;
            padding: 15px;
            text-decoration: none;
            color: var(--text-color);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            transition: var(--transition);
            background-color: #f9f9f9;
        }

        .user-option:hover {
            background-color: var(--light-bg);
            transform: translateX(5px);
        }

        .user-icon {
            margin-right: 15px;
            width: 36px;
            height: 36px;
            background-color: var(--primary-light);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: var(--transition);
        }

        .user-option:hover .user-icon {
            background-color: var(--secondary-color);
            transform: scale(1.1);
        }

        .arrow-right {
            margin-left: auto;
            transition: var(--transition);
            color: var(--primary-color);
        }

        .user-option:hover .arrow-right {
            transform: translateX(3px);
        }

        /* Section Styling */
        .section {
            padding: 60px 0;
        }

        .section-title {
            font-size: 32px;
            margin-bottom: 30px;
            color: var(--primary-color);
            font-weight: 700;
            position: relative;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
        }

        .section-title::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 4px;
            background-color: var(--secondary-color);
            bottom: -10px;
            left: 0;
            border-radius: 2px;
        }

        .section-text {
            max-width: 800px;
            line-height: 1.8;
            margin-bottom: 40px;
            color: var(--text-light);
            font-size: 16px;
        }

        /* Card Grid and Cards */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .card-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: var(--transition);
        }

        .card:hover .card-img {
            transform: scale(1.03);
        }

        .card-content {
            padding: 20px;
        }

        .card-date {
            color: var(--text-light);
            margin-bottom: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: 600;
            line-height: 1.4;
            transition: var(--transition);
        }

        .card:hover .card-title {
            color: var(--secondary-dark);
        }

        .card-description {
            color: var(--text-light);
            margin-bottom: 15px;
            line-height: 1.6;
            font-size: 14px;
        }

        .card-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            gap: 5px;
            font-size: 14px;
            transition: var(--transition);
        }

        .card-link:hover {
            color: var(--secondary-dark);
            gap: 8px;
        }

        /* News Section */
        .news-section {
            background-color: var(--light-bg);
            position: relative;
            overflow: hidden;
        }

        .news-section::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background-color: rgba(0, 51, 102, 0.05);
            border-radius: 50%;
            top: -100px;
            left: -100px;
        }

        .news-section::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background-color: rgba(255, 170, 0, 0.05);
            border-radius: 50%;
            bottom: -50px;
            right: -50px;
        }

        .nav-arrows {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 25px;
        }

        .nav-arrow {
            width: 40px;
            height: 40px;
            background-color: var(--white);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-arrow:hover {
            background-color: var(--primary-color);
            color: var(--white);
            transform: scale(1.05);
        }

        /* Card badge */
        .card-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Training cards */
        .training-card {
            position: relative;
            overflow: hidden;
        }

        .training-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40%;
            background: linear-gradient(to top, rgba(0, 51, 102, 0.8), transparent);
            z-index: 1;
        }

        .training-card .card-content {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            color: var(--white);
            z-index: 2;
        }

        .training-card .card-title {
            color: var(--white);
            font-size: 22px;
            margin-bottom: 5px;
        }

        .training-card .card-img {
            height: 300px;
        }

        .training-icon {
            position: absolute;
            top: 20px;
            left: 20px;
            background-color: var(--white);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Animations */
        .animate-hover {
            transition: var(--transition);
        }

        .animate-hover:hover {
            transform: translateY(-3px);
        }

        /* Arrow down style */
        .arrow-down {
            border: solid white;
            border-width: 0 2px 2px 0;
            display: inline-block;
            padding: 3px;
            transform: rotate(45deg);
            margin-left: 5px;
            transition: var(--transition);
        }

        .dropdown:hover .arrow-down {
            transform: rotate(-135deg);
        }

        /* Correction pour les flèches dans la navigation principale */
        nav a i {
            display: none;
            /* Supprime la deuxième flèche */
        }

        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 98;
            display: none;
        }

        .mobile-menu-overlay.active {
            display: block;
        }

        /* Style pour le bouton hamburger visible sur mobile */
        .mobile-menu-toggle {
            display: none;
            /* Par défaut caché, affiché seulement en mobile */
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            color: var(--primary-color);
            background: none;
            border: none;
            cursor: pointer;
            z-index: 100;
        }

        /* Animation du slideDown pour le header sticky */
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
            }

            to {
                transform: translateY(0);
            }
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .dropdown-container {
                flex-direction: column;
            }

            .dropdown-column {
                margin-bottom: 20px;
            }

            body {
                padding-top: 103px;
                /* Ajuster selon votre hauteur mobile */
            }

            .hero-content {
                max-width: 100%;
            }

            .user-menu {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 60px;
                /* Positionnement après la top-bar */
                right: 20px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }

            nav {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                height: 100vh;
                background-color: var(--white);
                z-index: 1000;
                transition: right 0.3s ease;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                overflow-y: auto;
                padding: 80px 20px 20px;
                margin-top: 43px;
                /* Hauteur de la top-bar */
            }

            nav.active {
                right: 0;
            }

            nav ul {
                flex-direction: column;
                gap: 0;
            }

            nav li {
                width: 100%;
            }

            nav a {
                padding: 15px 0;
                display: block;
                border-bottom: 1px solid #eee;
            }

            .dropdown-menu {
                position: static;
                box-shadow: none;
                width: 100%;
                max-height: 0;
                overflow: hidden;
            }

            nav li.active .dropdown-menu {
                max-height: 1000px;
            }

            .dropdown-container {
                padding: 0;
            }

            .search-box {
                display: none;
            }

            .logo-nav {
                justify-content: flex-start;
                padding-left: 20px;
            }


            /* Ajoutez ces règles à la fin de votre section style */

            @media (max-width: 768px) {

                /* Cacher le select "Espace Doctoral" sur mobile */
                #my-inbtp,
                .top-bar-right .select-wrapper:first-child {
                    display: none;
                }

                /* Ajuster la position du toggle menu pour qu'il soit sur la même ligne que le logo */
                .mobile-menu-toggle {
                    position: relative;
                    top: 0;
                    right: 0;
                    margin-left: auto;
                    background: transparent;
                    color: var(--primary-color);
                    box-shadow: none;
                }

                /* Réorganiser la barre de navigation pour mobile */
                .nav-container {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    width: 100%;
                }


            }

            /* Styles améliorés pour le menu mobile */
            @media (max-width: 768px) {
                .mobile-menu-toggle {
                    display: block;
                    background: transparent;
                    border: none;
                    color: var(--primary-color);
                    font-size: 24px;
                    cursor: pointer;
                    margin-left: auto;
                    padding: 10px;
                    z-index: 1001;
                    transition: var(--transition);
                }

                .mobile-menu-toggle:focus {
                    outline: none;
                }

                /* Animation pour l'icône du menu */
                .mobile-menu-toggle i {
                    transition: transform 0.3s ease;
                }

                .mobile-menu-toggle:hover i {
                    transform: scale(1.1);
                }

                /* Style pour le menu mobile actif */
                nav.active {
                    right: 0;
                    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2);
                }

                /* Style pour les dropdowns en mobile */
                nav li.active .dropdown-menu {
                    max-height: 1000px;
                    opacity: 1;
                    padding: 10px 0;
                    margin: 5px 0 15px;
                    background-color: #f5f5f5;
                    border-radius: var(--border-radius);
                }

                /* Indicateur de dropdown en mobile */
                .nav-link .fa-chevron-down {
                    transition: transform 0.3s ease;
                }

                nav li.active .nav-link .fa-chevron-down {
                    transform: rotate(180deg);
                }

                /* Amélioration de la structure des dropdowns en mobile */
                .dropdown-container {
                    flex-direction: column;
                    padding: 0 15px;
                }

                .dropdown-column {
                    margin-bottom: 15px;
                    padding: 0;
                }

                .dropdown-column-title {
                    font-size: 15px;
                    margin-bottom: 10px;
                }

                .dropdown-link {
                    padding: 8px 0;
                    font-size: 14px;
                }

            }

            /* Hero section simplifiée sur mobile */
            @media (max-width: 768px) {

                .hero p,
                .hero-btns {
                    display: none;
                }

                .hero-content {
                    padding: 20px 0;
                    text-align: center;
                }

                .hero h1 {
                    font-size: 18px;
                }

                .hero h1::after {
                    left: 50%;
                    transform: translateX(-50%);
                }
            }

        }


        .card-img-container {
            position: relative;
            overflow: hidden;
        }

        .card-img-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            /* Overlay semi-transparent */
        }

        .training-card .card-content {
            position: relative;
            background-color: rgba(255, 255, 255, 0.95);
            /* Fond presque opaque */
            padding: 20px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            z-index: 2;
        }

        .training-card .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .training-card .card-description {
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .training-card .training-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 50px;
            height: 50px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            z-index: 3;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        a {
            text-decoration: none !important;
            color: inherit;
        }



        /* Preloader styles avec background dégradé */
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
            background: linear-gradient(135deg, #003366 0%, #0055a4 50%, #002244 100%);
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
        }

        .logo-preloader {
            animation: pulse 2s infinite;
            margin-bottom: 10px;
        }

        .logo-preloader img {
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
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
        }

        .loading-percentage {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .loading-text {
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            text-align: center;
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
            background: radial-gradient(circle, var(--primary-dark) 0%, rgba(0, 34, 68, 0) 70%);
            top: 40%;
            left: 25%;
            animation: float 10s ease-in-out infinite 1s;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes float {
            0% {
                transform: translateY(0) translateX(0);
            }

            50% {
                transform: translateY(-20px) translateX(10px);
            }

            100% {
                transform: translateY(0) translateX(0);
            }
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

        /* Animation d'entrée pour le contenu du preloader */
        .preloader-content {
            animation: scaleIn 0.5s ease-out forwards;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0.9);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
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

        /* Animation améliorée pour le texte de chargement */
        .loading-text {
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 1px;
            text-align: center;
            transition: opacity 0.2s ease;
        }

        /* Animation pour la barre de progression */
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

        /* Animation pour le conteneur du preloader */
        .preloader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            /*background-color: rgba(255, 255, 255, 0.95);*/
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            max-width: 90%;
            width: 400px;
            animation: scaleIn 0.5s ease-out forwards;
        }

        /* Effet de verre (glassmorphism) pour le conteneur */
        .preloader-content {
            /*background: rgba(255, 255, 255, 0.9);*/
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Animation d'entrée améliorée */
        @keyframes scaleIn {
            0% {
                transform: scale(0.9);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Animation de sortie améliorée */
        #preloader.loaded .preloader-content {
            animation: scaleOut 0.5s ease-in forwards;
        }

        @keyframes scaleOut {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            100% {
                transform: scale(1.1);
                opacity: 0;
            }
        }

        /* Animation du logo */
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

        @keyframes glow {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }
    </style>

</head>

<body>

        <!-- Preloader avec background dégradé -->
        <!-- Preloader avec background dégradé et particules -->
        <div id="preloader">
            <div class="preloader-background"></div>
            <canvas id="preloader-canvas"></canvas>
            <div class="preloader-content">
                <div class="logo-preloader">
                    <img src="uploads/logo.png" alt="Logo ISTM" height="80px">
                </div>
                <div class="loading-bar">
                    <div class="loading-progress"></div>
                </div>
                <div class="loading-percentage">0%</div>
                <div class="loading-text">Initialisation...</div>
            </div>
        </div>


        <!-- Top Bar avec select au lieu de dropdown -->
        <div class="top-bar">
            <div class="container top-bar-content">
                <div class="top-bar-left">
                    <div class="select-wrapper">
                        <select class="top-bar-select" id="user-type">
                            <option value="" disabled selected>Je suis</option>
                            <option value="etudiant">Étudiant·e</option>
                            <option value="enseignant">Enseignant·e</option>
                            <option value="chercheur">Chercheur·euse</option>
                            <option value="professionnel">Professionnel·le</option>
                            <option value="partenaire">Partenaire</option>
                            <option value="alumni">Alumni</option>
                            <option value="visiteur">Visiteur</option>
                        </select>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="select-wrapper">
                        <select class="top-bar-select" id="my-inbtp">
                            <option value="" disabled selected>My ISTM</option>
                            <option value="portail-etudiant">Portail étudiant</option>
                            <option value="portail-enseignant">Portail enseignant</option>
                            <option value="bibliotheque">Bibliothèque numérique</option>
                            <option value="intranet">Intranet</option>
                        </select>
                    </div>
                    <div class="select-wrapper">
                        <select class="top-bar-select" id="language">
                            <option value="fr" selected>FR</option>
                            <option value="en">English</option>
                            <option value="ln">Lingala</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

         <!-- Logo and Navigation -->
    <div class="logo-nav">
        <div class="container">
            <div class="nav-container">
                <a href="accueil" class="logo">
                    <img src="uploads/logo.png" alt="Logo ISTM" height="50px" width="40px">
                    <span class="logo-text">ISTM</span>
                    <span class="logo-icon">BENI</span>
                </a>
                <nav>
                    <ul class="nav-menu">
                        <?php
                        // Récupérer les menus depuis la base de données
                        $db = Connexion::getInstance()->getPDO();
                        
                        // Récupérer les menus principaux (position 'main' et pas de parent)
                        $mainMenus = [];
                        try {
                            $stmt = $db->prepare("SELECT * FROM menus WHERE position = 'main' AND parent_id IS NULL AND is_active = 1 ORDER BY order_index");
                            $stmt->execute();
                            $mainMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            // Si erreur, on utilisera les menus par défaut
                        }
                        
                        // Si aucun menu n'est trouvé, utiliser des menus par défaut
                        if (empty($mainMenus)) {
                            $defaultMenus = [
                                ['id' => 'default-1', 'name' => 'Formation', 'url' => '#', 'icon' => ''],
                                ['id' => 'default-2', 'name' => 'Recherche', 'url' => '#', 'icon' => ''],
                                ['id' => 'default-3', 'name' => 'Services', 'url' => '#', 'icon' => ''],
                                ['id' => 'default-4', 'name' => 'Vie à l\'ISTM', 'url' => '#', 'icon' => ''],
                                ['id' => 'default-5', 'name' => 'À propos', 'url' => '#', 'icon' => '']
                            ];
                            $mainMenus = $defaultMenus;
                        }
                        
                        // Afficher les menus principaux
                        foreach ($mainMenus as $menu):
                            // Récupérer les sous-menus (enfants)
                            $submenus = [];
                            $hasChildren = false;
                            
                            if (isset($menu['id']) && !strpos($menu['id'], 'default')) {
                                try {
                                    $stmt = $db->prepare("SELECT * FROM menus WHERE parent_id = :parent_id AND is_active = 1 ORDER BY order_index");
                                    $stmt->bindParam(':parent_id', $menu['id']);
                                    $stmt->execute();
                                    $submenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $hasChildren = !empty($submenus);
                                } catch (Exception $e) {
                                    // En cas d'erreur, on continuera sans sous-menus
                                }
                            }
                        ?>
                        <li class="nav-item">
                            <a href="<?php echo htmlspecialchars($menu['url']); ?>" class="nav-link">
                                <?php if (!empty($menu['icon'])): ?>
                                    <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-1"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($menu['name']); ?>
                            </a>
                            
                            <?php if ($hasChildren || strpos($menu['id'], 'default') !== false): ?>
                            <div class="dropdown-menu">
                                <div class="dropdown-container">
                                    <?php
                                    // Pour les menus par défaut, afficher les contenus statiques prédéfinis
                                    if (strpos($menu['id'], 'default') !== false) {
                                        // Définir les sous-menus par défaut en fonction de l'identifiant du menu parent
                                        $defaultSubmenus = [];
                                        
                                        if ($menu['id'] === 'default-1') { // Formation
                                            echo '<div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Formations initiales</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-graduation-cap"></i> Licence en sciences infirmières</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-user-md"></i> Techniques de laboratoire</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-heartbeat"></i> Santé publique</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-calendar-alt"></i> Calendrier académique</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Formations continues</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-briefcase"></i> Perfectionnement professionnel</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-certificate"></i> Certifications médicales</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-procedures"></i> Soins spécialisés</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-hospital"></i> Formation en milieu hospitalier</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Programmes spécialisés</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-microscope"></i> Recherche biomédicale</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-flask"></i> Laboratoires d\'analyse</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-book-medical"></i> Publications scientifiques</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Équipes pédagogiques</a>
                                            </div>';
                                        }
                                        else if ($menu['id'] === 'default-2') { // Recherche
                                            echo '<div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Axes de recherche</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-virus"></i> Maladies infectieuses</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-pills"></i> Pharmacologie</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-baby"></i> Santé maternelle et infantile</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-leaf"></i> Médecine traditionnelle</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Centres et plateformes</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-flask"></i> Laboratoires d\'analyses</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-microscope"></i> Centre de diagnostic</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-cogs"></i> Plateforme technologique</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-stethoscope"></i> Clinique universitaire</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Valorisation et partenariats</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-handshake"></i> Partenariats hospitaliers</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-globe-africa"></i> Collaborations internationales</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-lightbulb"></i> Innovations médicales</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-project-diagram"></i> Projets en cours</a>
                                            </div>';
                                        }
                                        else if ($menu['id'] === 'default-3') { // Services
                                            echo '<div class="dropdown-column">
                                                                                                <h3 class="dropdown-column-title">Services médicaux</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-clipboard-check"></i> Consultations</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-vial"></i> Analyses de laboratoire</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-search"></i> Dépistage</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-chart-line"></i> Suivi médical</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Services à la communauté</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-first-aid"></i> Campagnes de santé</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-hand-holding-medical"></i> Soins de proximité</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-shield-virus"></i> Prévention</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-ambulance"></i> Urgences</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Prestations spécialisées</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-vial"></i> Analyses spécifiques</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-x-ray"></i> Imagerie médicale</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-file-medical-alt"></i> Rapports médicaux</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-chalkboard-teacher"></i> Formation sanitaire</a>
                                            </div>';
                                        }
                                        else if ($menu['id'] === 'default-4') { // Vie à l'ISTM
                                            echo '<div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Campus et installations</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-map-marker-alt"></i> Notre campus</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-book"></i> Bibliothèque médicale</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-utensils"></i> Restauration</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-home"></i> Hébergement</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Vie étudiante</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-users"></i> Associations étudiantes</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-futbol"></i> Sports et loisirs</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-calendar-alt"></i> Événements</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-hands-helping"></i> Services aux étudiants</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">International</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-globe-africa"></i> Partenariats internationaux</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-plane-departure"></i> Mobilité étudiante</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-chalkboard-teacher"></i> Mobilité enseignante</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-handshake"></i> Projets humanitaires</a>
                                            </div>';
                                        }
                                        else if ($menu['id'] === 'default-5') { // À propos
                                            echo '<div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Notre institution</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-history"></i> Histoire et mission</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-chart-line"></i> Chiffres clés</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-medal"></i> Accréditations</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-sitemap"></i> Organisation</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Gouvernance</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-user-tie"></i> Direction générale</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-users-cog"></i> Conseil d\'administration</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-graduation-cap"></i> Conseil pédagogique</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-file-contract"></i> Rapports annuels</a>
                                            </div>
                                            <div class="dropdown-column">
                                                <h3 class="dropdown-column-title">Ressources</h3>
                                                <a href="#" class="dropdown-link"><i class="fas fa-newspaper"></i> Actualités</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-photo-video"></i> Médiathèque</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-download"></i> Documents officiels</a>
                                                <a href="#" class="dropdown-link"><i class="fas fa-envelope"></i> Contact</a>
                                            </div>';
                                        }
                                    } else {
                                        // Organiser les sous-menus en colonnes (maximum 3 colonnes)
                                        $columnCount = min(3, ceil(count($submenus) / 5)); // Définir le nombre de colonnes basé sur le nombre d'éléments
                                        $itemsPerColumn = ceil(count($submenus) / $columnCount);
                                        
                                        // Regrouper les sous-menus par catégorie (en utilisant l'URL pour simplifier)
                                        $categories = [];
                                        foreach ($submenus as $submenu) {
                                            $urlParts = explode('/', $submenu['name']);
                                            $category = !empty($urlParts[0]) ? $urlParts[0] : 'general';
                                            
                                            if (!isset($categories[$category])) {
                                                $categories[$category] = [];
                                            }
                                            $categories[$category][] = $submenu;
                                        }
                                        
                                        // S'il n'y a pas de catégories définies, créer une catégorie par défaut
                                        if (empty($categories)) {
                                            $categories['general'] = $submenus;
                                        }
                                        
                                        // Afficher les colonnes avec les sous-menus
                                        foreach ($categories as $category => $items) {
                                            echo '<div class="dropdown-column">';
                                            echo '<h3 class="dropdown-column-title">' . ucfirst(str_replace('-', ' ', $category)) . '</h3>';
                                            
                                            foreach ($items as $submenu) {
                                                echo '<a href="' . htmlspecialchars($submenu['url']) . '" class="dropdown-link">';
                                                if (!empty($submenu['icon'])) {
                                                    echo '<i class="' . htmlspecialchars($submenu['icon']) . ' me-1"></i> ';
                                                }
                                                echo htmlspecialchars($submenu['name']) . '</a>';
                                            }
                                            
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <div class="search-box">
                    <input type="text" placeholder="Rechercher..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
        </div>
    </div>
