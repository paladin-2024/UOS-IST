<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du fournisseur
$fournisseurId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($fournisseurId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Fournisseur non trouvé'
        }).then(() => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}

// Récupération des détails du fournisseur
$query = "SELECT f.*, cc.numero_compte, cc.intitule_compte 
          FROM fournisseur f 
          LEFT JOIN compte_comptable cc ON f.id_compte_comptable = cc.id_compte 
          WHERE f.id_fournisseur = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $fournisseurId, PDO::PARAM_INT);
$stmt->execute();
$fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fournisseur) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Fournisseur non trouvé'
        }).then(() => {
            window.location.href = '../fournisseurs/fournisseurs.list';
        });
    </script>";
    exit;
}

// Récupération des demandes de prix
$queryDemandes = "SELECT id_demande_prix, numero_demande, date_demande, etat 
                 FROM demande_prix 
                 WHERE id_fournisseur = :id_fournisseur 
                 ORDER BY date_demande DESC 
                 LIMIT 5";
$stmtDemandes = $db->prepare($queryDemandes);
$stmtDemandes->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
$stmtDemandes->execute();
$demandes = $stmtDemandes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des commandes
$queryCommandes = "SELECT id_commande, numero_commande, date_commande, montant_ttc, etat 
                  FROM commande_fournisseur 
                  WHERE id_fournisseur = :id_fournisseur 
                  ORDER BY date_commande DESC 
                  LIMIT 5";
$stmtCommandes = $db->prepare($queryCommandes);
$stmtCommandes->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
$stmtCommandes->execute();
$commandes = $stmtCommandes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des factures
$queryFactures = "SELECT id_facture, numero_facture, date_facture, montant_ttc, montant_paye, solde, etat 
                 FROM facture_fournisseur 
                 WHERE id_fournisseur = :id_fournisseur 
                 ORDER BY date_facture DESC 
                 LIMIT 5";
$stmtFactures = $db->prepare($queryFactures);
$stmtFactures->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
$stmtFactures->execute();
$factures = $stmtFactures->fetchAll(PDO::FETCH_ASSOC);

// Récupération des paiements
$queryPaiements = "SELECT id_paiement, numero_paiement, date_paiement, montant, reference_paiement 
                  FROM paiement_fournisseur 
                  WHERE id_fournisseur = :id_fournisseur 
                  ORDER BY date_paiement DESC 
                  LIMIT 5";
$stmtPaiements = $db->prepare($queryPaiements);
$stmtPaiements->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
$stmtPaiements->execute();
$paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);

// Récupération des produits du fournisseur
$queryProduits = "SELECT pf.*, p.code_produit, p.libelle_produit 
                 FROM produit_fournisseur pf 
                 JOIN produit p ON pf.id_produit = p.id_produit
                 WHERE pf.id_fournisseur = :id_fournisseur";
