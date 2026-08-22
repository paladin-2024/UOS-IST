<?php
session_start();

echo "<h2>Test du chemin d'image généré</h2>";

$photo = $_SESSION['photo'] ?? null;
echo "Photo en session: <code>" . ($photo ?? 'NULL') . "</code><br><br>";

echo "<h3>Chemin généré:</h3>";
$imagePath = isset($_SESSION['photo']) && !empty($_SESSION['photo'])
    ? '../../uploads/' . $_SESSION['photo'] . '?t=' . time()
    : '../../uploads/user.png?t=' . time();

echo "URL complète: <code>" . $imagePath . "</code><br><br>";

echo "<h3>Test d'affichage:</h3>";
echo '<img src="' . $imagePath . '" width="100" style="border: 2px solid blue;" onerror="alert(\'Erreur de chargement!\'); this.style.border=\'3px solid red\'" onload="this.style.border=\'3px solid green\'">';
echo '<br><small>Bordure verte = image chargée, rouge = erreur</small><br><br>';

echo "<h3>Vérification du fichier:</h3>";
$fullPath = dirname(__DIR__) . '/../uploads/' . ($_SESSION['photo'] ?? 'user.png');
echo "Chemin absolu: <code>$fullPath</code><br>";
echo "Fichier existe: " . (file_exists($fullPath) ? '<span style="color:green">OUI ✓</span>' : '<span style="color:red">NON ✗</span>') . "<br><br>";

echo "<h3>Code HTML copié de sidebar.php:</h3>";
echo "<pre>";
echo htmlspecialchars('<img src="<?= isset($_SESSION[\'photo\']) && !empty($_SESSION[\'photo\'])
? \'../../uploads/\'.$_SESSION[\'photo\'].\'?t=\'.time()
: \'../../uploads/user.png?t=\'.time() ?>"
alt="Photo de profil"
     class="rounded-circle me-3"
     width="60"
     height="60"
     style="object-fit: cover; border: 3px solid var(--primary-light);">');
echo "</pre>";

echo "<h3>Rendu réel:</h3>";
?>
<img src="<?= isset($_SESSION['photo']) && !empty($_SESSION['photo'])
? '../../uploads/'.$_SESSION['photo'].'?t='.time()
: '../../uploads/user.png?t='.time() ?>"
alt="Photo de profil"
     class="rounded-circle me-3"
     width="60"
     height="60"
     style="object-fit: cover; border: 3px solid red;"
     onload="this.style.border='3px solid green'"
     onerror="alert('Image failed to load: ' + this.src)">
<br><small>Si bordure verte = OK</small>

<?php
echo "<h3>Test avec différents chemins:</h3>";
$tests = [
    '../../uploads/' . $_SESSION['photo'],
    '../uploads/' . $_SESSION['photo'],
    '/e_gestion/uploads/' . $_SESSION['photo'],
    'uploads/' . $_SESSION['photo'],
];

foreach ($tests as $i => $testPath) {
    echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>Test " . ($i + 1) . ":</strong> <code>$testPath</code><br>";
    echo '<img src="' . $testPath . '" width="80" style="border: 2px solid blue;" onerror="this.style.border=\'2px solid red\'" onload="this.style.border=\'2px solid green\'">';
    echo " <small>(vert=OK, rouge=erreur)</small>";
    echo "</div>";
}
?>
