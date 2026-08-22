<?php
require_once dirname(__DIR__) . '/../utils/Security.php';

if (!isset($_SESSION['id']) || !isset($_SESSION["login"])) {
  header("Location:" . BASE_PATH . "accueil");
  exit;
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location:" . BASE_PATH . "accueil?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!headers_sent()) {
  header('Content-Type: text/html; charset=utf-8');
}

date_default_timezone_set('Africa/Lubumbashi');
$img = $_SESSION['image'];
$nomU = $_SESSION['nom'];
$idUser = $_SESSION['id'];

// Récupérer tous les rôles de l'utilisateur et le rôle principal
$connexion = Connexion::getInstance()->getPDO();
$query = "SELECT ur.idRole, r.nomRole, ur.isPrincipal FROM t_user_roles ur INNER JOIN t_roles r ON ur.idRole = r.idRole WHERE ur.idUser = :idUser";
$stmt = $connexion->prepare($query);
$stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
$stmt->execute();
$userRolesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extraire les idRole pour les menus
$userRoles = array_column($userRolesData, 'idRole');

// Rôle principal (celui avec isPrincipal=1, ou le premier si aucun marqué)
$nomRole = '';
foreach ($userRolesData as $role) {
    if ($role['isPrincipal']) {
        $nomRole = $role['nomRole'];
        break;
    }
}
if (empty($nomRole) && !empty($userRolesData)) {
    $nomRole = $userRolesData[0]['nomRole'];
}

$module = new Module();
$navMenu = $module->generateNavMenu($userRoles);

$universite = new Universite();
$config = $universite->getConfigurationUniversite();
?>

<!DOCTYPE html>
<html lang="fr">

<!-- Head and importation des packages -->
<?php include("head.php"); ?>

