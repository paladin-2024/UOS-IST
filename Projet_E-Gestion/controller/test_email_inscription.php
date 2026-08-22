<?php
// Fichier de test pour vérifier l'envoi d'emails d'inscription
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['id'])) {
    die('Accès non autorisé');
}

// Données de test pour simuler une inscription
$inscriptionTest = [
    'id' => 999,
    'reference_inscription' => 'TEST-2024-001',
    'nom' => 'Doe',
    'postnom' => 'John',
    'prenom' => 'Test',
    'email' => 'test@example.com', // Remplacez par votre email pour tester
    'telephone' => '+243 123 456 789',
    'date_naissance' => '1995-05-15',
    'lieu_naissance' => 'Kinshasa',
    'sexe' => 'M',
    'nationalite' => 'Congolaise',
    'adresse_complete' => '123 Avenue Test, Kinshasa',
    'personne_contact' => 'Jane Doe',
    'telephone_contact' => '+243 987 654 321',
    'date_soumission' => date('Y-m-d H:i:s'),
    'statut' => 'En cours',
    'nom_promotion' => 'Licence 1 - Génie Civil',
    'annee_academique' => '2024-2025',
    'titre_lien' => 'Inscription Licence Génie Civil 2024'
];

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'test_validation':
        echo "<h2>Test d'envoi d'email de validation</h2>";
        $matricule = '2024-TEST-001';
        $idEtudiant = 999;
        
        $resultat = envoyerEmailValidation($inscriptionTest, $matricule, $idEtudiant);
        
        if ($resultat) {
            echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0;'>";
            echo "✅ Email de validation envoyé avec succès !<br>";
            echo "Destinataire: " . htmlspecialchars($inscriptionTest['email']) . "<br>";
            echo "Matricule: " . htmlspecialchars($matricule);
            echo "</div>";
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #fff0f0;'>";
            echo "❌ Erreur lors de l'envoi de l'email de validation";
            echo "</div>";
        }
        break;
        
    case 'test_rejet':
        echo "<h2>Test d'envoi d'email de rejet</h2>";
        $motifRejet = "Dossier incomplet : il manque la copie du diplôme d'État et la photo d'identité.";
        
        $resultat = envoyerEmailRejet($inscriptionTest, $motifRejet);
        
        if ($resultat) {
            echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0;'>";
            echo "✅ Email de rejet envoyé avec succès !<br>";
            echo "Destinataire: " . htmlspecialchars($inscriptionTest['email']) . "<br>";
            echo "Motif: " . htmlspecialchars($motifRejet);
            echo "</div>";
        } else {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #fff0f0;'>";
            echo "❌ Erreur lors de l'envoi de l'email de rejet";
            echo "</div>";
        }
        break;
        
    default:
        echo "<h2>Test des emails d'inscription</h2>";
        echo "<p>Choisissez le type d'email à tester :</p>";
        echo "<a href='?action=test_validation' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; margin: 5px;'>Tester Email de Validation</a>";
        echo "<a href='?action=test_rejet' style='display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; margin: 5px;'>Tester Email de Rejet</a>";
        echo "<br><br>";
        echo "<p><strong>Note:</strong> Modifiez l'email dans le code source pour recevoir les emails de test.</p>";
        break;
}

// Fonction pour envoyer l'email de validation (copie de la fonction du fichier principal)
function envoyerEmailValidation($inscription, $matricule, $idEtudiant) {
    try {
        $to = $inscription['email'];
        $subject = 'Inscription validée - Bienvenue à l\'INBTP Kinshasa';
        
        // Construire le contenu HTML de l'email
        $htmlMessage = genererCorpsEmailValidation($inscription, $matricule, $idEtudiant);
        
        // Configuration de l'entreprise
        $entreprise = [
            'nom' => 'INBTP Kinshasa',
            'email' => 'info@inbtpkinshasa.info'
        ];
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . htmlspecialchars($entreprise['nom']) . " <" . htmlspecialchars($entreprise['email']) . ">" . "\r\n";
        
        // Envoyer l'email
        return mail($to, $subject, $htmlMessage, $headers);
        
    } catch (Exception $e) {
        error_log('Erreur envoi email validation: ' . $e->getMessage());
        return false;
    }
}

// Fonction pour envoyer l'email de rejet (copie de la fonction du fichier de rejet)
function envoyerEmailRejet($inscription, $motifRejet) {
    try {
        $to = $inscription['email'];
        $subject = 'Inscription rejetée - INBTP Kinshasa';
        
        // Construire le contenu HTML de l'email
        $htmlMessage = genererCorpsEmailRejet($inscription, $motifRejet);
        
        // Configuration de l'entreprise
        $entreprise = [
            'nom' => 'INBTP Kinshasa',
            'email' => 'info@inbtpkinshasa.info'
        ];
        
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . htmlspecialchars($entreprise['nom']) . " <" . htmlspecialchars($entreprise['email']) . ">" . "\r\n";
        
        // Envoyer l'email
        return mail($to, $subject, $htmlMessage, $headers);
        
    } catch (Exception $e) {
        error_log('Erreur envoi email rejet: ' . $e->getMessage());
        return false;
    }
}

