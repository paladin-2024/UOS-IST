<?php
/**
 * Contrôleur du tableau de bord étudiant - Module Dossiers
 */

$model = new DossierModel();
$studentId = $_SESSION['dossier_student_id'];
$anneeAcadId = $_SESSION['dossier_student_annee_acad'];
$cycle = $_SESSION['dossier_student_cycle'];

switch ($action) {
    case 'dashboard':
        $dossier = $model->getOrCreateDossier($studentId, $anneeAcadId);

        if (!$dossier || !is_array($dossier)) {
            $_SESSION['dossier_error'] = 'Impossible de charger votre dossier. Veuillez contacter l\'administration (tables non initialisées).';
            redirect('index.php?action=login');
            break;
        }

        $documents = $model->getDocumentsWithStatus($dossier['id'], $cycle);
        $model->updateCompletionPercentage($dossier['id']);
        $dossier = $model->getDossierById($dossier['id']) ?: $dossier;

        $success = $_SESSION['dossier_success'] ?? null;
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_success'], $_SESSION['dossier_error']);

        require 'views/dashboard.php';
        break;

    case 'submit_dossier':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=dashboard');
        }

        $dossierId = intval($_POST['dossier_id'] ?? 0);
        $dossier = $model->getDossierById($dossierId);

        if (!$dossier || $dossier['etudiant_idetudiant'] != $studentId) {
            $_SESSION['dossier_error'] = 'Dossier introuvable.';
            redirect('index.php?action=dashboard');
        }

        // Vérifier que tous les documents obligatoires sont uploadés
        $documents = $model->getDocumentsWithStatus($dossierId, $cycle);
        $missingDocs = [];
        foreach ($documents as $doc) {
            if ($doc['est_obligatoire'] && !$doc['upload_id']) {
                $missingDocs[] = $doc['designation'];
            }
        }

        if (!empty($missingDocs)) {
            $_SESSION['dossier_error'] = 'Documents manquants : ' . implode(', ', $missingDocs);
            redirect('index.php?action=dashboard');
        }

        if ($model->submitDossier($dossierId)) {
            $model->logAction($dossierId, null, 'etudiant', $studentId, 'SOUMISSION', 'Dossier soumis pour validation');
            $_SESSION['dossier_success'] = 'Votre dossier a été soumis avec succès pour validation.';
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de la soumission du dossier.';
        }

        redirect('index.php?action=dashboard');
        break;

    case 'upload_list':
        $dossier = $model->getOrCreateDossier($studentId, $anneeAcadId);

        if (!$dossier || !is_array($dossier)) {
            $_SESSION['dossier_error'] = 'Impossible de charger votre dossier.';
            redirect('index.php?action=login');
            break;
        }

        $documents = $model->getDocumentsWithStatus($dossier['id'], $cycle);
        $model->updateCompletionPercentage($dossier['id']);
        $dossier = $model->getDossierById($dossier['id']) ?: $dossier;

        $success = $_SESSION['dossier_success'] ?? null;
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_success'], $_SESSION['dossier_error']);

        require 'views/upload_list.php';
        break;

    case 'mes_documents':
        $dossier = $model->getOrCreateDossier($studentId, $anneeAcadId);

        if (!$dossier || !is_array($dossier)) {
            $_SESSION['dossier_error'] = 'Impossible de charger votre dossier.';
            redirect('index.php?action=login');
            break;
        }

        $documents = $model->getDocumentsWithStatus($dossier['id'], $cycle);

        $success = $_SESSION['dossier_success'] ?? null;
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_success'], $_SESSION['dossier_error']);

        require 'views/mes_documents.php';
        break;
}
