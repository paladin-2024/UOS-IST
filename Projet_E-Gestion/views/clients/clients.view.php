<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération de l'ID du client
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clientId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Client non trouvé'
        }).then(() => {
            window.location.href = '../clients/clients.list';
        });
    </script>";
    exit;
}

// Récupération des détails du client
$query = "SELECT c.*, cc.numero_compte, cc.intitule_compte 
          FROM client c 
          LEFT JOIN compte_comptable cc ON c.id_compte_comptable = cc.id_compte 
          WHERE c.id_client = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Client non trouvé'
        }).then(() => {
            window.location.href = '../clients/clients.list';
        });
    </script>";
    exit;
}

// Récupération des devis du client
$queryDevis = "SELECT id_devis, numero_devis, date_devis, montant_ttc, etat 
               FROM devis 
               WHERE id_client = :id_client 
               ORDER BY date_devis DESC 
               LIMIT 5";
$stmtDevis = $db->prepare($queryDevis);
$stmtDevis->bindParam(':id_client', $clientId, PDO::PARAM_INT);
$stmtDevis->execute();
$devis = $stmtDevis->fetchAll(PDO::FETCH_ASSOC);

// Récupération des factures du client
$queryFactures = "SELECT id_facture, numero_facture, date_facture, montant_ttc, montant_paye, solde, etat 
                 FROM facture_client 
                 WHERE id_client = :id_client 
                 ORDER BY date_facture DESC 
                 LIMIT 5";
$stmtFactures = $db->prepare($queryFactures);
$stmtFactures->bindParam(':id_client', $clientId, PDO::PARAM_INT);
$stmtFactures->execute();
$factures = $stmtFactures->fetchAll(PDO::FETCH_ASSOC);

// Récupération des commandes du client
$queryCommandes = "SELECT id_commande, numero_commande, date_commande, montant_ttc, etat 
                  FROM commande_client 
                  WHERE id_client = :id_client 
                  ORDER BY date_commande DESC 
                  LIMIT 5";
$stmtCommandes = $db->prepare($queryCommandes);
$stmtCommandes->bindParam(':id_client', $clientId, PDO::PARAM_INT);
$stmtCommandes->execute();
$commandes = $stmtCommandes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des paiements du client
$queryPaiements = "SELECT id_paiement, numero_paiement, date_paiement, montant, reference_paiement 
                  FROM paiement_client 
                  WHERE id_client = :id_client 
                  ORDER BY date_paiement DESC 
                  LIMIT 5";
$stmtPaiements = $db->prepare($queryPaiements);
$stmtPaiements->bindParam(':id_client', $clientId, PDO::PARAM_INT);
$stmtPaiements->execute();
$paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU CLIENT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Ventes</li>
                <li class="breadcrumb-item"><a href="clients/clients.list">Clients</a></li>
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
                            <i class="bi bi-person-circle" style="font-size: 5rem;"></i>
                        </div>
                        <h2><?= htmlspecialchars($client['nom_client']) ?></h2>
                        <h3><?= $client['type_client'] ?></h3>
                        <div class="social-links mt-2">
                            <?php if (!empty($client['telephone'])): ?>
                                <a href="tel:<?= $client['telephone'] ?>" class="phone"><i class="bi bi-telephone"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($client['email'])): ?>
                                <a href="mailto:<?= $client['email'] ?>" class="email"><i class="bi bi-envelope"></i></a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3 d-flex justify-content-center">
                            <a href="clients/clients.edit&id=<?= $client['id_client'] ?>" class="btn btn-warning mx-1">
                                <i class="bi bi-pencil-square"></i> Modifier
                            </a>
                            <button onclick="confirmDelete(<?= $client['id_client'] ?>)" class="btn btn-danger mx-1">
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
                                    <div class="col-lg-3 col-md-4 label">Code client</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['code_client']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Type</div>
                                    <div class="col-lg-9 col-md-8"><?= $client['type_client'] ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Nom</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['nom_client']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Adresse</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['adresse'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Téléphone</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['telephone'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                <div class="col-lg-3 col-md-4 label">Email</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['email'] ?? 'N/A') ?></div>
                                </div>

                                <?php if ($client['type_client'] == 'Entreprise'): ?>
                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">NIF</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['nif'] ?? 'N/A') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">RCCM</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($client['rccm'] ?? 'N/A') ?></div>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Compte comptable</div>
                                    <div class="col-lg-9 col-md-8"><?= $client['numero_compte'] ?> - <?= htmlspecialchars($client['intitule_compte']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Plafond crédit</div>
                                    <div class="col-lg-9 col-md-8"><?= number_format($client['plafond_credit'], 2, ',', ' ') ?> USD</div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Délai paiement</div>
                                    <div class="col-lg-9 col-md-8"><?= $client['delai_paiement'] ?> jours</div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Statut</div>
                                    <div class="col-lg-9 col-md-8">
                                        <?= $client['actif'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglets pour afficher les opérations du client -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Opérations du client</h5>

                        <!-- Onglets -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#devis-tab" type="button" role="tab">Devis</button>
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
                        </ul>

                        <div class="tab-content">
                            <!-- Devis -->
                            <div class="tab-pane fade show active" id="devis-tab" role="tabpanel">
                                <?php if (empty($devis)): ?>
                                    <div class="alert alert-info mt-3">Aucun devis disponible pour ce client.</div>
                                <?php else: ?>
                                <table class="table table-striped table-bordered mt-3">
                                    <thead>
                                        <tr>
                                            <th>N° Devis</th>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>État</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devis as $d): ?>
                                        <tr>
                                            <td><?= $d['numero_devis'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['date_devis'])) ?></td>
                                            <td><?= number_format($d['montant_ttc'], 2, ',', ' ') ?> USD</td>
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
                                                <a href="ventes/devis.view&id=<?= $d['id_devis'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="ventes/devis.list&client=<?= $clientId ?>" class="btn btn-primary">Voir tous les devis</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Commandes -->
                            <div class="tab-pane fade" id="commandes-tab" role="tabpanel">
                                <?php if (empty($commandes)): ?>
                                    <div class="alert alert-info mt-3">Aucune commande disponible pour ce client.</div>
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
                                                    case 'Livré': echo '<span class="badge bg-info">Livré</span>'; break;
                                                    case 'Facturé': echo '<span class="badge bg-primary">Facturé</span>'; break;
                                                    case 'Annulé': echo '<span class="badge bg-danger">Annulé</span>'; break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="ventes/commandes.view&id=<?= $c['id_commande'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="ventes/commandes.list&client=<?= $clientId ?>" class="btn btn-primary">Voir toutes les commandes</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Factures -->
                            <div class="tab-pane fade" id="factures-tab" role="tabpanel">
                                <?php if (empty($factures)): ?>
                                    <div class="alert alert-info mt-3">Aucune facture disponible pour ce client.</div>
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
                                                <a href="ventes/factures.view&id=<?= $f['id_facture'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="ventes/factures.list&client=<?= $clientId ?>" class="btn btn-primary">Voir toutes les factures</a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Paiements -->
                            <div class="tab-pane fade" id="paiements-tab" role="tabpanel">
                                <?php if (empty($paiements)): ?>
                                    <div class="alert alert-info mt-3">Aucun paiement disponible pour ce client.</div>
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
                                                <a href="ventes/paiements.view&id=<?= $p['id_paiement'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-center mt-3">
                                    <a href="ventes/paiements.list&client=<?= $clientId ?>" class="btn btn-primary">Voir tous les paiements</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function confirmDelete(idClient) {
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
                window.location.href = 'controller/delete_client.php?id=' + idClient;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