$stmtProduits = $db->prepare($queryProduits);
$stmtProduits->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
$stmtProduits->execute();
$produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU FOURNISSEUR</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item"><a href="fournisseurs/fournisseurs.list">Fournisseurs</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
            </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <div class="profile-img">
                            <i class="bi bi-building" style="font-size: 5rem;"></i>
                        </div>
                        <h2><?= htmlspecialchars($fournisseur['nom_fournisseur']) ?></h2>
                        <h3>Fournisseur</h3>
                        <div class="social-links mt-2">
                            <?php if (!empty($fournisseur['telephone'])): ?>
                                <a href="tel:<?= $fournisseur['telephone'] ?>" class="phone"><i class="bi bi-telephone"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($fournisseur['email'])): ?>
                                <a href="mailto:<?= $fournisseur['email'] ?>" class="email"><i class="bi bi-envelope"></i></a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 d-flex justify-content-center">
                            <a href="fournisseurs/fournisseurs.edit&id=<?= $fournisseur['id_fournisseur'] ?>" class="btn btn-warning mx-1">
                                <i class="bi bi-pencil-square"></i> Modifier
                            </a>
                            <button onclick="confirmDelete(<?= $fournisseur['id_fournisseur'] ?>)" class="btn btn-danger mx-1">
                                <i class="bi bi-trash"></i> Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title">Informations détaillées</h5>
                        
                        <div class="tab-content">
                            <div class="tab-pane fade show active profile-overview">
                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Code fournisseur</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['code_fournisseur']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Nom</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['nom_fournisseur']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Adresse</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['adresse'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Téléphone</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['telephone'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Email</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['email'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">NIF</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['nif'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">RCCM</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($fournisseur['rccm'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Compte comptable</div>
                                    <div class="col-lg-9 col-md-8"><?= $fournisseur['numero_compte'] ?> - <?= htmlspecialchars($fournisseur['intitule_compte']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Délai paiement</div>
                                    <div class="col-lg-9 col-md-8"><?= $fournisseur['delai_paiement'] ?> jours</div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Statut</div>
                                    <div class="col-lg-9 col-md-8">
                                        <?= $fournisseur['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglets pour afficher les opérations du fournisseur -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Opérations du fournisseur</h5>

                        <!-- Onglets -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#demandes-tab" type="button" role="tab">Demandes de prix</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#commandes-tab" type="button" role="tab">Commandes</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#factures-tab" type="button" role="tab">Factures</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#paiements-tab" type="button" role="tab">Paiements</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#produits-tab" type="button" role="tab">Produits</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Demandes de prix -->
                            <div class="tab-pane fade show active" id="demandes-tab" role="tabpanel">
                                <?php if (empty($demandes)): ?>
                                    <div class="alert alert-info mt-3">Aucune demande de prix disponible pour ce fournisseur.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered mt-3">
                                    <thead>
                                        <tr>
                                            <th>N° Demande</th>
                                            <th>Date</th>
                                            <th>État</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demandes as $d): ?>
                                        <tr>
                                            <td><?= $d['numero_demande'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['date_demande'])) ?></td>
                                            <td>
                                                <?php
                                                switch ($d['etat']) {
                                                    case 'En cours': echo '<span class="badge bg-warning">En cours</span>'; break;
                                                    case 'Validé': echo '<span class="badge bg-success">Validé</span>'; break;
                                                    case 'Transformé': echo '<span class="badge bg-info">Transformé</span>'; break;
                                                    case 'Annulé': echo '<span class="badge bg-danger">Annulé</span>'; break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="achats/demandes.view&id=<?= $d['id_demande_prix'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="achats/demandes.list&fournisseur=<?= $fournisseurId ?>" class="btn btn-primary">Voir toutes les demandes</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Commandes -->
                            <div class="tab-pane fade" id="commandes-tab" role="tabpanel">
                                <?php if (empty($commandes)): ?>
                                    <div class="alert alert-info mt-3">Aucune commande disponible pour ce fournisseur.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered mt-3">
                                    <thead>
                                        <tr>
                                            <th>N° Commande</th>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>État</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commandes as $c): ?>
                                        <tr>
                                            <td><?= $c['numero_commande'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($c['date_commande'])) ?></td>
                                            <td><?= number_format($c['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                            <td>
                                                <?php
                                                switch ($c['etat']) {
                                                    case 'En cours': echo '<span class="badge bg-warning">En cours</span>'; break;
                                                    case 'Validé': echo '<span class="badge bg-success">Validé</span>'; break;
                                                    case 'Réceptionné': echo '<span class="badge bg-info">Réceptionné</span>'; break;
                                                    case 'Facturé': echo '<span class="badge bg-primary">Facturé</span>'; break;
                                                    case 'Annulé': echo '<span class="badge bg-danger">Annulé</span>'; break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="achats/commandes.view&id=<?= $c['id_commande'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="achats/commandes.list&fournisseur=<?= $fournisseurId ?>" class="btn btn-primary">Voir toutes les commandes</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Factures -->
                            <div class="tab-pane fade" id="factures-tab" role="tabpanel">
                                <?php if (empty($factures)): ?>
                                    <div class="alert alert-info mt-3">Aucune facture disponible pour ce fournisseur.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered mt-3">
                                    <thead>
                                        <tr>
                                            <th>N° Facture</th>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Payé</th>
                                            <th>Solde</th>
                                            <th>État</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($factures as $f): ?>
                                        <tr>
                                            <td><?= $f['numero_facture'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($f['date_facture'])) ?></td>
                                            <td><?= number_format($f['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                            <td><?= number_format($f['montant_paye'], 2, ',', ' ') ?> USD</td>
                                            <td><?= number_format($f['solde'], 2, ',', ' ') ?> USD</td>
                                            <td>
                                                <?php
                                                switch ($f['etat']) {
                                                    case 'En cours': echo '<span class="badge bg-warning">En cours</span>'; break;
                                                    case 'Validé': echo '<span class="badge bg-success">Validé</span>'; break;
                                                    case 'Payé partiellement': echo '<span class="badge bg-info">Payé partiellement</span>'; break;
                                                    case 'Payé': echo '<span class="badge bg-primary">Payé</span>'; break;
                                                    case 'Annulé': echo '<span class="badge bg-danger">Annulé</span>'; break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="achats/factures.view&id=<?= $f['id_facture'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="achats/factures.list&fournisseur=<?= $fournisseurId ?>" class="btn btn-primary">Voir toutes les factures</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Paiements -->
                            <div class="tab-pane fade" id="paiements-tab" role="tabpanel">
                                <?php if (empty($paiements)): ?>
                                    <div class="alert alert-info mt-3">Aucun paiement disponible pour ce fournisseur.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered mt-3">
                                    <thead>
                                        <tr>
                                            <th>N° Paiement</th>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Référence</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements as $p): ?>
                                        <tr>
                                            <td><?= $p['numero_paiement'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                            <td><?= number_format($p['montant'], 2, ',', ' ') ?> USD</td>
                                            <td><?= htmlspecialchars($p['reference_paiement'] ?? 'N/A') ?></td>
                                            <td>
                                                <a href="achats/paiements.view&id=<?= $p['id_paiement'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="achats/paiements.list&fournisseur=<?= $fournisseurId ?>" class="btn btn-primary">Voir tous les paiements</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Produits -->
                            <div class="tab-pane fade" id="produits-tab" role="tabpanel">
                                <div class="d-flex justify-content-end mt-3 mb-3">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProduitModal">
                                        <i class="bi bi-plus-circle"></i> Ajouter un produit
                                    </button>
                                </div>
                                
                                <?php if (empty($produits)): ?>
                                    <div class="alert alert-info">Aucun produit associé à ce fournisseur.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Produit</th>
                                            <th>Prix d'achat</th>
                                            <th>Délai (jours)</th>
                                            <th>Principal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produits as $p): ?>
                                        <tr>
                                            <td><?= $p['code_produit'] ?></td>
                                            <td><?= htmlspecialchars($p['libelle_produit']) ?></td>
                                            <td><?= number_format($p['prix_achat'], 2, ',', ' ') ?> USD</td>
                                            <td><?= $p['delai_livraison'] ?? 'N/A' ?></td>
                                            <td><?= $p['est_fournisseur_principal'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning edit-produit-btn" 
                                                    data-id="<?= $p['id_produit_fournisseur'] ?>"
                                                    data-produit-id="<?= $p['id_produit'] ?>"
                                                    data-produit-nom="<?= htmlspecialchars($p['libelle_produit']) ?>"
                                                    data-prix="<?= $p['prix_achat'] ?>"
                                                    data-delai="<?= $p['delai_livraison'] ?>"
                                                    data-principal="<?= $p['est_fournisseur_principal'] ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-produit-btn" data-id="<?= $p['id_produit_fournisseur'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un produit au fournisseur -->
<div class="modal fade" id="addProduitModal" tabindex="-1" aria-labelledby="addProduitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProduitModalLabel">Ajouter un produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/add_produit_fournisseur.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_fournisseur" value="<?= $fournisseurId ?>">
                    
                    <div class="mb-3">
                        <label for="id_produit" class="form-label">Produit</label>
                        <select class="form-select" id="id_produit" name="id_produit" required>
                            <option value="">Sélectionner un produit</option>
                            <?php
                            // Récupérer les produits non déjà associés à ce fournisseur
                            $queryAllProduits = "SELECT p.id_produit, p.code_produit, p.libelle_produit 
                                              FROM produit p 
                                              WHERE p.id_produit NOT IN (
                                                  SELECT pf.id_produit FROM produit_fournisseur pf 
                                                  WHERE pf.id_fournisseur = :id_fournisseur
                                              )
                                              AND p.actif = 1
                                              ORDER BY p.libelle_produit";
                            $stmtAllProduits = $db->prepare($queryAllProduits);
                            $stmtAllProduits->bindParam(':id_fournisseur', $fournisseurId, PDO::PARAM_INT);
                            $stmtAllProduits->execute();
                            $allProduits = $stmtAllProduits->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($allProduits as $prod) {
                                echo "<option value='{$prod['id_produit']}'>{$prod['code_produit']} - " . htmlspecialchars($prod['libelle_produit']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prix_achat" class="form-label">Prix d'achat (USD)</label>
                        <input type="number" class="form-control" id="prix_achat" name="prix_achat" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delai_livraison" class="form-label">Délai de livraison (jours)</label>
                        <input type="number" class="form-control" id="delai_livraison" name="delai_livraison" min="0">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="est_fournisseur_principal" name="est_fournisseur_principal">
                        <label class="form-check-label" for="est_fournisseur_principal">Fournisseur principal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour modifier un produit -->
<div class="modal fade" id="editProduitModal" tabindex="-1" aria-labelledby="editProduitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProduitModalLabel">Modifier un produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/update_produit_fournisseur.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_produit_fournisseur" id="edit_id_produit_fournisseur">
                    <input type="hidden" name="id_fournisseur" value="<?= $fournisseurId ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Produit</label>
                        <input type="text" class="form-control" id="edit_produit_nom" readonly>
                        <input type="hidden" name="id_produit" id="edit_id_produit">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_prix_achat" class="form-label">Prix d'achat (USD)</label>
                        <input type="number" class="form-control" id="edit_prix_achat" name="prix_achat" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_delai_livraison" class="form-label">Délai de livraison (jours)</label>
                        <input type="number" class="form-control" id="edit_delai_livraison" name="delai_livraison" min="0">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_est_fournisseur_principal" name="est_fournisseur_principal">
                        <label class="form-check-label" for="edit_est_fournisseur_principal">Fournisseur principal</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmDelete(idFournisseur) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Cette action ne peut pas être annulée!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_fournisseur.php?id=' + idFournisseur;
            }
        });
    }

    // Gestion de la modification d'un produit
    $('.edit-produit-btn').on('click', function() {
        const id = $(this).data('id');
        const produitId = $(this).data('produit-id');
        const produitNom = $(this).data('produit-nom');
        const prix = $(this).data('prix');
        const delai = $(this).data('delai');
        const principal = $(this).data('principal') == 1;
        
        $('#edit_id_produit_fournisseur').val(id);
        $('#edit_id_produit').val(produitId);
        $('#edit_produit_nom').val(produitNom);
        $('#edit_prix_achat').val(prix);
        $('#edit_delai_livraison').val(delai);
        $('#edit_est_fournisseur_principal').prop('checked', principal);
        
        $('#editProduitModal').modal('show');
    });

        // Gestion de la suppression d'un produit
        $('.delete-produit-btn').on('click', function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Voulez-vous supprimer ce produit de la liste des produits de ce fournisseur?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_produit_fournisseur.php?id=' + id + '&fournisseur=<?= $fournisseurId ?>';
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
