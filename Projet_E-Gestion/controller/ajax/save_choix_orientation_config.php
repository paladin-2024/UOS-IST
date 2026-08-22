<?php
header('Content-Type: application/json');
require_once '../../config/Connexion.php';
require_once '../../models/Universite.php';

$universite = new Universite();
$connexion = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

$promotion_source_id = isset($_POST['promotion_source_id']) ? intval($_POST['promotion_source_id']) : 0;
$annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
$promotions_cibles = isset($_POST['promotions_cibles']) ? json_decode($_POST['promotions_cibles'], true) : [];
$frais_ids = isset($_POST['frais_ids']) ? json_decode($_POST['frais_ids'], true) : [];

if (!$promotion_source_id || !$annee_acad_id || empty($promotions_cibles)) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres invalides'
    ]);
    exit;
}

try {
    $connexion->beginTransaction();

    // Désactiver les anciennes configurations pour cette promotion source et année
    $queryDesactive = "UPDATE configuration_choix_orientation 
                      SET est_active = 0 
                      WHERE promotion_source_id = :promotionSourceId 
                      AND annee_acad_id = :anneeAcadId";
    $stmtDesactive = $connexion->prepare($queryDesactive);
    $stmtDesactive->execute([
        'promotionSourceId' => $promotion_source_id,
        'anneeAcadId' => $annee_acad_id
    ]);

    // Créer les nouvelles configurations
    foreach ($promotions_cibles as $promotion_cible_id) {
        $queryInsert = "INSERT INTO configuration_choix_orientation 
                       (promotion_source_id, promotion_cible_id, annee_acad_id, est_active) 
                       VALUES (:promotionSourceId, :promotionCibleId, :anneeAcadId, 1)";
        $stmtInsert = $connexion->prepare($queryInsert);
        $stmtInsert->execute([
            'promotionSourceId' => $promotion_source_id,
            'promotionCibleId' => $promotion_cible_id,
            'anneeAcadId' => $annee_acad_id
        ]);

        $configId = $connexion->lastInsertId();

        // Ajouter les frais requis pour chaque configuration
        if (!empty($frais_ids)) {
            foreach ($frais_ids as $fraisId) {
                $queryFrais = "INSERT INTO frais_choix_orientation (frais_id, config_choix_orientation_id) 
                               VALUES (:fraisId, :configId)";
                $stmtFrais = $connexion->prepare($queryFrais);
                $stmtFrais->execute([
                    'fraisId' => $fraisId,
                    'configId' => $configId
                ]);
            }
        }
    }

    $connexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Configuration enregistrée avec succès'
    ]);
} catch (Exception $e) {
    $connexion->rollBack();
    error_log("Erreur save_choix_orientation_config: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
    ]);
}
