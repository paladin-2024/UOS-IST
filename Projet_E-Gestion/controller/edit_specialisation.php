<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editSpecialisationBtn'])) {
    // Récupérer les données du formulaire
    $idSpecialisation = isset($_POST['editIdSpecialisation']) ? intval($_POST['editIdSpecialisation']) : 0;
    $designation = isset($_POST['editDesignation']) ? trim($_POST['editDesignation']) : '';
    $idUniteRecherche = isset($_POST['editIdUniteRecherche']) ? intval($_POST['editIdUniteRecherche']) : 0;
    
    // Validation des données
    if (empty($idSpecialisation) || empty($designation) || empty($idUniteRecherche)) {
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
    
    try {
        // Récupérer d'abord la section de cette spécialisation
        $stmtSection = $db->prepare("SELECT idsection FROM specialisation WHERE idSpecialisation = ?");
        $stmtSection->execute([$idSpecialisation]);
        $idSection = $stmtSection->fetchColumn();
        
        if (!$idSection) {
            throw new Exception("Spécialisation non trouvée");
        }
        
        // Vérifier si la spécialisation avec ce nouveau nom existe déjà pour cette unité de recherche et cette section
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM specialisation 
                                   WHERE idUnite_recherche = ? AND idsection = ? AND designation = ? 
                                   AND idSpecialisation != ?");
        $stmtCheck->execute([$idUniteRecherche, $idSection, $designation, $idSpecialisation]);
        $count = $stmtCheck->fetchColumn();
        
        if ($count > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cette spécialisation existe déjà pour cette unité de recherche et cette section.'
                }).then(() => {
                    window.location.href = '../index.php?view=ur/unite_recherche';
                });
            </script>";
            exit;
        }
        
        // Mettre à jour la spécialisation
        $stmtUpdate = $db->prepare("UPDATE specialisation SET designation = ? WHERE idSpecialisation = ?");
        $stmtUpdate->execute([$designation, $idSpecialisation]);
        
        // Redirection avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La spécialisation a été modifiée avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    } catch (Exception $e) {
        error_log("Erreur lors de la modification de la spécialisation: " . $e->getMessage());
        
        // Redirection avec erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la modification de la spécialisation.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    }
} else {
    // Redirection si méthode non autorisée
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