// Fonction pour générer le corps de l'email de validation en HTML
function genererCorpsEmailValidation($inscription, $matricule, $idEtudiant) {
    $urlPortail = "https://inbtpkinshasa.info/portail/login";
    
    $htmlMessage = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Inscription validée</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
            
            body {
                font-family: "Poppins", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .wrapper {
                background-color: #f5f5f5;
                padding: 30px 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            }
            .header {
                background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-weight: 600;
                font-size: 24px;
                letter-spacing: 0.5px;
            }
            .content {
                padding: 40px 30px;
                color: #505050;
                font-size: 15px;
                line-height: 1.8;
            }
            .success-box {
                background-color: #f0fff4;
                border: 1px solid #9ae6b4;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #38a169;
            }
            .success-box h3 {
                margin-top: 0;
                color: #2f855a;
                font-size: 18px;
            }
            .info-box {
                background-color: #f7fafc;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #4299e1;
            }
            .matricule-box {
                background-color: #edf2f7;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                margin: 20px 0;
                border: 2px solid #4299e1;
            }
            .matricule-box .matricule {
                font-size: 24px;
                font-weight: bold;
                color: #2b6cb0;
                letter-spacing: 2px;
            }
            .footer {
                background-color: #f9f9f9;
                font-size: 13px;
                text-align: center;
                padding: 25px 20px;
                color: #777;
                border-top: 1px solid #eaeaea;
            }
            .btn {
                display: inline-block;
                background-color: #4299e1;
                color: white;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 4px;
                font-weight: 500;
                margin: 10px 0;
            }
            .steps-list {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .steps-list ol {
                margin: 0;
                padding-left: 20px;
            }
            .steps-list li {
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>🎉 Félicitations !</h1>
                    <p>Votre inscription a été validée</p>
                </div>
                
                <div class="content">
                    <p>Cher(e) <strong>' . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . '</strong>,</p>
                    
                    <div class="success-box">
                        <h3>✅ Inscription Validée avec Succès</h3>
                        <p>Nous avons le plaisir de vous informer que votre demande d\'inscription à l\'<strong>INBTP Kinshasa</strong> a été validée avec succès !</p>
                    </div>
                    
                    <div class="matricule-box">
                        <p style="margin: 0; font-size: 14px; color: #666;">Votre matricule étudiant</p>
                        <div class="matricule">' . htmlspecialchars($matricule) . '</div>
                        <p style="margin: 0; font-size: 12px; color: #666; margin-top: 5px;">Conservez précieusement ce matricule</p>
                    </div>
                    
                    <div class="info-box">
                        <h4>📋 Récapitulatif de votre inscription</h4>
                        <p><strong>Référence :</strong> ' . htmlspecialchars($inscription['reference_inscription']) . '</p>
                        <p><strong>Nom complet :</strong> ' . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['postnom'] . ' ' . $inscription['nom']) . '</p>
                        <p><strong>Email :</strong> ' . htmlspecialchars($inscription['email']) . '</p>
                        <p><strong>Téléphone :</strong> ' . htmlspecialchars($inscription['telephone']) . '</p>
                        <p><strong>Promotion :</strong> ' . htmlspecialchars($inscription['nom_promotion'] ?? 'Non spécifiée') . '</p>
                        <p><strong>Année académique :</strong> ' . htmlspecialchars($inscription['annee_academique'] ?? 'Non spécifiée') . '</p>
                        <p><strong>Date de validation :</strong> ' . date('d/m/Y à H:i') . '</p>
                    </div>
                    
                    <h3>🚀 Prochaines étapes</h3>
                    <div class="steps-list">
                        <ol>
                            <li><strong>Accédez au portail étudiant</strong> avec votre adresse email et un mot de passe temporaire qui vous sera communiqué</li>
                            <li><strong>Complétez votre profil</strong> et vérifiez vos informations personnelles</li>
                            <li><strong>Consultez votre emploi du temps</strong> et les informations sur vos cours</li>
                            <li><strong>Téléchargez les documents</strong> nécessaires pour la rentrée</li>
                            <li><strong>Contactez le service des étudiants</strong> pour toute question administrative</li>
                        </ol>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="' . $urlPortail . '" class="btn">🌐 Accéder au Portail Étudiant</a>
                    </p>
                    
                    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h4 style="margin-top: 0; color: #856404;">📞 Besoin d\'aide ?</h4>
                        <p style="margin-bottom: 0;">Si vous avez des questions ou besoin d\'assistance, n\'hésitez pas à contacter notre service des admissions ou à vous rendre directement sur le campus.</p>
                    </div>
                    
                    <p>Nous vous souhaitons la bienvenue dans la famille INBTP Kinshasa et vous souhaitons une excellente année académique !</p>
                    
                    <p>Cordialement,<br>
                    Le Service des Admissions<br>
                    <strong>INBTP Kinshasa</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>Institut National du Bâtiment et des Travaux Publics</strong></p>
                    <p>INBTP Kinshasa</p>
                    <p>Email: <a href="mailto:info@inbtpkinshasa.info" style="color: #4299e1; text-decoration: none;">info@inbtpkinshasa.info</a></p>
                    <p>Portail: <a href="' . $urlPortail . '" style="color: #4299e1; text-decoration: none;">inbtpkinshasa.info/portail</a></p>
                    <p>&copy; ' . date('Y') . ' INBTP Kinshasa. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $htmlMessage;
}

// Fonction pour générer le corps de l'email de rejet en HTML
function genererCorpsEmailRejet($inscription, $motifRejet) {
    $urlPortail = "https://inbtpkinshasa.info/portail/login";
    
    $htmlMessage = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Inscription rejetée</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
            
            body {
                font-family: "Poppins", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f5f5f5;
                margin: 0;
                padding: 0;
            }
            .wrapper {
                background-color: #f5f5f5;
                padding: 30px 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            }
            .header {
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-weight: 600;
                font-size: 24px;
                letter-spacing: 0.5px;
            }
            .content {
                padding: 40px 30px;
                color: #505050;
                font-size: 15px;
                line-height: 1.8;
            }
            .rejection-box {
                background-color: #fff5f5;
                border: 1px solid #fed7d7;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #e53e3e;
            }
            .rejection-box h3 {
                margin-top: 0;
                color: #c53030;
                font-size: 18px;
            }
            .info-box {
                background-color: #f7fafc;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #4299e1;
            }
            .footer {
                background-color: #f9f9f9;
                font-size: 13px;
                text-align: center;
                padding: 25px 20px;
                color: #777;
                border-top: 1px solid #eaeaea;
            }
            .btn {
                display: inline-block;
                background-color: #4299e1;
                color: white;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 4px;
                font-weight: 500;
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>❌ Inscription Rejetée</h1>
                    <p>INBTP Kinshasa</p>
                </div>
                
                <div class="content">
                    <p>Cher(e) <strong>' . htmlspecialchars($inscription['prenom'] . ' ' . $inscription['nom']) . '</strong>,</p>
                    
                    <p>Nous vous remercions pour votre demande d\'inscription à l\'INBTP Kinshasa.</p>
                    
                    <div class="rejection-box">
                        <h3>🚫 Statut de votre inscription</h3>
                        <p><strong>Malheureusement, votre demande d\'inscription a été rejetée.</strong></p>
                        
                        <p><strong>Motif du rejet :</strong></p>
                        <p>' . nl2br(htmlspecialchars($motifRejet)) . '</p>
                    </div>
                    
                    <div class="info-box">
                        <h4>📋 Informations de votre demande</h4>
                        <p><strong>Référence :</strong> ' . htmlspecialchars($inscription['reference_inscription']) . '</p>
                        <p><strong>Email :</strong> ' . htmlspecialchars($inscription['email']) . '</p>
                        <p><strong>Date de soumission :</strong> ' . date('d/m/Y à H:i', strtotime($inscription['date_soumission'])) . '</p>
                        <p><strong>Promotion demandée :</strong> ' . htmlspecialchars($inscription['nom_promotion'] ?? 'Non spécifiée') . '</p>
                        <p><strong>Année académique :</strong> ' . htmlspecialchars($inscription['annee_academique'] ?? 'Non spécifiée') . '</p>
                    </div>
                    
                    <h3>🔄 Que faire maintenant ?</h3>
                    <p>Si vous souhaitez corriger les éléments mentionnés et soumettre une nouvelle demande, vous pouvez :</p>
                    <ul>
                        <li>Préparer les documents manquants ou corriger les informations incorrectes</li>
                        <li>Contacter notre service des admissions pour plus d\'informations</li>
                        <li>Soumettre une nouvelle demande d\'inscription si un nouveau lien est disponible</li>
                    </ul>
                    
                    <p style="text-align: center;">
                        <a href="' . $urlPortail . '" class="btn">🌐 Accéder au Portail Étudiant</a>
                    </p>
                    
                    <p>Pour toute question concernant cette décision, n\'hésitez pas à nous contacter.</p>
                    
                    <p>Cordialement,<br>
                    Le Service des Admissions<br>
                    <strong>INBTP Kinshasa</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>Institut National du Bâtiment et des Travaux Publics</strong></p>
                    <p>INBTP Kinshasa</p>
                    <p>Email: <a href="mailto:info@inbtpkinshasa.info" style="color: #4299e1; text-decoration: none;">info@inbtpkinshasa.info</a></p>
                    <p>Portail: <a href="' . $urlPortail . '" style="color: #4299e1; text-decoration: none;">inbtpkinshasa.info/portail</a></p>
                    <p>&copy; ' . date('Y') . ' INBTP Kinshasa. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $htmlMessage;
}
?>