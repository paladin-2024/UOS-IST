<?php
/**
 * Point d'entrée du module Dossiers Étudiants
 * Routeur principal - gestion des actions via $_GET['action']
 */

require_once 'config/config.php';
require_once 'models/DossierModel.php';

$routeAction = isset($_GET['action']) ? $_GET['action'] : 'login';

switch ($routeAction) {
    // ── Routes étudiants ──
    case 'login':
        $action = 'login';
        require 'controllers/AuthController.php';
        break;

    case 'authenticate':
        $action = 'authenticate';
        require 'controllers/AuthController.php';
        break;

    case 'admin_login':
        $action = 'admin_login';
        require 'controllers/AuthController.php';
        break;

    case 'admin_authenticate':
        $action = 'admin_authenticate';
        require 'controllers/AuthController.php';
        break;

    case 'logout':
        unset(
            $_SESSION['dossier_student_id'],
            $_SESSION['dossier_student_matricule'],
            $_SESSION['dossier_student_name'],
            $_SESSION['dossier_student_photo'],
            $_SESSION['dossier_student_cycle'],
            $_SESSION['dossier_student_promotion'],
            $_SESSION['dossier_student_orientation'],
            $_SESSION['dossier_student_section'],
            $_SESSION['dossier_student_annee_acad'],
            $_SESSION['dossier_student_annee_designation'],
            $_SESSION['dossier_admin_id'],
            $_SESSION['dossier_admin_name'],
            $_SESSION['dossier_admin_role'],
            $_SESSION['dossier_admin_photo'],
            $_SESSION['dossier_universite_nom'],
            $_SESSION['dossier_universite_sigle'],
            $_SESSION['dossier_universite_logo']
        );
        redirect('index.php?action=login');
        break;

    case 'dashboard':
        requireLogin();
        $action = 'dashboard';
        require 'controllers/DashboardController.php';
        break;

    case 'upload':
        requireLogin();
        $action = 'upload';
        require 'controllers/DocumentController.php';
        break;

    case 'upload_process':
        requireLogin();
        $action = 'upload_process';
        require 'controllers/DocumentController.php';
        break;

    case 'view_document':
        if (!isLoggedIn() && !isAdmin()) {
            redirect('index.php?action=login');
        }
        $action = 'view_document';
        require 'controllers/DocumentController.php';
        break;

    case 'delete_document':
        requireLogin();
        $action = 'delete_document';
        require 'controllers/DocumentController.php';
        break;

    case 'submit_dossier':
        requireLogin();
        $action = 'submit_dossier';
        require 'controllers/DashboardController.php';
        break;

    case 'upload_list':
        requireLogin();
        $action = 'upload_list';
        require 'controllers/DashboardController.php';
        break;

    case 'mes_documents':
        requireLogin();
        $action = 'mes_documents';
        require 'controllers/DashboardController.php';
        break;

    // ── Routes administrateur ──
    case 'admin':
        requireAdmin();
        $action = 'admin';
        require 'controllers/AdminController.php';
        break;

    case 'admin_list':
        requireAdmin();
        $action = 'admin_list';
        require 'controllers/AdminController.php';
        break;

    case 'admin_list_ajax':
        requireAdmin();
        $action = 'admin_list_ajax';
        require 'controllers/AdminController.php';
        break;

    case 'admin_detail':
        requireAdmin();
        $action = 'admin_detail';
        require 'controllers/AdminController.php';
        break;

    case 'admin_validate':
        requireAdmin();
        $action = 'admin_validate';
        require 'controllers/AdminController.php';
        break;

    case 'admin_validate_doc':
        requireAdmin();
        $action = 'admin_validate_doc';
        require 'controllers/AdminController.php';
        break;

    case 'admin_download':
        requireAdmin();
        $action = 'admin_download';
        require 'controllers/AdminController.php';
        break;

    case 'admin_export':
        requireAdmin();
        $action = 'admin_export';
        require 'controllers/AdminController.php';
        break;

    case 'admin_validate_docs_bulk':
        requireAdmin();
        $action = 'admin_validate_docs_bulk';
        require 'controllers/AdminController.php';
        break;

    case 'admin_export_excel':
        requireAdmin();
        require 'controllers/ExportExcelController.php';
        break;

    case 'admin_types_documents':
        requireAdmin();
        $action = 'admin_types_documents';
        require 'controllers/AdminController.php';
        break;

    case 'admin_type_document_save':
        requireAdmin();
        $action = 'admin_type_document_save';
        require 'controllers/AdminController.php';
        break;

    case 'admin_type_document_delete':
        requireAdmin();
        $action = 'admin_type_document_delete';
        require 'controllers/AdminController.php';
        break;

    default:
        redirect('index.php?action=login');
        break;
}
