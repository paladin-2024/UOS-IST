<?php
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour de votre profil | <?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></title>
    
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
        --header-height: 70px; /* Hauteur fixe du header pour les calculs */
    }

    /* Image de fond avec flou */
    .background-image {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('../uploads/inbtp-student.png'); /* Chemin vers votre image */
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        filter: blur(2px); /* Niveau de flou */
        opacity: 0.2; /* Opacité réduite pour ne pas distraire */
        z-index: -1; /* Derrière tous les éléments */
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
        z-index: 1030; /* Valeur plus élevée pour s'assurer qu'il est toujours au-dessus */
        height: var(--header-height);
        display: flex;
        align-items: center;
        transition: all 0.3s;
        backdrop-filter: blur(10px); /* Effet de flou pour un look moderne */
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
        max-height: 35px; /* Réduire légèrement la taille du logo */
        margin-right: 10px;
    }
    
    .main {
        padding: 20px 0 50px 0; /* Ajuster le padding plutôt que la marge */
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
        
        
        
        
        
        
        .step-label {
            text-align: center;
            font-size: 0.85rem;
            margin-top: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        /* Form Styling */
        .form-section {
            display: none;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }
        
        .form-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
            animation: fadeInUp 0.5s ease forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(108, 117, 125, 0.2);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #43a047;
            border-color: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(76, 175, 80, 0.2);
        }
        
        /* Profile Upload */
        .profile-upload {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-upload .preview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            margin: 0 auto 25px;
            border: 3px solid #fff;
            overflow: hidden;
            position: relative;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .profile-upload .preview:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .profile-upload .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }
        
        .profile-upload .preview .placeholder-icon {
            font-size: 60px;
            color: #adb5bd;
            transition: all 0.3s ease;
        }
        
        .custom-file-upload {
            display: inline-block;
            cursor: pointer;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.2);
        }
        
        .custom-file-upload:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
        }
        
        #fileUpload {
            display: none;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }
        
        .welcome-banner h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .welcome-banner .lead {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
            margin-bottom: 15px;
        }

        .welcome-illustration {
            position: relative;
            z-index: 2;
        }
        
        .welcome-banner .icon {
            font-size: 90px;
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* Matricule Verification Section */
        .matricule-verification-section {
            max-width: 600px;
            margin: 30px auto 0;
        }

        .student-academic-info {
            position: relative;
            z-index: 2;
        }

        /* Rendre le header plus discret lors du défilement */
        .header.scrolled {
            height: 60px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: rgba(255, 255, 255, 0.98);
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

        .verification-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
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
        
        /* Confirmation Step */
        .list-group-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .list-group-item strong {
            color: var(--primary-color);
        }

        .academic-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.95rem;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .academic-badge:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .academic-badge i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Indicateurs d'étape professionnels */
.steps-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    position: relative;
    padding: 0 10px;
}

/* Ligne de progression entre les étapes */
.steps-container::before {
    content: '';
    position: absolute;
    top: 25px; /* Centre de l'étape */
    left: 0;
    right: 0;
    height: 4px;
    background: #e9ecef;
    z-index: 1;
}

/* Ligne de progression active */
.steps-container .progress-line {
    position: absolute;
    top: 25px;
    left: 0;
    height: 4px;
    background: linear-gradient(to right, var(--primary-color), var(--success-color));
    z-index: 2;
    transition: width 0.5s ease;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(67, 97, 238, 0.3);
}

.step {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #6c757d;
    position: relative;
    z-index: 3;
    transition: all 0.4s ease;
    border: 2px solid #e9ecef;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

/* Étape active */
.step.active {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: #fff;
    border-color: var(--primary-color);
    transform: scale(1.1);
    box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
}

/* Étape complétée */
.step.completed {
    background: linear-gradient(135deg, var(--success-color) 0%, #009688 100%);
    color: #fff;
    border-color: var(--success-color);
    box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
}

/* Contenu de l'étape (numéro ou icône) */
.step-content {
    font-size: 1.2rem;
    line-height: 1;
}

.step.completed .step-content {
    font-size: 1.4rem;
}

/* Label de l'étape */
.step-label {
    position: absolute;
    top: 60px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    width: 120px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    transition: all 0.3s ease;
}

.step.active .step-label {
    color: var(--primary-color);
    font-weight: 700;
}

.step.completed .step-label {
    color: var(--success-color);
}



        
        /* Animations */
        .pulse {
            animation: pulse 2s infinite;
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
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .welcome-banner {
                padding: 30px;
            }
            
            .welcome-banner h1 {
                font-size: 1.8rem;
            }
            
            .welcome-banner .lead {
                max-width: 100%;
            }
            
            
        }
        @media (max-width: 768px) {
        :root {
            --header-height: 60px; /* Header plus petit sur mobile */
        }
        
        .header .container {
            padding: 0 15px; /* Ajouter un peu d'espace sur les côtés */
        }
        
        .logo {
            font-size: 1.2rem;
        }
        
        .logo img {
            max-height: 30px;
        }
        
        .verification-card {
            padding: 25px; /* Réduire le padding sur mobile */
        }
    }

    .student-photo-container {
        display: flex;
        justify-content: flex-end;
        margin-right: 20px;
    }

    .welcome-illustration {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        height: 100%;
    }

    </style>
</head>
<body>
    <div class="background-image"></div>
    <!-- Remplacer le header par cette version améliorée -->
    <header id="header" class="header">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="profil_etudiant" class="logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="../<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <span><?= htmlspecialchars($configUniversite['sigle'].' - NUMERIQUE' ?? 'E-GESTION') ?></span>
            </a>
            
            <!-- Optionnel: Ajouter un lien de retour pour les étudiants -->
            <a href="profil_etudiant" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-house-door"></i> Accueil
            </a>
        </div>
    </header>

    <main id="main" class="main">
        <div class="container">
            <div class="matricule-verification-section" id="matriculeVerification" data-aos="fade-up" data-aos-delay="100">
                <div class="card verification-card">
                    <div class="card-body">
                        <div class="icon animate__animated animate__pulse animate__infinite">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h2 class="card-title">Vérification de votre statut</h2>
                        <p class="card-text">Veuillez saisir votre numéro matricule pour continuer</p>
                        
                        <form id="matriculeForm" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <input type="text" class="form-control form-control-lg" id="matricule" placeholder="Votre matricule" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir votre matricule.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-search me-2"></i> Vérifier mon statut
                            </button>
                            <a href="etudiants/presence_qrcode" class="btn btn-secondary btn-lg w-100 mt-2">
                                <i class="bi bi-search me-2"></i> Signer la Présence
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <div id="profileUpdateSection" style="display: none;">
            <div class="welcome-banner" data-aos="fade-up">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h1>Bienvenue, <span id="studentName">Étudiant</span>!</h1>
                        <p class="lead">Nous sommes ravis de vous compter parmi nos étudiants. Ci-dessous, votre Promotion, orientation et section d'affectation.</p>
                        
                        <div class="student-academic-info mt-3">
                            <div class="d-flex flex-wrap">
                                <div class="academic-badge me-2 mb-2">
                                    <i class="bi bi-mortarboard"></i> <span id="studentPromotion">-</span>
                                </div>
                                <div class="academic-badge me-2 mb-2">
                                    <i class="bi bi-diagram-3"></i> <span id="studentOrientation">-</span>
                                </div>
                                <div class="academic-badge mb-2">
                                    <i class="bi bi-book"></i> <span id="studentSection">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 text-end">
                        <div class="welcome-illustration">
                            <div class="student-photo-container">
                                <img id="welcomeStudentPhoto" src="../assets/img/profile-placeholder.jpg" alt="Photo de l'étudiant" class="img-fluid rounded-circle border border-3 border-white shadow" style="width: 140px; height: 140px; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>


                <div class="card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Mise à jour de votre profil</h5>
                        
                        <div class="steps-container">
                            <div class="progress-line" id="progress-line" style="width: 0%"></div>
                            
                            <div class="step active" id="step1">
                                <div class="step-content">1</div>
                                <div class="step-label">Informations</div>
                            </div>
                            
                            <div class="step" id="step2">
                                <div class="step-content">2</div>
                                <div class="step-label">Coordonnées</div>
                            </div>
                            
                            <div class="step" id="step3">
                                <div class="step-content">3</div>
                                <div class="step-label">Photo de profil</div>
                            </div>
                            
                            <div class="step" id="step4">
                                <div class="step-content">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="step-label">Confirmation</div>
                            </div>
                        </div>

                        
                        <form id="profileUpdateForm" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" id="studentId" name="studentId">
                            
                            <!-- Étape 1: Informations personnelles -->
                            <div class="form-section active" id="section1">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="noms" class="form-label">Nom complet <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                                            <input type="text" class="form-control" id="noms" name="noms" required>
                                        </div>
                                        <div class="invalid-feedback">Veuillez saisir votre nom complet.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lieuNaissance" class="form-label">Lieu de naissance</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                            <input type="text" class="form-control" id="lieuNaissance" name="lieuNaissance">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dateNaissance" class="form-label">Date de naissance</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                            <input type="date" class="form-control" id="dateNaissance" name="dateNaissance">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                                            <select class="form-select" id="sexe" name="sexe" required>
                                                <option value="">Sélectionner</option>
                                                <option value="Masculin">Masculin</option>
                                                <option value="Feminin">Féminin</option>
                                            </select>
                                        </div>
                                        <div class="invalid-feedback">Veuillez sélectionner votre sexe.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nationalite" class="form-label">Nationalité <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-flag"></i></span>
                                            <input type="text" class="form-control" id="nationalite" name="nationalite" required>
                                        </div>
                                        <div class="invalid-feedback">Veuillez saisir votre nationalité.</div>
                                    </div>
                                </div>
                                <div class="mt-4 d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary next-step">
                                        <i class="bi bi-arrow-right me-2"></i> Suivant
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Étape 2: Coordonnées -->
                            <div class="form-section" id="section2">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="adressemail" class="form-label">Adresse email <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control" id="adressemail" name="adressemail" required>
                                        </div>
                                        <div class="invalid-feedback">Veuillez saisir une adresse email valide.</div>
                                    </div>
                                    <!-- Modifiez le champ téléphone existant pour ajouter un bouton de vérification -->
                                    <div class="col-md-6">
                                        <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                            <span class="input-group-text">+243</span>
                                            <input type="tel" class="form-control" id="telephone" name="telephone" required placeholder="Ex: 976526633 (9 chiffres)">
                                            <button type="button" id="sendOtpBtn" class="btn btn-primary">
                                                <i class="bi bi-shield-check me-1"></i> Vérifier
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Veuillez saisir votre numéro de téléphone.</div>
                                        <div id="phoneVerificationStatus" class="form-text"></div>
                                        <small class="form-text text-muted">Entrez uniquement les chiffres sans le préfixe +243</small>
                                    </div>



                                    <div class="col-md-12 otp-verification-section" style="display: none;">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Un code de vérification a été envoyé à votre numéro de téléphone. Veuillez le saisir ci-dessous, ou soit <b>436432</b> en attendant..
                                        </div>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label for="otp" class="form-label">Code de vérification</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                                    <input type="text" class="form-control" id="otp" name="otp" placeholder="Entrez le code à 6 chiffres">
                                                    <div class="invalid-feedback">Veuillez saisir le code de vérification </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <button type="button" id="verifyOtpBtn" class="btn btn-success w-100">
                                                    <i class="bi bi-check-circle me-2"></i> Vérifier
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" id="resendOtpBtn" class="btn btn-link">
                                                <i class="bi bi-arrow-repeat me-1"></i> Renvoyer le code
                                            </button>
                                            <span id="otpTimer" class="text-muted"></span>
                                        </div>
                                    </div>


                                    <div class="col-md-12">
                                        <label for="adresse" class="form-label">Adresse</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-house"></i></span>
                                            <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label for="personne_contact" class="form-label">Personne à contacter en cas d'urgence</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                                            <input type="text" class="form-control" id="personne_contact" name="personne_contact">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="telephone_contact" class="form-label">Téléphone de la personne à contacter</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-step">
                                        <i class="bi bi-arrow-left me-2"></i> Précédent
                                    </button>
                                    <button type="button" class="btn btn-primary next-step">
                                        <i class="bi bi-arrow-right me-2"></i> Suivant
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Étape 3: Photo de profil -->
                            <div class="form-section" id="section3">
                                <div class="profile-upload">
                                    <div class="preview">
                                        <div class="placeholder-icon">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <img id="profileImage" src="" style="display: none;">
                                    </div>
                                    <label for="fileUpload" class="custom-file-upload">
                                        <i class="bi bi-upload me-2"></i> Choisir une photo
                                    </label>
                                    <input type="file" id="fileUpload" name="photo" accept="image/*">
                                    <!-- Ajouter ceci dans le formulaire -->
                                    <input type="hidden" id="existingPhoto" name="existingPhoto" value="">

                                    <div class="invalid-feedback d-block text-center mt-2" id="photoFeedback" style="display: none !important;">
                                        Veuillez télécharger une photo de profil.
                                    </div>
                                    <div class="alert alert-info mt-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Formats acceptés:</strong> JPG, PNG. <strong>Taille maximale:</strong> 2 Mo.
                                        <br>La photo doit clairement montrer votre visage et être de bonne qualité.
                                    </div>
                                </div>
                                <div class="mt-4 d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-step">
                                        <i class="bi bi-arrow-left me-2"></i> Précédent
                                    </button>
                                    <button type="button" class="btn btn-primary next-step">
                                        <i class="bi bi-arrow-right me-2"></i> Suivant
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Étape 4: Confirmation -->
                            <div class="form-section" id="section4">
                                <div class="text-center mb-4">
                                    <i class="bi bi-check-circle-fill" style="font-size: 70px; color: var(--success-color);"></i>
                                    <h4 class="mt-3">Vérifiez vos informations</h4>
                                    <p class="text-muted">Veuillez confirmer que toutes les informations saisies sont correctes avant de finaliser.</p>
                                </div>
                                
                                <div class="row">
                                <div class="col-md-6">
                                        <h5 class="text-primary mb-3"><i class="bi bi-person-lines-fill me-2"></i>Informations personnelles</h5>
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item"><strong>Matricule:</strong> <span id="confirm-matricule"></span></li>
                                            <li class="list-group-item"><strong>Nom:</strong> <span id="confirm-noms"></span></li>
                                            <li class="list-group-item"><strong>Sexe:</strong> <span id="confirm-sexe"></span></li>
                                            <li class="list-group-item"><strong>Date de naissance:</strong> <span id="confirm-dateNaissance"></span></li>
                                            <li class="list-group-item"><strong>Lieu de naissance:</strong> <span id="confirm-lieuNaissance"></span></li>
                                            <li class="list-group-item"><strong>Nationalité:</strong> <span id="confirm-nationalite"></span></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="text-primary mb-3"><i class="bi bi-geo-alt-fill me-2"></i>Coordonnées</h5>
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item"><strong>Email:</strong> <span id="confirm-adressemail"></span></li>
                                            <li class="list-group-item"><strong>Téléphone:</strong> <span id="confirm-telephone"></span></li>
                                            <li class="list-group-item"><strong>Adresse:</strong> <span id="confirm-adresse"></span></li>
                                            <li class="list-group-item"><strong>Contact d'urgence:</strong> <span id="confirm-personne_contact"></span></li>
                                            <li class="list-group-item"><strong>Téléphone du contact:</strong> <span id="confirm-telephone_contact"></span></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    En cliquant sur "Confirmer et Enregistrer", vous certifiez que les informations saisies sont exactes.
                                </div>
                                
                                <div class="mt-4 d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-step">
                                        <i class="bi bi-arrow-left me-2"></i> Précédent
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-lg me-2"></i> Confirmer et Enregistrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer id="footer" class="footer">
        <div class="container">
            <div class="copyright">
                &copy; <?= date('Y') ?> <strong><span><?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></span></strong>. Tous droits réservés
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser les animations AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true
            });
            
            const matriculeForm = document.getElementById('matriculeForm');
            const profileUpdateForm = document.getElementById('profileUpdateForm');
            const matriculeVerification = document.getElementById('matriculeVerification');
            const profileUpdateSection = document.getElementById('profileUpdateSection');
            
            // Navigation entre les étapes
            let currentStep = 1;
            
            // Gestionnaire pour le bouton "Suivant"
            document.querySelectorAll('.next-step').forEach(button => {
                button.addEventListener('click', function() {
                    goToNextStep();
                });
            });
            
            // Gestionnaire pour le bouton "Précédent"
            document.querySelectorAll('.prev-step').forEach(button => {
                button.addEventListener('click', function() {
                    goToPrevStep();
                });
            });
            
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
            
            // Traitement de la photo de profil
            const fileUpload = document.getElementById('fileUpload');
            const profileImage = document.getElementById('profileImage');
            
            fileUpload.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profileImage.src = e.target.result;
                        profileImage.style.display = 'block';
                        document.querySelector('.placeholder-icon').style.display = 'none';
                        document.getElementById('photoFeedback').style.display = 'none !important';
                        fileUpload.classList.remove('is-invalid');
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Soumission du formulaire de mise à jour du profil
            profileUpdateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Vérification de la validation du formulaire
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                // Vérifier si une photo a été téléchargée OU si une photo existante est présente
                if ((!fileUpload.files || !fileUpload.files[0]) && !document.getElementById('existingPhoto').value) {
                    document.getElementById('photoFeedback').style.display = 'block !important';
                    fileUpload.classList.add('is-invalid');
                    return;
                }
                
                // Soumettre le formulaire via AJAX
                submitProfileForm();
            });
            
            // Fonction pour passer à l'étape suivante
            function goToNextStep() {
                // Validation de l'étape actuelle
                const currentSection = document.getElementById(`section${currentStep}`);
                const inputs = currentSection.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (!input.value) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Champs manquants',
                        text: 'Veuillez remplir tous les champs obligatoires avant de continuer.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }
                
                // Si étape 3 (photo), vérifier si une photo a été téléchargée ou existe déjà
                if (currentStep === 3) {
                    const existingPhoto = document.getElementById('existingPhoto').value;
                    if (!existingPhoto && (!fileUpload.files || !fileUpload.files[0])) {
                        document.getElementById('photoFeedback').style.display = 'block !important';
                        fileUpload.classList.add('is-invalid');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Photo manquante',
                            text: 'Veuillez télécharger une photo de profil avant de continuer.',
                            confirmButtonColor: '#4361ee'
                        });
                        return;
                    }
                }

                // Si c'est l'étape 3 (avant confirmation), pré-remplir les champs de confirmation
                if (currentStep === 3) {
                    prepareConfirmationStep();
                }
                
                // Passer à l'étape suivante
                document.getElementById(`section${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}`).classList.add('completed');
                
                currentStep++;
                
                document.getElementById(`section${currentStep}`).classList.add('active');
                document.getElementById(`step${currentStep}`).classList.add('active');

                updateProgressBar();
                
                // Faire défiler vers le haut pour montrer le début de la nouvelle étape
                window.scrollTo({
                    top: document.querySelector('.steps-container').offsetTop - 100,
                    behavior: 'smooth'
                });
            }
            
            // Fonction pour revenir à l'étape précédente
            function goToPrevStep() {
                document.getElementById(`section${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}`).classList.remove('active');
                
                currentStep--;
                
                document.getElementById(`section${currentStep}`).classList.add('active');
                document.getElementById(`step${currentStep}`).classList.add('active');
                document.getElementById(`step${currentStep}`).classList.remove('completed');

                updateProgressBar();
                
                // Faire défiler vers le haut
                window.scrollTo({
                    top: document.querySelector('.steps-container').offsetTop - 100,
                    behavior: 'smooth'
                });
            }
            
            // Fonction pour préparer l'étape de confirmation
            function prepareConfirmationStep() {
                // Récupérer toutes les valeurs du formulaire
                document.getElementById('confirm-matricule').textContent = document.getElementById('matricule').value;
                document.getElementById('confirm-noms').textContent = document.getElementById('noms').value;
                document.getElementById('confirm-sexe').textContent = document.getElementById('sexe').value;
                document.getElementById('confirm-dateNaissance').textContent = document.getElementById('dateNaissance').value || 'Non spécifié';
                document.getElementById('confirm-lieuNaissance').textContent = document.getElementById('lieuNaissance').value || 'Non spécifié';
                document.getElementById('confirm-nationalite').textContent = document.getElementById('nationalite').value;
                document.getElementById('confirm-adressemail').textContent = document.getElementById('adressemail').value;
                document.getElementById('confirm-telephone').textContent = document.getElementById('telephone').value;
                // Dans la fonction prepareConfirmationStep()
                document.getElementById('confirm-telephone').textContent = "+243 " + document.getElementById('telephone').value;

                document.getElementById('confirm-adresse').textContent = document.getElementById('adresse').value || 'Non spécifié';
                document.getElementById('confirm-personne_contact').textContent = document.getElementById('personne_contact').value || 'Non spécifié';
                document.getElementById('confirm-telephone_contact').textContent = document.getElementById('telephone_contact').value || 'Non spécifié';
            }
            
            // Fonction pour vérifier le matricule
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
                
                // Requête AJAX pour vérifier le matricule
                fetch('../controller/verify_matricule.php', {
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
        title: 'Félicitations!',
        text: `Bienvenue ${data.student.noms}! Vous êtes bien inscrit dans notre établissement.`,
        confirmButtonText: 'Continuer',
        confirmButtonColor: '#4361ee'
    }).then((result) => {
        if (result.isConfirmed) {
            // Pré-remplir le formulaire avec les données existantes
            document.getElementById('studentId').value = data.student.idetudiant;
            document.getElementById('noms').value = data.student.noms;
            document.getElementById('lieuNaissance').value = data.student.lieuNaissance || '';
            document.getElementById('dateNaissance').value = data.student.dateNaissance || '';

            if (data.student.photo) {
                document.getElementById('welcomeStudentPhoto').src = '../' + data.student.photo;
            } else {
                document.getElementById('welcomeStudentPhoto').src = '../uploads/user.png';
            }
            document.getElementById('sexe').value = data.student.sexe || '';
            document.getElementById('nationalite').value = data.student.nationalite || '';
            document.getElementById('adressemail').value = data.student.adressemail || '';
            let telephone = data.student.telephone || '';
            if (telephone.startsWith('243')) {
                telephone = telephone.substring(3); // Enlever les 3 premiers caractères (243)
            }
            document.getElementById('telephone').value = telephone;

            if (telephone && telephone.length > 0) {
                // Marquer le téléphone comme vérifié
                phoneVerified = true;
                
                // Désactiver le champ téléphone et le bouton de vérification
                document.getElementById('telephone').setAttribute('readonly', 'readonly');
                document.getElementById('sendOtpBtn').disabled = true;
                
                // Ajouter un indicateur visuel que le numéro est déjà vérifié
                document.getElementById('phoneVerificationStatus').innerHTML = 
                    '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Numéro déjà vérifié</span>';
            }
            
            document.getElementById('adresse').value = data.student.adresse || '';
            document.getElementById('personne_contact').value = data.student.personne_contact || '';
            document.getElementById('telephone_contact').value = data.student.telephone_contact || '';
            
            // Afficher le nom de l'étudiant et ses informations académiques
            document.getElementById('studentName').textContent = data.student.noms;
            document.getElementById('studentPromotion').textContent = data.student.designationPromotion || 'Non définie';
            document.getElementById('studentOrientation').textContent = data.student.designationOrientation || 'Non définie';
            document.getElementById('studentSection').textContent = data.student.designationSection || 'Non définie';

            // Dans la partie où vous pré-remplissez le formulaire
            if (data.student.photo) {
                // Mettre à jour l'image de prévisualisation
                const profileImage = document.getElementById('profileImage');
                profileImage.src = '../' + data.student.photo;
                profileImage.style.display = 'block';
                
                // Masquer l'icône placeholder
                document.querySelector('.profile-upload .placeholder-icon').style.display = 'none';
                
                // Stocker le chemin de la photo existante
                document.getElementById('existingPhoto').value = data.student.photo;
            }

            
            // Masquer la section de vérification et afficher le formulaire avec une animation
            matriculeVerification.style.display = 'none';
            profileUpdateSection.style.display = 'block';
            
            // Initialiser la barre de progression
            updateProgressBar();
            
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
                    console.error('Erreur lors de la vérification:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la vérification. Veuillez réessayer plus tard.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                });
            }
            
            // Fonction pour soumettre le formulaire de mise à jour du profil
            function submitProfileForm() {
                // Créer un objet FormData pour inclure les fichiers
                const formData = new FormData(profileUpdateForm);

                // Ajouter le préfixe 243 au numéro de téléphone
                const phoneInput = document.getElementById('telephone').value;
                formData.set('telephone', '243' + phoneInput);

                            // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Enregistrement...',
                    text: 'Nous enregistrons vos informations',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Soumettre le formulaire via AJAX
                fetch('../controller/update_profile_etudiant.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher un message de succès
                        Swal.fire({
                            icon: 'success',
                            title: 'Profil mis à jour!',
                            text: 'Vos informations ont été enregistrées avec succès. Merci!',
                            confirmButtonText: 'Terminer',
                            confirmButtonColor: '#4CAF50'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Revenir au welcome-banner plutôt que rediriger
                                matriculeVerification.style.display = 'none';
                                profileUpdateSection.style.display = 'none';
                                
                                // Mettez à jour le welcome-banner avec les nouvelles informations
                                document.getElementById('studentName').textContent = document.getElementById('noms').value;
                                
                                // Mettre à jour la photo du welcome-banner si une nouvelle a été uploadée
                                if (fileUpload.files && fileUpload.files[0]) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        document.getElementById('welcomeStudentPhoto').src = e.target.result;
                                    }
                                    reader.readAsDataURL(fileUpload.files[0]);
                                }
                                
                                // Afficher le welcome-banner
                                const welcomeBanner = document.createElement('div');
                                welcomeBanner.className = 'container';
                                welcomeBanner.innerHTML = document.querySelector('.welcome-banner').outerHTML;
                                document.getElementById('main').innerHTML = '';
                                document.getElementById('main').appendChild(welcomeBanner);
                                
                                // Animer l'apparition
                                AOS.refresh();
                            }
                        });

                    } else {
                        // Afficher un message d'erreur
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de l\'enregistrement de vos informations.',
                            confirmButtonText: 'Réessayer',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la soumission:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer plus tard.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                });
            }
            
            // Gestionnaires d'événements pour la validation en temps réel
            document.querySelectorAll('input[required], select[required]').forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
                
                input.addEventListener('change', function() {
                    if (this.value) {
                        this.classList.remove('is-invalid');
                    }
                });
            });
            
            // Validation de l'email en temps réel
            document.getElementById('adressemail').addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    this.classList.add('is-invalid');
                    this.nextElementSibling.textContent = 'Veuillez saisir une adresse email valide.';
                } else if (this.value) {
                    this.classList.remove('is-invalid');
                }
            });
            
            // Validation du téléphone en temps réel
            document.getElementById('telephone').addEventListener('input', function() {
                // Supprimer tout caractère non numérique
                this.value = this.value.replace(/\D/g, '');
                
                // Vérifier que le numéro ne commence pas par 0
                if (this.value.startsWith('0')) {
                    this.value = this.value.substring(1);
                }
                
                // Limiter à 9 chiffres maximum
                if (this.value.length > 9) {
                    this.value = this.value.substring(0, 9);
                }
                
                // Référence au bouton de vérification
                const verifyButton = document.getElementById('sendOtpBtn');
                
                // Valider le format (9 chiffres et ne commence pas par 0)
                const phoneRegex = /^[1-9][0-9]{8}$/;
                if (this.value && !phoneRegex.test(this.value)) {
                    this.classList.add('is-invalid');
                    this.nextElementSibling.textContent = 'Numéro invalide. Doit contenir 9 chiffres et ne pas commencer par 0.';
                    
                    // Désactiver le bouton si le format est invalide
                    verifyButton.disabled = true;
                    verifyButton.innerHTML = '<i class="bi bi-shield-x me-1"></i> Format invalide';
                } else if (this.value) {
                    this.classList.remove('is-invalid');
                    
                    // Réactiver le bouton et restaurer son texte original
                    verifyButton.disabled = false;
                    verifyButton.innerHTML = '<i class="bi bi-shield-check me-1"></i> Vérifier';
                } else {
                    // Champ vide
                    verifyButton.disabled = true;
                    verifyButton.innerHTML = '<i class="bi bi-shield-check me-1"></i> Vérifier';
                }
            });



        });

        // Rendre le header plus discret lors du défilement
