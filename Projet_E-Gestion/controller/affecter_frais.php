<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialisation
$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération des données du formulaire
        $frais_id = intval($_POST['frais_id']);
        $type_affectation = $_POST['type_affectation'];
        $date_affectation = date('Y-m-d');
        $date_echeance = !empty($_POST['date_echeance']) ? $_POST['date_echeance'] : null;
        $motif_specifique = trim($_POST['motif_specifique'] ?? '');
        
        // Variables pour les valeurs spécifiques
        $promotion_id = null;
        $matricule_etudiant = null;
        $montant_specifique = null;
        $devise_specifique = null;
        
        // Valider le frais
        if ($frais_id <= 0) {
            throw new Exception('Veuillez sélectionner un frais valide');
        }
        
        // Vérifier si le frais existe
        $stmt = $connexion->prepare("SELECT * FROM frais WHERE id = :frais_id");
        $stmt->bindParam(':frais_id', $frais_id);
        $stmt->execute();
        $frais = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$frais) {
            throw new Exception('Le frais sélectionné n\'existe pas');
        }
        
        // Traiter selon le type d'affectation
        if ($type_affectation === 'promotion') {
            $promotion_id = intval($_POST['promotion_id']);
            
            if ($promotion_id <= 0) {
                throw new Exception('Veuillez sélectionner une promotion valide');
            }
            
            // Vérifier si cette promotion existe
            $stmt = $connexion->prepare("SELECT * FROM promotion WHERE idpromotion = :promotion_id");
            $stmt->bindParam(':promotion_id', $promotion_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('La promotion sélectionnée n\'existe pas');
            }
            
            // Vérifier si cette promotion a déjà ce frais affecté
            $stmt = $connexion->prepare("
                SELECT id FROM affectation_frais 
                WHERE frais_id = :frais_id AND promotion_id = :promotion_id
            ");
            $stmt->bindParam(':frais_id', $frais_id);
            $stmt->bindParam(':promotion_id', $promotion_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception('Ce frais est déjà affecté à cette promotion');
            }
        } 
        elseif ($type_affectation === 'promotions_multiples') {
            // Récupérer les promotions sélectionnées
            if (!isset($_POST['promotions_multiples']) || !is_array($_POST['promotions_multiples']) || empty($_POST['promotions_multiples'])) {
                throw new Exception('Veuillez sélectionner au moins une promotion');
            }
            
            $promotions_ids = array_map('intval', $_POST['promotions_multiples']);
            
            // Vérifier si un montant spécifique a été défini
            if (isset($_POST['montant_specifique']) && !empty($_POST['montant_specifique'])) {
                $montant_specifique = floatval($_POST['montant_specifique']);
                $devise_specifique = $_POST['devise_specifique'];
                
                if ($montant_specifique <= 0) {
                    throw new Exception('Le montant spécifique doit être supérieur à zéro');
                }
            }
            
            // Calculer le montant total à payer (montant spécifique ou montant standard)
            $montant_total = $montant_specifique ?? $frais['montant'];
            $devise = $devise_specifique ?? $frais['devise'];
            
            // Démarrer une transaction pour affecter à toutes les promotions
            $connexion->beginTransaction();
            
            $affectations_reussies = 0;
            $affectations_ignorees = 0;
            
            foreach ($promotions_ids as $promotion_id) {
                // Vérifier si cette promotion existe
                $stmt = $connexion->prepare("SELECT * FROM promotion WHERE idpromotion = :promotion_id");
                $stmt->bindParam(':promotion_id', $promotion_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    $affectations_ignorees++;
                    continue;
                }
                
                // Vérifier si cette promotion a déjà ce frais affecté
                $stmt = $connexion->prepare("
                    SELECT id FROM affectation_frais 
                    WHERE frais_id = :frais_id AND promotion_id = :promotion_id
                ");
                $stmt->bindParam(':frais_id', $frais_id);
                $stmt->bindParam(':promotion_id', $promotion_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $affectations_ignorees++;
                    continue; // Passer à la promotion suivante
                }
                
                // Insérer l'affectation pour cette promotion
                $stmt = $connexion->prepare("
                    INSERT INTO affectation_frais (
                        frais_id, promotion_id, matricule_etudiant, 
                        date_affectation, date_echeance, 
                        montant_specifique, devise, 
                        motif_specifique, est_exempte, statut_paiement, 
                        montant_paye, montant_restant, idUser
                    ) VALUES (
                        :frais_id, :promotion_id, NULL, 
                        :date_affectation, :date_echeance, 
                        :montant_specifique, :devise, 
                        :motif_specifique, 0, 'Non payé', 
                        0, :montant_restant, :idUser
                    )
                ");
                
                $stmt->bindParam(':frais_id', $frais_id);
                $stmt->bindParam(':promotion_id', $promotion_id);
                $stmt->bindParam(':date_affectation', $date_affectation);
                $stmt->bindParam(':date_echeance', $date_echeance);
                $stmt->bindParam(':montant_specifique', $montant_specifique);
                $stmt->bindParam(':devise', $devise); 
                $stmt->bindParam(':motif_specifique', $motif_specifique);
                $stmt->bindParam(':montant_restant', $montant_total);
                $stmt->bindParam(':idUser', $idUser);
                
                if ($stmt->execute()) {
                    $affectations_reussies++;
                    
                    // Récupérer l'ID de l'affectation créée
                    $affectation_id = $connexion->lastInsertId();
                    
                    // Si le frais est échelonnable, créer les échelonnements
                    if ($frais['est_echelonnable'] == 1) {
                        // Récupérer les configurations de tranches pour ce frais
                        $stmt = $connexion->prepare("
                            SELECT * FROM tranches_paiement_config 
                            WHERE frais_id = :frais_id 
                            ORDER BY numero_tranche
                        ");
                        $stmt->bindParam(':frais_id', $frais_id);
                        $stmt->execute();
                        $tranches_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Si aucune tranche n'est configurée, créer une tranche par défaut à 100%
                        if (empty($tranches_config)) {
                            $stmt = $connexion->prepare("
                                INSERT INTO echelonnement_paiement (
                                    affectation_id, numero_tranche, designation,
                                    pourcentage, montant, devise,
                                    date_echeance, est_requis_inscription,
                                    est_requis_examens, est_requis_deliberation,
                                    statut_paiement, montant_paye
                                ) VALUES (
                                    :affectation_id, 1, 'Paiement complet',
                                    100, :montant, :devise,
                                    :date_echeance, :est_requis_inscription,
                                    :est_requis_examens, :est_requis_deliberation,
                                    'Non payé', 0
                                )
                            ");
                            
                            $stmt->bindParam(':affectation_id', $affectation_id);
                            $stmt->bindParam(':montant', $montant_total);
                            $stmt->bindParam(':devise', $devise);
                            $stmt->bindParam(':date_echeance', $date_echeance);
                            $stmt->bindParam(':est_requis_inscription', $frais['est_requis_inscription']);
                            $stmt->bindParam(':est_requis_examens', $frais['est_requis_examens']);
                            $stmt->bindParam(':est_requis_deliberation', $frais['est_requis_deliberation']);
                            
                            if (!$stmt->execute()) {
                                throw new Exception('Erreur lors de la création de l\'échelonnement par défaut');
                            }
                        } else {
                            // Créer un échelonnement pour chaque tranche configurée
                            foreach ($tranches_config as $tranche) {
                                // Utiliser le montant fixe configuré si disponible, sinon recalculer
                                $montant_tranche = !empty($tranche['montant_fixe']) 
                                    ? floatval($tranche['montant_fixe']) 
                                    : (($montant_total * $tranche['pourcentage']) / 100);
                                $date_echeance_tranche = $tranche['date_echeance_fixe'] ?? $date_echeance;
                                
                                $stmt = $connexion->prepare("
                                    INSERT INTO echelonnement_paiement (
                                        affectation_id, numero_tranche, designation,
                                        pourcentage, montant,
                                        date_echeance, est_requis_inscription,
                                        est_requis_examens, est_requis_deliberation,
                                        statut_paiement, montant_paye
                                    ) VALUES (
                                        :affectation_id, :numero_tranche, :designation,
                                        :pourcentage, :montant,
                                        :date_echeance, :est_requis_inscription,
                                        :est_requis_examens, :est_requis_deliberation,
                                        'Non payé', 0
                                    )
                                ");
                                
                                $stmt->bindParam(':affectation_id', $affectation_id);
                                $stmt->bindParam(':numero_tranche', $tranche['numero_tranche']);
                                $stmt->bindParam(':designation', $tranche['designation']);
                                $stmt->bindParam(':pourcentage', $tranche['pourcentage']);
                                $stmt->bindParam(':montant', $montant_tranche);
                                $stmt->bindParam(':date_echeance', $date_echeance_tranche);
                                $stmt->bindParam(':est_requis_inscription', $tranche['est_requis_inscription']);
                                $stmt->bindParam(':est_requis_examens', $tranche['est_requis_examens']);
                                $stmt->bindParam(':est_requis_deliberation', $tranche['est_requis_deliberation']);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception('Erreur lors de la création de l\'échelonnement pour la tranche ' . $tranche['numero_tranche']);
                                }
                            }
                        }
                    }
                }
            }
            
            // Valider la transaction
            $connexion->commit();
            
            // Message de succès adapté
            $message_succes = "Le frais a été affecté avec succès à {$affectations_reussies} promotion(s).";
            if ($affectations_ignorees > 0) {
                $message_succes .= " {$affectations_ignorees} promotion(s) ont été ignorées (déjà affectée ou inexistante).";
            }
            $_SESSION['message'] = $message_succes;
            $_SESSION['messageType'] = 'success';
            
            // Redirection
            header('Location: ../?view=finance/affectation_frais');
            exit();
        } 
        elseif ($type_affectation === 'etudiant') {
            $matricule_etudiant = trim($_POST['matricule_etudiant']);
            
            if (empty($matricule_etudiant)) {
                throw new Exception('Le matricule de l\'étudiant est requis');
            }
            
            // Vérifier si l'étudiant existe
            $stmt = $connexion->prepare("
                SELECT * FROM etudiant 
                WHERE matricule = :matricule
            ");
            $stmt->bindParam(':matricule', $matricule_etudiant);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Aucun étudiant trouvé avec ce matricule');
            }
            
            // Vérifier si cet étudiant a déjà ce frais affecté
            $stmt = $connexion->prepare("
                SELECT id FROM affectation_frais 
                WHERE frais_id = :frais_id AND matricule_etudiant = :matricule
            ");
            $stmt->bindParam(':frais_id', $frais_id);
            $stmt->bindParam(':matricule', $matricule_etudiant);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception('Ce frais est déjà affecté à cet étudiant');
            }
        } elseif ($type_affectation === 'annee_academique') {
            $annee_academique_id = intval($_POST['annee_academique_id']);
            
            if ($annee_academique_id <= 0) {
                throw new Exception('Veuillez sélectionner une année académique valide');
            }
            
            // Vérifier si l'année académique existe
            $stmt = $connexion->prepare("SELECT * FROM annee_acad WHERE idannee_acad = :annee_acad_id");
            $stmt->bindParam(':annee_acad_id', $annee_academique_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('L\'année académique sélectionnée n\'existe pas');
            }
            
            // Récupérer toutes les promotions de cette année académique
            $stmt = $connexion->prepare("
                SELECT idpromotion FROM promotion 
                WHERE annee_acad_idannee_acad = :annee_acad_id
            ");
            $stmt->bindParam(':annee_acad_id', $annee_academique_id);
            $stmt->execute();
            $promotions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($promotions)) {
                throw new Exception('Aucune promotion trouvée pour cette année académique');
            }
            
            // Démarrer une transaction pour affecter à toutes les promotions
            $connexion->beginTransaction();
            
            $affectations_reussies = 0;
            $affectations_ignorees = 0;
            
            foreach ($promotions as $promotion_id) {
                // Vérifier si cette promotion a déjà ce frais affecté
                $stmt = $connexion->prepare("
                    SELECT id FROM affectation_frais 
                    WHERE frais_id = :frais_id AND promotion_id = :promotion_id
                ");
                $stmt->bindParam(':frais_id', $frais_id);
                $stmt->bindParam(':promotion_id', $promotion_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $affectations_ignorees++;
                    continue; // Passer à la promotion suivante
                }
                
                // Calculer le montant total à payer (montant spécifique ou montant standard)
                $montant_total = $montant_specifique ?? $frais['montant'];
                $devise = $devise_specifique ?? $frais['devise'];
                
                // Insérer l'affectation pour cette promotion
                $stmt = $connexion->prepare("
                    INSERT INTO affectation_frais (
                        frais_id, promotion_id, matricule_etudiant, 
                        date_affectation, date_echeance, 
                        montant_specifique, devise, 
                        motif_specifique, est_exempte, statut_paiement, 
                        montant_paye, montant_restant, idUser
                    ) VALUES (
                        :frais_id, :promotion_id, NULL, 
                        :date_affectation, :date_echeance, 
                        :montant_specifique, :devise, 
                        :motif_specifique, 0, 'Non payé', 
                        0, :montant_restant, :idUser
                    )
                ");
                
                $stmt->bindParam(':frais_id', $frais_id);
                $stmt->bindParam(':promotion_id', $promotion_id);
                $stmt->bindParam(':date_affectation', $date_affectation);
                $stmt->bindParam(':date_echeance', $date_echeance);
                $stmt->bindParam(':montant_specifique', $montant_specifique);
                $stmt->bindParam(':devise', $devise); 
                $stmt->bindParam(':motif_specifique', $motif_specifique);
                $stmt->bindParam(':montant_restant', $montant_total);
                $stmt->bindParam(':idUser', $idUser);
                
                if ($stmt->execute()) {
                    $affectations_reussies++;
                    
                    // Récupérer l'ID de l'affectation créée
                    $affectation_id = $connexion->lastInsertId();
                    
                    // Si le frais est échelonnable, créer les échelonnements comme pour une affectation unique
                    if ($frais['est_echelonnable'] == 1) {
                        // Récupérer les configurations de tranches pour ce frais
                        $stmt = $connexion->prepare("
                            SELECT * FROM tranches_paiement_config 
                            WHERE frais_id = :frais_id 
                            ORDER BY numero_tranche
                        ");
                        $stmt->bindParam(':frais_id', $frais_id);
                        $stmt->execute();
                        $tranches_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Si aucune tranche n'est configurée, créer une tranche par défaut à 100%
                        if (empty($tranches_config)) {
                            // Insérer un échelonnement avec le montant total
                            $stmt = $connexion->prepare("
                                INSERT INTO echelonnement_paiement (
                                    affectation_id, numero_tranche, designation,
                                    pourcentage, montant, devise,
                                    date_echeance, est_requis_inscription,
                                    est_requis_examens, est_requis_deliberation,
                                    statut_paiement, montant_paye
                                ) VALUES (
                                    :affectation_id, 1, 'Paiement complet',
                                    100, :montant, :devise,
                                    :date_echeance, :est_requis_inscription,
                                    :est_requis_examens, :est_requis_deliberation,
                                    'Non payé', 0
                                )
                            ");
                            
                            $stmt->bindParam(':affectation_id', $affectation_id);
                            $stmt->bindParam(':montant', $montant_total);
                            $stmt->bindParam(':devise', $devise);
                            $stmt->bindParam(':date_echeance', $date_echeance);
                            $stmt->bindParam(':est_requis_inscription', $frais['est_requis_inscription']);
                            $stmt->bindParam(':est_requis_examens', $frais['est_requis_examens']);
                            $stmt->bindParam(':est_requis_deliberation', $frais['est_requis_deliberation']);
                            
                            if (!$stmt->execute()) {
                                throw new Exception('Erreur lors de la création de l\'échelonnement par défaut');
                            }
                        } else {
                            // Créer un échelonnement pour chaque tranche configurée
                            foreach ($tranches_config as $tranche) {
                                // Utiliser le montant fixe configuré si disponible, sinon recalculer
                                $montant_tranche = !empty($tranche['montant_fixe']) 
                                    ? floatval($tranche['montant_fixe']) 
                                    : (($montant_total * $tranche['pourcentage']) / 100);
                                
                                // Utiliser la date d'échéance spécifique de la tranche si disponible, sinon celle de l'affectation
                                $date_echeance_tranche = $tranche['date_echeance_fixe'] ?? $date_echeance;
                                
                                $stmt = $connexion->prepare("
                                    INSERT INTO echelonnement_paiement (
                                        affectation_id, numero_tranche, designation,
                                        pourcentage, montant,
                                        date_echeance, est_requis_inscription,
                                        est_requis_examens, est_requis_deliberation,
                                        statut_paiement, montant_paye
                                    ) VALUES (
                                        :affectation_id, :numero_tranche, :designation,
                                        :pourcentage, :montant,
                                        :date_echeance, :est_requis_inscription,
                                        :est_requis_examens, :est_requis_deliberation,
                                        'Non payé', 0
                                    )
                                ");
                                
                                $stmt->bindParam(':affectation_id', $affectation_id);
                                $stmt->bindParam(':numero_tranche', $tranche['numero_tranche']);
                                $stmt->bindParam(':designation', $tranche['designation']);
                                $stmt->bindParam(':pourcentage', $tranche['pourcentage']);
                                $stmt->bindParam(':montant', $montant_tranche);
                                $stmt->bindParam(':date_echeance', $date_echeance_tranche);
                                $stmt->bindParam(':est_requis_inscription', $tranche['est_requis_inscription']);
                                $stmt->bindParam(':est_requis_examens', $tranche['est_requis_examens']);
                                $stmt->bindParam(':est_requis_deliberation', $tranche['est_requis_deliberation']);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception('Erreur lors de la création de l\'échelonnement pour la tranche ' . $tranche['numero_tranche']);
                                }
                            }
                        }
                    }
                }
            }
            
            // Valider la transaction
            $connexion->commit();
            
            // Message de succès adapté
            $_SESSION['message'] = "Le frais a été affecté avec succès à {$affectations_reussies} promotion(s). {$affectations_ignorees} promotion(s) ont été ignorées car le frais était déjà affecté.";
            $_SESSION['messageType'] = 'success';
            
            // Redirection
            header('Location: ../?view=finance/affectation_frais');
            exit();
        }
        else {
            throw new Exception('Type d\'affectation non valide');
        }
        
        // Vérifier si un montant spécifique a été défini
        if (isset($_POST['montant_specifique']) && !empty($_POST['montant_specifique'])) {
            $montant_specifique = floatval($_POST['montant_specifique']);
            $devise_specifique = $_POST['devise_specifique'];
            
            if ($montant_specifique <= 0) {
                throw new Exception('Le montant spécifique doit être supérieur à zéro');
            }
        }
        
        // Calculer le montant total à payer (montant spécifique ou montant standard)
        $montant_total = $montant_specifique ?? $frais['montant'];
        $devise = $devise_specifique ?? $frais['devise'];
        
        // Démarrer une transaction
        $connexion->beginTransaction();
        
        // Insérer l'affectation
        $stmt = $connexion->prepare("
            INSERT INTO affectation_frais (
                frais_id, promotion_id, matricule_etudiant, 
                date_affectation, date_echeance, 
                montant_specifique, devise, 
                motif_specifique, est_exempte, statut_paiement, 
                montant_paye, montant_restant, idUser
            ) VALUES (
                :frais_id, :promotion_id, :matricule_etudiant, 
                :date_affectation, :date_echeance, 
                :montant_specifique, :devise, 
                :motif_specifique, 0, 'Non payé', 
                0, :montant_restant, :idUser
            )
        ");
        
        $stmt->bindParam(':frais_id', $frais_id);
        $stmt->bindParam(':promotion_id', $promotion_id);
        $stmt->bindParam(':matricule_etudiant', $matricule_etudiant);
        $stmt->bindParam(':date_affectation', $date_affectation);
        $stmt->bindParam(':date_echeance', $date_echeance);
        $stmt->bindParam(':montant_specifique', $montant_specifique);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':motif_specifique', $motif_specifique);
        $stmt->bindParam(':montant_restant', $montant_total);
        $stmt->bindParam(':idUser', $idUser);
        
        if (!$stmt->execute()) {
            throw new Exception('Erreur lors de l\'enregistrement de l\'affectation');
        }
        
        // Récupérer l'ID de l'affectation créée
        $affectation_id = $connexion->lastInsertId();
        
        // Si le frais est échelonnable, créer les échelonnements de paiement
        if ($frais['est_echelonnable'] == 1) {
            // Récupérer les configurations de tranches pour ce frais
            $stmt = $connexion->prepare("
                SELECT * FROM tranches_paiement_config 
                WHERE frais_id = :frais_id 
                ORDER BY numero_tranche
            ");
            $stmt->bindParam(':frais_id', $frais_id);
            $stmt->execute();
            $tranches_config = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si aucune tranche n'est configurée, créer une tranche par défaut à 100%
            if (empty($tranches_config)) {
                // Insérer un échelonnement avec le montant total
                $stmt = $connexion->prepare("
                    INSERT INTO echelonnement_paiement (
                        affectation_id, numero_tranche, designation,
                        pourcentage, montant, devise,
                        date_echeance, est_requis_inscription,
                        est_requis_examens, est_requis_deliberation,
                        statut_paiement, montant_paye
                    ) VALUES (
                        :affectation_id, 1, 'Paiement complet',
                        100, :montant, :devise,
                        :date_echeance, :est_requis_inscription,
                        :est_requis_examens, :est_requis_deliberation,
                        'Non payé', 0
                    )
                ");
                
                $stmt->bindParam(':affectation_id', $affectation_id);
                $stmt->bindParam(':montant', $montant_total);
                $stmt->bindParam(':devise', $devise);
                $stmt->bindParam(':date_echeance', $date_echeance);
                $stmt->bindParam(':est_requis_inscription', $frais['est_requis_inscription']);
                $stmt->bindParam(':est_requis_examens', $frais['est_requis_examens']);
                $stmt->bindParam(':est_requis_deliberation', $frais['est_requis_deliberation']);
                
                if (!$stmt->execute()) {
                    throw new Exception('Erreur lors de la création de l\'échelonnement par défaut');
                }
            } else {
                // Créer un échelonnement pour chaque tranche configurée
                foreach ($tranches_config as $tranche) {
                    // Utiliser le montant fixe configuré si disponible, sinon recalculer
                    $montant_tranche = !empty($tranche['montant_fixe']) 
                        ? floatval($tranche['montant_fixe']) 
                        : (($montant_total * $tranche['pourcentage']) / 100);
                    
                    // Utiliser la date d'échéance spécifique de la tranche si disponible, sinon celle de l'affectation
                    $date_echeance_tranche = $tranche['date_echeance_fixe'] ?? $date_echeance;
                    
                    $stmt = $connexion->prepare("
                        INSERT INTO echelonnement_paiement (
                            affectation_id, numero_tranche, designation,
                            pourcentage, montant,
                            date_echeance, est_requis_inscription,
                            est_requis_examens, est_requis_deliberation,
                            statut_paiement, montant_paye
                        ) VALUES (
                            :affectation_id, :numero_tranche, :designation,
                            :pourcentage, :montant,
                            :date_echeance, :est_requis_inscription,
                            :est_requis_examens, :est_requis_deliberation,
                            'Non payé', 0
                        )
                    ");
                    
                    $stmt->bindParam(':affectation_id', $affectation_id);
                    $stmt->bindParam(':numero_tranche', $tranche['numero_tranche']);
                    $stmt->bindParam(':designation', $tranche['designation']);
                    $stmt->bindParam(':pourcentage', $tranche['pourcentage']);
                    $stmt->bindParam(':montant', $montant_tranche);
                    $stmt->bindParam(':date_echeance', $date_echeance_tranche);
                    $stmt->bindParam(':est_requis_inscription', $tranche['est_requis_inscription']);
                    $stmt->bindParam(':est_requis_examens', $tranche['est_requis_examens']);
                    $stmt->bindParam(':est_requis_deliberation', $tranche['est_requis_deliberation']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Erreur lors de la création de l\'échelonnement pour la tranche ' . $tranche['numero_tranche']);
                    }
                }
            }
        }
        
        // Valider la transaction
        $connexion->commit();
        
        // Définir un message de succès selon le type d'affectation
        if ($type_affectation === 'promotion') {
            $_SESSION['message'] = 'Le frais a été affecté avec succès à la promotion';
        } else {
            $_SESSION['message'] = 'Le frais a été affecté avec succès à l\'étudiant (matricule: ' . $matricule_etudiant . ')';
        }
        $_SESSION['messageType'] = 'success';
        
        // Redirection après succès
        header('Location: ../?view=finance/affectation_frais');
        exit();
        
    } catch (Exception $e) {
        // En cas d'erreur, annuler la transaction
        if ($connexion->inTransaction()) {
            $connexion->rollBack();
        }
        
        // Enregistrer l'erreur dans les logs
        error_log('Erreur dans affecter_frais.php: ' . $e->getMessage());
        
        // Stocker le message d'erreur pour l'afficher
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
        
        // Redirection avec le message d'erreur
        header('Location: ../?view=finance/affectation_frais');
        exit();
    }
} else {
    // Accès direct au fichier sans soumission de formulaire
    header('Location: ../?view=finance/affectation_frais');
    exit();
}

