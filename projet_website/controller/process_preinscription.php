<?php
// Inclure le fichier de configuration de la base de données
require_once dirname(__DIR__) . '/config/Connexion.php';

$db = Connexion::getInstance()->getPDO();
// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Fonction pour uploader un fichier
function uploadFile($file, $directory, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    
    // Vérifier le type de fichier
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Type de fichier non autorisé: " . $file_type);
    }
    
    // Vérifier la taille du fichier (max 2 Mo)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception("Le fichier est trop volumineux (max 2 Mo)");
    }
    
    // Générer un nom de fichier unique
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_extension;
    $file_path = $directory . $file_name;
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception("Erreur lors de l'upload du fichier");
    }
    
    return $file_name;
}

try {
    
    
    // Générer une référence unique pour la pré-inscription
    $reference = 'INBTP-' . date('Y') . '-' . uniqid();
    
    // Récupérer les données du formulaire
    $nom = $_POST['nom'];
    $postnom = $_POST['postnom'];
    $prenom = $_POST['prenom'];
    $lieu_naissance = $_POST['lieu_naissance'];
    $date_naissance = $_POST['date_naissance'];
    $sexe = $_POST['sexe'];
    $etat_civil = $_POST['etat_civil'];
    $nationalite = $_POST['nationalite'];
    $nom_pere = $_POST['nom_pere'];
    $nom_mere = $_POST['nom_mere'];
    $province = $_POST['province'];
    $district = $_POST['district'];
    $territoire = $_POST['territoire'];
    $secteur = $_POST['secteur'];
    $avenue = $_POST['avenue'];
    $numero = $_POST['numero'];
    $quartier = $_POST['quartier'];
    $commune = $_POST['commune'];
    $telephone = $_POST['telephone'];
    $email = $_POST['email'];
    $personne_contact = $_POST['personne_contact'];
    $telephone_contact = $_POST['telephone_contact'];
    
    // Données études secondaires
    $ecole_secondaire = $_POST['ecole_secondaire'];
    $adresse_ecole = $_POST['adresse_ecole'];
    $section_humanites = $_POST['section_humanites'];
    $option_humanites = $_POST['option_humanites'];
    $centre_examen = $_POST['centre_examen'];
    $annee_diplome = $_POST['annee_diplome'];
    $lieu_date_diplome = $_POST['lieu_date_diplome'];
    $pourcentage = $_POST['pourcentage'];
    $numero_diplome = $_POST['numero_diplome'];
    $activites_professionnelles = $_POST['activites_professionnelles'];
    $etudes_post_secondaires = $_POST['etudes_post_secondaires'];
    
    // Type d'inscription
    $type_inscription = $_POST['type_inscription'];
    
    // Traitement des répertoires pour les uploads
    $upload_dir = '../uploads/preinscriptions/';
    
    // Créer le répertoire s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Créer un sous-répertoire pour cette pré-inscription
    $preinscription_dir = $upload_dir . $reference . '/';
    mkdir($preinscription_dir, 0777, true);

    // Initialisation des variables
    $section_id = null;
    $orientation_choix1 = null;
    $orientation_choix2 = null;
    $matricule = null;
    $annee_academique_precedente = null;
    $promotion_precedente = null;
    $type_reinscription = null;
    $nouvelle_section_id = null;
    $motif_changement = null;
    $annee_abandon = null;
    $motif_abandon = null;
    $motif_reintegration = null;
    $universite = null;
    $faculte = null;
    $diplome_licence = null;
    $releve_notes_licence = null;
    $memoire_licence = null;
    $photo = null;
    $attestation_naissance = null;
    $diplome_etat = null;
    $bulletin_5eme = null;
    $bulletin_6eme = null;
    $attestation_aptitude = null;
    $preuve_paiement = null;
    $releve_notes = null;
    $attestation_reussite = null;
    $attestation_aptitude_master = null;

    // Données spécifiques selon le type d'inscription
    if ($type_inscription === 'Nouvelle inscription - Préparatoire') {
        $section_id = $_POST['section_id'];
        $orientation_choix1 = $_POST['orientation_choix1'];
        $orientation_choix2 = $_POST['orientation_choix2'];
        
        // Documents pour Préparatoire
        $photo = uploadFile($_FILES['photo'], $preinscription_dir, ['image/jpeg', 'image/png']);
        $attestation_naissance = uploadFile($_FILES['attestation_naissance'], $preinscription_dir);
        $diplome_etat = uploadFile($_FILES['diplome_etat'], $preinscription_dir);
        $bulletin_5eme = uploadFile($_FILES['bulletin_5eme'], $preinscription_dir);
        $bulletin_6eme = uploadFile($_FILES['bulletin_6eme'], $preinscription_dir);
        $attestation_aptitude = uploadFile($_FILES['attestation_aptitude'], $preinscription_dir);
        $preuve_paiement = uploadFile($_FILES['preuve_paiement'], $preinscription_dir);
    } 
    else if ($type_inscription === 'Nouvelle inscription - Master 1') {
        $section_id = $_POST['section_id'];
        $orientation_choix1 = $_POST['orientation_choix1'];
        $orientation_choix2 = $_POST['orientation_choix2'];
        
        // Données universitaires pour Master 1
        $universite = $_POST['universite'];
        $faculte = $_POST['faculte'];
        
        // Documents pour Master 1
        $photo = uploadFile($_FILES['photo'], $preinscription_dir, ['image/jpeg', 'image/png']);
        $attestation_naissance = uploadFile($_FILES['attestation_naissance'], $preinscription_dir);
        $diplome_licence = uploadFile($_FILES['diplome_licence'], $preinscription_dir);
        $releve_notes_licence = uploadFile($_FILES['releve_notes_licence'], $preinscription_dir);
        $attestation_aptitude_master = uploadFile($_FILES['attestation_aptitude_master'], $preinscription_dir);
        $preuve_paiement = uploadFile($_FILES['preuve_paiement'], $preinscription_dir);
        
        // Mémoire (facultatif)
        if (isset($_FILES['memoire_licence']) && !empty($_FILES['memoire_licence']['tmp_name'])) {
            $memoire_licence = uploadFile($_FILES['memoire_licence'], $preinscription_dir);
        }
    } 
    else {
        // Réinscription
        $matricule = $_POST['matricule'];
        $annee_academique_precedente = $_POST['annee_academique_precedente'];
        $promotion_precedente = $_POST['promotion_precedente'];
        $type_reinscription = $_POST['type_reinscription'];
        
        // Documents pour Réinscription
        $photo = uploadFile($_FILES['photo'], $preinscription_dir, ['image/jpeg', 'image/png']);
        $attestation_naissance = uploadFile($_FILES['attestation_naissance'], $preinscription_dir);
        $releve_notes = uploadFile($_FILES['releve_notes'], $preinscription_dir);
        $preuve_paiement = uploadFile($_FILES['preuve_paiement'], $preinscription_dir);
        
        // Attestation de réussite (facultatif)
        if (isset($_FILES['attestation_reussite']) && !empty($_FILES['attestation_reussite']['tmp_name'])) {
            $attestation_reussite = uploadFile($_FILES['attestation_reussite'], $preinscription_dir);
        }
        
        if ($type_reinscription === 'Changement de section') {
            $nouvelle_section_id = $_POST['nouvelle_section_id'];
            $motif_changement = $_POST['motif_changement'];
        } 
        elseif ($type_reinscription === 'Réintégration') {
            $annee_abandon = $_POST['annee_abandon'];
            $motif_abandon = $_POST['motif_abandon'];
            $motif_reintegration = $_POST['motif_reintegration'];
        }
        
        // Traitement des données spécifiques pour le passage en L1
        if ($type_reinscription === 'Passage en L1') {
            $orientation_choix1 = $_POST['orientation_choix1_l1'];
            $orientation_choix2 = $_POST['orientation_choix2_l1'];
        }
    }
    
    // Uploader les documents additionnels si présents
    $documents_additionnels = [];
    if (isset($_FILES['documents_additionnels']) && is_array($_FILES['documents_additionnels']['name'])) {
        $file_count = count($_FILES['documents_additionnels']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if (!empty($_FILES['documents_additionnels']['tmp_name'][$i])) {
                $file = [
                    'name' => $_FILES['documents_additionnels']['name'][$i],
                    'type' => $_FILES['documents_additionnels']['type'][$i],
                    'tmp_name' => $_FILES['documents_additionnels']['tmp_name'][$i],
                    'error' => $_FILES['documents_additionnels']['error'][$i],
                    'size' => $_FILES['documents_additionnels']['size'][$i]
                ];
                
                $documents_additionnels[] = uploadFile($file, $preinscription_dir);
            }
        }
    }
    
    // Récupérer l'année académique actuelle
    $stmt = $db->prepare("SELECT idannee_acad FROM annee_acad ORDER BY dateCreation DESC LIMIT 1");
    $stmt->execute();
    $annee_acad_id = $stmt->fetchColumn();
    
    if (!$annee_acad_id) {
        // Si l'année académique n'existe pas, on crée une nouvelle entrée
        $annee_actuelle = date('Y') . '-' . (date('Y') + 1);
        $stmt = $db->prepare("INSERT INTO annee_acad (designation, dateCreation) VALUES (?, NOW())");
        $stmt->execute([$annee_actuelle]);
        $annee_acad_id = $db->lastInsertId();
    }
    
    // Insérer les données dans la table de pré-inscription
    $stmt = $db->prepare("
    INSERT INTO preinscription (
        reference, nom, postnom, prenom, lieu_naissance, date_naissance, sexe, etat_civil, nationalite,
        nom_pere, nom_mere, province, district, territoire, secteur, avenue, numero, quartier, commune,
        telephone, email, personne_contact, telephone_contact, ecole_secondaire, adresse_ecole,
        section_humanites, option_humanites, centre_examen, annee_diplome, lieu_date_diplome,
        pourcentage, numero_diplome, activites_professionnelles, etudes_post_secondaires,
        type_inscription, section_id, orientation_choix1, orientation_choix2, matricule,
        annee_academique_precedente, promotion_precedente, type_reinscription, nouvelle_section_id,
        motif_changement, annee_abandon, motif_abandon, motif_reintegration, photo, attestation_naissance,
        diplome_etat, bulletin_5eme, bulletin_6eme, attestation_aptitude, preuve_paiement, releve_notes,
        attestation_reussite, documents_additionnels, signature_electronique, date_signature, annee_acad_id,
        universite, faculte, diplome_licence, releve_notes_licence, memoire_licence, attestation_aptitude_master
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
    ");

    $stmt->execute([
        $reference, $nom, $postnom, $prenom, $lieu_naissance, $date_naissance, $sexe, $etat_civil, $nationalite,
        $nom_pere, $nom_mere, $province, $district, $territoire, $secteur, $avenue, $numero, $quartier, $commune,
        $telephone, $email, $personne_contact, $telephone_contact, $ecole_secondaire, $adresse_ecole,
        $section_humanites, $option_humanites, $centre_examen, $annee_diplome, $lieu_date_diplome,
        $pourcentage, $numero_diplome, $activites_professionnelles, $etudes_post_secondaires,
        $type_inscription, $section_id, $orientation_choix1, $orientation_choix2, $matricule,
        $annee_academique_precedente, $promotion_precedente, $type_reinscription, $nouvelle_section_id,
        $motif_changement, $annee_abandon, $motif_abandon, $motif_reintegration, $photo, $attestation_naissance,
        $diplome_etat, $bulletin_5eme, $bulletin_6eme, $attestation_aptitude, $preuve_paiement, $releve_notes,
        $attestation_reussite, json_encode($documents_additionnels), $_POST['signature_electronique'], $_POST['date_signature'], $annee_acad_id,
        $universite, $faculte, $diplome_licence, $releve_notes_licence, $memoire_licence, $attestation_aptitude_master
    ]);
    
    // Valider la transaction
    
    
    /*
    // Envoyer un email de confirmation
    $to = $email;
    $subject = "Confirmation de pré-inscription à l'INBTP";
    
    $message = "
    <html>
    <head>
        <title>Confirmation de pré-inscription à l'INBTP</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <img src='' alt='Logo INBTP' style='max-width: 150px;'>
            </div>
            
            <h2 style='color: #003366; text-align: center;'>Confirmation de pré-inscription</h2>
            
            <p>Cher(e) <strong>$prenom $nom</strong>,</p>
            
            <p>Nous vous remercions pour votre demande de pré-inscription à l'Institut National du Bâtiment et des Travaux Publics (INBTP).</p>
            
            <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>Référence de votre demande :</strong> $reference</p>
            </div>
            
            <p>Votre demande a été enregistrée avec succès et sera examinée par notre service des admissions dans les plus brefs délais.</p>
            
            <p>Veuillez conserver précieusement cette référence, elle vous sera demandée pour toute communication concernant votre dossier.</p>
            
            <h3 style='color: #003366;'>Prochaines étapes :</h3>
            
            <ol>
                <li>Examen de votre dossier par le service des admissions (délai : environ 2 semaines)</li>
                <li>Notification par email de l'acceptation ou du rejet de votre demande</li>
                <li>En cas d'acceptation, instructions pour finaliser votre inscription</li>
            </ol>
            
            <p>Pour toute question concernant votre pré-inscription, veuillez contacter le service des admissions :</p>
            <ul>
                <li>Email : admissions@inbtp.edu.cd</li>
                <li>Téléphone : +243 123 456 789</li>
            </ul>
            
            <p>Vous pouvez également suivre l'état de votre demande en ligne en vous rendant sur notre site web et en saisissant votre référence.</p>
            
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #666; font-size: 12px;'>Institut National du Bâtiment et des Travaux Publics<br>
                123 Avenue de l'INBTP, Commune de Limete<br>
                Kinshasa, République Démocratique du Congo</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // En-têtes pour l'email HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: INBTP <info@inbtpkinshasa.info>' . "\r\n";
    
    // Envoyer l'email
    mail($to, $subject, $message, $headers);
    */
    
    // Retourner une réponse de succès
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'reference' => $reference]);
    
} catch (Exception $e) {
    
    
    // Supprimer le répertoire créé pour les fichiers si nécessaire
    if (isset($preinscription_dir) && file_exists($preinscription_dir)) {
        // Fonction récursive pour supprimer un répertoire et son contenu
        function deleteDirectory($dir) {
            if (!file_exists($dir)) {
                return true;
            }
            
            if (!is_dir($dir)) {
                return unlink($dir);
            }
            
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                
                if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                    return false;
                }
            }
            
            return rmdir($dir);
        }
        
        deleteDirectory($preinscription_dir);
    }
    
    // Retourner une réponse d'erreur
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
