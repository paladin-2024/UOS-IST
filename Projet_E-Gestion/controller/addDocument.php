<?php
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Structure.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $idCategorie = $_POST['id_categorie'] ?? '';
    $userId = $_SESSION['id'] ?? null;
    $preUploaded = isset($_POST['pre_uploaded_file']) ? trim($_POST['pre_uploaded_file']) : '';

    if (!$userId) {
        header("Location: ../document/doc.prive.add");
        exit();
    }

    if (empty($titre) || empty($idCategorie) || (!isset($_FILES['fichier']) && $preUploaded === '')) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../document/doc.prive.add';
            });
        </script>";
        exit();
    }

    // Utiliser soit un fichier scannÃ© prÃ©alablement envoyÃ©, soit le fichier du formulaire
    if ($preUploaded !== '') {
        $newFileName = basename($preUploaded);
    } else {
        $file = $_FILES['fichier'];
        $uploadDir = '../uploads/';
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = 'DOC_PRIV_' . date('Ymd_His') . '_' . $userId . '.' . $fileExtension;
        $filePath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Upload du document \\u00e9chou\\u00e9'
                }).then(() => {
                    window.location.href = '../document/doc.prive.add';
                });
            </script>";
            exit();
        }
    }

    $documentModel = new Structure();

    try {
        $result = $documentModel->addDocument($titre, $description, $newFileName, $userId, $idCategorie);

        if ($result) {
            // Associer automatiquement le propriétaire au document (user_document)
            $docId = $documentModel->getPrivateDocumentIdByFilename($newFileName, $userId);
            if ($docId) {
                @$documentModel->addUserToDocument($userId, $docId);
            }
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succ\\u00e8s',
                    text: 'Le document a \\u00e9t\\u00e9 ajout\\u00e9 avec succ\\u00e8s.'
                }).then(() => {
                    window.location.href = '../document/doc.prive.add';
                });
            </script>";
        } else {
            throw new Exception('Erreur lors de l\'ajout du document');
        }
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout du document: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../document/doc.prive.add';
            });
        </script>";
    }
} else {
    header("Location: ../document/doc.prive.add");
    exit();
}
?>

