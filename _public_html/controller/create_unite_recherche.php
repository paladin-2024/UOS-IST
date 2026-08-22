<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addResearchUnitBtn'])) {
    // Récupérer les données du formulaire
    $designationUR = isset($_POST['designationUR']) ? trim($_POST['designationUR']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $idSections = isset($_POST['idSection']) ? $_POST['idSection'] : [];
    
    // Validation des données
    if (empty($designationUR) || empty($idSections)) {
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
        // 1. Insérer l'unité de recherche
        $stmtUR = $db->prepare("INSERT INTO unite_recherche (\"designation_UR\", description, \"idUser\", \"dateCreation\") VALUES (?, ?, ?, NOW())");
        $idUser = isset($_SESSION['id']) ? $_SESSION['id'] : null;
        $stmtUR->execute([$designationUR, $description, $idUser]);
        
        // Récupérer l'ID généré
        $idUniteRecherche = $db->lastInsertId();
        
        // 2. Associer les sections
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
                text: 'L\'unité de recherche a été créée avec succès.'
            }).then(() => {
                window.location.href = '../index.php?view=ur/unite_recherche';
            });
        </script>";
    } catch (PDOException $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        error_log("Erreur lors de la création de l'unité de recherche: " . $e->getMessage());
        
        // Redirection avec erreur
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création de l\'unité de recherche.'
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
