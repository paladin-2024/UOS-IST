<?php
session_start();
require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/FlexPay.php';

header('Content-Type: application/json');

// Vérifier que l'étudiant est connecté
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour effectuer cette action.']);
    exit();
}

// Traitement des requêtes GET pour récupérer les frais impayés
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_frais_impayes') {
    try {
        $connexion = Connexion::getInstance()->getPDO();
        $matricule = $_SESSION['student_matricule'];
        $promotionId = $_SESSION['promotion_id'] ?? 0;
        $anneeAcadId = $_SESSION['annee_acad'] ?? 0;

        // Frais individuels de l'étudiant
        $sql = "
            SELECT 
                af.id as affectation_id,
                af.montant_specifique,
                af.devise as devise_affectation,
                af.statut_paiement,
                f.designation AS frais_designation,
                f.montant AS montant_frais,
                f.devise AS devise_frais,
                f.lieu_paiement,
                (SELECT COALESCE(SUM(pf.montant), 0) 
                 FROM paiements_frais pf 
                 WHERE pf.affectation_id = af.id 
                 AND pf.matricule_etudiant = :matricule) AS montant_paye
            FROM affectation_frais af
            INNER JOIN frais f ON af.frais_id = f.id
            WHERE af.matricule_etudiant = :matricule2
            AND af.est_exempte = 0
            AND f.annee_acad_id = :annee_acad_id
            
            UNION ALL
            
            SELECT 
                af.id as affectation_id,
                af.montant_specifique,
                af.devise as devise_affectation,
                af.statut_paiement,
                f.designation AS frais_designation,
                f.montant AS montant_frais,
                f.devise AS devise_frais,
                f.lieu_paiement,
                (SELECT COALESCE(SUM(pf.montant), 0) 
                 FROM paiements_frais pf 
                 WHERE pf.affectation_id = af.id 
                 AND pf.matricule_etudiant = :matricule3) AS montant_paye
            FROM affectation_frais af
            INNER JOIN frais f ON af.frais_id = f.id
            WHERE af.promotion_id = :promotion_id
            AND af.matricule_etudiant IS NULL
            AND af.est_exempte = 0
            AND f.annee_acad_id = :annee_acad_id2
            AND NOT EXISTS (
                SELECT 1 FROM affectation_frais af2 
                WHERE af2.frais_id = af.frais_id 
                AND af2.matricule_etudiant = :matricule4
            )
        ";

        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':matricule2', $matricule);
        $stmt->bindParam(':matricule3', $matricule);
        $stmt->bindParam(':matricule4', $matricule);
        $stmt->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_id', $anneeAcadId, PDO::PARAM_INT);
        $stmt->bindParam(':annee_acad_id2', $anneeAcadId, PDO::PARAM_INT);
        $stmt->execute();
        $frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrer les frais non entièrement payés et uniquement ceux payables à la Faculté
        $fraisImpayes = [];
        foreach ($frais as $f) {
            $montantTotal = $f['montant_specifique'] > 0 ? $f['montant_specifique'] : $f['montant_frais'];
            $montantPaye = floatval($f['montant_paye']);
            $devise = $f['devise_affectation'] ?: $f['devise_frais'];
            // Ne conserver que les frais dont le lieu de paiement est "Faculté"
            if ($montantPaye < $montantTotal && isset($f['lieu_paiement']) && $f['lieu_paiement'] === 'Faculté') {
                $fraisImpayes[] = [
                    'affectation_id' => $f['affectation_id'],
                    'frais_designation' => $f['frais_designation'],
                    'montant_total' => $montantTotal,
                    'montant_paye' => $montantPaye,
                    'devise' => $devise,
                    'lieu_paiement' => $f['lieu_paiement'],
                ];
            }
        }

        echo json_encode(['success' => true, 'frais' => $fraisImpayes]);
    } catch (Exception $e) {
        error_log("FlexPay - Erreur récupération frais impayés: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit();
}

try {
    // Lire le corps de la requête JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Données de requête invalides.");
    }

    $affectation_id = $input['affectation_id'] ?? null;
    $telephone = $input['telephone'] ?? null;
    $type_paiement = $input['type_paiement'] ?? 'mobile_money';
    $paiement_nature = $input['paiement_nature'] ?? 'frais'; // 'frais', 'travail' ou 'travail_groupe'
    $groupe_id = $input['groupe_id'] ?? null;

    // Validation des données
    if (!$affectation_id) {
        throw new Exception("L'identifiant du frais est obligatoire.");
    }

    if ($type_paiement === 'mobile_money' && empty($telephone)) {
        throw new Exception("Le numéro de téléphone est obligatoire pour le paiement Mobile Money.");
    }

    if (!in_array($type_paiement, ['mobile_money', 'carte'])) {
        throw new Exception("Type de paiement non supporté.");
    }

    $connexion = Connexion::getInstance()->getPDO();
    $matricule = $_SESSION['student_matricule'];
    $studentId = $_SESSION['student_id'];
    $nomEtudiant = $_SESSION['student_name'];

    // Initialiser les variables de transaction
    $transactionAffectationId = null;
    $transactionDevoirId = null;

    if ($paiement_nature === 'travail_groupe') {
        // Paiement d'un travail pratique en groupe
        $stmt = $connexion->prepare("SELECT iddevoir, titre, type_prix_groupe, prix_forfaitaire, prix_par_etudiant, devise, est_payant FROM devoirs WHERE iddevoir = :devoir_id");
        $stmt->bindParam(':devoir_id', $affectation_id, PDO::PARAM_INT);
        $stmt->execute();
        $devoir = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devoir) throw new Exception("Travail pratique non trouvé.");
        if (!$devoir['est_payant']) throw new Exception("Ce travail n'est pas payant.");

        // Calculer le montant selon le type de prix
        if ($devoir['type_prix_groupe'] === 'forfaitaire') {
            $montant = floatval($devoir['prix_forfaitaire']);
        } else {
            // Par étudiant — compter les membres du groupe
            $nbMembres = 1;
            if ($groupe_id) {
                $stmtMb = $connexion->prepare("SELECT COUNT(*) as count FROM membres_groupe_travail WHERE id_groupe = :g");
                $stmtMb->bindParam(':g', $groupe_id, PDO::PARAM_INT);
                $stmtMb->execute();
                $row = $stmtMb->fetch(PDO::FETCH_ASSOC);
                $nbMembres = $row ? max(1, intval($row['count'])) : 1;
            }
            $montant = floatval($devoir['prix_par_etudiant']) * $nbMembres;
        }

        $devise = $devoir['devise'] ?: 'USD';
        $designation = $devoir['titre'];
        $transactionDevoirId = intval($affectation_id);
        $transactionGroupeId = $groupe_id ? intval($groupe_id) : null;
        $paiement_nature = 'travail'; // Stocker comme 'travail' (ENUM compatible)

        if ($montant <= 0) throw new Exception("Le montant du groupe est invalide (0).");

    } elseif ($paiement_nature === 'travail') {
        // Paiement d'un travail pratique individuel — récupérer depuis la table devoirs
        $stmt = $connexion->prepare("
            SELECT iddevoir, titre, prix_par_etudiant, devise, est_payant
            FROM devoirs
            WHERE iddevoir = :devoir_id
        ");
        $stmt->bindParam(':devoir_id', $affectation_id, PDO::PARAM_INT);
        $stmt->execute();
        $devoir = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devoir) {
            throw new Exception("Travail pratique non trouvé.");
        }
        if (!$devoir['est_payant']) {
            throw new Exception("Ce travail n'est pas payant.");
        }

        // Vérifier si déjà payé
                    $stmtCheck = $connexion->prepare("SELECT id_paiement FROM paiements_travaux WHERE id_devoir = :devoir AND id_etudiant = :etudiant AND statut = 'reussi' LIMIT 1");
        $stmtCheck->bindParam(':devoir', $affectation_id, PDO::PARAM_INT);
        $stmtCheck->bindParam(':etudiant', $studentId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            throw new Exception("Ce travail est déjà payé.");
        }

        $montant = floatval($devoir['prix_par_etudiant']);
        $devise = $devoir['devise'] ?: 'USD';
        $designation = $devoir['titre'];
        $transactionDevoirId = intval($affectation_id);
        $transactionGroupeId = null;

        if ($montant <= 0) {
            throw new Exception("Le prix de ce travail est invalide (0).");
        }
    } else {
        // Paiement d'un frais académique — récupérer depuis affectation_frais + frais
        $stmt = $connexion->prepare("
            SELECT af.id as affectation_id, af.montant_specifique, af.frais_id, af.devise as devise_affectation,
                   f.designation, f.montant, f.devise,
                   (SELECT COALESCE(SUM(pf.montant), 0) 
                    FROM paiements_frais pf 
                    WHERE pf.affectation_id = af.id 
                    AND pf.matricule_etudiant = :matricule) AS montant_paye
            FROM affectation_frais af
            INNER JOIN frais f ON af.frais_id = f.id
            WHERE af.id = :affectation_id
        ");
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':affectation_id', $affectation_id, PDO::PARAM_INT);
        $stmt->execute();
        $affectation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$affectation) {
            throw new Exception("Frais non trouvé.");
        }

        $montantTotal = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant'];
        $montantPaye = floatval($affectation['montant_paye']);
        $montant = $montantTotal - $montantPaye;
        $devise = $affectation['devise_affectation'] ?: $affectation['devise'] ?: 'CDF';
        $designation = $affectation['designation'];
        $transactionAffectationId = intval($affectation_id);

        if ($montant <= 0) {
            throw new Exception("Ce frais est déjà entièrement payé.");
        }
    }

    // Générer une référence unique et un order_number
    $reference = 'EG-' . $matricule . '-' . time();
    $orderNumber = 'ORDER-' . uniqid();
    $description = "Paiement {$designation} - {$nomEtudiant} ({$matricule})";

    // Initier le paiement via FlexPay
    $flexpay = new FlexPay();

    if ($type_paiement === 'mobile_money') {
        $reponseApi = $flexpay->initierPaiementMobile($telephone, $montant, $devise, $reference, $description);
    } else {
        $reponseApi = $flexpay->initierPaiementCarte($montant, $devise, $reference, $description);
    }

    // Récupérer le orderNumber retourné par FlexPay si disponible
    if (isset($reponseApi['orderNumber'])) {
        $orderNumber = $reponseApi['orderNumber'];
    }

    // Enregistrer la transaction en base de données
    $transactionData = [
        'matricule_etudiant' => $matricule,
        'affectation_frais_id' => $transactionAffectationId,
        'nature' => $paiement_nature,
        'devoir_id' => $transactionDevoirId,
        'groupe_id' => $transactionGroupeId ?? null,
        'order_number' => $orderNumber,
        'reference' => $reference,
        'montant' => $montant,
        'devise' => $devise,
        'telephone' => $telephone,
        'type_paiement' => $type_paiement,
        'statut' => 'en_attente',
        'reponse_api' => $reponseApi,
    ];

    $transactionId = $flexpay->enregistrerTransaction($transactionData);

    if (!$transactionId) {
        throw new Exception("Erreur lors de l'enregistrement de la transaction.");
    }

    // Vérifier la réponse de FlexPay
    $codeReponse = $reponseApi['code'] ?? '-1';
    if ($codeReponse == '0') {
        echo json_encode([
            'success' => true,
            'message' => 'Paiement initié avec succès. Veuillez confirmer sur votre téléphone.',
            'order_number' => $orderNumber,
            'reference' => $reference,
        ]);
    } else {
        $messageErreur = $reponseApi['message'] ?? 'Erreur inconnue de FlexPay';
        echo json_encode([
            'success' => false,
            'message' => 'Échec de l\'initiation du paiement: ' . $messageErreur,
            'order_number' => $orderNumber,
        ]);
    }

} catch (Exception $e) {
    error_log("FlexPay Controller - Erreur: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
