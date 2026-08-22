<?php
session_start();
require_once dirname(__DIR__) . '/../config/Connexion.php';
require_once dirname(__DIR__) . '/../models/Etudiant.php';

if (!isset($_SESSION['student_id'])) {
    die("Pas de session");
}

$etudiantModel = new Etudiant();
$studentData = $etudiantModel->getEtudiantById($_SESSION['student_id']);

echo "<h2>Debug Photo Path</h2>";
echo "<hr>";

echo "<h3>Depuis la base de données:</h3>";
echo "Photo field: <code>" . ($studentData['photo'] ?? 'NULL') . "</code><br>";

echo "<h3>Depuis la session:</h3>";
echo "Photo session: <code>" . ($_SESSION['photo'] ?? 'NULL') . "</code><br>";

echo "<h3>Structure de dossiers:</h3>";
echo "Current file: <code>" . __FILE__ . "</code><br>";
echo "Uploads dir: <code>" . realpath(dirname(__DIR__) . '/../uploads') . "</code><br>";

echo "<h3>Chemin construit:</h3>";
$photoPath = isset($studentData['photo']) && !empty($studentData['photo'])
    ? '../../uploads/' . $studentData['photo']
    : '../../uploads/user.png';
echo "Chemin: <code>" . $photoPath . "</code><br>";

echo "<h3>Test de fichiers:</h3>";
echo "user.png existe: " . (file_exists(dirname(__DIR__) . '/../uploads/user.png') ? 'OUI' : 'NON') . "<br>";

if (!empty($studentData['photo'])) {
    $fullPath = dirname(__DIR__) . '/../uploads/' . $studentData['photo'];
    echo "Photo existe: " . (file_exists($fullPath) ? 'OUI' : 'NON') . " (chemin: <code>$fullPath</code>)<br>";
}

echo "<h3>Test affichage:</h3>";
echo '<img src="' . $photoPath . '" width="100" onerror="this.style.border=\'3px solid red\'" onload="this.style.border=\'3px solid green\'">';
echo '<br><small>Bordure verte = OK, rouge = erreur</small>';
?>
