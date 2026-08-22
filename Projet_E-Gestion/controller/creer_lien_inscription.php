<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération des données du formulaire
    $titre = isset($_POST['titre']) ? trim($_POST['titre']) : '';
    $reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $promotion_id = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
    $annee_acad_id = isset($_POST['annee_acad_id']) ? intval($_POST['annee_acad_id']) : 0;
    $date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : '';
    $date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : '';
    $max_inscriptions = !empty($_POST['max_inscriptions']) ? intval($_POST['max_inscriptions']) : null;
    $couleur_theme = $_POST['couleur_theme'] ?? '#007bff';
    $utiliser_docs_defaut = isset($_POST['utiliser_docs_defaut']) ? 1 : 0;
    $message_accueil = isset($_POST['message_accueil']) ? trim($_POST['message_accueil']) : '';
    $message_succes = isset($_POST['message_succes']) ? trim($_POST['message_succes']) : '';
    $idUser = $_SESSION['id'];
    
    // Validation
    if (empty($titre) || empty($reference) || empty($promotion_id) || empty($annee_acad_id) || empty($date_debut) || empty($date_fin)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
        exit();
    }

    try {
        $connexion = Connexion::getInstance()->getPDO();
        $connexion->beginTransaction();
        
        // Vérifier l'unicité de la référence
        $stmt = $connexion->prepare("SELECT COUNT(*) FROM liens_inscription_externe WHERE reference = ?");
        $stmt->execute([$reference]);
        if ($stmt->fetchColumn() > 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cette référence existe déjà. Veuillez en choisir une autre.'
                }).then(() => {
                    window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
                });
            </script>";
            exit();
        }
    
    // Générer un token unique
    $token_unique = bin2hex(random_bytes(32));
    
    // Construire l'URL complète
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $url_complete = "inscription_externe&token=" . $token_unique;
    
    // Gestion de l'upload du logo
    $logo_personnalise = null;
    if (isset($_FILES['logo_personnalise']) && $_FILES['logo_personnalise']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/logos_inscription/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['logo_personnalise']['name'], PATHINFO_EXTENSION);
        $logo_personnalise = 'logo_' . $token_unique . '.' . $file_extension;
        $upload_path = $upload_dir . $logo_personnalise;
        
        if (!move_uploaded_file($_FILES['logo_personnalise']['tmp_name'], $upload_path)) {
            throw new Exception("Erreur lors de l'upload du logo.");
        }
    }
    
    // Préparer les documents personnalisés si nécessaire
    $documents_personnalises = null;
    if (!$utiliser_docs_defaut && isset($_POST['docs_personnalises'])) {
        $documents_personnalises = json_encode($_POST['docs_personnalises']);
    }
    
    // Insérer le lien d'inscription
    $stmt = $connexion->prepare("
        INSERT INTO liens_inscription_externe (
            reference, titre, description, promotion_id, annee_acad_id, 
            token_unique, url_complete, date_debut, date_fin, max_inscriptions,
            utiliser_docs_defaut, documents_personnalises, message_accueil, 
            message_succes, couleur_theme, logo_personnalise, \"idUser\"
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $reference, $titre, $description, $promotion_id, $annee_acad_id,
        $token_unique, $url_complete, $date_debut, $date_fin, $max_inscriptions,
        $utiliser_docs_defaut, $documents_personnalises, $message_accueil,
        $message_succes, $couleur_theme, $logo_personnalise, $idUser
    ]);
    
    $lien_id = $connexion->lastInsertId();
    
    // Si on utilise les documents par défaut, les copier
    if ($utiliser_docs_defaut) {
        // Récupérer le cycle de la promotion
        $stmt = $connexion->prepare("
            SELECT p.cycle FROM promotion p WHERE p.idpromotion = ?
        ");
        $stmt->execute([$promotion_id]);
        $cycle = $stmt->fetchColumn();
        
        // Récupérer les documents obligatoires pour ce cycle
        $stmt = $connexion->prepare("
            SELECT * FROM documents_obligatoires 
            WHERE cycle = ? OR cycle = 'Tous'
            ORDER BY designation
        ");
        $stmt->execute([$cycle]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insérer les documents pour ce lien
        $stmt_insert_doc = $connexion->prepare("
            INSERT INTO lien_inscription_documents (
                lien_inscription_id, document_obligatoire_id, designation, 
                description, est_obligatoire, delai_jours, ordre_affichage
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $ordre = 1;
        foreach ($documents as $doc) {
            $stmt_insert_doc->execute([
                $lien_id, $doc['id'], $doc['designation'],
                $doc['description'], $doc['est_obligatoire'], 
                $doc['delai_jours'], $ordre++
            ]);
        }
    } else {
        // Insérer les documents personnalisés
        if (isset($_POST['docs_personnalises']) && is_array($_POST['docs_personnalises'])) {
            $stmt_insert_doc = $connexion->prepare("
                INSERT INTO lien_inscription_documents (
                    lien_inscription_id, designation, description, 
                    est_obligatoire, delai_jours, ordre_affichage
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ordre = 1;
            foreach ($_POST['docs_personnalises'] as $doc) {
                if (!empty($doc['designation'])) {
                    $stmt_insert_doc->execute([
                        $lien_id, $doc['designation'], $doc['description'] ?? '',
                        intval($doc['est_obligatoire']), 
                        !empty($doc['delai_jours']) ? intval($doc['delai_jours']) : null,
                        $ordre++
                    ]);
                }
            }
        }
    }
    
    $connexion->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le lien d\\'inscription externe a été créé avec succès.',
                showConfirmButton: true
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
        
    } catch (Exception $e) {
        $connexion->rollBack();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de la création du lien : " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../index.php?view=etudiants/liens_inscription_externe';
            });
        </script>";
    }
} else {
    header("Location: ../index.php");
    exit();
}
?>