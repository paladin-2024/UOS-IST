<?php
session_start();
require_once '../config/Connexion.php';

if (!isset($_SESSION['id'])) {
    $_SESSION['message'] = "Vous devez être connecté pour effectuer cette action.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?view=finance/declarations_paiements_etudiants');
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();

    // Cas 1 : déclarations en_attente alors qu'une validée existe déjà pour le même frais/étudiant
    $stmt1 = $connexion->prepare("
        SELECT dp.id
        FROM declarations_paiement dp
        WHERE dp.statut_validation = 'en_attente'
        AND EXISTS (
            SELECT 1 FROM declarations_paiement dp2
            WHERE dp2.affectation_id = dp.affectation_id
            AND dp2.matricule_etudiant = dp.matricule_etudiant
            AND dp2.statut_validation = 'validé'
        )
    ");
    $stmt1->execute();
    $doublons_valides = $stmt1->fetchAll(PDO::FETCH_COLUMN);

    // Cas 2 : plusieurs déclarations en_attente pour le même frais/étudiant, garder la plus ancienne
    $stmt2 = $connexion->prepare("
        SELECT dp.id
        FROM declarations_paiement dp
        INNER JOIN (
            SELECT affectation_id, matricule_etudiant, MIN(id) AS min_id
            FROM declarations_paiement
            WHERE statut_validation = 'en_attente'
            GROUP BY affectation_id, matricule_etudiant
            HAVING COUNT(*) > 1
        ) dups ON dp.affectation_id = dups.affectation_id 
              AND dp.matricule_etudiant = dups.matricule_etudiant
              AND dp.id > dups.min_id
        WHERE dp.statut_validation = 'en_attente'
    ");
    $stmt2->execute();
    $doublons_attente = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $doublons = array_unique(array_merge($doublons_valides, $doublons_attente));

    if (empty($doublons)) {
        $_SESSION['message'] = "Aucun doublon trouvé.";
        $_SESSION['messageType'] = "info";
    } else {
        $count = count($doublons);
        $placeholders = implode(',', array_fill(0, $count, '?'));

        // Supprimer les fichiers de preuve des doublons
        $stmt_files = $connexion->prepare("SELECT preuve_paiement FROM declarations_paiement WHERE id IN ($placeholders)");
        $stmt_files->execute($doublons);
        $fichiers = $stmt_files->fetchAll(PDO::FETCH_COLUMN);
        foreach ($fichiers as $fichier) {
            if ($fichier) {
                $chemin = __DIR__ . '/../uploads/preuves_paiement/' . $fichier;
                if (file_exists($chemin)) {
                    unlink($chemin);
                }
            }
        }

        // Supprimer les doublons
        $stmt_delete = $connexion->prepare("DELETE FROM declarations_paiement WHERE id IN ($placeholders)");
        $stmt_delete->execute($doublons);

        $_SESSION['message'] = "$count déclaration(s) doublon(s) supprimée(s) avec succès.";
        $_SESSION['messageType'] = "success";
    }
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur : " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

header('Location: ../index.php?view=finance/declarations_paiements_etudiants&statut=en_attente');
exit();
