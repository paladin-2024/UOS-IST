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
    $datePresence = isset($_POST['datePresence']) ? trim($_POST['datePresence']) : '';
    $presents = isset($_POST['present']) && is_array($_POST['present']) ? $_POST['present'] : [];
    $arrivees = isset($_POST['heureArrivee']) && is_array($_POST['heureArrivee']) ? $_POST['heureArrivee'] : [];
    $departs = isset($_POST['heureDepart']) && is_array($_POST['heureDepart']) ? $_POST['heureDepart'] : [];
    $comments = isset($_POST['commentaire']) && is_array($_POST['commentaire']) ? $_POST['commentaire'] : [];
    $userId = $_SESSION['id'];

    try {
        if (empty($datePresence)) throw new Exception('La date est obligatoire.');
        $todayYmd = date('Y-m-d');
        if ($datePresence > $todayYmd) throw new Exception("Impossible d'enregistrer une présence dans le futur.");
        $interval = (new DateTime($todayYmd))->diff(new DateTime($datePresence))->days;
        if ($datePresence < $todayYmd && $interval > 3) throw new Exception("La saisie manuelle est limitée à 3 jours en arrière.");
        if (!$agentModel->isWorkingDay($datePresence)) throw new Exception("Le jour sélectionné n'est pas ouvrable.");

        $saved = 0; $skippedDup = 0; $errors = 0;

        // Build set of agent IDs to consider (union of keys)
        $idsSet = [];
        foreach (array_keys($presents) as $id) { $idsSet[(int)$id] = true; }
        foreach (array_keys($arrivees) as $id) { $idsSet[(int)$id] = true; }
        foreach (array_keys($departs) as $id) { $idsSet[(int)$id] = true; }
        foreach (array_keys($comments) as $id) { $idsSet[(int)$id] = true; }

        $pdo->beginTransaction();
        foreach (array_keys($idsSet) as $agentId) {
            $agentId = (int)$agentId;
            // Restreindre aux agents Administratifs uniquement
            $ag = $agentModel->getAgentById($agentId);
            if (!$ag || (isset($ag['type_agent']) && $ag['type_agent'] !== 'Administratif')) {
                continue;
            }
            $arrRaw = isset($arrivees[$agentId]) ? trim($arrivees[$agentId]) : '';
            $depRaw = isset($departs[$agentId]) ? trim($departs[$agentId]) : '';
            $com = isset($comments[$agentId]) ? trim($comments[$agentId]) : '';

            $rowChecked = isset($presents[$agentId]) || $arrRaw !== '' || $depRaw !== '' || $com !== '';
            if (!$rowChecked) continue;

            $arr = $arrRaw !== '' ? ($datePresence . ' ' . $arrRaw . ':00') : null;
            $dep = $depRaw !== '' ? ($datePresence . ' ' . $depRaw . ':00') : null;

            if ($agentModel->existsDailyPresence($agentId, $datePresence)) { $skippedDup++; continue; }
            if ($arr && $dep && strtotime($dep) < strtotime($arr)) { $errors++; continue; }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ok = $agentModel->addDailyPresence($agentId, $datePresence, $arr, $dep, $com, $userId, $ip, $ua);
            if ($ok) $saved++; else $errors++;
        }
        $pdo->commit();

        $msg = "Enregistrés: $saved, doublons: $skippedDup";
        if ($errors > 0) { $msg .= ", erreurs: $errors"; }

        echo "<script>
            Swal.fire({ icon:'success', title:'Encodage terminé', text: '" . $msg . "' }).then(()=>{ window.location.href='../grh/agent.pres.add'; });
        </script>";
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo "<script>
            Swal.fire({ icon:'error', title:'Erreur', text:'" . addslashes($e->getMessage()) . "' }).then(()=>{ window.location.href='../grh/agent.pres.add'; });
        </script>";
        exit();
    }
} else {
    header('Location: ../grh/agent.pres.add');
    exit();
}
?>
