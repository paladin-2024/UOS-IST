<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

$structure = new Structure();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $journal = $_POST['journal'] ?? null;
    $numeroPiece = $_POST['numeroPiece'] ?? null;
    $dateEcriture = $_POST['dateEcriture'] ?? null;
    $description = $_POST['libelle'] ?? null;
    $lines = json_decode($_POST['lines'], true) ?? [];

    if (empty($journal) || empty($numeroPiece) || empty($dateEcriture) || empty($lines)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../comptabilite/ecriture.add';
            });
        </script>";
        exit();
    }

    try {

        // Calculate total amount
        $totalAmount = array_reduce($lines, function($carry, $line) {
            return $carry + $line['montant'];
        }, 0);

        $totalAmount=round(($totalAmount/2),2);

        // Insert the main entry into the ecriture table
        $ecritureId = $structure->insertEcriture($totalAmount, $dateEcriture, $numeroPiece, $description, $journal, $_SESSION['id']);

        // Insert each line into the ecriture_detail table
        foreach ($lines as $line) {
            $structure->insertEcritureDetail($ecritureId, $line['compteId'], $line['montant'], $line['type']);
        }

        


        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Écriture ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../comptabilite/ecriture.add';
            });
        </script>";
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'écriture.'
            }).then(() => {
                window.location.href = '../comptabilite/ecriture.add';
            });
        </script>";
    }
} else {
    header("Location: ../comptabilite/ecriture.add");
    exit();
}