<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier connexion
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php?view=login');
    exit;
}

// Récup données
$annee_academique = isset($_POST['annee_academique']) ? trim($_POST['annee_academique']) : '';
$section = isset($_POST['section']) ? trim($_POST['section']) : '';
$promotion = isset($_POST['promotion']) ? trim($_POST['promotion']) : '';
$session = isset($_POST['session']) ? trim($_POST['session']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$typePalmares = isset($_POST['type_palmares']) ? trim($_POST['type_palmares']) : 'classique';
$designation = 'Palmarès ' . $annee_academique . '-' . $promotion . '-' . $session;
$idUser = $_SESSION['id'];

// Validations
if (empty($annee_academique) || empty($section) || empty($promotion) || empty($session)) {
    $_SESSION['error'] = 'Tous les champs obligatoires doivent être remplis.';
    header('Location: ../index.php?view=academique/ajouter_palmares_archive');
    exit;
}
if (!isset($_POST['etudiants']) || empty($_POST['etudiants'])) {
    $_SESSION['error'] = 'Vous devez ajouter au moins un étudiant au palmarès.';
    header('Location: ../index.php?view=academique/ajouter_palmares_archive');
    exit;
}

// Upload PDF optionnel
$fichier_scanne = '';
if (isset($_FILES['fichier_scanne']) && $_FILES['fichier_scanne']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/palmares/';
    if (!file_exists($uploadDir)) { mkdir($uploadDir, 0777, true); }
    $filename = basename($_FILES['fichier_scanne']['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        $_SESSION['error'] = 'Seuls les fichiers PDF sont acceptés.';
        header('Location: ../index.php?view=academique/ajouter_palmares_archive');
        exit;
    }
    $newFilename = 'palmares_' . time() . '_' . uniqid() . '.pdf';
    $dest = $uploadDir . $newFilename;
    if (!move_uploaded_file($_FILES['fichier_scanne']['tmp_name'], $dest)) {
        $_SESSION['error'] = 'Erreur lors du téléversement du fichier scanné.';
        header('Location: ../index.php?view=academique/ajouter_palmares_archive');
        exit;
    }
    $fichier_scanne = 'uploads/palmares/' . $newFilename;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();

    // Détection colonne type_palmares
    $hasType = false;
    $chk = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='palmares_archives' AND COLUMN_NAME='type_palmares' AND TABLE_SCHEMA = DATABASE()");
    $chk->execute();
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if ($row && intval($row['c']) > 0) { $hasType = true; }

    if ($hasType) {
        $sql = "INSERT INTO palmares_archives (designation, description, fichier_scanne, annee_academique, section, promotion, session, type_palmares, date_creation, \"idUser\")
                VALUES (:designation, :description, :fichier_scanne, :annee_academique, :section, :promotion, :session, :type_palmares, NOW(), :idUser)";
    } else {
        $sql = "INSERT INTO palmares_archives (designation, description, fichier_scanne, annee_academique, section, promotion, session, date_creation, \"idUser\")
                VALUES (:designation, :description, :fichier_scanne, :annee_academique, :section, :promotion, :session, NOW(), :idUser)";
    }
    $st = $pdo->prepare($sql);
    $st->bindParam(':designation', $designation);
    $st->bindParam(':description', $description);
    $st->bindParam(':fichier_scanne', $fichier_scanne);
    $st->bindParam(':annee_academique', $annee_academique);
    $st->bindParam(':section', $section);
    $st->bindParam(':promotion', $promotion);
    $st->bindParam(':session', $session);
    if ($hasType) { $st->bindParam(':type_palmares', $typePalmares); }
    $st->bindParam(':idUser', $idUser, PDO::PARAM_INT);
    $st->execute();

    $palmaresId = $pdo->lastInsertId();

    // Détection credits_valides
    $hasCredits = false;
    $chk2 = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='etudiants_palmares_archives' AND COLUMN_NAME='credits_valides' AND TABLE_SCHEMA = DATABASE()");
    $chk2->execute();
    $row2 = $chk2->fetch(PDO::FETCH_ASSOC);
    if ($row2 && intval($row2['c']) > 0) { $hasCredits = true; }

    if ($hasCredits) {
        $sqlStu = "INSERT INTO etudiants_palmares_archives (idpalmares, matricule, nom_complet, pourcentage, decision, session, credits_valides)
                   VALUES (:idpalmares, :matricule, :nom_complet, :pourcentage, :decision, :session_palmares, :credits_valides)";
    } else {
        $sqlStu = "INSERT INTO etudiants_palmares_archives (idpalmares, matricule, nom_complet, pourcentage, decision, session)
                   VALUES (:idpalmares, :matricule, :nom_complet, :pourcentage, :decision, :session_palmares)";
    }
    $stStu = $pdo->prepare($sqlStu);

    foreach ($_POST['etudiants'] as $e) {
        if (empty($e['nom_complet']) || empty($e['matricule'])) continue;
        $stStu->bindParam(':idpalmares', $palmaresId, PDO::PARAM_INT);
        $stStu->bindParam(':matricule', $e['matricule']);
        $stStu->bindParam(':nom_complet', $e['nom_complet']);
        $stStu->bindParam(':pourcentage', $e['pourcentage']);
        $stStu->bindParam(':decision', $e['decision']);
        $stStu->bindParam(':session_palmares', $session);
        if ($hasCredits) {
            $cv = isset($e['credits_valides']) ? $e['credits_valides'] : null;
            $stStu->bindParam(':credits_valides', $cv);
        }
        $stStu->execute();
    }

    $pdo->commit();
    $_SESSION['success'] = 'Le palmarès a été enregistré avec succès.';
    header('Location: ../index.php?view=academique/palmares_archives');
    exit;
} catch (PDOException $e) {
    if (!empty($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['error'] = "Erreur lors de l'enregistrement du palmarès: " . $e->getMessage();
    header('Location: ../index.php?view=academique/ajouter_palmares_archive');
    exit;
}

