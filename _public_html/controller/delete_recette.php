<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$structure = new Structure();
$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recetteId = isset($_POST['idRecette']) ? intval($_POST['idRecette']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate recette ID
    if ($recetteId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de recette invalide.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
        exit();
    }

    // Retrieve recette details
    $recette = $structure->getRecetteById($recetteId);
    if (!$recette) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Recette non trouvée.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
        exit();
    }

    $montantR = $recette['montantR'];
    $dateOperation = $recette['dateOperation'];
    $motif = $recette['motif'];
    $ligneRecetteId = $recette['ligne_recette_structure_idligne_recette_structure'];
    $bankId = $recette['Banque_idBanque'];

    // Delete the recette
    if ($structure->deleteRecette($recetteId)) {
        // Update bank account balance
        $banque->updateBankBalance($bankId, -$montantR);

        // Retrieve bank and account details
        $bankAccount = $banque->getBanksById($bankId);
        $recetteAccount = $structure->getLignesRecetteById($ligneRecetteId);

        if (!empty($bankAccount) && !empty($recetteAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $recetteAccountNumber = $recetteAccount[0]['numeroCompte'];
            $recetteAccountLabel = $recetteAccount[0]['intituleCompte'];
            $structureId = $recetteAccount[0]['Structure_idStructure'];

            // Create reverse journal entries
            $structure->addJournalAutomatique($dateOperation, $recetteAccountNumber, $recetteAccountLabel, $montantR, 0, "Annulation: $motif", $recetteAccountNumber, $structureId, $userId);
            $structure->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, 0, $montantR, "Annulation: $motif", $recetteAccountNumber, $structureId, $userId);
            
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Recette supprimée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la recette.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/recette.add");
    exit();
}
?>