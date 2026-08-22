<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editResearchUnitBtn'])) {
    // Récupérer les données du formulaire
    $idUniteRecherche = isset($_POST['editIdUniteRecherche']) ? intval($_POST['editIdUniteRecherche']) : 0;
    $designationUR = isset($_POST['editDesignationUR']) ? trim($_POST['editDesignationUR']) : '';
    $description = isset($_POST['editDescription']) ? trim($_POST['editDescription']) : '';
    $idSections = isset($_POST['editIdSection']) ? $_POST['editIdSection'] : [];
    
    // Validation des données
    if (empty($idUniteRecherche) || empty($designationUR) || empty($idSections)) {
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
        // 1. Mettre à jour l'unité de recherche
        $stmtUR = $db->prepare("UPDATE unite_recherche SET designation_UR = ?, description = ? WHERE idunite_recherche = ?");
        $stmtUR->execute([$designationUR, $description, $idUniteRecherche]);
        
        // 2. Supprimer les anciennes associations avec les sections
        $stmtDeleteSections = $db->prepare("DELETE FROM unite_recherche_section WHERE idunite_recherche = ?");
        $stmtDeleteSections->execute([$idUniteRecherche]);
        
        // 3. Créer les nouvelles associations avec les sections
        $stmtSection = $db->prepare("INSERT INTO unite_recherche_section (idunite_recherche, idsection) VALUES (?, ?)");
        
        foreach ($idSections as $idSection) {
            $stmtSection->execute([$idUniteRecherche, $idSection]);
        }
        
        // Valider la transaction
        $db->commit();
        
        // Redirection avec succès
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\'unité de recherche a été modifiée avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        error_log("Erreur lors de la modification de l'unité de recherche: " . $e->getMessage());
        
        // Redirection avec erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la modification de l\'unité de recherche.'
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
