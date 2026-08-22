<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assignMultipleBtn'])) {
    // Récupérer les données du formulaire
    $enseignantsIds = isset($_POST['enseignantsIds']) ? $_POST['enseignantsIds'] : [];
    $idSpecialisation = isset($_POST['idSpecialisation']) ? intval($_POST['idSpecialisation']) : 0;
    $idSection = isset($_POST['idSection']) ? intval($_POST['idSection']) : 0;
    
    // Validation des données
    if (empty($enseignantsIds) || $idSpecialisation <= 0 || $idSection <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Les données soumises sont invalides. Veuillez sélectionner au moins un enseignant et une spécialisation.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur§ion={$idSection}';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    $idUser = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    $success = 0;
    $skipped = 0;
    $errors = 0;
    
    try {
        // Commencer une transaction
        $db->beginTransaction();
        
        // Préparer la requête pour vérifier l'existence
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM enseignant_specialisation WHERE idAgent = ? AND idSpecialisation = ?");
        
        // Préparer la requête d'insertion
        $stmtInsert = $db->prepare("INSERT INTO enseignant_specialisation (idAgent, idSpecialisation, dateAffectation, idUser) VALUES (?, ?, NOW(), ?)");
        
        foreach ($enseignantsIds as $idAgent) {
            // Vérifier si l'enseignant est déjà affecté à cette spécialisation
            $stmtCheck->execute([$idAgent, $idSpecialisation]);
            $exists = $stmtCheck->fetchColumn();
            
            if ($exists) {
                $skipped++;
                continue;
            }
            
            // Insérer l'affectation
            if ($stmtInsert->execute([$idAgent, $idSpecialisation, $idUser])) {
                $success++;
            } else {
                $errors++;
            }
        }
        
        // Valider la transaction
        $db->commit();
        
        // Message de succès
        $message = "";
        if ($success > 0) {
            $message .= "{$success} enseignant(s) affecté(s) avec succès. ";
        }
        if ($skipped > 0) {
            $message .= "{$skipped} enseignant(s) déjà affecté(s) à cette spécialisation. ";
        }
        if ($errors > 0) {
            $message .= "{$errors} erreur(s) lors de l'affectation.";
        }
        
        $icon = ($success > 0) ? 'success' : ($skipped > 0 && $errors == 0 ? 'info' : 'warning');
        
        echo "<script>
            Swal.fire({
                icon: '{$icon}',
                title: 'Affectation terminée',
                text: '{$message}'
            }).then(() => {
                window.location.href = '../ur/affecation_ur&section={$idSection}';
            });
        </script>";
        
    } catch (PDOException $e) {
        // En cas d'erreur, annuler la transaction
        $db->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur s\'est produite lors de l\'affectation: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=ur/affecation_ur§ion={$idSection}';
            });
        </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Méthode non autorisée.'
        }).then(() => {
            window.location.href = '../index.php?view=ur/affecation_ur';
        });
    </script>";
}