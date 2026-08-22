<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
    $objetEmail = isset($_POST['objet_email']) ? trim($_POST['objet_email']) : '';
    $contenuEmail = isset($_POST['contenu_email']) ? trim($_POST['contenu_email']) : '';
    $copieMoi = isset($_POST['copie_moi']) ? true : false;
    
    if (empty($promotionId) || empty($objetEmail) || empty($contenuEmail)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs sont obligatoires.'
            }).then(() => {
                window.location.href = '../?view=enseignement/suivi_documents_etudiants&promotion=" . $promotionId . "';
            });
        </script>";
        exit();
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Récupérer le cycle de la promotion
        $stmt = $conn->prepare("SELECT cycle FROM promotion WHERE idpromotion = ?");
        $stmt->execute([$promotionId]);
        $cyclePromo = $stmt->fetchColumn();
        
        // Récupérer les documents obligatoires pour ce cycle
        $stmt = $conn->prepare("
            SELECT * FROM documents_obligatoires 
            WHERE cycle = ? OR cycle = 'Tous'
            ORDER BY designation
        ");
        $stmt->execute([$cyclePromo]);
        $documentsObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les étudiants de cette promotion
        $stmt = $conn->prepare("
            SELECT e.idetudiant, e.matricule, e.noms, e.adressemail
            FROM etudiant e
            WHERE e.promotion_idpromotion = ? AND e.adressemail IS NOT NULL AND e.adressemail != ''
            ORDER BY e.noms
        ");
        $stmt->execute([$promotionId]);
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les informations de l'université
        $stmtUniv = $conn->prepare("SELECT * FROM configuration_universite LIMIT 1");
        $stmtUniv->execute();
        $universite = $stmtUniv->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les documents fournis par ces étudiants
        $documentsParEtudiant = [];
        $etudiantsAvertir = [];
        
        if (!empty($etudiants) && !empty($documentsObligatoires)) {
            $matricules = array_column($etudiants, 'matricule');
            $placeholders = str_repeat('?,', count($matricules) - 1) . '?';
            
            $stmt = $conn->prepare("
                SELECT ed.*, do.id as doc_obligatoire_id
                FROM etudiant_documents ed
                LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
                WHERE ed.matricule IN ($placeholders)
            ");
            $stmt->execute($matricules);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($documents as $doc) {
                if (!isset($documentsParEtudiant[$doc['matricule']])) {
                    $documentsParEtudiant[$doc['matricule']] = [];
                }
                
                if ($doc['document_obligatoire_id']) {
                    $documentsParEtudiant[$doc['matricule']][$doc['document_obligatoire_id']] = $doc['statut'];
                }
            }
            
            // Identifier les étudiants qui ont des documents manquants ou rejetés
            foreach ($etudiants as $etudiant) {
                $docsManquants = [];
                
                foreach ($documentsObligatoires as $doc) {
                    $statut = isset($documentsParEtudiant[$etudiant['matricule']][$doc['id']]) 
                           ? $documentsParEtudiant[$etudiant['matricule']][$doc['id']] 
                           : null;
                    
                    if (!$statut || $statut == 'Rejeté') {
                        $docsManquants[] = $doc['designation'];
                    }
                }
                
                if (!empty($docsManquants)) {
                    $etudiantsAvertir[] = [
                        'idetudiant' => $etudiant['idetudiant'],
                        'matricule' => $etudiant['matricule'],
                        'nom' => $etudiant['noms'],
                        'email' => $etudiant['adressemail'],
                        'documents_manquants' => $docsManquants
                    ];
                }
            }
        }
        
        if (empty($etudiantsAvertir)) {
            echo "<script>
                Swal.fire({
                    icon: 'info',
                    title: 'Information',
                    text: 'Aucun étudiant n\'a de documents manquants ou rejetés.'
                }).then(() => {
                    window.location.href = '../?view=enseignement/suivi_documents_etudiants&promotion=" . $promotionId . "';
                });
            </script>";
            exit();
        }
        
        // Envoyer les emails de rappel
        $emailsEnvoyes = 0;
        $emailsEnErreur = 0;
        
        foreach ($etudiantsAvertir as $etudiant) {
            // Préparer le contenu personnalisé
            $listeDocuments = '<ul style="padding-left: 20px;">';
            foreach ($etudiant['documents_manquants'] as $doc) {
                $listeDocuments .= '<li>' . htmlspecialchars($doc) . '</li>';
            }
            $listeDocuments .= '</ul>';
            
            $contenuPersonnalise = str_replace('[LISTE_DOCUMENTS]', $listeDocuments, $contenuEmail);
            $contenuPersonnalise = str_replace('[NOM_ETUDIANT]', $etudiant['nom'], $contenuPersonnalise);
            $contenuPersonnalise = str_replace('[MATRICULE]', $etudiant['matricule'], $contenuPersonnalise);
            
            // Envoyer l'email avec le modèle HTML amélioré
            $envoi = sendReminderEmail($etudiant, $contenuPersonnalise, $objetEmail, $universite, $copieMoi ? $_SESSION['email'] : null);
            
            if ($envoi) {
                $emailsEnvoyes++;
                
                // Enregistrer l'envoi dans la base de données
                $stmt = $conn->prepare("
                    INSERT INTO notifications_documents 
                    (idetudiant, matricule, objet, contenu, date_envoi, \"idUser\") 
                    VALUES (?, ?, ?, ?, NOW(), ?)
                ");
                
                $stmt->execute([
                    $etudiant['idetudiant'],
                    $etudiant['matricule'],
                    $objetEmail,
                    $contenuPersonnalise,
                    $_SESSION['id']
                ]);
            } else {
                $emailsEnErreur++;
            }
        }
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Opération terminée',
                text: 'Emails envoyés: " . $emailsEnvoyes . " | Emails en erreur: " . $emailsEnErreur . "'
            }).then(() => {
                window.location.href = '../?view=enseignement/suivi_documents_etudiants&promotion=" . $promotionId . "';
            });
        </script>";
    } catch (PDOException $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../?view=enseignement/suivi_documents_etudiants&promotion=" . $promotionId . "';
            });
        </script>";
    }
} else {
    header("Location: ../index.php");
    exit();
}

