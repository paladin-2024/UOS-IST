<?php
// Pas besoin d'inclure le header standard car nous voulons une page spéciale
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: index");
    exit();
}

$universite = new Universite();

$superUser = new SuperUser();

$configUniversitee = $universite->getConfigurationUniversite();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Changement de mot de passe - <?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta content="" name="description">
    <meta content="" name="keywords">
    
    <?php if (!empty($configUniversitee['logo'])): ?>
        <!-- Favicons --> 
	<link href="./<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="icon">
	<link href="./<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Correction du chemin des icônes Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Template Main CSS File -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>
    <main>
        <div class="container">
            <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-6 col-md-8 d-flex flex-column align-items-center justify-content-center">
                            <div class="d-flex justify-content-center py-4">
                                <a href="index.php" class="logo d-flex align-items-center w-auto">
                                <?php
                                    if (!empty($configUniversitee['logo'])) {
                                        $logoPath = './' . $configUniversitee['logo'];
                                        if (file_exists($logoPath)) {
                                            $logoData = base64_encode(file_get_contents($logoPath));
                                            $logoMime = mime_content_type($logoPath);
                                            echo '<img src="'.$logoPath.'" alt="Logo">';
                                        }else{
                                            echo 'Configurer le logo une fois connecté';
                                        }
                                    }
                                ?>
                                    <span style="color:blue!important" class="d-none d-lg-block"><?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?></span>
                                </a>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="pt-4 pb-2">
                                        <h5 class="card-title text-center pb-0 fs-4">Changement de mot de passe obligatoire</h5>
                                        <p class="text-center small">Pour des raisons de sécurité, vous devez changer votre mot de passe lors de votre première connexion.</p>
                                    </div>
                                    
                                    <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($_SESSION['error']) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['error']); endif; ?>
                                    
                                    <form class="row g-3 needs-validation" method="POST" action="controller/update_first_password.php" novalidate>
                                        <div class="col-12">
                                            <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                                            <div class="input-group has-validation">
                                                <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-key-fill"></i></span>
                                                <input type="password" name="newPassword" class="form-control" id="newPassword" required>
                                                <div class="invalid-feedback">Veuillez entrer un nouveau mot de passe.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                                            <div class="input-group has-validation">
                                                <span class="input-group-text" id="inputGroupPrepend"><i class="bi bi-key"></i></span>
                                                <input type="password" name="confirmPassword" class="form-control" id="confirmPassword" required>
                                                <div class="invalid-feedback">Veuillez confirmer votre mot de passe.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="terms" id="acceptTerms" required>
                                                <label class="form-check-label" for="acceptTerms">Je comprends l'importance de choisir un mot de passe sécurisé</label>
                                                <div class="invalid-feedback">Vous devez accepter avant de soumettre.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button class="btn btn-primary w-100" type="submit">Changer le mot de passe</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="credits">
                                <div class="copyright">
                                    &copy; Copyright <strong><span><?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?></span></strong>. Tous droits réservés
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Validation du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.needs-validation');
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                // Vérifier que les mots de passe correspondent
                if (newPassword.value !== confirmPassword.value) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Les mots de passe ne correspondent pas.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        confirmPassword.value = '';
                        confirmPassword.focus();
                    });
                    return false;
                }
                
                // Vérifier la complexité du mot de passe
                if (newPassword.value.length < 8) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Mot de passe trop court',
                        text: 'Le mot de passe doit contenir au moins 8 caractères.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        newPassword.focus();
                    });
                    return false;
                }
                
                
                
                form.classList.add('was-validated');
            }, false);
        });
    </script>
</body>
</html>
