<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $tacheId = $_POST['tache_id'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    $typeAuteur = $_POST['type_auteur'] ?? '';
    $idAuteur = $_POST['id_auteur'] ?? '';

    // Validation des données requises
    if (!$tacheId || !$commentaire || !$typeAuteur || !$idAuteur) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires'
            }).then(() => {
                window.location.href = '../portail/student';
            });
        </script>";
        exit();
    }

    try {
        $etudiantModel = new Etudiant();
        
        // Vérifier que la tâche existe et appartient au bon sujet
        $tache = $etudiantModel->getTacheDetails($tacheId);
        if (!$tache) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Tâche non trouvée'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
            exit();
        }

        // Traitement du fichier s'il existe
        $fichier = null;
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/echanges/';
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
                        window.location.href = '../portail/student';
                    });
                </script>";
                exit();
            }
        }

        // Ajouter l'échange
        $success = $etudiantModel->ajouterEchange(
            $tacheId,
            $commentaire,
            $fichier,
            $typeAuteur,
            $idAuteur
        );

        if ($success) {
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Votre réponse a été enregistrée avec succès'
                }).then(() => {
                    window.location.href = '../portail/student';
                });
            </script>";
        } else {
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
    } catch (Exception $e) {
        // Log l'erreur pour l'administrateur
        error_log("Erreur lors de l'ajout d'un échange: " . $e->getMessage());
        
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