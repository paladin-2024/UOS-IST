<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';

// Récupérer le crédit horaire depuis la configuration
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;


// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données JSON envoyées
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }
    
    // Récupérer les paramètres
    $bureauId = isset($data['bureau']) ? intval($data['bureau']) : 0;
    $promotionId = isset($data['promotion']) ? intval($data['promotion']) : 0;
    $sessionId = isset($data['session']) ? intval($data['session']) : 0;
    $anneeId = isset($data['annee']) ? intval($data['annee']) : 0;
    
    // Récupérer les données calculées
    $moyennesUE = isset($data['moyennesUE']) ? $data['moyennesUE'] : [];
    $moyennesSemestre = isset($data['moyennesSemestre']) ? $data['moyennesSemestre'] : [];
    $moyennesAnnuelles = isset($data['moyennesAnnuelles']) ? $data['moyennesAnnuelles'] : [];
    $validationsUE = isset($data['validationsUE']) ? $data['validationsUE'] : [];
    $validationsSemestre = isset($data['validationsSemestre']) ? $data['validationsSemestre'] : [];
    $validationsAnnuelles = isset($data['validationsAnnuelles']) ? $data['validationsAnnuelles'] : [];
    
    // Connexion à la base de données
    $connexion = Connexion::getInstance()->getPDO();
    
    try {
        // Commencer une transaction
        $connexion->beginTransaction();
        
        // 1. Mise à jour des moyennes UE
        foreach ($moyennesUE as $matricule => $ueData) {
            foreach ($ueData as $ueId => $moyenne) {
                if ($moyenne === null) {
                    // Si la moyenne est null, l'UE est automatiquement non validée
                    $estValidee = false;
                } else {
                    // Une UE est validée si sa moyenne est supérieure ou égale à 10
                    $estValidee = ($moyenne >= 10);
                    
                    // Si les données de validation sont disponibles, les utiliser,
                    // mais seulement si la moyenne n'est pas null
                    if (isset($validationsUE[$matricule][$ueId])) {
                        $estValidee = $validationsUE[$matricule][$ueId];
                    }
                }
                    
                    // Récupérer les crédits de l'UE
                    $stmt = $connexion->prepare("
                        SELECT SUM((e.\"CMI\" + e.\"TD\" + e.\"TP\") /$heureCredit) as credits
                        FROM ecue e
                        WHERE e.\"UE_idUE\" = :ueId
                    ");
                    $stmt->execute([':ueId' => $ueId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $creditsUE = $result ? floatval($result['credits']) : 0;
                    
                    // Les crédits obtenus sont égaux aux crédits de l'UE si elle est validée
                    $creditsObtenus = ($estValidee && $moyenne !== null) ? $creditsUE : 0;
                    
                    // Vérifier si l'enregistrement existe déjà
                    $stmt = $connexion->prepare("
                        SELECT idmoyenne_ue FROM moyenne_ue 
                        WHERE matricule = :matricule 
                        AND \"idUE\" = :ueId 
                        AND session_idsession = :sessionId 
                        AND annee_acad_idannee_acad = :anneeId
                    ");
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':ueId' => $ueId,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId
                    ]);
                    
                    if ($stmt->fetch()) {
                        // Mise à jour
                        $stmt = $connexion->prepare("
                            UPDATE moyenne_ue 
                            SET moyenne_brute = :moyenne, 
                                moyenne_deliberee = :moyenne,
                                est_validee = :estValidee,
                                credits_obtenus = :creditsObtenus,
                                date_calcul = NOW(),
                                \"idUser\" = :idUser
                            WHERE matricule = :matricule 
                            AND \"idUE\" = :ueId 
                            AND session_idsession = :sessionId 
                            AND annee_acad_idannee_acad = :anneeId
                        ");
                    } else {
                        // Insertion
                        $stmt = $connexion->prepare("
                            INSERT INTO moyenne_ue 
                            (matricule, \"idUE\", session_idsession, annee_acad_idannee_acad, 
                             moyenne_brute, moyenne_deliberee, est_validee, credits_obtenus, 
                             type_validation, date_calcul, \"idUser\") 
                            VALUES 
                            (:matricule, :ueId, :sessionId, :anneeId, 
                             :moyenne, :moyenne, :estValidee, :creditsObtenus, 
                             'Normale', NOW(), :idUser)
                        ");
                    }
                    
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':ueId' => $ueId,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId,
                        ':moyenne' => $moyenne,
                        ':estValidee' => $estValidee ? 1 : 0,
                        ':creditsObtenus' => $creditsObtenus,
                        ':idUser' => $_SESSION['id'] ?? null
                    ]);
                
            }
        }
        
        // 2. Mise à jour des moyennes de semestre
        foreach ($moyennesSemestre as $matricule => $semestreData) {
            foreach ($semestreData as $semId => $moyenne) {
                if ($moyenne !== null) {
                    // Valeurs par défaut
                    $estValide = ($moyenne >= 10);
                    $creditsObtenus = 0;
                    $creditsTotal = 0;
                    
                    // Si les données de validation sont disponibles, les utiliser
                    if (isset($validationsSemestre[$matricule][$semId])) {
                        $validation = $validationsSemestre[$matricule][$semId];
                        $estValide = isset($validation['est_valide']) ? $validation['est_valide'] : $estValide;
                        $creditsObtenus = isset($validation['credits_valides']) ? $validation['credits_valides'] : 0;
                        $creditsTotal = isset($validation['credits_total']) ? $validation['credits_total'] : 0;
                    }
                    
                    // Vérifier si l'enregistrement existe déjà
                    $stmt = $connexion->prepare("
                        SELECT idmoyenne_semestre FROM moyenne_semestre 
                        WHERE matricule = :matricule 
                        AND idsemestre = :semId 
                        AND session_idsession = :sessionId 
                        AND annee_acad_idannee_acad = :anneeId
                    ");
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':semId' => $semId,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId
                    ]);
                    
                    if ($stmt->fetch()) {
                        // Mise à jour
                        $stmt = $connexion->prepare("
                            UPDATE moyenne_semestre 
                            SET moyenne_brute = :moyenne, 
                                moyenne_deliberee = :moyenne,
                                est_valide = :estValide,
                                credits_obtenus = :creditsObtenus,
                                credits_total = :creditsTotal,
                                date_calcul = NOW(),
                                \"idUser\" = :idUser
                            WHERE matricule = :matricule 
                            AND idsemestre = :semId 
                            AND session_idsession = :sessionId 
                            AND annee_acad_idannee_acad = :anneeId
                        ");
                    } else {
                        // Insertion
                        $stmt = $connexion->prepare("
                            INSERT INTO moyenne_semestre 
                            (matricule, idsemestre, session_idsession, annee_acad_idannee_acad, 
                             moyenne_brute, moyenne_deliberee, est_valide, credits_obtenus, 
                             credits_total, date_calcul, \"idUser\") 
                            VALUES 
                            (:matricule, :semId, :sessionId, :anneeId, 
                             :moyenne, :moyenne, :estValide, :creditsObtenus, 
                             :creditsTotal, NOW(), :idUser)
                        ");
                    }
                    
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':semId' => $semId,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId,
                        ':moyenne' => $moyenne,
                        ':estValide' => $estValide ? 1 : 0,
                        ':creditsObtenus' => $creditsObtenus,
                        ':creditsTotal' => $creditsTotal,
                        ':idUser' => $_SESSION['id'] ?? null
                    ]);
                }
            }
        }

        // 3. Mise à jour des moyennes annuelles
        foreach ($moyennesAnnuelles as $matricule => $moyenne) {
            if ($moyenne !== null) {
                // Valeurs par défaut
                $estAdmis = ($moyenne >= 10);
                $creditsObtenus = 0;
                $creditsTotal = 0;
                
                // Si les données de validation sont disponibles, les utiliser
                if (isset($validationsAnnuelles[$matricule])) {
                    $validation = $validationsAnnuelles[$matricule];
                    $estAdmis = isset($validation['est_valide']) ? $validation['est_valide'] : $estAdmis;
                    $creditsObtenus = isset($validation['credits_valides']) ? $validation['credits_valides'] : 0;
                    $creditsTotal = isset($validation['credits_total']) ? $validation['credits_total'] : 0;
                }
                
                // Déterminer la mention en fonction de la moyenne
                $mention = null;
                if ($moyenne >= 16) {
                    $mention = 'Excellent';
                } elseif ($moyenne >= 14) {
                    $mention = 'Très Bien';
                } elseif ($moyenne >= 12) {
                    $mention = 'Bien';
                } elseif ($moyenne >= 11) {
                    $mention = 'Assez Bien';
                } elseif ($moyenne >= 10) {
                    $mention = 'Passable';
                }
                
                // Déterminer la décision finale
                $decision = '';
                $creditsValidesPercent = 0;
                if ($creditsTotal > 0) {
                    $creditsValidesPercent = ($creditsObtenus / $creditsTotal) * 100;
                }
                
                // Récupérer les informations de la session
                $stmt = $connexion->prepare("SELECT \"designSession\" FROM session WHERE idsession = :sessionId");
                $stmt->execute([':sessionId' => $sessionId]);
                $sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;
                
                if ($isDeuxiemeSession) {
                    // En deuxième session
                    if ($creditsObtenus == $creditsTotal) {
                        $decision = 'ADMIS SANS RACHAT';
                    } else {
                        // Vérifier si l'étudiant est en classe terminale
                        $stmt = $connexion->prepare("
                            SELECT est_terminale FROM promotion WHERE idpromotion = :promotionId
                        ");
                        $stmt->execute([':promotionId' => $promotionId]);
                        $promotionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                        $estClasseTerminale = $promotionInfo && $promotionInfo['est_terminale'] == 1;
                        
                        if (!$estClasseTerminale && $creditsValidesPercent >= 75 && $moyenne >= 10) {
                            $decision = 'ADMIS AVEC RACHAT';
                        } else {
                            $decision = 'AJOURNÉ';
                        }
                    }
                } else {
                    // En première session
                    if ($creditsObtenus == $creditsTotal) {
                        $decision = 'ADMIS SANS RACHAT';
                    } else {
                        $decision = 'ADMIS AU RATTRAPAGE';
                    }
                }
                
                // Vérifier si l'enregistrement existe déjà
                $stmt = $connexion->prepare("
                    SELECT idmoyenne_annuelle FROM moyenne_annuelle 
                    WHERE matricule = :matricule 
                    AND idpromotion = :promotionId 
                    AND session_idsession = :sessionId 
                    AND annee_acad_idannee_acad = :anneeId
                ");
                $stmt->execute([
                    ':matricule' => $matricule,
                    ':promotionId' => $promotionId,
                    ':sessionId' => $sessionId,
                    ':anneeId' => $anneeId
                ]);
                
                if ($stmt->fetch()) {
                    // Mise à jour
                    $stmt = $connexion->prepare("
                        UPDATE moyenne_annuelle 
                        SET moyenne_brute = :moyenne, 
                            moyenne_deliberee = :moyenne,
                            est_admis = :estAdmis,
                            credits_obtenus = :creditsObtenus,
                            credits_total = :creditsTotal,
                            mention = :mention,
                            date_calcul = NOW(),
                            \"idUser\" = :idUser
                        WHERE matricule = :matricule 
                        AND idpromotion = :promotionId 
                        AND session_idsession = :sessionId 
                        AND annee_acad_idannee_acad = :anneeId
                    ");
                } else {
                    // Insertion
                    $stmt = $connexion->prepare("
                        INSERT INTO moyenne_annuelle 
                        (matricule, idpromotion, session_idsession, annee_acad_idannee_acad, 
                         moyenne_brute, moyenne_deliberee, est_admis, credits_obtenus, 
                         credits_total, mention, date_calcul, \"idUser\") 
                        VALUES 
                        (:matricule, :promotionId, :sessionId, :anneeId, 
                         :moyenne, :moyenne, :estAdmis, :creditsObtenus, 
                         :creditsTotal, :mention, NOW(), :idUser)
                    ");
                }
                
                $stmt->execute([
                    ':matricule' => $matricule,
                    ':promotionId' => $promotionId,
                    ':sessionId' => $sessionId,
                    ':anneeId' => $anneeId,
                    ':moyenne' => $moyenne,
                    ':estAdmis' => $estAdmis ? 1 : 0,
                    ':creditsObtenus' => $creditsObtenus,
                    ':creditsTotal' => $creditsTotal,
                    ':mention' => $mention,
                    ':idUser' => $_SESSION['id'] ?? null
                ]);
                
                // Avant de traiter les dettes, vérifier si l'étudiant avait une décision différente avant
                // Pour cela, on va stocker temporairement la décision actuelle avant la mise à jour
                $ancienneDecision = '';
                
                // Récupérer l'ancienne décision si elle existe (basée sur les crédits et la mention)
                $stmt = $connexion->prepare("
                    SELECT 
                        CASE 
                            WHEN credits_obtenus = credits_total THEN 'ADMIS SANS RACHAT'
                            WHEN credits_obtenus >= (credits_total * 0.75) AND moyenne_deliberee >= 10 THEN 'ADMIS AVEC RACHAT'
                            ELSE 'AJOURNÉ'
                        END as ancienne_decision
                    FROM moyenne_annuelle 
                    WHERE matricule = :matricule 
                    AND idpromotion = :promotionId 
                    AND session_idsession = :sessionId 
                    AND annee_acad_idannee_acad = :anneeId
                ");
                $stmt->execute([
                    ':matricule' => $matricule,
                    ':promotionId' => $promotionId,
                    ':sessionId' => $sessionId,
                    ':anneeId' => $anneeId
                ]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $ancienneDecision = $result['ancienne_decision'];
                }
                
                // Si l'ancienne décision était "ADMIS AVEC RACHAT" et que la nouvelle ne l'est pas,
                // supprimer les dettes non validées de cette année
                if ($ancienneDecision === 'ADMIS AVEC RACHAT' && $decision !== 'ADMIS AVEC RACHAT') {
                    // Supprimer les dettes non validées de l'année en cours
                    $stmt = $connexion->prepare("
                        DELETE FROM dette_etudiant 
                        WHERE matricule = :matricule 
                        AND annee_acad_idannee_acad = :anneeId 
                        AND promotion_idpromotion = :promotionId
                        AND statut = 'En cours'
                    ");
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':anneeId' => $anneeId,
                        ':promotionId' => $promotionId
                    ]);
                    
                    // Enregistrer dans un log cette suppression
                    error_log("Dettes supprimées pour l'étudiant $matricule - Changement de décision: $ancienneDecision -> $decision");
                    
                    // Enregistrer l'historique du changement de décision
                    $stmt = $connexion->prepare("
                        INSERT INTO historique_changement_decision (
                            matricule, ancienne_decision, nouvelle_decision, 
                            promotion_id, session_id, annee_acad_id, 
                            date_changement, created_by, nb_dettes_supprimees
                        ) VALUES (
                            :matricule, :ancienneDecision, :nouvelleDecision, 
                            :promotionId, :sessionId, :anneeId, 
                            NOW(), :userId, :nbDettes
                        )
                    ");
                    
                    // Compter le nombre de dettes supprimées
                    $nbDettesSupprimeesQuery = $connexion->prepare("
                        SELECT ROW_COUNT() as nb
                    ");
                    $nbDettesSupprimeesQuery->execute();
                    $nbDettes = $nbDettesSupprimeesQuery->fetchColumn();
                    
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':ancienneDecision' => $ancienneDecision,
                        ':nouvelleDecision' => $decision,
                        ':promotionId' => $promotionId,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId,
                        ':userId' => $_SESSION['id'] ?? null,
                        ':nbDettes' => $nbDettes
                    ]);
                }
                
                // Si l'étudiant est ADMIS AVEC RACHAT, créer automatiquement les dettes
                if ($decision === 'ADMIS AVEC RACHAT') {
                    // D'abord, récupérer toutes les UE non validées pour cet étudiant
                    $stmt = $connexion->prepare("
                        SELECT DISTINCT u.\"idUE\"
                        FROM ue u
                        INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                        LEFT JOIN moyenne_ue mu ON mu.\"idUE\" = u.\"idUE\" 
                            AND mu.matricule = :matricule 
                            AND mu.session_idsession = :sessionId 
                            AND mu.annee_acad_idannee_acad = :anneeId
                        WHERE s.promotion_idpromotion = :promotionId
                        AND (mu.est_validee = 0 OR mu.est_validee IS NULL OR mu.moyenne_brute < 10 OR mu.moyenne_brute IS NULL)
                    ");
                    
                    $stmt->execute([
                        ':matricule' => $matricule,
                        ':sessionId' => $sessionId,
                        ':anneeId' => $anneeId,
                        ':promotionId' => $promotionId
                    ]);
                    
                    $uesNonValidees = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Ensuite, récupérer TOUS les ECUE de ces UE non validées
                    if (!empty($uesNonValidees)) {
                        $placeholders = implode(',', array_fill(0, count($uesNonValidees), '?'));
                        $stmt = $connexion->prepare("
                            SELECT DISTINCT 
                                e.\"idECUE\", 
                                e.\"designationECUE\",
                                e.\"UE_idUE\",
                                u.\"codeUE\", 
                                u.\"designationUE\", 
                                s.idsemestre, 
                                s.\"numeroSemestre\",
                                cg.\"MF\" as note_obtenue,
                                ((e.\"CMI\" + e.\"TD\" + e.\"TP\") / ?) as credits_ecue
                            FROM ecue e
                            INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                            INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                            LEFT JOIN cotes_grille cg ON cg.\"ECUE_idECUE\" = e.\"idECUE\" 
                                AND cg.matricule = ? 
                                AND cg.session_idsession = ? 
                                AND cg.annee_acad_id = ?
                            WHERE e.\"UE_idUE\" IN ($placeholders)
                            ORDER BY s.\"numeroSemestre\", u.\"codeUE\", e.\"designationECUE\"
                        ");
                        
                        $params = array_merge(
                            [$heureCredit, $matricule, $sessionId, $anneeId],
                            $uesNonValidees
                        );
                        
                        $stmt->execute($params);
                        $ecuesNonValidees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $ecuesNonValidees = [];
                    }
                    
                    foreach ($ecuesNonValidees as $ecue) {
                        // Vérifier si une dette existe déjà pour cet ECUE
                        $stmt = $connexion->prepare("
                            SELECT id_dette FROM dette_etudiant 
                            WHERE matricule = :matricule 
                            AND \"ECUE_idECUE\" = :ecueId 
                            AND annee_acad_idannee_acad = :anneeId
                            AND statut = 'En cours'
                        ");
                        $stmt->execute([
                            ':matricule' => $matricule,
                            ':ecueId' => $ecue['idECUE'],
                            ':anneeId' => $anneeId
                        ]);
                        
                        if (!$stmt->fetch()) {
                            // Créer la dette
                            $stmt = $connexion->prepare("
                                INSERT INTO dette_etudiant (
                                    matricule, 
                                    \"ECUE_idECUE\", 
                                    \"UE_idUE\", 
                                    semestre_idsemestre, 
                                    promotion_idpromotion, 
                                    session_idsession, 
                                    annee_acad_idannee_acad,
                                    note_obtenue, 
                                    credits_ecue, 
                                    statut, 
                                    date_creation, 
                                    \"idUser\"
                                ) VALUES (
                                    :matricule, 
                                    :ecue_id, 
                                    :ue_id, 
                                    :semestre_id, 
                                    :promotion_id, 
                                    :session_id, 
                                    :annee_acad_id,
                                    :note_obtenue, 
                                    :credits_ecue, 
                                    'En cours', 
                                    NOW(), 
                                    :user_id
                                )
                            ");
                            
                            $stmt->execute([
                                ':matricule' => $matricule,
                                ':ecue_id' => $ecue['idECUE'],
                                ':ue_id' => $ecue['UE_idUE'],
                                ':semestre_id' => $ecue['idsemestre'],
                                ':promotion_id' => $promotionId,
                                ':session_id' => $sessionId,
                                ':annee_acad_id' => $anneeId,
                                ':note_obtenue' => $ecue['note_obtenue'],
                                ':credits_ecue' => round($ecue['credits_ecue'], 2),
                                ':user_id' => $_SESSION['id'] ?? null
                            ]);
                            
                            // Enregistrer dans l'historique
                            $detteId = $connexion->lastInsertId();
                            if ($detteId) {
                                $stmt = $connexion->prepare("
                                    INSERT INTO dette_historique (
                                        id_dette, 
                                        action, 
                                        details, 
                                        date_action, 
                                        \"idUser\"
                                    ) VALUES (
                                        :id_dette, 
                                        'Creation', 
                                        :details, 
                                        NOW(), 
                                        :user_id
                                    )
                                ");
                                
                                $details = "Dette créée automatiquement pour l'ECUE " . $ecue['designationECUE'] . 
                                          " de l'UE non validée " . $ecue['codeUE'] . " - " . $ecue['designationUE'] .
                                          " (Note ECUE: " . ($ecue['note_obtenue'] ?? 'N/A') . "/20)";
                                
                                $stmt->execute([
                                    ':id_dette' => $detteId,
                                    ':details' => $details,
                                    ':user_id' => $_SESSION['id'] ?? null
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Générer automatiquement un palmarès à partir des moyennes calculées
        try {
            // Récupérer les informations sur la promotion
            $stmt = $connexion->prepare("
                SELECT p.\"designationPromotion\", s.\"designationSection\"  
                FROM promotion p
                LEFT JOIN section s ON p.orientation_idorientation = s.idsection
                WHERE p.idpromotion = :promotionId
            ");
            $stmt->execute([':promotionId' => $promotionId]);
            $promotionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer les informations sur la session
            $stmt = $connexion->prepare("SELECT \"designSession\" FROM session WHERE idsession = :sessionId");
            $stmt->execute([':sessionId' => $sessionId]);
            $sessionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Récupérer les informations sur l'année académique
            $stmt = $connexion->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = :anneeId");
            $stmt->execute([':anneeId' => $anneeId]);
            $anneeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier si un palmarès existe déjà pour cette combinaison
            $stmt = $connexion->prepare("
                SELECT id_palmares FROM palmares_archive 
                WHERE promotion_idpromotion = :promotionId 
                AND session_idsession = :sessionId 
                AND annee_acad_idannee_acad = :anneeId
            ");
            $stmt->execute([
                ':promotionId' => $promotionId,
                ':sessionId' => $sessionId,
                ':anneeId' => $anneeId
            ]);
            $palmaresExistant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($palmaresExistant) {
                // Si le palmarès existe, on le met à jour
                $idPalmares = $palmaresExistant['id_palmares'];
                
                $stmt = $connexion->prepare("
                    UPDATE palmares_archive SET
                    date_modification = NOW(),
                    \"idUser\" = :idUser
                    WHERE id_palmares = :idPalmares
                ");
                $stmt->execute([
                    ':idUser' => $_SESSION['id'] ?? null,
                    ':idPalmares' => $idPalmares
                ]);
            } else {
                // Créer un nouveau palmarès avec champs libres
                $designation = "Palmarès " . 
                              ($promotionInfo['designationPromotion'] ?? "Promotion $promotionId") . " - " . 
                              ($sessionInfo['designSession'] ?? "Session $sessionId") . " - " . 
                              ($anneeInfo['designation'] ?? "Année $anneeId");
                
                $stmt = $connexion->prepare("
                    INSERT INTO palmares_archive (
                        designation, 
                        description, 
                        annee_academique, 
                        promotion, 
                        session, 
                        date_creation,
                        \"idUser\",
                        annee_acad_idannee_acad,
                        promotion_idpromotion,
                        session_idsession
                    ) VALUES (
                        :designation, 
                        :description, 
                        :annee_academique, 
                        :promotion, 
                        :session, 
                        NOW(),
                        :idUser,
                        :annee_acad_idannee_acad,
                        :promotion_idpromotion,
                        :session_idsession
                    )
                ");
                
                $stmt->execute([
                    ':designation' => $designation,
                    ':description' => "Palmarès généré automatiquement suite au calcul des moyennes",
                    ':annee_academique' => $anneeInfo['designation'] ?? "Année académique $anneeId",
                    ':promotion' => $promotionInfo['designationPromotion'] ?? "Promotion $promotionId",
                    ':session' => $sessionInfo['designSession'] ?? "Session $sessionId",
                    ':idUser' => $_SESSION['id'] ?? null,
                    ':annee_acad_idannee_acad' => $anneeId,
                    ':promotion_idpromotion' => $promotionId,
                    ':session_idsession' => $sessionId
                ]);
                
                $idPalmares = $connexion->lastInsertId();
            }
            
            // Effacer les anciens résultats des étudiants (en cas de mise à jour)
            if (isset($idPalmares) && $idPalmares > 0) {
                $stmt = $connexion->prepare("DELETE FROM palmares_etudiant WHERE id_palmares = :idPalmares");
                $stmt->execute([':idPalmares' => $idPalmares]);
                
                // Ajouter les résultats des étudiants au palmarès
foreach ($moyennesAnnuelles as $matricule => $moyenne) {
    try {
        // Récupérer les informations de l'étudiant
        $stmt = $connexion->prepare("
            SELECT idetudiant, noms FROM etudiant WHERE matricule = :matricule
        ");
        $stmt->execute([':matricule' => $matricule]);
        $etudiantInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Récupérer les crédits et la mention depuis la moyenne annuelle
        $stmt = $connexion->prepare("
            SELECT 
                credits_obtenus, 
                credits_total, 
                mention,
                est_admis
            FROM moyenne_annuelle 
            WHERE matricule = :matricule 
            AND idpromotion = :promotionId 
            AND session_idsession = :sessionId 
            AND annee_acad_idannee_acad = :anneeId
        ");
        $stmt->execute([
            ':matricule' => $matricule,
            ':promotionId' => $promotionId,
            ':sessionId' => $sessionId,
            ':anneeId' => $anneeId
        ]);
        $moyenneInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Déterminer le rang (en fonction du pourcentage)
        $stmt = $connexion->prepare("
            SELECT COUNT(*) + 1 as rang
            FROM moyenne_annuelle
            WHERE idpromotion = :promotionId 
            AND session_idsession = :sessionId 
            AND annee_acad_idannee_acad = :anneeId
            AND moyenne_deliberee > :moyenne
        ");
        $stmt->execute([
            ':promotionId' => $promotionId,
            ':sessionId' => $sessionId,
            ':anneeId' => $anneeId,
            ':moyenne' => $moyenne
        ]);
        $rangInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier la validité de la mention pour qu'elle corresponde à l'énumération
        $mention = $moyenneInfo['mention'] ?? null;
        $validMentions = ['Passable', 'Assez Bien', 'Bien', 'Très Bien', 'Excellent', 'Distinction', 'Grande Distinction', 'La Plus Grande Distinction'];
        if (!in_array($mention, $validMentions)) {
            // Si la mention n'est pas valide, essayer de la déterminer en fonction du pourcentage
            if ($moyenne >= 16) {
                $mention = 'Excellent';
            } elseif ($moyenne >= 14) {
                $mention = 'Très Bien';
            } elseif ($moyenne >= 12) {
                $mention = 'Bien';
            } elseif ($moyenne >= 11) {
                $mention = 'Assez Bien';
            } elseif ($moyenne >= 10) {
                $mention = 'Passable';
            } else {
                $mention = null; // Pas de mention si < 10
            }
        }
        
        // Insérer l'étudiant dans le palmarès
        $stmt = $connexion->prepare("
            INSERT INTO palmares_etudiant (
                id_palmares,
                nom_complet,
                matricule,
                idetudiant,
                pourcentage,
                mention,
                rang,
                credit_obtenu,
                credit_total,
                commentaire
            ) VALUES (
                :id_palmares,
                :nom_complet,
                :matricule,
                :idetudiant,
                :pourcentage,
                :mention,
                :rang,
                :credit_obtenu,
                :credit_total,
                :commentaire
            )
        ");

        //$moyenne=round(($moyenne/20)*100,1);
        
        $stmt->execute([
            ':id_palmares' => $idPalmares,
            ':nom_complet' => $etudiantInfo['noms'] ?? 'Étudiant ' . $matricule,
            ':matricule' => $matricule,
            ':idetudiant' => $etudiantInfo['idetudiant'] ?? null,
            ':pourcentage' => round(($moyenne/20)*100,1),
            ':mention' => $mention,
            ':rang' => $rangInfo['rang'] ?? null,
            ':credit_obtenu' => $moyenneInfo['credits_obtenus'] ?? 0,
            ':credit_total' => $moyenneInfo['credits_total'] ?? 0,
            ':commentaire' => isset($moyenneInfo['est_admis']) && $moyenneInfo['est_admis'] ? 'Admis' : 'Non admis'
        ]);
    } catch (Exception $e) {
        // Enregistrer l'erreur spécifique à l'étudiant mais continuer la boucle
        error_log('Erreur lors de l\'ajout de l\'étudiant ' . $matricule . ' au palmarès: ' . $e->getMessage());
        continue; // Passer à l'étudiant suivant
    }
}

            }
        } catch (Exception $e) {
            // Enregistrer l'erreur mais ne pas interrompre la transaction principale
            error_log('Erreur lors de la génération du palmarès: ' . $e->getMessage());
        }

        // Valider la transaction
        $connexion->commit();
        
        // Réponse de succès
        echo json_encode(['success' => true, 'message' => 'Moyennes mises à jour avec succès']);
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $connexion->rollBack();
        
        // Réponse d'erreur
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour des moyennes: ' . $e->getMessage()]);
    }
} else {
    // Méthode non autorisée
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