window.addEventListener('scroll', function() {
    const header = document.getElementById('header');
    if (window.scrollY > 50) {
        header.classList.add('scrolled');
    } else {
        header.classList.remove('scrolled');
    }
});

function updateProgressBar() {
    const progressLine = document.getElementById('progress-line');
    // Calculer le pourcentage de progression basé sur l'étape actuelle
    const percentage = ((currentStep - 1) / 3) * 100;
    progressLine.style.width = `${percentage}%`;
}



// Ajouter ce code dans la section script existante
// Gestion de la vérification OTP
let otpSent = false;
let phoneVerified = false;
let originalPhoneValue = '';
let otpValue = '';
let countdown = 0;
let countdownTimer;

let verificationProcessStarted = false;

document.getElementById('sendOtpBtn').addEventListener('click', function() {
    const phoneInput = document.getElementById('telephone').value;
    // Vérifier si le numéro est au format valide (9 chiffres et ne commence pas par 0)
    const phoneRegex = /^[1-9][0-9]{8}$/;
    
    if (!phoneInput || !phoneRegex.test(phoneInput)) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Veuillez saisir un numéro de téléphone valide (9 chiffres et ne commençant pas par 0).',
            confirmButtonColor: '#4361ee'
        });
        return;
    }

    // Ajouter le préfixe 243 pour l'envoi
    const phone = "243" + phoneInput;

    // Afficher l'indicateur de chargement
    Swal.fire({
        title: 'Envoi en cours...',
        text: 'Nous envoyons un code de vérification à votre numéro',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Générer un code OTP à 6 chiffres
    otpValue = Math.floor(100000 + Math.random() * 900000).toString();
    
    // Préparer le message SMS
    const msg = `Votre code de verification est : ${otpValue}. Ne partagez ce code avec personne.`;
    
    // Envoyer le SMS en utilisant votre API
    fetch('../controller/send_otp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `phone=${encodeURIComponent(phone)}&message=${encodeURIComponent(msg)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Afficher la section de vérification OTP
            document.querySelector('.otp-verification-section').style.display = 'block';
            otpSent = true;

            verificationProcessStarted = true;
            
            // Démarrer le compte à rebours pour le renvoi
            startOtpCountdown();
            
            Swal.fire({
                icon: 'success',
                title: 'Code envoyé',
                text: 'Un code de vérification a été envoyé à votre numéro de téléphone.',
                confirmButtonColor: '#4361ee'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Impossible d\'envoyer le code de vérification. Veuillez réessayer.',
                confirmButtonColor: '#4361ee'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue. Veuillez réessayer plus tard.',
            confirmButtonColor: '#4361ee'
        });
    });
});

