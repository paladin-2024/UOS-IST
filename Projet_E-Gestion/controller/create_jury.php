<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designation = $_POST['designation'] ?? '';
    $numeroDecision = $_POST['numero_decision'] ?? '';
    $dateDecision = $_POST['date_decision'] ?? '';
    $presidentId = $_POST['president_id'] ?? '';
    $secretaireId = $_POST['secretaire_id'] ?? '';
    $anneeAcadId = $_POST['idAnnee'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    
    // Récupérer l'ID de l'utilisateur connecté
    $idUser = $_SESSION['id'] ?? 0;

    // Valider les entrées
    if (empty($designation) || empty($numeroDecision) || empty($dateDecision) || 
        empty($presidentId) || empty($secretaireId) || empty($anneeAcadId)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
        exit();
    }

    // Créer le jury
    $result = $universite->createJury($designation, $numeroDecision, $dateDecision, 
                                    $presidentId, $secretaireId, $anneeAcadId, 
                                    $commentaire, $idUser);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Bureau de jury créé avec succès.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création du bureau de jury.'
            }).then(() => {
                window.location.href = '../configuration/jury';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../configuration/jury");
    exit();
}
?>
