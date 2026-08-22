<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Accès Interdit</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .error-container {
            text-align: center;
            color: white;
            padding: 40px;
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
        }
        .error-message {
            font-size: 24px;
            margin-bottom: 30px;
        }
        .btn-home {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .btn-home:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">403</div>
        <div class="error-message">Accès Interdit</div>
        <p>Vous n'avez pas l'autorisation d'accéder à cette page.</p>
        <a href="index" class="btn-home">Retour à l'accueil</a>
    </div>
</body>
</html>