<body>
  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="index" class="logo d-flex align-items-center">
      <?php if (!empty($config['logo'])): ?>
          <img src="<?= htmlspecialchars($config['logo']) ?>" alt="<?= htmlspecialchars($config['sigle']) ?> Logo" class="rounded-circle">
      <?php endif; ?>
        <span class="d-none d-lg-block"><?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?></span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        <li class="nav-item dropdown pe-3">
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="uploads/<?= $img ?>" width="40" height="40" alt=" " class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?= mb_strtoupper($nomU) ?></span>
          </a><!-- End Profile Iamge Icon -->

          <!-- Menu Administrateurs -->
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <span><?= mb_strtoupper($nomU) ?></span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li> 
            <li>
              <a class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalModifNom">
                <i class="bi bi-person"></i>
                <span>Modifier Nom</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalModifLog">
                <i class="bi bi-key"></i>
                <span>Modifier Login</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalModifPass">
                <i class="bi bi-lock"></i>
                <span>Modifier PassWord</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalModifPhoto">
                <i class="bi bi-image-alt"></i>
                <span>Modifier Photo</span>
              </a>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="index.php?view=deconnexion">
                <i class="bi bi-box-arrow-right"></i>
                <span>Déconnexion</span>
              </a>
            </li>

          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->
      </ul>
    </nav><!-- End Icons Navigation -->

  </header><!-- End Header -->

  <!-- ======= Menu principal ======= -->
  <?php echo $navMenu; ?>

  <!-- ======= Spinner ======= -->
  <div id="spinner">
    <img src="assets/img/spinok.gif" alt="Chargement...">
  </div>

  <!-- Modal modifier PassWord -->
  <div class="modal fade" id="modalModifPass" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Modifier votre mot de passe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- <div class="tab-pane fade pt-3"> -->
          <!-- Change profil Form -->
          <form method="post" action="controller/profiles.php" class="tab-pane needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo AppSecurity::csrfToken(); ?>">

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="password" class="form-label">Mot de passe actuel</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="password" name="password" class="form-control" id="currentPassword" required>
                  <div class="invalid-feedback">Veillez saisir le mot de passe !</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="newpassword" class="form-label">Nouveau mot de passe</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="password" name="newpassword" class="form-control" id="newPassword" required>
                  <div class="invalid-feedback">Veillez saisir votre nouveau mot de passe !</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="renewpassword" class="form-label">Resaisissez le nouveau Mot de passe</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="password" name="renewpassword" class="form-control" id="renewPassword" required>
                  <div class="invalid-feedback">Veillez resaisir votre nouveau mot de passe !</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <div class="form-check iconePass">
                  <input class="form-check-input" type="checkbox" onclick="changer()" name="remember" id="rememberMe">
                  <label class="form-check-label" for="rememberMe">Afficher mot de passe</label>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btnModClose" data-bs-dismiss="modal">Fermer</button>
              <button type="submit" name="modifPass" class="btnModSave">Enregistrer</button>
            </div>
          </form><!-- End Change Password Form -->
        </div>

      </div>
    </div>
  </div><!-- End Vertically centered Modal MODIFIER PASSWORD-->

  <!-- MODAL MODIFIER Nom -->
  <div class="modal fade" id="modalModifNom" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Modifier votre Nom</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" action="controller/profiles.php" class="tab-pane needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo AppSecurity::csrfToken(); ?>">

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="newlogin" class="form-label">Nouveau Nom</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-person"></i></span>
                  <input type="text" name="newNom" class="form-control" id="newLogin" required>
                  <div class="invalid-feedback">Veillez saisir votre nouveau nom !</div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btnModClose" data-bs-dismiss="modal">Fermer</button>
              <button type="submit" name="modifNom" class="btnModSave">Enregistrer</button>
            </div>
          </form><!-- End Change Password Form -->
        </div>

      </div>
    </div>
  </div><!-- End Vertically centered Modal MODIFIER NOM-->

  <!-- MODAL MODIFIER LOGIN -->
  <div class="modal fade" id="modalModifLog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Modifier votre login</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" action="controller/profiles.php" class="tab-pane needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo AppSecurity::csrfToken(); ?>">

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="login" class="form-label">Login actuel</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="text" name="login" class="form-control" id="currentLogin2" required>
                  <div class="invalid-feedback">Veillez saisir votre Login !</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="newlogin" class="form-label">Nouveau Login</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="text" name="newlogin" class="form-control" id="newLogin2" required>
                  <div class="invalid-feedback">Veillez saisir votre nouveau Login !</div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="renewlogin" class="form-label">Resaisissez le nouveau Login</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="text" name="renewlogin" class="form-control" id="renewLogin" required>
                  <div class="invalid-feedback">Veillez resaisir votre nouveau Login !</div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btnModClose" data-bs-dismiss="modal">Fermer</button>
              <button type="submit" name="modifLog" class="btnModSave">Enregistrer</button>
            </div>
          </form><!-- End Change Password Form -->
        </div>

      </div>
    </div>
  </div><!-- End Vertically centered Modal MODIFIER LOGIN-->

  <!-- MODAL MODIFIER LOGIN -->
  <div class="modal fade" id="modalModifPhoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Modifier votre photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form method="post" action="controller/profiles.php" enctype="multipart/form-data" class="tab-pane needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo AppSecurity::csrfToken(); ?>">

            <div class="row mb-3">
              <div class="col-md-12 col-lg-12">
                <label for="login" class="form-label">Choisissez une photo</label>
                <div class="input-group has-validation">
                  <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-lock"></i></span>
                  <input type="file" name="photo" class="form-control" id="currentLogin" required>
                  <div class="invalid-feedback">Veillez choisir votre photo SVP!</div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btnModClose" data-bs-dismiss="modal">Fermer</button>
              <button type="submit" name="modifImage" class="btnModSave">Enregistrer</button>
            </div>
          </form><!-- End Change Password Form -->
        </div>

      </div>
    </div>
  </div><!-- End Vertically centered Modal MODIFIER LOGIN-->
