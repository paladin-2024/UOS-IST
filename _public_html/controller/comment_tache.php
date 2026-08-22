<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $tacheId = $_POST['tache_id'] ?? '';
    $role = $_POST['role'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    $validation = $_POST['validation'] ?? '';
    $userId = $_SESSION['id'] ?? '';

    // Validation des données requises
    if (!$tacheId || !$role || !$commentaire || !$validation || !$userId) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires'
            }).then(() => {
                window.location.href = '../recherche/projet.taches';
            });
        </script>";
        exit();
    }

    try {
        $universite = new Universite();

        $tachesModel = new Etudiant();
        $idEnseignant = $universite->getEnseignantIdByUserId($userId);

        if (!$idEnseignant) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Enseignant non trouvé'
                }).then(() => {
                    window.location.href = '../recherche/projet.taches';
                });
            </script>";
            exit();
        }

        // Vérifier que l'enseignant est bien le directeur ou l'encadreur de ce sujet
        $tache = $universite->getTacheDetails($tacheId);
        if (!$tache) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tâche non trouvée'
                }).then(() => {
                    window.location.href = '../recherche/projet.taches';
                });
            </script>";
            exit();
        }

        // Traitement du fichier s'il existe
        $fichier = null;
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            $extension = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            
            // Vérifier l'extension du fichier
            $allowedExtensions = ['pdf', 'doc', 'docx', 'txt'];
            if (!in_array($extension, $allowedExtensions)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Type de fichier non autorisé. Utilisez PDF, DOC, DOCX ou TXT'
                    }).then(() => {
                        window.location.href = '../recherche/projet.taches';
                    });
                </script>";
                exit();
            }

            // Générer un nom unique pour le fichier
            $fichier = uniqid() . '_echange_' . date('Ymd') . '.' . $extension;
            $uploadFile = $uploadDir . $fichier;

            // Déplacer le fichier
            if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors du téléchargement du fichier'
                    }).then(() => {
                        window.location.href = '../recherche/projet.taches';
                    });
                </script>";
                exit();
            }
        }

        // Ajouter l'échange et mettre à jour le statut de la tâche
        $success = $tachesModel->ajouterEchange(
            $tacheId,
            $commentaire,
            $fichier,
            $role,
            $idEnseignant
        );

        // Mettre à jour le statut de la tâche
        if ($success) {
            $success = $tachesModel->updateTacheValidation($tacheId, $validation);
        }

        if ($success) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Votre commentaire a été enregistré avec succès'
                }).then(() => {
                    window.location.href = '../recherche/projet.taches';
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'enregistrement'
                }).then(() => {
                    window.location.href = '../recherche/projet.taches';
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
                window.location.href = '../recherche/projet.taches';
            });
        </script>";
    }
} else {
    // Redirection si accès direct au fichier
    header('Location: ../recherche/projet.taches');
    exit();
}
?>