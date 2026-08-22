<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
    $idUniteRecherche = isset($_POST['idUniteRecherche']) ? intval($_POST['idUniteRecherche']) : 0;
    $idOrientations = isset($_POST['idOrientation']) ? $_POST['idOrientation'] : [];
    
    // Validation des données
    if (empty($designation) || empty($idUniteRecherche) || empty($idOrientations)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
        exit;
    }
    
    $db = Connexion::getInstance()->getPDO();
    $db->beginTransaction();
    
    try {
        $insertedCount = 0;
        $errorCount = 0;
        
        foreach ($idOrientations as $idOrientation) {
            // Vérifier si la spécialisation existe déjà pour cette unité de recherche et cette orientation
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM specialisation 
                                   WHERE \"idUnite_recherche\" = ? AND idorientation = ? AND designation = ?");
            $stmtCheck->execute([$idUniteRecherche, $idOrientation, $designation]);
            $count = $stmtCheck->fetchColumn();
            
            if ($count > 0) {
                $errorCount++;
                continue;
            }
            
            // Insérer la spécialisation
            $stmtInsert = $db->prepare("INSERT INTO specialisation (designation, \"dateCreation\", \"idUnite_recherche\", idorientation) 
                                    VALUES (?, NOW(), ?, ?)");
            $stmtInsert->execute([$designation, $idUniteRecherche, $idOrientation]);
            $insertedCount++;
        }
        
        $db->commit();
        
        if ($insertedCount > 0) {
            $message = "La spécialisation a été créée avec succès";
            if ($errorCount > 0) {
                $message .= " (sauf pour $errorCount orientation(s) où elle existait déjà)";
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: '$message.'
                }).then(() => {
                    window.location.href = '../index.php?view=ur/unite_recherche';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Cette spécialisation existe déjà pour toutes les orientations sélectionnées.'
                }).then(() => {
                    window.location.href = '../index.php?view=ur/unite_recherche';
                });
            </script>";
        }
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Erreur lors de la création de la spécialisation: " . $e->getMessage());
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création de la spécialisation.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
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
            window.location.href = '../index.php?view=ur/unite_recherche';
        });
    </script>";
}
