<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupérer les paramètres
$idSeance = isset($_GET['idSeance']) ? intval($_GET['idSeance']) : 0;
$idEtudiant = isset($_GET['idEtudiant']) ? intval($_GET['idEtudiant']) : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : 'Présent';
$commentaire = isset($_GET['commentaire']) ? $_GET['commentaire'] : null;

// Validation des données
if (!$idSeance || !$idEtudiant) {
    $_SESSION['error'] = "Paramètres invalides.";
    header('Location: ../cours/seances.list');
    exit;
}

// Valider le statut
$statutsValides = ['Présent', 'Retard', 'Absent', 'Excusé'];
if (!in_array($statut, $statutsValides)) {
    $statut = 'Présent';
}

try {
    $db = Connexion::getInstance()->getPDO();
    
    // Vérifier si l'étudiant existe
    $stmtEtudiant = $db->prepare("SELECT idetudiant FROM etudiant WHERE idetudiant = :idEtudiant");
    $stmtEtudiant->bindParam(':idEtudiant', $idEtudiant);
    $stmtEtudiant->execute();
    
    if (!$stmtEtudiant->fetch()) {
        throw new Exception("Étudiant non trouvé.");
    }
    
    // Vérifier si la séance existe
    $stmtSeance = $db->prepare("SELECT idseance FROM seance_cours WHERE idseance = :idSeance");
    $stmtSeance->bindParam(':idSeance', $idSeance);
    $stmtSeance->execute();
    
    if (!$stmtSeance->fetch()) {
        throw new Exception("Séance non trouvée.");
    }
    
    // Vérifier si l'étudiant est déjà marqué présent pour cette séance
    $stmtCheck = $db->prepare("SELECT idpresence FROM presence_cours WHERE idseance = :idSeance AND idetudiant = :idEtudiant");
    $stmtCheck->bindParam(':idSeance', $idSeance);
    $stmtCheck->bindParam(':idEtudiant', $idEtudiant);
    $stmtCheck->execute();
    
    if ($stmtCheck->fetch()) {
        throw new Exception("Cet étudiant est déjà enregistré pour cette séance.");
    }
    
    // Enregistrer la présence
    $now = new DateTime();
    $heureArrivee = $now->format('Y-m-d H:i:s');
    $idUser = $_SESSION['id'];
    $methodeEnregistrement = 'Manuel';
    
    // Récupérer l'adresse IP
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $db->prepare("INSERT INTO presence_cours (idseance, idetudiant, heure_arrivee, statut, commentaire, methode_enregistrement, ip_address, \"idUser\", date_enregistrement) 
                          VALUES (:idSeance, :idEtudiant, :heureArrivee, :statut, :commentaire, :methodeEnregistrement, :ipAddress, :idUser, NOW())");
    
    $stmt->bindParam(':idSeance', $idSeance);
    $stmt->bindParam(':idEtudiant', $idEtudiant);
    $stmt->bindParam(':heureArrivee', $heureArrivee);
    $stmt->bindParam(':statut', $statut);
    $stmt->bindParam(':commentaire', $commentaire);
    $stmt->bindParam(':methodeEnregistrement', $methodeEnregistrement);
    $stmt->bindParam(':ipAddress', $ipAddress);
    $stmt->bindParam(':idUser', $idUser);
    
    $stmt->execute();
    
    // Enregistrer dans le journal d'activités
    $stmtJournal = $db->prepare("INSERT INTO journal_activites (user_type, user_id, type_activite, id_element, description, ip_address) 
                                VALUES ('admin', :idUser, 'presence', :idSeance, :description, :ipAddress)");
    
    $description = "Présence marquée pour l'étudiant ID: $idEtudiant, Statut: $statut";
    $stmtJournal->bindParam(':idUser', $idUser);
    $stmtJournal->bindParam(':idSeance', $idSeance);
    $stmtJournal->bindParam(':description', $description);
    $stmtJournal->bindParam(':ipAddress', $ipAddress);
    
    $stmtJournal->execute();
    
    $_SESSION['success'] = "Présence enregistrée avec succès.";
    
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur: " . $e->getMessage();
}

// Rediriger vers la page de liste des présences
header("Location: ../cours/presence.list&id=$idSeance");
exit;
?>