document.getElementById('verifyOtpBtn').addEventListener('click', function() {
    const enteredOtp = document.getElementById('otp').value;
    
    if (!enteredOtp) {
        document.getElementById('otp').classList.add('is-invalid');
        return;
    }
    
    if (enteredOtp === otpValue || enteredOtp === '436432') {
        // OTP valide
        phoneVerified = true;
        verificationProcessStarted = false;
        
        document.getElementById('phoneVerificationStatus').innerHTML = 
            '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Numéro vérifié</span>';
        
        document.getElementById('telephone').setAttribute('readonly', 'readonly');
        document.getElementById('sendOtpBtn').disabled = true;
        document.querySelector('.otp-verification-section').style.display = 'none';
        
        Swal.fire({
            icon: 'success',
            title: 'Vérifié!',
            text: 'Votre numéro de téléphone a été vérifié avec succès.',
            confirmButtonColor: '#4CAF50'
        });
    } else {
        // OTP invalide
        Swal.fire({
            icon: 'error',
            title: 'Code incorrect',
            text: 'Le code de vérification saisi est incorrect. Veuillez réessayer.',
            confirmButtonColor: '#4361ee'
        });
    }
});

document.getElementById('resendOtpBtn').addEventListener('click', function() {
    if (countdown > 0) return;
    
    // Réinitialiser et renvoyer le code
    document.getElementById('sendOtpBtn').click();
});

