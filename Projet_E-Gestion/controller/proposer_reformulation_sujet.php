<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $connexion = Connexion::getInstance()->getPDO();
        $connexion->beginTransaction();

        // Récupération des données du formulaire
        $sujetId = intval($_POST['sujet_id'] ?? 0);
        $etudiantId = intval($_POST['etudiant_id'] ?? 0);
        $intitulePropose = trim($_POST['intitule_propose'] ?? '');
        $idSpecialisationPropose = intval($_POST['idSpecialisation_propose'] ?? 0);
        $directeurIdPropose = intval($_POST['directeur_id_propose'] ?? 0);
        $encadreurIdPropose = !empty($_POST['encadreur_id_propose']) ? intval($_POST['encadreur_id_propose']) : null;
        $justificationEtudiant = trim($_POST['justification_etudiant'] ?? '');

        // Validation des données requises
        if (empty($sujetId) || empty($etudiantId) || empty($intitulePropose) || 
            empty($idSpecialisationPropose) || empty($directeurIdPropose) || empty($justificationEtudiant)) {
            throw new Exception("Tous les champs obligatoires doivent être renseignés.");
        }

        // Validation : directeur et encadreur différents
        if ($encadreurIdPropose && $directeurIdPropose === $encadreurIdPropose) {
            throw new Exception("Le directeur et l'encadreur doivent être différents.");
        }

        // Vérifier que le sujet existe et appartient à l'étudiant
        $checkQuery = "SELECT s.*, sc.commentaire_commission 
                       FROM sujets s 
                       LEFT JOIN (
                           SELECT idsujets, commentaire_commission 
                           FROM sujets 
                           WHERE commentaire_commission IS NOT NULL
                       ) sc ON s.idsujets = sc.idsujets
                       WHERE s.idsujets = :sujet_id AND s.etudiant_idetudiant = :etudiant_id 
                       AND s.statut_validation = 'A reformulé'";
        $checkStmt = $connexion->prepare($checkQuery);
        $checkStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $checkStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $checkStmt->execute();
        $existingSujet = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingSujet) {
            throw new Exception("Le sujet n'existe pas, n'appartient pas à cet étudiant ou n'est pas en statut 'A reformulé'.");
        }

        // Vérifier qu'il n'y a pas déjà une reformulation en attente
        $checkReformulationQuery = "SELECT COUNT(*) as count FROM sujet_reformulations 
                                   WHERE idsujets = :sujet_id AND statut_reformulation = 'En attente'";
        $checkReformulationStmt = $connexion->prepare($checkReformulationQuery);
        $checkReformulationStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $checkReformulationStmt->execute();
        $reformulationCount = $checkReformulationStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($reformulationCount > 0) {
            throw new Exception("Une reformulation est déjà en attente de traitement pour ce sujet.");
        }

        // Insérer la proposition de reformulation
        $insertQuery = "INSERT INTO sujet_reformulations 
                       (idsujets, etudiant_idetudiant, intitule_propose, idSpecialisation_propose, 
                        idDirecteur_propose, idEncadreur_propose, justification_etudiant, 
                        commentaire_commission_original, date_proposition) 
                       VALUES 
                       (:sujet_id, :etudiant_id, :intitule_propose, :specialisation_propose, 
                        :directeur_propose, :encadreur_propose, :justification, 
                        :commentaire_original, NOW())";

        $insertStmt = $connexion->prepare($insertQuery);
        $insertStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $insertStmt->bindValue(':etudiant_id', $etudiantId, PDO::PARAM_INT);
        $insertStmt->bindValue(':intitule_propose', $intitulePropose, PDO::PARAM_STR);
        $insertStmt->bindValue(':specialisation_propose', $idSpecialisationPropose, PDO::PARAM_INT);
        $insertStmt->bindValue(':directeur_propose', $directeurIdPropose, PDO::PARAM_INT);
        $insertStmt->bindValue(':encadreur_propose', $encadreurIdPropose, PDO::PARAM_INT);
        $insertStmt->bindValue(':justification', $justificationEtudiant, PDO::PARAM_STR);
        $insertStmt->bindValue(':commentaire_original', $existingSujet['commentaire_commission'], PDO::PARAM_STR);

        $insertStmt->execute();

        // Enregistrer dans l'historique
        $historiqueQuery = "INSERT INTO sujet_historique 
                           (idsujets, action, intitule_avant, intitule_apres, statut_avant, statut_apres, 
                            commentaire, idUser, type_utilisateur, details_modification) 
                           VALUES 
                           (:sujet_id, 'Reformulation demandée', :intitule_avant, :intitule_apres, 
                            'A reformulé', 'A reformulé', :justification, :user_id, 'Etudiant', :details)";

        $details = json_encode([
            'specialisation_avant' => $existingSujet['idSpecialisation'],
            'specialisation_apres' => $idSpecialisationPropose,
            'directeur_avant' => $existingSujet['idDirecteur'],
            'directeur_apres' => $directeurIdPropose,
            'encadreur_avant' => $existingSujet['idEncadreur'],
            'encadreur_apres' => $encadreurIdPropose
        ]);

        $historiqueStmt = $connexion->prepare($historiqueQuery);
        $historiqueStmt->bindValue(':sujet_id', $sujetId, PDO::PARAM_INT);
        $historiqueStmt->bindValue(':intitule_avant', $existingSujet['intitule'], PDO::PARAM_STR);
        $historiqueStmt->bindValue(':intitule_apres', $intitulePropose, PDO::PARAM_STR);
        $historiqueStmt->bindValue(':justification', $justificationEtudiant, PDO::PARAM_STR);
        $historiqueStmt->bindValue(':user_id', $_SESSION['student_id'], PDO::PARAM_INT);
        $historiqueStmt->bindValue(':details', $details, PDO::PARAM_STR);

        $historiqueStmt->execute();

        $connexion->commit();

        $_SESSION['success'] = "Votre proposition de reformulation a été soumise avec succès. Elle sera examinée par l'administration.";
        header("Location: ../portail/student");
        exit();

    } catch (Exception $e) {
        if (isset($connexion)) {
            $connexion->rollBack();
        }
        
        error_log("Erreur proposition reformulation: " . $e->getMessage());
        $_SESSION['error'] = "Une erreur est survenue lors de la soumission de votre proposition: " . $e->getMessage();
        header("Location: ../portail/student");
        exit();
    }
} else {
    header("Location: ../portail/student");
    exit();
}
?>