<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/SecurityUtils.php';

// Vérification des droits d'accès
if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

try {
    $universite = new Universite();
    $securityUtils = new SecurityUtils();
    
    // Récupérer l'ID de l'étudiant
    $idEtudiant = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($idEtudiant <= 0) {
        throw new Exception("ID étudiant invalide");
    }
    
    // Récupérer les données de l'étudiant
    $etudiant = $universite->getStudentById($idEtudiant);
    
    if (!$etudiant) {
        throw new Exception("Étudiant non trouvé");
    }
    
    // Vérifier si la photo existe, sinon rediriger vers la page d'upload
    if (empty($etudiant['photo']) && !isset($_GET['skip_photo'])) {
        $_SESSION['upload_photo_student_id'] = $idEtudiant;
        header("Location: ../etudiants/upload-photo?id=$idEtudiant&redirect=ecard");
        exit();
    }
    
    // Informations pour le QR code
    $currentTime = time();
    $expirationTime = $currentTime + (365 * 24 * 60 * 60); // Expire dans 1 an
    
    // Générer une signature numérique avec une clé privée (pour une sécurité accrue)
    $cardData = [
        'id' => $etudiant['idetudiant'],
        'matricule' => $etudiant['matricule'],
        'nom' => $etudiant['noms'],
        'issued_at' => $currentTime,
        'expires_at' => $expirationTime,
        'card_id' => $securityUtils->generateUniqueCardId($etudiant['matricule'], $etudiant['idetudiant'])
    ];
    
    // Signer les données avec une clé privée
    $privateKey = getenv('CONFIG_PRIVATE_KEY') ?: '';
    $signature = $securityUtils->signData(json_encode($cardData), $privateKey);
    
    // Données finales du QR code
    $qrData = array_merge($cardData, ['signature' => $signature]);
    $qrCode = json_encode($qrData);
    
    // Générer un hologramme numérique basé sur l'identité de l'étudiant
    $hologramData = $securityUtils->generateHologram($etudiant['matricule'], $cardId, $etudiant['promotion_idpromotion']);

    
    // Couleur de carte par faculté/promotion
    $colorScheme = $securityUtils->getCardColorScheme($etudiant['promotion_idpromotion']);
    
    // Enregistrer les informations de la carte dans la base de données
    $cardRecord = [
        'student_id' => $etudiant['idetudiant'],
        'card_id' => $cardData['card_id'],
        'issued_at' => date('Y-m-d H:i:s', $currentTime),
        'expires_at' => date('Y-m-d H:i:s', $expirationTime),
        'status' => 'active'
    ];
    
    // Enregistrer la carte dans l'historique
    $recordId = $securityUtils->recordCardIssuance($cardRecord);
    
    // Passer les données à la vue
    $_SESSION['ecard_data'] = [
        'etudiant' => $etudiant,
        'qr_code' => $qrCode,
        'date_generation' => date('Y-m-d H:i:s', $currentTime),
        'date_expiration' => date('Y-m-d H:i:s', $expirationTime),
        'hologram_data' => $hologramData,
        'color_scheme' => $colorScheme,
        'card_id' => $cardData['card_id'],
        'record_id' => $recordId
    ];
    
    // Rediriger vers la page de la carte
    header("Location: ../etudiants/e-card?id=$idEtudiant");
    exit();
    
} catch (Exception $e) {    $_SESSION['error'] = $e->getMessage();
    //header("Location: ../etudiants/etudiant.inscrit");
    echo $e->getMessage();
    //exit();
}