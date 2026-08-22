<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$section = new Universite();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idSection = isset($_POST['editSectionId']) ? intval($_POST['editSectionId']) : 0;
    $designationSection = isset($_POST['editSectionDesignation']) ? trim($_POST['editSectionDesignation']) : '';
    $idAnnee = isset($_POST['editSectionAnnee']) ? intval($_POST['editSectionAnnee']) : 0;
    $adresse = isset($_POST['editAdresse']) ? trim($_POST['editAdresse']) : null;
    $telephone = isset($_POST['editTelephone']) ? trim($_POST['editTelephone']) : null;
    $email = isset($_POST['editEmail']) ? trim($_POST['editEmail']) : null;
    $boite_postale = isset($_POST['editBoitePostale']) ? trim($_POST['editBoitePostale']) : null;
    $site_web = isset($_POST['editSiteWeb']) ? trim($_POST['editSiteWeb']) : null;

    if ($idSection <= 0 || empty($designationSection) || $idAnnee <= 0) {
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
        if ($existingSection['idsection'] != $idSection && $existingSection['idAnnee'] == $idAnnee && strcasecmp($existingSection['designationSection'], $designationSection) == 0) {
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

    // Update the section with contact information
    if ($section->updateSection($idSection, $designationSection, $idAnnee, $adresse, $telephone, $email, $boite_postale, $site_web)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Section mise à jour avec succès.'
            }).then(() => {
                window.location.href = '../configuration/faculte';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la mise à jour de la section.'
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