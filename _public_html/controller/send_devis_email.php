<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_devis = isset($_POST['id_devis']) ? intval($_POST['id_devis']) : 0;
        $recipient_email = isset($_POST['recipient_email']) ? trim($_POST['recipient_email']) : '';
        $recipient_name = isset($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
        $email_subject = isset($_POST['email_subject']) ? trim($_POST['email_subject']) : '';
        $email_message = isset($_POST['email_message']) ? trim($_POST['email_message']) : '';
        
        // Validation des données
        if ($id_devis <= 0) {
            throw new Exception("ID de devis invalide");
        }
        
        if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Adresse email du destinataire invalide");
        }
        
        if (empty($email_subject)) {
            throw new Exception("Le sujet de l'email est requis");
        }
        
        // Récupérer les informations du devis
        $query = "SELECT d.*, c.nom_client, c.code_client 
                  FROM devis d 
                  JOIN client c ON d.id_client = c.id_client 
                  WHERE d.id_devis = :id_devis";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
        $stmt->execute();
        $devis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$devis) {
            throw new Exception("Devis introuvable");
        }
        
        // Récupérer les lignes du devis
        $queryLignes = "SELECT ld.*, p.code_produit, p.libelle_produit 
                        FROM ligne_devis ld 
                        JOIN produit p ON ld.id_produit = p.id_produit 
                        WHERE ld.id_devis = :id_devis";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_devis', $id_devis, PDO::PARAM_INT);
        $stmtLignes->execute();
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les informations de l'entreprise
        $queryConfig = "SELECT * FROM configuration_universite LIMIT 1";
        $stmtConfig = $db->prepare($queryConfig);
        $stmtConfig->execute();
        $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
        
        // Si la table config_universite n'existe pas, utiliser des valeurs par défaut
        if (!$config) {
            $config = [
                'nom' => 'E-GESTION',
                'sigle' => 'EG',
                'ministere_tutelle' => 'SYSTÈME DE GESTION',
                'adresse' => '',
                'telephone' => '',
                'email' => '',
                'site_web' => '',
                'logo' => 'assets/img/logo.png'
            ];
        }
        
        // Générer le contenu HTML de l'email
        $emailContent = generateEmailTemplate($devis, $lignes, $config, $email_message);
        
        // Configuration de PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Configuration du serveur
            $mail->SMTPDebug = SMTP::DEBUG_OFF; // Mettre à DEBUG_SERVER pour le débogage
            $mail->isSMTP();
            $mail->Host       = getenv('MAIL_HOST') ?: 'mail.bdomsoft.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('MAIL_USERNAME') ?: '';
            $mail->Password   = getenv('MAIL_PASSWORD') ?: '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10; // Timeout en secondes
            
            // Destinataires
            $mail->setFrom('no-reply@votre-domaine.com', $config['nom'] ?? 'E-GESTION');
            $mail->addAddress($recipient_email, $recipient_name);
            $mail->addReplyTo($config['email'] ?? 'contact@votre-domaine.com', $config['nom'] ?? 'E-GESTION');
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $email_subject;
            $mail->Body    = $emailContent;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $email_message));
            
            $mail->send();
            
            // Journaliser l'action
            $logStmt = $db->prepare("INSERT INTO log_operation 
                (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
                VALUES 
                (:id_user, 'envoi_email', 'devis', :id_enregistrement, :description, :adresse_ip, :navigateur)");
            
            $description = "Envoi du devis " . $devis['numero_devis'] . " par email à " . $recipient_email;
            $adresse_ip = $_SERVER['REMOTE_ADDR'];
            $navigateur = $_SERVER['HTTP_USER_AGENT'];
            
            $logStmt->bindParam(':id_user', $_SESSION['id'], PDO::PARAM_INT);
            $logStmt->bindParam(':id_enregistrement', $id_devis, PDO::PARAM_INT);
            $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
            $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
            $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
            
            $logStmt->execute();
            
            // Rediriger avec un message de succès (utiliser header au lieu de JavaScript)
            header("Location: ../ventes/devis/devis.view&id=" . $id_devis . "&status=success&message=" . urlencode("Le devis a été envoyé avec succès à " . $recipient_email));
            exit;
            
        } catch (Exception $e) {
            // Journaliser l'erreur
            error_log("Erreur d'envoi d'email: " . $mail->ErrorInfo);
            throw new Exception("Erreur lors de l'envoi de l'email: " . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        // Journaliser l'erreur
        error_log("Erreur dans send_devis_email.php: " . $e->getMessage());
        
        // Rediriger avec un message d'erreur
        header("Location: ../ventes/devis/devis.view&id=" . $id_devis . "&status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Si accès direct au fichier sans POST
    header('Location: ../index');
    exit();
}


function generateEmailTemplate($devis, $lignes, $config, $message) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Devis ' . $devis['numero_devis'] . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f9f9f9;
            }
            .container {
                max-width: 650px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                padding: 20px 0;
                border-bottom: 2px solid #0057a2;
            }
            .logo {
                max-height: 80px;
                max-width: 200px;
            }
            .company-name {
                font-size: 22px;
                font-weight: bold;
                color: #0057a2;
                margin: 10px 0 5px;
            }
            .company-details {
                font-size: 14px;
                color: #666;
                margin-bottom: 5px;
            }
            .message {
                padding: 20px 0;
                border-bottom: 1px solid #eee;
            }
            .devis-details {
                background-color: #f5f9ff;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
                border-left: 4px solid #0057a2;
            }
            .devis-number {
                font-size: 18px;
                font-weight: bold;
                color: #0057a2;
                margin-bottom: 10px;
            }
            .devis-info {
                margin-bottom: 5px;
                font-size: 14px;
            }
            .highlight {
                font-weight: bold;
                color: #0057a2;
            }
            .products-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .products-table th {
                background-color: #0057a2;
                color: white;
                text-align: left;
                padding: 10px;
            }
            .products-table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .products-table tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            .total-row {
                font-weight: bold;
                background-color: #e6f0ff !important;
            }
            .footer {
                text-align: center;
                padding: 20px 0;
                font-size: 12px;
                color: #777;
                border-top: 1px solid #eee;
                margin-top: 20px;
            }
            .cta-button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #0057a2;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin: 20px 0;
            }
            @media only screen and (max-width: 600px) {
                .container {
                    width: 100%;
                    padding: 10px;
                }
                .products-table th, .products-table td {
                    padding: 5px;
                    font-size: 12px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="'.dirname(__DIR__) . '/' . (isset($config['logo']) ? 'https://bdom-soft.org/' . $config['logo'] : '') . '" alt="Logo" class="logo">
                <div class="company-name">' . ($config['nom'] ?? 'E-GESTION') . '</div>
                <div class="company-details">' . ($config['adresse'] ?? '') . '</div>
                <div class="company-details">Tél: ' . ($config['telephone'] ?? '') . ' | Email: ' . ($config['email'] ?? '') . '</div>
                <div class="company-details">' . ($config['site_web'] ?? '') . '</div>
            </div>
            
            <div class="message">
                <p>Bonjour ' . htmlspecialchars($devis['nom_client']) . ',</p>
                ' . nl2br(htmlspecialchars($message)) . '
            </div>
            
            <div class="devis-details">
                <div class="devis-number">Devis N° ' . $devis['numero_devis'] . '</div>
                <div class="devis-info"><span class="highlight">Date:</span> ' . date('d/m/Y', strtotime($devis['date_devis'])) . '</div>
                <div class="devis-info"><span class="highlight">Client:</span> ' . htmlspecialchars($devis['code_client'] . ' - ' . $devis['nom_client']) . '</div>
                <div class="devis-info"><span class="highlight">Validité:</span> ' . $devis['validite'] . ' jours</div>
                <div class="devis-info"><span class="highlight">Montant total:</span> ' . number_format($devis['montant_ttc'], 2, ',', ' ') . ' USD</div>
            </div>
            
            <h3 style="color: #0057a2;">Détails des produits</h3>
            
            <table class="products-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Qté</th>
                        <th>Prix Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($lignes as $ligne) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($ligne['designation']) . '</td>
                        <td>' . number_format($ligne['quantite'], 2, ',', ' ') . '</td>
                        <td>' . number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD</td>
                        <td>' . number_format($ligne['montant_ht'], 2, ',', ' ') . ' USD</td>
                    </tr>';
    }
    
    $html .= '
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total HT:</td>
                        <td>' . number_format($devis['montant_ht'], 2, ',', ' ') . ' USD</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">TVA (' . number_format($devis['taux_tva'], 2, ',', ' ') . '%):</td>
                        <td>' . number_format($devis['montant_tva'], 2, ',', ' ') . ' USD</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total TTC:</td>
                        <td>' . number_format($devis['montant_ttc'], 2, ',', ' ') . ' USD</td>
                    </tr>
                </tbody>
            </table>
            
                        <div class="footer">
                <p>Ce message a été envoyé automatiquement, merci de ne pas y répondre directement.</p>
                <p>&copy; ' . date('Y') . ' ' . ($config['nom'] ?? 'E-GESTION') . '. Tous droits réservés.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

