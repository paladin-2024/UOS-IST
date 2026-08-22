<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Test de diagnostic Excel</h3>";

// Test 1: Vérifier PHP Extensions
echo "<h4>1. Extensions PHP</h4>";
$required_extensions = ['zip', 'xml', 'mbstring', 'gd'];
foreach ($required_extensions as $ext) {
    echo "Extension $ext: " . (extension_loaded($ext) ? "✅ OK" : "❌ MANQUANT") . "<br>";
}

// Test 2: Vérifier Composer
echo "<h4>2. Composer & PhpSpreadsheet</h4>";
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
echo "Chemin vendor: $autoloadPath<br>";
echo "Vendor existe: " . (file_exists($autoloadPath) ? "✅ OK" : "❌ MANQUANT") . "<br>";

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    echo "PhpSpreadsheet: " . (class_exists('PhpOffice\PhpSpreadsheet\IOFactory') ? "✅ OK" : "❌ MANQUANT") . "<br>";
}

// Test 3: Vérifier dossier temp
echo "<h4>3. Dossier temporaire</h4>";
$tempDir = dirname(__DIR__) . '/temp';
echo "Chemin temp: $tempDir<br>";
echo "Temp existe: " . (is_dir($tempDir) ? "✅ OK" : "❌ MANQUANT") . "<br>";
echo "Temp writable: " . (is_writable(dirname($tempDir)) ? "✅ OK" : "❌ MANQUANT") . "<br>";

// Test 4: Limites PHP
echo "<h4>4. Configuration PHP</h4>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";

// Test 5: Créer un fichier Excel simple pour test
if (file_exists($autoloadPath)) {
    echo "<h4>5. Test création Excel</h4>";
    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Test');
        $sheet->setCellValue('B1', 'Excel');
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $tempFile = $tempDir . '/test_excel.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        
        echo "Création Excel: " . (file_exists($tempFile) ? "✅ OK" : "❌ ERREUR") . "<br>";
        
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
    } catch (Exception $e) {
        echo "Erreur création Excel: ❌ " . $e->getMessage() . "<br>";
    }
}

echo "<h4>6. Test simple d'upload</h4>";
echo '<form method="post" enctype="multipart/form-data">
        <input type="file" name="test_file" accept=".xlsx,.xls">
        <button type="submit">Test Upload</button>
      </form>';

if (isset($_FILES['test_file'])) {
    echo "Fichier reçu: " . $_FILES['test_file']['name'] . "<br>";
    echo "Taille: " . $_FILES['test_file']['size'] . " bytes<br>";
    echo "Type: " . $_FILES['test_file']['type'] . "<br>";
    echo "Erreur: " . $_FILES['test_file']['error'] . "<br>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK && file_exists($autoloadPath)) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['test_file']['tmp_name']);
            $spreadsheet = $reader->load($_FILES['test_file']['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            
            echo "Lecture Excel: ✅ OK<br>";
            echo "Lignes: " . $worksheet->getHighestRow() . "<br>";
            echo "Colonnes: " . $worksheet->getHighestColumn() . "<br>";
            
        } catch (Exception $e) {
            echo "Erreur lecture Excel: ❌ " . $e->getMessage() . "<br>";
        }
    }
}

// Test 7: Test AJAX comme dans l'interface
if (isset($_POST) && !empty($_POST) && !isset($_FILES['test_file'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'Fichier non reçu. Code erreur: ' . ($_FILES['fichier']['error'] ?? 'inconnu')
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Test AJAX réussi',
        'debug' => [
            'nom' => $_FILES['fichier']['name'],
            'taille' => $_FILES['fichier']['size'],
            'type' => $_FILES['fichier']['type']
        ]
    ]);
    exit;
}
?>
