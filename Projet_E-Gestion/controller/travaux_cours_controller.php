<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/FlexPay.php';

// Vérifier si l'utilisateur est connecté (admin ou étudiant)
if (!isset($_SESSION['id']) && !isset($_SESSION['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$db = Connexion::getInstance()->getPDO();
$universite = new Universite();
$ecueModel = new Ecue();
$etudiantModel = new Etudiant();

// Créer la table fichiers_groupes_travail si elle n'existe pas
try {
    $db->exec("CREATE TABLE IF NOT EXISTS fichiers_groupes_travail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_devoir INT NOT NULL,
        numero_groupe INT NOT NULL,
        fichier VARCHAR(255) NOT NULL,
        date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_devoir_groupe (id_devoir, numero_groupe),
        FOREIGN KEY (id_devoir) REFERENCES devoirs(iddevoir) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Erreur création table fichiers_groupes_travail: " . $e->getMessage());
}

// Traitement des requêtes AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_travaux') {
    header('Content-Type: application/json');
    
    $idECUE = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
    
    if ($idECUE <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM devoirs WHERE \"idECUE\" = :id ORDER BY \"dateCreation\" DESC");
        $stmt->bindParam(':id', $idECUE, PDO::PARAM_INT);
        $stmt->execute();
        $travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'travaux' => $travaux]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $result = ['success' => false, 'message' => '', 'data' => []];
    
    switch ($action) {
        // =====================================================
        // CRÉATION D'UN TRAVAIL PRATIQUE
        // =====================================================
        case 'create_travail':
            try {
                $idECUE = intval($_POST['idECUE'] ?? 0);
                $titre = trim($_POST['titre'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $type_travail = $_POST['type_travail'] ?? 'individuel';
                $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
                
                // Validation
                if ($idECUE <= 0 || empty($titre)) {
                    $result['message'] = 'Tous les champs obligatoires doivent être remplis.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier si un devoir avec le même titre existe déjà pour ce cours
                $checkStmt = $db->prepare("SELECT iddevoir FROM devoirs WHERE \"idECUE\" = :idECUE AND titre = :titre LIMIT 1");
                $checkStmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $checkStmt->bindParam(':titre', $titre, PDO::PARAM_STR);
                $checkStmt->execute();
                
                if ($checkStmt->fetch()) {
                    $result['message'] = 'Un travail pratique avec ce titre existe déjà pour ce cours.';
                    echo json_encode($result);
                    exit;
                }
                
                // Gestion du fichier
                $fichier = null;
                if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__) . '/uploads/travaux_cours/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
                    $allowedExtensions = ['pdf', 'doc', 'docx'];
                    
                    if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                        $result['message'] = 'Seuls les fichiers PDF et Word sont autorisés.';
                        echo json_encode($result);
                        exit;
                    }
                    
                    $fichier = uniqid() . '.' . $fileExtension;
                    $uploadFile = $uploadDir . $fichier;
                    
                    if (!move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadFile)) {
                        $result['message'] = 'Erreur lors de l\'upload du fichier.';
                        echo json_encode($result);
                        exit;
                    }
                }
                
                // Paramètres selon le type de travail
                $max_etudiants_groupe = 0;
                $nombre_groupes = 0;
                $fichier_par_groupe = 0;
                $prix_par_etudiant = 0;
                $prix_forfaitaire = 0;
                $type_prix_groupe = 'forfaitaire';
                $est_payant = 0;
                
                if ($type_travail === 'individuel') {
                    $est_payant = isset($_POST['est_payant']) ? 1 : 0;
                    $prix_par_etudiant = floatval($_POST['prix_par_etudiant'] ?? 0);
                } else {
                    // Groupe
                    $est_payant = isset($_POST['est_payant']) ? 1 : 0;
                    $max_etudiants_groupe = intval($_POST['max_etudiants_groupe'] ?? 0);
                    $nombre_groupes = intval($_POST['nombre_groupes'] ?? 0);
                    $fichier_par_groupe = isset($_POST['fichier_par_groupe']) ? 1 : 0;
                    $type_prix_groupe = $_POST['type_prix_groupe'] ?? 'forfaitaire';
                    $prix_forfaitaire = floatval($_POST['prix_forfaitaire'] ?? 0);
                    $prix_par_etudiant = floatval($_POST['prix_par_etudiant_groupe'] ?? 0);
                }
                
                $devise = $_POST['devise'] ?? 'USD';
                
                // Insertion dans la base de données
                $query = "INSERT INTO devoirs (
                    \"idECUE\", titre, description, fichier, date_limite, 
                    est_payant, type_travail, max_etudiants_groupe, nombre_groupes,
                    fichier_par_groupe, prix_par_etudiant, prix_forfaitaire, type_prix_groupe, devise
                ) VALUES (
                    :idECUE, :titre, :description, :fichier, :date_limite,
                    :est_payant, :type_travail, :max_etudiants_groupe, :nombre_groupes,
                    :fichier_par_groupe, :prix_par_etudiant, :prix_forfaitaire, :type_prix_groupe, :devise
                )";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':idECUE', $idECUE, PDO::PARAM_INT);
                $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
                $stmt->bindParam(':date_limite', $date_limite, PDO::PARAM_STR);
                $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_INT);
                $stmt->bindParam(':type_travail', $type_travail, PDO::PARAM_STR);
                $stmt->bindParam(':max_etudiants_groupe', $max_etudiants_groupe, PDO::PARAM_INT);
                $stmt->bindParam(':nombre_groupes', $nombre_groupes, PDO::PARAM_INT);
                $stmt->bindParam(':fichier_par_groupe', $fichier_par_groupe, PDO::PARAM_INT);
                $stmt->bindParam(':prix_par_etudiant', $prix_par_etudiant, PDO::PARAM_STR);
                $stmt->bindParam(':prix_forfaitaire', $prix_forfaitaire, PDO::PARAM_STR);
                $stmt->bindParam(':type_prix_groupe', $type_prix_groupe, PDO::PARAM_STR);
                $stmt->bindParam(':devise', $devise, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    $idDevoirInsere = $db->lastInsertId();
                    $result['success'] = true;
                    $result['message'] = 'Travail pratique créé avec succès.';
                    $result['data']['id'] = $idDevoirInsere;
                    
                    // Si fichier par groupe, gérer les fichiers individuels
                    if ($fichier_par_groupe && $type_travail === 'groupe' && $nombre_groupes > 0) {
                        $uploadDir = dirname(__DIR__) . '/uploads/travaux_cours/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fichiersUploades = 0;
                        for ($i = 1; $i <= $nombre_groupes; $i++) {
                            $key = "fichier_groupe_{$i}";
                            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                                    $nomFichier = uniqid() . "_grp{$i}." . $ext;
                                    if (move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . $nomFichier)) {
                                        $stmtFG = $db->prepare("INSERT INTO fichiers_groupes_travail (id_devoir, numero_groupe, fichier) VALUES (:id, :grp, :fichier)
                                            ON DUPLICATE KEY UPDATE fichier = VALUES(fichier)");
                                        $stmtFG->bindParam(':id', $idDevoirInsere, PDO::PARAM_INT);
                                        $stmtFG->bindParam(':grp', $i, PDO::PARAM_INT);
                                        $stmtFG->bindParam(':fichier', $nomFichier, PDO::PARAM_STR);
                                        $stmtFG->execute();
                                        $fichiersUploades++;
                                    }
                                }
                            }
                        }
                        if ($fichiersUploades > 0) {
                            $result['message'] = "Travail pratique créé avec {$fichiersUploades} fichier(s) de groupe uploadé(s).";
                        }
                    }
                } else {
                    $result['message'] = 'Erreur lors de la création du travail pratique.';
                }
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
                error_log("Erreur create_travail: " . $e->getMessage());
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // MISE À JOUR D'UN TRAVAIL PRATIQUE
        // =====================================================
        case 'update_travail':
            try {
                $idDevoir = intval($_POST['idDevoir'] ?? 0);
                $titre = trim($_POST['titre'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $type_travail = $_POST['type_travail'] ?? 'individuel';
                $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
                
                if ($idDevoir <= 0 || empty($titre)) {
                    $result['message'] = 'Données invalides.';
                    echo json_encode($result);
                    exit;
                }
                
                // Récupérer le fichier actuel
                $stmt = $db->prepare("SELECT fichier FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $currentDevoir = $stmt->fetch(PDO::FETCH_ASSOC);
                $fichier = $currentDevoir['fichier'] ?? null;
                
                // Nouveau fichier si uploadé
                if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = dirname(__DIR__) . '/uploads/travaux_cours/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Supprimer l'ancien fichier
                    if ($fichier && file_exists($uploadDir . $fichier)) {
                        unlink($uploadDir . $fichier);
                    }
                    
                    $fileExtension = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
                    $fichier = uniqid() . '.' . $fileExtension;
                    move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadDir . $fichier);
                }
                
                // Paramètres selon le type
                $max_etudiants_groupe = 0;
                $nombre_groupes = 0;
                $fichier_par_groupe = 0;
                $prix_par_etudiant = 0;
                $prix_forfaitaire = 0;
                $type_prix_groupe = 'forfaitaire';
                $est_payant = 0;
                
                if ($type_travail === 'individuel') {
                    $est_payant = isset($_POST['est_payant']) ? 1 : 0;
                    $prix_par_etudiant = floatval($_POST['prix_par_etudiant'] ?? 0);
                } else {
                    $est_payant = isset($_POST['est_payant']) ? 1 : 0;
                    $max_etudiants_groupe = intval($_POST['max_etudiants_groupe'] ?? 0);
                    $nombre_groupes = intval($_POST['nombre_groupes'] ?? 0);
                    $fichier_par_groupe = isset($_POST['fichier_par_groupe']) ? 1 : 0;
                    $type_prix_groupe = $_POST['type_prix_groupe'] ?? 'forfaitaire';
                    $prix_forfaitaire = floatval($_POST['prix_forfaitaire'] ?? 0);
                    $prix_par_etudiant = floatval($_POST['prix_par_etudiant_groupe'] ?? 0);
                }
                
                $devise = $_POST['devise'] ?? 'USD';
                
                $query = "UPDATE devoirs SET 
                    titre = :titre, description = :description, fichier = :fichier,
                    date_limite = :date_limite, est_payant = :est_payant,
                    type_travail = :type_travail, max_etudiants_groupe = :max_etudiants_groupe,
                    nombre_groupes = :nombre_groupes, fichier_par_groupe = :fichier_par_groupe,
                    prix_par_etudiant = :prix_par_etudiant, prix_forfaitaire = :prix_forfaitaire,
                    type_prix_groupe = :type_prix_groupe, devise = :devise
                    WHERE iddevoir = :idDevoir";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':titre', $titre, PDO::PARAM_STR);
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':fichier', $fichier, PDO::PARAM_STR);
                $stmt->bindParam(':date_limite', $date_limite, PDO::PARAM_STR);
                $stmt->bindParam(':est_payant', $est_payant, PDO::PARAM_INT);
                $stmt->bindParam(':type_travail', $type_travail, PDO::PARAM_STR);
                $stmt->bindParam(':max_etudiants_groupe', $max_etudiants_groupe, PDO::PARAM_INT);
                $stmt->bindParam(':nombre_groupes', $nombre_groupes, PDO::PARAM_INT);
                $stmt->bindParam(':fichier_par_groupe', $fichier_par_groupe, PDO::PARAM_INT);
                $stmt->bindParam(':prix_par_etudiant', $prix_par_etudiant, PDO::PARAM_STR);
                $stmt->bindParam(':prix_forfaitaire', $prix_forfaitaire, PDO::PARAM_STR);
                $stmt->bindParam(':type_prix_groupe', $type_prix_groupe, PDO::PARAM_STR);
                $stmt->bindParam(':devise', $devise, PDO::PARAM_STR);
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $result['success'] = true;
                    $result['message'] = 'Travail pratique mis à jour avec succès.';
                    
                    // Si fichier par groupe, gérer les fichiers individuels mis à jour
                    if ($fichier_par_groupe && $type_travail === 'groupe' && $nombre_groupes > 0) {
                        $uploadDir = dirname(__DIR__) . '/uploads/travaux_cours/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        for ($i = 1; $i <= $nombre_groupes; $i++) {
                            $key = "fichier_groupe_{$i}";
                            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                                if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                                    // Supprimer l'ancien fichier du groupe
                                    $stmtOld = $db->prepare("SELECT fichier FROM fichiers_groupes_travail WHERE id_devoir = :id AND numero_groupe = :grp");
                                    $stmtOld->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                                    $stmtOld->bindParam(':grp', $i, PDO::PARAM_INT);
                                    $stmtOld->execute();
                                    $oldFichier = $stmtOld->fetchColumn();
                                    if ($oldFichier && file_exists($uploadDir . $oldFichier)) {
                                        unlink($uploadDir . $oldFichier);
                                    }
                                    $nomFichier = uniqid() . "_grp{$i}." . $ext;
                                    if (move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . $nomFichier)) {
                                        $stmtFG = $db->prepare("INSERT INTO fichiers_groupes_travail (id_devoir, numero_groupe, fichier) VALUES (:id, :grp, :fichier)
                                            ON DUPLICATE KEY UPDATE fichier = VALUES(fichier)");
                                        $stmtFG->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                                        $stmtFG->bindParam(':grp', $i, PDO::PARAM_INT);
                                        $stmtFG->bindParam(':fichier', $nomFichier, PDO::PARAM_STR);
                                        $stmtFG->execute();
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $result['message'] = 'Erreur lors de la mise à jour.';
                }
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // CRÉATION D'UN GROUPE DE TRAVAIL
        // =====================================================
        case 'create_groupe':
            try {
                $idDevoir = intval($_POST['id_devoir'] ?? 0);
                $idEtudiant = intval($_POST['id_etudiant'] ?? ($_SESSION['student_id'] ?? 0));
                $numero_groupe = intval($_POST['numero_groupe'] ?? 1);
                
                if ($idDevoir <= 0 || $idEtudiant <= 0) {
                    $result['message'] = 'Données invalides.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier si le devoir existe et est de type groupe
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id AND type_travail = 'groupe'");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$devoir) {
                    $result['message'] = 'Travail non trouvé ou non groupé.';
                    echo json_encode($result);
                    exit;
                }
                
                $maxEtudiantsParGroupe = intval($devoir['max_etudiants_groupe'] ?? 3);
                $nombreGroupesMax = intval($devoir['nombre_groupes'] ?? 10);
                
                // Vérifier si l'étudiant a déjà un groupe
                $stmt = $db->prepare("SELECT mgt.id_groupe FROM membres_groupe_travail mgt 
                    INNER JOIN groupes_travail gt ON mgt.id_groupe = gt.id_groupe 
                    WHERE gt.id_devoir = :idDevoir AND mgt.id_etudiant = :idEtudiant");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $result['message'] = 'Vous êtes déjà dans un groupe.';
                    echo json_encode($result);
                    exit;
                }
                
                // NOUVELLE LOGIQUE: Trouver un groupe avec de la place
                // D'abord, chercher si un groupe existant a de la place
                $trouveGroupe = null;
                
                // Récupérer tous les groupes existants avec leur nombre de membres
                $stmt = $db->prepare("
                    SELECT gt.id_groupe, gt.numero_groupe, 
                           (SELECT COUNT(*) FROM membres_groupe_travail WHERE id_groupe = gt.id_groupe) as nb_membres
                    FROM groupes_travail gt
                    WHERE gt.id_devoir = :idDevoir
                    ORDER BY gt.numero_groupe
                ");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $groupesExistants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Chercher le premier groupe avec de la place
                foreach ($groupesExistants as $groupe) {
                    if ($groupe['nb_membres'] < $maxEtudiantsParGroupe) {
                        $trouveGroupe = $groupe;
                        break;
                    }
                }
                
                // Si aucun groupe existant n'a de la place, créer un nouveau groupe s'il y a de la place
                if (!$trouveGroupe) {
                    $dernierNumero = 0;
                    if (!empty($groupesExistants)) {
                        $dernierNumero = max(array_column($groupesExistants, 'numero_groupe'));
                    }
                    
                    // Vérifier si on peut créer un nouveau groupe
                    if ($dernierNumero < $nombreGroupesMax) {
                        $nouveauNumero = $dernierNumero + 1;
                        
                        $stmt = $db->prepare("INSERT INTO groupes_travail (id_devoir, numero_groupe) VALUES (:idDevoir, :numero)");
                        $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                        $stmt->bindParam(':numero', $nouveauNumero, PDO::PARAM_INT);
                        $stmt->execute();
                        $trouveGroupe = [
                            'id_groupe' => $db->lastInsertId(),
                            'numero_groupe' => $nouveauNumero,
                            'nb_membres' => 0
                        ];
                    }
                }
                
                // Si aucun groupe n'est disponible
                if (!$trouveGroupe) {
                    $result['message'] = 'Aucun groupe disponible. Tous les groupes sont complets.';
                    echo json_encode($result);
                    exit;
                }
                
                $idGroupe = $trouveGroupe['id_groupe'];
                $numero_groupe = $trouveGroupe['numero_groupe'];
                
                // Ajouter l'étudiant comme créateur
                $stmt = $db->prepare("INSERT INTO membres_groupe_travail (id_groupe, id_etudiant, est_createur) 
                    VALUES (:idGroupe, :idEtudiant, 1)");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                
                $result['success'] = true;
                $result['message'] = 'Groupe créé avec succès.';
                $result['data']['id_groupe'] = $idGroupe;
                $result['data']['numero_groupe'] = $numero_groupe;
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // AJOUTER UN MEMBRE AU GROUPE
        // =====================================================
        case 'add_membre_groupe':
            try {
                $idGroupe = intval($_POST['id_groupe'] ?? 0);
                $idEtudiant = intval($_POST['id_etudiant'] ?? 0);
                
                if ($idGroupe <= 0 || $idEtudiant <= 0) {
                    $result['message'] = 'Données invalides.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier la capacité du groupe
                $stmt = $db->prepare("SELECT gt.*, d.max_etudiants_groupe FROM groupes_travail gt 
                    INNER JOIN devoirs d ON gt.id_devoir = d.iddevoir 
                    WHERE gt.id_groupe = :idGroupe");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->execute();
                $groupe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$groupe) {
                    $result['message'] = 'Groupe non trouvé.';
                    echo json_encode($result);
                    exit;
                }
                
                // Compter les membres actuels
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM membres_groupe_travail WHERE id_groupe = :idGroupe");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($count['count'] >= $groupe['max_etudiants_groupe']) {
                    $result['message'] = 'Le groupe est complet.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier si l'étudiant n'est pas déjà dans un groupe pour ce devoir
                $stmt = $db->prepare("SELECT mgt.id_groupe FROM membres_groupe_travail mgt 
                    INNER JOIN groupes_travail gt ON mgt.id_groupe = gt.id_groupe 
                    WHERE gt.id_devoir = :idDevoir AND mgt.id_etudiant = :idEtudiant");
                $stmt->bindParam(':idDevoir', $groupe['id_devoir'], PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $result['message'] = 'Cet étudiant est déjà dans un groupe.';
                    echo json_encode($result);
                    exit;
                }
                
                // Ajouter le membre
                $stmt = $db->prepare("INSERT INTO membres_groupe_travail (id_groupe, id_etudiant, est_createur) 
                    VALUES (:idGroupe, :idEtudiant, 0)");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                
                $result['success'] = true;
                $result['message'] = 'Membre ajouté au groupe.';
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // INITIER PAIEMENT POUR TRAVAIL
        // =====================================================
        case 'init_paiement':
            try {
                $idDevoir = intval($_POST['id_devoir'] ?? 0);
                $idEtudiant = intval($_POST['id_etudiant'] ?? 0);
                $telephone = trim($_POST['telephone'] ?? '');
                $type_paiement = $_POST['type_paiement'] ?? 'individuel';
                $idGroupe = !empty($_POST['id_groupe']) ? intval($_POST['id_groupe']) : null;
                
                if ($idDevoir <= 0 || $idEtudiant <= 0 || empty($telephone)) {
                    $result['message'] = 'Données invalides.';
                    echo json_encode($result);
                    exit;
                }
                
                // Récupérer les infos du travail
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$devoir || !$devoir['est_payant']) {
                    $result['message'] = 'Travail non payable.';
                    echo json_encode($result);
                    exit;
                }
                
                // Calculer le montant
                $montant = 0;
                if ($devoir['type_travail'] === 'individuel') {
                    $montant = floatval($devoir['prix_par_etudiant']);
                } else {
                    if ($devoir['type_prix_groupe'] === 'forfaitaire') {
                        $montant = floatval($devoir['prix_forfaitaire']);
                    } else {
                        // Par étudiant
                        if ($idGroupe) {
                            $stmt = $db->prepare("SELECT COUNT(*) as count FROM membres_groupe_travail WHERE id_groupe = :id");
                            $stmt->bindParam(':id', $idGroupe, PDO::PARAM_INT);
                            $stmt->execute();
                            $membres = $stmt->fetch(PDO::FETCH_ASSOC);
                            $montant = floatval($devoir['prix_par_etudiant']) * $membres['count'];
                        } else {
                            $montant = floatval($devoir['prix_par_etudiant']);
                        }
                    }
                }
                
                if ($montant <= 0) {
                    $result['message'] = 'Montant invalide.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier si déjà payé (pour travail individuel)
                if ($devoir['type_travail'] === 'individuel') {
                    $stmt = $db->prepare("SELECT id_paiement FROM paiements_travaux 
                        WHERE id_devoir = :idDevoir AND id_etudiant = :idEtudiant AND statut = 'reussi'");
                    $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                    $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $result['message'] = 'Vous avez déjà payé ce travail.';
                        echo json_encode($result);
                        exit;
                    }
                }
                
                // Créer l'enregistrement de paiement
                $reference = 'TP-' . $idDevoir . '-' . $idEtudiant . '-' . time();
                
                $stmt = $db->prepare("INSERT INTO paiements_travaux 
                    (id_groupe, id_etudiant, id_devoir, montant, mode_paiement, reference_transaction, statut) 
                    VALUES (:idGroupe, :idEtudiant, :idDevoir, :montant, 'mobile_money', :reference, 'en_attente')");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
                $stmt->bindParam(':reference', $reference, PDO::PARAM_STR);
                $stmt->execute();
                
                $idPaiement = $db->lastInsertId();
                
                // Initier le paiement FlexPay
                $flexpay = new FlexPay();
                $description = "Paiement Travail Pratique - " . $devoir['titre'];
                
                $devise = $devoir['devise'] ?? 'USD';
                $reponseApi = $flexpay->initierPaiementMobile($telephone, $montant, $devise, $reference, $description);
                
                if (isset($reponseApi['orderNumber'])) {
                    // Mettre à jour avec le order number
                    $stmt = $db->prepare("UPDATE paiements_travaux SET order_number_flexpay = :orderNumber WHERE id_paiement = :id");
                    $stmt->bindParam(':orderNumber', $reponseApi['orderNumber'], PDO::PARAM_STR);
                    $stmt->bindParam(':id', $idPaiement, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    // Ajouter au suivi
                    $stmt = $db->prepare("INSERT INTO suivi_paiements_travaux 
                        (id_devoir, id_etudiant, id_groupe, type_paiement, montant, statut) 
                        VALUES (:idDevoir, :idEtudiant, :idGroupe, :typePaiement, :montant, 'en_attente')");
                    $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                    $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                    $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                    $stmt->bindParam(':typePaiement', $type_paiement, PDO::PARAM_STR);
                    $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    $result['success'] = true;
                    $result['data'] = [
                        'order_number' => $reponseApi['orderNumber'],
                        'montant' => $montant,
                        'reference' => $reference
                    ];
                } else {
                    $result['message'] = 'Erreur lors de l\'initiation du paiement: ' . ($reponseApi['message'] ?? 'Erreur inconnue');
                }
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
                error_log("Erreur init_paiement: " . $e->getMessage());
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // VÉRIFIER STATUT PAIEMENT
        // =====================================================
        case 'check_paiement':
            try {
                $orderNumber = $_POST['order_number'] ?? '';
                
                if (empty($orderNumber)) {
                    $result['message'] = 'Numéro de commande invalide.';
                    echo json_encode($result);
                    exit;
                }
                
                // Vérifier le statut auprès de FlexPay
                $flexpay = new FlexPay();
                $reponseApi = $flexpay->verifierTransaction($orderNumber);
                
                // Déterminer le statut
                $statut = 'en_attente';
                $messageApi = $reponseApi['message'] ?? '';
                
                if ($flexpay->estPaiementReussi($messageApi)) {
                    $statut = 'reussi';
                } elseif ($flexpay->estPaiementEchoue($messageApi)) {
                    $statut = 'echoue';
                }
                
                // Mettre à jour le paiement
                $stmt = $db->prepare("UPDATE paiements_travaux SET statut = :statut, date_confirmation = NOW() 
                    WHERE order_number_flexpay = :orderNumber");
                $stmt->bindParam(':statut', $statut, PDO::PARAM_STR);
                $stmt->bindParam(':orderNumber', $orderNumber, PDO::PARAM_STR);
                $stmt->execute();
                
                // Si paiement réussi, marquer le groupe comme payé
                if ($statut === 'reussi') {
                    $stmt = $db->prepare("SELECT * FROM paiements_travaux WHERE order_number_flexpay = :orderNumber");
                    $stmt->bindParam(':orderNumber', $orderNumber, PDO::PARAM_STR);
                    $stmt->execute();
                    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($paiement && $paiement['id_groupe']) {
                        // Marquer tout le groupe comme payé
                        $stmt = $db->prepare("UPDATE groupes_travail SET est_paye = 1, date_paiement = NOW(), 
                            reference_paiement = :ref WHERE id_groupe = :id");
                        $stmt->bindParam(':ref', $paiement['reference_transaction'], PDO::PARAM_STR);
                        $stmt->bindParam(':id', $paiement['id_groupe'], PDO::PARAM_INT);
                        $stmt->execute();
                    }
                    
                    // Mettre à jour le suivi
                    $stmt = $db->prepare("UPDATE suivi_paiements_travaux SET statut = 'reussi' 
                        WHERE id_devoir = :idDevoir AND (id_etudiant = :idEtudiant OR id_groupe = :idGroupe)");
                    $stmt->bindParam(':idDevoir', $paiement['id_devoir'], PDO::PARAM_INT);
                    $stmt->bindParam(':idEtudiant', $paiement['id_etudiant'], PDO::PARAM_INT);
                    $stmt->bindParam(':idGroupe', $paiement['id_groupe'], PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                $result['success'] = true;
                $result['data'] = [
                    'statut' => $statut,
                    'message' => $messageApi
                ];
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // OBTENIR LES PAIEMENTS POUR L'ENSEIGNANT
        // =====================================================
        case 'get_paiements_enseignant':
            try {
                $idDevoir = intval($_POST['id_devoir'] ?? 0);
                
                if ($idDevoir <= 0) {
                    $result['message'] = 'ID invalide.';
                    echo json_encode($result);
                    exit;
                }
                
                $query = "SELECT sp.*, e.matricule, e.noms as nom_etudiant, gt.numero_groupe
                    FROM suivi_paiements_travaux sp
                    LEFT JOIN etudiant e ON sp.id_etudiant = e.idetudiant
                    LEFT JOIN groupes_travail gt ON sp.id_groupe = gt.id_groupe
                    WHERE sp.id_devoir = :idDevoir
                    ORDER BY sp.date_suivi DESC";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $result['success'] = true;
                $result['data'] = $paiements;
                
            } catch (Exception $e) {
                $result['message'] = 'Erreur: ' . $e->getMessage();
            }
            echo json_encode($result);
            exit;
            
        // =====================================================
        // OBTENIR INFO GROUPE POUR ÉTUDIANT
        // =====================================================
        case 'get_groupe_info':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                $idEtudiant = intval($_SESSION['student_id'] ?? 0);
                
                if ($idDevoir <= 0 || $idEtudiant <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                // Vérifier si l'étudiant a un groupe
                $stmt = $db->prepare("SELECT gt.* FROM groupes_travail gt
                    INNER JOIN membres_groupe_travail mgt ON gt.id_groupe = mgt.id_groupe
                    WHERE gt.id_devoir = :idDevoir AND mgt.id_etudiant = :idEtudiant");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                $groupe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$groupe) {
                    echo json_encode(['success' => false, 'message' => 'Pas de groupe']);
                    exit;
                }
                
                // Récupérer les membres
                $stmt = $db->prepare("SELECT e.idetudiant, e.noms, e.matricule, mgt.est_createur
                    FROM membres_groupe_travail mgt
                    INNER JOIN etudiant e ON mgt.id_etudiant = e.idetudiant
                    WHERE mgt.id_groupe = :idGroupe");
                $stmt->bindParam(':idGroupe', $groupe['id_groupe'], PDO::PARAM_INT);
                $stmt->execute();
                $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Récupérer le devoir pour avoir les infos de prix
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montant = 0;
                if ($devoir['type_prix_groupe'] === 'forfaitaire') {
                    $montant = floatval($devoir['prix_forfaitaire']);
                } else {
                    $montant = floatval($devoir['prix_par_etudiant']) * count($membres);
                }
                
                echo json_encode([
                    'success' => true,
                    'groupe' => $groupe,
                    'membres' => $membres,
                    'montant' => $montant,
                    'max_etudiants_groupe' => $devoir['max_etudiants_groupe'] ?? 3,
                    'type_prix_groupe' => $devoir['type_prix_groupe'] ?? 'forfaitaire',
                    'prix_par_etudiant' => floatval($devoir['prix_par_etudiant'] ?? 0),
                    'prix_forfaitaire' => floatval($devoir['prix_forfaitaire'] ?? 0),
                    'devise' => $devoir['devise'] ?? 'USD'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        // =====================================================
        // OBTENIR LE PROCHAIN NUMÉRO DE GROUPE DISPONIBLE
        // =====================================================
        case 'get_next_groupe_number':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                
                if ($idDevoir <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                // Récupérer les infos du devoir
                $stmt = $db->prepare("SELECT nombre_groupes, max_etudiants_groupe FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $maxGroupes = intval($devoir['nombre_groupes'] ?? 10);
                $maxEtudiantsParGroupe = intval($devoir['max_etudiants_groupe'] ?? 3);
                
                // Trouver le premier groupe avec de la place
                $stmt = $db->prepare("
                    SELECT gt.id_groupe, gt.numero_groupe, 
                           (SELECT COUNT(*) FROM membres_groupe_travail WHERE id_groupe = gt.id_groupe) as nb_membres
                    FROM groupes_travail gt
                    WHERE gt.id_devoir = :idDevoir
                    ORDER BY gt.numero_groupe
                ");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $groupesExistants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $nextNum = null;
                $nextIdGroupe = null;
                
                // Chercher le premier groupe existant avec de la place
                foreach ($groupesExistants as $groupe) {
                    if ($groupe['nb_membres'] < $maxEtudiantsParGroupe) {
                        $nextNum = $groupe['numero_groupe'];
                        $nextIdGroupe = $groupe['id_groupe'];
                        break;
                    }
                }
                
                // Si aucun groupe existant n'a de la place, proposer un nouveau groupe
                if ($nextNum === null) {
                    $dernierNumero = 0;
                    if (!empty($groupesExistants)) {
                        $dernierNumero = max(array_column($groupesExistants, 'numero_groupe'));
                    }
                    
                    if ($dernierNumero < $maxGroupes) {
                        $nextNum = $dernierNumero + 1;
                        $nextIdGroupe = 'new';
                    }
                }
                
                if ($nextNum === null) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Aucun groupe disponible',
                        'max_groupes' => $maxGroupes
                    ]);
                    exit;
                }
                
                echo json_encode([
                    'success' => true, 
                    'next_number' => $nextNum,
                    'id_groupe' => $nextIdGroupe,
                    'max_groupes' => $maxGroupes,
                    'max_etudiants_par_groupe' => $maxEtudiantsParGroupe
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        // =====================================================
        // RECHERCHER ÉTUDIANT PAR MATRICULE
        // =====================================================
        case 'rechercher_etudiant':
            try {
                $matricule = trim($_GET['matricule'] ?? '');
                
                if (empty($matricule)) {
                    echo json_encode(['success' => false, 'message' => 'Matricule invalide']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT idetudiant, noms, matricule FROM etudiant WHERE matricule = :matricule");
                $stmt->bindParam(':matricule', $matricule, PDO::PARAM_STR);
                $stmt->execute();
                $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($etudiant) {
                    echo json_encode(['success' => true, 'etudiant' => $etudiant]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        // =====================================================
        // OBTENIR MONTANT GROUPE
        // =====================================================
        case 'get_montant_groupe':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                $idGroupe = intval($_GET['groupe'] ?? 0);
                
                // Récupérer les infos du travail
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Compter les membres
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM membres_groupe_travail WHERE id_groupe = :id");
                $stmt->bindParam(':id', $idGroupe, PDO::PARAM_INT);
                $stmt->execute();
                $membres = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montant = 0;
                if ($devoir['type_prix_groupe'] === 'forfaitaire') {
                    $montant = floatval($devoir['prix_forfaitaire']);
                } else {
                    $montant = floatval($devoir['prix_par_etudiant']) * $membres['count'];
                }
                
                echo json_encode(['success' => true, 'montant' => $montant]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        default:
            $result['message'] = 'Action non reconnue.';
            echo json_encode($result);
            exit;
    }
}

// Traitement des requêtes GET avec action
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_groupe_info':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                $idEtudiant = intval($_SESSION['student_id'] ?? 0);
                
                if ($idDevoir <= 0 || $idEtudiant <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT gt.* FROM groupes_travail gt
                    INNER JOIN membres_groupe_travail mgt ON gt.id_groupe = mgt.id_groupe
                    WHERE gt.id_devoir = :idDevoir AND mgt.id_etudiant = :idEtudiant");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->bindParam(':idEtudiant', $idEtudiant, PDO::PARAM_INT);
                $stmt->execute();
                $groupe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$groupe) {
                    echo json_encode(['success' => false, 'message' => 'Pas de groupe']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT e.idetudiant, e.noms, e.matricule, mgt.est_createur
                    FROM membres_groupe_travail mgt
                    INNER JOIN etudiant e ON mgt.id_etudiant = e.idetudiant
                    WHERE mgt.id_groupe = :idGroupe");
                $stmt->bindParam(':idGroupe', $groupe['id_groupe'], PDO::PARAM_INT);
                $stmt->execute();
                $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montant = 0;
                if (($devoir['type_prix_groupe'] ?? 'forfaitaire') === 'forfaitaire') {
                    $montant = floatval($devoir['prix_forfaitaire'] ?? 0);
                } else {
                    $montant = floatval($devoir['prix_par_etudiant'] ?? 0) * count($membres);
                }
                
                echo json_encode([
                    'success' => true,
                    'groupe' => $groupe,
                    'membres' => $membres,
                    'montant' => $montant,
                    'max_etudiants_groupe' => $devoir['max_etudiants_groupe'] ?? 3,
                    'type_prix_groupe' => $devoir['type_prix_groupe'] ?? 'forfaitaire',
                    'prix_par_etudiant' => floatval($devoir['prix_par_etudiant'] ?? 0),
                    'prix_forfaitaire' => floatval($devoir['prix_forfaitaire'] ?? 0),
                    'devise' => $devoir['devise'] ?? 'USD'
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_next_groupe_number':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                
                if ($idDevoir <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                // Récupérer les infos du devoir
                $stmt = $db->prepare("SELECT nombre_groupes, max_etudiants_groupe FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $maxGroupes = intval($devoir['nombre_groupes'] ?? 10);
                $maxEtudiantsParGroupe = intval($devoir['max_etudiants_groupe'] ?? 3);
                
                // Trouver le premier groupe avec de la place
                $stmt = $db->prepare("
                    SELECT gt.id_groupe, gt.numero_groupe, 
                           (SELECT COUNT(*) FROM membres_groupe_travail WHERE id_groupe = gt.id_groupe) as nb_membres
                    FROM groupes_travail gt
                    WHERE gt.id_devoir = :idDevoir
                    ORDER BY gt.numero_groupe
                ");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $groupesExistants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $nextNum = null;
                $nextIdGroupe = null;
                
                foreach ($groupesExistants as $groupe) {
                    if ($groupe['nb_membres'] < $maxEtudiantsParGroupe) {
                        $nextNum = $groupe['numero_groupe'];
                        $nextIdGroupe = $groupe['id_groupe'];
                        break;
                    }
                }
                
                if ($nextNum === null) {
                    $dernierNumero = 0;
                    if (!empty($groupesExistants)) {
                        $dernierNumero = max(array_column($groupesExistants, 'numero_groupe'));
                    }
                    
                    if ($dernierNumero < $maxGroupes) {
                        $nextNum = $dernierNumero + 1;
                        $nextIdGroupe = 'new';
                    }
                }
                
                if ($nextNum === null) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Aucun groupe disponible',
                        'max_groupes' => $maxGroupes
                    ]);
                    exit;
                }
                
                echo json_encode([
                    'success' => true, 
                    'next_number' => $nextNum,
                    'id_groupe' => $nextIdGroupe,
                    'max_groupes' => $maxGroupes,
                    'max_etudiants_par_groupe' => $maxEtudiantsParGroupe
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'rechercher_etudiant':
            try {
                $matricule = $_GET['matricule'] ?? '';
                
                if (empty($matricule)) {
                    echo json_encode(['success' => false, 'message' => 'Matricule requis']);
                    exit;
                }
                
                $stmt = $db->prepare("SELECT idetudiant, noms, matricule FROM etudiant WHERE matricule = :matricule");
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($etudiant) {
                    echo json_encode(['success' => true, 'etudiant' => $etudiant]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_montant_groupe':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                $idGroupe = intval($_GET['groupe'] ?? 0);
                
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM membres_groupe_travail WHERE id_groupe = :idGroupe");
                $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
                $stmt->execute();
                $membres = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $montant = 0;
                if (($devoir['type_prix_groupe'] ?? 'forfaitaire') === 'forfaitaire') {
                    $montant = floatval($devoir['prix_forfaitaire'] ?? 0);
                } else {
                    $montant = floatval($devoir['prix_par_etudiant'] ?? 0) * $membres['count'];
                }
                
                echo json_encode(['success' => true, 'montant' => $montant]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_travail':
            try {
                $idDevoir = intval($_GET['id'] ?? 0);
                if ($idDevoir <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $travail = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($travail) {
                    // Récupérer les fichiers par groupe si applicable
                    if ($travail['fichier_par_groupe']) {
                        $stmtFG = $db->prepare("SELECT numero_groupe, fichier FROM fichiers_groupes_travail WHERE id_devoir = :id ORDER BY numero_groupe");
                        $stmtFG->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                        $stmtFG->execute();
                        $travail['fichiers_groupes'] = $stmtFG->fetchAll(PDO::FETCH_ASSOC);
                    }
                    echo json_encode(['success' => true, 'travail' => $travail]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Travail non trouvé']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'get_paiements':
            try {
                $idDevoir = intval($_GET['devoir'] ?? 0);
                if ($idDevoir <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                // Get the TP info
                $stmt = $db->prepare("SELECT * FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$devoir) {
                    echo json_encode(['success' => false, 'message' => 'Travail non trouvé']);
                    exit;
                }
                
                // Get payments with student info
                $stmt = $db->prepare("SELECT pt.*, e.noms, e.matricule, gt.numero_groupe
                    FROM paiements_travaux pt
                    INNER JOIN etudiant e ON pt.id_etudiant = e.idetudiant
                    LEFT JOIN groupes_travail gt ON pt.id_groupe = gt.id_groupe
                    WHERE pt.id_devoir = :idDevoir
                    ORDER BY pt.date_paiement DESC");
                $stmt->bindParam(':idDevoir', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Stats
                $totalRecu = 0;
                $totalEnAttente = 0;
                $nbReussi = 0;
                $nbEnAttente = 0;
                $nbEchoue = 0;
                foreach ($paiements as $p) {
                    if ($p['statut'] === 'reussi') {
                        $totalRecu += floatval($p['montant']);
                        $nbReussi++;
                    } elseif ($p['statut'] === 'en_attente') {
                        $totalEnAttente += floatval($p['montant']);
                        $nbEnAttente++;
                    } else {
                        $nbEchoue++;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'devoir' => $devoir,
                    'paiements' => $paiements,
                    'stats' => [
                        'total_recu' => $totalRecu,
                        'total_en_attente' => $totalEnAttente,
                        'nb_reussi' => $nbReussi,
                        'nb_en_attente' => $nbEnAttente,
                        'nb_echoue' => $nbEchoue
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        // =====================================================
        // SUPPRIMER UN TRAVAIL PRATIQUE
        // =====================================================
        case 'delete_travail':
            try {
                $idDevoir = isset($_GET['id']) ? intval($_GET['id']) : 0;
                
                if ($idDevoir <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID invalide']);
                    exit;
                }
                
                // Vérifier si le devoir existe
                $stmt = $db->prepare("SELECT fichier FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                $devoir = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$devoir) {
                    echo json_encode(['success' => false, 'message' => 'Travail non trouvé']);
                    exit;
                }
                
                // Supprimer le fichier s'il existe
                if (!empty($devoir['fichier'])) {
                    $fichierPath = dirname(__DIR__) . '/uploads/travaux_cours/' . $devoir['fichier'];
                    if (file_exists($fichierPath)) {
                        unlink($fichierPath);
                    }
                }
                
                // Supprimer les enregistrements liés (groupes, membres, paiements)
                $stmt = $db->prepare("DELETE FROM paiements_travaux WHERE id_devoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM suivi_paiements_travaux WHERE id_devoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM membres_groupe_travail WHERE id_groupe IN (SELECT id_groupe FROM groupes_travail WHERE id_devoir = :id)");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                
                $stmt = $db->prepare("DELETE FROM groupes_travail WHERE id_devoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                $stmt->execute();
                
                // Supprimer le devoir
                $stmt = $db->prepare("DELETE FROM devoirs WHERE iddevoir = :id");
                $stmt->bindParam(':id', $idDevoir, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Travail supprimé avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit;
    }
}

// Si accès direct
header("Location: ../index");
exit;
