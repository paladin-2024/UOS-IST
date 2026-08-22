<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$universite = new Universite();
$ecueModel = new Ecue();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération de l'action
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Récupération des données communes
    $idUser = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
    
    $result = false;
    $message = '';
    $redirectUrl = '';
    
    switch ($action) {
        case 'add_assignment':
            // Récupération des données spécifiques
            $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
            $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $date_limite = isset($_POST['date_limite']) ? trim($_POST['date_limite']) : '';
            $est_payant = isset($_POST['est_payant']) ? 1 : 0;
            $idFrais = isset($_POST['idFrais']) && $est_payant ? intval($_POST['idFrais']) : null;
            
            // Validation des données obligatoires
            if ($idECUE <= 0 || empty($titre) || empty($description) || empty($date_limite)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Gestion du fichier uploadé
            if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le fichier est obligatoire.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Traitement du fichier
            $uploadDir = dirname(__DIR__) . '/uploads/devoirs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $fichier = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $fichier;
            
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors de l\'upload du fichier.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Ajouter le devoir
            $result = $ecueModel->addAssignment($idECUE, $titre, $description, $fichier, $date_limite, $est_payant, $idFrais);
            $message = $result ? 'Le devoir a été ajouté avec succès.' : 'Une erreur est survenue lors de l\'ajout du devoir.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'update_assignment':
            // Récupération des données spécifiques
            $idDevoir = isset($_POST['idDevoir']) ? intval($_POST['idDevoir']) : 0;
            $idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
            $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $date_limite = isset($_POST['date_limite']) ? trim($_POST['date_limite']) : '';
            $est_payant = isset($_POST['est_payant']) ? 1 : 0;
            $idFrais = isset($_POST['idFrais']) && $est_payant ? intval($_POST['idFrais']) : null;
            
            // Validation des données obligatoires
            if ($idDevoir <= 0 || $idECUE <= 0 || empty($titre) || empty($description) || empty($date_limite)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Tous les champs obligatoires doivent être remplis.'
                    }).then(() => {
                        window.location.href = '../enseignement/devoir.edit?id={$idDevoir}';
                    });
                </script>";
                exit();
            }
            
            // Récupérer les informations actuelles du devoir
            $currentDevoir = $ecueModel->getAssignmentById($idDevoir);
            if (!$currentDevoir) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Devoir non trouvé.'
                    }).then(() => {
                        window.location.href = '../enseignement/cours.details?id={$idECUE}';
                    });
                </script>";
                exit();
            }
            
            // Gestion du fichier uploadé (si un nouveau fichier est fourni)
            $fichier = $currentDevoir['fichier']; // Par défaut, conserver le fichier actuel
            
            if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/devoirs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Supprimer l'ancien fichier s'il existe
                if ($currentDevoir['fichier']) {
                    $oldFilePath = $uploadDir . $currentDevoir['fichier'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                
                $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
                $fichier = uniqid() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $fichier;
                
                if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Erreur lors de l\'upload du fichier.'
                        }).then(() => {
                            window.location.href = '../enseignement/devoir.edit?id={$idDevoir}';
                        });
                    </script>";
                    exit();
                }
            }
            
            // Mettre à jour le devoir
            $result = $ecueModel->updateAssignment($idDevoir, $titre, $description, $fichier, $date_limite, $est_payant, $idFrais);
            $message = $result ? 'Le devoir a été mis à jour avec succès.' : 'Une erreur est survenue lors de la mise à jour du devoir.';
            $redirectUrl = "../enseignement/cours.details?id={$idECUE}";
            break;
            
        case 'grade_assignment':
            // Récupération des données spécifiques
            $idReponse = isset($_POST['idReponse']) ? intval($_POST['idReponse']) : 0;
            $idDevoir = isset($_POST['idDevoir']) ? intval($_POST['idDevoir']) : 0;
            $note = isset($_POST['note']) ? floatval($_POST['note']) : 0;
            $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
            
            // Validation des données obligatoires
            if ($idReponse <= 0 || $idDevoir <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Identifiants invalides.'
                    }).then(() => {
                        window.location.href = '../enseignement/devoir.details?id={$idDevoir}';
                    });
                </script>";
                exit();
            }
            
            // Noter la réponse
            $result = $ecueModel->gradeAssignmentResponse($idReponse, $note, $feedback);
            $message = $result ? 'La note a été enregistrée avec succès.' : 'Une erreur est survenue lors de l\'enregistrement de la note.';
            $redirectUrl = "../enseignement/devoir.details?id={$idDevoir}";
            break;
            
        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.history.back();
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
            window.location.href = '{$redirectUrl}';
        });
    </script>";
    
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
    // Traitement des actions via GET
    $action = $_GET['action'];
    $idDevoir = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    switch ($action) {
        case 'delete_assignment':
            // Validation des données
            if ($idDevoir <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID du devoir invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Récupérer l'ECUE associé pour la redirection
            $devoir = $ecueModel->getAssignmentById($idDevoir);
            if (!$devoir) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Devoir non trouvé.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            $idECUE = $devoir['idECUE'];
            
            // Suppression du devoir
            $result = $ecueModel->deleteAssignment($idDevoir);
            $message = $result ? 'Le devoir a été supprimé avec succès.' : 'Une erreur est survenue lors de la suppression du devoir.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/cours.details?id={$idECUE}';
                });
            </script>";
            break;
            
        case 'delete_response':
            $idReponse = isset($_GET['response_id']) ? intval($_GET['response_id']) : 0;
            
            // Validation des données
            if ($idReponse <= 0 || $idDevoir <= 0) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'ID de la réponse ou du devoir invalide.'
                    }).then(() => {
                        window.history.back();
                    });
                </script>";
                exit();
            }
            
            // Suppression de la réponse
            $result = $ecueModel->deleteAssignmentResponse($idReponse);
            $message = $result ? 'La réponse a été supprimée avec succès.' : 'Une erreur est survenue lors de la suppression de la réponse.';
            
            echo "<script>
                Swal.fire({
                    icon: '" . ($result ? 'success' : 'error') . "',
                    title: '" . ($result ? 'Succès' : 'Erreur') . "',
                    text: '" . addslashes($message) . "'
                }).then(() => {
                    window.location.href = '../enseignement/devoir.details?id={$idDevoir}';
                });
            </script>";
            break;
            
        default:
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit();
    }
} else {
    // Redirection si accès direct au fichier
    header("Location: ../enseignement/cours");
    exit();
}
?>
