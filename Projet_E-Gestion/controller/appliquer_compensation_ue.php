<?php

/**
 * Contrôleur pour appliquer les compensations inter-UE
 * 
 * Reçoit les compensations sélectionnées et les applique à la base de données
 * en ajustant les notes des ECUE de manière intelligente.
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
$deliberation = new Deliberation();

if (!$isAdmin) {
    $agent = new Agent();
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

    if (!$data || !isset($data['compensations']) || empty($data['compensations'])) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }

    $compensations = $data['compensations'];
    $db = Connexion::getInstance()->getPDO();

    // Récupérer la configuration du credit horaire
    $configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
    $config = $configQuery->fetch(PDO::FETCH_ASSOC);
    $creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;

    // Récupérer les paramètres
    $sessionId = $data['session'];
    $anneeId = $data['annee'];
    
    // Vérifier si c'est la deuxième session
    $universite = new Universite();
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;
    
    // Récupérer l'ID de la première session si nécessaire
    $premiereSessionId = null;
    if ($isDeuxiemeSession) {
        $premiereSessions = $universite->getSessions("Première session");
        if (!empty($premiereSessions)) {
            $premiereSessionId = $premiereSessions[0]['idsession'];
        }
    }

    // Démarrer une transaction
    $db->beginTransaction();

    $compteur = 0;
    $erreurs = [];
    $appliquees = [];

    // Grouper les compensations par étudiant
    $compensationsParEtudiant = [];
    foreach ($compensations as $comp) {
        $matricule = $comp['matricule'];
        if (!isset($compensationsParEtudiant[$matricule])) {
            $compensationsParEtudiant[$matricule] = [];
        }
        $compensationsParEtudiant[$matricule][] = $comp;
    }

    // Traiter chaque étudiant
    foreach ($compensationsParEtudiant as $matricule => $compsEtudiant) {
        try {
            // Grouper les compensations par paire déficitaire-compensatrice
            foreach ($compsEtudiant as $comp) {
                $ueDeficitaireId = $comp['ueDeficitaireId'];
                $ueCompensatriceId = $comp['ueCompensatriceId'];

                // Récupérer TOUTES les notes des deux UE
                // En deuxième session, combiner les notes S1 (pour ECUE >= 10) et S2 (pour ECUE < 10 ou repris)
                if ($isDeuxiemeSession && $premiereSessionId) {
                    // Récupérer les notes de l'UE déficitaire (prendre la meilleure note entre S1 et S2 pour chaque ECUE)
                    $getNotesDeficitaireQuery = $db->prepare("
                        SELECT e.idECUE as ECUE_idECUE, e.CMI, e.TD, e.TP,
                               COALESCE(
                                   CASE 
                                       WHEN cg1.MF >= 10 THEN cg1.MF
                                       WHEN cg2.MF IS NOT NULL THEN cg2.MF
                                       ELSE cg1.MF
                                   END,
                                   cg2.MF,
                                   cg1.MF
                               ) as MF,
                               CASE 
                                   WHEN cg1.MF >= 10 THEN ? 
                                   WHEN cg2.MF IS NOT NULL THEN ?
                                   ELSE ?
                               END as source_session
                        FROM ecue e
                        LEFT JOIN cotes_grille cg1 ON e.idECUE = cg1.ECUE_idECUE 
                            AND cg1.matricule = ? AND cg1.session_idsession = ? AND cg1.annee_acad_id = ?
                        LEFT JOIN cotes_grille cg2 ON e.idECUE = cg2.ECUE_idECUE 
                            AND cg2.matricule = ? AND cg2.session_idsession = ? AND cg2.annee_acad_id = ?
                        WHERE e.UE_idUE = ?
                        HAVING MF IS NOT NULL
                    ");
                    $getNotesDeficitaireQuery->execute([
                        $premiereSessionId, $sessionId, $premiereSessionId,
                        $matricule, $premiereSessionId, $anneeId,
                        $matricule, $sessionId, $anneeId,
                        $ueDeficitaireId
                    ]);
                    $notesDeficitaire = $getNotesDeficitaireQuery->fetchAll(PDO::FETCH_ASSOC);

                    // Récupérer les notes de l'UE compensatrice
                    $getNotesCompensatriceQuery = $db->prepare("
                        SELECT e.idECUE as ECUE_idECUE, e.CMI, e.TD, e.TP,
                               COALESCE(
                                   CASE 
                                       WHEN cg1.MF >= 10 THEN cg1.MF
                                       WHEN cg2.MF IS NOT NULL THEN cg2.MF
                                       ELSE cg1.MF
                                   END,
                                   cg2.MF,
                                   cg1.MF
                               ) as MF,
                               CASE 
                                   WHEN cg1.MF >= 10 THEN ? 
                                   WHEN cg2.MF IS NOT NULL THEN ?
                                   ELSE ?
                               END as source_session
                        FROM ecue e
                        LEFT JOIN cotes_grille cg1 ON e.idECUE = cg1.ECUE_idECUE 
                            AND cg1.matricule = ? AND cg1.session_idsession = ? AND cg1.annee_acad_id = ?
                        LEFT JOIN cotes_grille cg2 ON e.idECUE = cg2.ECUE_idECUE 
                            AND cg2.matricule = ? AND cg2.session_idsession = ? AND cg2.annee_acad_id = ?
                        WHERE e.UE_idUE = ?
                        HAVING MF IS NOT NULL
                    ");
                    $getNotesCompensatriceQuery->execute([
                        $premiereSessionId, $sessionId, $premiereSessionId,
                        $matricule, $premiereSessionId, $anneeId,
                        $matricule, $sessionId, $anneeId,
                        $ueCompensatriceId
                    ]);
                    $notesCompensatrice = $getNotesCompensatriceQuery->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Première session: requête simple
                    $getNotesDeficitaireQuery = $db->prepare("
                        SELECT cg.ECUE_idECUE, cg.MF, e.CMI, e.TD, e.TP, ? as source_session
                        FROM cotes_grille cg
                        JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                        WHERE cg.matricule = ? AND e.UE_idUE = ? AND cg.session_idsession = ? AND cg.annee_acad_id = ?
                    ");
                    $getNotesDeficitaireQuery->execute([$sessionId, $matricule, $ueDeficitaireId, $sessionId, $anneeId]);
                    $notesDeficitaire = $getNotesDeficitaireQuery->fetchAll(PDO::FETCH_ASSOC);

                    $getNotesCompensatriceQuery = $db->prepare("
                        SELECT cg.ECUE_idECUE, cg.MF, e.CMI, e.TD, e.TP, ? as source_session
                        FROM cotes_grille cg
                        JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
                        WHERE cg.matricule = ? AND e.UE_idUE = ? AND cg.session_idsession = ? AND cg.annee_acad_id = ?
                    ");
                    $getNotesCompensatriceQuery->execute([$sessionId, $matricule, $ueCompensatriceId, $sessionId, $anneeId]);
                    $notesCompensatrice = $getNotesCompensatriceQuery->fetchAll(PDO::FETCH_ASSOC);
                }

                if (empty($notesDeficitaire) || empty($notesCompensatrice)) {
                    throw new Exception("Données manquantes pour les UE");
                }

                // === ÉTAPE 1: RELEVER L'UE DÉFICITAIRE À 10 ===
                // Calculer le déficit total
                $totalCoeffDeficitaire = 0;
                $totalPointsDeficitaire = 0;
                $notesMapDeficitaire = [];

                foreach ($notesDeficitaire as $note) {
                    $credit = ($note['CMI'] + $note['TD'] + $note['TP']) / $creditHeure;
                    $mf = (float)$note['MF'];
                    $sourceSession = isset($note['source_session']) ? $note['source_session'] : $sessionId;
                    
                    $notesMapDeficitaire[$note['ECUE_idECUE']] = [
                        'note' => $mf,
                        'credit' => $credit,
                        'source_session' => $sourceSession
                    ];
                    
                    $totalCoeffDeficitaire += $credit;
                    $totalPointsDeficitaire += $mf * $credit;
                }

                // Moyenne actuelle de l'UE déficitaire
                $moyenneActuelleDeficitaire = $totalCoeffDeficitaire > 0 ? $totalPointsDeficitaire / $totalCoeffDeficitaire : 0;
                
                // Déficit à couvrir
                $deficitTotal = (10 - $moyenneActuelleDeficitaire) * $totalCoeffDeficitaire;

                // === ÉTAPE 2: RÉDUIRE PROPORTIONNELLEMENT L'UE COMPENSATRICE ===
                $totalCoeffCompensatrice = 0;
                $totalPointsCompensatrice = 0;
                $notesMapCompensatrice = [];

                foreach ($notesCompensatrice as $note) {
                    $credit = ($note['CMI'] + $note['TD'] + $note['TP']) / $creditHeure;
                    $mf = (float)$note['MF'];
                    $sourceSession = isset($note['source_session']) ? $note['source_session'] : $sessionId;
                    
                    $notesMapCompensatrice[$note['ECUE_idECUE']] = [
                        'note' => $mf,
                        'credit' => $credit,
                        'source_session' => $sourceSession
                    ];
                    
                    $totalCoeffCompensatrice += $credit;
                    $totalPointsCompensatrice += $mf * $credit;
                }

                // Moyenne actuelle de l'UE compensatrice
                $moyenneActuelleCompensatrice = $totalCoeffCompensatrice > 0 ? $totalPointsCompensatrice / $totalCoeffCompensatrice : 0;

                // Réduction estimée par crédit
                $reductionParCredit = $deficitTotal / $totalCoeffCompensatrice;

                // === ÉTAPE 3: CALCULER LES NOUVELLES NOTES ===
                $nouvellesNotes = [];

                // UE déficitaire: relevée à 10 (tous les ECUE proportionnellement)
                $moyenneAugmentation = 10 - $moyenneActuelleDeficitaire;
                foreach ($notesMapDeficitaire as $ecueId => $noteData) {
                    // Augmenter chaque ECUE proportionnellement pour atteindre une moyenne de 10
                    $nouvellesNotes[$ecueId] = [
                        'note' => $noteData['note'] + $moyenneAugmentation,
                        'source_session' => $noteData['source_session']
                    ];
                }

                // UE compensatrice: réduite proportionnellement
                foreach ($notesMapCompensatrice as $ecueId => $noteData) {
                    // Réduire chaque ECUE proportionnellement
                    $nouvelleNote = $noteData['note'] - $reductionParCredit;
                    
                    // Vérifier que la note ne descend pas en dessous de 10
                    if ($nouvelleNote < 10 - 0.01) {
                        throw new Exception("Impossible: ECUE " . $ecueId . " descendrait à " . round($nouvelleNote, 2));
                    }
                    
                    $nouvellesNotes[$ecueId] = [
                        'note' => $nouvelleNote,
                        'source_session' => $noteData['source_session']
                    ];
                }

                // === ÉTAPE 4: APPLIQUER LES CHANGEMENTS EN BASE DE DONNÉES ===
                foreach ($nouvellesNotes as $ecueId => $noteInfo) {
                    $targetSession = $noteInfo['source_session'];
                    $updateQuery = $db->prepare("
                        UPDATE cotes_grille 
                        SET MF = ?, date_compilation = NOW()
                        WHERE matricule = ? AND ECUE_idECUE = ? AND session_idsession = ? AND annee_acad_id = ?
                    ");
                    $updateQuery->execute([$noteInfo['note'], $matricule, $ecueId, $targetSession, $anneeId]);
                }

                $compteur++;
                $appliquees[] = [
                    'matricule' => $matricule,
                    'ueDeficitaire' => $comp['ueDeficitaireDesignation'],
                    'ueCompensatrice' => $comp['ueCompensatriceDesignation'],
                    'moyenneActuelleDeficitaire' => round($moyenneActuelleDeficitaire, 2),
                    'nouvelle_moyenne' => 10.0,
                    'reduction' => round($reductionParCredit, 2)
                ];
            }
        } catch (Exception $e) {
            $erreurs[] = "Erreur étudiant $matricule: " . $e->getMessage();
        }
    }

    // Valider la transaction
    $db->commit();

    $message = "{$compteur} compensation(s) inter-UE appliquée(s) avec succès";
    if (!empty($erreurs)) {
        $message .= ". Erreurs: " . implode("; ", $erreurs);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'compteur' => $compteur,
        'appliquees' => $appliquees
    ]);
} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
