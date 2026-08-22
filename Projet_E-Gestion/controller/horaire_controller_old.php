<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php'; // Importer la page qui contient SweetAlert
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

// Créer une instance de la classe Horaire
$horaire = new Horaire();

// Récupérer l'action demandée
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Récupérer les paramètres de redirection
$idPromotion = isset($_REQUEST['promotion']) ? intval($_REQUEST['promotion']) : 0;
$weekOffset = isset($_REQUEST['week']) ? intval($_REQUEST['week']) : 0;
$redirectParams = "promotion=$idPromotion&week=$weekOffset";

switch ($action) {
    case 'add_horaire':
        // Récupérer les données du formulaire
        $jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
        $heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $typeCours = isset($_POST['type_cours']) ? trim($_POST['type_cours']) : 'CM';
        $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
        $idUser = $_SESSION['id'];
        $date_cours = isset($_POST['date_cours']) ? trim($_POST['date_cours']) : '';
        
        // Validation des données
        if (empty($jour) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idAnneeAcad <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Vérifier que l'heure de fin est après l'heure de début
        if ($heureDebut >= $heureFin) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'L\\'heure de fin doit être postérieure à l\\'heure de début.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter l'horaire
        if ($horaire->addHoraire($jour, $heureDebut, $heureFin, $salle, $idECUE, $idAnneeAcad, $idUser, $typeCours,$date_cours)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\\'ajout de l\\'horaire. Vérifiez qu\\'il n\\'y a pas de chevauchement.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
    
    case 'edit_horaire':
        // Récupérer les données du formulaire
        $idHoraire = isset($_POST['idHoraire']) ? intval($_POST['idHoraire']) : 0;
        $jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
        $heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
        $heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
        $salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
        $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
        $typeCours = isset($_POST['type_cours']) ? trim($_POST['type_cours']) : 'CM';
        
        // Validation des données
        if (empty($jour) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idHoraire <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Vérifier que l'heure de fin est après l'heure de début
        if ($heureDebut >= $heureFin) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'L\\'heure de fin doit être postérieure à l\\'heure de début.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Mettre à jour l'horaire
        if ($horaire->updateHoraire($idHoraire, $jour, $heureDebut, $heureFin, $salle, $idECUE, $typeCours)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été modifié avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la modification de l\\'horaire. Vérifiez qu\\'il n\\'y a pas de chevauchement.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
    
    case 'delete_horaire':
        // Récupérer l'ID de l'horaire à supprimer
        $idHoraire = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($idHoraire <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'ID d\\'horaire invalide.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
            exit();
        }
        
        // Supprimer l'horaire
        if ($horaire->deleteHoraire($idHoraire)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'L\\'horaire a été supprimé avec succès.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la suppression de l\\'horaire.'
                }).then(() => {
                    window.location.href = '../index.php?view=enseignement/horaires&$redirectParams';
                });
            </script>";
        }
        break;
    
    default:
        // Redirection par défaut
        header("Location: ../index.php?view=enseignement/horaires&$redirectParams");
        exit();
}
