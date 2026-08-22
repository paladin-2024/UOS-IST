<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login.php');
    exit;
}

$idUser = $_SESSION['id'];
$connexion = Connexion::getInstance()->getPDO();

// Vérifier que l'utilisateur a des droits sur les caisses
function verifierDroitsCaisse($connexion, $idUser, $caisse_id) {
    $stmt = $connexion->prepare("
        SELECT niveau 
        FROM droits_acces_finances 
        WHERE \"idUser\" = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1
        ORDER BY entite_id DESC, niveau DESC
        LIMIT 1
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer l'idAgent de l'utilisateur connecté
function getIdAgent($connexion, $idUser) {
    $stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->execute();
    $user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user_agent ? $user_agent['idAgent'] : null;
}

// Fonction pour générer une référence unique
function genererReference($type, $prefix = '') {
    $date = date('Ymd');
    $rand = mt_rand(1000, 9999);
    
    if ($type === 'Recette') {
        $prefix = 'REC';
    } elseif ($type === 'Dépense') {
        $prefix = 'DEP';
    } elseif ($type === 'Transfert') {
        $prefix = 'TR';
    } else {
        $prefix = 'OP';
    }
    
    return $prefix . '-' . $date . '-' . $rand;
}

// Fonction pour mettre à jour récursivement les catégories parentes
function updateParentCategories($connexion, $categorie_id, $montant, $type_operation, $exercice_id = null) {
    // Ne rien faire si pas d'exercice ou pas de catégorie
    if (!$exercice_id || !$categorie_id) {
        return;
    }
    
    // Mettre à jour d'abord la catégorie courante
    updateCategoryBudget($connexion, $categorie_id, $montant, $type_operation, $exercice_id);
    
    // Récupérer le parent de cette catégorie
    $stmt = $connexion->prepare("SELECT parent_id FROM categories_budget WHERE id = :id");
    $stmt->bindParam(':id', $categorie_id);
    $stmt->execute();
    $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si pas de parent, on s'arrête
    if (!$categorie || empty($categorie['parent_id'])) {
        return;
    }
    
    // Appel récursif pour mettre à jour le parent
    updateParentCategories($connexion, $categorie['parent_id'], $montant, $type_operation, $exercice_id);
}

// Fonction auxiliaire pour mettre à jour le budget d'une seule catégorie
function updateCategoryBudget($connexion, $categorie_id, $montant, $type_operation, $exercice_id) {
    // Vérifier si un budget existe pour cette catégorie
    $stmt = $connexion->prepare("
        SELECT id, montant_engage, montant_realise, disponible, montant_prevu 
        FROM budget 
        WHERE exercice_id = :exercice_id AND categorie_id = :categorie_id
    ");
    $stmt->bindParam(':exercice_id', $exercice_id);
    $stmt->bindParam(':categorie_id', $categorie_id);
    $stmt->execute();
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($budget) {
        // Calculer les nouveaux montants selon le type d'opération
        if ($type_operation === 'engage') {
            $nouveau_montant_engage = $budget['montant_engage'] + $montant;
            $nouveau_disponible = $budget['disponible'] - $montant;
            
            // Mettre à jour le budget de la catégorie
            $stmt = $connexion->prepare("
                UPDATE budget 
                SET montant_engage = :montant_engage, 
                    disponible = :disponible
                WHERE id = :id
            ");
            $stmt->bindParam(':montant_engage', $nouveau_montant_engage);
            $stmt->bindParam(':disponible', $nouveau_disponible);
            $stmt->bindParam(':id', $budget['id']);
            $stmt->execute();
        } else if ($type_operation === 'realise') {
            $nouveau_montant_realise = $budget['montant_realise'] + $montant;
            
            // Mettre à jour le budget de la catégorie
            $stmt = $connexion->prepare("
                UPDATE budget 
                SET montant_realise = :montant_realise
                WHERE id = :id
            ");
            $stmt->bindParam(':montant_realise', $nouveau_montant_realise);
            $stmt->bindParam(':id', $budget['id']);
            $stmt->execute();
        }
    } else {
        // Si le budget n'existe pas pour cette catégorie mais qu'on doit y enregistrer des opérations,
        // on peut optionnellement créer une entrée dans la table budget
        $type_categorie = '';
        
        // Déterminer le type de catégorie (Recette ou Dépense)
        $stmt = $connexion->prepare("SELECT type FROM categories_budget WHERE id = :id");
        $stmt->bindParam(':id', $categorie_id);
        $stmt->execute();
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($categorie) {
            $type_categorie = $categorie['type'];
            
            // Créer une nouvelle entrée de budget avec les valeurs par défaut
            $montant_prevu = 0;
            $montant_engage = 0;
            $montant_realise = 0;
            $disponible = 0;
            
            // Initialiser les valeurs selon le type d'opération
            if ($type_operation === 'engage') {
                $montant_engage = $montant;
                $disponible = -$montant; // Disponible négatif car pas de montant prévu
            } else if ($type_operation === 'realise') {
                $montant_realise = $montant;
            }
            
            $stmt = $connexion->prepare("
                INSERT INTO budget (
                    exercice_id, categorie_id, montant_prevu, montant_revise, 
                    montant_engage, montant_realise, disponible, date_creation, \"idUser\"
                ) VALUES (
                    :exercice_id, :categorie_id, :montant_prevu, NULL, 
                    :montant_engage, :montant_realise, :disponible, NOW(), :idUser
                )
            ");
            
            // Récupérer l'ID utilisateur depuis la session ou le paramètre global
            global $idUser;
            
            $stmt->bindParam(':exercice_id', $exercice_id);
            $stmt->bindParam(':categorie_id', $categorie_id);
            $stmt->bindParam(':montant_prevu', $montant_prevu);
            $stmt->bindParam(':montant_engage', $montant_engage);
            $stmt->bindParam(':montant_realise', $montant_realise);
            $stmt->bindParam(':disponible', $disponible);
            $stmt->bindParam(':idUser', $idUser);
            
            $stmt->execute();
        }
    }
}


// Fonction pour télécharger des pièces jointes
function uploadPiecesJointes($files) {
    $uploadDir = dirname(__DIR__) . '/uploads/transactions/';
    
    // Créer le répertoire s'il n'existe pas
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadedFiles = [];
    
    // Vérifier si des fichiers ont été téléchargés
    if (!empty($files['name'][0])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            $fileName = $files['name'][$i];
            $fileTmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileError = $files['error'][$i];
            
            // Vérifier les erreurs et la taille
            if ($fileError === 0 && $fileSize <= 5242880) { // 5 MB max
                // Générer un nom de fichier unique
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid('doc_') . '.' . $fileExtension;
                $destination = $uploadDir . $newFileName;
                
                // Déplacer le fichier téléchargé
                if (move_uploaded_file($fileTmpName, $destination)) {
                    $uploadedFiles[] = $newFileName;
                }
            }
        }
    }
    
    return !empty($uploadedFiles) ? implode(',', $uploadedFiles) : null;
}

try {
    $action = isset($_POST['action']) ? $_POST['action'] : null;
    
    // Ajouter une nouvelle transaction
    if ($action === 'ajouter') {
        $type = $_POST['type'];
        $source = $_POST['source'];
        $source_id = intval($_POST['source_id']);
        $session_caisse_id = !empty($_POST['session_caisse_id']) ? intval($_POST['session_caisse_id']) : null;
        $idAgent = !empty($_POST['idAgent']) ? intval($_POST['idAgent']) : getIdAgent($connexion, $idUser);
        $reference = $_POST['reference'];
        $montant = floatval($_POST['montant']);
        $devise = $_POST['devise'];
        $date_transaction = $_POST['date_transaction'];
        $description = $_POST['description'];
        $categorie_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
        $confirmer = isset($_POST['confirmer']) ? true : false;
        
        $depositaire = null;
        if ($type === 'Recette' && !empty($_POST['depositaire'])) {
            $depositaire = $_POST['depositaire'];
        }
        
        // Variables spécifiques selon le type
        $destination_id = null;
        $taux_change = null;
        
        // Variable pour stocker le bénéficiaire (pour une utilisation ultérieure dans la table depenses)
        $beneficiaire_nom = null;
        
        // Vérifier les droits d'accès
        $droits = verifierDroitsCaisse($connexion, $idUser, $source_id);
        if (!$droits || ($droits['niveau'] === 'Lecture')) {
            $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour effectuer cette opération.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $source_id);
            exit;
        }
        
        // Vérifier que la session de caisse existe et est active
        if ($session_caisse_id) {
            $stmt = $connexion->prepare("SELECT statut FROM sessions_caisse WHERE id = :id");
            $stmt->bindParam(':id', $session_caisse_id);
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session || $session['statut'] !== 'Ouverte') {
                $_SESSION['message'] = "La session de caisse n'est pas ouverte. Impossible d'effectuer cette opération.";
                $_SESSION['messageType'] = "danger";
                header('Location: ../?view=finance/operations_caisse&caisse_id=' . $source_id);
                exit;
            }
        } else {
            $_SESSION['message'] = "Aucune session de caisse n'est ouverte. Impossible d'effectuer cette opération.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $source_id);
            exit;
        }
        
        // Vérifier que l'agent est bien celui qui a ouvert la session (sauf pour les administrateurs)
        if ($droits['niveau'] !== 'Administration') {
            $stmt = $connexion->prepare("SELECT \"idAgent\" FROM sessions_caisse WHERE id = :id");
            $stmt->bindParam(':id', $session_caisse_id);
            $stmt->execute();
            $session_agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session_agent['idAgent'] != $idAgent) {
                $_SESSION['message'] = "Vous ne pouvez pas effectuer d'opérations sur une session ouverte par un autre agent.";
                $_SESSION['messageType'] = "danger";
                header('Location: ../?view=finance/operations_caisse&caisse_id=' . $source_id);
                exit;
            }
        }
        
        // Vérifier le solde suffisant pour les dépenses et transferts
        if ($type === 'Dépense' || $type === 'Transfert') {
            $stmt = $connexion->prepare("SELECT solde_actuel FROM caisses WHERE id = :id");
            $stmt->bindParam(':id', $source_id);
            $stmt->execute();
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($caisse['solde_actuel'] < $montant) {
                $_SESSION['message'] = "Solde insuffisant dans la caisse pour effectuer cette opération.";
                $_SESSION['messageType'] = "danger";
                header('Location: ../?view=finance/operations_caisse&caisse_id=' . $source_id);
                exit;
            }
        }
        
        // Traitement spécifique pour le type de transaction
        if ($type === 'Transfert') {
            $destination_type = $_POST['destination_type'];
            
            if ($destination_type === 'Caisse') {
                $destination_id = intval($_POST['destination_caisse_id']);
                
                // Vérifier si la devise est différente
                $stmt = $connexion->prepare("SELECT devise FROM caisses WHERE id = :id");
                $stmt->bindParam(':id', $destination_id);
                $stmt->execute();
                $dest_caisse = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dest_caisse['devise'] !== $devise) {
                    $taux_change = floatval($_POST['taux_change']);
                }
            } elseif ($destination_type === 'Banque') {
                $destination_id = intval($_POST['destination_banque_id']);
                
                // Vérifier si la devise est différente
                $stmt = $connexion->prepare("SELECT devise FROM comptes_bancaires WHERE id = :id");
                $stmt->bindParam(':id', $destination_id);
                $stmt->execute();
                $dest_banque = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dest_banque['devise'] !== $devise) {
                    $taux_change = floatval($_POST['taux_change']);
                }
            }
        } elseif ($type === 'Dépense') {
            // Récupérer le nom du bénéficiaire pour l'utiliser dans la table depenses
            $beneficiaire_nom = $_POST['beneficiaire'];
        }
        
        // Télécharger les pièces jointes
        $pieces_jointes = isset($_FILES['pieces_jointes']) ? uploadPiecesJointes($_FILES['pieces_jointes']) : null;
        
        // Déterminer le statut initial
        $statut = $confirmer ? 'Confirmée' : 'Provisoire';
        
        // Début de la transaction SQL
        $connexion->beginTransaction();
        
        // Insérer la transaction - SANS le champ beneficiaire qui n'existe pas dans la table
        $sql = "INSERT INTO transactions (
            reference, type, montant, devise, taux_change, date_transaction, 
            source, source_id, destination_id, categorie_id, description, 
            pieces_jointes, statut, \"idAgent\", session_caisse_id, \"idUser\", 
            beneficiaire, depositaire
        ) VALUES (
            :reference, :type, :montant, :devise, :taux_change, :date_transaction,
            :source, :source_id, :destination_id, :categorie_id, :description,
            :pieces_jointes, :statut, :idAgent, :session_caisse_id, :idUser,
            :beneficiaire, :depositaire
        )";

        
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':reference', $reference);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':devise', $devise);
        $stmt->bindParam(':taux_change', $taux_change);
        $stmt->bindParam(':date_transaction', $date_transaction);
        $stmt->bindParam(':source', $source);
        $stmt->bindParam(':source_id', $source_id);
        $stmt->bindParam(':destination_id', $destination_id);
        $stmt->bindParam(':categorie_id', $categorie_id);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':pieces_jointes', $pieces_jointes);
        $stmt->bindParam(':statut', $statut);
        $stmt->bindParam(':idAgent', $idAgent);
        $stmt->bindParam(':session_caisse_id', $session_caisse_id);
        $stmt->bindParam(':idUser', $idUser);
        $beneficiaire = ($type === 'Dépense' && !empty($_POST['beneficiaire'])) ? $_POST['beneficiaire'] : null;
        $stmt->bindParam(':beneficiaire', $beneficiaire);
        $stmt->bindParam(':depositaire', $depositaire);
        
        $stmt->execute();
        $transaction_id = $connexion->lastInsertId();
        
        // Si c'est une dépense, enregistrer les détails du bénéficiaire dans la table depenses
        if ($type === 'Dépense' && !empty($beneficiaire)) {
            // Récupérer l'exercice budgétaire actif
            $stmt = $connexion->prepare("SELECT id FROM exercices_budgetaires WHERE est_actif = 1 AND est_cloture = 0 LIMIT 1");
            $stmt->execute();
            $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
            $exercice_id = $exercice ? $exercice['id'] : null;
            
            if ($exercice_id && $categorie_id) {
                $sql_depense = "INSERT INTO depenses (
                    transaction_id, categorie_budget_id, exercice_id, montant, devise, 
                    beneficiaire, motif, date_depense, statut, \"idUser\"
                ) VALUES (
                    :transaction_id, :categorie_budget_id, :exercice_id, :montant, :devise,
                    :beneficiaire, :motif, :date_depense, :statut, :idUser
                )";
                
                $stmt_depense = $connexion->prepare($sql_depense);
                $stmt_depense->bindParam(':transaction_id', $transaction_id);
                $stmt_depense->bindParam(':categorie_budget_id', $categorie_id);
                $stmt_depense->bindParam(':exercice_id', $exercice_id);
                $stmt_depense->bindParam(':montant', $montant);
                $stmt_depense->bindParam(':devise', $devise);
                $stmt_depense->bindParam(':beneficiaire', $beneficiaire_nom);
                $stmt_depense->bindParam(':motif', $description);
                $stmt_depense->bindParam(':date_depense', $date_transaction);
                $stmt_depense->bindParam(':statut', $statut);
                $stmt_depense->bindParam(':idUser', $idUser);
                
                $stmt_depense->execute();
            }
        }
        
        // Mettre à jour les soldes si la transaction est confirmée
        if ($statut === 'Confirmée') {
            // Pour la source (caisse ou banque)
            if ($source === 'Caisse') {
                if ($type === 'Recette') {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $montant);
                $stmt->bindParam(':id', $source_id);
                $stmt->execute();
            } elseif ($source === 'Banque') {
                if ($type === 'Recette') {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $montant);
                $stmt->bindParam(':id', $source_id);
                $stmt->execute();
            }
            
            // Pour la destination (si transfert)
            if ($type === 'Transfert' && $destination_id) {
                $montant_destination = $montant;
                if ($taux_change) {
                    $montant_destination = $montant * $taux_change;
                }
                
                if ($destination_type === 'Caisse') {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $montant_destination);
                $stmt->bindParam(':id', $destination_id);
                $stmt->execute();
                
                // Créer une transaction miroir pour la destination
                $reference_dest = str_replace('TR-', 'TR-DEST-', $reference);
                $source_dest = $destination_type;
                
                $sql = "INSERT INTO transactions (
                            reference, type, montant, devise, taux_change, date_transaction, 
                            source, source_id, destination_id, categorie_id, description, 
                            pieces_jointes, statut, \"idAgent\", \"idUser\"
                        ) VALUES (
                            :reference, 'Recette', :montant, :devise, :taux_change, :date_transaction,
                            :source, :source_id, :destination_id, :categorie_id, :description,
                            :pieces_jointes, 'Confirmée', :idAgent, :idUser
                        )";
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':reference', $reference_dest);
                $stmt->bindParam(':montant', $montant_destination);
                
                if ($destination_type === 'Caisse') {
                    $stmt_dest = $connexion->prepare("SELECT devise FROM caisses WHERE id = :id");
                } else {
                    $stmt_dest = $connexion->prepare("SELECT devise FROM comptes_bancaires WHERE id = :id");
                }
                
                $stmt_dest->bindParam(':id', $destination_id);
                $stmt_dest->execute();
                $dest_info = $stmt_dest->fetch(PDO::FETCH_ASSOC);
                
                $devise_dest = $dest_info['devise'];
                $stmt->bindParam(':devise', $devise_dest);
                $stmt->bindParam(':taux_change', $taux_change);
                $stmt->bindParam(':date_transaction', $date_transaction);
                $stmt->bindParam(':source', $source_dest);
                $stmt->bindParam(':source_id', $destination_id);
                $stmt->bindParam(':destination_id', $source_id);
                $stmt->bindParam(':categorie_id', $categorie_id);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':pieces_jointes', $pieces_jointes);
                $stmt->bindParam(':idAgent', $idAgent);
                $stmt->bindParam(':idUser', $idUser);
                
                $stmt->execute();
            }

            // Récupérer l'exercice budgétaire actif
            $stmt = $connexion->prepare("SELECT id FROM exercices_budgetaires WHERE est_actif = 1 AND est_cloture = 0 LIMIT 1");
            $stmt->execute();
            $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exercice && $categorie_id) {
                $exercice_id = $exercice['id'];
                
                // Si c'est une recette, on met à jour le montant réalisé
                if ($type === 'Recette') {
                    // Mettre à jour les catégories parentes
                    updateParentCategories($connexion, $categorie_id, $montant, 'realise', $exercice_id);
                } 
                // Si c'est une dépense, on met à jour le montant engagé
                else if ($type === 'Dépense') {
                    // Mettre à jour les catégories parentes
                    updateParentCategories($connexion, $categorie_id, $montant, 'engage', $exercice_id);
                    updateParentCategories($connexion, $categorie_id, $montant, 'realise', $exercice_id);
                }

            }
        }
        
        // Valider la transaction SQL
        $connexion->commit();
        
        $_SESSION['message'] = "La transaction a été " . ($statut === 'Confirmée' ? "enregistrée et confirmée" : "enregistrée comme provisoire") . " avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
    // Confirmer une transaction existante
    elseif ($action === 'confirmer') {
        $transaction_id = intval($_POST['transaction_id']);
        $caisse_id = intval($_POST['caisse_id']);
        
        // Vérifier les droits d'accès
        $droits = verifierDroitsCaisse($connexion, $idUser, $caisse_id);
        if (!$droits || ($droits['niveau'] === 'Lecture')) {
            $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour confirmer cette transaction.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Récupérer les détails de la transaction
        $stmt = $connexion->prepare("
                        SELECT t.*, c.solde_actuel AS caisse_solde 
            FROM transactions t
            LEFT JOIN caisses c ON t.source = 'Caisse' AND t.source_id = c.id
            WHERE t.id = :id AND t.statut = 'Provisoire'
        ");
        $stmt->bindParam(':id', $transaction_id);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            $_SESSION['message'] = "Transaction introuvable ou déjà confirmée.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Vérifier le solde suffisant pour les dépenses et transferts
        if (($transaction['type'] === 'Dépense' || $transaction['type'] === 'Transfert') 
            && $transaction['source'] === 'Caisse' 
            && $transaction['caisse_solde'] < $transaction['montant']) {
            $_SESSION['message'] = "Solde insuffisant dans la caisse pour confirmer cette transaction.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Début de la transaction SQL
        $connexion->beginTransaction();
        
        // Mettre à jour le statut de la transaction
        $stmt = $connexion->prepare("
            UPDATE transactions 
            SET statut = 'Confirmée', date_validation = NOW(), \"idValidateur\" = :idUser 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $transaction_id);
        $stmt->bindParam(':idUser', $idUser);
        $stmt->execute();
        
        // Mettre à jour les soldes
        if ($transaction['source'] === 'Caisse') {
            if ($transaction['type'] === 'Recette') {
                $sql = "UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :id";
            } else {
                $sql = "UPDATE caisses SET solde_actuel = solde_actuel - :montant WHERE id = :id";
            }
            
            $stmt = $connexion->prepare($sql);
            $stmt->bindParam(':montant', $transaction['montant']);
            $stmt->bindParam(':id', $transaction['source_id']);
            $stmt->execute();
        } elseif ($transaction['source'] === 'Banque') {
            if ($transaction['type'] === 'Recette') {
                $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :id";
            } else {
                $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel - :montant WHERE id = :id";
            }
            
            $stmt = $connexion->prepare($sql);
            $stmt->bindParam(':montant', $transaction['montant']);
            $stmt->bindParam(':id', $transaction['source_id']);
            $stmt->execute();
        }
        
        // Pour la destination (si transfert)
        if ($transaction['type'] === 'Transfert' && $transaction['destination_id']) {
            $montant_destination = $transaction['montant'];
            if ($transaction['taux_change']) {
                $montant_destination = $transaction['montant'] * $transaction['taux_change'];
            }
            
            // Déterminer le type de destination
            $stmt = $connexion->prepare("SELECT id FROM caisses WHERE id = :id");
            $stmt->bindParam(':id', $transaction['destination_id']);
            $stmt->execute();
            $dest_caisse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dest_caisse) {
                $destination_type = 'Caisse';
                $sql = "UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :id";
            } else {
                $destination_type = 'Banque';
                $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :id";
            }
            
            $stmt = $connexion->prepare($sql);
            $stmt->bindParam(':montant', $montant_destination);
            $stmt->bindParam(':id', $transaction['destination_id']);
            $stmt->execute();
            
            // Créer une transaction miroir pour la destination
            $reference_dest = str_replace('TR-', 'TR-DEST-', $transaction['reference']);
            $source_dest = $destination_type;
            
            // Récupérer la devise de la destination
            if ($destination_type === 'Caisse') {
                $stmt_dest = $connexion->prepare("SELECT devise FROM caisses WHERE id = :id");
            } else {
                $stmt_dest = $connexion->prepare("SELECT devise FROM comptes_bancaires WHERE id = :id");
            }
            $stmt_dest->bindParam(':id', $transaction['destination_id']);
            $stmt_dest->execute();
            $dest_info = $stmt_dest->fetch(PDO::FETCH_ASSOC);
            $devise_dest = $dest_info['devise'];
            
            $sql = "INSERT INTO transactions (
                        reference, type, montant, devise, taux_change, date_transaction, 
                        source, source_id, destination_id, categorie_id, description, 
                        pieces_jointes, statut, \"idAgent\", \"idUser\"
                    ) VALUES (
                        :reference, 'Recette', :montant, :devise, :taux_change, :date_transaction,
                        :source, :source_id, :destination_id, :categorie_id, :description,
                        :pieces_jointes, 'Confirmée', :idAgent, :idUser
                    )";
            
            $stmt = $connexion->prepare($sql);
            $stmt->bindParam(':reference', $reference_dest);
            $stmt->bindParam(':montant', $montant_destination);
            $stmt->bindParam(':devise', $devise_dest);
            $stmt->bindParam(':taux_change', $transaction['taux_change']);
            $stmt->bindParam(':date_transaction', $transaction['date_transaction']);
            $stmt->bindParam(':source', $source_dest);
            $stmt->bindParam(':source_id', $transaction['destination_id']);
            $stmt->bindParam(':destination_id', $transaction['source_id']);
            $stmt->bindParam(':categorie_id', $transaction['categorie_id']);
            $stmt->bindParam(':description', $transaction['description']);
            $stmt->bindParam(':pieces_jointes', $transaction['pieces_jointes']);
            $stmt->bindParam(':idAgent', $transaction['idAgent']);
            $stmt->bindParam(':idUser', $idUser);
            
            $stmt->execute();
        }

        // Si la transaction est de type 'Dépense', mettre à jour le statut dans la table 'depenses'
        if ($transaction['type'] === 'Dépense') {
            $stmt = $connexion->prepare("
                UPDATE depenses 
                SET statut = 'Validée' 
                WHERE transaction_id = :transaction_id
            ");
            $stmt->bindParam(':transaction_id', $transaction_id);
            $stmt->execute();
        }

        // Récupérer l'exercice budgétaire actif
        $stmt = $connexion->prepare("SELECT id FROM exercices_budgetaires WHERE est_actif = 1 AND est_cloture = 0 LIMIT 1");
        $stmt->execute();
        $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exercice && $transaction['categorie_id']) {
            $exercice_id = $exercice['id'];
            $montant = $transaction['montant'];
            
            // Si c'est une recette, on met à jour le montant réalisé
            if ($transaction['type'] === 'Recette') {
                // Mettre à jour les catégories parentes
                updateParentCategories($connexion, $transaction['categorie_id'], $montant, 'realise', $exercice_id);
            } 
            // Si c'est une dépense, on met à jour le montant engagé
            else if ($transaction['type'] === 'Dépense') {
                // Mettre à jour les catégories parentes
                updateParentCategories($connexion, $transaction['categorie_id'], $montant, 'engage', $exercice_id);
                updateParentCategories($connexion, $transaction['categorie_id'], $montant, 'realise', $exercice_id);
                
            }
        }
        
        // Valider la transaction SQL
        $connexion->commit();
        
        $_SESSION['message'] = "La transaction a été confirmée avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
    // Annuler une transaction
    elseif ($action === 'annuler') {
        $transaction_id = intval($_POST['transaction_id']);
        $caisse_id = intval($_POST['caisse_id']);
        $motif_annulation = $_POST['motif_annulation'];
        
        // Vérifier les droits d'accès (il faut au moins être validateur ou administrateur)
        $droits = verifierDroitsCaisse($connexion, $idUser, $caisse_id);
        if (!$droits || !in_array($droits['niveau'], ['Validation', 'Administration'])) {
            $_SESSION['message'] = "Vous n'avez pas les droits nécessaires pour annuler cette transaction.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Récupérer les détails de la transaction
        $stmt = $connexion->prepare("
            SELECT t.* 
            FROM transactions t
            WHERE t.id = :id AND t.statut != 'Annulée'
        ");
        $stmt->bindParam(':id', $transaction_id);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            $_SESSION['message'] = "Transaction introuvable ou déjà annulée.";
            $_SESSION['messageType'] = "danger";
            header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
            exit;
        }
        
        // Début de la transaction SQL
        $connexion->beginTransaction();
        
        // Mettre à jour le statut de la transaction
        $stmt = $connexion->prepare("
            UPDATE transactions 
            SET statut = 'Annulée', motif_annulation = :motif_annulation 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $transaction_id);
        $stmt->bindParam(':motif_annulation', $motif_annulation);
        $stmt->execute();
        
        // Mettre à jour les soldes si la transaction était confirmée
        if ($transaction['statut'] === 'Confirmée') {
            // Pour la source (caisse ou banque)
            if ($transaction['source'] === 'Caisse') {
                if ($transaction['type'] === 'Recette') {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $transaction['montant']);
                $stmt->bindParam(':id', $transaction['source_id']);
                $stmt->execute();
            } elseif ($transaction['source'] === 'Banque') {
                if ($transaction['type'] === 'Recette') {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel + :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $transaction['montant']);
                $stmt->bindParam(':id', $transaction['source_id']);
                $stmt->execute();
            }
            
            // Pour la destination (si transfert)
            if ($transaction['type'] === 'Transfert' && $transaction['destination_id']) {
                $montant_destination = $transaction['montant'];
                if ($transaction['taux_change']) {
                    $montant_destination = $transaction['montant'] * $transaction['taux_change'];
                }
                
                // Déterminer le type de destination
                $stmt = $connexion->prepare("SELECT id FROM caisses WHERE id = :id");
                $stmt->bindParam(':id', $transaction['destination_id']);
                $stmt->execute();
                $dest_caisse = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dest_caisse) {
                    $sql = "UPDATE caisses SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                } else {
                    $sql = "UPDATE comptes_bancaires SET solde_actuel = solde_actuel - :montant WHERE id = :id";
                }
                
                $stmt = $connexion->prepare($sql);
                $stmt->bindParam(':montant', $montant_destination);
                $stmt->bindParam(':id', $transaction['destination_id']);
                $stmt->execute();
                
                // Annuler aussi la transaction miroir si elle existe
                $reference_dest = str_replace('TR-', 'TR-DEST-', $transaction['reference']);
                $stmt = $connexion->prepare("
                    UPDATE transactions 
                    SET statut = 'Annulée', motif_annulation = :motif_annulation 
                    WHERE reference = :reference
                ");
                $stmt->bindParam(':reference', $reference_dest);
                $stmt->bindParam(':motif_annulation', $motif_annulation);
                $stmt->execute();
            }
            
            // Si c'est une dépense, mettre à jour la table depenses
            if ($transaction['type'] === 'Dépense') {
                $stmt = $connexion->prepare("
                    UPDATE depenses 
                    SET statut = 'Annulée' 
                    WHERE transaction_id = :transaction_id
                ");
                $stmt->bindParam(':transaction_id', $transaction_id);
                $stmt->execute();
            }
            
            // Récupérer l'exercice budgétaire actif si une catégorie est spécifiée
            if ($transaction['categorie_id']) {
                                $stmt = $connexion->prepare("SELECT id FROM exercices_budgetaires WHERE est_actif = 1 AND est_cloture = 0 LIMIT 1");
                $stmt->execute();
                $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($exercice) {
                    $exercice_id = $exercice['id'];
                    $montant = $transaction['montant'];
                    
                    // Si c'est une recette, on doit réduire le montant réalisé
                    if ($transaction['type'] === 'Recette') {
                        // Montant négatif pour annuler l'effet
                        updateParentCategories($connexion, $transaction['categorie_id'], -$montant, 'realise', $exercice_id);
                    } 
                    // Si c'est une dépense, on doit réduire le montant engagé
                    else if ($transaction['type'] === 'Dépense') {
                        // Montant négatif pour annuler l'effet
                        updateParentCategories($connexion, $transaction['categorie_id'], -$montant, 'engage', $exercice_id);
                        updateParentCategories($connexion, $transaction['categorie_id'], -$montant, 'realise', $exercice_id);
                    }
                }
            }
        }
        
        // Valider la transaction SQL
        $connexion->commit();
        
        $_SESSION['message'] = "La transaction a été annulée avec succès.";
        $_SESSION['messageType'] = "success";
    }
    
} catch (PDOException $e) {
    // En cas d'erreur, annuler les modifications
    if ($connexion->inTransaction()) {
        $connexion->rollBack();
    }
    
    $_SESSION['message'] = "Erreur lors de l'opération: " . $e->getMessage();
    $_SESSION['messageType'] = "danger";
}

// Redirection
if (isset($caisse_id)) {
    header('Location: ../?view=finance/operations_caisse&caisse_id=' . $caisse_id);
} else {
    header('Location: ../?view=finance/operations_caisse');
}
exit;
?>



