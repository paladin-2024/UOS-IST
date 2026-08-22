<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

$agentId = isset($_GET['id']) ? $_GET['id'] : null;

if ($agentId) {
    $agent = new Agent();
    $agentData = $agent->getAgentById($agentId);

    if ($agentData) {
        // Contenu HTML pour la carte de service
        $html = '
        <style>
            * {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }
            .service-card {
                width: 85mm;
                height: 54mm;
                border-radius: 5px;
                background: linear-gradient(to right, #222831, #FFD369);
                color: white;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 5mm;
                box-sizing: border-box;
            }
            .left {
                display: flex;
                flex-direction: column;
                justify-content: center;
                width: 60%;
            }
            .right {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
                width: 40%;
            }
            .logo {
                font-size: 14px;
                font-weight: bold;
                text-align: left;
            }
            .photo {
                width: 25mm;
                height: 25mm;
                border-radius: 50%;
                background-color: white;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .photo img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            h2 {
                font-size: 14px;
                color: #FFD369;
            }
            p {
                font-size: 10px;
            }
            .dates {
                font-size: 9px;
                color: #FFD369;
            }
            .contact-info {
                font-size: 9px;
            }
            .qr-code {
                width: 18mm;
                height: 18mm;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .qr-code img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
        </style>

        <div class="service-card">
            <div class="left">
                <div class="logo">Ciomo <span style="color:#FFD369;">Pro</span></div>
                <h2>' . htmlspecialchars($agentData['noms']) . '</h2>
                <p>Poste: </p>
                <div class="dates">
                    <p> Début: </p>
                    <p>Expiration: </p>
                </div>
                <div class="contact-info">
                    <p> ' . htmlspecialchars($agentData['telephone']) . '</p>
                    <p> ' . htmlspecialchars($agentData['email']) . '</p>
                </div>
            </div>
            <div class="right">
                <div class="photo">
                    <img src="uploads/agents/' . htmlspecialchars($agentData['photo']) . '" alt="Photo de l\'agent">
                </div>
                <div class="qr-code">
                    <img src="uploads/qrcode.jpg" alt="QR Code">
                </div>
            </div>
        </div>';

        // Génération du PDF en paysage (L)
        try {
            $html2pdf = new Html2Pdf('L', array(85, 54), 'fr', true, 'UTF-8', array(0, 0, 0, 0));
            $html2pdf->writeHTML($html);
            $html2pdf->output('carte_de_service.pdf', 'I');
        } catch (Exception $e) {
            echo 'Erreur: ' . $e->getMessage();
        }
    } else {
        echo 'Agent introuvable';
    }
} else {
    echo 'Requête invalide';
}
?>
