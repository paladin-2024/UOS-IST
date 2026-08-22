<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du produit
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Produit non trouvé'
        }).then(() => {
            window.location.href = '../produits/produits.list';
        });
    </script>";
    exit;
}

// Récupération des détails du produit
$query = "SELECT p.*, c.libelle_categorie, us.libelle_unite AS unite_stockage, 
           uv.libelle_unite AS unite_vente, cc.numero_compte, cc.intitule_compte
          FROM produit p 
          LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
          LEFT JOIN unite_mesure us ON p.id_unite_stockage = us.id_unite
          LEFT JOIN unite_mesure uv ON p.id_unite_vente = uv.id_unite
          LEFT JOIN compte_comptable cc ON p.id_compte_comptable = cc.id_compte
          WHERE p.id_produit = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $productId, PDO::PARAM_INT);
$stmt->execute();
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Produit non trouvé'
        }).then(() => {
            window.location.href = '../produits/produits.list';
        });
    </script>";
    exit;
}

// Récupération des fournisseurs du produit
$queryFournisseurs = "SELECT pf.*, f.nom_fournisseur 
                      FROM produit_fournisseur pf
                      JOIN fournisseur f ON pf.id_fournisseur = f.id_fournisseur
                      WHERE pf.id_produit = :id_produit
                      ORDER BY pf.est_fournisseur_principal DESC";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->bindParam(':id_produit', $productId, PDO::PARAM_INT);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

// Récupération des documents associés au produit
$queryDocuments = "SELECT * FROM document_produit WHERE id_produit = :id_produit ORDER BY date_creation DESC";
$stmtDocuments = $db->prepare($queryDocuments);
$stmtDocuments->bindParam(':id_produit', $productId, PDO::PARAM_INT);
$stmtDocuments->execute();
$documents = $stmtDocuments->fetchAll(PDO::FETCH_ASSOC);

