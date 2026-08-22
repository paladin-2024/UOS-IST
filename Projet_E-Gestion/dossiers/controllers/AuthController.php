<?php
/**
 * Contrôleur d'authentification - Module Dossiers
 * Authentification par matricule (étudiants) ou login/mot de passe (admin)
 */

$model = new DossierModel();

switch ($action) {
    case 'login':
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_error']);
        $loginTab = $_GET['tab'] ?? 'student';
        require 'views/login.php';
        break;

    case 'authenticate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=login');
        }

        $matricule = trim($_POST['matricule'] ?? '');

        if (empty($matricule)) {
            $_SESSION['dossier_error'] = 'Veuillez saisir votre matricule.';
            redirect('index.php?action=login');
        }

        $result = $model->authenticateStudent($matricule);

        if ($result === false) {
            $_SESSION['dossier_error'] = 'Matricule introuvable ou compte inactif.';
            redirect('index.php?action=login');
        }

        if (is_array($result) && isset($result['error']) && $result['error'] === 'not_finaliste') {
            $_SESSION['dossier_error'] = 'Accès réservé aux étudiants finalistes. Votre promotion n\'est pas une classe terminale.';
            redirect('index.php?action=login');
        }

        // Connexion réussie
        $_SESSION['dossier_student_id'] = $result['idetudiant'];
        $_SESSION['dossier_student_matricule'] = $result['matricule'];
        $_SESSION['dossier_student_name'] = $result['noms'];
        $_SESSION['dossier_student_photo'] = $result['photo'];
        $_SESSION['dossier_student_cycle'] = $result['cycle'];
        $_SESSION['dossier_student_promotion'] = $result['designationPromotion'];
        $_SESSION['dossier_student_orientation'] = $result['designationOrientation'];
        $_SESSION['dossier_student_section'] = $result['designationSection'];
        $_SESSION['dossier_student_annee_acad'] = $result['annee_acad_idannee_acad'];
        $_SESSION['dossier_student_annee_designation'] = $result['annee_designation'];

        // Charger config université en session
        if (empty($_SESSION['dossier_universite_nom'])) {
            $configUniv = $model->getConfigurationUniversite();
            $_SESSION['dossier_universite_nom'] = $configUniv['nom'] ?? 'Université';
            $_SESSION['dossier_universite_sigle'] = $configUniv['sigle'] ?? '';
            $_SESSION['dossier_universite_logo'] = $configUniv['logo'] ?? '';
        }

        // Log de connexion
        $dossier = $model->getOrCreateDossier($result['idetudiant'], $result['annee_acad_idannee_acad']);
        if ($dossier) {
            $model->logAction($dossier['id'], null, 'etudiant', $result['idetudiant'], 'CONNEXION', 'Connexion au module dossiers');
        }

        redirect('index.php?action=dashboard');
        break;

    case 'admin_login':
        $error = $_SESSION['dossier_error'] ?? null;
        unset($_SESSION['dossier_error']);
        $loginTab = 'admin';
        require 'views/login.php';
        break;

    case 'admin_authenticate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?action=login&tab=admin');
        }

        $login = trim($_POST['login'] ?? '');
        $password = $_POST['pwd'] ?? '';

        if (empty($login) || empty($password)) {
            $_SESSION['dossier_error'] = 'Veuillez saisir votre login et mot de passe.';
            redirect('index.php?action=login&tab=admin');
        }

        require_once APP_ROOT . '/models/SuperUser.php';
        $userManager = new SuperUser();
        $user = $userManager->seConnecter($login, $password);

        if ($user && $user->etatUser == 1) {
            $_SESSION['dossier_admin_id'] = $user->idUser;
            $_SESSION['dossier_admin_name'] = $user->nomUser;
            $_SESSION['dossier_admin_role'] = $user->nomRole;
            $_SESSION['dossier_admin_photo'] = $user->imageUser ?? '';
            // Charger config université en session
            $configUniv = $model->getConfigurationUniversite();
            $_SESSION['dossier_universite_nom'] = $configUniv['nom'] ?? 'Université';
            $_SESSION['dossier_universite_sigle'] = $configUniv['sigle'] ?? '';
            $_SESSION['dossier_universite_logo'] = $configUniv['logo'] ?? '';
            redirect('index.php?action=admin');
        } else {
            $_SESSION['dossier_error'] = 'Login ou mot de passe incorrect ou compte inactif.';
            redirect('index.php?action=login&tab=admin');
        }
        break;
}
