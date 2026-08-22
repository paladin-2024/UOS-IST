<?php
// Empêcher l'accès direct si déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php?view=admin/dashboard');
    exit;
}

// Récupérer les messages d'erreur ou de succès stockés en session
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']);

$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISTM Beni - Connexion Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" href="../uploads/logo.png" type="image/x-icon">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-height: 120px;
        }
        .btn-primary {
            background-color: #003366;
            border-color: #003366;
        }
        .btn-primary:hover {
            background-color: #002244;
            border-color: #002244;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="../uploads/logo.png" alt="ISTM Beni Logo">
            <h4 class="mt-3">ISTM Beni Admin</h4>
        </div>
        
        <!-- Toast Notifications -->
        <div class="toast-container">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="POST" action="../controller/process_login.php" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text" class="form-control" id="username" name="username" required>
                <div class="invalid-feedback">Veuillez saisir votre nom d'utilisateur.</div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div class="invalid-feedback">Veuillez saisir votre mot de passe.</div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Se souvenir de moi</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="../accueil" class="text-decoration-none">Retour au site</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des formulaires
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
