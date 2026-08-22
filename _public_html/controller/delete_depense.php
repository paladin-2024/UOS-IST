<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Banque.php';

$structure = new Structure();
$banque = new Banque();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $depenseId = isset($_POST['idDepense']) ? intval($_POST['idDepense']) : 0;
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate depense ID
    if ($depenseId <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID de dépense invalide.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
        exit();
    }

    // Retrieve depense details
    $depense = $structure->getDepenseById($depenseId);
    if (!$depense) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Dépense non trouvée.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
        exit();
    }

    $montantD = $depense['montantD'];
    $dateOperation = $depense['dateoperation'];
    $motifD = $depense['motifD'];
    $ligneDepenseId = $depense['ligne_depense_structure_idligne_depense_structure'];
    $bankId = $depense['Banque_idBanque'];

    // Delete the depense
    if ($structure->deleteDepense($depenseId)) {
        // Update bank account balance
        $banque->updateBankBalance($bankId, $montantD);

        // Retrieve bank and account details
        $bankAccount = $banque->getBanksById($bankId);
        $depenseAccount = $structure->getLignesDepenseById($ligneDepenseId);

        if (!empty($bankAccount) && !empty($depenseAccount)) {
            $bankAccountNumber = $bankAccount[0]['numeroCompte'];
            $bankAccountLabel = $bankAccount[0]['designation'];
            $depenseAccountNumber = $depenseAccount[0]['numeroCompte'];
            $depenseAccountLabel = $depenseAccount[0]['intituleCompte'];
            $structureId = $depenseAccount[0]['Structure_idStructure'];

            // Create reverse journal entries
            $structure->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, $montantD, 0, "Annulation: $motifD", $depenseAccountNumber, $structureId, $userId);
            $structure->addJournalAutomatique($dateOperation, $depenseAccountNumber, $depenseAccountLabel, 0, $montantD, "Annulation: $motifD", $depenseAccountNumber, $structureId, $userId);
            
        }

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Dépense supprimée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/depense.add';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la suppression de la dépense.'
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