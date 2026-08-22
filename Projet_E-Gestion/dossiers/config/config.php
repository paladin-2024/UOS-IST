<?php
/**
 * Configuration du module Dossiers
 * Module autonome MVC pour la gestion des dossiers étudiants
 */

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chemins du module
define('DOSSIERS_ROOT', dirname(__DIR__));
define('APP_ROOT', dirname(DOSSIERS_ROOT));

// Configuration des uploads
define('UPLOAD_DIR', DOSSIERS_ROOT . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 Mo
define('ALLOWED_EXTENSIONS', ['pdf']);
define('ALLOWED_MIME_TYPES', ['application/pdf']);

// Connexion à la base de données de l'application principale
require_once APP_ROOT . '/config/Connexion.php';

// Créer le répertoire d'upload s'il n'existe pas
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Redirection vers une URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Vérifier si un étudiant est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['dossier_student_id']);
}

/**
 * Vérifier si l'utilisateur est un administrateur connecté via le module dossiers
 */
function isAdmin() {
    return isset($_SESSION['dossier_admin_id']);
}

/**
 * Exiger la connexion étudiant, sinon rediriger vers le formulaire de connexion
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php?action=login');
    }
}

/**
 * Exiger la connexion administrateur, sinon rediriger vers le login
 */
function requireAdmin() {
    if (!isAdmin()) {
        redirect('index.php?action=login');
    }
}

/**
 * Nettoyer une chaîne pour l'affichage HTML
 */
function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Formater une taille de fichier en format lisible
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' Go';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' Mo';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' Ko';
    }
    return $bytes . ' octets';
}
