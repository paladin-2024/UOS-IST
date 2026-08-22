<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$section = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $designationSection = isset($_POST['designationSection']) ? trim($_POST['designationSection']) : '';
    $idAnnee = isset($_POST['idAnnee']) ? intval($_POST['idAnnee']) : 0;
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : null;
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $boite_postale = isset($_POST['boite_postale']) ? trim($_POST['boite_postale']) : null;
    $site_web = isset($_POST['site_web']) ? trim($_POST['site_web']) : null;

    if (empty($designationSection) || $idAnnee <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
        exit();
    }

    // Check for duplicate designation within the same academic year
    $existingSections = $section->getSections();
    foreach ($existingSections as $existingSection) {
        if ($existingSection['idAnnee'] == $idAnnee && strcasecmp($existingSection['designationSection'], $designationSection) == 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cette section existe déjà pour l\'année académique sélectionnée.'
                }).then(() => {
                    window.location.href = '../configuration/faculte';
                });
            </script>";
            exit();
        }
    }

    // Add the new section with contact information
    if ($section->createSection($designationSection, $idAnnee, $adresse, $telephone, $email, $boite_postale, $site_web)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Section ajoutée avec succès.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de la section.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    }
} else {
    header("Location: ../configuration/faculte");
    exit();
}
?>