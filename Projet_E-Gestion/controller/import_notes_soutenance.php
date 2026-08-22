<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier la présence du fichier (peut être 'file' ou 'fichier_notes')
$fileKey = isset($_FILES['fichier_notes']) ? 'fichier_notes' : 'file';
if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite PHP)',
        UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
        UPLOAD_ERR_PARTIAL => 'Fichier partiellement uploadé',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier envoyé',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
        UPLOAD_ERR_EXTENSION => 'Extension PHP bloquante'
    ];
    $errorCode = $_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE;
    $message = $errorMessages[$errorCode] ?? 'Erreur lors de l\'upload';
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Vérifier l'extension du fichier
$allowedExtensions = ['xlsx', 'xls'];
$fileName = $_FILES[$fileKey]['name'];
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Format de fichier non supporté. Utilisez .xlsx ou .xls']);
    exit();
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Récupérer l'année académique active
    $stmtAnnee = $pdo->query("SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1");
    $anneeActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    
    if (!$anneeActive) {
        // Si pas d'année active, prendre la plus récente
        $stmtAnnee = $pdo->query("SELECT idannee_acad FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1");
        $anneeActive = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$anneeActive) {
        echo json_encode(['success' => false, 'message' => 'Aucune année académique trouvée']);
        exit();
    }
    
    $anneeId = $anneeActive['idannee_acad'];
    
    // Charger le fichier Excel
    $spreadsheet = IOFactory::load($_FILES[$fileKey]['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    
    $imported = 0;
    $errors = [];
    $skipped = 0;
    
    // Démarrer la transaction
    $pdo->beginTransaction();
    
    // Préparer les requêtes
    $stmtFindSoutenance = $pdo->prepare("
        SELECT so.idsoutenance 
        FROM soutenance so
        INNER JOIN sujets sj ON so.sujets_idsujets = sj.idsujets
        INNER JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
        WHERE e.matricule = :matricule
        AND so.annee_acad_idannee_acad = :annee_id
        LIMIT 1
    ");
    
    $stmtUpdate = $pdo->prepare("
        UPDATE soutenance 
        SET note_finale = :note, 
            date_encodage_note = NOW(), 
            id_encodeur = :encodeur
        WHERE idsoutenance = :idsoutenance
    ");
    
    // Parcourir les lignes (en supposant que la ligne 1 contient les en-têtes)
    // Format accepté: 
    //   - Format export complet (Matricule col B, Note col K)
    //   - Format export ancien (Matricule col B, Note col I)
    //   - Format simplifié (Matricule col A, Note col B)
    for ($row = 2; $row <= $highestRow; $row++) {
        // Détecter le format: si colonne B contient un matricule valide, utiliser format export
        $colBValue = trim((string) $sheet->getCell('B' . $row)->getValue());
        $colAValue = trim((string) $sheet->getCell('A' . $row)->getValue());
        $colKValue = $sheet->getCell('K' . $row)->getValue();
        $colIValue = $sheet->getCell('I' . $row)->getValue();
        
        // Format export: Matricule en B, Note en K (ou I pour ancien format)
        // Format simplifié: Matricule en A, Note en B
        if (!empty($colBValue) && !is_numeric($colBValue)) {
            // Format export (B=Matricule)
            $matricule = $colBValue;
            // Vérifier K d'abord (nouveau format), puis I (ancien format)
            if ($colKValue !== null && $colKValue !== '') {
                $noteValue = $colKValue;
            } else {
                $noteValue = $colIValue;
            }
        } else {
            // Format simplifié (A=Matricule, B=Note)
            $matricule = $colAValue;
            $noteValue = $sheet->getCell('B' . $row)->getValue();
        }
        
        // Ignorer les lignes vides
        if (empty($matricule)) {
            continue;
        }
        
        // Valider la note
        if ($noteValue === null || $noteValue === '') {
            $skipped++;
            continue;
        }
        
        $note = floatval(str_replace(',', '.', $noteValue));
        
        if ($note < 0 || $note > 20) {
            $errors[] = "Ligne $row: Note invalide ($note) pour matricule $matricule - doit être entre 0 et 20";
            continue;
        }
        
        // Trouver la soutenance pour ce matricule et cette année
        $stmtFindSoutenance->execute([
            'matricule' => $matricule,
            'annee_id' => $anneeId
        ]);
        
        $soutenance = $stmtFindSoutenance->fetch(PDO::FETCH_ASSOC);
        
        if (!$soutenance) {
            $errors[] = "Ligne $row: Aucune soutenance trouvée pour le matricule $matricule";
            continue;
        }
        
        // Mettre à jour la note
        $result = $stmtUpdate->execute([
            'note' => $note,
            'encodeur' => $_SESSION['id'],
            'idsoutenance' => $soutenance['idsoutenance']
        ]);
        
        if ($result) {
            $imported++;
        } else {
            $errors[] = "Ligne $row: Erreur lors de la mise à jour pour matricule $matricule";
        }
    }
    
    // Valider la transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Import terminé: $imported note(s) importée(s)",
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => array_slice($errors, 0, 20)
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur import_notes_soutenance (DB): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur import_notes_soutenance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la lecture du fichier Excel']);
}
