<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Session</title>
</head>
<body>
    <h2>Contenu de la session</h2>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h2>Test d'inclusion</h2>
    <?php
    require_once dirname(__DIR__) . '/../config/Connexion.php';
    require_once dirname(__DIR__) . '/../models/Etudiant.php';
    require_once dirname(__DIR__) . '/../models/Universite.php';
    
    if (isset($_SESSION['student_id'])) {
        $etudiantModel = new Etudiant();
        $universite = new Universite();
        $studentId = $_SESSION['student_id'];
        $studentName = $_SESSION['student_name'] ?? 'Étudiant';
        $studentMatricule = $_SESSION['student_matricule'] ?? '';
        $currentYear = $universite->getAnneeAcademiqueById($_SESSION['annee_acad']);
        
        echo "<h3>Variables initialisées:</h3>";
        echo "studentId: " . $studentId . "<br>";
        echo "studentName: " . $studentName . "<br>";
        echo "studentMatricule: " . $studentMatricule . "<br>";
        echo "Session photo: " . ($_SESSION['photo'] ?? 'NULL') . "<br>";
        
        echo "<h3>Test d'inclusion sidebar.php:</h3>";
        echo "<div style='border: 2px solid blue; padding: 10px;'>";
        include "includes/sidebar.php";
        echo "</div>";
        
        echo "<h3>Code source généré:</h3>";
        ob_start();
        include "includes/sidebar.php";
        $html = ob_get_clean();
        echo "<textarea rows='20' cols='100'>" . htmlspecialchars($html) . "</textarea>";
    } else {
        echo "Pas de session student_id";
    }
    ?>
</body>
</html>
