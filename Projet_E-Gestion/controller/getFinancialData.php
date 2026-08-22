<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Comptabilite.php';
session_start();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['startDate'], $input['endDate'], $input['structureId'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$startDate = $input['startDate'];
$endDate = $input['endDate'];
$structureId = $input['structureId'];
$userId=$_SESSION['id'];

try {
    $compta = new Comptabilite();

    // Fetch data
    $groupedRecettes = $compta->getDetailsRecettesParGroupe($userId,$structureId, $startDate, $endDate);
    $groupedDepenses = $compta->getDetailsDepensesParGroupe($userId,$structureId, $startDate, $endDate);
    $soldeReport = $compta->getSoldeReport($userId,$structureId, $startDate);
    $clientPayments = $compta->getPaiementsClients($structureId, $startDate, $endDate);
    $supplierPayments = $compta->getPaiementsFournisseurs($structureId, $startDate, $endDate);

    // Calculate totals
    $totalRecettes = array_sum(array_column($groupedRecettes, 'total_groupe')) + $clientPayments;
    $totalDepenses = array_sum(array_column($groupedDepenses, 'total_groupe')) + $supplierPayments;
    $soldeFinal = $soldeReport + $totalRecettes - $totalDepenses;

    // Prepare chart data
    $chartData = [
        'labels' => [],
        'values' => []
    ];

    foreach ($groupedRecettes as $recette) {
        $chartData['labels'][] = $recette['nom_groupe'];
        $chartData['values'][] = $recette['total_groupe'];
    }

    foreach ($groupedDepenses as $depense) {
        $chartData['labels'][] = $depense['nom_groupe'];
        $chartData['values'][] = -$depense['total_groupe']; // Negative for expenses
    }

    // Prepare table data
    $tableData = [];

    foreach ($groupedRecettes as $recette) {
        foreach ($recette['lignes'] as $ligne) {
            $tableData[] = [
                'type' => 'Entrée',
                'groupe' => $recette['nom_groupe'],
                'ligne' => $ligne['nom_ligne'],
                'montant' => number_format($ligne['total_ligne'], 2)
            ];
        }
    }

    foreach ($groupedDepenses as $depense) {
        foreach ($depense['lignes'] as $ligne) {
            $tableData[] = [
                'type' => 'Sortie',
                'groupe' => $depense['nom_groupe'],
                'ligne' => $ligne['nom_ligne'],
                'montant' => number_format($ligne['total_ligne'], 2)
            ];
        }
    }

    // Return the data as JSON
    echo json_encode([
        'chartData' => $chartData,
        'tableData' => $tableData,
        'soldeReport' => $soldeReport,
        'totalEntrees' => $totalRecettes,
        'totalSorties' => $totalDepenses,
        'soldeFinal' => $soldeFinal,
        'clientPayments' => $clientPayments,
        'supplierPayments' => $supplierPayments
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching data: ' . $e->getMessage()]);
}
?>