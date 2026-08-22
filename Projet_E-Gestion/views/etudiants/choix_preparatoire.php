<?php
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choix de classe préparatoire | <?= htmlspecialchars($configUniversite['nom_etablissement'] ?? 'E-GESTION') ?></title>
    
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
        
        .class-option {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .class-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .class-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .class-option .check-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            color: var(--primary-color);
            font-size: 1.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .class-option.selected .check-icon {
            opacity: 1;
        }
        
        .class-option h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .class-option p {
            color: #6c757d;
            margin-bottom: 0;
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

            <div id="classChoiceSection" style="display: none;">
                <div class="welcome-banner" data-aos="fade-up">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h1>Bienvenue, <span id="studentName">Étudiant</span>!</h1>
                            <p class="lead">Vous êtes inscrit en classe préparatoire. Veuillez choisir votre option de classe.</p>
                        </div>
                    </div>
                </div>

                <div class="card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Choisissez votre classe préparatoire</h5>
                        
                        <form id="classChoiceForm" method="POST" action="../controller/update_preparatoire_choice.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" id="studentId" name="studentId">
                            
                            <div class="mb-4">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="class-option" data-value="PREPARATOIRE A">
                                            <div class="check-icon"><i class="bi bi-check-circle-fill"></i></div>
                                            <h3>Préparatoire A</h3>
                                            <p>Bienvenue à l'INBTP</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="class-option" data-value="PREPARATOIRE B">
                                            <div class="check-icon"><i class="bi bi-check-circle-fill"></i></div>
                                            <h3>Préparatoire B</h3>
                                            <p>Bienvenue à l'INBTP</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="class-option" data-value="PREPARATOIRE C">
                                            <div class="check-icon"><i class="bi bi-check-circle-fill"></i></div>
                                            <h3>Préparatoire C</h3>
                                            <p>Bienvenue à l'INBTP</p>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="selectedClass" name="selectedClass" required>
                                <div class="invalid-feedback" id="classError">
                                    Veuillez sélectionner une classe préparatoire.
                                </div>
                            </div>
                            
                            <div class="profile-upload mt-5">
                            <h5 class="mb-4">Téléchargez votre photo de profil</h5>
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
                            
                            <div class="alert alert-warning mt-4">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                En cliquant sur "Confirmer mon choix", vous certifiez que les informations saisies sont exactes et que votre choix de classe est définitif.
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-2"></i> Confirmer mon choix
                                </button>
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
            const classChoiceForm = document.getElementById('classChoiceForm');
            const matriculeVerification = document.getElementById('matriculeVerification');
            const classChoiceSection = document.getElementById('classChoiceSection');
            
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
            
            // Gestion du choix de classe
            document.querySelectorAll('.class-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Retirer la sélection précédente
                    document.querySelectorAll('.class-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Ajouter la sélection actuelle
                    this.classList.add('selected');
                    
                    // Mettre à jour le champ caché
                    document.getElementById('selectedClass').value = this.dataset.value;
                    document.getElementById('classError').style.display = 'none';
                });
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
            
            // Soumission du formulaire de choix de classe
            classChoiceForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Vérification de la validation du formulaire
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                // Vérifier si une classe a été sélectionnée
                if (!document.getElementById('selectedClass').value) {
                    document.getElementById('classError').style.display = 'block';
                    return;
                }
                
                // Soumettre le formulaire via AJAX
                submitClassChoiceForm();
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
                            title: 'Félicitations!',
                            text: `Bienvenue ${data.student.noms}! Vous êtes bien inscrit en classe préparatoire.`,
                            confirmButtonText: 'Continuer',
                            confirmButtonColor: '#4361ee'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Pré-remplir le formulaire avec les données existantes
                                document.getElementById('studentId').value = data.student.idetudiant;
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
                                
                                // Afficher le nom de l'étudiant
                                document.getElementById('studentName').textContent = data.student.noms;
                                
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

                                // Si l'étudiant a déjà choisi une classe préparatoire, sélectionner cette classe
                                if (data.student.promotion_designation) {
                                    const selectedClass = data.student.promotion_designation;
                                    document.getElementById('selectedClass').value = selectedClass;

                                    console.log('classe'+selectedClass);
                                    
                                    // Trouver et sélectionner l'option de classe correspondante
                                    document.querySelectorAll('.class-option').forEach(option => {
                                        if (option.dataset.value === selectedClass) {
                                            option.classList.add('selected');
                                        }
                                    });
                                }

                                
                                // Masquer la section de vérification et afficher le formulaire avec une animation
                                matriculeVerification.style.display = 'none';
                                classChoiceSection.style.display = 'block';
                                
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
            
            // Fonction pour soumettre le formulaire de choix de classe
            function submitClassChoiceForm() {
                // Créer un objet FormData pour inclure les fichiers
                const formData = new FormData(classChoiceForm);
                
                // Ajouter le préfixe 243 au numéro de téléphone
                const phoneInput = document.getElementById('telephone').value;
                formData.set('telephone', '243' + phoneInput);
                
                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Enregistrement...',
                    text: 'Nous enregistrons votre choix et vos informations',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Soumettre le formulaire via AJAX
                fetch('../controller/update_preparatoire_choice.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher un message de succès
                        Swal.fire({
                            icon: 'success',
                            title: 'Choix enregistré!',
                            text: 'Votre choix de classe préparatoire et vos informations ont été enregistrés avec succès.',
                            confirmButtonText: 'Terminer',
                            confirmButtonColor: '#4CAF50'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Rediriger vers une page de confirmation ou de connexion
                                window.location.href = 'confirmation_preparatoire';
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
        });
    </script>
</body>
</html>

