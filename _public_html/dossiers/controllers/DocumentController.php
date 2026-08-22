<?php
/**
 * Contrôleur de gestion des documents - Module Dossiers
 */

$model = new DossierModel();
$studentId = $_SESSION['dossier_student_id'] ?? null;
$anneeAcadId = $_SESSION['dossier_student_annee_acad'] ?? null;
$cycle = $_SESSION['dossier_student_cycle'] ?? null;

switch ($action) {
    case 'upload':
        $dossier = $model->getOrCreateDossier($studentId, $anneeAcadId);
        $typeDocId = intval($_GET['type'] ?? 0);
        $typeDoc = null;

        if ($typeDocId > 0) {
            $requiredDocs = $model->getRequiredDocuments($cycle);
            foreach ($requiredDocs as $doc) {
                if ($doc['id'] == $typeDocId) {
                    $typeDoc = $doc;
                    break;
                }
            }
        }

        if (!$typeDoc) {
            $_SESSION['dossier_error'] = 'Type de document invalide.';
            redirect('index.php?action=dashboard');
        }

        // Vérifier si le dossier est encore modifiable
        if (in_array($dossier['statut'], ['soumis', 'valide'])) {
            $_SESSION['dossier_error'] = 'Votre dossier a déjà été soumis et ne peut plus être modifié.';
            redirect('index.php?action=dashboard');
        }

        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_error']);
        require 'views/upload.php';
        break;

    case 'upload_process':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=dashboard');
        }

        $dossierId = intval($_POST['dossier_id'] ?? 0);
        $typeDocId = intval($_POST['type_document_id'] ?? 0);

        $dossier = $model->getDossierById($dossierId);
        if (!$dossier || $dossier['etudiant_idetudiant'] != $studentId) {
            $_SESSION['dossier_error'] = 'Dossier introuvable.';
            redirect('index.php?action=dashboard');
        }

        if (in_array($dossier['statut'], ['soumis', 'valide'])) {
            $_SESSION['dossier_error'] = 'Le dossier ne peut plus être modifié.';
            redirect('index.php?action=dashboard');
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['dossier_error'] = 'Erreur lors de l\'upload du fichier.';
            redirect('index.php?action=upload&type=' . $typeDocId);
        }

        $file = $_FILES['document'];

        // Vérifier la taille
        if ($file['size'] > MAX_FILE_SIZE) {
            $_SESSION['dossier_error'] = 'Le fichier dépasse la taille maximale autorisée (' . formatFileSize(MAX_FILE_SIZE) . ').';
            redirect('index.php?action=upload&type=' . $typeDocId);
        }

        // Vérifier l'extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            $_SESSION['dossier_error'] = 'Format de fichier non autorisé. Formats acceptés : ' . implode(', ', ALLOWED_EXTENSIONS);
            redirect('index.php?action=upload&type=' . $typeDocId);
        }

        // Vérifier le type MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            $_SESSION['dossier_error'] = 'Type de fichier non autorisé.';
            redirect('index.php?action=upload&type=' . $typeDocId);
        }

        // Générer un nom unique
        $matricule = $_SESSION['dossier_student_matricule'];
        $nomStocke = $matricule . '_' . $typeDocId . '_' . time() . '.' . $ext;
        $cheminComplet = UPLOAD_DIR . $nomStocke;

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $cheminComplet)) {
            $_SESSION['dossier_error'] = 'Erreur lors de l\'enregistrement du fichier.';
            redirect('index.php?action=upload&type=' . $typeDocId);
        }

        // Enregistrer en base
        $docId = $model->uploadDocument(
            $dossierId, $typeDocId, $file['name'], $nomStocke, $cheminComplet, $file['size'], $mimeType
        );

        if ($docId) {
            $model->updateCompletionPercentage($dossierId);
            $model->logAction($dossierId, $docId, 'etudiant', $studentId, 'UPLOAD', 
                'Upload du document: ' . $file['name']);
            $_SESSION['dossier_success'] = 'Document uploadé avec succès.';
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de l\'enregistrement du document.';
            if (file_exists($cheminComplet)) unlink($cheminComplet);
        }

        redirect('index.php?action=dashboard');
        break;

    case 'view_document':
        $docId = intval($_GET['id'] ?? 0);
        $doc = $model->getDocument($docId);

        if (!$doc) {
            $_SESSION['dossier_error'] = 'Document introuvable.';
            redirect('index.php?action=dashboard');
        }

        // Admin peut voir n'importe quel document, étudiant seulement les siens
        if (!isAdmin() && $doc['etudiant_idetudiant'] != $studentId) {
            $_SESSION['dossier_error'] = 'Document introuvable.';
            redirect('index.php?action=dashboard');
        }

        if (file_exists($doc['chemin_fichier'])) {
            header('Content-Type: ' . $doc['type_mime']);
            header('Content-Disposition: inline; filename="' . $doc['nom_fichier_original'] . '"');
            header('Content-Length: ' . filesize($doc['chemin_fichier']));
            readfile($doc['chemin_fichier']);
            exit;
        }

        $_SESSION['dossier_error'] = 'Fichier introuvable sur le serveur.';
        redirect('index.php?action=dashboard');
        break;

    case 'delete_document':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=dashboard');
        }

        $docId = intval($_POST['document_id'] ?? 0);
        $doc = $model->getDocument($docId);

        if (!$doc || $doc['etudiant_idetudiant'] != $studentId) {
            $_SESSION['dossier_error'] = 'Document introuvable.';
            redirect('index.php?action=dashboard');
        }

        // Vérifier que le document n'est pas déjà validé
        if ($doc['statut'] === 'valide') {
            $_SESSION['dossier_error'] = 'Impossible de supprimer un document validé.';
            redirect('index.php?action=dashboard');
        }

        $deleted = $model->deleteDocument($docId);
        if ($deleted) {
            if (file_exists($deleted['chemin_fichier'])) {
                unlink($deleted['chemin_fichier']);
            }
            $model->updateCompletionPercentage($deleted['dossier_id']);
            $model->logAction($deleted['dossier_id'], null, 'etudiant', $studentId, 'SUPPRESSION', 
                'Suppression du document ID: ' . $docId);
            $_SESSION['dossier_success'] = 'Document supprimé avec succès.';
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de la suppression.';
        }

        redirect('index.php?action=dashboard');
        break;
}
