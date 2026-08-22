<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fournisseurId = isset($_POST['fournisseurId']) ? intval($_POST['fournisseurId']) : 0;
    $dateFacture = isset($_POST['dateFacture']) ? trim($_POST['dateFacture']) : '';
    $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0.0;
    $motif = isset($_POST['motif']) ? trim($_POST['motif']) : '';
    $numeroFacture = isset($_POST['numeroFacture']) ? trim($_POST['numeroFacture']) : '';
    $compteProduit = isset($_POST['compteId']) ? trim($_POST['compteId']) : '';
    $statut = 'Non Paye'; // Default status
    $userId = $_SESSION['id']; // Assuming user ID is stored in session

    // Validate required fields
    if ($fournisseurId <= 0 || empty($dateFacture) || $montant <= 0 || empty($numeroFacture)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.add';
            });
        </script>";
        exit();
    }

    // Check for future date
    if (strtotime($dateFacture) > time()) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La date de la facture ne peut pas être dans le futur.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.add';
            });
        </script>";
        exit();
    }

    // Check for duplicate invoice number within the same supplier
    if ($structure->checkDuplicateFournisseurInvoice($numeroFacture, $fournisseurId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une facture avec le même numéro existe déjà pour ce fournisseur.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.add';
            });
        </script>";
        exit();
    }

    // Insert the new supplier invoice using the model
    if ($structure->addSupplierInvoice($dateFacture, $montant, $motif, $numeroFacture, $statut, $userId, $fournisseurId)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Facture fournisseur ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.add';
            });
        </script>";

        // Retrieve supplier data
        $fournisseur = $structure->getFournisseursById($fournisseurId);
        if (!empty($fournisseur)) {
            $compteFournisseur = $fournisseur[0]['numeroCompte'];
            $libelleCompteFournisseur = $fournisseur[0]['intituleCompte'];
            $structureId = $fournisseur[0]['Structure_idStructure'];

            //Récupération des données du compte
            $cmpt=$structure->getCompteById($compteProduit);

            $compteCredit = $cmpt['numeroCompte']; // Example account for purchases
            $libelleCompteCredit = $cmpt['intituleCompte']; // Example label for purchases
            $compteTVA = "44566"; // Example account for VAT
            $libelleCompteTVA = "TVA Déductible"; // Example label for VAT

            // Calculate VAT
            $tauxTVA = 0.16; // Example VAT rate (16%)
            $montantTVA = $montant * $tauxTVA / (1 + $tauxTVA);
            $montantNet = $montant - $montantTVA;

            // Record the journal entry
            $dateOperation = date('Y-m-d');
            $libele = "Facture fournisseur: $numeroFacture";
            $numPiece = $numeroFacture;

            // Debit the supplier's account with the net amount
            $structure->addJournalAutomatique($dateOperation, $compteFournisseur, $libelleCompteFournisseur, 0, $montant, $libele, $numPiece, $structureId,$userId);

            // Credit the purchases account with the net amount
            $structure->addJournalAutomatique($dateOperation, $compteCredit, $libelleCompteCredit, $montantNet, 0, $libele, $numPiece, $structureId,$userId);

            // Credit the VAT account with the VAT amount
            $structure->addJournalAutomatique($dateOperation, $compteTVA, $libelleCompteTVA, $montantTVA, 0, $libele, $numPiece, $structureId,$userId);
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la récupération des données du fournisseur.'
                }).then(() => {
                    window.location.href = '../comptabilite/facture_frs.add';
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la facture fournisseur.'
            }).then(() => {
                window.location.href = '../comptabilite/facture_frs.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/facture_frs.add");
    exit();
}
?>