function startOtpCountdown() {
    // Désactiver le bouton de renvoi pendant 60 secondes
    countdown = 60;
    document.getElementById('resendOtpBtn').disabled = true;
    
    countdownTimer = setInterval(function() {
        countdown--;
        document.getElementById('otpTimer').textContent = `(${countdown}s)`;
        
        if (countdown <= 0) {
            clearInterval(countdownTimer);
            document.getElementById('resendOtpBtn').disabled = false;
            document.getElementById('otpTimer').textContent = '';
        }
    }, 1000);
}

document.querySelectorAll('.next-step').forEach(button => {
    const originalClickHandler = button.onclick;
    
    button.onclick = function(e) {
        // Si nous sommes à l'étape 2 (coordonnées)
        if (currentStep === 2) {
            const phoneInput = document.getElementById('telephone');

            // Si un processus de vérification a été initié mais n'est pas terminé
            if (verificationProcessStarted && !phoneVerified) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Vérification en cours',
                    text: 'Vous devez terminer la vérification de votre numéro de téléphone avant de continuer.',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            // Si le champ téléphone est requis et rempli mais pas vérifié
            if (phoneInput.hasAttribute('required') && phoneInput.value && !phoneVerified) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Vérification requise',
                    text: 'Veuillez vérifier votre numéro de téléphone avant de continuer.',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
            
            // Si le champ téléphone est requis mais vide
            if (phoneInput.hasAttribute('required') && !phoneInput.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Champ requis',
                    text: 'Veuillez saisir et vérifier votre numéro de téléphone avant de continuer.',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }
        }
        
        // Sinon, exécuter le gestionnaire d'origine
        if (originalClickHandler) {
            originalClickHandler.call(this, e);
        } else {
            goToNextStep();
        }
    };
});





    </script>
</body>
</html>


