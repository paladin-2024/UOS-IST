<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idSortie = intval($_GET['id']);
    $idUser = $_SESSION['id'];
    
    try {
        // Vérifier si la sortie existe et si elle est en état "En cours"
        $stmt = $db->prepare("SELECT s.*, d.libelle_depot FROM sortie_stock s
                             LEFT JOIN depot d ON s.id_depot = d.id_depot 
                             WHERE s.id_sortie = :id_sortie");
        $stmt->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
        $stmt->execute();
        $sortie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sortie) {
            throw new Exception("Sortie de stock non trouvée.");
        }
        
        if ($sortie['etat'] != 'En cours') {
            throw new Exception("Cette sortie de stock ne peut pas être validée car elle n'est pas en état 'En cours'.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer tous les détails de la sortie de stock et les lots associés
        $stmt = $db->prepare("SELECT dss.*, dsl.id_lot, dsl.quantite as quantite_lot, dsl.prix_unitaire, dsl.montant_total,
                             p.id_produit, p.type_produit, p.id_compte_comptable, cc.numero_compte 
                             FROM detail_sortie_stock dss
                             LEFT JOIN detail_sortie_lot dsl ON dss.id_detail_sortie = dsl.id_detail_sortie
                             JOIN produit p ON dss.id_produit = p.id_produit
                             LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte
                             WHERE dss.id_sortie = :id_sortie");
        $stmt->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
        $stmt->execute();
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($details)) {
            throw new Exception("Aucun détail trouvé pour cette sortie de stock.");
        }
        
        // Mettre à jour les stocks en fonction des détails de la sortie
        foreach ($details as $detail) {
            $idLot = $detail['id_lot'];
            $quantite = $detail['quantite_lot'];
            
            // Vérifier si le lot existe
            $stmt = $db->prepare("SELECT * FROM lot_produit WHERE id_lot = :id_lot");
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->execute();
            $lot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lot) {
                throw new Exception("Lot non trouvé (ID: $idLot).");
            }
            
            // Vérifier si la quantité disponible est suffisante
            if ($lot['quantite_disponible'] < $quantite) {
                throw new Exception("Stock insuffisant pour le lot ID: $idLot. Stock disponible: {$lot['quantite_disponible']}, Quantité demandée: $quantite");
            }
            
            // Mettre à jour le stock du lot
            $nouvelleDispo = $lot['quantite_disponible'] - $quantite;
            $stmt = $db->prepare("UPDATE lot_produit 
                                 SET quantite_disponible = :nouvelle_dispo
                                 WHERE id_lot = :id_lot");
            $stmt->bindParam(':nouvelle_dispo', $nouvelleDispo, PDO::PARAM_STR);
            $stmt->bindParam(':id_lot', $idLot, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Mettre à jour l'état de la sortie de stock
        $stmt = $db->prepare("UPDATE sortie_stock 
                             SET etat = 'Validé', 
                                 id_user_validation = :id_user_validation,
                                 date_validation = NOW()
                             WHERE id_sortie = :id_sortie");
        $stmt->bindParam(':id_user_validation', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':id_sortie', $idSortie, PDO::PARAM_INT);
        $stmt->execute();
        
        // AJOUT: Création des écritures comptables pour la sortie de stock
        
        // 1. Vérifier si le journal des stocks existe, sinon le créer
        $stmt = $db->prepare("SELECT id_journal FROM journal_comptable WHERE code_journal = 'STK' LIMIT 1");
        $stmt->execute();
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$journal) {
            $stmt = $db->prepare("INSERT INTO journal_comptable 
                (code_journal, libelle_journal, description, actif, id_user_creation) 
                VALUES 
                ('STK', 'Journal des stocks', 'Journal pour les opérations de stock', 1, :id_user_creation)");
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            $idJournal = $db->lastInsertId();
        } else {
            $idJournal = $journal['id_journal'];
        }
        
        // 2. Vérifier si l'exercice comptable existe pour la date de la sortie
        $date_sortie = $sortie['date_sortie'];
        $annee = date('Y', strtotime($date_sortie));
        $stmt = $db->prepare("SELECT id_exercice FROM exercice_comptable 
                             WHERE :date_sortie BETWEEN date_debut AND date_fin 
                             AND est_cloture = 0 
                             LIMIT 1");
        $stmt->bindParam(':date_sortie', $date_sortie, PDO::PARAM_STR);
        $stmt->execute();
        $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exercice) {
            // Créer un nouvel exercice si nécessaire
            $dateDebut = $annee . '-01-01';
            $dateFin = $annee . '-12-31';
            
            $stmt = $db->prepare("INSERT INTO exercice_comptable 
                (annee, date_debut, date_fin, est_cloture, id_user_creation) 
                VALUES 
                (:annee, :date_debut, :date_fin, 0, :id_user_creation)");
            $stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
            $stmt->bindParam(':date_debut', $dateDebut, PDO::PARAM_STR);
            $stmt->bindParam(':date_fin', $dateFin, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            $idExercice = $db->lastInsertId();
        } else {
            $idExercice = $exercice['id_exercice'];
        }
        
        // Préparer les données pour les écritures comptables
        $lignesProduits = [];
        foreach ($details as $detail) {
            $typeProduit = $detail['type_produit'] ?? 'Produit fini';
            
            // Déterminer les comptes appropriés selon le type de produit
            $compteStockNumero = '';
            $compteStockIntitule = '';
            $compteVariationNumero = '';
            $compteVariationIntitule = '';
            
            switch ($typeProduit) {
                case 'Matière première':
                    $compteStockNumero = '32';
                    $compteStockIntitule = 'STOCKS DE MATIÈRES PREMIÈRES';
                    $compteVariationNumero = '6032';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MATIÈRES PREMIÈRES';
                    break;
                case 'Consommable':
                    $compteStockNumero = '322';
                    $compteStockIntitule = 'STOCKS DE FOURNITURES CONSOMMABLES';
                    $compteVariationNumero = '60322';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE FOURNITURES CONSOMMABLES';
                    break;
                case 'Medicament':
                    $compteStockNumero = '31';
                    $compteStockIntitule = 'STOCKS DE MÉDICAMENTS';
                    $compteVariationNumero = '6031';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MÉDICAMENTS';
                    break;
                case 'Service':
                    // Les services n'ont pas de compte de stock
                    $compteStockNumero = '';
                    $compteStockIntitule = '';
                    $compteVariationNumero = '';
                    $compteVariationIntitule = '';
                case 'Produit fini':
                default:
                    $compteStockNumero = '31';
                    $compteStockIntitule = 'STOCKS DE MARCHANDISES';
                    $compteVariationNumero = '6031';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MARCHANDISES';
                    break;
            }
            
            // Ignorer les services qui n'ont pas de compte de stock
            if (empty($compteStockNumero)) {
                continue;
            }
            
            // Vérifier et créer les comptes nécessaires
            // 1. Compte de stock
            $compteStockId = verifierCreerCompte($db, $compteStockNumero, $compteStockIntitule, 3, 'Actif', $idUser);
            
            // 2. Compte de variation
            $compteVariationId = verifierCreerCompte($db, $compteVariationNumero, $compteVariationIntitule, 6, 'Charge', $idUser);
            
            // Mettre à jour le produit avec le compte de stock si nécessaire
            if (!isset($detail['id_compte_comptable']) || !$detail['id_compte_comptable']) {
                $stmt = $db->prepare("UPDATE produit SET id_compte_comptable = :id_compte WHERE id_produit = :id_produit");
                $stmt->bindParam(':id_compte', $compteStockId, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $detail['id_produit'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Ajouter à notre tableau pour les écritures comptables
            $lignesProduits[] = [
                'id_produit' => $detail['id_produit'],
                'montant_total' => $detail['montant_total'],
                'compte_stock_id' => $compteStockId,
                'compte_variation_id' => $compteVariationId
            ];
        }
        
        // Générer un numéro d'écriture unique
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_ecriture, 5) AS UNSIGNED)) as max_num FROM ecriture_comptable");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numeroEcriture = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
        
        // Créer l'écriture comptable pour la sortie de stock
        $libelleEcriture = "Sortie de stock " . $sortie['numero_sortie'] . " - " . $sortie['type_sortie'];
        $stmt = $db->prepare("INSERT INTO ecriture_comptable
            (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
            est_validee, id_exercice, id_user_creation)
            VALUES
            (:numero_ecriture, :date_ecriture, :id_journal, :libelle, :piece_reference,
            1, :id_exercice, :id_user_creation)");
        $stmt->bindParam(':numero_ecriture', $numeroEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':date_ecriture', $date_sortie, PDO::PARAM_STR);
        $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
        $stmt->bindParam(':libelle', $libelleEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':piece_reference', $sortie['reference_document'], PDO::PARAM_STR);
        $stmt->bindParam(':id_exercice', $idExercice, PDO::PARAM_INT);
        $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
        $stmt->execute();
        $idEcriture = $db->lastInsertId();
        
        // Pour chaque produit, créer les lignes d'écriture comptable
        // Pour une sortie de stock, c'est l'inverse d'une entrée:
        // - Débit au compte de variation de stock
        // - Crédit au compte de stock
        foreach ($lignesProduits as $ligne) {
            $libelleDetail = "Sortie de stock - Produit ID: " . $ligne['id_produit'];
            
            // Débit au compte de variation de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, :debit, 0, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $ligne['compte_variation_id'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleDetail, PDO::PARAM_STR);
            $stmt->bindParam(':debit', $ligne['montant_total'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
            
            // Crédit au compte de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, 0, :credit, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $ligne['compte_stock_id'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleDetail, PDO::PARAM_STR);
            $stmt->bindParam(':credit', $ligne['montant_total'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $idUser, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'validation', 'sortie_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Validation de la sortie de stock: {$sortie['numero_sortie']} du dépôt {$sortie['libelle_depot']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $idUser, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $idSortie, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La sortie de stock a été validée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../stock/stock.sortie.view&id=" . $idSortie . "';
                }
            });
        </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        
        echo "<script>
            Swal.fire({
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.history.back();
                }
            });
        </script>";
        exit;
    }
} else {
    // Redirection si accès direct au fichier sans ID
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Paramètre manquant',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../stock/stock.sortie.list';
        });
    </script>";
    exit;
}

// Fonction pour vérifier et créer un compte comptable
function verifierCreerCompte($db, $numeroCompte, $intituleCompte, $classeCompte, $typeCompte, $idUserCreation) {
    // Vérifier si le compte existe déjà
    $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = :numero_compte LIMIT 1");
    $stmt->bindParam(':numero_compte', $numeroCompte, PDO::PARAM_STR);
    $stmt->execute();
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($compte) {
        return $compte['id_compte'];
    }
    
    // Vérifier si la classe existe
    $classeNumero = substr($numeroCompte, 0, 1);
    $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = :numero_compte AND compte_parent IS NULL LIMIT 1");
    $stmt->bindParam(':numero_compte', $classeNumero, PDO::PARAM_STR);
    $stmt->execute();
    $classe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $compteParent = null;
    
    // Si la classe n'existe pas, la créer
    if (!$classe) {
        $intituleClasse = '';
        
        switch ($classeCompte) {
            case 3:
                $intituleClasse = 'COMPTES DE STOCKS';
                break;
            case 6:
                $intituleClasse = 'COMPTES DE CHARGES';
                break;
        }
        
        $stmt = $db->prepare("INSERT INTO compte_comptable 
            (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
            VALUES 
            (:numero_compte, :intitule_compte, :classe_compte, NULL, :type_compte, :id_user_creation)");
        $stmt->bindParam(':numero_compte', $classeNumero, PDO::PARAM_STR);
        $stmt->bindParam(':intitule_compte', $intituleClasse, PDO::PARAM_STR);
        $stmt->bindParam(':classe_compte', $classeCompte, PDO::PARAM_INT);
        $stmt->bindParam(':type_compte', $typeCompte, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $idUserCreation, PDO::PARAM_INT);
        $stmt->execute();
        $compteParent = $db->lastInsertId();
    } else {
        $compteParent = $classe['id_compte'];
    }
    
    // Créer les comptes intermédiaires si nécessaire
    if (strlen($numeroCompte) > 1) {
        for ($i = 2; $i <= strlen($numeroCompte); $i++) {
            $sousCompteNumero = substr($numeroCompte, 0, $i);
            
            // Vérifier si ce sous-compte existe déjà
            $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = :numero_compte LIMIT 1");
            $stmt->bindParam(':numero_compte', $sousCompteNumero, PDO::PARAM_STR);
            $stmt->execute();
            $sousCompte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sousCompte && $sousCompteNumero != $numeroCompte) {
                // Créer un intitulé générique pour le compte intermédiaire
                $intituleSousCompte = 'COMPTE ' . $sousCompteNumero;
                
                $stmt = $db->prepare("INSERT INTO compte_comptable 
                    (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
                    VALUES 
                    (:numero_compte, :intitule_compte, :classe_compte, :compte_parent, :type_compte, :id_user_creation)");
                $stmt->bindParam(':numero_compte', $sousCompteNumero, PDO::PARAM_STR);
                $stmt->bindParam(':intitule_compte', $intituleSousCompte, PDO::PARAM_STR);
                $stmt->bindParam(':classe_compte', $classeCompte, PDO::PARAM_INT);
                $stmt->bindParam(':compte_parent', $compteParent, PDO::PARAM_INT);
                $stmt->bindParam(':type_compte', $typeCompte, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $idUserCreation, PDO::PARAM_INT);
                $stmt->execute();
                $compteParent = $db->lastInsertId();
            } else if ($sousCompte) {
                $compteParent = $sousCompte['id_compte'];
            }
        }
    }
    
    // Créer le compte final s'il n'existe pas déjà
    if ($numeroCompte != substr($numeroCompte, 0, strlen($numeroCompte)-1)) {
        $stmt = $db->prepare("INSERT INTO compte_comptable 
            (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
            VALUES 
            (:numero_compte, :intitule_compte, :classe_compte, :compte_parent, :type_compte, :id_user_creation)");
        $stmt->bindParam(':numero_compte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':intitule_compte', $intituleCompte, PDO::PARAM_STR);
        $stmt->bindParam(':classe_compte', $classeCompte, PDO::PARAM_INT);
        $stmt->bindParam(':compte_parent', $compteParent, PDO::PARAM_INT);
        $stmt->bindParam(':type_compte', $typeCompte, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $idUserCreation, PDO::PARAM_INT);
        $stmt->execute();
        
        return $db->lastInsertId();
    }
    
    return $compteParent;
}
?>
