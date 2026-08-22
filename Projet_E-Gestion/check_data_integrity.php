<?php
/**
 * Script de vérification de l'intégrité des données
 * Vérifie les références orphelines dans la base de données
 */

require_once 'config/Connexion.php';

function checkIntegrity($pdo, $title, $query, $columns) {
    echo "\n=== $title ===\n";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Nombre trouvé : " . count($results) . "\n";

        if (count($results) > 0) {
            // Afficher les en-têtes
            $header = '';
            foreach ($columns as $col) {
                $header .= str_pad($col, 20);
            }
            echo $header . "\n";
            echo str_repeat("-", strlen($header)) . "\n";

            // Afficher les données
            foreach ($results as $row) {
                $line = '';
                foreach ($columns as $col) {
                    $value = isset($row[$col]) ? $row[$col] : '';
                    $line .= str_pad(substr($value, 0, 18), 20);
                }
                echo $line . "\n";
            }
        } else {
            echo "✓ Aucune anomalie détectée\n";
        }

    } catch (Exception $e) {
        echo "Erreur : " . $e->getMessage() . "\n";
    }
}

try {
    $pdo = Connexion::getInstance()->getPDO();

    echo "=== VÉRIFICATION DE L'INTÉGRITÉ DES DONNÉES ===\n";

    // 1. Étudiants avec promotions inexistantes
    checkIntegrity(
        $pdo,
        "ÉTUDIANTS AVEC PROMOTIONS INEXISTANTES",
        "SELECT e.idetudiant, e.matricule, e.noms, e.promotion_idpromotion
         FROM etudiant e
         LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
         WHERE p.idpromotion IS NULL",
        ['idetudiant', 'matricule', 'noms', 'promotion_idpromotion']
    );

    // 2. Étudiants avec années académiques inexistantes
    checkIntegrity(
        $pdo,
        "ÉTUDIANTS AVEC ANNÉES ACADÉMIQUES INEXISTANTES",
        "SELECT e.idetudiant, e.matricule, e.noms, e.annee_acad_idannee_acad
         FROM etudiant e
         LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
         WHERE a.idannee_acad IS NULL",
        ['idetudiant', 'matricule', 'noms', 'annee_acad_idannee_acad']
    );

    // 3. Promotions avec orientations inexistantes
    checkIntegrity(
        $pdo,
        "PROMOTIONS AVEC ORIENTATIONS INEXISTANTES",
        "SELECT p.idpromotion, p.designationPromotion, p.orientation_idorientation
         FROM promotion p
         LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
         WHERE o.idorientation IS NULL",
        ['idpromotion', 'designationPromotion', 'orientation_idorientation']
    );

    // 4. Promotions avec années académiques inexistantes
    checkIntegrity(
        $pdo,
        "PROMOTIONS AVEC ANNÉES ACADÉMIQUES INEXISTANTES",
        "SELECT p.idpromotion, p.designationPromotion, p.annee_acad_idannee_acad
         FROM promotion p
         LEFT JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
         WHERE a.idannee_acad IS NULL",
        ['idpromotion', 'designationPromotion', 'annee_acad_idannee_acad']
    );

    // 5. Départements avec sections inexistantes (si la table existe)
    try {
        checkIntegrity(
            $pdo,
            "DÉPARTEMENTS AVEC SECTIONS INEXISTANTES",
            "SELECT d.iddepartement, d.designationDepartement, d.section_idsection
             FROM departement d
             LEFT JOIN section s ON d.section_idsection = s.idsection
             WHERE s.idsection IS NULL",
            ['iddepartement', 'designationDepartement', 'section_idsection']
        );
    } catch (Exception $e) {
        echo "Note : Table departement non trouvée, vérification ignorée\n";
    }

    // 6. Sections avec années académiques inexistantes
    checkIntegrity(
        $pdo,
        "SECTIONS AVEC ANNÉES ACADÉMIQUES INEXISTANTES",
        "SELECT s.idsection, s.designationSection, s.idAnnee
         FROM section s
         LEFT JOIN annee_acad a ON s.idAnnee = a.idannee_acad
         WHERE a.idannee_acad IS NULL",
        ['idsection', 'designationSection', 'idAnnee']
    );

    // 7. Agents avec sections inexistantes (vérifier les colonnes disponibles)
    try {
        $agentColumns = ['idAgent', 'noms'];
        $agentQuery = "SELECT a.idAgent, a.noms, sec.idsection
                       FROM agent a
                       LEFT JOIN agent_section sec ON a.idAgent = sec.idAgent
                       LEFT JOIN section s ON sec.idsection = s.idsection
                       WHERE sec.idsection IS NOT NULL AND s.idsection IS NULL";

        checkIntegrity($pdo, "AGENTS AVEC SECTIONS INEXISTANTES", $agentQuery, $agentColumns);
    } catch (Exception $e) {
        echo "Erreur vérification agents : " . $e->getMessage() . "\n";
    }

    // 8. Vérification des statistiques d'inscription
    echo "\n=== VÉRIFICATION DES STATISTIQUES D'INSCRIPTION ===\n";

    // Obtenir la dernière année académique
    $yearQuery = "SELECT idannee_acad, designation FROM annee_acad ORDER BY idannee_acad DESC LIMIT 1";
    $yearStmt = $pdo->prepare($yearQuery);
    $yearStmt->execute();
    $latestYear = $yearStmt->fetch(PDO::FETCH_ASSOC);

    if ($latestYear) {
        echo "Année académique testée : {$latestYear['designation']}\n";

        // Statistiques totales
        $totalQuery = "SELECT COUNT(*) as total FROM etudiant WHERE annee_acad_idannee_acad = ?";
        $totalStmt = $pdo->prepare($totalQuery);
        $totalStmt->execute([$latestYear['idannee_acad']]);
        $totalStudents = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Statistiques par promotion
        $promoQuery = "SELECT COUNT(*) as total FROM etudiant e
                       JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       WHERE e.annee_acad_idannee_acad = ?";
        $promoStmt = $pdo->prepare($promoQuery);
        $promoStmt->execute([$latestYear['idannee_acad']]);
        $promoStudents = $promoStmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo "Total étudiants inscrits : $totalStudents\n";
        echo "Étudiants avec promotion : $promoStudents\n";

        if ($totalStudents != $promoStudents) {
            echo "⚠️ INCOHÉRENCE : {$totalStudents} étudiants inscrits vs {$promoStudents} avec promotion\n";
        } else {
            echo "✓ Statistiques cohérentes\n";
        }
    }

    echo "\n=== FIN DE LA VÉRIFICATION ===\n";

} catch (Exception $e) {
    echo "Erreur générale : " . $e->getMessage() . "\n";
}
?>
