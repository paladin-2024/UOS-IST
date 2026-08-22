<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte de Service</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .service-card {
            width: 350px;
            border-radius: 15px;
            background: linear-gradient(to right, #222831, #FFD369);
            color: white;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: left;
            position: relative;
        }
        .service-card h2 {
            margin: 10px 0;
            color: #FFD369;
        }
        .service-card p {
            margin: 5px 0;
            font-size: 14px;
        }
        .logo {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .contact-info {
            font-size: 12px;
        }
        .photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: white;
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        .photo img {
            width: 100%;
            height: auto;
        }
        .qr-code {
            width: 50px;
            height: 50px;
            background: white;
            position: absolute;
            bottom: 10px;
            right: 10px;
        }
        .dates {
            margin-top: 10px;
            font-size: 12px;
            color: #FFD369;
        }
    </style>
</head>
<body>
    <div class="service-card">
        <div class="logo">Ciomo <span style="color:#FFD369;">Pro</span></div>
        <div class="photo">
            <img src="uploads/agents/agent_10_cropped_1739389802.jpg" alt="Photo de l'agent">
        </div>
        <h2>Michal Hansen</h2>
        <p>Poste: CEO & Founder</p>
        <div class="dates">
            <p>📅 Début: 01-01-2018</p>
            <p>📅 Expiration: 12-2022</p>
        </div>
        <div class="contact-info">
            <p>📞 000 111 222 333</p>
            <p>📧 yourgmail@name.com</p>
            <p>🌐 www.yourwebsitename.com</p>
            <p>📍 Your Street Address Here</p>
        </div>
        <div class="qr-code"></div>
    </div>
</body>
</html>
