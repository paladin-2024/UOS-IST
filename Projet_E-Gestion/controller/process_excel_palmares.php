<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Assurez-vous d'avoir PhpSpreadsheet installé

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Utilisateur non connecté']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['excelFile'])) {
    echo json_encode(['error' => 'Méthode non autorisée ou fichier manquant']);
    exit;
}

try {
    // Récupérer les paramètres d'importation
    $startRow = intval($_POST['startRow'] ?? 2);
    $colNom = $_POST['colNom'] ?? 'A';
    $colMatricule = $_POST['colMatricule'] ?? 'B';
    $colPourcentage = $_POST['colPourcentage'] ?? 'C';
    $colMention = $_POST['colMention'] ?? 'D';
    $colRang = $_POST['colRang'] ?? 'E';
    $colCreditsObtenus = $_POST['colCreditsObtenus'] ?? 'F';
    $colCreditsTotaux = $_POST['colCreditsTotaux'] ?? 'G';
    
    // Charger le fichier Excel
    $inputFileName = $_FILES['excelFile']['tmp_name'];
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    $highestRow = $worksheet->getHighestDataRow();
    $etudiants = [];
    
    // Fonction pour déterminer la mention en fonction du pourcentage
    function getMentionFromPercentage($percentage) {
        if ($percentage >= 90) return 'La Plus Grande Distinction';
        if ($percentage >= 85) return 'Grande Distinction';
        if ($percentage >= 80) return 'Distinction';
        if ($percentage >= 75) return 'Excellent';
        if ($percentage >= 70) return 'Très Bien';
        if ($percentage >= 65) return 'Bien';
        if ($percentage >= 60) return 'Assez Bien';
        if ($percentage >= 50) return 'Passable';
        return '';
    }
    
    // Parcourir les lignes et extraire les données
    for ($row = $startRow; $row <= $highestRow; $row++) {
        $nomComplet = $worksheet->getCell($colNom . $row)->getValue();
        
        // Ignorer les lignes vides
        if (empty($nomComplet)) {
            continue;
        }
        
        $matricule = $worksheet->getCell($colMatricule . $row)->getValue();
        $pourcentage = floatval($worksheet->getCell($colPourcentage . $row)->getValue());
        
        // Récupérer la mention ou la calculer automatiquement
        $mention = $worksheet->getCell($colMention . $row)->getValue();
        if (empty($mention) && $pourcentage > 0) {
            $mention = getMentionFromPercentage($pourcentage);
        }
        
        $rang = intval($worksheet->getCell($colRang . $row)->getValue());
        $creditsObtenus = intval($worksheet->getCell($colCreditsObtenus . $row)->getValue());
        $creditsTotaux = intval($worksheet->getCell($colCreditsTotaux . $row)->getValue());
        
        $etudiants[] = [
            'nom_complet' => $nomComplet,
            'matricule' => $matricule,
            'pourcentage' => $pourcentage,
            'mention' => $mention,
            'rang' => $rang ?: count($etudiants) + 1,
            'credit_obtenu' => $creditsObtenus,
            'credit_total' => $creditsTotaux
        ];
    }
    
    // Trier les étudiants par rang si nécessaire
    usort($etudiants, function($a, $b) {
        return $a['rang'] - $b['rang'];
    });
    
    echo json_encode([
        'success' => true,
        'etudiants' => $etudiants
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
}
?>