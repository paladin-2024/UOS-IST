<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Cours.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    header('Location: ../portail/login');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etudiantModel = new Etudiant();
    $coursModel = new Cours();

    // Récupérer les données du formulaire
    $idDevoir = isset($_POST['iddevoir']) ? intval($_POST['iddevoir']) : 0;
    $idEtudiant = isset($_POST['idetudiant']) ? intval($_POST['idetudiant']) : 0;
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';

    //echo "DEVOIR = ".$idDevoir;

    try {
        // Vérifications
        if ($idDevoir <= 0 || $idEtudiant <= 0) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Données invalides. Veuillez réessayer.'
                    }).then(() => {
                        window.history.back();
                    });
                });
            </script>";
            exit();
        }

        // Vérifier si le devoir existe et est encore valide
        $devoirInfo = $coursModel->getDevoirById($idDevoir);
        
        if (!$devoirInfo) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le devoir demandé n\\'existe pas.'
                    }).then(() => {
                        window.history.back();
                    });
                });
            </script>";
            exit();
        }
        
        // Vérifier si le délai n'est pas dépassé
        $dateLimite = new DateTime($devoirInfo['date_limite']);
        $maintenant = new DateTime();
        
        if ($maintenant > $dateLimite) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Délai dépassé',
                        text: 'La date limite de soumission est dépassée.'
                    }).then(() => {
                        window.history.back();
                    });
                });
            </script>";
            exit();
        }
        
        // Vérifier si une soumission existe déjà
        $soumissionExistante = $coursModel->checkExistingSubmission($idDevoir, $idEtudiant);
        
        if ($soumissionExistante && !isset($_POST['confirm_overwrite'])) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Soumission existante',
                        text: 'Vous avez déjà soumis une réponse à ce devoir. Souhaitez-vous la remplacer?',
                        showCancelButton: true,
                        confirmButtonText: 'Oui, remplacer',
                        cancelButtonText: 'Non, annuler'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            let form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '../controller/submit_assignment.php';
                            
                            // Ajouter les champs existants
                            let idDevoirInput = document.createElement('input');
                            idDevoirInput.type = 'hidden';
                            idDevoirInput.name = 'iddevoir';
                            idDevoirInput.value = " . $idDevoir . ";
                            form.appendChild(idDevoirInput);
                            
                            let idEtudiantInput = document.createElement('input');
                            idEtudiantInput.type = 'hidden';
                            idEtudiantInput.name = 'idetudiant';
                            idEtudiantInput.value = " . $idEtudiant . ";
                            form.appendChild(idEtudiantInput);
                            
                            let commentaireInput = document.createElement('input');
                            commentaireInput.type = 'hidden';
                            commentaireInput.name = 'commentaire';
                            commentaireInput.value = '" . addslashes($commentaire) . "';
                            form.appendChild(commentaireInput);
                            
                            // Ajouter le champ de confirmation
                            let confirmInput = document.createElement('input');
                            confirmInput.type = 'hidden';
                            confirmInput.name = 'confirm_overwrite';
                            confirmInput.value = '1';
                            form.appendChild(confirmInput);
                            
                            // Soumettre le formulaire
                            document.body.appendChild(form);
                            form.submit();
                        } else {
                            window.history.back();
                        }
                    });
                });
            </script>";
            exit();
        }

        // Gestion du fichier
        $fichierNom = '';
        $dossierDestination = dirname(__DIR__) . '/uploads/reponses/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($dossierDestination)) {
            mkdir($dossierDestination, 0777, true);
        }
        
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] == 0) {
            // Vérifier l'extension et la taille du fichier
            $extension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $extensionsAutorisees = ['pdf', 'doc', 'docx', 'zip', 'rar', 'txt', 'ppt', 'pptx', 'xls', 'xlsx'];
            $tailleMax = 20 * 1024 * 1024; // 20 Mo
            
            if (!in_array(strtolower($extension), $extensionsAutorisees)) {
                throw new Exception("L'extension du fichier n'est pas autorisée. Extensions autorisées: pdf, doc, docx, zip, rar, txt, ppt, pptx, xls, xlsx");
            }
            
            if ($_FILES['fichier']['size'] > $tailleMax) {
                throw new Exception("Le fichier est trop volumineux. Taille maximale: 20 Mo");
            }
            
            // Générer un nom unique pour le fichier
            $fichierNom = 'devoir_' . $idDevoir . '_etudiant_' . $idEtudiant . '_' . time() . '.' . $extension;
            $cheminFichier = $dossierDestination . $fichierNom;
            
            // Déplacer le fichier uploadé
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $cheminFichier)) {
                throw new Exception("Une erreur est survenue lors du téléchargement du fichier.");
            }
        } else {
            throw new Exception("Aucun fichier n'a été téléchargé ou une erreur s'est produite.");
        }

        // Enregistrer la soumission dans la base de données
        $result = $coursModel->submitAssignment($idDevoir, $idEtudiant, $commentaire, $fichierNom);
        
        if ($result) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Votre devoir a été soumis avec succès.'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                });
            </script>";
        } else {
            throw new Exception("Une erreur est survenue lors de l'enregistrement de votre soumission.");
        }

    } catch (Exception $e) {
        // Supprimer le fichier en cas d'erreur
        if (isset($cheminFichier) && file_exists($cheminFichier)) {
            unlink($cheminFichier);
        }
        
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.history.back();
                });
            });
        </script>";
    }
} else {
    header('Location: ../portail/student');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soumission de devoir</title>
</head>
<body>
    <!-- Cette page est uniquement pour traiter les données -->
</body>
</html>
