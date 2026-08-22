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

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    try {
        $id_entree = intval($_GET['id']);
        $id_user = $_SESSION['id'];
        
        // Vérifier si l'entrée existe et si elle est en état "En cours"
        $stmt = $db->prepare("SELECT * FROM entree_stock WHERE id_entree = :id_entree");
        $stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $stmt->execute();
        $entree = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entree) {
            throw new Exception("Entrée de stock non trouvée.");
        }
        
        if ($entree['etat'] != 'En cours') {
            throw new Exception("Cette entrée de stock ne peut pas être validée car elle n'est pas en état 'En cours'.");
        }
        
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Mise à jour de l'état de l'entrée
        $stmt = $db->prepare("UPDATE entree_stock 
                              SET etat = 'Validé', 
                                  id_user_validation = :id_user_validation, 
                                  date_validation = NOW() 
                              WHERE id_entree = :id_entree");
        
        $stmt->bindParam(':id_user_validation', $id_user, PDO::PARAM_INT);
        $stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $stmt->execute();
        
        // Récupérer les détails de l'entrée pour les écritures comptables
        $stmt = $db->prepare("SELECT des.*, p.id_produit, p.type_produit, p.id_compte_comptable, cc.numero_compte 
                             FROM detail_entree_stock des
                             JOIN produit p ON des.id_produit = p.id_produit
                             LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte
                             WHERE des.id_entree = :id_entree");
        $stmt->bindParam(':id_entree', $id_entree, PDO::PARAM_INT);
        $stmt->execute();
        $lignesProduits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // AJOUT: Création des écritures comptables pour l'entrée en stock
        
        // 1. Vérifier si le journal des stocks existe, sinon le créer
        $stmt = $db->prepare("SELECT id_journal FROM journal_comptable WHERE code_journal = 'STK' LIMIT 1");
        $stmt->execute();
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$journal) {
            $stmt = $db->prepare("INSERT INTO journal_comptable 
                (code_journal, libelle_journal, description, actif, id_user_creation) 
                VALUES 
                ('STK', 'Journal des stocks', 'Journal pour les opérations de stock', 1, :id_user_creation)");
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            $stmt->execute();
            $idJournal = $db->lastInsertId();
        } else {
            $idJournal = $journal['id_journal'];
        }
        
        // 2. Vérifier si l'exercice comptable existe pour la date de l'entrée
        $date_entree = $entree['date_entree'];
        $annee = date('Y', strtotime($date_entree));
        $stmt = $db->prepare("SELECT id_exercice FROM exercice_comptable 
                             WHERE :date_entree BETWEEN date_debut AND date_fin 
                             AND est_cloture = 0 
                             LIMIT 1");
        $stmt->bindParam(':date_entree', $date_entree, PDO::PARAM_STR);
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
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            $stmt->execute();
            $idExercice = $db->lastInsertId();
        } else {
            $idExercice = $exercice['id_exercice'];
        }
        
        // Vérifier et corriger les comptes comptables des produits
        foreach ($lignesProduits as $key => $ligne) {
            $typeProduit = $ligne['type_produit'] ?? 'Produit fini';
            
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
                    $compteStockNumero = '';  // Laisser vide pour indiquer qu'il n'y a pas de compte
                    $compteStockIntitule = '';
                    $compteVariationNumero = '';
                    $compteVariationIntitule = '';
                    break;
                case 'Produit fini':
                default:
                    $compteStockNumero = '31';
                    $compteStockIntitule = 'STOCKS DE MARCHANDISES';
                    $compteVariationNumero = '6031';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MARCHANDISES';
                    break;
            }

            // Vérifier si le produit est un service (pas de compte de stock)
            if (empty($compteStockNumero)) {
                // Passer à l'itération suivante du foreach
                continue;
            }
            
            // Vérifier et créer les comptes nécessaires
            // 1. Compte de stock
            $compteStockId = verifierCreerCompte($db, $compteStockNumero, $compteStockIntitule, 3, 'Actif', $id_user);
            $lignesProduits[$key]['compte_stock_id'] = $compteStockId;
            
            // 2. Compte de variation
            $compteVariationId = verifierCreerCompte($db, $compteVariationNumero, $compteVariationIntitule, 6, 'Charge', $id_user);
            $lignesProduits[$key]['compte_variation_id'] = $compteVariationId;
            
            // Mettre à jour le produit avec le compte de stock si nécessaire
            if (!isset($ligne['id_compte_comptable']) || !$ligne['id_compte_comptable']) {
                $stmt = $db->prepare("UPDATE produit SET id_compte_comptable = :id_compte WHERE id_produit = :id_produit");
                $stmt->bindParam(':id_compte', $compteStockId, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Générer un numéro d'écriture unique
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_ecriture, 5) AS UNSIGNED)) as max_num FROM ecriture_comptable");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numeroEcriture = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
        
        // Créer l'écriture comptable pour la variation de stock
        $libelleEcriture = "Entrée en stock " . $entree['numero_entree'] . " - " . $entree['type_entree'];
        $stmt = $db->prepare("INSERT INTO ecriture_comptable
            (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
            est_validee, id_exercice, id_user_creation)
            VALUES
            (:numero_ecriture, :date_ecriture, :id_journal, :libelle, :piece_reference,
            1, :id_exercice, :id_user_creation)");
        $stmt->bindParam(':numero_ecriture', $numeroEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':date_ecriture', $date_entree, PDO::PARAM_STR);
        $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
        $stmt->bindParam(':libelle', $libelleEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':piece_reference', $entree['reference_document'], PDO::PARAM_STR);
        $stmt->bindParam(':id_exercice', $idExercice, PDO::PARAM_INT);
        $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
        $stmt->execute();
        $idEcriture = $db->lastInsertId();
        
        // Pour chaque produit stockable, créer les lignes d'écriture de variation de stock
        foreach ($lignesProduits as $ligne) {
            if (!isset($ligne['compte_stock_id']) || !isset($ligne['compte_variation_id'])) {
                continue;
            }
            
            $libelleDetail = "Entrée en stock - Produit ID: " . $ligne['id_produit'];
            
            // Débit au compte de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, :debit, 0, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $ligne['compte_stock_id'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleDetail, PDO::PARAM_STR);
            $stmt->bindParam(':debit', $ligne['montant_total'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            $stmt->execute();
            
            // Crédit au compte de variation de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, 0, :credit, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $ligne['compte_variation_id'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleDetail, PDO::PARAM_STR);
            $stmt->bindParam(':credit', $ligne['montant_total'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'validation', 'entree_stock', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Validation de l'entrée de stock: {$entree['numero_entree']}";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $id_entree, PDO::PARAM_INT);
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
                    text: 'L\'entrée de stock a été validée avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../stock/stock.entree.view&id=" . $id_entree . "';
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
                window.location.href = '../stock/stock.entree.list';
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
    