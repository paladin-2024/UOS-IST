<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - E-GESTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-container {
            text-align: center;
            color: white;
            padding: 40px;
            max-width: 600px;
        }
        .maintenance-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        .maintenance-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
        }
        .maintenance-message {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .maintenance-timer {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .btn-home {
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn-home:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="bi bi-tools"></i>
        </div>
        <div class="maintenance-title">SITE EN MAINTENANCE</div>
        <div class="maintenance-message">
            Nous effectuons actuellement des travaux de maintenance pour améliorer nos services.
            <br>Nous revenons bientôt!
        </div>
        <div class="maintenance-timer">
            <i class="bi bi-clock-history"></i> Retour prévu dans les plus brefs délais
        </div>
        <a href="#" class="btn-home" onclick="location.reload(); return false;">
            <i class="bi bi-arrow-clockwise"></i> Réessayer plus tard
        </a>
    </div>
</body>
</html>
