<?php

/**
 * Contrôleur pour appliquer les compensations inter-ECUE
 * 
 * Reçoit les compensations sélectionnées et les applique directement
 * à la base de données sans passer par la grille manuelle.
 */

header('Content-Type: application/json');

session_start();

require_once '../config/Connexion.php';
require_once '../models/Etudiant.php';
require_once '../models/Deliberation.php';

// Vérifier l'authentification
if (!isset($_SESSION['idRole'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les droits (Admin ou Jury Member)
$isAdmin = $_SESSION['idRole'] == 1;
if (!$isAdmin) {
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

    // Démarrer une transaction
    $db->beginTransaction();

    $compteur = 0;
    $erreurs = [];

    // Grouper les compensations par UE et matricule
    $compensationsParUE = [];
    foreach ($compensations as $comp) {
        $cle = $comp['matricule'] . '_' . $comp['ueId'];
        if (!isset($compensationsParUE[$cle])) {
            $compensationsParUE[$cle] = [];
        }
        $compensationsParUE[$cle][] = $comp;
    }

    foreach ($compensationsParUE as $ueCle => $compsUE) {
        try {
            $matricule = $compsUE[0]['matricule'];
            $ueId = $compsUE[0]['ueId'];
            $sessionId = $data['session'];
            $anneeId = $data['annee'];

            // Récupérer TOUTES les notes de l'UE
            $getNotesQuery = $db->prepare("
                SELECT cg.\"ECUE_idECUE\", cg.MF, e.CMI, e.TD, e.TP
                FROM cotes_grille cg
                JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                WHERE cg.matricule = ? AND e.\"UE_idUE\" = ? AND cg.session_idsession = ? AND cg.annee_acad_id = ?
            ");
            $getNotesQuery->execute([$matricule, $ueId, $sessionId, $anneeId]);
            $notesUE = $getNotesQuery->fetchAll(PDO::FETCH_ASSOC);

            if (empty($notesUE)) {
                throw new Exception("Aucune note trouvée pour cette UE");
            }

            // Créer un mapping des notes par ECUE
            $notesMap = [];
            foreach ($notesUE as $note) {
                $credit = ($note['CMI'] + $note['TD'] + $note['TP']) / $creditHeure;
                $notesMap[$note['ECUE_idECUE']] = [
                    'note' => (float)$note['MF'],
                    'credit' => $credit
                ];
            }

            // Calculer le déficit total
            $deficitTotal = 0;
            $ecuesACompenser = [];
            foreach ($compsUE as $comp) {
                $ecueId = $comp['ecueId'];
                if (isset($notesMap[$ecueId])) {
                    $note = $notesMap[$ecueId]['note'];
                    $credit = $notesMap[$ecueId]['credit'];
                    $deficit = (10 - $note) * $credit;
                    $deficitTotal += $deficit;
                    $ecuesACompenser[] = [
                        'ecueId' => $ecueId,
                        'noteActuelle' => $note,
                        'credit' => $credit,
                        'deficit' => $deficit
                    ];
                }
            }

            // Calculer les excédents disponibles des ECUE réussis
            $excedentsTotal = 0;
            $ecuesReussis = [];
            foreach ($notesMap as $ecueId => $noteData) {
                $estACompenser = false;
                foreach ($ecuesACompenser as $comp) {
                    if ($comp['ecueId'] == $ecueId) {
                        $estACompenser = true;
                        break;
                    }
                }

                if (!$estACompenser && $noteData['note'] >= 10) {
                    $excedent = ($noteData['note'] - 10) * $noteData['credit'];
                    $excedentsTotal += $excedent;
                    $ecuesReussis[] = [
                        'ecueId' => $ecueId,
                        'noteActuelle' => $noteData['note'],
                        'credit' => $noteData['credit'],
                        'excedent' => $excedent
                    ];
                }
            }

            // Vérifier si compensation possible
            if ($excedentsTotal < $deficitTotal) {
                $erreurs[] = "UE $ueId - Étudiant $matricule: Points excédentaires insuffisants ($excedentsTotal < $deficitTotal)";
                continue;
            }

            // Répartir le déficit proportionnellement sur les ECUE réussis
            $nouvellesNotes = [];
            foreach ($ecuesReussis as &$reussi) {
                $contribution = ($reussi['excedent'] / $excedentsTotal) * $deficitTotal;
                $reduction = $contribution / $reussi['credit'];
                $reussi['nouvelleNote'] = $reussi['noteActuelle'] - $reduction;

                // Vérifier que la note reste >= 10
                if ($reussi['nouvelleNote'] < 10 - 0.01) { // Tolérance pour arrondis
                    throw new Exception("Impossible: " . $reussi['ecueId'] . " descendrait à " . round($reussi['nouvelleNote'], 2));
                }

                $nouvellesNotes[$reussi['ecueId']] = $reussi['nouvelleNote'];
            }

            // Ajouter les ECUE à compenser (relevés à 10)
            foreach ($ecuesACompenser as $comp) {
                $nouvellesNotes[$comp['ecueId']] = 10.0;
            }

            // Appliquer les changements en base de données
            foreach ($nouvellesNotes as $ecueId => $nouvelleNote) {
                $updateQuery = $db->prepare("
                    UPDATE cotes_grille 
                    SET MF = ?, date_compilation = NOW()
                    WHERE matricule = ? AND \"ECUE_idECUE\" = ? AND session_idsession = ? AND annee_acad_id = ?
                ");
                $updateQuery->execute([$nouvelleNote, $matricule, $ecueId, $sessionId, $anneeId]);
            }

            $compteur += count($compsUE);
        } catch (Exception $e) {
            $erreurs[] = "Erreur UE {$ueId} - Étudiant $matricule: " . $e->getMessage();
        }
    }

    // Valider la transaction
    $db->commit();

    $message = "{$compteur} compensation(s) appliquée(s) avec succès";
    if (!empty($erreurs)) {
        $message .= ". Erreurs: " . implode("; ", $erreurs);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'compteur' => $compteur
    ]);
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
