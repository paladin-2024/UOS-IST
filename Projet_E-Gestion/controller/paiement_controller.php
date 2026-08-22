<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

$fraisModel = new Frais();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action (create, update ou delete)
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $type_paiement = isset($_POST['type_paiement']) ? $_POST['type_paiement'] : 'academique';
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    
    $result = false;
    $message = '';

    switch ($action) {
        case 'create':
            // Récupération des données pour créer un paiement
            $etudiantId = isset($_POST['etudiant']) ? intval($_POST['etudiant']) : 0;
            $fraisId = isset($_POST['frais']) ? intval($_POST['frais']) : 0;
            $montantPaye = isset($_POST['montantPaye']) ? floatval($_POST['montantPaye']) : 0;
            $referencePaiement = isset($_POST['referencePaiement']) ? trim($_POST['referencePaiement']) : '';
            $modePaiement = isset($_POST['modePaiement']) ? trim($_POST['modePaiement']) : '';
            $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
            $anneeAcadId = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
            
            // Validation des données obligatoires
            if ($etudiantId <= 0 || $fraisId <= 0 || $montantPaye <= 0 || empty($referencePaiement) || empty($modePaiement) || $anneeAcadId <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../index.php?view=frais/paiement';
                    });
                </script>";
                exit();
            }

            // Dans le case 'create', avant d'enregistrer le paiement, ajoutez ce code:

            // Vérifier que la référence de paiement est unique
            $stmt = $fraisModel->checkDuplicateReference($referencePaiement);

            if ($stmt) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Cette référence de paiement existe déjà. Veuillez en utiliser une autre.'
                    }).then(() => {
                        window.location.href = '../index.php?view=frais/paiement';
                    });
                </script>";
            }

            
            
            // Enregistrement du paiement selon le type
            if ($type_paiement == 'academique') {
                $result = $fraisModel->enregistrerPaiement($etudiantId, $fraisId, $montantPaye, $referencePaiement, $modePaiement, $commentaire, $anneeAcadId, $idUser);
                $message = 'Le paiement a été enregistré avec succès.';
            } else {
                $result = $fraisModel->enregistrerPaiementSoutenance($fraisId, $etudiantId, $montantPaye, $referencePaiement, $modePaiement, $commentaire, $anneeAcadId, $idUser);
                $message = 'Le paiement de soutenance a été enregistré avec succès.';
            }
            
            if (!$result) {
                $message = 'Une erreur est survenue lors de l\'enregistrement du paiement.';
            }
            break;
            
        case 'update':
            // Récupération des données pour mettre à jour un paiement
            $idPaiement = isset($_POST['idPaiement']) ? intval($_POST['idPaiement']) : 0;
            $montantPaye = isset($_POST['montantPaye']) ? floatval($_POST['montantPaye']) : 0;
            $referencePaiement = isset($_POST['referencePaiement']) ? trim($_POST['referencePaiement']) : '';
            $modePaiement = isset($_POST['modePaiement']) ? trim($_POST['modePaiement']) : '';
            $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
            
            // Validation des données obligatoires
            if ($idPaiement <= 0 || $montantPaye <= 0 || empty($referencePaiement) || empty($modePaiement)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../index.php?view=frais/paiement';
                    });
                </script>";
                exit();
            }
            
            // Mise à jour du paiement selon le type
            if ($type_paiement == 'academique') {
                $result = $fraisModel->updatePaiement($idPaiement, $montantPaye, $referencePaiement, $modePaiement, $commentaire);
                $message = 'Le paiement a été mis à jour avec succès.';
            } else {
                $result = $fraisModel->updatePaiementSoutenance($idPaiement, $montantPaye, $referencePaiement, $modePaiement, $commentaire);
                $message = 'Le paiement de soutenance a été mis à jour avec succès.';
            }
            
            if (!$result) {
                $message = 'Une erreur est survenue lors de la mise à jour du paiement.';
            }
            break;
            
        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.location.href = '../index.php?view=frais/paiement';
                });
            </script>";
            exit();
    }
    
    // Affichage du message de résultat
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../index.php?view=frais/paiement';
        });
    </script>";
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete') {
    // Traitement de la suppression via GET
    $idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $type_paiement = isset($_GET['type']) ? $_GET['type'] : 'academique';
    $fraisId = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
    
    if ($idPaiement <= 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID du paiement invalide.'
            }).then(() => {
                window.location.href = '../index.php?view=frais/paiement';
            });
        </script>";
        exit();
    }
    
    // Suppression du paiement selon le type
    if ($type_paiement == 'academique') {
        $result = $fraisModel->deletePaiement($idPaiement);
        $message = $result ? 'Le paiement a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du paiement.';
    } else {
        $result = $fraisModel->deletePaiementSoutenance($idPaiement);
        $message = $result ? 'Le paiement de soutenance a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du paiement.';
    }
    
    echo "<script>
        Swal.fire({
            icon: '" . ($result ? 'success' : 'error') . "',
            title: '" . ($result ? 'Succès' : 'Erreur') . "',
            text: '" . addslashes($message) . "'
        }).then(() => {
            window.location.href = '../index.php?view=frais/paiement';
        });
    </script>";
    
} else {
    // Redirection si accès direct au fichier
    header("Location: ../index.php");
    exit();
}
?>
