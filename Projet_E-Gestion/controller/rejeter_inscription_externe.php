<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID manquant.'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/inscriptions_externes';
        });
    </script>";
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    $id = intval($_GET['id']);
    $idUser = $_SESSION['id'];
    $motifRejet = isset($_GET['motif']) ? $_GET['motif'] : 'Dossier incomplet ou non conforme';
    
    // Commencer une transaction
    $connexion->beginTransaction();
    
    // Récupérer les informations de l'inscription externe
    $stmt = $connexion->prepare("
        SELECT ie.*, lie.promotion_id, lie.annee_acad_id, lie.titre as titre_lien,
               p.\"designationPromotion\" as nom_promotion, aa.designation as annee_academique
        FROM inscriptions_externes ie
        JOIN liens_inscription_externe lie ON ie.lien_inscription_id = lie.id
        LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
        LEFT JOIN annee_acad aa ON lie.annee_acad_id = aa.idannee_acad
        WHERE ie.id = ? AND ie.statut != 'Rejetée'
    ");
    $stmt->execute([$id]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscription) {
        $connexion->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Inscription non trouvée ou déjà rejetée.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/inscriptions_externes';
            });
        </script>";
        exit();
    }
    
    // Mettre à jour le statut de l'inscription externe
    $stmt = $connexion->prepare("
        UPDATE inscriptions_externes 
        SET statut = 'Rejetée', 
            date_validation = NOW(), 
            id_validateur = ?,
            commentaire_admin = ?
        WHERE id = ?
    ");
    $stmt->execute([$idUser, $motifRejet, $id]);
    
    // Envoyer l'email de rejet
    $emailEnvoye = envoyerEmailRejet($inscription, $motifRejet);
    
    // Valider la transaction
    $connexion->commit();
    
    $messageSucces = "L'inscription a été rejetée avec succès.";
    if ($emailEnvoye) {
        $messageSucces .= " Un email de notification a été envoyé à l'étudiant.";
    } else {
        $messageSucces .= " Attention: L'email de notification n'a pas pu être envoyé.";
    }
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '" . addslashes($messageSucces) . "'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/inscriptions_externes';
        });
    </script>";
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors du rejet : " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/inscriptions_externes';
        });
    </script>";
}

// Fonction pour envoyer l'email de rejet
function envoyerEmailRejet($inscription, $motifRejet) {
    try {
        $to = $inscription['email'];
        $subject = 'Inscription rejetée - INBTP Kinshasa';
        
        // Construire le contenu HTML de l'email
        $htmlMessage = genererCorpsEmailRejet($inscription, $motifRejet);
        
        // Configuration de l'entreprise
        $entreprise = [
            'nom' => 'INBTP Kinshasa',
            'email' => 'scolarite@istmbeni.ac.cd'
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

// Fonction pour générer le corps de l'email de rejet en HTML
function genererCorpsEmailRejet($inscription, $motifRejet) {
    $urlPortail = "https://std-ucg-butembo.wscsarl.info/login";
    
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
                    <p>Email: <a href="mailto:scolarite@istmbeni.ac.cd" style="color: #4299e1; text-decoration: none;">scolarite@istmbeni.ac.cd</a></p>
                    <p>Portail: <a href="' . $urlPortail . '" style="color: #4299e1; text-decoration: none;">std-ucg-butembo.wscsarl.info</a></p>
                    <p>&copy; ' . date('Y') . ' INBTP Kinshasa. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $htmlMessage;
}
?>