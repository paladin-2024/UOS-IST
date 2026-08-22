<?php
// Simple test pour vérifier si le contrôleur fonctionne
session_start();

// Simuler une session valide pour les tests
if (!isset($_SESSION['id'])) {
    $_SESSION['id'] = 1;
    $_SESSION['idRole'] = 1;
}

// Rediriger vers le contrôleur avec des paramètres de test
$testUrl = "load_more_sujets.php?page=1&limit=5&search=&filter_cycle=&filter_specialisation=&filter_statut=&filter_annee=&filter_affectation=";

header("Location: $testUrl");
?>
