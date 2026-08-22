<?php
/**
 * Script pour nettoyer les étudiants dont les promotions n'existent plus
 */

require_once 'config/Connexion.php';

try {
    $pdo = Connexion::getInstance()->getPDO();

    // Identifier les étudiants dont la promotion n'existe plus
    $query = "SELECT e.idetudiant, e.noms, e.matricule, e.promotion_idpromotion,
                     p.designationPromotion, e.annee_acad_idannee_acad, a.designation as annee
              FROM etudiant e
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
              WHERE p.idpromotion IS NULL
              ORDER BY e.annee_acad_idannee_acad DESC, e.noms ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $orphanedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== ÉTUDIANTS DONT LES PROMOTIONS N'EXISTENT PLUS ===\n\n";
    echo "Nombre d'étudiants orphelins trouvés : " . count($orphanedStudents) . "\n\n";

    if (count($orphanedStudents) > 0) {
        echo "Liste des étudiants :\n";
        echo str_pad("ID", 8) . str_pad("Matricule", 15) . str_pad("Noms", 30) . str_pad("Année", 15) . "Promotion ID\n";
        echo str_repeat("-", 80) . "\n";

        foreach ($orphanedStudents as $student) {
            echo str_pad($student['idetudiant'], 8) .
                 str_pad($student['matricule'], 15) .
                 str_pad(substr($student['noms'], 0, 28), 30) .
                 str_pad($student['annee'], 15) .
                 $student['promotion_idpromotion'] . "\n";
        }

        echo "\n=== OPTIONS DE NETTOYAGE ===\n";
        echo "1. Désactiver les étudiants orphelins (recommandé)\n";
        echo "2. Supprimer définitivement les étudiants orphelins\n";
        echo "3. Afficher seulement (pas de modification)\n";

        // Pour un usage en ligne de commande ou via navigateur
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
        } else {
            $action = '3'; // Par défaut, afficher seulement
        }

        switch ($action) {
            case '1':
                echo "\n=== DÉSACTIVATION DES ÉTUDIANTS ORPHELINS ===\n";
                $updateQuery = "UPDATE etudiant SET est_actif = 0 WHERE idetudiant = ?";
                $updateStmt = $pdo->prepare($updateQuery);

                $successCount = 0;
                foreach ($orphanedStudents as $student) {
                    try {
                        $updateStmt->execute([$student['idetudiant']]);
                        $successCount++;
                        echo "✓ Étudiant {$student['noms']} ({$student['matricule']}) désactivé\n";
                    } catch (Exception $e) {
                        echo "✗ Erreur pour {$student['noms']}: " . $e->getMessage() . "\n";
                    }
                }
                echo "\nTotal désactivés : $successCount / " . count($orphanedStudents) . "\n";
                break;

            case '2':
                echo "\n=== SUPPRESSION DÉFINITIVE DES ÉTUDIANTS ORPHELINS ===\n";
                echo "⚠️ ATTENTION : Cette action est IRRÉVERSIBLE !\n";
                echo "Les étudiants suivants vont être supprimés :\n";

                foreach ($orphanedStudents as $student) {
                    echo "- {$student['noms']} ({$student['matricule']})\n";
                }

                // Demander confirmation pour la suppression
                if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
                    echo "\nPour confirmer la suppression, ajoutez ?confirm=yes à l'URL\n";
                    break;
                }

                $deleteQuery = "DELETE FROM etudiant WHERE idetudiant = ?";
                $deleteStmt = $pdo->prepare($deleteQuery);

                $successCount = 0;
                $pdo->beginTransaction();

                try {
                    foreach ($orphanedStudents as $student) {
                        $deleteStmt->execute([$student['idetudiant']]);
                        $successCount++;
                    }
                    $pdo->commit();
                    echo "\nTotal supprimés : $successCount / " . count($orphanedStudents) . "\n";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "\nErreur lors de la suppression : " . $e->getMessage() . "\n";
                    echo "Aucun étudiant n'a été supprimé.\n";
                }
                break;

            default:
                echo "\nAucune action effectuée. Utilisez :\n";
                echo "- ?action=1 pour désactiver\n";
                echo "- ?action=2 pour supprimer (puis ?action=2&confirm=yes pour confirmer)\n";
                break;
        }

    } else {
        echo "Aucun étudiant orphelin trouvé. La base de données est propre.\n";
    }

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>
