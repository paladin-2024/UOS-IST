<?php
// Démarrer la session seulement si pas encore active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Variables pour gérer les erreurs
$error_type = null;
$error_message = null;
$error_icon = null;
$error_details = null;
$configUniversite = null;
$lien = null;

// Vérifier le token
if (!isset($_GET['token'])) {
    $error_type = 'invalid_token';
    $error_message = 'Lien d\'inscription invalide';
    $error_icon = 'bi-exclamation-triangle';
    $error_details = 'Le lien d\'inscription que vous avez utilisé n\'est pas valide. Veuillez vérifier l\'URL ou contacter l\'administration.';
} else {
    $token = $_GET['token'];

    try {
        $connexion = Connexion::getInstance()->getPDO();
        $universiteModel = new Universite();
        
        // Récupérer les informations de l'université
        $configUniversite = $universiteModel->getConfigurationUniversite();
        
        // Récupérer les informations du lien d'inscription
        $stmt = $connexion->prepare("
            SELECT lie.*, 
                   p.designationPromotion, p.cycle,
                   o.designationOrientation,
                   s.designationSection,
                   aa.designation as annee_academique
            FROM liens_inscription_externe lie
            LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
            LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
            LEFT JOIN section s ON o.section_idsection = s.idsection
            LEFT JOIN annee_acad aa ON lie.annee_acad_id = aa.idannee_acad
            WHERE lie.token_unique = ? AND lie.est_actif = 1
        ");
        $stmt->execute([$token]);
        $lien = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lien) {
            $error_type = 'invalid_link';
            $error_message = 'Lien d\'inscription invalide ou expiré';
            $error_icon = 'bi-link-45deg';
            $error_details = 'Ce lien d\'inscription n\'existe pas ou a été désactivé. Veuillez contacter l\'administration pour obtenir un nouveau lien.';
        } else {
            // Vérifier les dates
            $now = new DateTime();
            $debut = new DateTime($lien['date_debut']);
            $fin = new DateTime($lien['date_fin']);
            
            if ($now < $debut) {
                $error_type = 'not_started';
                $error_message = 'La période d\'inscription n\'a pas encore commencé';
                $error_icon = 'bi-clock';
                $error_details = 'Les inscriptions pour cette formation débuteront le ' . $debut->format('d/m/Y à H:i') . '. Veuillez revenir à cette date.';
            } elseif ($now > $fin) {
                $error_type = 'expired';
                $error_message = 'La période d\'inscription est terminée';
                $error_icon = 'bi-calendar-x';
                $error_details = 'Les inscriptions pour cette formation se sont terminées le ' . $fin->format('d/m/Y à H:i') . '. Contactez l\'administration pour plus d\'informations.';
            } else {
                // Vérifier le nombre maximum d'inscriptions
                if ($lien['max_inscriptions']) {
                    $stmt = $connexion->prepare("SELECT COUNT(*) FROM inscriptions_externes WHERE lien_inscription_id = ?");
                    $stmt->execute([$lien['id']]);
                    $nb_inscriptions = $stmt->fetchColumn();
                    
                    if ($nb_inscriptions >= $lien['max_inscriptions']) {
                        $error_type = 'max_reached';
                        $error_message = 'Le nombre maximum d\'inscriptions a été atteint';
                        $error_icon = 'bi-people-fill';
                        $error_details = 'Toutes les places disponibles (' . $lien['max_inscriptions'] . ') ont été pourvues. Les inscriptions sont fermées.';
                    }
                }
                
                // Si pas d'erreur, récupérer les documents requis
                if (!$error_type) {
                    $stmt = $connexion->prepare("
                        SELECT * FROM lien_inscription_documents 
                        WHERE lien_inscription_id = ? 
                        ORDER BY ordre_affichage
                    ");
                    $stmt->execute([$lien['id']]);
                    $documents_requis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
        
    } catch (Exception $e) {
        $error_type = 'system_error';
        $error_message = 'Erreur système';
        $error_icon = 'bi-exclamation-octagon';
        $error_details = 'Une erreur technique s\'est produite. Veuillez réessayer plus tard ou contacter l\'administration.';
    }
}

// Gestion de l'affichage du succès après redirection
if (isset($_GET['success']) && isset($_SESSION['inscription_success'])) {
    $success = true;
    $reference_inscription = $_SESSION['inscription_success']['reference'];
    $message_succes = $_SESSION['inscription_success']['message'];
    
    // Nettoyer la session
    unset($_SESSION['inscription_success']);
}

// Traitement du formulaire (seulement si pas d'erreur et pas déjà en succès)
if (!$error_type && !isset($success) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_started = false;
    try {
        // Vérification anti-double soumission simple
        $submission_key = 'submission_' . $token . '_' . session_id();
        if (isset($_SESSION[$submission_key])) {
            throw new Exception("Cette inscription a déjà été soumise. Veuillez ne pas soumettre le formulaire plusieurs fois.");
        }
        
        // Debug temporaire - à supprimer après résolution
        $debug_info = "Données POST reçues:\n";
        $debug_info .= "nom: '" . ($_POST['nom'] ?? 'NON_DEFINI') . "'\n";
        $debug_info .= "postnom: '" . ($_POST['postnom'] ?? 'NON_DEFINI') . "'\n";
        $debug_info .= "prenom: '" . ($_POST['prenom'] ?? 'NON_DEFINI') . "'\n";
        $debug_info .= "email: '" . ($_POST['email'] ?? 'NON_DEFINI') . "'\n";
        $debug_info .= "Toutes les clés POST: " . implode(', ', array_keys($_POST));
        
        // Récupération et validation des données
        $nom = trim($_POST['nom'] ?? '');
        $postnom = trim($_POST['postnom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
        $sexe = $_POST['sexe'] ?? '';
        $nationalite = trim($_POST['nationalite'] ?? '');
        $adresse_complete = trim($_POST['adresse_complete'] ?? '');
        $personne_contact = trim($_POST['personne_contact'] ?? '');
        $telephone_contact = trim($_POST['telephone_contact'] ?? '');
        
        // Validation simple et directe avec debug
        if (!$nom) throw new Exception("Le nom est obligatoire. DEBUG: " . $debug_info);
        if (!$postnom) throw new Exception("Le post-nom est obligatoire.");
        if (!$prenom) throw new Exception("Le prénom est obligatoire.");
        if (!$email) throw new Exception("L'email est obligatoire.");
        if (!$telephone) throw new Exception("Le téléphone est obligatoire.");
        if (!$date_naissance) throw new Exception("La date de naissance est obligatoire.");
        if (!$lieu_naissance) throw new Exception("Le lieu de naissance est obligatoire.");
        if (!$sexe) throw new Exception("Le sexe est obligatoire.");
        if (!$nationalite) throw new Exception("La nationalité est obligatoire.");
        if (!$adresse_complete) throw new Exception("L'adresse complète est obligatoire.");
        
        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide.");
        }
        
        // Vérifier l'unicité de l'email
        $stmt = $connexion->prepare("SELECT COUNT(*) FROM inscriptions_externes WHERE email = ? AND lien_inscription_id = ?");
        $stmt->execute([$email, $lien['id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cette adresse email est déjà utilisée pour cette inscription.");
        }
        
        // Vérification supplémentaire contre les doublons basée sur nom+prénom+téléphone
        $stmt = $connexion->prepare("
            SELECT COUNT(*) FROM inscriptions_externes 
            WHERE nom = ? AND prenom = ? AND telephone = ? AND lien_inscription_id = ?
        ");
        $stmt->execute([$nom, $prenom, $telephone, $lien['id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Une inscription avec ces informations (nom, prénom, téléphone) existe déjà.");
        }
        
        // Commencer la transaction seulement après les validations
        $connexion->beginTransaction();
        $transaction_started = true;
        
        // Générer une référence unique
        $reference_inscription = 'INS-' . $lien['reference'] . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insérer l'inscription
        $stmt = $connexion->prepare("
            INSERT INTO inscriptions_externes (
                lien_inscription_id, reference_inscription, nom, postnom, prenom,
                email, telephone, date_naissance, lieu_naissance, sexe, nationalite,
                adresse_complete, personne_contact, telephone_contact, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $lien['id'], $reference_inscription, $nom, $postnom, $prenom,
            $email, $telephone, $date_naissance, $lieu_naissance, $sexe, $nationalite,
            $adresse_complete, $personne_contact, $telephone_contact,
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $inscription_id = $connexion->lastInsertId();
        
        // Traitement des fichiers uploadés
        $upload_dir = 'uploads/inscriptions_externes/' . $inscription_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($documents_requis as $doc) {
            $field_name = 'document_' . $doc['id'];
            
            if (isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION);
                $nom_fichier_stocke = 'doc_' . $doc['id'] . '_' . time() . '.' . $file_extension;
                $chemin_fichier = $upload_dir . $nom_fichier_stocke;
                
                if (move_uploaded_file($_FILES[$field_name]['tmp_name'], $chemin_fichier)) {
                    // Enregistrer le document en base
                    $stmt = $connexion->prepare("
                        INSERT INTO documents_inscription_externe (
                            inscription_externe_id, lien_document_id, nom_fichier_original,
                            nom_fichier_stocke, chemin_fichier, taille_fichier, type_mime
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $inscription_id, $doc['id'], $_FILES[$field_name]['name'],
                        $nom_fichier_stocke, $chemin_fichier, $_FILES[$field_name]['size'],
                        $_FILES[$field_name]['type']
                    ]);
                }
            } elseif ($doc['est_obligatoire']) {
                throw new Exception("Le document '{$doc['designation']}' est obligatoire.");
            }
        }
        
        // Mettre à jour le compteur d'inscriptions
        $stmt = $connexion->prepare("
            UPDATE liens_inscription_externe 
            SET nb_inscriptions_actuelles = nb_inscriptions_actuelles + 1 
            WHERE id = ?
        ");
        $stmt->execute([$lien['id']]);
        
        $connexion->commit();
        $transaction_started = false;
        
        // Marquer cette soumission comme traitée
        $_SESSION[$submission_key] = time();
        
        // Redirection POST-REDIRECT-GET pour éviter la resoumission
        $_SESSION['inscription_success'] = [
            'reference' => $reference_inscription,
            'message' => $lien['message_succes'] ?: "Votre inscription a été soumise avec succès. Vous recevrez une confirmation par email.",
            'token' => $token
        ];
        
        header('Location: ' . $_SERVER['REQUEST_URI'] . '&success=1');
        exit;
        
    } catch (Exception $e) {
        // Rollback seulement si une transaction est active
        if ($transaction_started && $connexion->inTransaction()) {
            $connexion->rollBack();
        }
        $form_error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= $error_type ? 'Erreur' : htmlspecialchars($lien['titre'] ?? 'Inscription') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $lien['couleur_theme'] ?? '#0056b3' ?>;
            --secondary-color: #6c757d;
            --accent-color: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Header universitaire professionnel */
        .university-header {
            background: var(--white);
            padding: 1.5rem 0;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .university-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .university-logo {
            max-height: 80px;
            width: auto;
            object-fit: contain;
        }

        .university-info h1 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .university-info .subtitle {
            color: var(--secondary-color);
            font-weight: 500;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .university-contact {
            font-size: 0.875rem;
            color: var(--secondary-color);
        }

        /* Section principale */
        .main-section {
            background: var(--light-bg);
            min-height: calc(100vh - 120px);
            padding: 2rem 0;
        }

        /* Page d'erreur stylée */
        .error-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 3rem;
            text-align: center;
            max-width: 700px;
            margin: 2rem auto;
            position: relative;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--danger-color), #ff6b6b);
        }

        .error-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            animation: errorPulse 2s infinite;
        }

        .error-icon.warning {
            color: var(--warning-color);
        }

        .error-icon.danger {
            color: var(--danger-color);
        }

        .error-icon.info {
            color: var(--accent-color);
        }

        @keyframes errorPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .error-details {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin: 1.5rem 0;
            border-left: 4px solid var(--warning-color);
        }

        .contact-info {
            background: rgba(13, 110, 253, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }

        .inscription-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
        }

        .inscription-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
        }

        .inscription-header h2 {
            font-weight: 600;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .inscription-badges {
            position: relative;
            z-index: 2;
        }

        .badge-custom {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .period-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            position: relative;
            z-index: 2;
        }

        /* Cards modernes */
        .modern-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
            overflow: hidden;
            transition: var(--transition);
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: var(--white);
            padding: 1.5rem;
            border: none;
            position: relative;
        }

        .card-header-modern h5 {
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-header-modern i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        /* Formulaires stylisés */
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            background: var(--white);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--accent-color);
        }

        .required {
            color: var(--danger-color);
            font-weight: 600;
        }

        /* Boutons modernes */
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .btn-primary-modern:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
        }

        .btn-primary-modern:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Alertes modernes */
        .alert-modern {
            border: none;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-info-modern {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border-left-color: #2196f3;
            color: #1565c0;
        }

        .alert-danger-modern {
            background: linear-gradient(135deg, #ffebee, #fce4ec);
            border-left-color: #f44336;
            color: #c62828;
        }

        .alert-success-modern {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            border-left-color: #4caf50;
            color: #2e7d32;
        }

        /* Page de succès */
        .success-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            margin: 2rem auto;
        }

        .success-icon {
            font-size: 5rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
            animation: successPulse 2s infinite;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .reference-box {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin: 1.5rem 0;
            border-left: 4px solid var(--success-color);
        }

        /* Upload de fichiers stylisé */
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }

        .file-upload-info {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            color: var(--secondary-color);
            margin-top: 0.5rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .university-header {
                text-align: center;
            }
            
            .university-info h1 {
                font-size: 1.5rem;
            }
            
            .inscription-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .btn-primary-modern {
                width: 100%;
                margin-top: 1rem;
            }
            
            .success-container, .error-container {
                margin: 1rem;
                padding: 2rem;
            }
        }

        @media (max-width: 576px) {
            .main-section {
                padding: 1rem 0;
            }
            
            .modern-card {
                margin-bottom: 1rem;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Progress indicator */
        .progress-indicator {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            width: 100%;
            border-radius: 2px;
        }

        /* Spinner de chargement */
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- En-tête universitaire -->
    <?php if ($configUniversite): ?>
    <div class="university-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php if (!empty($configUniversite['logo'])): ?>
                        <img src="<?= htmlspecialchars($configUniversite['logo']) ?>" 
                             alt="Logo" class="university-logo">
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <div class="university-info">
                        <?php if (!empty($configUniversite['ministere_tutelle'])): ?>
                            <div class="subtitle"><?= strtoupper(htmlspecialchars($configUniversite['ministere_tutelle'])) ?></div>
                        <?php endif; ?>
                        <h1><?= strtoupper(htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ')) ?></h1>
                        <?php if (!empty($configUniversite['sigle'])): ?>
                            <div class="subtitle"><?= htmlspecialchars($configUniversite['sigle']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="university-contact text-end">
                        <?php if (!empty($configUniversite['adresse'])): ?>
                            <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($configUniversite['adresse']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($configUniversite['telephone'])): ?>
                            <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($configUniversite['telephone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-section">
        <div class="container">
            <?php if ($error_type): ?>
                <!-- Page d'erreur stylée -->
                <div class="error-container fade-in">
                    <div class="error-icon <?= in_array($error_type, ['not_started', 'expired']) ? 'warning' : (in_array($error_type, ['invalid_token', 'invalid_link', 'system_error']) ? 'danger' : 'info') ?>">
                        <i class="<?= $error_icon ?>"></i>
                    </div>
                    <h2 class="mb-4"><?= htmlspecialchars($error_message) ?></h2>
                    
                    <div class="error-details">
                        <p class="mb-0"><?= htmlspecialchars($error_details) ?></p>
                    </div>

                    <?php if ($lien): ?>
                        <div class="mt-4">
                            <h5>Informations sur l'inscription</h5>
                            <div class="row text-start">
                                <div class="col-md-6">
                                    <strong>Formation :</strong> <?= htmlspecialchars($lien['titre']) ?><br>
                                    <strong>Section :</strong> <?= htmlspecialchars($lien['designationSection']) ?><br>
                                    <strong>Orientation :</strong> <?= htmlspecialchars($lien['designationOrientation']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Promotion :</strong> <?= htmlspecialchars($lien['designationPromotion']) ?><br>
                                    <strong>Cycle :</strong> <?= htmlspecialchars($lien['cycle']) ?><br>
                                    <strong>Année :</strong> <?= htmlspecialchars($lien['annee_academique']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($configUniversite): ?>
                        <div class="contact-info">
                            <h6><i class="bi bi-info-circle me-2"></i>Besoin d'aide ?</h6>
                            <p class="mb-0">
                                Contactez l'administration :
                                <?php if (!empty($configUniversite['telephone'])): ?>
                                    <strong><?= htmlspecialchars($configUniversite['telephone']) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($configUniversite['email'])): ?>
                                    | <strong><?= htmlspecialchars($configUniversite['email']) ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif (isset($success) && $success): ?>
                <!-- Page de succès -->
                <div class="success-container fade-in">
                    <div class="success-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="mb-4">Inscription réussie !</h2>
                    <p class="lead mb-4"><?= htmlspecialchars($message_succes) ?></p>
                    
                    <div class="reference-box">
                        <h5><i class="bi bi-bookmark-fill me-2"></i>Référence d'inscription</h5>
                        <h3 class="text-primary"><?= htmlspecialchars($reference_inscription) ?></h3>
                        <p class="mb-0 text-muted">Conservez précieusement cette référence pour le suivi de votre dossier</p>
                    </div>

                    <div class="mt-4">
                        <p class="text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Vous recevrez un email de confirmation à l'adresse fournie lors de l'inscription.
                        </p>
                    </div>
                </div>

            <?php else: ?>
                <!-- Formulaire d'inscription -->
                <div class="modern-card fade-in">
                    <div class="inscription-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2><i class="bi bi-person-plus me-3"></i><?= htmlspecialchars($lien['titre']) ?></h2>
                                <div class="inscription-badges">
                                    <span class="badge-custom">
                                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($lien['designationSection']) ?>
                                    </span>
                                    <span class="badge-custom">
                                        <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($lien['designationOrientation']) ?>
                                    </span>
                                    <span class="badge-custom">
                                        <i class="bi bi-award me-1"></i><?= htmlspecialchars($lien['designationPromotion']) ?>
                                    </span>
                                    <span class="badge-custom">
                                        <i class="bi bi-layers me-1"></i><?= htmlspecialchars($lien['cycle']) ?> cycle
                                    </span>
                                    <span class="badge-custom">
                                        <i class="bi bi-calendar me-1"></i><?= htmlspecialchars($lien['annee_academique']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="period-info">
                                    <h6><i class="bi bi-clock me-2"></i>Période d'inscription</h6>
                                    <div>Du <?= date('d/m/Y', strtotime($lien['date_debut'])) ?></div>
                                    <div>Au <?= date('d/m/Y', strtotime($lien['date_fin'])) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barre de progression -->
                    <div class="progress-indicator">
                        <div class="progress-bar-custom"></div>
                    </div>

                    <!-- Messages -->
                    <?php if (!empty($lien['message_accueil'])): ?>
                        <div class="alert alert-info-modern">
                            <i class="bi bi-info-circle me-2"></i>
                            <?= nl2br(htmlspecialchars($lien['message_accueil'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($form_error_message)): ?>
                        <div class="alert alert-danger-modern">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($form_error_message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire -->
                    <form method="POST" enctype="multipart/form-data" class="p-4" id="inscriptionForm">
                        <div class="row">
                            <!-- Informations personnelles -->
                            <div class="col-lg-8">
                                <div class="modern-card mb-4">
                                    <div class="card-header-modern">
                                        <h5><i class="bi bi-person-circle"></i>Informations personnelles</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="nom" class="form-label">Nom <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="nom" name="nom" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="postnom" class="form-label">Post-nom <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="postnom" name="postnom" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="prenom" class="form-label">Prénom <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Adresse email <span class="required">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                                    <input type="email" class="form-control" id="email" name="email" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="telephone" class="form-label">Téléphone <span class="required">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                                    <input type="tel" class="form-control" id="telephone" name="telephone" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="date_naissance" class="form-label">Date de naissance <span class="required">*</span></label>
                                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="lieu_naissance" class="form-label">Lieu de naissance <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="sexe" class="form-label">Sexe <span class="required">*</span></label>
                                                <select class="form-select" id="sexe" name="sexe" required>
                                                    <option value="">Sélectionner</option>
                                                    <option value="M">Masculin</option>
                                                    <option value="F">Féminin</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="nationalite" class="form-label">Nationalité <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="nationalite" name="nationalite" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="adresse_complete" class="form-label">Adresse complète <span class="required">*</span></label>
                                                <textarea class="form-control" id="adresse_complete" name="adresse_complete" rows="2" required></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="personne_contact" class="form-label">Personne à contacter</label>
                                                <input type="text" class="form-control" id="personne_contact" name="personne_contact">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="telephone_contact" class="form-label">Téléphone de contact</label>
                                                <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Documents requis -->
                            <div class="col-lg-4">
                                <div class="modern-card">
                                    <div class="card-header-modern">
                                        <h5><i class="bi bi-file-earmark-text"></i>Documents requis</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <?php if (!empty($documents_requis)): ?>
                                            <?php foreach ($documents_requis as $doc): ?>
                                                <div class="file-upload-wrapper">
                                                    <label for="document_<?= $doc['id'] ?>" class="form-label">
                                                        <i class="bi bi-paperclip me-1"></i>
                                                        <?= htmlspecialchars($doc['designation']) ?>
                                                        <?php if ($doc['est_obligatoire']): ?>
                                                            <span class="required">*</span>
                                                        <?php endif; ?>
                                                    </label>
                                                    <?php if (!empty($doc['description'])): ?>
                                                        <div class="text-muted small mb-2"><?= htmlspecialchars($doc['description']) ?></div>
                                                    <?php endif; ?>
                                                    <input type="file" 
                                                           class="form-control" 
                                                           id="document_<?= $doc['id'] ?>" 
                                                           name="document_<?= $doc['id'] ?>"
                                                           accept=".pdf,.jpg,.jpeg,.png"
                                                           <?= $doc['est_obligatoire'] ? 'required' : '' ?>>
                                                    <div class="file-upload-info">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        Formats: PDF, JPG, PNG (max 5MB)
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted">Aucun document requis pour cette inscription.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary-modern" id="submitBtn">
                                <i class="bi bi-check-circle me-2"></i>Soumettre l'inscription
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prévention des soumissions multiples
        let formSubmitted = false;
        
        // Validation des fichiers avec feedback visuel
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                const file = this.files[0];
                const wrapper = this.closest('.file-upload-wrapper');
                const infoDiv = wrapper.querySelector('.file-upload-info');
                
                if (file) {
                    // Vérifier la taille (5MB max)
                    if (file.size > 5 * 1024 * 1024) {
                        infoDiv.innerHTML = '<i class="bi bi-exclamation-triangle text-danger me-1"></i>Fichier trop volumineux (max 5MB)';
                        infoDiv.className = 'file-upload-info text-danger';
                        this.value = '';
                        return;
                    }
                    
                    // Vérifier le type
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    if (!allowedTypes.includes(file.type)) {
                        infoDiv.innerHTML = '<i class="bi bi-exclamation-triangle text-danger me-1"></i>Type de fichier non autorisé';
                        infoDiv.className = 'file-upload-info text-danger';
                        this.value = '';
                        return;
                    }
                    
                    // Fichier valide
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    infoDiv.innerHTML = `<i class="bi bi-check-circle text-success me-1"></i>Fichier sélectionné: ${file.name} (${sizeInMB} MB)`;
                    infoDiv.className = 'file-upload-info text-success';
                } else {
                    infoDiv.innerHTML = '<i class="bi bi-info-circle me-1"></i>Formats: PDF, JPG, PNG (max 5MB)';
                    infoDiv.className = 'file-upload-info';
                }
            });
        });

        // Animation d'entrée pour les cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.modern-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 100);
            });
        });

        // Validation du formulaire en temps réel
        const form = document.querySelector('#inscriptionForm');
        if (form) {
            const requiredFields = form.querySelectorAll('[required]');
            const submitBtn = document.getElementById('submitBtn');
            
            // Validation en temps réel
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });
            
            function validateField(field) {
                const value = field.value.trim();
                
                if (value === '') {
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                    return false;
                }
                
                // Validation spécifique pour l'email
                if (field.type === 'email' && !isValidEmail(value)) {
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                    return false;
                }
                
                // Validation spécifique pour la date
                if (field.type === 'date' && !isValidDate(value)) {
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                    return false;
                }
                
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                return true;
            }
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function isValidDate(dateString) {
                const date = new Date(dateString);
                return date instanceof Date && !isNaN(date);
            }
            
            // Validation avant soumission
            form.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                
                // Valider tous les champs obligatoires
                let isValid = true;
                const invalidFields = [];
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                        invalidFields.push(field.closest('.mb-3').querySelector('label').textContent.replace(' *', ''));
                    }
                });
                
                // Vérifier les documents obligatoires
                const requiredFiles = form.querySelectorAll('input[type="file"][required]');
                requiredFiles.forEach(fileInput => {
                    if (!fileInput.files || fileInput.files.length === 0) {
                        isValid = false;
                        const label = fileInput.closest('.file-upload-wrapper').querySelector('label').textContent.replace(' *', '');
                        invalidFields.push(label);
                        fileInput.classList.add('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    
                    // Afficher un message d'erreur
                    let errorMessage = 'Veuillez corriger les erreurs suivantes :\n';
                    errorMessage += '• ' + invalidFields.join('\n• ');
                    
                    alert(errorMessage);
                    
                    // Faire défiler vers le premier champ invalide
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                    
                    return false;
                }
                
                // Marquer comme soumis mais NE PAS désactiver les champs avant la soumission
                formSubmitted = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Traitement en cours...';
                
                // Laisser le formulaire se soumettre normalement
                // Les champs seront désactivés après la soumission réussie
            });
        }
    </script>
</body>
</html>