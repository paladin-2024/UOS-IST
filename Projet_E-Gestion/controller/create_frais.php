<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $designation = $_POST['designation'] ?? '';
    $montant = $_POST['montant'] ?? 0;
    $devise = $_POST['devise'] ?? '';
    $promotionId = $_POST['promotionId'] ?? '';
    $anneeAcadId = $_POST['anneeAcadId'] ?? '';
    $description = $_POST['description'] ?? '';
    $estObligatoire = isset($_POST['estObligatoire']);
    $idUser = $_SESSION['id'] ?? null;

    // Validation des données
    if (empty($designation) || empty($montant) || empty($devise) || empty($promotionId) || empty($anneeAcadId) || $idUser === null) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
        exit();
    }

    // Validation du montant
    if ($montant <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le montant doit être supérieur à 0.'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
        exit();
    }

    // Vérification de l'existence d'un frais similaire
    $fraisExistants = $universite->getFraisByPromotionAndYear($promotionId, $anneeAcadId);
    foreach ($fraisExistants as $frais) {
        if (strtolower($frais['designation']) === strtolower($designation)) {
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Un frais avec cette désignation existe déjà pour cette promotion et cette année académique.',
                    showCancelButton: true,
                    confirmButtonText: 'Modifier',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../frais/frais_add&edit=" . $frais['idfrais'] . "';
                    } else {
                        window.location.href = '../frais/frais_add';
                    }
                });
            </script>";
            exit();
        }
    }

    try {
        // Création du frais
        $result = $universite->createFrais(
            $designation,
            floatval($montant),
            $devise,
            intval($promotionId),
            intval($anneeAcadId),
            $description,
            $estObligatoire
        );

        if ($result) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le frais a été créé avec succès.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../frais/frais_add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la création du frais');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la création du frais : " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
    }
    exit();
} else {
    // Redirection si la méthode n'est pas POST
    header("Location: ../frais/frais_add");
    exit();
}
?>