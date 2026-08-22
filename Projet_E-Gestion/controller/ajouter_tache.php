<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $description = $_POST['description'] ?? '';
    $etudiantId = $_SESSION['student_id'] ?? '';

    // Validation des données requises
    if (!$description || !$etudiantId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir la description de la tâche'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        </script>";
        exit();
    }

    try {
        $etudiantModel = new Etudiant();
        
        // Vérifier si l'étudiant a un sujet validé
        $sujetAssigne = $etudiantModel->getSujetAssigne($etudiantId);
        
        if (!$sujetAssigne) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Vous n\'avez pas de sujet assigné'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
            exit();
        }

        if ($sujetAssigne['statut_validation'] !== 'Validé') {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Votre sujet doit être validé avant de pouvoir ajouter des tâches'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
            exit();
        }

        // Traitement du fichier s'il existe
        $fichier = null;
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/taches/';

            $extension = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));

            // Créer le répertoire s'il n'existe pas
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Vérifier l'extension du fichier
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt'];
            if (!in_array($extension, $allowedExtensions)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Type de fichier non autorisé. Utilisez PDF, DOC, DOCX ou TXT'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                </script>";
                exit();
            }

            // Générer un nom unique pour le fichier
            $fichier = uniqid() . '_' . date('Ymd') . '.' . $extension;
            $uploadFile = $uploadDir . $fichier;

            // Déplacer le fichier
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors du téléchargement du fichier'
                    }).then(() => {
                        window.location.href = '../portail/student';
                    });
                </script>";
                exit();
            }
        }

        // Ajouter la tâche
        $success = $etudiantModel->creerTache(
            $sujetAssigne['idsujets'],
            $description,
            $fichier,
            $etudiantId
        );
        
        if ($success) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'La tâche a été ajoutée avec succès'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'ajout de la tâche'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'enregistrement'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../portail/student');
    exit();
}
?>