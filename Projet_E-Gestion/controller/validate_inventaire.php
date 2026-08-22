<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer l'ID de l'inventaire
$id_inventaire = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_inventaire <= 0) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'ID d\'inventaire invalide.',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../stock/inventaire.list';
            }
        });
    </script>";
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $db->beginTransaction();

    // 1. Vérifier que l'inventaire existe et est en état "En cours"
    $stmt = $db->prepare("SELECT i.*, d.libelle_depot FROM inventaire i 
                         LEFT JOIN depot d ON i.id_depot = d.id_depot
                         WHERE i.id_inventaire = ? AND i.etat = 'En cours'");
    $stmt->execute([$id_inventaire]);
    $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventaire) {
        throw new Exception("L'inventaire n'existe pas ou n'est plus modifiable.");
    }

    // 2. Récupérer tous les détails de l'inventaire
    $stmt = $db->prepare("SELECT di.*, p.libelle_produit, p.id_produit, p.type_produit, p.id_compte_comptable, 
                         cc.numero_compte, l.numero_lot 
                         FROM detail_inventaire di
                         JOIN produit p ON di.id_produit = p.id_produit
                         LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte
                         LEFT JOIN lot_produit l ON di.id_lot = l.id_lot
                         WHERE di.id_inventaire = ?");
    $stmt->execute([$id_inventaire]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $date = date('Y-m-d');
    $id_user = $_SESSION['id'];

    // Vérifier s'il y a des écarts positifs ou négatifs
    $hasPositiveGap = false;
    $hasNegativeGap = false;

    foreach ($details as $detail) {
        $ecart = $detail['ecart'];
        if ($ecart > 0) {
            $hasPositiveGap = true;
        } elseif ($ecart < 0) {
            $hasNegativeGap = true;
        }

        // Si on a déjà trouvé les deux types d'écarts, on peut sortir de la boucle
        if ($hasPositiveGap && $hasNegativeGap) {
            break;
        }
    }

    // Variables pour les IDs de sortie et d'entrée
    $id_sortie = null;
    $id_entree = null;
    $total_sortie = 0;
    $total_entree = 0;

    // 3. Créer une sortie de stock uniquement s'il y a des écarts négatifs
    if ($hasNegativeGap) {
        $numero_sortie = "SORT-INV-" . date('YmdHis') . "-" . $id_inventaire;

        $stmt = $db->prepare("INSERT INTO sortie_stock (numero_sortie, date_sortie, id_depot, type_sortie, 
                             reference_document, observation, etat, id_user_creation, id_user_validation, date_validation) 
                             VALUES (?, ?, ?, 'Inventaire', ?, ?, 'Validé', ?, ?, NOW())");
        $stmt->execute([
            $numero_sortie,
            $date,
            $inventaire['id_depot'],
            'INV-' . $inventaire['numero_inventaire'],
            'Ajustement négatif suite à l\'inventaire ' . $inventaire['numero_inventaire'],
            $id_user,
            $id_user
        ]);
        $id_sortie = $db->lastInsertId();
    }

    // Créer une entrée de stock uniquement s'il y a des écarts positifs
    if ($hasPositiveGap) {
        $numero_entree = "ENT-INV-" . date('YmdHis') . "-" . $id_inventaire;

        $stmt = $db->prepare("INSERT INTO entree_stock (numero_entree, date_entree, id_depot, type_entree, 
                             reference_document, observation, etat, id_user_creation, id_user_validation, date_validation) 
                             VALUES (?, ?, ?, 'Inventaire', ?, ?, 'Validé', ?, ?, NOW())");
        $stmt->execute([
            $numero_entree,
            $date,
            $inventaire['id_depot'],
            'INV-' . $inventaire['numero_inventaire'],
            'Ajustement positif suite à l\'inventaire ' . $inventaire['numero_inventaire'],
            $id_user,
            $id_user
        ]);
        $id_entree = $db->lastInsertId();
    }

    // Préparer les tableaux pour les écritures comptables
    $lignesEntree = [];
    $lignesSortie = [];

    // 4. Traiter chaque ligne de l'inventaire
    foreach ($details as $detail) {
        $ecart = $detail['ecart'];

        // Mettre à jour le stock disponible dans le lot
        if ($detail['id_lot']) {
            $stmt = $db->prepare("UPDATE lot_produit SET quantite_disponible = ? WHERE id_lot = ?");
            $stmt->execute([$detail['stock_physique'], $detail['id_lot']]);
        }

        // Si écart négatif (stock physique < stock théorique) et qu'on a créé un document de sortie
        if ($ecart < 0 && $id_sortie) {
            $quantite_sortie = abs($ecart);
            $montant_sortie = $quantite_sortie * $detail['prix_unitaire'];
            $total_sortie += $montant_sortie;

            // Créer l'enregistrement de détail de sortie
            $stmt = $db->prepare("INSERT INTO detail_sortie_stock (id_sortie, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_sortie,
                $detail['id_produit'],
                $quantite_sortie,
                $detail['prix_unitaire'],
                $montant_sortie,
                $id_user
            ]);

            $id_detail_sortie = $db->lastInsertId();

            // Enregistrer le détail de sortie par lot
            if ($detail['id_lot']) {
                $stmt = $db->prepare("INSERT INTO detail_sortie_lot (id_detail_sortie, id_lot, quantite, prix_unitaire, montant_total, id_user_creation) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id_detail_sortie,
                    $detail['id_lot'],
                    $quantite_sortie,
                    $detail['prix_unitaire'],
                    $montant_sortie,
                    $id_user
                ]);
            }

            // Ajouter à notre tableau pour les écritures comptables
            $typeProduit = $detail['type_produit'] ?? 'Produit fini';
            if ($typeProduit != 'Service') {
                $lignesSortie[] = [
                    'id_produit' => $detail['id_produit'],
                    'type_produit' => $typeProduit,
                    'montant_total' => $montant_sortie
                ];
            }
        }

        // Si écart positif (stock physique > stock théorique) et qu'on a créé un document d'entrée
        if ($ecart > 0 && $id_entree) {
            $quantite_entree = $ecart;
            $montant_entree = $quantite_entree * $detail['prix_unitaire'];
            $total_entree += $montant_entree;

            // Créer l'enregistrement de détail d'entrée
            $stmt = $db->prepare("INSERT INTO detail_entree_stock (id_entree, id_produit, quantite, prix_unitaire, montant_total, id_user_creation) 
                                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id_entree,
                $detail['id_produit'],
                $quantite_entree,
                $detail['prix_unitaire'],
                $montant_entree,
                $id_user
            ]);

            $id_detail_entree = $db->lastInsertId();

            // Créer ou mettre à jour un lot pour le produit
            if ($detail['id_lot']) {
                // Si c'est un lot existant, on l'a déjà mis à jour plus haut
            } else {
                // Créer un nouveau lot
                $numero_lot = "LOT-INV-" . date('Ymd') . "-" . $detail['id_produit'];
                $stmt = $db->prepare("INSERT INTO lot_produit (numero_lot, id_produit, id_detail_entree, quantite_initiale, 
                                    quantite_disponible, prix_unitaire_achat, prix_unitaire_vente) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $numero_lot,
                    $detail['id_produit'],
                    $id_detail_entree,
                    $quantite_entree,
                    $quantite_entree,
                    $detail['prix_unitaire'],
                    $detail['prix_unitaire'] * 1.2 // Exemple de majoration de 20%
                ]);
            }

            // Ajouter à notre tableau pour les écritures comptables
            $typeProduit = $detail['type_produit'] ?? 'Produit fini';
            if ($typeProduit != 'Service') {
                $lignesEntree[] = [
                    'id_produit' => $detail['id_produit'],
                    'type_produit' => $typeProduit,
                    'montant_total' => $montant_entree
                ];
            }
        }
    }

    // Mettre à jour les totaux dans les documents
    if ($id_sortie) {
        $stmt = $db->prepare("UPDATE sortie_stock SET montant_total = ? WHERE id_sortie = ?");
        $stmt->execute([$total_sortie, $id_sortie]);
    }

    if ($id_entree) {
        $stmt = $db->prepare("UPDATE entree_stock SET montant_total = ? WHERE id_entree = ?");
        $stmt->execute([$total_entree, $id_entree]);
    }

    // 5. Mettre à jour l'inventaire comme validé
    $stmt = $db->prepare("UPDATE inventaire SET etat = 'Validé', id_user_validation = ?, date_validation = NOW() WHERE id_inventaire = ?");
    $stmt->execute([$id_user, $id_inventaire]);

    // AJOUT: Création des écritures comptables pour les ajustements d'inventaire

    // 1. Vérifier si le journal des stocks existe, sinon le créer
    $stmt = $db->prepare("SELECT id_journal FROM journal_comptable WHERE code_journal = 'STK' LIMIT 1");
    $stmt->execute();
    $journal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$journal) {
        $stmt = $db->prepare("INSERT INTO journal_comptable 
            (code_journal, libelle_journal, description, actif, id_user_creation) 
            VALUES 
            ('STK', 'Journal des stocks', 'Journal pour les opérations de stock', 1, ?)");
        $stmt->execute([$id_user]);
        $idJournal = $db->lastInsertId();
    } else {
        $idJournal = $journal['id_journal'];
    }

    // 2. Vérifier si l'exercice comptable existe pour la date de l'inventaire
    $annee = date('Y', strtotime($date));
    $stmt = $db->prepare("SELECT id_exercice FROM exercice_comptable 
                         WHERE ? BETWEEN date_debut AND date_fin 
                         AND est_cloture = 0 
                         LIMIT 1");
    $stmt->execute([$date]);
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exercice) {
        // Créer un nouvel exercice si nécessaire
        $dateDebut = $annee . '-01-01';
        $dateFin = $annee . '-12-31';

        $stmt = $db->prepare("INSERT INTO exercice_comptable 
            (annee, date_debut, date_fin, est_cloture, id_user_creation) 
            VALUES 
            (?, ?, ?, 0, ?)");
        $stmt->execute([$annee, $dateDebut, $dateFin, $id_user]);
        $idExercice = $db->lastInsertId();
    } else {
        $idExercice = $exercice['id_exercice'];
    }

    // Traiter les écritures comptables pour les entrées d'inventaire
    if (!empty($lignesEntree)) {
        // Générer un numéro d'écriture unique pour l'entrée
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_ecriture, 5) AS UNSIGNED)) as max_num FROM ecriture_comptable");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numeroEcritureEntree = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        // Créer l'écriture comptable pour l'entrée d'inventaire
        $libelleEcritureEntree = "Ajustement positif d'inventaire " . $inventaire['numero_inventaire'];
        $stmt = $db->prepare("INSERT INTO ecriture_comptable
        (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
        est_validee, id_exercice, id_user_creation)
        VALUES
        (?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([
            $numeroEcritureEntree,
            $date,
            $idJournal,
            $libelleEcritureEntree,
            'INV-' . $inventaire['numero_inventaire'],
            $idExercice,
            $id_user
        ]);
        $idEcritureEntree = $db->lastInsertId();

        // Traiter chaque ligne d'entrée pour les écritures comptables
        foreach ($lignesEntree as $ligne) {
            $typeProduit = $ligne['type_produit'];

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

            // Vérifier et créer les comptes nécessaires
            $compteStockId = verifierCreerCompte($db, $compteStockNumero, $compteStockIntitule, 3, 'Actif', $id_user);
            $compteVariationId = verifierCreerCompte($db, $compteVariationNumero, $compteVariationIntitule, 6, 'Charge', $id_user);

            $libelleDetail = "Ajustement positif d'inventaire - Produit ID: " . $ligne['id_produit'];

            // Pour une entrée d'inventaire:
            // Débit au compte de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
            (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
            VALUES
            (?, ?, ?, ?, 0, ?)");
            $stmt->execute([
                $idEcritureEntree,
                $compteStockId,
                $libelleDetail,
                $ligne['montant_total'],
                $id_user
            ]);

            // Crédit au compte de variation de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
            (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
            VALUES
            (?, ?, ?, 0, ?, ?)");
            $stmt->execute([
                $idEcritureEntree,
                $compteVariationId,
                $libelleDetail,
                $ligne['montant_total'],
                $id_user
            ]);
        }
    }

    // Traiter les écritures comptables pour les sorties d'inventaire
    if (!empty($lignesSortie)) {
        // Générer un numéro d'écriture unique pour la sortie
        $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING(numero_ecriture, 5) AS UNSIGNED)) as max_num FROM ecriture_comptable");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $numeroEcritureSortie = 'ECR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

        // Créer l'écriture comptable pour la sortie d'inventaire
        $libelleEcritureSortie = "Ajustement négatif d'inventaire " . $inventaire['numero_inventaire'];
        $stmt = $db->prepare("INSERT INTO ecriture_comptable
        (numero_ecriture, date_ecriture, id_journal, libelle, piece_reference,
        est_validee, id_exercice, id_user_creation)
        VALUES
        (?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([
            $numeroEcritureSortie,
            $date,
            $idJournal,
            $libelleEcritureSortie,
            'INV-' . $inventaire['numero_inventaire'],
            $idExercice,
            $id_user
        ]);
        $idEcritureSortie = $db->lastInsertId();

        // Traiter chaque ligne de sortie pour les écritures comptables
        foreach ($lignesSortie as $ligne) {
            $typeProduit = $ligne['type_produit'];

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
            $compteStockId = verifierCreerCompte($db, $compteStockNumero, $compteStockIntitule, 3, 'Actif', $id_user);
            $compteVariationId = verifierCreerCompte($db, $compteVariationNumero, $compteVariationIntitule, 6, 'Charge', $id_user);

            $libelleDetail = "Ajustement négatif d'inventaire - Produit ID: " . $ligne['id_produit'];

            // Pour une sortie d'inventaire:
            // Débit au compte de variation de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
            (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
            VALUES
            (?, ?, ?, ?, 0, ?)");
            $stmt->execute([
                $idEcritureSortie,
                $compteVariationId,
                $libelleDetail,
                $ligne['montant_total'],
                $id_user
            ]);

            // Crédit au compte de stock
            $stmt = $db->prepare("INSERT INTO ligne_ecriture_comptable
            (id_ecriture, id_compte, libelle, debit, credit, id_user_creation)
            VALUES
            (?, ?, ?, 0, ?, ?)");
            $stmt->execute([
                $idEcritureSortie,
                $compteStockId,
                $libelleDetail,
                $ligne['montant_total'],
                $id_user
            ]);
        }
    }

    // Journalisation de l'action
    $logStmt = $db->prepare("INSERT INTO log_operation 
    (id_user, type_operation, table_concernee, id_enregistrement, description, adresse_ip, navigateur) 
    VALUES 
    (?, 'validation', 'inventaire', ?, ?, ?, ?)");

    $description = "Validation de l'inventaire: {$inventaire['numero_inventaire']} du dépôt {$inventaire['libelle_depot']}";
    $adresse_ip = $_SERVER['REMOTE_ADDR'];
    $navigateur = $_SERVER['HTTP_USER_AGENT'];

    $logStmt->execute([
        $id_user,
        $id_inventaire,
        $description,
        $adresse_ip,
        $navigateur
    ]);

    // Valider la transaction
    $db->commit();

    // Rediriger avec un message de succès
    echo "<script>
    Swal.fire({
        title: 'Succès',
        text: 'L\'inventaire a été validé avec succès.',
        icon: 'success',
        confirmButtonText: 'OK'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../stock/inventaire.view&id=" . $id_inventaire . "';
        }
    });
</script>";
    exit;
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if ($db->inTransaction()) {
        $db->rollBack();
    }

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

// Fonction pour vérifier et créer un compte comptable
function verifierCreerCompte($db, $numeroCompte, $intituleCompte, $classeCompte, $typeCompte, $idUserCreation)
{
    // Vérifier si le compte existe déjà
    $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = ? LIMIT 1");
    $stmt->execute([$numeroCompte]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($compte) {
        return $compte['id_compte'];
    }

    // Vérifier si la classe existe
    $classeNumero = substr($numeroCompte, 0, 1);
    $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = ? AND compte_parent IS NULL LIMIT 1");
    $stmt->execute([$classeNumero]);
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
        (?, ?, ?, NULL, ?, ?)");
        $stmt->execute([
            $classeNumero,
            $intituleClasse,
            $classeCompte,
            $typeCompte,
            $idUserCreation
        ]);
        $compteParent = $db->lastInsertId();
    } else {
        $compteParent = $classe['id_compte'];
    }

    // Créer les comptes intermédiaires si nécessaire
    if (strlen($numeroCompte) > 1) {
        for ($i = 2; $i <= strlen($numeroCompte); $i++) {
            $sousCompteNumero = substr($numeroCompte, 0, $i);

            // Vérifier si ce sous-compte existe déjà
            $stmt = $db->prepare("SELECT id_compte FROM compte_comptable WHERE numero_compte = ? LIMIT 1");
            $stmt->execute([$sousCompteNumero]);
            $sousCompte = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sousCompte && $sousCompteNumero != $numeroCompte) {
                // Créer un intitulé générique pour le compte intermédiaire
                $intituleSousCompte = 'COMPTE ' . $sousCompteNumero;

                $stmt = $db->prepare("INSERT INTO compte_comptable 
                (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
                VALUES 
                (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $sousCompteNumero,
                    $intituleSousCompte,
                    $classeCompte,
                    $compteParent,
                    $typeCompte,
                    $idUserCreation
                ]);
                $compteParent = $db->lastInsertId();
            } else if ($sousCompte) {
                $compteParent = $sousCompte['id_compte'];
            }
        }
    }

    // Créer le compte final s'il n'existe pas déjà
    if ($numeroCompte != substr($numeroCompte, 0, strlen($numeroCompte) - 1)) {
        $stmt = $db->prepare("INSERT INTO compte_comptable 
        (numero_compte, intitule_compte, classe_compte, compte_parent, type_compte, id_user_creation) 
        VALUES 
        (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $numeroCompte,
            $intituleCompte,
            $classeCompte,
            $compteParent,
            $typeCompte,
            $idUserCreation
        ]);

        return $db->lastInsertId();
    }

    return $compteParent;
}
