<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Soutenance.php';

// Créer une instance de la classe Soutenance
$soutenance = new Soutenance();

// Vérifier l'action demandée
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'programmer_soutenance':
        // Récupérer les données du formulaire
        $dateSoutenance = isset($_POST['date_soutenance']) ? trim($_POST['date_soutenance']) : '';
        $lieu = isset($_POST['lieu']) ? trim($_POST['lieu']) : '';
        $idSujet = isset($_POST['idSujet']) ? intval($_POST['idSujet']) : 0;
        $idUser = $_SESSION['id'];
        
        // Validation des données
        if (empty($dateSoutenance) || empty($lieu) || $idSujet <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tous les champs sont obligatoires.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Programmer la soutenance
        $result = $soutenance->programmerSoutenance($dateSoutenance, $lieu, $idSujet, $idUser);
        
        if ($result['success']) {
            // Ajouter les membres du jury si spécifiés
            $idSoutenance = $result['id'];
            $jurys = isset($_POST['jury']) ? $_POST['jury'] : [];
            $roles = isset($_POST['role']) ? $_POST['role'] : [];
            
            for ($i = 0; $i < count($jurys); $i++) {
                if (!empty($jurys[$i]) && !empty($roles[$i])) {
                    $soutenance->ajouterMembreJury($idSoutenance, $jurys[$i], $roles[$i]);
                }
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La soutenance a été programmée avec succès.'
                }).then(() => {
                    window.location.href = '../recherche/soutenances';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . $result['message'] . "'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
        
    case 'add_frais_soutenance':
        // Récupérer les données du formulaire
        $designation = isset($_POST['designation']) ? trim($_POST['designation']) : '';
        $montant = isset($_POST['montant']) ? floatval($_POST['montant']) : 0;
        $devise = isset($_POST['devise']) ? trim($_POST['devise']) : 'USD';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
        $idUser = $_SESSION['id'];
        
        // Validation des données
        if (empty($designation) || $montant <= 0 || $idAnneeAcad <= 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'La désignation, le montant et l\'année académique sont obligatoires.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
        }
        
        // Ajouter le frais de soutenance
        if ($soutenance->addFraisSoutenance($designation, $montant, $devise, $description, $idAnneeAcad, $idUser)) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le frais de soutenance a été ajouté avec succès.'
                }).then(() => {
                    window.location.href = '../recherche/frais_soutenance';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout du frais de soutenance.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
        }
        break;
    
    default:
        // Redirection par défaut
        header('Location: ../index');
        exit();
}

