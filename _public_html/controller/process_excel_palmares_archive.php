<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Vous devez être connecté pour effectuer cette action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Aucun fichier ou erreur lors de l\'upload.']);
    exit;
}

// Paramètres de mapping
$startRow = isset($_POST['startRow']) ? intval($_POST['startRow']) : 2;
$colMatricule = isset($_POST['colMatricule']) ? strtoupper($_POST['colMatricule']) : 'A';
$colNom = isset($_POST['colNom']) ? strtoupper($_POST['colNom']) : 'B';
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
        $matricule = $worksheet->getCell($colMatricule . $row)->getValue();
        $nom = $worksheet->getCell($colNom . $row)->getValue();
        if (empty($matricule) && empty($nom)) continue;

        $pourcentage = $worksheet->getCell($colPourcentage . $row)->getValue();
        $decision = $worksheet->getCell($colDecision . $row)->getValue();

        if (!empty($decision)) {
            $decision = normaliserDecision($decision);
        } elseif (!empty($pourcentage)) {
            if ($type === 'lmd') {
                $m = floatval($pourcentage);
                if ($m >= 16) $decision = 'Très Bien';
                elseif ($m >= 14) $decision = 'Bien';
                elseif ($m >= 12) $decision = 'Assez Bien';
                elseif ($m >= 10) $decision = 'Satisfaction';
                else $decision = 'Ajourné';
            } else {
                $decision = getDecisionFromPercentage(floatval($pourcentage));
            }
        }

        $rowData = [
            'matricule' => $matricule,
            'nom_complet' => $nom,
            'pourcentage' => $pourcentage,
            'decision' => $decision,
        ];
        if ($colCreditsValides) {
            $rowData['credits_valides'] = $worksheet->getCell($colCreditsValides . $row)->getValue();
        }
        $etudiants[] = $rowData;
    }

    echo json_encode(['etudiants' => $etudiants]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors du traitement du fichier: ' . $e->getMessage()]);
}

function normaliserDecision($decision) {
    $decision = trim($decision);
    if (preg_match('/(tr[eé]s\s+grande|la\s+plus\s+grande)\s+distinction/i', $decision)) return 'Très grande distinction';
    if (preg_match('/(grande)\s+distinction/i', $decision)) return 'Grande Distinction';
    if (preg_match('/distinction/i', $decision)) return 'Distinction';
    if (preg_match('/(satisfaction|r[eé]ussite|satisfaisant)/i', $decision)) return 'Satisfaction';
    if (preg_match('/(ajourn[eé]|[eé]chec)/i', $decision)) return 'Ajourné';
    if (preg_match('/(assimil[eé]).*(ajourn[eé])/i', $decision)) return 'Assimilé aux ajournés';
    if (preg_match('/(abandon)/i', $decision)) return 'Abandon';
    return $decision;
}

function getDecisionFromPercentage($p) {
    if ($p >= 90) return 'Très grande distinction';
    if ($p >= 80) return 'Grande Distinction';
    if ($p >= 70) return 'Distinction';
    if ($p >= 50) return 'Satisfaction';
    return 'Ajourné';
}

