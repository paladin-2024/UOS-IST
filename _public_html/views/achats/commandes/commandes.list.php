<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des paramètres de filtrage
$fournisseurId = isset($_GET['fournisseur']) ? intval($_GET['fournisseur']) : 0;
$etat = isset($_GET['etat']) ? $_GET['etat'] : '';
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construction de la requête de base
$query = "SELECT cf.*, f.nom_fournisseur 
          FROM commande_fournisseur cf 
          JOIN fournisseur f ON cf.id_fournisseur = f.id_fournisseur 
          WHERE 1=1";
$params = [];

// Ajout des filtres
if ($fournisseurId > 0) {
    $query .= " AND cf.id_fournisseur = :fournisseur_id";
    $params[':fournisseur_id'] = $fournisseurId;
}

if (!empty($etat)) {
    $query .= " AND cf.etat = :etat";
    $params[':etat'] = $etat;
}

if (!empty($dateDebut)) {
    $query .= " AND cf.date_commande >= :date_debut";
    $params[':date_debut'] = $dateDebut;
}

if (!empty($dateFin)) {
    $query .= " AND cf.date_commande <= :date_fin";
    $params[':date_fin'] = $dateFin;
}

// Tri par date décroissante
$query .= " ORDER BY cf.date_commande DESC, cf.id_commande DESC";

// Exécution de la requête
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des fournisseurs pour le filtre
$queryFournisseurs = "SELECT id_fournisseur, nom_fournisseur FROM fournisseur WHERE actif = 1 ORDER BY nom_fournisseur";
$stmtFournisseurs = $db->prepare($queryFournisseurs);
$stmtFournisseurs->execute();
$fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES COMMANDES FOURNISSEURS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Achats</li>
                <li class="breadcrumb-item active">Commandes</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Commandes fournisseurs</h5>
                            <a href="achats/commandes/commandes.add" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Nouvelle commande
                            </a>
                        </div>

                        <!-- Filtres -->
                        <form action="" method="GET" class="row g-3 mb-4">
                            <input type="hidden" name="p" value="achats/commandes/commandes.list">
                            
                            <div class="col-md-3">
                                <label for="fournisseur" class="form-label">Fournisseur</label>
                                <select class="form-select" id="fournisseur" name="fournisseur">
                                    <option value="">Tous les fournisseurs</option>
                                    <?php foreach ($fournisseurs as $f): ?>
                                        <option value="<?= $f['id_fournisseur'] ?>" <?= $fournisseurId == $f['id_fournisseur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['nom_fournisseur']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="etat" class="form-label">État</label>
                                <select class="form-select" id="etat" name="etat">
                                    <option value="">Tous les états</option>
                                    <option value="En cours" <?= $etat == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="Validé" <?= $etat == 'Validé' ? 'selected' : '' ?>>Validé</option>
                                    <option value="Réceptionné" <?= $etat == 'Réceptionné' ? 'selected' : '' ?>>Réceptionné</option>
                                    <option value="Facturé" <?= $etat == 'Facturé' ? 'selected' : '' ?>>Facturé</option>
                                    <option value="Annulé" <?= $etat == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_debut" class="form-label">Date début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $dateDebut ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_fin" class="form-label">Date fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $dateFin ?>">
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                                <a href="achats/commandes/commandes.list" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                </a>
                            </div>
                        </form>

                        <!-- Tableau des commandes -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">N° Commande</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Fournisseur</th>
                                        <th scope="col">Montant TTC</th>
                                        <th scope="col">Date livraison</th>
                                        <th scope="col">État</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($commandes)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune commande trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($commandes as $commande): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($commande['numero_commande']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></td>
                                                <td><?= htmlspecialchars($commande['nom_fournisseur']) ?></td>
                                                <td><?= number_format($commande['montant_ttc'], 2, ',', ' ') ?> USD</td>
                                                <td>
                                                    <?= $commande['date_livraison_prevue'] ? date('d/m/Y', strtotime($commande['date_livraison_prevue'])) : 'N/A' ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    switch ($commande['etat']) {
                                                        case 'En cours':
                                                            echo '<span class="badge bg-warning">En cours</span>';
                                                            break;
                                                        case 'Validé':
                                                            echo '<span class="badge bg-success">Validé</span>';
                                                            break;
                                                        case 'Réceptionné':
                                                            echo '<span class="badge bg-info">Réceptionné</span>';
                                                            break;
                                                        case 'Facturé':
                                                            echo '<span class="badge bg-primary">Facturé</span>';
                                                            break;
                                                        case 'Annulé':
                                                            echo '<span class="badge bg-danger">Annulé</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="achats/commandes/commandes.view&id=<?= $commande['id_commande'] ?>" class="btn btn-sm btn-info" title="Voir">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($commande['etat'] == 'En cours'): ?>
                                                        <a href="achats/commandes/commandes.edit&id=<?= $commande['id_commande'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    $(document).ready(function() {
        // Initialisation de DataTables avec options personnalisées
        $('.datatable').DataTable({
            language: {
                url: 'assets/plugins/datatables/fr-FR.json'
            },
            responsive: true,
            order: [[1, 'desc']], // Tri par date décroissante
            pageLength: 25,
            dom: 'Bfrtip',
            buttons: [
                'copy', 'excel', 'pdf', 'print'
            ]
        });
        
        // Initialiser Select2 si disponible
        if (typeof $.fn.select2 !== 'undefined') {
            $('#fournisseur').select2({
                placeholder: 'Sélectionner un fournisseur',
                allowClear: true
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
