<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON du corps de la requête
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier si les données nécessaires sont présentes
if (!isset($input['etudiant_id']) || !isset($input['document_id']) || !isset($input['objet']) || !isset($input['message'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$etudiantId = intval($input['etudiant_id']);
$documentId = intval($input['document_id']);
$objet = trim($input['objet']);
$message = trim($input['message']);

if (empty($etudiantId) || empty($documentId) || empty($objet) || empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations de l'étudiant
    $stmt = $conn->prepare("
        SELECT e.*, d.designation as document_nom 
        FROM etudiant e
        JOIN documents_obligatoires d ON d.id = ?
        WHERE e.idetudiant = ?
    ");
    $stmt->execute([$documentId, $etudiantId]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Étudiant ou document non trouvé']);
        exit();
    }
    
    // Personnaliser le message
    $messagePersonnalise = str_replace('[NOM_DOCUMENT]', $etudiant['document_nom'], $message);
    $messagePersonnalise = str_replace('[NOM_ETUDIANT]', $etudiant['noms'], $messagePersonnalise);
    $messagePersonnalise = str_replace('[MATRICULE]', $etudiant['matricule'], $messagePersonnalise);
    
    // Récupérer les informations de l'université
    $stmtUniv = $conn->prepare("SELECT * FROM configuration_universite LIMIT 1");
    $stmtUniv->execute();
    $universite = $stmtUniv->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les informations de l'utilisateur connecté (expéditeur)
    $stmtUser = $conn->prepare("
    SELECT u.*, r.nomRole as role_nom 
    FROM t_users u
    JOIN t_roles r ON u.idRole = r.idRole
    WHERE u.idUser = ?
    ");
    $stmtUser->execute([$_SESSION['id']]);
    $expediteur = $stmtUser->fetch(PDO::FETCH_ASSOC);

    
    // Envoyer l'email avec le template HTML
    $envoi = false;
    if (!empty($etudiant['adressemail'])) {
        $envoi = sendDocumentRequestEmail($etudiant, $messagePersonnalise, $objet, $universite, $expediteur);
    }
    
    // Enregistrer la demande dans la base de données
    $stmt = $conn->prepare("
        INSERT INTO demandes_documents 
        (idetudiant, matricule, document_obligatoire_id, objet, contenu, date_envoi, email_envoye, idUser) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    
    $stmt->execute([
        $etudiantId,
        $etudiant['matricule'],
        $documentId,
        $objet,
        $messagePersonnalise,
        $envoi ? 1 : 0,
        $_SESSION['id']
    ]);
    
    // Réponse JSON
    header('Content-Type: application/json');
    if ($envoi) {
        echo json_encode(['success' => true, 'message' => 'Demande envoyée avec succès']);
    } else {
        echo json_encode([
            'success' => true, 
            'warning' => true,
            'message' => 'Demande enregistrée mais l\'email n\'a pas pu être envoyé (adresse email manquante ou problème d\'envoi)'
        ]);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

/**
 * Envoie un email de demande de document à l'étudiant
 * 
 * @param array $etudiant Informations de l'étudiant
 * @param string $messagePersonnalise Contenu personnalisé du message
 * @param string $objetEmail Objet de l'email
 * @param array $universite Informations de l'université
 * @param array $expediteur Informations sur l'expéditeur (utilisateur connecté)
 * @return bool Succès de l'envoi
 */
function sendDocumentRequestEmail($etudiant, $messagePersonnalise, $objetEmail, $universite, $expediteur) {
    // Vérifier si l'étudiant a une adresse email
    if (empty($etudiant['adressemail'])) {
        return false;
    }
    
    $to = $etudiant['adressemail'];
    $subject = $objetEmail;
    
    // Construire le contenu HTML de l'email
    $htmlMessage = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . $subject . '</title>
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
                background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .logo-container {
                margin-bottom: 15px;
            }
            .logo {
                max-width: 80px;
                height: auto;
                border-radius: 8px;
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
            .content p:first-child {
                margin-top: 0;
            }
            .divider {
                height: 1px;
                background-color: #eaeaea;
                margin: 30px 0;
            }
            .request-info {
                background-color: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .request-info h3 {
                margin-top: 0;
                color: #2c3e50;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .footer {
                background-color: #f9f9f9;
                font-size: 13px;
                text-align: center;
                padding: 25px 20px;
                color: #777;
                border-top: 1px solid #eaeaea;
            }
            .contact-info {
                margin-bottom: 10px;
            }
            .contact-info p {
                margin: 5px 0;
            }
            .copyright {
                font-size: 12px;
                color: #999;
                margin-top: 15px;
            }
            .button {
                display: inline-block;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                padding: 12px 25px;
                border-radius: 4px;
                font-weight: 500;
                margin: 15px 0;
                text-align: center;
            }
            .sender-info {
                margin-top: 30px;
                border-top: 1px dashed #e0e0e0;
                padding-top: 15px;
                font-size: 13px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <div class="logo-container">
                        ' . (!empty($universite['logo']) ? '<img src="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $universite['logo'] . '" alt="Logo Université" class="logo">' : '') . '
                    </div>
                    <h1>' . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . '</h1>
                </div>
                <div class="content">
                    <p>Cher(e) <strong>' . htmlspecialchars($etudiant['noms']) . '</strong> (Matricule: ' . htmlspecialchars($etudiant['matricule']) . '),</p>
                    
                    <p>' . nl2br(htmlspecialchars($messagePersonnalise)) . '</p>
                    
                    <div class="request-info">
                        <h3>Information sur la demande</h3>
                        <p><strong>Document demandé:</strong> ' . htmlspecialchars($etudiant['document_nom']) . '</p>
                        <p><strong>Date de la demande:</strong> ' . date('d/m/Y H:i') . '</p>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <p>Veuillez soumettre ce document dès que possible pour compléter votre dossier.</p>
                    
                    <a href="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/etudiants/gestion_scolarite" class="button">Accéder à mon espace documents</a>
                    
                    <div class="sender-info">
                        <p>Cordialement,</p>
                        <p><strong>' . htmlspecialchars($expediteur['nomsUser']) . '</strong><br>
                        ' . htmlspecialchars($expediteur['role_nom'] ?? 'Administration') . '<br>
                        ' . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . '</p>
                    </div>
                </div>
                <div class="footer">
                    <div class="contact-info">
                        <p><strong>' . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . '</strong></p>
                        <p>' . htmlspecialchars($universite['adresse'] ?? '') . '</p>
                        <p>Email: <a href="mailto:' . htmlspecialchars($universite['email'] ?? '') . '" style="color: #3498db; text-decoration: none;">' . htmlspecialchars($universite['email'] ?? '') . '</a> | Téléphone: ' . htmlspecialchars($universite['telephone'] ?? '') . '</p>
                    </div>
                    <div class="copyright">
                        &copy; ' . date('Y') . ' ' . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . '. Tous droits réservés.
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . " <" . htmlspecialchars($universite['email'] ?? 'no-reply@example.com') . ">" . "\r\n";
        $headers .= "Reply-To: " . (!empty($expediteur['email']) ? $expediteur['email'] : $universite['email']) . "\r\n";
        
        // Envoyer l'email
        return mail($to, $subject, $htmlMessage, $headers);
    }
    
    /**
     * Alternative: Utiliser PHPMailer pour l'envoi d'emails
     * Décommentez cette fonction et commentez la précédente si vous souhaitez utiliser PHPMailer
     */
    /*
    function sendDocumentRequestEmail($etudiant, $messagePersonnalise, $objetEmail, $universite, $expediteur) {
        // Vérifier si l'étudiant a une adresse email
        if (empty($etudiant['adressemail'])) {
            return false;
        }
        
        // Inclure PHPMailer
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Configuration du serveur
            $mail->isSMTP();
            $mail->Host       = 'smtp.example.com';  // Serveur SMTP
            $mail->SMTPAuth   = true;
            $mail->Username   = 'user@example.com';  // Utilisateur SMTP
            $mail->Password   = 'password';          // Mot de passe SMTP
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Destinataires
            $mail->setFrom($universite['email'] ?? 'no-reply@example.com', $universite['nom'] ?? $universite['sigle']);
            $mail->addAddress($etudiant['adressemail'], $etudiant['noms']);
            $mail->addReplyTo(!empty($expediteur['email']) ? $expediteur['email'] : $universite['email']);
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $objetEmail;
            
            // Même contenu HTML que dans la fonction précédente
            $mail->Body = '<!DOCTYPE html>...'; // Insérez le même contenu HTML que ci-dessus
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Échec de l'envoi de l'email: " . $mail->ErrorInfo);
            return false;
        }
    }
    */
    
