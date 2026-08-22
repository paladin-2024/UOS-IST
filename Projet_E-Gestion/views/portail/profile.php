<?php
// Vérification de la connexion
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login');
    exit();
}

// Inclusion des fichiers nécessaires
require_once dirname(__DIR__) . '/../config/Connexion.php';
require_once dirname(__DIR__) . '/../models/Etudiant.php';
require_once dirname(__DIR__) . '/../models/Universite.php';
require_once dirname(__DIR__) . '/../models/Agent.php';

// Initialisation des modèles
$etudiantModel = new Etudiant();
$universite = new Universite();
$agentModel = new Agent();

// Récupération des informations de l'étudiant
$studentId = $_SESSION['student_id'];
$studentInfo = $etudiantModel->getEtudiantById($studentId);

// Debug: vérifier la valeur de la photo
error_log("Photo in DB: " . ($studentInfo['photo'] ?? 'NULL'));

if (!$studentInfo) {
    header('Location: ../login');
    exit();
}

// Synchroniser la photo en session avec celle de la BD
if (isset($studentInfo['photo'])) {
    $_SESSION['photo'] = $studentInfo['photo'];
}



// Afficher les messages de succès ou d'erreur
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Nettoyer les messages de session
unset($_SESSION['success']);
unset($_SESSION['error']);
$configUniversitee = $universite->getConfigurationUniversite();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - ScienceHub Mobile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php if (!empty($configUniversitee['logo'])): ?>
        <!-- Favicons --> 
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="icon">
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary-color: #f8fafc;
            --accent-color: #f59e0b;
            --text-color: #334155;
            --text-light: #64748b;
            --light-text: #fff;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #0ea5e9;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --header-height: 56px;
            --bottom-nav-height: 60px;
            --border-radius: 12px;
        }

        body {
            background-color: var(--secondary-color);
            font-family: 'Poppins', sans-serif;
            padding-top: var(--header-height);
            padding-bottom: calc(var(--bottom-nav-height) + 16px);
            color: var(--text-color);
            line-height: 1.6;
        }

        /* ===== HEADER ===== */
        .mobile-header {
            background-color: #fff;
            padding: 0 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: var(--header-height);
            display: flex;
            align-items: center;
        }

        .mobile-header .back-btn {
            color: var(--primary-color);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .mobile-header .back-btn:hover {
            background: var(--primary-light);
        }

        .mobile-header h1 {
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            font-size: 1.05rem;
        }

        /* ===== PROFILE SECTION ===== */
        .profile-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 12px;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .info-group {
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--border-color);
        }

        .info-group:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .info-value {
            font-size: 1rem;
            color: var(--text-color);
            word-break: break-word;
        }

        /* ===== BOTTOM NAV ===== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -1px 3px rgba(0,0,0,0.1);
            z-index: 1000;
            height: var(--bottom-nav-height);
            display: flex;
            align-items: center;
            border-top: 1px solid var(--border-color);
        }

        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
            padding: 4px 2px;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            transition: color 0.2s;
        }

        .bottom-nav .nav-item i {
            font-size: 1.15rem;
        }

        .bottom-nav .nav-item small {
            font-size: 0.6rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        .bottom-nav .nav-item.active {
            color: var(--primary-color);
            font-weight: 600;
        }

        .bottom-nav .nav-item:hover {
            color: var(--primary-color);
        }

        /* ===== BUTTONS ===== */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-danger {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
        }

        /* ===== FORMS ===== */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            transition: border-color 0.2s, box-shadow 0.2s;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-light);
            margin-bottom: 4px;
        }

        /* ===== PROFILE UPLOAD ===== */
        .profile-upload .preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 12px;
            border: 3px solid var(--primary-color);
            overflow: hidden;
            position: relative;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .profile-upload .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #fileUpload {
            display: none;
        }

        /* Required field indicator */
        .required::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        /* Section headings */
        .section-heading {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
        }

        .section-heading i {
            margin-right: 8px;
            font-size: 0.95rem;
        }

        /* OTP verification section */
        .otp-verification-section {
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
        }

        /* Academic info read-only items */
        .academic-item {
            background: var(--secondary-color);
            border-radius: 8px;
            padding: 10px 14px;
        }

        .academic-item small {
            color: var(--text-light);
            font-size: 0.75rem;
        }

        .academic-item strong {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* ===== MODAL ===== */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 767.98px) {
            body {
                padding-top: var(--header-height);
                padding-bottom: calc(var(--bottom-nav-height) + 8px);
            }

            .container {
                padding-left: 12px;
                padding-right: 12px;
            }

            .profile-section {
                padding: 16px;
                border-radius: 10px;
                margin-bottom: 12px;
            }

            .profile-avatar {
                width: 90px;
                height: 90px;
            }

            .profile-upload .preview {
                width: 90px;
                height: 90px;
            }

            .profile-header h2 {
                font-size: 1.1rem;
            }

            .form-control, .form-select {
                font-size: 0.9rem;
                padding: 9px 12px;
            }

            .form-label {
                font-size: 0.8rem;
            }

            .btn-lg {
                font-size: 0.95rem;
                padding: 10px 16px;
            }

            .section-heading {
                font-size: 0.85rem;
            }

            /* Modal full-screen on mobile */
            .modal-dialog {
                margin: 0;
                max-width: 100%;
                min-height: 100vh;
            }

            .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }

            /* Row gaps tighter on mobile */
            .row.g-3 {
                --bs-gutter-y: 0.75rem;
                --bs-gutter-x: 0.75rem;
            }
        }

        /* ===== DESKTOP ===== */
        @media (min-width: 768px) {
            :root {
                --header-height: 64px;
            }

            .container {
                max-width: 640px;
            }

            .profile-section {
                padding: 32px;
            }

            .bottom-nav {
                display: none;
            }

            .mobile-header h1 {
                font-size: 1.15rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="d-flex align-items-center w-100">
            <a href="student" class="back-btn me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="mb-0">Mon Profil</h1>
        </div>
    </header>

    <!-- Content Area -->
    <div class="container py-3">
        <!-- Profile Card -->
        <div class="profile-section">
            <form id="profileForm" enctype="multipart/form-data">
                <input type="hidden" name="studentId" value="<?= $studentId ?>">
                <input type="hidden" name="existingPhoto" value="<?= htmlspecialchars($studentInfo['photo'] ?? '') ?>">
                
                <!-- Avatar + Name -->
                <div class="text-center mb-4">
                    <div class="profile-upload">
                        <div class="preview position-relative mx-auto">
                            <img id="profilePreview"
                                src="<?= isset($studentInfo['photo']) && !empty($studentInfo['photo'])
                                    ? '../uploads/'.$studentInfo['photo'].'?t='.time()
                                    : '../uploads/user.png?t='.time() ?>"
                                alt="Photo de profil">
                            <div id="photoLoadingSpinner" class="position-absolute top-50 start-50 translate-middle" style="display: none;">
                                <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="fileUpload" name="photo" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="document.getElementById('fileUpload').click()">
                            <i class="fas fa-camera me-1"></i>Changer la photo
                        </button>
                    </div>
                    <h5 class="fw-bold mt-3 mb-0"><?= htmlspecialchars($studentInfo['noms'] ?? '') ?></h5>
                    <span class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($studentInfo['matricule']) ?></span>
                </div>

                <!-- Academic Info Banner -->
                <div class="row g-2 mb-4">
                    <div class="col-4">
                        <div class="academic-item text-center">
                            <small class="d-block">Promotion</small>
                            <strong class="d-block" style="font-size: 0.8rem;"><?= htmlspecialchars($studentInfo['promotion']) ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="academic-item text-center">
                            <small class="d-block">Orientation</small>
                            <strong class="d-block" style="font-size: 0.8rem;"><?= htmlspecialchars($studentInfo['departement'] ?? '—') ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="academic-item text-center">
                            <small class="d-block">Année</small>
                            <strong class="d-block" style="font-size: 0.8rem;"><?= htmlspecialchars($studentInfo['annee_academique']) ?></strong>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Personal Info Section -->
        <div class="profile-section">
                <h6 class="section-heading"><i class="fas fa-id-card"></i>Informations personnelles</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="noms" class="form-label required">Noms complets</label>
                        <input type="text" class="form-control" id="noms" name="noms"
                            value="<?= htmlspecialchars($studentInfo['noms'] ?? '') ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="lieuNaissance" class="form-label">Lieu de naissance</label>
                        <input type="text" class="form-control" id="lieuNaissance" name="lieuNaissance"
                            value="<?= htmlspecialchars($studentInfo['lieuNaissance'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label for="dateNaissance" class="form-label">Date de naissance</label>
                        <input type="date" class="form-control" id="dateNaissance" name="dateNaissance"
                            value="<?= htmlspecialchars($studentInfo['dateNaissance'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label for="sexe" class="form-label required">Sexe</label>
                        <select class="form-select" id="sexe" name="sexe" required>
                            <option value="">Sélectionnez</option>
                            <option value="Masculin" <?= ($studentInfo['sexe'] ?? '') === 'Masculin' ? 'selected' : '' ?>>Masculin</option>
                            <option value="Féminin" <?= ($studentInfo['sexe'] ?? '') === 'Féminin' ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="nationalite" class="form-label required">Nationalité</label>
                        <input type="text" class="form-control" id="nationalite" name="nationalite"
                            value="<?= htmlspecialchars($studentInfo['nationalite'] ?? '') ?>" required>
                    </div>
                </div>
        </div>

        <!-- Contact Info Section -->
        <div class="profile-section">
                <h6 class="section-heading"><i class="fas fa-address-book"></i>Informations de contact</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="adressemail" class="form-label required">Email</label>
                        <input type="email" class="form-control" id="adressemail" name="adressemail"
                            value="<?= htmlspecialchars($studentInfo['adressemail'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="telephone" class="form-label required">Téléphone</label>
                        <div class="input-group">
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                value="<?= htmlspecialchars(substr($studentInfo['telephone'] ?? '', 3)) ?>" required>
                            <button class="btn btn-outline-secondary" type="button" id="sendOtpBtn">
                                <i class="fas fa-shield-check me-1"></i>Vérifier
                            </button>
                        </div>
                        <div id="phoneVerificationStatus" class="mt-1">
                            <?php if (!empty($studentInfo['telephone'])): ?>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i>Numéro vérifié</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"
                            placeholder="Votre adresse complète"><?= htmlspecialchars($studentInfo['adresse'] ?? '') ?></textarea>
                    </div>
                </div>
        </div>

        <!-- Emergency Contact Section -->
        <div class="profile-section">
                <h6 class="section-heading"><i class="fas fa-user-friends"></i>Contact d'urgence</h6>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="personne_contact" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="personne_contact" name="personne_contact"
                            value="<?= htmlspecialchars($studentInfo['personne_contact'] ?? '') ?>">
                    </div>
                    <div class="col-6">
                        <label for="telephone_contact" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact"
                            value="<?= htmlspecialchars($studentInfo['telephone_contact'] ?? '') ?>">
                    </div>
                </div>
        </div>

        <!-- Save Button -->
        <div class="profile-section">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Enregistrer les modifications
                    </button>
                </div>
            </form>

            <!-- OTP Verification Section (hidden by default) -->
            <div class="card mt-3 border-warning otp-verification-section" style="display: none;">
                <div class="card-body">
                    <h6 class="card-title fw-bold text-warning mb-3">
                        <i class="fas fa-shield-alt me-2"></i>Vérification du téléphone
                    </h6>
                    <div class="alert alert-info py-2 mb-3">
                        <small><i class="fas fa-info-circle me-1"></i>
                        Un code a été envoyé à votre numéro.</small>
                        <br><small class="text-muted"><i class="fas fa-lightbulb me-1"></i>
                        <em>Test : code <strong>436432</strong></em></small>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <input type="text" class="form-control" id="otp" maxlength="6" placeholder="000000">
                        </div>
                        <div class="col-5 d-grid">
                            <button type="button" class="btn btn-success btn-sm" id="verifyOtpBtn">
                                <i class="fas fa-check me-1"></i>Vérifier
                            </button>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resendOtpBtn" disabled>
                                Renvoyer <span id="otpTimer"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Section -->
        <div class="profile-section">
            <div class="d-grid gap-2">
                <button class="btn btn-outline-primary" onclick="showChangePasswordModal()">
                    <i class="fas fa-key me-2"></i>Changer le mot de passe
                </button>
                <a href="../controller/logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Changement de mot de passe -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Changer le mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changePasswordForm" action="../controller/change_password.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="currentPassword" 
                                   name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="newPassword" 
                                   name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="confirmPassword" 
                                   name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Changer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    

    <!-- Bottom Navigation (same as includes/bottom_nav.php) -->
    <nav class="bottom-nav">
        <div class="d-flex justify-content-around w-100">
            <a href="student" class="nav-item">
                <i class="fas fa-home d-block"></i>
                <small>Accueil</small>
            </a>
            <a href="student?tab=evaluations" class="nav-item">
                <i class="fas fa-chart-bar d-block"></i>
                <small>Notes</small>
            </a>
            <a href="student?tab=courses" class="nav-item">
                <i class="fas fa-book d-block"></i>
                <small>Cours</small>
            </a>
            <a href="stage" class="nav-item">
                <i class="fas fa-building d-block"></i>
                <small>Stage</small>
            </a>
            <a href="frais_academiques" class="nav-item">
                <i class="fas fa-money-check-alt d-block"></i>
                <small>Frais</small>
            </a>
            <a href="profile" class="nav-item active">
                <i class="fas fa-user d-block"></i>
                <small>Profil</small>
            </a>
        </div>
    </nav>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialisation des composants Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM chargé - initialisation des composants profile.php');
            
            // Afficher les messages de succès ou d'erreur avec SweetAlert2
            <?php if (!empty($successMessage)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '<?= addslashes($successMessage) ?>',
                timer: 3000,
                timerProgressBar: true
            });
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '<?= addslashes($errorMessage) ?>',
                timer: 3000,
                timerProgressBar: true
            });
            <?php endif; ?>
            
            // Form validation for password change
            const changePasswordForm = document.getElementById('changePasswordForm');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Les mots de passe ne correspondent pas'
                        });
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Le mot de passe doit contenir au moins 6 caractères'
                        });
                        return false;
                    }
                });
            } else {
                console.error('Formulaire de changement de mot de passe non trouvé');
            }
            
            console.log('Initialisation des composants profile.php terminée');
        });

        // Fonction pour afficher le modal de changement de mot de passe
        function showChangePasswordModal() {
            const modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
            if (modal) {
                modal.show();
            } else {
                console.error('Modal de changement de mot de passe non trouvé');
            }
        }



        // Phone verification variables
        let otpSent = false;
        let phoneVerified = <?= !empty($studentInfo['telephone']) ? 'true' : 'false' ?>;
        let originalPhoneValue = '<?= htmlspecialchars(substr($studentInfo['telephone'] ?? '', 3)) ?>';
        let otpValue = '';
        let countdown = 0;
        let countdownTimer;

        // Initialize phone verification state
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('telephone');
            const verifyBtn = document.getElementById('sendOtpBtn');

            // Allow editing even if phone is already verified
            // Just update the button text to show verified status
            if (phoneVerified) {
            verifyBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Vérifié';
            }

            // Enable verify button when phone changes
            phoneInput.addEventListener('input', function() {
            if (this.value !== originalPhoneValue) {
            if (!phoneVerified) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-shield-check me-1"></i>Vérifier';
            }
            document.getElementById('phoneVerificationStatus').innerHTML = '';
            phoneVerified = false;
                otpSent = false;
                    document.querySelector('.otp-verification-section').style.display = 'none';
                } else if (this.value === originalPhoneValue && phoneVerified) {
                    // Reset to verified state if user re-enters original verified number
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Vérifié';
                    document.getElementById('phoneVerificationStatus').innerHTML =
                        '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Numéro déjà vérifié</small>';
                    phoneVerified = true;
                }
            });
        });

        // Send OTP button click handler
        document.getElementById('sendOtpBtn').addEventListener('click', function() {
            const phoneInput = document.getElementById('telephone').value;

            // Validate phone format (9 digits, no leading 0)
            const phoneRegex = /^[1-9][0-9]{8}$/;
            if (!phoneInput || !phoneRegex.test(phoneInput)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Numéro invalide',
                    text: 'Veuillez saisir un numéro de téléphone valide (9 chiffres et ne commençant pas par 0).',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            // Add 243 prefix for sending
            const phone = "243" + phoneInput;

            // Show loading
            Swal.fire({
                title: 'Envoi en cours...',
                text: 'Nous envoyons un code de vérification à votre numéro',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Generate OTP
            otpValue = Math.floor(100000 + Math.random() * 900000).toString();

            // Prepare message
            const msg = `Votre code de verification est : ${otpValue}. Ne partagez ce code avec personne.`;

            // Send SMS
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
                    // Show OTP verification section
                    document.querySelector('.otp-verification-section').style.display = 'block';
                    otpSent = true;

                    // Start countdown for resend
                    startOtpCountdown();

                    Swal.fire({
                        icon: 'success',
                        title: 'Code envoyé',
                        text: 'Un code de vérification a été envoyé à votre numéro de téléphone.',
                        confirmButtonColor: '#4CAF50'
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

        // Verify OTP button click handler
        document.getElementById('verifyOtpBtn').addEventListener('click', function() {
            const enteredOtp = document.getElementById('otp').value;

            if (!enteredOtp) {
                document.getElementById('otp').classList.add('is-invalid');
                return;
            }

            if (enteredOtp === otpValue || enteredOtp === '436432') {
            // OTP valid
            phoneVerified = true;

            document.getElementById('phoneVerificationStatus').innerHTML =
            '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Numéro vérifié</small>';

            document.getElementById('telephone').setAttribute('readonly', 'readonly');
            document.getElementById('sendOtpBtn').disabled = true;
            document.getElementById('sendOtpBtn').innerHTML = '<i class="fas fa-check-circle me-1"></i>Vérifié';
            document.querySelector('.otp-verification-section').style.display = 'none';

            // Store original verified value
            originalPhoneValue = document.getElementById('telephone').value;

            Swal.fire({
            icon: 'success',
            title: 'Vérifié!',
            text: 'Votre numéro de téléphone a été vérifié avec succès.',
            confirmButtonColor: '#4CAF50'
            });
            } else {
                // OTP invalid
                Swal.fire({
                    icon: 'error',
                    title: 'Code incorrect',
                    text: 'Le code de vérification saisi est incorrect. Veuillez réessayer.',
                    confirmButtonColor: '#4361ee'
                });
            }
        });

        // Resend OTP button click handler
        document.getElementById('resendOtpBtn').addEventListener('click', function() {
            if (countdown > 0) return;

            // Reset and resend
            document.getElementById('sendOtpBtn').click();
        });

        // Function to start OTP countdown
        function startOtpCountdown() {
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

        // Profile form submission
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Basic validation
                const requiredFields = ['noms', 'sexe', 'nationalite', 'adressemail', 'telephone'];
                let isValid = true;

                requiredFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        element.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        element.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Champs requis',
                        text: 'Veuillez remplir tous les champs obligatoires.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const emailField = document.getElementById('adressemail');
                if (!emailRegex.test(emailField.value)) {
                    emailField.classList.add('is-invalid');
                    Swal.fire({
                        icon: 'error',
                        title: 'Email invalide',
                        text: 'Veuillez saisir une adresse email valide.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Phone validation (9 digits, no leading 0)
                const phoneField = document.getElementById('telephone');
                const phoneRegex = /^[1-9][0-9]{8}$/;
                if (!phoneRegex.test(phoneField.value)) {
                    phoneField.classList.add('is-invalid');
                    Swal.fire({
                        icon: 'error',
                        title: 'Numéro de téléphone invalide',
                        text: 'Le numéro doit contenir 9 chiffres et ne pas commencer par 0.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Show loading
                Swal.fire({
                    title: 'Enregistrement...',
                    text: 'Nous enregistrons vos informations',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Create FormData
                const formData = new FormData(profileForm);

                // Add 243 prefix to phone
                const phoneInput = document.getElementById('telephone').value;
                formData.set('telephone', '243' + phoneInput);

                // Submit form
                fetch('../controller/update_profile_etudiant.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Profil mis à jour!',
                            text: 'Vos informations ont été enregistrées avec succès.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4CAF50'
                        }).then(() => {
                            // Reload page to reflect changes
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue lors de la mise à jour.',
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
                        text: 'Vérifiez votre connexion internet et réessayez.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#4361ee'
                    });
                });
            });
        }

        // Photo upload preview
        document.getElementById('fileUpload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Afficher le spinner
                const spinner = document.getElementById('photoLoadingSpinner');
                const preview = document.getElementById('profilePreview');
                
                spinner.style.display = 'block';
                preview.style.opacity = '0.3';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Créer une nouvelle image pour précharger
                    const img = new Image();
                    img.onload = function() {
                        // Quand l'image est chargée, l'afficher et cacher le spinner
                        preview.src = e.target.result;
                        preview.style.opacity = '1';
                        spinner.style.display = 'none';
                    };
                    img.onerror = function() {
                        // En cas d'erreur
                        preview.style.opacity = '1';
                        spinner.style.display = 'none';
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Impossible de charger l\'image',
                            confirmButtonColor: '#4361ee'
                        });
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Real-time validation
        document.querySelectorAll('#profileForm input[required], #profileForm select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });

        // Phone validation
        document.getElementById('telephone')?.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Don't allow leading 0
            if (this.value.startsWith('0')) {
                this.value = this.value.substring(1);
            }

            // Limit to 9 digits
            if (this.value.length > 9) {
                this.value = this.value.substring(0, 9);
            }

            // Validate format
            const phoneRegex = /^[1-9][0-9]{8}$/;
            if (this.value && !phoneRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else if (this.value) {
                this.classList.remove('is-invalid');
            }
        });

        // OTP input validation
        document.getElementById('otp')?.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.substring(0, 6);
            }

            // Remove invalid class when user types
            if (this.value) {
                this.classList.remove('is-invalid');
            }
        });
        
        // Animation pour les éléments au scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                }
            });
        }, {
            threshold: 0.1
        });
        
        document.querySelectorAll('.profile-section').forEach(section => {
            observer.observe(section);
        });
    </script>
</body>
</html>
