<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$exercice_id = isset($_GET['exercice_id']) ? intval($_GET['exercice_id']) : 0;

if (!$exercice_id) {
    $_SESSION['message'] = "ID de l'exercice manquant.";
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_budget');
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations de l'exercice
    $stmt = $connexion->prepare("SELECT designation FROM exercices_budgetaires WHERE id = :id");
    $stmt->bindParam(':id', $exercice_id);
    $stmt->execute();
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        throw new Exception("Exercice budgétaire non trouvé");
    }
    
    // Récupérer les données du budget
    $stmt = $connexion->prepare("
        SELECT c.code, c.designation, c.type, b.montant_prevu, b.montant_revise, 
               b.montant_engage, b.montant_realise, b.disponible, b.commentaire
        FROM categories_budget c
        LEFT JOIN budget b ON c.id = b.categorie_id AND b.exercice_id = :exercice_id
        WHERE c.est_actif = 1
        ORDER BY c.type, c.niveau, c.code
    ");
    $stmt->bindParam(':exercice_id', $exercice_id);
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Définir les en-têtes pour le téléchargement
    $filename = "Budget_" . preg_replace('/[^a-zA-Z0-9]/', '_', $exercice['designation']) . "_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Créer le fichier CSV
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'Code',
        'Désignation',
        'Type',
        'Montant Prévu',
        'Montant Révisé',
        'Montant Engagé',
        'Montant Réalisé',
        'Disponible',
        'Commentaire'
    ]);
    
    // Données
    foreach ($budgets as $budget) {
        fputcsv($output, [
            $budget['code'],
            $budget['designation'],
            $budget['type'],
            $budget['montant_prevu'] ?? '0.00',
            $budget['montant_revise'] ?? '',
            $budget['montant_engage'] ?? '0.00',
            $budget['montant_realise'] ?? '0.00',
            $budget['disponible'] ?? '0.00',
            $budget['commentaire'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    $_SESSION['message'] = "Erreur lors de l'exportation du budget: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
    header('Location: ../?view=finance/config_budget&exercice_id=' . $exercice_id);
    exit;
}