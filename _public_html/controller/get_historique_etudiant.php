<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier si le matricule est fourni
if (!isset($_GET['matricule']) || empty($_GET['matricule'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Matricule non spécifié']);
    exit;
}

$matricule = trim($_GET['matricule']);

try {
    // Initialisation de la connexion à la base de données
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les moyennes annuelles
    $stmt = $connexion->prepare("
        SELECT ma.idpromotion, p.\"designationPromotion\", aa.designation as annee_academique,
               ma.moyenne_deliberee, ma.est_admis, ma.credits_obtenus, ma.credits_total,
               ma.mention
        FROM moyenne_annuelle ma
        JOIN promotion p ON ma.idpromotion = p.idpromotion
        JOIN annee_acad aa ON ma.annee_acad_idannee_acad = aa.idannee_acad
        WHERE ma.matricule = ?
        ORDER BY aa.designation DESC
    ");
    $stmt->execute([$matricule]);
    $moyennes_annuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les moyennes par semestre
    $stmt = $connexion->prepare("
        SELECT ms.idsemestre, s.\"numeroSemestre\", p.\"designationPromotion\",
               aa.designation as annee_academique, ms.moyenne_deliberee,
               ms.est_valide, ms.credits_obtenus, ms.credits_total
                FROM moyenne_semestre ms
        JOIN semestre s ON ms.idsemestre = s.idsemestre
        JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
        JOIN annee_acad aa ON ms.annee_acad_idannee_acad = aa.idannee_acad
        WHERE ms.matricule = ?
        ORDER BY aa.designation DESC, s.\"numeroSemestre\"
    ");
    $stmt->execute([$matricule]);
    $moyennes_semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les moyennes par UE
    $stmt = $connexion->prepare("
        SELECT mu.\"idUE\", u.\"designationUE\", s.\"numeroSemestre\", 
               p.\"designationPromotion\", aa.designation as annee_academique,
               mu.moyenne_deliberee, mu.est_validee, mu.credits_obtenus,
               mu.type_validation
        FROM moyenne_ue mu
        JOIN ue u ON mu.\"idUE\" = u.\"idUE\"
        JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
        JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
        JOIN annee_acad aa ON mu.annee_acad_idannee_acad = aa.idannee_acad
        WHERE mu.matricule = ?
        ORDER BY aa.designation DESC, s.\"numeroSemestre\", u.\"designationUE\"
    ");
    $stmt->execute([$matricule]);
    $moyennes_ue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Préparer la réponse
    $response = [
        'moyennes_annuelles' => $moyennes_annuelles,
        'moyennes_semestres' => $moyennes_semestres,
        'moyennes_ue' => $moyennes_ue
    ];
    
    // Envoyer la réponse
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    exit;
}
