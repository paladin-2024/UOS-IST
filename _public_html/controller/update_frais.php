<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $id = $_POST['id'] ?? '';
    $designation = $_POST['designation'] ?? '';
    $montant = $_POST['montant'] ?? 0;
    $devise = $_POST['devise'] ?? '';
    $promotionId = $_POST['promotionId'] ?? '';
    $anneeAcadId = $_POST['anneeAcadId'] ?? '';
    $description = $_POST['description'] ?? '';
    $estObligatoire = isset($_POST['estObligatoire']);
    $idUser = $_SESSION['id'] ?? null;

    // Validation des données
    if (empty($id) || empty($designation) || empty($montant) || empty($devise) || empty($promotionId) || empty($anneeAcadId) || $idUser === null) {
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

    // Vérification de l'existence du frais
    $fraisExistant = $universite->getFraisById($id);
    if (!$fraisExistant) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Le frais que vous essayez de modifier n\'existe pas.'
            }).then(() => {
                window.location.href = '../frais/frais_add';
            });
        </script>";
        exit();
    }

    // Vérification des doublons (même désignation pour la même promotion et année académique)
    $fraisExistants = $universite->getFraisByPromotionAndYear($promotionId, $anneeAcadId);
    foreach ($fraisExistants as $frais) {
        if ($frais['idfrais'] != $id && strtolower($frais['designation']) === strtolower($designation)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Un frais avec cette désignation existe déjà pour cette promotion et cette année académique.'
                }).then(() => {
                    window.location.href = '../frais/frais_add';
                });
            </script>";
            exit();
        }
    }

    try {
        // Mise à jour du frais
        $result = $universite->updateFrais(
            intval($id),
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
                    text: 'Le frais a été modifié avec succès.',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = '../frais/frais_add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de la modification du frais');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la modification du frais : " . addslashes($e->getMessage()) . "'
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