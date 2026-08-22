<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit();
}

$agentModel = new Agent();
$pdo = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agentId = isset($_POST['agentId']) ? intval($_POST['agentId']) : 0;
    $datePresence = isset($_POST['datePresence']) ? trim($_POST['datePresence']) : '';
    $heureArrivee = isset($_POST['heureArrivee']) ? trim($_POST['heureArrivee']) : '';
    $heureDepart = isset($_POST['heureDepart']) ? trim($_POST['heureDepart']) : '';
    $commentaire = isset($_POST['commentaire']) ? trim($_POST['commentaire']) : '';
    $userId = $_SESSION['id'];

    try {
        if ($agentId <= 0 || empty($datePresence)) {
            throw new Exception("Agent et date de présence sont obligatoires.");
        }

        // Vérifier que l'agent est de type Administratif
        $ag = $agentModel->getAgentById($agentId);
        if (!$ag || (isset($ag['type_agent']) && $ag['type_agent'] !== 'Administratif')) {
            throw new Exception("Cet agent n'est pas éligible aux présences (Administratif requis).");
        }

        // Security rules (compare on date-only to avoid TZ issues)
        $todayYmd = date('Y-m-d');
        $dateObj = DateTime::createFromFormat('!Y-m-d', $datePresence);
        if (!$dateObj) throw new Exception('Format de date invalide.');
        if ($datePresence > $todayYmd) throw new Exception("Impossible d'enregistrer une présence dans le futur.");

        // Backfill limit: max 3 days in the past
        $interval = (new DateTime($todayYmd))->diff(new DateTime($datePresence))->days;
        if ($datePresence < $todayYmd && $interval > 3) {
            throw new Exception("La saisie manuelle est limitée à 3 jours en arrière.");
        }

        // Working day check
        if (!$agentModel->isWorkingDay($datePresence)) {
            throw new Exception("Le jour sélectionné n'est pas un jour ouvrable selon la configuration.");
        }

        // Validate times
        $fullArrivee = null; $fullDepart = null;
        if (!empty($heureArrivee)) {
            $fullArrivee = $datePresence . ' ' . $heureArrivee . ':00';
        }
        if (!empty($heureDepart)) {
            $fullDepart = $datePresence . ' ' . $heureDepart . ':00';
        }
        if ($fullArrivee && $fullDepart && strtotime($fullDepart) < strtotime($fullArrivee)) {
            throw new Exception("L'heure de sortie ne peut pas être antérieure à l'heure d'arrivée.");
        }

        // Duplicate check
        if ($agentModel->existsDailyPresence($agentId, $datePresence)) {
            throw new Exception("Une présence pour cette date existe déjà.");
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $pdo->beginTransaction();
        $agentModel->addDailyPresence($agentId, $datePresence, $fullArrivee, $fullDepart, $commentaire, $userId, $ip, $ua);
        $pdo->commit();

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Présence enregistrée',
                text: 'La présence journalière a été enregistrée avec succès.'
            }).then(() => { window.location.href = '../grh/agent.pres.add'; });
        </script>";
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => { window.location.href = '../grh/agent.pres.add'; });
        </script>";
        exit();
    }
} else {
    header('Location: ../grh/agent.pres.add');
    exit();
}
?>
