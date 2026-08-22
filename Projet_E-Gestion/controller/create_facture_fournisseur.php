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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Démarrer une transaction
        $db->beginTransaction();
        
        // Récupérer les données du formulaire
        $numero_facture = isset($_POST['numero_facture']) ? trim($_POST['numero_facture']) : '';
        $reference_fournisseur = isset($_POST['reference_fournisseur']) ? trim($_POST['reference_fournisseur']) : null;
        $date_facture = isset($_POST['date_facture']) ? trim($_POST['date_facture']) : '';
        $id_fournisseur = isset($_POST['id_fournisseur']) ? intval($_POST['id_fournisseur']) : 0;
        $id_reception = isset($_POST['id_reception']) ? intval($_POST['id_reception']) : 0;
        $montant_ht = isset($_POST['montant_ht']) ? floatval($_POST['montant_ht']) : 0.00;
        $taux_tva = isset($_POST['taux_tva']) ? floatval($_POST['taux_tva']) : 0.00;
        $montant_tva = isset($_POST['montant_tva']) ? floatval($_POST['montant_tva']) : 0.00;
        $montant_ttc = isset($_POST['montant_ttc']) ? floatval($_POST['montant_ttc']) : 0.00;
        $date_echeance = isset($_POST['date_echeance']) ? trim($_POST['date_echeance']) : '';
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : null;
        $id_user_creation = $_SESSION['id'];
        
        // Validation des données
        if (empty($numero_facture) || empty($date_facture) || $id_fournisseur <= 0 || $montant_ttc <= 0 || empty($date_echeance)) {
            throw new Exception("Les champs Numéro, Date, Fournisseur, Montant et Date d'échéance sont obligatoires.");
        }
        
        // Vérifier si le numéro de facture existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM facture_fournisseur WHERE numero_facture = :numero_facture");
        $stmt->bindParam(':numero_facture', $numero_facture, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Ce numéro de facture existe déjà. Veuillez en choisir un autre.");
        }
        
        // Récupérer les produits si pas de réception
        $products = [];
        if ($id_reception <= 0) {
            if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) == 0) {
                throw new Exception("Veuillez ajouter au moins un produit à la facture.");
            }
            $products = $_POST['products'];
        }
        
        // Récupérer les informations du fournisseur pour la comptabilité
        $stmt = $db->prepare("SELECT f.id_compte_comptable, cc.numero_compte 
                             FROM fournisseur f 
                             LEFT JOIN compte_comptable cc ON f.id_compte_comptable = cc.id_compte 
                             WHERE f.id_fournisseur = :id_fournisseur");
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->execute();
        $fournisseurCompte = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fournisseurCompte || !$fournisseurCompte['id_compte_comptable']) {
            throw new Exception("Le fournisseur n'a pas de compte comptable associé.");
        }
        
        // Insertion de la facture
        $stmt = $db->prepare("INSERT INTO facture_fournisseur 
            (numero_facture, reference_fournisseur, date_facture, id_fournisseur, id_reception, 
            montant_ht, taux_tva, montant_tva, montant_ttc, montant_paye, solde, date_echeance, 
            observation, etat, id_user_creation) 
            VALUES 
            (:numero_facture, :reference_fournisseur, :date_facture, :id_fournisseur, :id_reception, 
            :montant_ht, :taux_tva, :montant_tva, :montant_ttc, 0, :solde, :date_echeance, 
            :observation, 'En cours', :id_user_creation)");
        
        $solde = $montant_ttc; // Solde initial = montant total
        
        $stmt->bindParam(':numero_facture', $numero_facture, PDO::PARAM_STR);
        $stmt->bindParam(':reference_fournisseur', $reference_fournisseur, PDO::PARAM_STR);
        $stmt->bindParam(':date_facture', $date_facture, PDO::PARAM_STR);
        $stmt->bindParam(':id_fournisseur', $id_fournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
        $stmt->bindParam(':montant_ht', $montant_ht, PDO::PARAM_STR);
        $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_tva', $montant_tva, PDO::PARAM_STR);
        $stmt->bindParam(':montant_ttc', $montant_ttc, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':date_echeance', $date_echeance, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        
        $stmt->execute();
        $factureId = $db->lastInsertId();
        
        // Tableau pour stocker les lignes de produits pour les écritures comptables
        $lignesProduits = [];
        
        // Si basé sur une réception, récupérer les lignes de réception
        if ($id_reception > 0) {
            // Récupérer les lignes de réception
            $stmt = $db->prepare("SELECT lrf.*, p.id_compte_comptable, cc.numero_compte, p.type_produit
                                 FROM ligne_reception_fournisseur lrf
                                 JOIN produit p ON lrf.id_produit = p.id_produit
                                 LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte
                                 WHERE lrf.id_reception = :id_reception");
            $stmt->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
            $stmt->execute();
            $lignesReception = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($lignesReception as $ligne) {
                $stmt = $db->prepare("INSERT INTO ligne_facture_fournisseur
                    (id_facture, id_produit, designation, quantite, prix_unitaire, remise, montant_remise,
                    montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation)
                    VALUES
                    (:id_facture, :id_produit, :designation, :quantite, :prix_unitaire, 0, 0,
                    :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)");
                
                $montantHt = $ligne['quantite'] * $ligne['prix_unitaire'];
                $montantTva = $montantHt * ($taux_tva / 100);
                $montantTtc = $montantHt + $montantTva;
                
                $stmt->bindParam(':id_facture', $factureId, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
                $stmt->bindParam(':designation', $ligne['designation'], PDO::PARAM_STR);
                $stmt->bindParam(':quantite', $ligne['quantite'], PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $ligne['prix_unitaire'], PDO::PARAM_STR);
                $stmt->bindParam(':montant_ht', $montantHt, PDO::PARAM_STR);
                $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                $stmt->bindParam(':montant_tva', $montantTva, PDO::PARAM_STR);
                $stmt->bindParam(':montant_ttc', $montantTtc, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Ajouter à notre tableau pour les écritures comptables
                $lignesProduits[] = [
                    'id_produit' => $ligne['id_produit'],
                    'designation' => $ligne['designation'],
                    'montant_ht' => $montantHt,
                    'montant_ttc' => $montantTtc,
                    'type_produit' => $ligne['type_produit'],
                    'id_compte_comptable' => $ligne['id_compte_comptable'],
                    'numero_compte' => $ligne['numero_compte']
                ];
            }
            
            // Mettre à jour l'état de la réception
            $stmt = $db->prepare("UPDATE reception_fournisseur SET etat = 'Facturé' WHERE id_reception = :id_reception");
            $stmt->bindParam(':id_reception', $id_reception, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Insertion des lignes de facture à partir du formulaire
            foreach ($products as $product) {
                $id_produit = intval($product['id_produit']);
                $designation = trim($product['designation']);
                $quantite = floatval($product['quantite']);
                $prix_unitaire = floatval($product['prix_unitaire']);
                $remise = isset($product['remise']) ? floatval($product['remise']) : 0;
                
                if ($id_produit <= 0 || $quantite <= 0 || $prix_unitaire <= 0) {
                    throw new Exception("Données de produit invalides.");
                }
                
                // Calcul des montants
                $montant_remise = ($prix_unitaire * $quantite) * ($remise / 100);
                $montant_ht_ligne = ($prix_unitaire * $quantite) - $montant_remise;
                $montant_tva_ligne = $montant_ht_ligne * ($taux_tva / 100);
                $montant_ttc_ligne = $montant_ht_ligne + $montant_tva_ligne;
                
                $stmt = $db->prepare("INSERT INTO ligne_facture_fournisseur
                    (id_facture, id_produit, designation, quantite, prix_unitaire, remise, montant_remise,
                    montant_ht, taux_tva, montant_tva, montant_ttc, id_user_creation)
                    VALUES
                    (:id_facture, :id_produit, :designation, :quantite, :prix_unitaire, :remise, :montant_remise,
                    :montant_ht, :taux_tva, :montant_tva, :montant_ttc, :id_user_creation)");
                
                $stmt->bindParam(':id_facture', $factureId, PDO::PARAM_INT);
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
                $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
                $stmt->bindParam(':prix_unitaire', $prix_unitaire, PDO::PARAM_STR);
                $stmt->bindParam(':remise', $remise, PDO::PARAM_STR);
                $stmt->bindParam(':montant_remise', $montant_remise, PDO::PARAM_STR);
                $stmt->bindParam(':montant_ht', $montant_ht_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':taux_tva', $taux_tva, PDO::PARAM_STR);
                $stmt->bindParam(':montant_tva', $montant_tva_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':montant_ttc', $montant_ttc_ligne, PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Récupérer les informations du produit pour la comptabilité
                $stmt = $db->prepare("SELECT p.id_compte_comptable, cc.numero_compte, p.type_produit 
                                     FROM produit p 
                                     LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte 
                                     WHERE p.id_produit = :id_produit");
                $stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
                $stmt->execute();
                $produitInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Ajouter à notre tableau pour les écritures comptables
                $lignesProduits[] = [
                    'id_produit' => $id_produit,
                    'designation' => $designation,
                    'montant_ht' => $montant_ht_ligne,
                    'montant_ttc' => $montant_ttc_ligne,
                    'type_produit' => $produitInfo['type_produit'] ?? 'Produit fini',
                    'id_compte_comptable' => $produitInfo['id_compte_comptable'] ?? null,
                    'numero_compte' => $produitInfo['numero_compte'] ?? null
                ];
            }
        }
        
        // Journalisation de l'action
        $logStmt = $db->prepare("INSERT INTO log_operation 
            (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
            VALUES 
            (:id_user, 'création', 'facture_fournisseur', :id_enregistrement, :description, :adresse_ip, :navigateur)");
        
        $description = "Création de la facture fournisseur: $numero_facture";
        $adresse_ip = $_SERVER['REMOTE_ADDR'];
        $navigateur = $_SERVER['HTTP_USER_AGENT'];
        
        $logStmt->bindParam(':id_user', $id_user_creation, PDO::PARAM_INT);
        $logStmt->bindParam(':id_enregistrement', $factureId, PDO::PARAM_INT);
        $logStmt->bindParam(':description', $description, PDO::PARAM_STR);
        $logStmt->bindParam(':adresse_ip', $adresse_ip, PDO::PARAM_STR);
        $logStmt->bindParam(':navigateur', $navigateur, PDO::PARAM_STR);
        
        $logStmt->execute();
        
        // Création des écritures comptables
        
        // 1. Vérifier si le journal des achats existe, sinon le créer
        $stmt = $db->prepare("SELECT id_journal FROM journal_comptable WHERE code_journal = 'ACH' LIMIT 1");
        $stmt->execute();
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$journal) {
            $stmt = $db->prepare("INSERT INTO journal_comptable 
                (code_journal, libelle_journal, description, actif, id_user_creation) 
                VALUES 
                ('ACH', 'Journal des achats', 'Journal pour les opérations d\'achat', 1, :id_user_creation)");
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
            $idJournal = $db->lastInsertId();
        } else {
            $idJournal = $journal['id_journal'];
        }
        
        // 2. Vérifier si l'exercice comptable existe pour la date de la facture
        $annee = date('Y', strtotime($date_facture));
        $stmt = $db->prepare("SELECT id_exercice FROM exercice_comptable 
                             WHERE :date_facture BETWEEN date_debut AND date_fin 
                             AND est_cloture = 0 
                             LIMIT 1");
        $stmt->bindParam(':date_facture', $date_facture, PDO::PARAM_STR);
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
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
            $idExercice = $db->lastInsertId();
        } else {
            $idExercice = $exercice['id_exercice'];
        }
        
        // 3. Vérifier le compte de TVA déductible
        $numeroCompteTVA = '4456'; // Compte TVA déductible selon SYSCOHADA
        $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = :numero_compte LIMIT 1");
        $stmt->bindParam(':numero_compte', $numeroCompteTVA, PDO::PARAM_STR);
        $stmt->execute();
        $compteTVA = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$compteTVA) {
            // Créer le compte TVA déductible
            $stmt = $db->prepare("INSERT INTO compte_comptable 
                (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
                VALUES 
                (:numero_compte, 'TVA DÉDUCTIBLE', 4, NULL, 'Actif', :id_user_creation)");
            $stmt->bindParam(':numero_compte', $numeroCompteTVA, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
            $compteTVA['id_compte'] = $db->lastInsertId();
        }
        
        // Vérifier et corriger les comptes comptables des produits
        foreach ($lignesProduits as $key => $ligne) {
            // Récupérer le type de produit s'il n'est pas déjà défini
            if (!isset($ligne['type_produit'])) {
                $stmt = $db->prepare("SELECT type_produit FROM produit WHERE id_produit = :id_produit");
                $stmt->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
                $stmt->execute();
                $produit = $stmt->fetch(PDO::FETCH_ASSOC);
                $typeProduit = $produit['type_produit'] ?? 'Produit fini';
                $lignesProduits[$key]['type_produit'] = $typeProduit;
            } else {
                $typeProduit = $ligne['type_produit'];
            }
            
            // Déterminer les comptes appropriés selon le type de produit
            $compteStockNumero = '';
            $compteStockIntitule = '';
            $compteAchatNumero = '';
            $compteAchatIntitule = '';
            $compteVariationNumero = '';
            $compteVariationIntitule = '';
            
            switch ($typeProduit) {
                case 'Matière première':
                    $compteStockNumero = '32';
                    $compteStockIntitule = 'STOCKS DE MATIÈRES PREMIÈRES';
                    $compteAchatNumero = '602';
                    $compteAchatIntitule = 'ACHATS DE MATIÈRES PREMIÈRES';
                    $compteVariationNumero = '6032';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MATIÈRES PREMIÈRES';
                    break;
                case 'Consommable':
                    $compteStockNumero = '322';
                    $compteStockIntitule = 'STOCKS DE FOURNITURES CONSOMMABLES';
                    $compteAchatNumero = '6022';
                    $compteAchatIntitule = 'ACHATS DE FOURNITURES CONSOMMABLES';
                    $compteVariationNumero = '60322';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE FOURNITURES CONSOMMABLES';
                    break;
                case 'Medicament':
                    $compteStockNumero = '31';
                    $compteStockIntitule = 'STOCKS DE MÉDICAMENTS';
                    $compteAchatNumero = '601';
                    $compteAchatIntitule = 'ACHATS DE MÉDICAMENTS';
                    $compteVariationNumero = '6031';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MÉDICAMENTS';
                    break;
                case 'Service':
                    // Les services n'ont pas de compte de stock
                    $compteAchatNumero = '604';
                    $compteAchatIntitule = 'ACHATS DE SERVICES';
                    break;
                case 'Produit fini':
                default:
                    $compteStockNumero = '31';
                    $compteStockIntitule = 'STOCKS DE MARCHANDISES';
                    $compteAchatNumero = '601';
                    $compteAchatIntitule = 'ACHATS DE MARCHANDISES';
                    $compteVariationNumero = '6031';
                    $compteVariationIntitule = 'VARIATIONS DES STOCKS DE MARCHANDISES';
                    break;
            }
            
            // Vérifier et créer les comptes nécessaires
            // 1. Compte d'achat
            $compteAchatId = verifierCreerCompte($db, $compteAchatNumero, $compteAchatIntitule, 6, $id_user_creation);
            $lignesProduits[$key]['compte_achat_id'] = $compteAchatId;
            
            // 2. Compte de stock et variation (sauf pour les services)
            if ($typeProduit !== 'Service') {
                $compteStockId = verifierCreerCompte($db, $compteStockNumero, $compteStockIntitule, 3, $id_user_creation);
                $compteVariationId = verifierCreerCompte($db, $compteVariationNumero, $compteVariationIntitule, 6, $id_user_creation);
                
                $lignesProduits[$key]['compte_stock_id'] = $compteStockId;
                $lignesProduits[$key]['compte_variation_id'] = $compteVariationId;
                
                // Mettre à jour le produit avec le compte de stock si nécessaire
                if (!isset($ligne['id_compte_comptable']) || !$ligne['id_compte_comptable']) {
                    $stmt = $db->prepare("UPDATE produit SET id_compte_comptable = :id_compte WHERE id_produit = :id_produit");
                    $stmt->bindParam(':id_compte', $compteStockId, PDO::PARAM_INT);
                    $stmt->bindParam(':id_produit', $ligne['id_produit'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }
        
        // Générer un numéro d'écriture unique
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_ecriture, 5) AS UNSIGNED)) as max_num FROM ecriture_comptable");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numeroEcriture = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        // Créer l'écriture comptable principale (facture fournisseur)
        $libelleEcriture = "Facture fournisseur " . $numero_facture;
        $stmt = $db->prepare("INSERT INTO ecriture_comptable
            (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
            id_facture_fournisseur, est_validee, id_exercice, id_user_creation)
            VALUES
            (:numero_ecriture, :date_ecriture, :id_journal, :libelle, :piece_reference,
            :id_facture_fournisseur, 0, :id_exercice, :id_user_creation)");
        $stmt->bindParam(':numero_ecriture', $numeroEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':date_ecriture', $date_facture, PDO::PARAM_STR);
        $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
        $stmt->bindParam(':libelle', $libelleEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':piece_reference', $reference_fournisseur, PDO::PARAM_STR);
        $stmt->bindParam(':id_facture_fournisseur', $factureId, PDO::PARAM_INT);
        $stmt->bindParam(':id_exercice', $idExercice, PDO::PARAM_INT);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        $stmt->execute();
        $idEcriture = $db->lastInsertId();

        // Crédit au compte fournisseur pour le montant TTC
        $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
            (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
            VALUES
            (:id_ecriture, :id_compte, :libelle, 0, :credit, :id_user_creation)");
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->bindParam(':id_compte', $fournisseurCompte['id_compte_comptable'], PDO::PARAM_INT);
        $stmt->bindParam(':libelle', $libelleEcriture, PDO::PARAM_STR);
        $stmt->bindParam(':credit', $montant_ttc, PDO::PARAM_STR);
        $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
        $stmt->execute();

        // Débit aux comptes de charges pour les montants HT
        foreach ($lignesProduits as $ligne) {
            if (!isset($ligne['compte_achat_id'])) {
                continue; // Ignorer les lignes sans compte d'achat
            }
            
            $libelleDetail = "Achat " . $ligne['designation'];
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, :debit, 0, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $ligne['compte_achat_id'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleDetail, PDO::PARAM_STR);
            $stmt->bindParam(':debit', $ligne['montant_ht'], PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Débit au compte de TVA déductible pour le montant de la TVA
        if ($montant_tva > 0 && isset($compteTVA['id_compte'])) {
            $libelleTVA = "TVA déductible sur facture " . $numero_facture;
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                VALUES
                (:id_ecriture, :id_compte, :libelle, :debit, 0, :id_user_creation)");
            $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $compteTVA['id_compte'], PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleTVA, PDO::PARAM_STR);
            $stmt->bindParam(':debit', $montant_tva, PDO::PARAM_STR);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Pour les produits stockables, créer une écriture de variation de stock
        $produitsStockables = array_filter($lignesProduits, function($ligne) {
            return isset($ligne['type_produit']) && $ligne['type_produit'] !== 'Service';
        });

        if (!empty($produitsStockables)) {
            // Générer un nouveau numéro d'écriture pour la variation de stock
            $nextNum++;
            $numeroEcritureStock = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            
            // Créer l'écriture comptable pour la variation de stock
            $libelleEcritureStock = "Variation de stock - Facture " . $numero_facture;
            $stmt = $db->prepare("INSERT INTO ecriture_comptable
                (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
                id_facture_fournisseur, est_validee, id_exercice, id_user_creation)
                VALUES
                (:numero_ecriture, :date_ecriture, :id_journal, :libelle, :piece_reference,
                :id_facture_fournisseur, 0, :id_exercice, :id_user_creation)");
            $stmt->bindParam(':numero_ecriture', $numeroEcritureStock, PDO::PARAM_STR);
            $stmt->bindParam(':date_ecriture', $date_facture, PDO::PARAM_STR);
            $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelleEcritureStock, PDO::PARAM_STR);
            $stmt->bindParam(':piece_reference', $reference_fournisseur, PDO::PARAM_STR);
            $stmt->bindParam(':id_facture_fournisseur', $factureId, PDO::PARAM_INT);
            $stmt->bindParam(':id_exercice', $idExercice, PDO::PARAM_INT);
            $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
            $stmt->execute();
            $idEcritureStock = $db->lastInsertId();
            
            // Pour chaque produit stockable, créer les lignes d'écriture de variation de stock
            foreach ($produitsStockables as $ligne) {
                if (!isset($ligne['compte_stock_id']) || !isset($ligne['compte_variation_id'])) {
                    continue;
                }
                
                $libelleDetailStock = "Entrée en stock " . $ligne['designation'];
                
                // Débit au compte de stock
                $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                    (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                    VALUES
                    (:id_ecriture, :id_compte, :libelle, :debit, 0, :id_user_creation)");
                $stmt->bindParam(':id_ecriture', $idEcritureStock, PDO::PARAM_INT);
                $stmt->bindParam(':id_compte', $ligne['compte_stock_id'], PDO::PARAM_INT);
                $stmt->bindParam(':libelle', $libelleDetailStock, PDO::PARAM_STR);
                $stmt->bindParam(':debit', $ligne['montant_ht'], PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                $stmt->execute();
                
                // Crédit au compte de variation de stock
                $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
                    (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
                    VALUES
                    (:id_ecriture, :id_compte, :libelle, 0, :credit, :id_user_creation)");
                $stmt->bindParam(':id_ecriture', $idEcritureStock, PDO::PARAM_INT);
                $stmt->bindParam(':id_compte', $ligne['compte_variation_id'], PDO::PARAM_INT);
                $stmt->bindParam(':libelle', $libelleDetailStock, PDO::PARAM_STR);
                $stmt->bindParam(':credit', $ligne['montant_ht'], PDO::PARAM_STR);
                $stmt->bindParam(':id_user_creation', $id_user_creation, PDO::PARAM_INT);
                $stmt->execute();
            }
        }
        
        // Valider la transaction
        $db->commit();
        
        // Rediriger avec un message de succès
        echo "<script>
            Swal.fire({
                title: 'Succès',
                text: 'La facture fournisseur a été créée avec succès.',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../achats/factures/factures.view&id=" . $factureId . "';
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
    // Redirection si accès direct au fichier
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Accès non autorisé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            window.location.href = '../achats/factures/factures.list';
        });
    </script>";
    exit;
}

// Fonction pour vérifier et créer un compte comptable
function verifierCreerCompte($db, $numeroCompte, $intituleCompte, $classeCompte, $idUserCreation) {
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
        $typeCompte = '';
        
        switch ($classeCompte) {
            case 3:
                $intituleClasse = 'COMPTES DE STOCKS';
                $typeCompte = 'Actif';
                break;
            case 6:
                $intituleClasse = 'COMPTES DE CHARGES';
                $typeCompte = 'Charge';
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
                $typeCompte = ($classeCompte == 3) ? 'Actif' : 'Charge';
                
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
        $typeCompte = ($classeCompte == 3) ? 'Actif' : 'Charge';
        
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

