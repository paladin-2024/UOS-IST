<!DOCTYPE html>
<html lang="fr">

<!-- Head and importation des packages -->
<?php include("include/head_2.php"); 

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
$universite = new Universite();

$configUniversite = $universite->getConfigurationUniversite();
?>

<body style="margin: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;">

<?php
    if (!empty($configUniversite['logo'])) {
      $logoPath = '../' . $configUniversite['logo'];
      if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = mime_content_type($logoPath);
        echo '<img style="max-width: 100%; height: auto;" src="'.$logoPath.'" alt="Logo" width="448" height="50">';
      }else{
        echo 'Configurer le logo une fois connecté';
      }
    }
  ?>

  
    <!-- Footer et importation JavaScript -->
    <?php include("include/footer_3.php"); ?>
