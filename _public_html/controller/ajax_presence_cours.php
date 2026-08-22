<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$db = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

try {
    switch ($action) {
        case 'mark':
            $idSeance = intval($_POST['idSeance'] ?? 0);
            $idEtudiant = intval($_POST['idEtudiant'] ?? 0);
            $statut = $_POST['statut'] ?? 'Présent';
            $commentaire = $_POST['commentaire'] ?? null;

            if (!$idSeance || !$idEtudiant) throw new Exception('Paramètres invalides.');

            $stmtCheck = $db->prepare("SELECT idpresence FROM presence_cours WHERE idseance = :s AND idetudiant = :e");
            $stmtCheck->execute(['s' => $idSeance, 'e' => $idEtudiant]);
            if ($stmtCheck->fetch()) throw new Exception('Étudiant déjà enregistré.');

            $stmt = $db->prepare("INSERT INTO presence_cours (idseance, idetudiant, heure_arrivee, statut, commentaire, methode_enregistrement, ip_address, \"idUser\", date_enregistrement) VALUES (:s, :e, NOW(), :st, :c, 'Manuel', :ip, :u, NOW())");
            $stmt->execute([
                's' => $idSeance, 'e' => $idEtudiant, 'st' => $statut,
                'c' => $commentaire, 'ip' => $_SERVER['REMOTE_ADDR'], 'u' => $idUser
            ]);

            $idPresence = $db->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Présence enregistrée.', 'idPresence' => $idPresence]);
            break;

        case 'update':
            $idPresence = intval($_POST['idPresence'] ?? 0);
            $statut = $_POST['statut'] ?? '';
            $commentaire = $_POST['commentaire'] ?? '';

            if (!$idPresence) throw new Exception('Paramètres invalides.');

            $stmt = $db->prepare("UPDATE presence_cours SET statut = :st, commentaire = :c WHERE idpresence = :id");
            $stmt->execute(['st' => $statut, 'c' => $commentaire, 'id' => $idPresence]);

            echo json_encode(['success' => true, 'message' => 'Statut modifié.']);
            break;

        case 'delete':
            $idPresence = intval($_POST['idPresence'] ?? 0);
            if (!$idPresence) throw new Exception('Paramètres invalides.');

            $stmt = $db->prepare("DELETE FROM presence_cours WHERE idpresence = :id");
            $stmt->execute(['id' => $idPresence]);

            echo json_encode(['success' => true, 'message' => 'Présence supprimée.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