// Chemin de l'image
$imagePath = !empty($produit['image_produit']) 
    ? './uploads/produits/' . $produit['image_produit'] 
    : './uploads/cube.jpg';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU PRODUIT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="produits/produits.list">Produits</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($produit['libelle_produit']) ?>" class="img-fluid rounded">
                        <h2 class="mt-3"><?= htmlspecialchars($produit['libelle_produit']) ?></h2>
                        <div class="mt-2">
                            <span class="badge <?= $produit['actif'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $produit['actif'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </div>
                        <h3 class="mt-2">Code: <?= htmlspecialchars($produit['code_produit']) ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="produits/produits.edit&id=<?= $produit['id_produit'] ?>" class="btn btn-primary me-2">
                                <i class="bi bi-pencil-square"></i> Modifier
                            </a>
                            <a href="produits/produits.list" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                        </div>

                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#infos-generales" aria-selected="true" role="tab">Infos générales</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stock-config" aria-selected="false" role="tab" tabindex="-1">Stock</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#detail-lots" aria-selected="false" role="tab" tabindex="-1">Lots</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fournisseurs" aria-selected="false" role="tab" tabindex="-1">Fournisseurs</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" aria-selected="false" role="tab" tabindex="-1">Documents</button>
                            </li>
                        </ul>


                        <div class="tab-content pt-2">
                        <div class="tab-pane fade show active" id="infos-generales" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <h5>Description</h5>
                                            <p><?= nl2br(htmlspecialchars($produit['description'] ?? 'Non spécifié')) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <h5>Catégorie</h5>
                                            <p><?= htmlspecialchars($produit['libelle_categorie'] ?? 'Non spécifiée') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Type de produit</h5>
                                            <p><?= htmlspecialchars($produit['type_produit']) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Famille</h5>
                                            <p><?= htmlspecialchars($produit['famille'] ?? 'Non spécifiée') ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Marge bénéficiaire</h5>
                                            <p><?= $produit['marge_beneficiaire'] ?> %</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Poids</h5>
                                            <p><?= $produit['poids'] ? $produit['poids'].' kg' : 'Non spécifié' ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Volume</h5>
                                            <p><?= $produit['volume'] ? $produit['volume'].' m³' : 'Non spécifié' ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Date de création</h5>
                                            <p><?= date('d/m/Y H:i', strtotime($produit['date_creation'])) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <h5>Compte comptable</h5>
                                            <p><?= $produit['numero_compte'] ?> - <?= htmlspecialchars($produit['intitule_compte']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="stock-config" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <h5>Unité de stockage</h5>
                                            <p><?= htmlspecialchars($produit['unite_stockage']) ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <h5>Unité de vente</h5>
                                            <p><?= htmlspecialchars($produit['unite_vente']) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Conditionnement</h5>
                                            <p><?= $produit['conditionnement'] ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Suivi du stock</h5>
                                            <p><?= $produit['est_stock_suivi'] ? 'Oui' : 'Non' ?></p>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <h5>Suivi de péremption</h5>
                                            <p><?= $produit['est_peremption_suivi'] ? 'Oui' : 'Non' ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Informations sur le stock actuel -->
                                <hr>
                                <h4>Stock actuel</h4>
                                <?php
                                // Requête pour obtenir le stock par dépôt
                                $queryStock = "SELECT d.libelle_depot, 
                                    SUM(l.quantite_disponible) as stock_total,
                                    MIN(l.date_peremption) as date_peremption_proche
                                    FROM lot_produit l
                                    JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                                    JOIN entree_stock es ON des.id_entree = es.id_entree
                                    JOIN depot d ON es.id_depot = d.id_depot
                                    WHERE l.id_produit = :id_produit 
                                    AND l.quantite_disponible > 0
                                    AND es.etat = 'Validé'  -- Ajoute cette condition pour exclure les entrées annulées
                                    GROUP BY d.id_depot
                                    ORDER BY d.libelle_depot";

                                $stmtStock = $db->prepare($queryStock);
                                $stmtStock->bindParam(':id_produit', $productId, PDO::PARAM_INT);
                                $stmtStock->execute();
                                $stocks = $stmtStock->fetchAll(PDO::FETCH_ASSOC);
                                
                                if(count($stocks) > 0) {
                                    echo '<div class="table-responsive mt-3">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Dépôt</th>
                                                        <th>Stock disponible</th>
                                                        <th>Date de péremption la plus proche</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
                                    
                                    foreach($stocks as $stock) {
                                        echo '<tr>
                                                <td>'.htmlspecialchars($stock['libelle_depot']).'</td>
                                                <td>'.number_format($stock['stock_total'], 2).' '.htmlspecialchars($produit['unite_stockage']).'</td>
                                                <td>'.($stock['date_peremption_proche'] ? date('d/m/Y', strtotime($stock['date_peremption_proche'])) : 'N/A').'</td>
                                              </tr>';
                                    }
                                    
                                    echo '</tbody></table></div>';
                                } else {
                                    echo '<div class="alert alert-info mt-3">Aucun stock disponible pour ce produit.</div>';
                                }
                                ?>
                            </div>

                            <div class="tab-pane fade" id="fournisseurs" role="tabpanel">
                                <?php if(count($fournisseurs) > 0) : ?>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Fournisseur</th>
                                                    <th>Prix d'achat</th>
                                                    <th>Délai de livraison</th>
                                                    <th>Principal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($fournisseurs as $fournisseur) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fournisseur['nom_fournisseur']) ?></td>
                                                    <td><?= number_format($fournisseur['prix_achat'], 2) ?> USD</td>
                                                    <td><?= $fournisseur['delai_livraison'] ? $fournisseur['delai_livraison'].' jours' : 'Non spécifié' ?></td>
                                                    <td><?= $fournisseur['est_fournisseur_principal'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <div class="alert alert-info mt-3">Aucun fournisseur associé à ce produit.</div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="documents" role="tabpanel">
                                <?php if(count($documents) > 0) : ?>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Titre</th>
                                                    <th>Type</th>
                                                    <th>Date d'ajout</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($documents as $document) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($document['titre_document']) ?></td>
                                                    <td><?= htmlspecialchars($document['type_document']) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($document['date_creation'])) ?></td>
                                                    <td>
                                                        <a href="<?= htmlspecialchars($document['chemin_fichier']) ?>" class="btn btn-sm btn-info" target="_blank">
                                                            <i class="bi bi-eye"></i> Voir
                                                        </a>
                                                        <a href="controller/download_document.php?id=<?= $document['id_document'] ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-download"></i> Télécharger
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <div class="alert alert-info mt-3">Aucun document associé à ce produit.</div>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="detail-lots" role="tabpanel">
                                <h4 class="mt-3">Détail des lots disponibles</h4>
                                <?php
                                // Requête pour obtenir les détails de tous les lots du produit
                                $queryLots = "SELECT l.id_lot, l.numero_lot, l.quantite_initiale, l.quantite_disponible, 
                                            l.prix_unitaire_achat, l.prix_unitaire_vente, l.date_peremption, l.date_creation,
                                            d.libelle_depot, es.numero_entree, es.date_entree
                                            FROM lot_produit l
                                            JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                                            JOIN entree_stock es ON des.id_entree = es.id_entree
                                            JOIN depot d ON es.id_depot = d.id_depot
                                            WHERE l.id_produit = :id_produit 
                                            AND l.quantite_disponible > 0
                                            AND es.etat = 'Validé'
                                            ORDER BY d.libelle_depot, l.date_peremption";
                                            
                                $stmtLots = $db->prepare($queryLots);
                                $stmtLots->bindParam(':id_produit', $productId, PDO::PARAM_INT);
                                $stmtLots->execute();
                                $lots = $stmtLots->fetchAll(PDO::FETCH_ASSOC);
                                
                                if(count($lots) > 0) {
                                    echo '<div class="table-responsive mt-3">
                                            <table class="table table-bordered table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Dépôt</th>
                                                        <th>N° Lot</th>
                                                        <th>N° Entrée</th>
                                                        <th>Date entrée</th>
                                                        <th>Quantité initiale</th>
                                                        <th>Quantité disponible</th>
                                                        <th>Prix achat</th>
                                                        <th>Prix vente</th>
                                                        <th>Date péremption</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
                                    
                                    foreach($lots as $lot) {
                                        $date_peremption = $lot['date_peremption'] ? date('d/m/Y', strtotime($lot['date_peremption'])) : 'N/A';
                                        echo '<tr>
                                                <td>'.htmlspecialchars($lot['libelle_depot']).'</td>
                                                <td>'.htmlspecialchars($lot['numero_lot']).'</td>
                                                <td>'.htmlspecialchars($lot['numero_entree']).'</td>
                                                <td>'.date('d/m/Y', strtotime($lot['date_entree'])).'</td>
                                                <td>'.number_format($lot['quantite_initiale'], 2).' '.htmlspecialchars($produit['unite_stockage']).'</td>
                                                <td>'.number_format($lot['quantite_disponible'], 2).' '.htmlspecialchars($produit['unite_stockage']).'</td>
                                                <td>'.number_format($lot['prix_unitaire_achat'], 2).' USD</td>
                                                <td>'.number_format($lot['prix_unitaire_vente'], 2).' USD</td>
                                                <td>'.$date_peremption.'</td>
                                            </tr>';
                                    }
                                    
                                    echo '</tbody></table></div>';
                                } else {
                                    echo '<div class="alert alert-info mt-3">Aucun lot disponible pour ce produit.</div>';
                                }
                                ?>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
