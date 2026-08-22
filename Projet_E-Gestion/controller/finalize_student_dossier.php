<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier les données
if (!isset($data['studentId']) || !isset($data['matricule']) || !isset($data['anneeAcadId'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$studentId = intval($data['studentId']);
$matricule = trim($data['matricule']);
$anneeAcadId = intval($data['anneeAcadId']);
$pdo = Connexion::getInstance()->getPDO();

try {
    // Vérifier si l'étudiant existe
    $stmtStudent = $pdo->prepare("
        SELECT e.*, p.idpromotion, p.\"designationPromotion\", a.designation as annee_academique,
               s.\"designationSection\", o.\"designationOrientation\"
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        WHERE e.idetudiant = :studentId AND e.matricule = :matricule
    ");
    
    $stmtStudent->execute([
        'studentId' => $studentId,
        'matricule' => $matricule
    ]);
    
    $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit;
    }
    
    // Récupérer les documents soumis par l'étudiant
    $stmtDocs = $pdo->prepare("
        SELECT ed.*, do.designation, do.est_obligatoire
        FROM etudiant_documents ed
        LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
        WHERE ed.idetudiant = :studentId AND ed.annee_acad_id = :anneeAcadId
    ");
    
    $stmtDocs->execute([
        'studentId' => $studentId,
        'anneeAcadId' => $anneeAcadId
    ]);
    
    $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les informations de l'université
    $stmtUniv = $pdo->prepare("SELECT * FROM configuration_universite LIMIT 1");
    $stmtUniv->execute();
    $universite = $stmtUniv->fetch(PDO::FETCH_ASSOC);
    
    // Marquer l'étudiant comme ayant complété son dossier
    $stmt = $pdo->prepare("
        UPDATE etudiant
        SET dossier_complete = 1
        WHERE idetudiant = :studentId
    ");
    $stmt->execute(['studentId' => $studentId]);
    
    // Envoyer un email de confirmation
    $emailSent = sendConfirmationEmail($student, $documents, $universite);
    
    // Journaliser l'action
    $stmt = $pdo->prepare("
        INSERT INTO journal_activites 
        (user_type, user_id, type_activite, id_element, description, date_activite)
        VALUES 
        ('etudiant', :studentId, 'dossier_complete', :studentId, :description, NOW())
    ");
    
    $stmt->execute([
        'studentId' => $studentId,
        'description' => "L'étudiant {$student['noms']} (Matricule: {$matricule}) a complété son dossier."
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Dossier finalisé avec succès',
        'emailSent' => $emailSent
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}

/**
 * Envoie un email de confirmation à l'étudiant avec un résumé de ses informations
 */
function sendConfirmationEmail($student, $documents, $universite) {
    // Vérifier si l'étudiant a une adresse email
    if (empty($student['adressemail'])) {
        return false;
    }
    
    $to = $student['adressemail'];
    $subject = "Confirmation de soumission de dossier - " . $universite['sigle'];
    
    // Préparer la liste des documents soumis
    $documentsList = '';
    if (!empty($documents)) {
        $documentsList .= '<ul style="padding-left: 20px;">';
        foreach ($documents as $doc) {
            $documentsList .= '<li>' . htmlspecialchars($doc['designation']) . ' <span style="color: green;">(Soumis)</span></li>';
        }
        $documentsList .= '</ul>';
    } else {
        $documentsList = '<p><em>Aucun document soumis.</em></p>';
    }
    
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
            .student-info {
                background-color: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .student-info h3 {
                margin-top: 0;
                color: #2c3e50;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .info-row {
                display: flex;
                margin-bottom: 10px;
            }
            .info-label {
                font-weight: 600;
                width: 40%;
                color: #555;
            }
            .info-value {
                width: 60%;
            }
            .divider {
                height: 1px;
                background-color: #eaeaea;
                margin: 30px 0;
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
            @media only screen and (max-width: 600px) {
                .info-row {
                    flex-direction: column;
                }
                .info-label, .info-value {
                    width: 100%;
                }
                .info-label {
                    margin-bottom: 5px;
                }
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
                    <p>Cher(e) <strong>' . htmlspecialchars($student['noms']) . '</strong>,</p>
                    
                    <p>Nous vous confirmons que votre dossier a été soumis avec succès. Vous trouverez ci-dessous un récapitulatif de vos informations et des documents soumis.</p>
                    
                    <div class="student-info">
                        <h3>Informations personnelles</h3>
                        <div class="info-row">
                            <div class="info-label">Matricule:</div>
                            <div class="info-value">' . htmlspecialchars($student['matricule']) . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Nom complet:</div>
                            <div class="info-value">' . htmlspecialchars($student['noms']) . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date de naissance:</div>
                            <div class="info-value">' . ($student['dateNaissance'] ? date('d/m/Y', strtotime($student['dateNaissance'])) : 'Non renseignée') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Lieu de naissance:</div>
                            <div class="info-value">' . htmlspecialchars($student['lieuNaissance'] ?? 'Non renseigné') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Sexe:</div>
                            <div class="info-value">' . htmlspecialchars($student['sexe'] ?? 'Non renseigné') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Nationalité:</div>
                            <div class="info-value">' . htmlspecialchars($student['nationalite'] ?? 'Non renseignée') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value">' . htmlspecialchars($student['adressemail']) . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Téléphone:</div>
                            <div class="info-value">' . htmlspecialchars($student['telephone'] ?? 'Non renseigné') . '</div>
                        </div>
                    </div>
                    
                    <div class="student-info">
                        <h3>Informations académiques</h3>
                        <div class="info-row">
                            <div class="info-label">Année académique:</div>
                            <div class="info-value">' . htmlspecialchars($student['annee_academique'] ?? 'Non renseignée') . '</div>
                        </div>
                                                <div class="info-row">
                            <div class="info-label">Section:</div>
                            <div class="info-value">' . htmlspecialchars($student['designationSection'] ?? 'Non renseignée') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Orientation:</div>
                            <div class="info-value">' . htmlspecialchars($student['designationOrientation'] ?? 'Non renseignée') . '</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Promotion:</div>
                            <div class="info-value">' . htmlspecialchars($student['designationPromotion'] ?? 'Non renseignée') . '</div>
                        </div>
                    </div>
                    
                    <div class="student-info">
                        <h3>Documents soumis</h3>
                        ' . $documentsList . '
                    </div>
                    
                    <div class="divider"></div>
                    
                    <p>Votre dossier est maintenant complet et sera examiné par notre administration. Vous pouvez suivre l\'état de votre dossier en vous connectant à votre espace étudiant.</p>
                    
                    <p style="font-style: italic; color: #777;">Si vous avez des questions supplémentaires, n\'hésitez pas à contacter notre service de scolarité.</p>
                    
                    <a href="' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/portail/login" class="button">Accéder à mon espace étudiant</a>
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
    $headers .= "From: " . htmlspecialchars($universite['nom'] ?? $universite['sigle']) . " <" . htmlspecialchars($universite['email'] ?? 'no-reply@istmbeni.ac.cd') . ">" . "\r\n";
    
    // Envoyer l'email
    return mail($to, $subject, $htmlMessage, $headers);
}