/**
 * Envoie un email de rappel à l'étudiant concernant les documents manquants
 * 
 * @param array $etudiant Informations de l'étudiant
 * @param string $contenuPersonnalise Contenu personnalisé du message
 * @param string $objetEmail Objet de l'email
 * @param array $universite Informations de l'université
 * @param string|null $copieEmail Adresse email à mettre en copie (optionnel)
 * @return bool Succès de l'envoi
 */
function sendReminderEmail($etudiant, $contenuPersonnalise, $objetEmail, $universite, $copieEmail = null) {
    // Vérifier si l'étudiant a une adresse email
    if (empty($etudiant['email'])) {
        return false;
    }
    
    $to = $etudiant['email'];
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
                max-width: 180px;
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
            .documents-list {
                background-color: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .documents-list h3 {
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
                    <p>Cher(e) <strong>' . htmlspecialchars($etudiant['nom']) . '</strong> (Matricule: ' . htmlspecialchars($etudiant['matricule']) . '),</p>
                    
                                        <p>' . nl2br(htmlspecialchars($contenuPersonnalise)) . '</p>
                    
                    <div class="divider"></div>
                    
                    <p style="font-style: italic; color: #777;">Veuillez soumettre ces documents dès que possible pour compléter votre dossier.</p>
                    
                    <a href="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/etudiants/gestion_scolarite" class="button">Accéder à mon espace documents</a>
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
    
    // Ajouter le destinataire en copie si demandé
    if (!empty($copieEmail)) {
        $headers .= "Cc: " . $copieEmail . "\r\n";
    }
    
    // Envoyer l'email
    return mail($to, $subject, $htmlMessage, $headers);
}
