<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - ScienceHub Mobile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 68, 148, 0.9) 0%, rgba(0, 68, 148, 0.7) 100%);
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
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
        }
        .maintenance-message {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .maintenance-timer {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .btn-home {
            background: white;
            color: #004494;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
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
        <div class="maintenance-title">MAINTENANCE EN COURS</div>
        <div class="maintenance-message">
            Nous effectuons actuellement des travaux de maintenance sur notre plateforme.
            <br>Nous revenons bientôt avec des améliorations!
        </div>
        <div class="maintenance-timer">
            <i class="bi bi-clock-history"></i> Retour prévu dans les plus brefs délais
        </div>
        <button class="btn-home" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Réessayer plus tard
        </button>
    </div>
</body>
</html>
