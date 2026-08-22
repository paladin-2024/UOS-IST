<?php
/**
 * Contrôleur d'administration - Module Dossiers
 */

$model = new DossierModel();
$adminId = $_SESSION['dossier_admin_id'];

switch ($action) {
    case 'admin':
        $annees = $model->getAnneesAcademiques();
        $defaultAnnee = $model->getAnneeAcadActiveId() ?: ($annees[0]['idannee_acad'] ?? 0);
        $anneeAcadId = intval($_GET['annee'] ?? $defaultAnnee);
        $sectionId = intval($_GET['section'] ?? 0) ?: null;

        $sections = $model->getSections($anneeAcadId);
        $stats = $model->getDossierStats($anneeAcadId, $sectionId);

        require 'views/admin/dashboard.php';
        break;

    case 'admin_list':
        $perPage = 30;
        $annees = $model->getAnneesAcademiques();
        $defaultAnnee = $model->getAnneeAcadActiveId() ?: ($annees[0]['idannee_acad'] ?? 0);
        $anneeAcadId = intval($_GET['annee'] ?? $defaultAnnee);
        $sectionId = intval($_GET['section'] ?? 0) ?: null;
        $orientationId = intval($_GET['orientation'] ?? 0) ?: null;
        $statut = $_GET['statut'] ?? null;

        $sections = $model->getSections($anneeAcadId);
        $orientations = $sectionId ? $model->getOrientationsBySection($sectionId, $anneeAcadId) : [];
        $totalEtudiants = $model->countFinalistesWithDossiers($anneeAcadId, $sectionId, $orientationId, $statut);
        $etudiants = $model->getFinalistesWithDossiers($anneeAcadId, $sectionId, $orientationId, $statut, $perPage, 0);

        require 'views/admin/list.php';
        break;

    case 'admin_list_ajax':
        header('Content-Type: application/json; charset=utf-8');
        $perPage = 30;
        $anneeAcadId = intval($_GET['annee'] ?? 0);
        $sectionId = intval($_GET['section'] ?? 0) ?: null;
        $orientationId = intval($_GET['orientation'] ?? 0) ?: null;
        $statut = $_GET['statut'] ?? null;
        $offset = intval($_GET['offset'] ?? 0);

        $etudiants = $model->getFinalistesWithDossiers($anneeAcadId, $sectionId, $orientationId, $statut, $perPage, $offset);
        $total = $model->countFinalistesWithDossiers($anneeAcadId, $sectionId, $orientationId, $statut);

        echo json_encode([
            'data' => $etudiants,
            'total' => $total,
            'offset' => $offset,
            'hasMore' => ($offset + count($etudiants)) < $total
        ]);
        exit;

    case 'admin_detail':
        $etudiantId = intval($_GET['etudiant'] ?? 0);
        $anneeAcadId = intval($_GET['annee'] ?? 0);

        if (!$etudiantId || !$anneeAcadId) {
            redirect('index.php?action=admin');
        }

        $dossier = $model->getDossierByEtudiant($etudiantId, $anneeAcadId);
        if (!$dossier) {
            // Créer le dossier si inexistant
            $dossier = $model->getOrCreateDossier($etudiantId, $anneeAcadId);
            $dossier = $model->getDossierById($dossier['id']);
        }

        $documents = $model->getDocumentsWithStatus($dossier['id'], $dossier['cycle']);
        $journal = $model->getJournal($dossier['id']);

        $success = $_SESSION['dossier_success'] ?? null;
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_success'], $_SESSION['dossier_error']);

        require 'views/admin/etudiant_detail.php';
        break;

    case 'admin_validate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=admin');
        }

        $dossierId = intval($_POST['dossier_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $commentaire = trim($_POST['commentaire'] ?? '');
        $etudiantId = intval($_POST['etudiant_id'] ?? 0);
        $anneeAcadId = intval($_POST['annee_acad_id'] ?? 0);

        if (!in_array($statut, ['valide', 'rejete', 'incomplet'])) {
            $_SESSION['dossier_error'] = 'Statut invalide.';
            redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        }

        if ($model->updateDossierStatut($dossierId, $statut, $commentaire, $adminId)) {
            $model->logAction($dossierId, null, 'admin', $adminId, 'VALIDATION_DOSSIER', 
                "Statut: $statut - $commentaire");
            $_SESSION['dossier_success'] = 'Dossier mis à jour avec succès.';
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de la mise à jour.';
        }

        redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        break;

    case 'admin_validate_doc':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=admin');
        }

        $documentId = intval($_POST['document_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $commentaire = trim($_POST['commentaire'] ?? '');
        $etudiantId = intval($_POST['etudiant_id'] ?? 0);
        $anneeAcadId = intval($_POST['annee_acad_id'] ?? 0);

        if (!in_array($statut, ['valide', 'rejete'])) {
            $_SESSION['dossier_error'] = 'Statut invalide.';
            redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        }

        $doc = $model->getDocument($documentId);
        if (!$doc) {
            $_SESSION['dossier_error'] = 'Document introuvable.';
            redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        }

        if ($model->validateDocument($documentId, $statut, $commentaire, $adminId)) {
            $model->updateCompletionPercentage($doc['dossier_id']);
            $model->logAction($doc['dossier_id'], $documentId, 'admin', $adminId, 'VALIDATION_DOCUMENT', 
                "Document: {$doc['type_designation']} - Statut: $statut - $commentaire");
            $_SESSION['dossier_success'] = 'Document mis à jour avec succès.';
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de la validation.';
        }

        redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        break;

    case 'admin_validate_docs_bulk':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=admin');
        }

        $documentIds = $_POST['document_ids'] ?? [];
        $statut = $_POST['statut'] ?? '';
        $commentaire = trim($_POST['commentaire'] ?? '');
        $etudiantId = intval($_POST['etudiant_id'] ?? 0);
        $anneeAcadId = intval($_POST['annee_acad_id'] ?? 0);

        if (!in_array($statut, ['valide', 'rejete']) || empty($documentIds)) {
            $_SESSION['dossier_error'] = 'Paramètres invalides.';
            redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        }

        $ids = array_map('intval', $documentIds);
        $firstDoc = $model->getDocument($ids[0]);
        $dossierId = $firstDoc ? $firstDoc['dossier_id'] : 0;

        $count = $model->validateDocumentsBulk($ids, $statut, $commentaire, $adminId);
        if ($count > 0 && $dossierId) {
            $model->updateCompletionPercentage($dossierId);
            $statutLabel = $statut === 'valide' ? 'validé(s)' : 'rejeté(s)';
            $model->logAction($dossierId, null, 'admin', $adminId, 'VALIDATION_DOCUMENTS_BULK',
                "$count document(s) $statutLabel - $commentaire");
            $_SESSION['dossier_success'] = "$count document(s) $statutLabel avec succès.";
        } else {
            $_SESSION['dossier_error'] = 'Erreur lors de la validation groupée.';
        }

        redirect("index.php?action=admin_detail&etudiant=$etudiantId&annee=$anneeAcadId");
        break;

    case 'admin_download':
        $docId = intval($_GET['id'] ?? 0);
        $doc = $model->getDocument($docId);

        if ($doc && file_exists($doc['chemin_fichier'])) {
            header('Content-Type: ' . $doc['type_mime']);
            header('Content-Disposition: inline; filename="' . $doc['nom_fichier_original'] . '"');
            header('Content-Length: ' . filesize($doc['chemin_fichier']));
            readfile($doc['chemin_fichier']);
            exit;
        }

        $_SESSION['dossier_error'] = 'Fichier introuvable.';
        redirect('index.php?action=admin');
        break;

    case 'admin_export':
        $anneeAcadId = intval($_GET['annee'] ?? 0);
        $sectionId = intval($_GET['section'] ?? 0) ?: null;

        $etudiants = $model->getFinalistesWithDossiers($anneeAcadId, $sectionId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_dossiers_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($output, ['Matricule', 'Nom', 'Promotion', 'Cycle', 'Orientation', 'Section', 
                          'Statut Dossier', 'Complétion (%)', 'Date Soumission'], ';');

        foreach ($etudiants as $etu) {
            fputcsv($output, [
                $etu['matricule'],
                $etu['noms'],
                $etu['designationPromotion'],
                $etu['cycle'],
                $etu['designationOrientation'],
                $etu['designationSection'],
                $etu['dossier_statut'] ?? 'Non commencé',
                $etu['pourcentage_completion'] ?? 0,
                $etu['date_soumission'] ?? '-'
            ], ';');
        }

        fclose($output);
        exit;
        break;

    case 'admin_types_documents':
        $types = $model->getAllTypesDocuments();
        $success = $_SESSION['dossier_success'] ?? null;
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_success'], $_SESSION['dossier_error']);
        require 'views/admin/types_documents.php';
        break;

    case 'admin_type_document_save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=admin_types_documents');
        }

        $id = intval($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $designation = trim($_POST['designation'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cycles = $_POST['cycle_requis'] ?? [];
        $cycleRequis = implode(',', $cycles);
        $estObligatoire = intval($_POST['est_obligatoire'] ?? 0);
        $ordreAffichage = intval($_POST['ordre_affichage'] ?? 0);
        $estActif = intval($_POST['est_actif'] ?? 1);

        if (empty($code) || empty($designation) || empty($cycleRequis)) {
            $_SESSION['dossier_error'] = 'Veuillez remplir tous les champs obligatoires (Code, Désignation, Cycle).';
            redirect('index.php?action=admin_types_documents');
        }

        $data = [
            'code' => $code,
            'designation' => $designation,
            'description' => $description,
            'cycle_requis' => $cycleRequis,
            'est_obligatoire' => $estObligatoire,
            'ordre_affichage' => $ordreAffichage,
            'est_actif' => $estActif
        ];

        if ($id > 0) {
            if ($model->updateTypeDocument($id, $data)) {
                $_SESSION['dossier_success'] = 'Type de document modifié avec succès.';
            } else {
                $_SESSION['dossier_error'] = 'Erreur lors de la modification. Le code existe peut-être déjà.';
            }
        } else {
            if ($model->createTypeDocument($data)) {
                $_SESSION['dossier_success'] = 'Type de document créé avec succès.';
            } else {
                $_SESSION['dossier_error'] = 'Erreur lors de la création. Le code existe peut-être déjà.';
            }
        }

        redirect('index.php?action=admin_types_documents');
        break;

    case 'admin_type_document_delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=admin_types_documents');
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $result = $model->deleteTypeDocument($id);
            if ($result === true) {
                $_SESSION['dossier_success'] = 'Type de document supprimé avec succès.';
            } elseif (is_array($result) && isset($result['error'])) {
                $_SESSION['dossier_error'] = $result['error'];
            } else {
                $_SESSION['dossier_error'] = 'Erreur lors de la suppression.';
            }
        }

        redirect('index.php?action=admin_types_documents');
        break;
}
