<?php

/**
 * Contrôleur pour calculer les compensations inter-UE
 * 
 * Calcule intelligemment quelles UE peuvent être compensées entre elles
 * en tenant compte du mode sélectionné (même pondération ou libre).
 */

header('Content-Type: application/json');

session_start();

require_once '../config/Connexion.php';
require_once '../models/Etudiant.php';
require_once '../models/Deliberation.php';
require_once '../models/Universite.php';

// Vérifier l'authentification
if (!isset($_SESSION['idRole'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les droits (Admin ou Membre du Jury)
require_once '../models/Agent.php';
$isAdmin = $_SESSION['idRole'] == 1;
$isJuryMember = false;

if (!$isAdmin) {
    $agent = new Agent();
    $deliberation = new Deliberation();
    $userId = $_SESSION['id'];
    $agentId = $agent->getAgentIdByUserId($userId);
    
    if ($agentId) {
        $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
        $isJuryMember = !empty($juryBureaux);
    }
}

if (!$isAdmin && !$isJuryMember) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['mode'])) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }

    $mode = $data['mode']; // 'same_weight' ou 'any'
    $db = Connexion::getInstance()->getPDO();
    $deliberation = new Deliberation();
    $universite = new Universite();

    // Récupérer la configuration du credit horaire
    $configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $config = $configQuery->fetch(PDO::FETCH_ASSOC);
    $creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;

    // Récupérer les UE du semestre
    $promotionId = $data['promotion'];
    $semestreId = $data['semestre'];
    $sessionId = $data['session'];
    $anneeId = $data['annee'];
    $deuxSemestres = isset($data['deux_semestres']) && $data['deux_semestres'] == '1';

    // Vérifier si c'est la deuxième session
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

    // Récupérer les semestres à traiter
    $semestresQuery = $db->prepare("
        SELECT idsemestre FROM semestre 
        WHERE promotion_idpromotion = ?
        ORDER BY \"numeroSemestre\"
    ");
    $semestresQuery->execute([$promotionId]);
    $semestres = $semestresQuery->fetchAll(PDO::FETCH_ASSOC);
    
    $semestresToProcess = [];
    if ($deuxSemestres) {
        $semestresToProcess = array_column($semestres, 'idsemestre');
    } else {
        $semestresToProcess = [$semestreId];
    }

    // Récupérer les UE des semestres concernés
    $placeholders = implode(',', array_fill(0, count($semestresToProcess), '?'));
    $uesQuery = $db->prepare("
        SELECT DISTINCT u.\"idUE\", u.\"designationUE\", u.semestre_idsemestre,
               SUM((e.CMI + e.TD + e.TP) / ?) as total_credits
        FROM ue u
        LEFT JOIN ecue e ON u.\"idUE\" = e.\"UE_idUE\"
        WHERE u.semestre_idsemestre IN ({$placeholders})
        GROUP BY u.\"idUE\", u.\"designationUE\", u.semestre_idsemestre
        ORDER BY u.\"designationUE\"
    ");
    $params = array_merge([$creditHeure], $semestresToProcess);
    $uesQuery->execute($params);
    $ues = $uesQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($ues)) {
        echo json_encode(['success' => false, 'message' => 'Aucune UE trouvée']);
        exit;
    }

    // Créer un map des UE par ID avec leurs crédits
    $uesMap = [];
    foreach ($ues as $ue) {
        $uesMap[$ue['idUE']] = [
            'designation' => $ue['designationUE'],
            'credits' => (float)$ue['total_credits'],
            'semestre' => $ue['semestre_idsemestre']
        ];
    }

    // Récupérer les étudiants selon la session
    if ($isDeuxiemeSession) {
        // En deuxième session, récupérer les étudiants éligibles
        $semestresToShow = [];
        foreach ($semestresToProcess as $semId) {
            $semestresToShow[] = ['idsemestre' => $semId];
        }
        $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
    } else {
        // En première session, tous les étudiants
        $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    }

    if (empty($etudiants)) {
        echo json_encode(['success' => false, 'message' => 'Aucun étudiant trouvé']);
        exit;
    }

    $compensations = [];

    // Pour chaque étudiant
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];
        $nomEtudiant = $etudiant['noms'] ?? $matricule;

        // Récupérer les moyennes et validations des UE pour cet étudiant
        $moyennesUEEtudiant = $data['moyennesUE'][$matricule] ?? [];
        $validationsUEEtudiant = $data['validationsUE'][$matricule] ?? [];
        $notesByEcueEtudiant = $data['notesByEtudiantEcue'][$matricule] ?? [];

        if (empty($moyennesUEEtudiant)) {
            continue; // Pas de données pour cet étudiant
        }

        // Trouver les UE déficitaires (< 10)
        $uesDeficitaires = [];
        foreach ($moyennesUEEtudiant as $ueId => $moyenne) {
            if ($moyenne !== null && $moyenne < 10) {
                $uesDeficitaires[] = [
                    'ueId' => intval($ueId),
                    'moyenne' => (float)$moyenne,
                    'designation' => $uesMap[$ueId]['designation'] ?? "UE {$ueId}",
                    'credits' => $uesMap[$ueId]['credits'] ?? 0
                ];
            }
        }

        // Trouver les UE compensatrices (>= 10)
        $uesCompensatrices = [];
        foreach ($moyennesUEEtudiant as $ueId => $moyenne) {
            if ($moyenne !== null && $moyenne >= 10) {
                $uesCompensatrices[] = [
                    'ueId' => intval($ueId),
                    'moyenne' => (float)$moyenne,
                    'designation' => $uesMap[$ueId]['designation'] ?? "UE {$ueId}",
                    'credits' => $uesMap[$ueId]['credits'] ?? 0
                ];
            }
        }

        // Créer les paires déficitaire-compensatrice
        foreach ($uesDeficitaires as $deficitaire) {
            foreach ($uesCompensatrices as $compensatrice) {
                // Appliquer le filtre de pondération si nécessaire
                if ($mode === 'same_weight' && abs($deficitaire['credits'] - $compensatrice['credits']) > 0.01) {
                    continue; // Sauter si les crédits ne correspondent pas exactement
                }

                // Calculer la réduction estimée
                // Le déficit à couvrir = (10 - moyenne_déficitaire) * credits_déficitaire
                $deficitTotal = (10 - $deficitaire['moyenne']) * $deficitaire['credits'];
                
                // La réduction estimée = déficit / credits_compensatrice
                $reductionEstimee = $deficitTotal / $compensatrice['credits'];

                // Vérifier que la réduction ne fait pas tomber la moyenne en dessous de 10
                if ($compensatrice['moyenne'] - $reductionEstimee < 10 - 0.01) {
                    // La compensation n'est pas applicable
                    $compensations[] = [
                        'matricule' => $matricule,
                        'etudiantNoms' => $nomEtudiant,
                        'ueDeficitaireId' => $deficitaire['ueId'],
                        'ueDeficitaireDesignation' => $deficitaire['designation'],
                        'moyenneDeficitaire' => $deficitaire['moyenne'],
                        'ueCompensatriceId' => $compensatrice['ueId'],
                        'ueCompensatriceDesignation' => $compensatrice['designation'],
                        'creditsUE' => $compensatrice['credits'],
                        'reductionEstimee' => $reductionEstimee,
                        'estApplicable' => false,
                        'raison' => 'Réduction trop importante pour l\'UE compensatrice'
                    ];
                } else {
                    // La compensation est applicable
                    $compensations[] = [
                        'matricule' => $matricule,
                        'etudiantNoms' => $nomEtudiant,
                        'ueDeficitaireId' => $deficitaire['ueId'],
                        'ueDeficitaireDesignation' => $deficitaire['designation'],
                        'moyenneDeficitaire' => $deficitaire['moyenne'],
                        'ueCompensatriceId' => $compensatrice['ueId'],
                        'ueCompensatriceDesignation' => $compensatrice['designation'],
                        'creditsUE' => $compensatrice['credits'],
                        'reductionEstimee' => $reductionEstimee,
                        'estApplicable' => true,
                        'raison' => null
                    ];
                }
            }
        }
    }

    if (empty($compensations)) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune compensation inter-UE disponible avec les critères sélectionnés.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'compensations' => $compensations,
            'message' => count($compensations) . ' compensation(s) disponible(s)'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
