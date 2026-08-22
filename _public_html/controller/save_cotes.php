<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/JournalServeur.php';

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Initialiser les classes
$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();
$journalServeur = new JournalServeur();

// Vérifier que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON du corps de la requête
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['ecueId']) || !isset($data['sessionId']) || !isset($data['anneeId']) || !isset($data['cotes'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$ecueId = intval($data['ecueId']);
$sessionId = intval($data['sessionId']);
$anneeId = intval($data['anneeId']);
$bureauId = intval($data['bureauId'] ?? 0); // Récupérer le bureauId
$cotes = $data['cotes'];
$motif = $data['motif'] ?? 'Modification manuelle'; // Nouveau: récupérer le motif s'il existe

// Récupérer les informations de l'ECUE (y compris la promotion)
$ecueInfo = $ecue->getEcueById($ecueId);
$promotionId = $ecueInfo['idpromotion'] ?? 0;

if (!$ecueInfo || !$promotionId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ECUE non trouvé ou promotion non identifiée']);
    exit();
}



// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);

if (!$isAdmin) {
    // Vérifier si l'utilisateur est membre d'un jury gérant cette promotion
    $hasAccess = $universite->canAgentAccessPromotion($agentId, $promotionId);

    if (!$hasAccess) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas accès à cette promotion']);
        exit();
    }
}


// Récupérer la configuration de délibération
$configDeliberation = null;
$calculerAvecNotesVides = false;

if ($bureauId > 0) {
    $configDeliberation = $universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
    $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ?
        (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
}

// Récupérer la configuration de calcul de la moyenne pour cet ECUE
$configMoyenne = $universite->getConfigurationMoyenne($ecueId, $sessionId, $anneeId);

// Valeurs par défaut si pas de configuration spécifique
// Récupérer les pondérations depuis la configuration par défaut si pas de config spécifique
require_once '../models/Universite.php';
$universite = new Universite();
$ponderationsDefaut = $universite->getPonderationsDefaut();
$ponderationCC = $configMoyenne['ponderation_cc'] ?? $ponderationsDefaut['ponderation_cc'];
$ponderationEX = $configMoyenne['ponderation_ex'] ?? $ponderationsDefaut['ponderation_ex'];

// Traiter et sauvegarder chaque cote
$resultats = [];
$erreurs = [];

foreach ($cotes as $cote) {
    $matricule = $cote['matricule'];
    $cc = isset($cote['cc']) ? floatval($cote['cc']) : null;
    $ex = isset($cote['ex']) ? floatval($cote['ex']) : null;

    // Récupérer les anciennes valeurs avant modification
    $anciennesCotes = $universite->getCoteGrille($ecueId, $sessionId, $anneeId, $matricule);

    // Calculer la moyenne finale si CC et EX sont définis
    $mf = null;
    // Vérifier si c'est une deuxième session
    $sessionInfo = $universite->getSessionById($sessionId);
    $isDeuxiemeSession = stripos($sessionInfo['designSession'], 'deuxième') !== false;

    // NOUVEAU SYSTÈME : En 2ème session, appliquer les pondérations comme en 1ère session
    if ($isDeuxiemeSession) {
        // 🎯 EN DEUXIÈME SESSION : Appliquer les pondérations (CC + EX)
        // La cote CC de 1ère session a été copiée et doit être utilisée
        if ($cc !== null && $ex !== null) {
            // Si les deux notes sont présentes, appliquer les pondérations
            $mf = ($cc * $ponderationCC) + ($ex * $ponderationEX);
        } else {
            // Pas assez de notes
            $mf = null;
        }
    } else if ($calculerAvecNotesVides) {
        // 1ère session : Si on est configuré pour calculer avec des notes vides
        if ($cc !== null && $ex !== null) {
            $mf = ($cc * $ponderationCC) + ($ex * $ponderationEX);
        } elseif ($ex !== null) {
            // S'il n'y a que la note d'examen
            $mf = $ex;
        } elseif ($cc !== null) {
            // S'il n'y a que la note de CC
            $mf = $cc;
        }
    } else {
        // 1ère session : Si on n'est pas configuré pour calculer avec des notes vides
        if ($cc !== null && $ex !== null) {
            $mf = ($cc * $ponderationCC) + ($ex * $ponderationEX);
        }
    }

    // Sauvegarder dans la table cotes_grille
    $result = $universite->saveCoteGrille($ecueId, $sessionId, $anneeId, $matricule, $cc, $ex, $mf, $userId);

    if ($result) {
        // Si la sauvegarde a réussi, enregistrer l'historique si les valeurs ont changé
        if ($anciennesCotes) {
            $cc_avant = $anciennesCotes['CC'];
            $ex_avant = $anciennesCotes['EX'];
            $mf_avant = $anciennesCotes['MF'];

            // Vérifier si les valeurs ont changé
            if (
                round((float)$cc_avant, 2) != round((float)$cc, 2) ||
                round((float)$ex_avant, 2) != round((float)$ex, 2) ||
                round((float)$mf_avant, 2) != round((float)$mf, 2)
            ) {
                // Enregistrer l'historique
                $universite->saveHistoriqueCotes(
                    $ecueId,
                    $sessionId,
                    $anneeId,
                    $matricule,
                    $cc_avant,
                    $ex_avant,
                    $mf_avant,
                    $cc,
                    $ex,
                    $mf,
                    $motif,
                    $userId
                );

                // Journaliser la modification des cotes
                $description = "Points modifiés pour l'étudiant $matricule - ECUE: {$ecueInfo['designationECUE']}, Motif: $motif";
                $journalServeur->enregistrerAction(
                    'UPDATE',
                    'COTES_GRILLE',
                    $description,
                    $userId,
                    $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                    'cotes_grille',
                    $ecueId,
                    ['matricule' => $matricule, 'CC' => $cc_avant, 'EX' => $ex_avant, 'MF' => $mf_avant],
                    ['matricule' => $matricule, 'CC' => $cc, 'EX' => $ex, 'MF' => $mf],
                    'succes'
                );
            } else {
                // Les valeurs n'ont pas changé - journaliser quand même comme tentative
                $journalServeur->enregistrerAction(
                    'UPDATE',
                    'COTES_GRILLE',
                    "Tentative de modification des points (pas de changement) pour $matricule - ECUE: {$ecueInfo['designationECUE']}",
                    $userId,
                    $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                    'cotes_grille',
                    $ecueId,
                    ['matricule' => $matricule, 'CC' => $cc_avant, 'EX' => $ex_avant, 'MF' => $mf_avant],
                    null,
                    'succes'
                );
            }
        } else {
            // Première saisie (pas de cote antérieure)
            $journalServeur->enregistrerAction(
                'CREATE',
                'COTES_GRILLE',
                "Points créés pour l'étudiant $matricule - ECUE: {$ecueInfo['designationECUE']}",
                $userId,
                $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
                'cotes_grille',
                $ecueId,
                null,
                ['matricule' => $matricule, 'CC' => $cc, 'EX' => $ex, 'MF' => $mf],
                'succes'
            );
        }

        $resultats[] = [
            'matricule' => $matricule,
            'CC' => $cc,
            'EX' => $ex,
            'MF' => $mf
        ];
    } else {
        // Journaliser l'erreur de sauvegarde
        $cc_avant = $anciennesCotes ? $anciennesCotes['CC'] : null;
        $ex_avant = $anciennesCotes ? $anciennesCotes['EX'] : null;
        $mf_avant = $anciennesCotes ? $anciennesCotes['MF'] : null;

        $journalServeur->enregistrerAction(
            'UPDATE',
            'COTES_GRILLE',
            "Erreur de sauvegarde des points pour $matricule - ECUE: {$ecueInfo['designationECUE']}",
            $userId,
            $_SESSION['nom'] ?? $_SESSION['nomAgent'] ?? 'Utilisateur',
            'cotes_grille',
            $ecueId,
            ['matricule' => $matricule, 'CC' => $cc_avant, 'EX' => $ex_avant, 'MF' => $mf_avant],
            ['matricule' => $matricule, 'CC' => $cc, 'EX' => $ex, 'MF' => $mf],
            'erreur',
            'Erreur lors de la sauvegarde des cotes'
        );

        $erreurs[] = $matricule;
    }
}

// Préparer la réponse
$response = [
    'success' => empty($erreurs),
    'resultats' => $resultats,
    'erreurs' => $erreurs,
    'promotionId' => $promotionId,
    'message' => empty($erreurs) ? 'Cotes sauvegardées avec succès' : 'Certaines cotes n\'ont pas pu être sauvegardées'
];

// Envoyer la réponse
header('Content-Type: application/json');
echo json_encode($response);
