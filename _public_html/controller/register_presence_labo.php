<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier que la méthode est bien POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer et valider les données
$matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
$idSeance = isset($_POST['idSeance']) ? intval($_POST['idSeance']) : 0;

if (empty($matricule) || $idSeance <= 0) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

try {
    // Vérifier que la séance existe et est active
    $querySeance = "SELECT sl.*, l.nom as nom_labo, l.localisation
                    FROM seance_labo sl
                    JOIN laboratoire l ON sl.idlabo = l.idlabo
                    WHERE sl.idseance_labo = :idSeance";
                    
    $stmtSeance = $db->prepare($querySeance);
    $stmtSeance->bindParam(':idSeance', $idSeance);
    $stmtSeance->execute();
    $seance = $stmtSeance->fetch(PDO::FETCH_ASSOC);
    
    if (!$seance) {
        throw new Exception('Séance introuvable');
    }
    
    // Vérifier si le QR code est activé pour cette séance
    if (!$seance['activer_qrcode']) {
        throw new Exception('L\'enregistrement par QR code n\'est pas activé pour cette séance');
    }
    
    // Vérifier que la séance est bien aujourd'hui
    $dateSeance = new DateTime($seance['date_seance']);
    $dateJour = new DateTime();
    if ($dateSeance->format('Y-m-d') != $dateJour->format('Y-m-d')) {
        throw new Exception('Cette séance n\'est pas programmée pour aujourd\'hui');
    }
    
    // Vérifier l'heure de la séance (marge de 15 minutes avant et 30 minutes après)
    $heureDebut = new DateTime($seance['heure_debut']);
    $heureFin = new DateTime($seance['heure_fin']);
    $heureDebutMoins15 = clone $heureDebut;
    $heureDebutMoins15->modify('-15 minutes');
    $heureFinPlus30 = clone $heureFin;
    $heureFinPlus30->modify('+30 minutes');
    
    $heureActuelle = new DateTime();
    if ($heureActuelle < $heureDebutMoins15 || $heureActuelle > $heureFinPlus30) {
        throw new Exception('L\'enregistrement de présence n\'est pas disponible en dehors des heures de la séance');
    }
    
    // Vérifier que l'étudiant existe
    $queryEtudiant = "SELECT * FROM etudiant WHERE matricule = :matricule";
    $stmtEtudiant = $db->prepare($queryEtudiant);
    $stmtEtudiant->bindParam(':matricule', $matricule);
    $stmtEtudiant->execute();
    $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        throw new Exception('Matricule non reconnu. Veuillez vérifier votre saisie');
    }
    
    // Vérifier si l'étudiant est déjà enregistré pour cette séance
    $queryPresenceExistante = "SELECT * FROM presence_labo 
                               WHERE idseance_labo = :idSeance AND idetudiant = :idEtudiant";
    $stmtPresenceExistante = $db->prepare($queryPresenceExistante);
    $stmtPresenceExistante->bindParam(':idSeance', $idSeance);
    $stmtPresenceExistante->bindParam(':idEtudiant', $etudiant['idetudiant']);
    $stmtPresenceExistante->execute();
    $presenceExistante = $stmtPresenceExistante->fetch(PDO::FETCH_ASSOC);
    
    if ($presenceExistante) {
        throw new Exception('Vous êtes déjà enregistré comme présent pour cette séance');
    }
    
    // Enregistrer la présence
    $queryInsert = "INSERT INTO presence_labo (idseance_labo, idetudiant, heure_arrivee, methode_enregistrement, ip_address, user_agent)
                    VALUES (:idSeance, :idEtudiant, NOW(), 'QR', :ipAddress, :userAgent)";
    $stmtInsert = $db->prepare($queryInsert);
    $stmtInsert->bindParam(':idSeance', $idSeance);
    $stmtInsert->bindParam(':idEtudiant', $etudiant['idetudiant']);
    $stmtInsert->bindParam(':ipAddress', $_SERVER['REMOTE_ADDR']);
    $stmtInsert->bindParam(':userAgent', $_SERVER['HTTP_USER_AGENT']);
    $stmtInsert->execute();
    
    $idPresence = $db->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Présence enregistrée avec succès',
        'idPresence' => $idPresence
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
