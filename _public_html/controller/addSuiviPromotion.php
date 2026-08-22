<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuiviCours.php';

// Capturer tout output parasite (warnings PHP, notices) pour ne pas corrompre le JSON
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$idsection            = filter_input(INPUT_POST, 'idsection',             FILTER_VALIDATE_INT);
$idannee              = filter_input(INPUT_POST, 'idannee',               FILTER_VALIDATE_INT);
$designationPromotion = trim(htmlspecialchars(strip_tags($_POST['designationPromotion'] ?? ''), ENT_QUOTES));
$cycle                = htmlspecialchars(strip_tags($_POST['cycle'] ?? ''), ENT_QUOTES);
$idorientation        = filter_input(INPUT_POST, 'idorientation',         FILTER_VALIDATE_INT);
$nouvelleOrientation  = trim(htmlspecialchars(strip_tags($_POST['nouvelleOrientation'] ?? ''), ENT_QUOTES));
$est_terminale        = filter_input(INPUT_POST, 'est_terminale',         FILTER_VALIDATE_INT) ?? 0;
$semestres            = htmlspecialchars(strip_tags($_POST['semestres']   ?? ''), ENT_QUOTES);

if (!$idsection || !$idannee || !$designationPromotion || !$cycle) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit;
}

$cyclesValides = ['Premier', 'Deuxieme', 'Troisieme'];
if (!in_array($cycle, $cyclesValides)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Cycle invalide']);
    exit;
}

$db = null;
try {
    $model = new SuiviCours();
    $db    = Connexion::getInstance()->getPDO();
    $db->beginTransaction();

    if ($idorientation) {
        $idOrient = $idorientation;
    } elseif ($nouvelleOrientation !== '') {
        $idOrient = $model->getOrCreateOrientation($nouvelleOrientation, $idsection);
    } else {
        $db->rollBack();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Veuillez choisir ou créer une orientation']);
        exit;
    }

    $idpromotion = $model->addPromotion($designationPromotion, $cycle, $idOrient, $idannee, (int)$est_terminale);

    $semestresCrees = [];
    if ($semestres !== '') {
        foreach (explode(',', $semestres) as $num) {
            $num = trim($num);
            if ($num !== '') {
                $idsemestre       = $model->addSemestre($num, $idpromotion);
                $semestresCrees[] = ['id' => $idsemestre, 'numero' => $num];
            }
        }
    }

    $db->commit();
    ob_end_clean();
    echo json_encode([
        'success'     => true,
        'message'     => 'Promotion créée avec succès',
        'idpromotion' => $idpromotion,
        'semestres'   => $semestresCrees,
    ]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('addSuiviPromotion: ' . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
