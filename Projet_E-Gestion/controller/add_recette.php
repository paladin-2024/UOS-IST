<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$structure = new Structure();
$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dateOperation = isset($_POST['dateOperation']) ? trim($_POST['dateOperation']) : '';
    $montantR = isset($_POST['montantR']) ? floatval($_POST['montantR']) : 0.0;
    $motif = isset($_POST['motif']) ? trim($_POST['motif']) : '';
    $depositaire = isset($_POST['depositaire']) ? trim($_POST['depositaire']) : '';
    $ligneRecetteId = isset($_POST['ligneRecetteId']) ? intval($_POST['ligneRecetteId']) : 0;
    $bankId = isset($_POST['bankId']) ? intval($_POST['bankId']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate required fields
    if (empty($dateOperation) || $montantR <= 0 || $ligneRecetteId <= 0 || $bankId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
        exit();
    }

    // Check for future date
    if (strtotime($dateOperation) > time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de l\'opération ne peut pas être dans le futur.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
        exit();
    }

    // Insert the new recette using the model
    if ($structure->addRecette($montantR, $motif, $depositaire, $dateOperation, $userId, $ligneRecetteId, $bankId)) {
        // Update bank account balance
        $banque->updateBankBalance($bankId, $montantR);

        // Retrieve bank and account details
        $bankAccount = $banque->getBanksById($bankId);
        $recetteAccount = $structure->getLignesRecetteById($ligneRecetteId);

        if (!empty($bankAccount) && !empty($recetteAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $recetteAccountNumber = $recetteAccount[0]['numeroCompte'];
            $recetteAccountLabel = $recetteAccount[0]['intituleCompte'];
            $structureId = $recetteAccount[0]['Structure_idStructure'];

            // Create journal entries
            $structure->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, $montantR, 0, $motif, "NA", $structureId, $userId);
            $structure->addJournalAutomatique($dateOperation, $recetteAccountNumber, $recetteAccountLabel, 0, $montantR, $motif, "NA", $structureId, $userId);
            
            
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Recette ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/recette.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la recette.'
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