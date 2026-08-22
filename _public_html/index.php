<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 300);
session_start();

//test ZZZz

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data:; font-src \'self\' https://cdnjs.cloudflare.com;');

define('BASE_PATH', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/');

include "config/Connexion.php";
include "config/chargement.php";

charger();

$allowedViews = include "config/allowed_views.php";

if (isset($_GET['view'])) {
    $requestedView = $_GET['view'];
    
    // Normaliser pour la vérification dans allowed_views (remplacer . par _)
    $normalizedView = str_replace(['/', '\\'], '/', $requestedView);
    $normalizedView = str_replace('.', '_', $normalizedView);
    $normalizedView = trim($normalizedView, '/');
    
    if (!in_array($normalizedView, $allowedViews)) {
        http_response_code(403);
        include "views/403.php";
        exit;
    }
    
    // Utiliser le nom original avec points pour le fichier
    $filenameView = str_replace(['/', '\\'], '/', $requestedView);
    $filenameView = trim($filenameView, '/');
    $filename = "views/" . $filenameView . ".php";
    if (is_file($filename) && preg_match('/^[a-zA-Z0-9_\-\/.]+$/', $filenameView)) {
        include $filename;
    } else {
        include "views/404.php";
    }
} else {
    include "views/accueil.php";
}