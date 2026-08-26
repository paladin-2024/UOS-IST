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
        WHERE ie.id = ? AND ie.statut != 'Validée'
    ");
    $stmt->execute([$id]);
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscription) {
        $connexion->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Inscription non trouvée ou déjà validée.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/inscriptions_externes';
            });
        </script>";
        exit();
    }
    
    // Vérifier si l'étudiant n'existe pas déjà (par email)
    $stmt = $connexion->prepare("SELECT idetudiant FROM etudiant WHERE adressemail = ?");
    $stmt->execute([$inscription['email']]);
    $etudiantExistant = $stmt->fetch();
    
    if ($etudiantExistant) {
        $connexion->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Un étudiant avec cette adresse email existe déjà dans le système.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/inscriptions_externes';
            });
        </script>";
        exit();
    }
    
    // Générer un matricule unique
    $anneeActuelle = date('Y');
    $stmt = $connexion->prepare("
        SELECT COUNT(*) as nb_etudiants 
        FROM etudiant 
        WHERE EXTRACT(YEAR FROM \"dateEnregistrement\") = ?
    ");
    $stmt->execute([$anneeActuelle]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $numeroSequentiel = $result['nb_etudiants'] + 1;
    
    // Format du matricule: ANNEE-NUMERO (ex: 2024-0001)
    $matricule = $anneeActuelle . '-' . str_pad($numeroSequentiel, 4, '0', STR_PAD_LEFT);
    
    // Vérifier l'unicité du matricule (au cas où)
    $stmt = $connexion->prepare("SELECT idetudiant FROM etudiant WHERE matricule = ?");
    $stmt->execute([$matricule]);
    if ($stmt->fetch()) {
        // Si le matricule existe déjà, utiliser un timestamp pour garantir l'unicité
        $matricule = $anneeActuelle . '-' . str_pad($numeroSequentiel, 4, '0', STR_PAD_LEFT) . '-' . time();
    }
    
    // Construire le nom complet
    $nomComplet = trim($inscription['nom'] . ' ' . $inscription['postnom'] . ' ' . $inscription['prenom']);
    
    // Insérer l'étudiant dans la table etudiant
    $stmt = $connexion->prepare("
        INSERT INTO etudiant (
            matricule,
            noms,
            \"lieuNaissance\",
            \"dateNaissance\",
            adressemail,
            telephone,
            adresse,
            personne_contact,
            telephone_contact,
            sexe,
            nationalite,
            \"dateEnregistrement\",
            annee_acad_idannee_acad,
            promotion_idpromotion,
            \"idUser\",
            est_actif,
            dossier_complete
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 1, 0)
    ");
    
    $stmt->execute([
        $matricule,
        $nomComplet,
        $inscription['lieu_naissance'],
        $inscription['date_naissance'],
        $inscription['email'],
        $inscription['telephone'],
        $inscription['adresse_complete'],
        $inscription['personne_contact'],
        $inscription['telephone_contact'],
        $inscription['sexe'],
        $inscription['nationalite'],
        $inscription['annee_acad_id'],
        $inscription['promotion_id'],
        $idUser
    ]);
    
    $idEtudiantCree = $connexion->lastInsertId();
    
    // Mettre à jour le statut de l'inscription externe
    $stmt = $connexion->prepare("
        UPDATE inscriptions_externes 
        SET statut = 'Validée', 
            date_validation = NOW(), 
            id_validateur = ?,
            commentaire_admin = CONCAT(COALESCE(commentaire_admin, ''), 'Étudiant créé avec matricule: " . $matricule . " (ID: " . $idEtudiantCree . ")')
        WHERE id = ?
    ");
    $stmt->execute([$idUser, $id]);
    
    // Envoyer l'email de validation
    $emailEnvoye = envoyerEmailValidation($inscription, $matricule, $idEtudiantCree);
    
    // Valider la transaction
    $connexion->commit();
    
    $messageSucces = "L'inscription a été validée avec succès.<br><strong>Matricule généré:</strong> " . $matricule;
    if ($emailEnvoye) {
        $messageSucces .= "<br><small>Un email de confirmation a été envoyé à l'étudiant.</small>";
    } else {
        $messageSucces .= "<br><small style='color: orange;'>Attention: L'email de confirmation n'a pas pu être envoyé.</small>";
    }
    
    echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            html: '" . addslashes($messageSucces) . "'
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
            text: 'Erreur lors de la validation : " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../index.php?view=etudiants/inscriptions_externes';
        });
    </script>";
}

// Fonction pour envoyer l'email de validation
function envoyerEmailValidation($inscription, $matricule, $idEtudiant) {
    try {
        $to = $inscription['email'];
        $subject = 'Inscription validée - Bienvenue à l\'ISTM-BENI';
        
        // Construire le contenu HTML de l'email
        $htmlMessage = genererCorpsEmailValidation($inscription, $matricule, $idEtudiant);
        
        // Configuration de l'entreprise
        $entreprise = [
            'nom' => 'ISTM-BENI',
            'email' => 'scolarite@istmbeni.ac.cd'
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

// Fonction pour générer le corps de l'email de validation en HTML
function genererCorpsEmailValidation($inscription, $matricule, $idEtudiant) {
    $urlPortail = "https://std.ucg-butembo.net/login";
    
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
                        <p>Nous avons le plaisir de vous informer que votre demande d\'inscription à l\'<strong>ISTM-BENI</strong> a été validée avec succès !</p>
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
                    
                    <p>Nous vous souhaitons la bienvenue dans la famille ISTM-BENI et vous souhaitons une excellente année académique !</p>
                    
                    <p>Cordialement,<br>
                    Le Service des Admissions<br>
                    <strong>ISTM-BENI</strong></p>
                </div>
                
                <div class="footer">
                    <p><strong>Institut National du Bâtiment et des Travaux Publics</strong></p>
                    <p>ISTM-BENI</p>
                    <p>Email: <a href="mailto:scolarite@istmbeni.ac.cd" style="color: #4299e1; text-decoration: none;">scolarite@istmbeni.ac.cd</a></p>
                    <p>Portail: <a href="' . $urlPortail . '" style="color: #4299e1; text-decoration: none;">std.ucg-butembo.net</a></p>
                    <p>&copy; ' . date('Y') . ' ISTM-BENI. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $htmlMessage;
}
?>