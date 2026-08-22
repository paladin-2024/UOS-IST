<?php
require_once '../config/Connexion.php';
require_once '../models/Universite.php';

$universite = new Universite();


$configUniversitee = $universite->getConfigurationUniversite();

?>
<html>
<head>
	<meta charset="UTF-8">
	<meta content="width=device-width, initial-scale=1.0" name="viewport">

	<title><?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?></title>
	<meta content="" name="description">
	<meta content="" name="keywords">

	<?php if (!empty($configUniversitee['logo'])): ?>
        <!-- Favicons --> 
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="icon">
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>


	<!-- Vendor CSS Files -->
	<link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

	<!-- Template Main CSS File -->
	<link href="../assets/css/style.css" rel="stylesheet">
</head>