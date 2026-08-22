<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISTM BENI - Site en maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #004A98;
            --secondary-color: #FFD700;
            --text-color: #333;
            --light-bg: #f8f9fa;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
        }
        
        .maintenance-box {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 800px;
            width: 90%;
        }
        
        .logo {
            margin-bottom: 2rem;
        }
        
        .logo img {
            max-width: 200px;
            height: auto;
        }
        
        h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        h2 {
            color: var(--text-color);
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }
        
        p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-container {
            background-color: #e9ecef;
            border-radius: 10px;
            height: 20px;
            width: 100%;
            margin: 2rem 0;
            overflow: hidden;
        }
        
        .progress-bar {
            background-color: var(--primary-color);
            height: 100%;
            border-radius: 10px;
            width: 75%;
            animation: progressAnimation 3s ease-in-out infinite alternate;
        }
        
        .countdown {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 1.5rem 0;
        }
        
        .contact-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            background-color: var(--secondary-color);
            color: var(--text-color);
            transform: translateY(-3px);
        }
        
        .cta-button {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.8rem 1.5rem;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            background-color: var(--secondary-color);
            color: var(--text-color);
            transform: translateY(-3px);
        }
        
        @keyframes progressAnimation {
            0% {
                width: 70%;
            }
            100% {
                width: 90%;
            }
        }
        
        footer {
            margin-top: 2rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .maintenance-box {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="maintenance-box">
            <div class="logo">
                <!-- Remplacez par le vrai logo si disponible -->
                <h1>ISTM BENI</h1>
                <p>Institut Supérieur des Techniques Médicales de Beni</p>
            </div>
            
            <h2>Site en maintenance</h2>
            
            <p>Nous sommes en train d'améliorer notre plateforme pour vous offrir une meilleure expérience.</p>
            <p>Notre site sera bientôt disponible avec de nouvelles fonctionnalités.</p>
            
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            
            <div class="countdown">
                Lancement prévu dans <span id="countdown-timer">2 jours</span>
            </div>
            
            <div class="contact-info">
                <p>Pour toute information complémentaire, n'hésitez pas à nous contacter :</p>
                <p><i class="fas fa-envelope"></i> info@istmbeni.ac.cd</p>
                <p><i class="fas fa-phone"></i> +243 993 616 190</p>
                
                <div class="social-icons">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
                
                <a href="mailto:info@istmbeni.ac.cd" class="cta-button">Nous contacter</a>
            </div>
        </div>
        
        <footer>
            &copy; 2025 ISTM BENI - Tous droits réservés
        </footer>
    </div>
    
    <script>
        // Script pour le compte à rebours (facultatif)
        function updateCountdown() {
            const launchDate = new Date();
            launchDate.setDate(launchDate.getDate() + 2); // Date de lancement dans 7 jours
            
            const now = new Date();
            const diff = launchDate - now;
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            
            let countdownText = "";
            if (days > 0) {
                countdownText = `${days} jour${days > 1 ? 's' : ''}`;
            } else {
                countdownText = `${hours} heure${hours > 1 ? 's' : ''}`;
            }
            
            document.getElementById('countdown-timer').textContent = countdownText;
        }
        
        // Mettre à jour le compte à rebours toutes les heures
        updateCountdown();
        setInterval(updateCountdown, 3600000);
    </script>
</body>
</html>
