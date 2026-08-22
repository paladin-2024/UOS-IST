<?php
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer l'année académique active
$connexion = Connexion::getInstance()->getPDO();
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC LIMIT 1");
$stmt->execute();
$anneeAcad = $stmt->fetch(PDO::FETCH_ASSOC);
$idAnneeAcad = $anneeAcad['idannee_acad'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la scolarité | <?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></title>
    
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
        
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
        }
        
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
        
        .document-item {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .document-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .document-item.uploaded {
            border-color: var(--success-color);
            background-color: rgba(76, 175, 80, 0.05);
        }
        
        .document-item .status-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            transition: opacity 0.3s ease;
        }
        
        .document-item .upload-icon {
            color: #adb5bd;
        }
        
        .document-item .success-icon {
            color: var(--success-color);
        }
        
        .document-item h3 {
            color: var(--text-color);
            font-size: 1.25rem;
            margin-bottom: 10px;
            padding-right: 30px;
        }
        
        .document-item p {
            color: #6c757d;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .document-item .upload-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .document-item .upload-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .document-item .upload-btn i {
            margin-right: 5px;
        }
        
        .document-item .file-name {
            font-size: 0.9rem;
            color: #495057;
            font-style: italic;
            margin-top: 5px;
            word-break: break-all;
        }
        
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
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 33.33%;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .progress-step.active .step-circle,
        .progress-step.completed .step-circle {
            background: var(--primary-color);
            color: white;
        }
        
        .step-title {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0;
            transition: all 0.3s;
        }
        
        .progress-step.active .step-title,
        .progress-step.completed .step-title {
            color: var(--primary-color);
            font-weight: 600;
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
            
            .welcome-banner {
                padding: 30px;
            }
            
            .welcome-banner h1 {
                font-size: 1.8rem;
            }
            
            .progress-steps {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .progress-steps::before {
                display: none;
            }
            
            .progress-step {
                width: 100%;
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            
            .step-circle {
                margin: 0 15px 0 0;
            }
        }
    </style>
</head>
<body>
    <div class="background-image"></div>
    
    <header id="header" class="header">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="gestion_scolarite" class="logo">
                <?php if (!empty($configUniversite['logo'])): ?>
                    <img src="../<?= htmlspecialchars($configUniversite['logo']) ?>" alt="Logo">
                <?php endif; ?>
                <span><?= htmlspecialchars($configUniversite['sigle'].' - NUMERIQUE' ?? 'E-GESTION') ?></span>
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
                        <h2 class="card-title">Gestion de votre scolarité</h2>
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

            <div id="studentInfoSection" style="display: none;">
                <div class="welcome-banner" data-aos="fade-up">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                        <h1>Bienvenue, <span id="studentName">Étudiant</span>!</h1>
                        <p class="lead">Vous êtes inscrit à l'année académique <span id="academicYear">---</span></p>

                        </div>
                    </div>
                </div>

                <div class="progress-steps" data-aos="fade-up" data-aos-delay="200">
                    <div class="progress-step active" id="step1">
                        <div class="step-circle">1</div>
                        <p class="step-title">Informations personnelles</p>
                    </div>
                    <div class="progress-step" id="step2">
                        <div class="step-circle">2</div>
                        <p class="step-title">Documents obligatoires</p>
                    </div>
                    <div class="progress-step" id="step3">
                        <div class="step-circle">3</div>
                        <p class="step-title">Confirmation</p>
                    </div>
                </div>

                <!-- Étape 1: Informations personnelles -->
                <div class="card" data-aos="fade-up" data-aos-delay="200" id="personalInfoSection">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Vos informations personnelles</h5>
                        
                        <form id="personalInfoForm" method="POST" action="../controller/update_student_info.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" id="studentId" name="studentId">
                            <input type="hidden" id="studentMatricule" name="matricule">
                            <input type="hidden" id="anneeAcadId" name="anneeAcadId" value="<?= $idAnneeAcad ?>">
                            
                            <div class="profile-upload mt-4">
                                <h5 class="mb-4">Votre photo de profil</h5>
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
                            
                            <div class="row g-4 mt-3">
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
                                <div class="col-md-6">
                                    <label for="adressemail" class="form-label">Adresse email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="adressemail" name="adressemail" required>
                                    </div>
                                    <div class="invalid-feedback">Veuillez saisir une adresse email valide.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                        <span class="input-group-text">+243</span>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" required placeholder="Ex: 976526633 (9 chiffres)">
                                    </div>
                                    <div class="invalid-feedback">Veuillez saisir votre numéro de téléphone.</div>
                                    <small class="form-text text-muted">Entrez uniquement les chiffres sans le préfixe +243</small>
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
                            
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-right me-2"></i> Passer aux documents
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Étape 2: Documents obligatoires -->
                <div class="card" data-aos="fade-up" data-aos-delay="200" id="documentsSection" style="display: none;">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Documents obligatoires pour votre dossier</h5>
                        <p class="text-muted mb-4">Veuillez télécharger les documents suivants pour compléter votre dossier étudiant. Tous les documents marqués d'un astérisque (*) sont obligatoires.</p>
                        
                        <div id="documentsContainer" class="mb-4">
                            <!-- Les documents seront chargés ici dynamiquement -->
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="mt-2">Chargement des documents requis...</p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Formats acceptés:</strong> PDF, JPG, PNG. <strong>Taille maximale:</strong> 5 Mo par document.
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary btn-lg" id="backToInfoBtn">
                                <i class="bi bi-arrow-left me-2"></i> Retour aux informations
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" id="goToConfirmationBtn">
                                <i class="bi bi-arrow-right me-2"></i> Passer à la confirmation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Étape 3: Confirmation -->
                <div class="card" data-aos="fade-up" data-aos-delay="200" id="confirmationSection" style="display: none;">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Confirmation de votre dossier</h5>
                        
                        <div class="alert alert-success mb-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="bi bi-check-circle-fill fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="alert-heading mb-1">Félicitations!</h6>
                                    <p class="mb-0">Vous avez complété toutes les étapes nécessaires pour la mise à jour de votre dossier étudiant.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-person me-2"></i> Informations personnelles</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush" id="infoSummary">
                                            <!-- Les informations seront chargées dynamiquement -->
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-file-earmark me-2"></i> Documents fournis</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush" id="documentsSummary">
                                            <!-- Les documents seront chargés dynamiquement -->
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            En cliquant sur "Confirmer mon dossier", vous certifiez que les informations saisies sont exactes et que les documents fournis sont authentiques.
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary btn-lg" id="backToDocumentsBtn">
                                <i class="bi bi-arrow-left me-2"></i> Retour aux documents
                            </button>
                            <button type="button" class="btn btn-success btn-lg" id="confirmDossierBtn">
                                <i class="bi bi-check-lg me-2"></i> Confirmer mon dossier
                            </button>
                        </div>
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
            
            // Éléments du DOM
            const matriculeForm = document.getElementById('matriculeForm');
            const personalInfoForm = document.getElementById('personalInfoForm');
            const matriculeVerification = document.getElementById('matriculeVerification');
            const studentInfoSection = document.getElementById('studentInfoSection');
            const personalInfoSection = document.getElementById('personalInfoSection');
            const documentsSection = document.getElementById('documentsSection');
            const confirmationSection = document.getElementById('confirmationSection');
            
            // Buttons de navigation
            const backToInfoBtn = document.getElementById('backToInfoBtn');
            const goToConfirmationBtn = document.getElementById('goToConfirmationBtn');
            const backToDocumentsBtn = document.getElementById('backToDocumentsBtn');
            const confirmDossierBtn = document.getElementById('confirmDossierBtn');
            
            // Steps indicators
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            
            // Variables globales
            let studentData = null;
            let uploadedDocuments = {};
            let requiredDocuments = [];
            
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
            
            // Soumission du formulaire des infos personnelles
            personalInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                // Sauvegarder les infos et passer à l'étape suivante
                updatePersonalInfo();
            });
            
            // Navigation entre les étapes
            backToInfoBtn.addEventListener('click', function() {
                showPersonalInfoSection();
            });
            
            goToConfirmationBtn.addEventListener('click', function() {
                // Vérifier si tous les documents obligatoires sont fournis
                const missingDocuments = requiredDocuments.filter(doc => 
                    doc.est_obligatoire && !uploadedDocuments[doc.id]
                );
                
                if (missingDocuments.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Documents manquants',
                        html: `Veuillez fournir les documents obligatoires suivants:<br><ul>
                            ${missingDocuments.map(doc => `<li>${doc.designation}</li>`).join('')}
                            </ul>`,
                        confirmButtonText: 'Compris',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }
                
                showConfirmationSection();
            });
            
            backToDocumentsBtn.addEventListener('click', function() {
                showDocumentsSection();
            });
            
            confirmDossierBtn.addEventListener('click', function() {
                finalizeStudentDossier();
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
            });
            
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
                fetch('../controller/verify_student_matricule.php', {
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
                            title: 'Matricule reconnu!',
                            text: `Bienvenue ${data.student.noms}! Vous pouvez maintenant gérer votre dossier étudiant.`,
                            confirmButtonText: 'Continuer',
                            confirmButtonColor: '#4361ee'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Stocker les données de l'étudiant
                                studentData = data.student;
                                
                                // Pré-remplir le formulaire avec les données existantes
                                document.getElementById('studentId').value = data.student.idetudiant;
                                document.getElementById('studentMatricule').value = data.student.matricule;
                                document.getElementById('noms').value = data.student.noms || '';
                                document.getElementById('lieuNaissance').value = data.student.lieuNaissance || '';
                                document.getElementById('dateNaissance').value = data.student.dateNaissance || '';
                                document.getElementById('sexe').value = data.student.sexe || '';
                                document.getElementById('nationalite').value = data.student.nationalite || '';
                                document.getElementById('adressemail').value = data.student.adressemail || '';
                                
                                let telephone = data.student.telephone || '';
                                if (telephone.startsWith('243')) {
                                    telephone = telephone.substring(3); // Enlever les 3 premiers caractères (243)
                                }
                                document.getElementById('telephone').value = telephone;
                                document.getElementById('adresse').value = data.student.adresse || '';
                                document.getElementById('personne_contact').value = data.student.personne_contact || '';
                                document.getElementById('telephone_contact').value = data.student.telephone_contact || '';
                                
                                // Afficher le nom de l'étudiant dans la bannière
                                document.getElementById('studentName').textContent = data.student.noms;
                                // Afficher l'année académique de l'étudiant
                                document.getElementById('academicYear').textContent = data.student.annee_academique || '---';

                                
                                // Si une photo existe déjà
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
                                studentInfoSection.style.display = 'block';
                                
                                // Charger les documents obligatoires pour le cycle de l'étudiant
                                loadRequiredDocuments(data.student.cycle);
                                
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
            
            // Fonction pour mettre à jour les infos personnelles
function updatePersonalInfo() {
    // Créer un objet FormData pour inclure les fichiers
    const formData = new FormData(personalInfoForm);
    
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
    fetch('../controller/update_student_info.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Ajouter cette vérification pour voir si la réponse est bien reçue
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Fermer explicitement le SweetAlert de chargement
        Swal.close();
        
        if (data.success) {
            // Mettre à jour les données de l'étudiant
            Object.assign(studentData, data.updatedData);
            
            // Passer à la section des documents
            showDocumentsSection();
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
        // Fermer explicitement le SweetAlert de chargement en cas d'erreur
        Swal.close();
        
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

            
            // Fonction pour charger les documents obligatoires
            function loadRequiredDocuments(cycle) {
                // Requête AJAX pour récupérer les documents obligatoires selon le cycle
                fetch(`../controller/get_required_documents.php?cycle=${cycle}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.documents)) {
                        requiredDocuments = data.documents;
                        renderDocumentsList(data.documents);
                        
                        // Vérifier s'il y a des documents déjà téléchargés
                        checkExistingDocuments();
                    } else {
                        document.getElementById('documentsContainer').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucun document requis trouvé ou erreur lors du chargement.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des documents:', error);
                    document.getElementById('documentsContainer').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Une erreur est survenue lors du chargement des documents requis.
                        </div>
                    `;
                });
            }
            
            // Fonction pour afficher la liste des documents
            function renderDocumentsList(documents) {
                if (!documents.length) {
                    document.getElementById('documentsContainer').innerHTML = `
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun document n'est requis pour votre cycle d'études.
                        </div>
                    `;
                    return;
                }
                
                const documentItems = documents.map(doc => `
                    <div class="document-item" id="doc-item-${doc.id}">
                        <div class="status-icon upload-icon">
                            <i class="bi bi-cloud-upload"></i>
                        </div>
                        <h3>${doc.designation} ${doc.est_obligatoire ? '<span class="text-danger">*</span>' : ''}</h3>
                        <p>${doc.description || 'Aucune description disponible'}</p>
                        <div class="document-upload-area">
                            <input type="file" id="doc-${doc.id}" class="document-input" data-docid="${doc.id}" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                            <button type="button" class="upload-btn" onclick="document.getElementById('doc-${doc.id}').click();">
                                <i class="bi bi-upload"></i> Télécharger le document
                            </button>
                            <div class="file-name mt-2" id="doc-name-${doc.id}"></div>
                        </div>
                    </div>
                `).join('');
                
                document.getElementById('documentsContainer').innerHTML = documentItems;
                
                // Ajouter les event listeners pour les inputs de fichiers
                documents.forEach(doc => {
                    const fileInput = document.getElementById(`doc-${doc.id}`);
                    fileInput.addEventListener('change', function() {
                        uploadDocument(doc.id, this.files[0]);
                    });
                });
            }
            
            // Fonction pour vérifier les documents existants
            function checkExistingDocuments() {
                const studentId = document.getElementById('studentId').value;
                
                fetch(`../controller/get_student_documents.php?studentId=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.documents)) {
                        // Mettre à jour l'interface pour les documents déjà téléchargés
                        data.documents.forEach(doc => {
                            markDocumentAsUploaded(doc.document_obligatoire_id, doc.chemin_fichier, doc.titre);
                            // Stocker les documents déjà téléchargés
                            uploadedDocuments[doc.document_obligatoire_id] = doc;
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la vérification des documents existants:', error);
                });
            }
            
            // Fonction pour télécharger un document
            function uploadDocument(docId, file) {
                if (!file) return;
                
                // Validation du fichier
                const maxSize = 5 * 1024 * 1024; // 5 MB
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                
                if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fichier trop volumineux',
                        text: 'La taille maximale autorisée est de 5 Mo.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }
                
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format non supporté',
                        text: 'Formats acceptés: PDF, JPG, PNG.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }
                
                // Créer un objet FormData pour l'upload
                const formData = new FormData();
                formData.append('document', file);
                formData.append('docId', docId);
                formData.append('studentId', document.getElementById('studentId').value);
                formData.append('matricule', document.getElementById('studentMatricule').value);
                formData.append('anneeAcadId', document.getElementById('anneeAcadId').value);
                
                // Afficher un indicateur de chargement
                const docItem = document.getElementById(`doc-item-${docId}`);
                docItem.classList.add('uploading');
                document.getElementById(`doc-name-${docId}`).textContent = 'Téléchargement en cours...';
                
                // Envoyer le fichier
                fetch('../controller/upload_student_document.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Marquer le document comme téléchargé
                        markDocumentAsUploaded(docId, data.filePath, file.name);
                        
                        // Stocker le document téléchargé
                        uploadedDocuments[docId] = {
                            document_obligatoire_id: docId,
                            chemin_fichier: data.filePath,
                            titre: file.name
                        };
                        
                        // Notification
                        Swal.fire({
                            icon: 'success',
                            title: 'Document téléchargé',
                            text: 'Votre document a été téléchargé avec succès.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4CAF50',
                            timer: 2000,
                            timerProgressBar: true
                        });
                    } else {
                        docItem.classList.remove('uploading');
                        document.getElementById(`doc-name-${docId}`).textContent = '';
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de téléchargement',
                            text: data.message || 'Une erreur est survenue lors du téléchargement.',
                            confirmButtonText: 'Réessayer',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du téléchargement:', error);
                    docItem.classList.remove('uploading');
                    document.getElementById(`doc-name-${docId}`).textContent = '';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors du téléchargement. Veuillez réessayer plus tard.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                });
            }
            
            // Fonction pour marquer un document comme téléchargé
            function markDocumentAsUploaded(docId, filePath, fileName) {
                const docItem = document.getElementById(`doc-item-${docId}`);
                if (!docItem) return;
                
                docItem.classList.remove('uploading');
                docItem.classList.add('uploaded');
                
                                // Mettre à jour l'icône
                                const statusIcon = docItem.querySelector('.status-icon');
                statusIcon.classList.remove('upload-icon');
                statusIcon.classList.add('success-icon');
                statusIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                
                // Afficher le nom du fichier
                document.getElementById(`doc-name-${docId}`).textContent = fileName;
                
                // Mettre à jour le bouton
                const uploadBtn = docItem.querySelector('.upload-btn');
                uploadBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Remplacer le document';
            }
            
            // Fonction pour afficher la section des infos personnelles
            function showPersonalInfoSection() {
                // Changer les indicateurs d'étape
                step1.classList.add('active');
                step1.classList.add('completed');
                step2.classList.remove('active');
                step2.classList.remove('completed');
                step3.classList.remove('active');
                step3.classList.remove('completed');
                
                // Afficher la bonne section
                personalInfoSection.style.display = 'block';
                documentsSection.style.display = 'none';
                confirmationSection.style.display = 'none';
                
                // Réinitialiser AOS
                setTimeout(() => { AOS.refresh(); }, 100);
            }
            
            // Fonction pour afficher la section des documents
            function showDocumentsSection() {
                // Changer les indicateurs d'étape
                step1.classList.add('completed');
                step1.classList.remove('active');
                step2.classList.add('active');
                step2.classList.add('completed');
                step3.classList.remove('active');
                step3.classList.remove('completed');
                
                // Afficher la bonne section
                personalInfoSection.style.display = 'none';
                documentsSection.style.display = 'block';
                confirmationSection.style.display = 'none';
                
                // Réinitialiser AOS
                setTimeout(() => { AOS.refresh(); }, 100);
            }
            
            // Fonction pour afficher la section de confirmation
            function showConfirmationSection() {
                // Changer les indicateurs d'étape
                step1.classList.add('completed');
                step1.classList.remove('active');
                step2.classList.add('completed');
                step2.classList.remove('active');
                step3.classList.add('active');
                step3.classList.add('completed');
                
                // Afficher la bonne section
                personalInfoSection.style.display = 'none';
                documentsSection.style.display = 'none';
                confirmationSection.style.display = 'block';
                
                // Afficher le résumé des informations personnelles
                const infoSummary = document.getElementById('infoSummary');
                infoSummary.innerHTML = `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Nom complet</span>
                        <span class="text-muted">${document.getElementById('noms').value}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Matricule</span>
                        <span class="text-muted">${document.getElementById('studentMatricule').value}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Date de naissance</span>
                        <span class="text-muted">${document.getElementById('dateNaissance').value || 'Non renseigné'}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Email</span>
                        <span class="text-muted">${document.getElementById('adressemail').value}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Téléphone</span>
                        <span class="text-muted">+243 ${document.getElementById('telephone').value}</span>
                    </li>
                `;
                
                // Afficher le résumé des documents
                const documentsSummary = document.getElementById('documentsSummary');
                let documentsHtml = '';
                
                // Parcourir la liste des documents requis
                requiredDocuments.forEach(doc => {
                    const isUploaded = uploadedDocuments[doc.id] !== undefined;
                    const statusClass = isUploaded ? 'text-success' : (doc.est_obligatoire ? 'text-danger' : 'text-warning');
                    const statusText = isUploaded ? 'Fourni' : (doc.est_obligatoire ? 'Manquant (obligatoire)' : 'Non fourni (facultatif)');
                    const statusIcon = isUploaded ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
                    
                    documentsHtml += `
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>${doc.designation}</span>
                                <span class="${statusClass}">
                                    <i class="bi ${statusIcon} me-1"></i> ${statusText}
                                </span>
                            </div>
                            ${isUploaded ? `<small class="text-muted d-block mt-1">Fichier: ${uploadedDocuments[doc.id].titre}</small>` : ''}
                        </li>
                    `;
                });
                
                documentsSummary.innerHTML = documentsHtml || '<li class="list-group-item">Aucun document requis trouvé.</li>';
                
                // Réinitialiser AOS
                setTimeout(() => { AOS.refresh(); }, 100);
            }
            
            // Fonction pour finaliser le dossier étudiant
            function finalizeStudentDossier() {
                // Vérifier si tous les documents obligatoires sont fournis
                const missingDocuments = requiredDocuments.filter(doc => 
                    doc.est_obligatoire && !uploadedDocuments[doc.id]
                );
                
                if (missingDocuments.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Documents manquants',
                        html: `Veuillez fournir les documents obligatoires suivants:<br><ul>
                            ${missingDocuments.map(doc => `<li>${doc.designation}</li>`).join('')}
                            </ul>`,
                        confirmButtonText: 'Compris',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }
                
                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Finalisation...',
                    text: 'Nous finalisons votre dossier',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Préparation des données à envoyer
                const data = {
                    studentId: document.getElementById('studentId').value,
                    matricule: document.getElementById('studentMatricule').value,
                    anneeAcadId: document.getElementById('anneeAcadId').value
                };
                
                // Requête AJAX pour finaliser le dossier
                fetch('../controller/finalize_student_dossier.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {

                        let message = 'Votre dossier a été finalisé avec succès.';
        
                        // Ajouter un message concernant l'email si disponible
                        if (data.hasOwnProperty('emailSent')) {
                            if (data.emailSent) {
                                message += ' Un email de confirmation a été envoyé à votre adresse email.';
                            } else {
                                message += ' Nous n\'avons pas pu vous envoyer un email de confirmation. Veuillez vérifier votre adresse email.';
                            }
                        }
                        // Afficher un message de succès
                        Swal.fire({
                            icon: 'success',
                            title: 'Dossier complété!',
                            text: 'Votre dossier étudiant a été mis à jour avec succès.',
                            confirmButtonText: 'Terminer',
                            confirmButtonColor: '#4CAF50'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Rediriger vers la page d'accueil ou une page de confirmation
                                window.location.href = 'gestion_scolarite';
                            }
                        });
                    } else {
                        // Afficher un message d'erreur
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de la finalisation de votre dossier.',
                            confirmButtonText: 'Réessayer',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la finalisation:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la finalisation. Veuillez réessayer plus tard.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                });
            }
        });
    </script>
</body>
</html>

