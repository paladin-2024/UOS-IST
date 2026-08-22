<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

// Créer une instance de la classe Agent
$agent = new Agent();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupérer les données du formulaire
    $idDossierFamille = isset($_POST['idDossierFamille']) ? trim($_POST['idDossierFamille']) : '';
    $noms = isset($_POST['noms']) ? trim($_POST['noms']) : '';
    $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
    $dateNaissance = isset($_POST['dateNaissance']) ? trim($_POST['dateNaissance']) : '';
    $lieuNaissance = isset($_POST['lieuNaissance']) ? trim($_POST['lieuNaissance']) : '';
    $typeLiaison = isset($_POST['typeLiaison']) ? trim($_POST['typeLiaison']) : '';

    // Validation des champs
    if (empty($noms) || empty($sexe) || empty($dateNaissance) || empty($lieuNaissance) || empty($typeLiaison)) {
        // Message d'erreur si un champ est vide
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../grh/agent.famille.add';
            });
        </script>";
        exit();
    }

    // Vérifier quel bouton a été cliqué
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        // Appeler la fonction updateFamilyMember
        if ($agent->updateFamilyMember($idDossierFamille, $noms, $sexe, $dateNaissance, $lieuNaissance, $typeLiaison)) {
            // Redirection avec succès et message Swal
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Membre de la famille mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.famille.edit';
                });
            </script>";
        } else {
            // Message d'erreur avec Swal
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la mise à jour du membre de la famille.'
                }).then(() => {
                    window.location.href = '../grh/agent.famille.edit';
                });
            </script>";
        }
    } else {
        // Redirection pour ajout
        // Appeler la fonction updateFamilyMember
        if ($agent->updateFamilyMember($idDossierFamille, $noms, $sexe, $dateNaissance, $lieuNaissance, $typeLiaison)) {
            // Redirection avec succès et message Swal
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Membre de la famille mis à jour avec succès.'
                }).then(() => {
                    window.location.href = '../grh/agent.famille.add';
                });
            </script>";
        } else {
            // Message d'erreur avec Swal
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Erreur lors de la mise à jour du membre de la famille.'
                }).then(() => {
                    window.location.href = '../grh/agent.famille.add';
                });
            </script>";
        }
    }
} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.famille.add");
    exit();
}