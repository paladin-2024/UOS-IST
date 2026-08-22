<?php
session_start();
require_once '../config/Connexion.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Aucun fichier n\'a été téléversé ou une erreur s\'est produite']);
    exit;
}

$startRow = isset($_POST['startRow']) ? intval($_POST['startRow']) : 2;
$colNom = isset($_POST['colNom']) ? strtoupper($_POST['colNom']) : 'B';
$colMatricule = isset($_POST['colMatricule']) ? strtoupper($_POST['colMatricule']) : 'A';
$colPourcentage = isset($_POST['colPourcentage']) ? strtoupper($_POST['colPourcentage']) : 'C';
$colDecision = isset($_POST['colDecision']) ? strtoupper($_POST['colDecision']) : 'D';
$colCreditsValides = isset($_POST['colCreditsValides']) ? strtoupper($_POST['colCreditsValides']) : null;
$type = isset($_POST['type_palmares']) ? $_POST['type_palmares'] : 'classique';

try {
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();

    $etudiants = [];
    $highestRow = $worksheet->getHighestRow();

    for ($row = $startRow; $row <= $highestRow; $row++) {
        $nom = $worksheet->getCell($colNom . $row)->getValue();
        if (empty($nom)) continue;
        $matricule = $worksheet->getCell($colMatricule . $row)->getValue();
        $pourcentage = $worksheet->getCell($colPourcentage . $row)->getValue();
        $decision = $worksheet->getCell($colDecision . $row)->getValue();

        if (empty($decision) && !empty($pourcentage)) {
            if ($type === 'lmd') {
                $m = floatval($pourcentage);
                if ($m >= 16) $decision = 'Très Bien';
                elseif ($m >= 14) $decision = 'Bien';
                elseif ($m >= 12) $decision = 'Assez Bien';
                elseif ($m >= 10) $decision = 'Satisfaction';
                else $decision = 'Ajourné';
            } else {
                $p = floatval($pourcentage);
                if ($p >= 90) $decision = 'Très grande distinction';
                elseif ($p >= 80) $decision = 'Grande Distinction';
                elseif ($p >= 70) $decision = 'Distinction';
                elseif ($p >= 50) $decision = 'Satisfaction';
                else $decision = 'Ajourné';
            }
        }

        $rowData = [
            'nom_complet' => $nom,
            'matricule' => $matricule,
            'pourcentage' => $pourcentage,
            'decision' => $decision
        ];
        if ($colCreditsValides) {
            $rowData['credits_valides'] = $worksheet->getCell($colCreditsValides . $row)->getValue();
        }
        $etudiants[] = $rowData;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'etudiants' => $etudiants
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Erreur lors du traitement du fichier: ' . $e->getMessage()
    ]);
}