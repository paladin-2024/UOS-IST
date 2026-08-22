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
    $montantD = isset($_POST['montantD']) ? floatval($_POST['montantD']) : 0.0;
    $motifD = isset($_POST['motifD']) ? trim($_POST['motifD']) : '';
    $beneficiaire = isset($_POST['beneficiaire']) ? trim($_POST['beneficiaire']) : '';
    $ligneDepenseId = isset($_POST['ligneDepenseId']) ? intval($_POST['ligneDepenseId']) : 0;
    $bankId = isset($_POST['bankId']) ? intval($_POST['bankId']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate required fields
    if (empty($dateOperation) || $montantD <= 0 || $ligneDepenseId <= 0 || $bankId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
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
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
        exit();
    }

    // Insert the new depense using the model
    if ($structure->addDepense($montantD, $motifD, $beneficiaire, $dateOperation, $userId, $ligneDepenseId, $bankId, null)) {
        // Update bank account balance
        $banque->updateBankBalance($bankId, -$montantD);

        // Retrieve bank and account details
        $bankAccount = $banque->getBanksById($bankId);
        $depenseAccount = $structure->getLignesByGroupe($ligneDepenseId);

        if (!empty($bankAccount) && !empty($depenseAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $depenseAccountNumber = $depenseAccount[0]['numeroCompte'];
            $depenseAccountLabel = $depenseAccount[0]['intituleCompte'];
            $structureId = $depenseAccount[0]['Structure_idStructure'];

            // Create journal entries
            $structure->addJournalAutomatique($dateOperation, $depenseAccountNumber, $depenseAccountLabel, $montantD, 0, $motifD, "NA", $structureId, $userId);
            $structure->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, 0, $montantD, $motifD, "NA", $structureId, $userId);
            
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Dépense ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la dépense.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/depense.add");
    exit();
}
?>