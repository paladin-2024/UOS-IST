<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

try {
    $sujetId = intval($_GET['sujet_id'] ?? 0);
    //$etudiantId = intval($_SESSION['student_id'] ?? 0);

    /*
    if (empty($sujetId) || empty($etudiantId)) {
        throw new Exception("Paramètres manquants");
    }
        */

    $connexion = Connexion::getInstance()->getPDO();

    // Récupérer les reformulations pour ce sujet
    $query = "SELECT sr.*, 
                     sp.designation as specialisation_nom,
                     ad.noms as directeur_nom, ad.grade_id as directeur_grade,
                     ae.noms as encadreur_nom, ae.grade_id as encadreur_grade,
                     gd.designation as grade_directeur,
                     ge.designation as grade_encadreur,
                     u.loginUser as validateur_nom
              FROM sujet_reformulations sr
              LEFT JOIN specialisation sp ON sr.idSpecialisation_propose = sp.idSpecialisation
              LEFT JOIN agent ad ON sr.idDirecteur_propose = ad.idAgent
              LEFT JOIN agent ae ON sr.idEncadreur_propose = ae.idAgent
              LEFT JOIN grade gd ON ad.grade_id = gd.idgrade
              LEFT JOIN grade ge ON ae.grade_id = ge.idgrade
              LEFT JOIN t_users u ON sr.idValidateur = u.idUser
              WHERE sr.idsujets = :sujet_id 
              ORDER BY sr.date_proposition DESC";

    $stmt = $connexion->prepare($query);
    $stmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
    $stmt->execute();
    $reformulations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'historique du sujet
    $historiqueQuery = "SELECT sh.*, 
                               u.loginUser as user_nom,
                               CASE 
                                   WHEN sh.type_utilisateur = 'Etudiant' THEN e.noms
                                   WHEN sh.type_utilisateur = 'Enseignant' THEN a.noms
                                   ELSE u.loginUser
                               END as auteur_nom
                        FROM sujet_historique sh
                        LEFT JOIN t_users u ON sh.idUser = u.idUser
                        LEFT JOIN etudiant e ON sh.idUser = e.idetudiant AND sh.type_utilisateur = 'Etudiant'
                        LEFT JOIN agent a ON sh.idUser = a.idAgent AND sh.type_utilisateur = 'Enseignant'
                        WHERE sh.idsujets = :sujet_id
                        ORDER BY sh.date_action DESC";

    $historiqueStmt = $connexion->prepare($historiqueQuery);
    $historiqueStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
    $historiqueStmt->execute();
    $historique = $historiqueStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'reformulations' => $reformulations,
        'historique' => $historique
    ]);

} catch (Exception $e) {
    error_log("Erreur get_sujet_reformulations: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>