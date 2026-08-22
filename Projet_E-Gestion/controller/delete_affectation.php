<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php?view=ur/affecation_ur');
    exit;
}

$id = intval($_GET['id']);
$section = isset($_GET['section']) ? intval($_GET['section']) : 0;
$annee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

$db = Connexion::getInstance()->getPDO();

try {
    $stmt = $db->prepare("DELETE FROM enseignant_specialisation WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "L'affectation a été supprimée avec succès.";
    } else {
        $_SESSION['warning'] = "Aucune affectation n'a été trouvée avec cet identifiant.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
}

// Rediriger vers la page d'affectation avec les mêmes filtres
$redirect = '../index.php?view=ur/affecation_ur';
if ($section > 0) {
        $redirect .= '&section=' . $section;
}
if ($annee > 0) {
    $redirect .= '&annee=' . $annee;
}

header('Location: ' . $redirect);
exit;
