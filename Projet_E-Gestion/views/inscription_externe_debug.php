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

// Debug des données POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>DEBUG - Données POST reçues :</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>DEBUG - Données FILES reçues :</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
}

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

// Traitement du formulaire (seulement si pas d'erreur et pas déjà en succès)
if (!$error_type && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_started = false;
    try {
        // Vérification anti-double soumission simple
        $submission_key = 'submission_' . $token . '_' . session_id();
        if (isset($_SESSION[$submission_key])) {
            throw new Exception("Cette inscription a déjà été soumise. Veuillez ne pas soumettre le formulaire plusieurs fois.");
        }
        
        // Validation des données avec vérification d'existence des clés
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
        
        echo "<h3>DEBUG - Valeurs après traitement :</h3>";
        echo "<pre>";
        echo "nom: '" . $nom . "' (empty: " . (empty($nom) ? 'true' : 'false') . ")\n";
        echo "postnom: '" . $postnom . "' (empty: " . (empty($postnom) ? 'true' : 'false') . ")\n";
        echo "prenom: '" . $prenom . "' (empty: " . (empty($prenom) ? 'true' : 'false') . ")\n";
        echo "email: '" . $email . "' (empty: " . (empty($email) ? 'true' : 'false') . ")\n";
        echo "telephone: '" . $telephone . "' (empty: " . (empty($telephone) ? 'true' : 'false') . ")\n";
        echo "date_naissance: '" . $date_naissance . "' (empty: " . (empty($date_naissance) ? 'true' : 'false') . ")\n";
        echo "lieu_naissance: '" . $lieu_naissance . "' (empty: " . (empty($lieu_naissance) ? 'true' : 'false') . ")\n";
        echo "sexe: '" . $sexe . "' (empty: " . (empty($sexe) ? 'true' : 'false') . ")\n";
        echo "nationalite: '" . $nationalite . "' (empty: " . (empty($nationalite) ? 'true' : 'false') . ")\n";
        echo "adresse_complete: '" . $adresse_complete . "' (empty: " . (empty($adresse_complete) ? 'true' : 'false') . ")\n";
        echo "</pre>";
        
        // Validation plus détaillée
        $errors = [];
        if (empty($nom)) $errors[] = "Nom";
        if (empty($postnom)) $errors[] = "Post-nom";
        if (empty($prenom)) $errors[] = "Prénom";
        if (empty($email)) $errors[] = "Email";
        if (empty($telephone)) $errors[] = "Téléphone";
        if (empty($date_naissance)) $errors[] = "Date de naissance";
        if (empty($lieu_naissance)) $errors[] = "Lieu de naissance";
        if (empty($sexe)) $errors[] = "Sexe";
        if (empty($nationalite)) $errors[] = "Nationalité";
        if (empty($adresse_complete)) $errors[] = "Adresse complète";
        
        if (!empty($errors)) {
            echo "<h3>DEBUG - Erreurs détectées :</h3>";
            echo "<pre>";
            print_r($errors);
            echo "</pre>";
            throw new Exception("Les champs suivants sont obligatoires et doivent être remplis : " . implode(", ", $errors));
        }
        
        echo "<h3>DEBUG - Validation réussie, tentative d'insertion...</h3>";
        
        // Le reste du code d'insertion...
        echo "<p>Si vous voyez ce message, la validation a réussi !</p>";
        
    } catch (Exception $e) {
        echo "<h3>DEBUG - Exception capturée :</h3>";
        echo "<pre>" . $e->getMessage() . "</pre>";
        $form_error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEBUG - Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>DEBUG - Formulaire d'inscription</h1>
        
        <?php if (isset($form_error_message)): ?>
            <div class="alert alert-danger">
                <strong>Erreur :</strong> <?= htmlspecialchars($form_error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$error_type && $lien): ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="postnom" class="form-label">Post-nom *</label>
                            <input type="text" class="form-control" id="postnom" name="postnom" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="telephone" class="form-label">Téléphone *</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance *</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="lieu_naissance" class="form-label">Lieu de naissance *</label>
                            <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="sexe" class="form-label">Sexe *</label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionner</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="nationalite" class="form-label">Nationalité *</label>
                            <input type="text" class="form-control" id="nationalite" name="nationalite" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="adresse_complete" class="form-label">Adresse complète *</label>
                            <textarea class="form-control" id="adresse_complete" name="adresse_complete" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="personne_contact" class="form-label">Personne à contacter</label>
                            <input type="text" class="form-control" id="personne_contact" name="personne_contact">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="telephone_contact" class="form-label">Téléphone de contact</label>
                            <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Tester la soumission</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">
                Erreur : <?= htmlspecialchars($error_message ?? 'Erreur inconnue') ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>