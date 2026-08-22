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
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    try {
        switch ($action) {
            case 'ajouter':
                // Récupération des données du formulaire
                $categorie_id = intval($_POST['categorie_id']);
                $designation = trim($_POST['designation']);
                $montant = floatval($_POST['montant']);
                $devise = $_POST['devise'];
                $annee_acad_id = intval($_POST['annee_acad_id']);
                $cycle = $_POST['cycle'];
                $niveau = trim($_POST['niveau'] ?? '');
                $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
                $est_echelonnable = isset($_POST['est_echelonnable']) ? 1 : 0;
                $nb_tranches_max = $est_echelonnable ? intval($_POST['nb_tranches_max']) : 1;
                $date_echeance_globale = !empty($_POST['date_echeance_globale']) ? $_POST['date_echeance_globale'] : null;
                $est_requis_inscription = isset($_POST['est_requis_inscription']) ? 1 : 0;
                $est_requis_examens = isset($_POST['est_requis_examens']) ? 1 : 0;
                $est_requis_deliberation = isset($_POST['est_requis_deliberation']) ? 1 : 0;
                $description = trim($_POST['description'] ?? '');
                $lieu_paiement = $_POST['lieu_paiement'] ?? 'Caisse centrale';
                
                // Validation des données
                if (empty($designation)) {
                    throw new Exception('La désignation est obligatoire');
                }
                
                if ($categorie_id <= 0) {
                    throw new Exception('Veuillez sélectionner une catégorie valide');
                }
                
                if ($montant <= 0) {
                    throw new Exception('Le montant doit être supérieur à zéro');
                }
                
                if ($annee_acad_id <= 0) {
                    throw new Exception('Veuillez sélectionner une année académique valide');
                }
                
                // Vérifier si un frais similaire existe déjà
                $stmt = $connexion->prepare("
                    SELECT id FROM frais 
                    WHERE designation = :designation 
                    AND annee_acad_id = :annee_acad_id 
                    AND categorie_id = :categorie_id
                    AND cycle = :cycle
                    AND (niveau = :niveau OR (niveau IS NULL AND :niveau_is_empty))
                ");
                $niveau_is_empty = empty($niveau) ? 1 : 0;
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':annee_acad_id', $annee_acad_id);
                $stmt->bindParam(':categorie_id', $categorie_id);
                $stmt->bindParam(':cycle', $cycle);
                $stmt->bindParam(':niveau', $niveau);
                $stmt->bindParam(':niveau_is_empty', $niveau_is_empty);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    throw new Exception('Un frais similaire existe déjà pour cette année académique');
                }
                
                // Insertion du nouveau frais
                $stmt = $connexion->prepare("
                    INSERT INTO frais (
                        categorie_id, designation, montant, devise, annee_acad_id, 
                        cycle, niveau, est_obligatoire, est_echelonnable, nb_tranches_max, 
                        date_echeance_globale, est_requis_inscription, est_requis_examens, 
                        est_requis_deliberation, description, lieu_paiement, idUser
                    ) VALUES (
                        :categorie_id, :designation, :montant, :devise, :annee_acad_id, 
                        :cycle, :niveau, :est_obligatoire, :est_echelonnable, :nb_tranches_max, 
                        :date_echeance_globale, :est_requis_inscription, :est_requis_examens, 
                        :est_requis_deliberation, :description, :lieu_paiement, :idUser
                    )
                ");
                
                $stmt->bindParam(':categorie_id', $categorie_id);
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':montant', $montant);
                $stmt->bindParam(':devise', $devise);
                $stmt->bindParam(':annee_acad_id', $annee_acad_id);
                $stmt->bindParam(':cycle', $cycle);
                $stmt->bindParam(':niveau', $niveau);
                $stmt->bindParam(':est_obligatoire', $est_obligatoire, PDO::PARAM_INT);
                $stmt->bindParam(':est_echelonnable', $est_echelonnable, PDO::PARAM_INT);
                $stmt->bindParam(':nb_tranches_max', $nb_tranches_max);
                $stmt->bindParam(':date_echeance_globale', $date_echeance_globale);
                $stmt->bindParam(':est_requis_inscription', $est_requis_inscription, PDO::PARAM_INT);
                $stmt->bindParam(':est_requis_examens', $est_requis_examens, PDO::PARAM_INT);
                $stmt->bindParam(':est_requis_deliberation', $est_requis_deliberation, PDO::PARAM_INT);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':lieu_paiement', $lieu_paiement);
                $stmt->bindParam(':idUser', $idUser);
                
                if ($stmt->execute()) {
                    $frais_id = $connexion->lastInsertId();
                    
                    // Si le frais est échelonnable, créer une tranche par défaut
                    if ($est_echelonnable) {
                        $stmt = $connexion->prepare("
                            INSERT INTO tranches_paiement_config (
                                frais_id, numero_tranche, designation, pourcentage, 
                                montant_fixe, est_requis_inscription, est_requis_examens, est_requis_deliberation
                            ) VALUES (
                                :frais_id, 1, 'Paiement complet', 100, :montant, 
                                :est_requis_inscription, :est_requis_examens, :est_requis_deliberation
                            )
                        ");
                        
                        $stmt->bindParam(':frais_id', $frais_id);
                        $stmt->bindParam(':montant', $montant);
                        $stmt->bindParam(':est_requis_inscription', $est_requis_inscription, PDO::PARAM_INT);
                        $stmt->bindParam(':est_requis_examens', $est_requis_examens, PDO::PARAM_INT);
                        $stmt->bindParam(':est_requis_deliberation', $est_requis_deliberation, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                    
                    $_SESSION['message'] = 'Le frais a été ajouté avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de l\'ajout du frais');
                }
                
                break;
                
            case 'modifier':
                // Récupération des données du formulaire
                $id = intval($_POST['id']);
                $categorie_id = intval($_POST['categorie_id']);
                $designation = trim($_POST['designation']);
                $montant = floatval($_POST['montant']);
                $devise = $_POST['devise'];
                $annee_acad_id = intval($_POST['annee_acad_id']);
                $cycle = $_POST['cycle'];
                $niveau = trim($_POST['niveau'] ?? '');
                $est_obligatoire = isset($_POST['est_obligatoire']) ? 1 : 0;
                $est_echelonnable = isset($_POST['est_echelonnable']) ? 1 : 0;
                $nb_tranches_max = $est_echelonnable ? intval($_POST['nb_tranches_max']) : 1;
                $date_echeance_globale = !empty($_POST['date_echeance_globale']) ? $_POST['date_echeance_globale'] : null;
                $est_requis_inscription = isset($_POST['est_requis_inscription']) ? 1 : 0;
                $est_requis_examens = isset($_POST['est_requis_examens']) ? 1 : 0;
                $est_requis_deliberation = isset($_POST['est_requis_deliberation']) ? 1 : 0;
                $description = trim($_POST['description'] ?? '');
                $lieu_paiement = $_POST['lieu_paiement'] ?? 'Caisse centrale';
                
                // Validation des données
                if (empty($designation)) {
                    throw new Exception('La désignation est obligatoire');
                }
                
                if ($id <= 0) {
                    throw new Exception('ID de frais invalide');
                }
                
                if ($categorie_id <= 0) {
                    throw new Exception('Veuillez sélectionner une catégorie valide');
                }
                
                if ($montant <= 0) {
                    throw new Exception('Le montant doit être supérieur à zéro');
                }
                
                if ($annee_acad_id <= 0) {
                    throw new Exception('Veuillez sélectionner une année académique valide');
                }
                
                // Vérifier si la modification affecterait des frais déjà payés
                $stmt = $connexion->prepare("
                    SELECT COUNT(*) AS nb_paiements 
                    FROM paiements_frais pf
                    JOIN affectation_frais af ON pf.affectation_id = af.id
                    WHERE af.frais_id = :id
                ");


                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Si le frais a déjà des paiements, vérifier si le montant a changé
                if ($row['nb_paiements'] > 0) {
                    $stmt = $connexion->prepare("SELECT montant FROM frais WHERE id = :id");
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();
                    $currentFrais = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($currentFrais['montant'] != $montant) {
                        // Option 1: Empêcher la modification
                        // throw new Exception('Ce frais a déjà des paiements associés. Vous ne pouvez pas modifier son montant.');
                        
                        // Option 2: Avertir mais permettre la modification
                        $_SESSION['warning'] = 'Attention: Ce frais a déjà des paiements associés. La modification du montant pourrait créer des incohérences.';
                    }
                }
                
                // Mise à jour du frais
                $stmt = $connexion->prepare("
                    UPDATE frais SET
                        categorie_id = :categorie_id,
                        designation = :designation,
                        montant = :montant,
                        devise = :devise,
                        annee_acad_id = :annee_acad_id,
                        cycle = :cycle,
                        niveau = :niveau,
                        est_obligatoire = :est_obligatoire,
                        est_echelonnable = :est_echelonnable,
                        nb_tranches_max = :nb_tranches_max,
                        date_echeance_globale = :date_echeance_globale,
                        est_requis_inscription = :est_requis_inscription,
                        est_requis_examens = :est_requis_examens,
                        est_requis_deliberation = :est_requis_deliberation,
                        description = :description,
                        lieu_paiement = :lieu_paiement
                    WHERE id = :id
                ");
                
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':categorie_id', $categorie_id);
                $stmt->bindParam(':designation', $designation);
                $stmt->bindParam(':montant', $montant);
                $stmt->bindParam(':devise', $devise);
                $stmt->bindParam(':annee_acad_id', $annee_acad_id);
                $stmt->bindParam(':cycle', $cycle);
                $stmt->bindParam(':niveau', $niveau);
                $stmt->bindParam(':est_obligatoire', $est_obligatoire, PDO::PARAM_INT);
                $stmt->bindParam(':est_echelonnable', $est_echelonnable, PDO::PARAM_INT);
                $stmt->bindParam(':nb_tranches_max', $nb_tranches_max);
                $stmt->bindParam(':date_echeance_globale', $date_echeance_globale);
                $stmt->bindParam(':est_requis_inscription', $est_requis_inscription, PDO::PARAM_INT);
                $stmt->bindParam(':est_requis_examens', $est_requis_examens, PDO::PARAM_INT);
                $stmt->bindParam(':est_requis_deliberation', $est_requis_deliberation, PDO::PARAM_INT);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':lieu_paiement', $lieu_paiement);
                
                if ($stmt->execute()) {
                    // Si le frais devient échelonnable et n'a pas de tranches configurées, ajouter une tranche par défaut
                    if ($est_echelonnable) {
                        $stmt = $connexion->prepare("
                            SELECT COUNT(*) AS nb_tranches 
                            FROM tranches_paiement_config 
                            WHERE frais_id = :id
                        ");
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($row['nb_tranches'] == 0) {
                            $stmt = $connexion->prepare("
                                INSERT INTO tranches_paiement_config (
                                    frais_id, numero_tranche, designation, pourcentage, 
                                    montant_fixe, est_requis_inscription, est_requis_examens, est_requis_deliberation
                                ) VALUES (
                                    :frais_id, 1, 'Paiement complet', 100, :montant, 
                                    :est_requis_inscription, :est_requis_examens, :est_requis_deliberation
                                )
                            ");
                            
                            $stmt->bindParam(':frais_id', $id);
                            $stmt->bindParam(':montant', $montant);
                            $stmt->bindParam(':est_requis_inscription', $est_requis_inscription, PDO::PARAM_INT);
                            $stmt->bindParam(':est_requis_examens', $est_requis_examens, PDO::PARAM_INT);
                            $stmt->bindParam(':est_requis_deliberation', $est_requis_deliberation, PDO::PARAM_INT);
                            $stmt->execute();
                        }
                    } else {
                        // Si le frais n'est plus échelonnable, supprimer toutes les tranches
                        $stmt = $connexion->prepare("
                            DELETE FROM tranches_paiement_config 
                            WHERE frais_id = :id
                        ");
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                    }
                    
                    $_SESSION['message'] = 'Le frais a été mis à jour avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de la mise à jour du frais');
                }
                
                break;
                
            case 'supprimer':
                // Récupération de l'ID
                $id = intval($_POST['id']);
                
                if ($id <= 0) {
                    throw new Exception('ID de frais invalide');
                }
                
                // Vérifier si des paiements sont associés à ce frais
                $stmt = $connexion->prepare("
                    SELECT COUNT(*) AS nb_paiements 
                    FROM paiements_frais pf
                    JOIN affectation_frais af ON pf.affectation_id = af.id
                    WHERE af.frais_id = :id
                ");


                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row['nb_paiements'] > 0) {
                    throw new Exception('Ce frais a des paiements associés. Il ne peut pas être supprimé.');
                }
                
                // Supprimer d'abord les tranches de paiement associées
                $stmt = $connexion->prepare("
                    DELETE FROM tranches_paiement_config 
                    WHERE frais_id = :id
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                // Supprimer les affectations de frais (si elles existent)
                $stmt = $connexion->prepare("
                    DELETE FROM affectation_frais 
                    WHERE frais_id = :id
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                // Suppression du frais
                $stmt = $connexion->prepare("DELETE FROM frais WHERE id = :id");
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Le frais a été supprimé avec succès';
                    $_SESSION['messageType'] = 'success';
                } else {
                    throw new Exception('Erreur lors de la suppression du frais');
                }
                
                break;
                
            case 'configurer_tranches':
                // Récupération des données du formulaire
                $frais_id = intval($_POST['frais_id']);
                $tranche_numeros = $_POST['tranche_numero'] ?? [];
                $tranche_designations = $_POST['tranche_designation'] ?? [];
                $tranche_pourcentages = $_POST['tranche_pourcentage'] ?? [];
                $tranche_montants = $_POST['tranche_montant'] ?? [];
                $tranche_dates_echeance = $_POST['tranche_date_echeance'] ?? [];
                
                if ($frais_id <= 0) {
                    throw new Exception('ID de frais invalide');
                }
                
                if (empty($tranche_numeros)) {
                    throw new Exception('Aucune tranche spécifiée');
                }
                
                // Vérifier si le total des pourcentages est égal à 100%
                $total_pourcentage = array_sum($tranche_pourcentages);
                if (abs($total_pourcentage - 100) > 0.1) {
                    throw new Exception('Le total des pourcentages doit être égal à 100%');
                }
                
                // Supprimer les tranches existantes
                $stmt = $connexion->prepare("
                    DELETE FROM tranches_paiement_config 
                    WHERE frais_id = :frais_id
                ");
                $stmt->bindParam(':frais_id', $frais_id);
                $stmt->execute();
                
                // Insérer les nouvelles tranches
                for ($i = 0; $i < count($tranche_numeros); $i++) {
                    $numero = intval($tranche_numeros[$i]);
                    $designation = trim($tranche_designations[$i]);
                    $pourcentage = floatval($tranche_pourcentages[$i]);
                    $montant = floatval($tranche_montants[$i]);
                    $date_echeance = !empty($tranche_dates_echeance[$i]) ? $tranche_dates_echeance[$i] : null;
                    
                    // Récupérer les options "requis pour"
                    $requis_inscription = isset($_POST['tranche_requis_inscription'][$numero]) ? 1 : 0;
                    $requis_examens = isset($_POST['tranche_requis_examens'][$numero]) ? 1 : 0;
                    $requis_deliberation = isset($_POST['tranche_requis_deliberation'][$numero]) ? 1 : 0;
                    
                    if (empty($designation)) {
                        throw new Exception('La désignation de la tranche #' . $numero . ' ne peut pas être vide');
                    }
                    
                    if ($pourcentage <= 0) {
                        throw new Exception('Le pourcentage de la tranche #' . $numero . ' doit être supérieur à zéro');
                    }
                    
                    if ($montant <= 0) {
                        throw new Exception('Le montant de la tranche #' . $numero . ' doit être supérieur à zéro');
                    }
                    
                    $stmt = $connexion->prepare("
                        INSERT INTO tranches_paiement_config (
                            frais_id, numero_tranche, designation, pourcentage, 
                            montant_fixe, date_echeance_fixe, est_requis_inscription, 
                            est_requis_examens, est_requis_deliberation
                        ) VALUES (
                            :frais_id, :numero, :designation, :pourcentage, 
                            :montant, :date_echeance, :requis_inscription, 
                            :requis_examens, :requis_deliberation
                        )
                    ");
                    
                    $stmt->bindParam(':frais_id', $frais_id);
                    $stmt->bindParam(':numero', $numero);
                    $stmt->bindParam(':designation', $designation);
                    $stmt->bindParam(':pourcentage', $pourcentage);
                    $stmt->bindParam(':montant', $montant);
                    $stmt->bindParam(':date_echeance', $date_echeance);
                    $stmt->bindParam(':requis_inscription', $requis_inscription, PDO::PARAM_INT);
                    $stmt->bindParam(':requis_examens', $requis_examens, PDO::PARAM_INT);
                    $stmt->bindParam(':requis_deliberation', $requis_deliberation, PDO::PARAM_INT);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Erreur lors de l\'ajout de la tranche #' . $numero);
                    }
                }
                
                $_SESSION['message'] = 'Les tranches de paiement ont été configurées avec succès';
                $_SESSION['messageType'] = 'success';
                
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
        
        // Redirection après succès
        header('Location: ../?view=finance/creation_frais' . ($action === 'configurer_tranches' ? '&edit_id=' . $frais_id : ''));
        exit();
        
    } catch (Exception $e) {
        // Enregistrer l'erreur dans les logs
        error_log('Erreur dans frais_operations.php: ' . $e->getMessage());
        
        // Stocker le message d'erreur pour l'afficher
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
        
        // Redirection avec le message d'erreur
        header('Location: ../?view=finance/creation_frais');
        exit();
    }
} else {
    // Accès direct au fichier sans soumission de formulaire
    header('Location: ../?view=finance/creation_frais');
    exit();
}

