<?php 
include "./views/include/header.php"; 

// Récupérer l'ID du compte
$id_compte_bancaire = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier si l'ID est valide
if ($id_compte_bancaire <= 0) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'ID de compte bancaire invalide',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'finance/config_comptes_bancaires';
        });
    </script>";
    exit;
}

// Récupérer les informations du compte
$db = Connexion::getInstance()->getPDO();
$query = "SELECT * FROM comptes_bancaires WHERE id = :id_compte_bancaire";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_compte_bancaire', $id_compte_bancaire, PDO::PARAM_INT);
$stmt->execute();
$compte = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si le compte existe
if (!$compte) {
    echo "<script>
        Swal.fire({
            title: 'Erreur',
            text: 'Compte bancaire non trouvé',
            icon: 'error',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = 'finance/config_comptes_bancaires';
        });
    </script>";
    exit;
}

// Récupérer les dernières transactions liées à ce compte
$query_transactions = "SELECT t.*, c.designation as categorie_nom  
                     FROM transactions t
                     LEFT JOIN categories_budget c ON t.categorie_id = c.id
                     WHERE t.source = 'Banque' AND t.source_id = :id_compte_bancaire
                     ORDER BY t.date_transaction DESC, t.id DESC
                     LIMIT 10";
$stmt_transactions = $db->prepare($query_transactions);
$stmt_transactions->bindParam(':id_compte_bancaire', $id_compte_bancaire, PDO::PARAM_INT);
$stmt_transactions->execute();
$transactions = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails du Compte Bancaire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item"><a href="finance/config_comptes_bancaires">Comptes Bancaires</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
            <div class="card">
    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
        <!-- Icône Bootstrap au lieu de l'image statique -->
        <div class="icon-box mb-3">
            <i class="bi bi-bank fs-1 text-primary bg-light-primary p-3 rounded-circle"></i>
        </div>
        
<center><h2 class="mb-2"><?= htmlspecialchars($compte['intitule_compte']) ?></h2></center>
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-credit-card-2-front me-2 text-muted"></i>
            <h3 class="mb-0 text-muted"><?= htmlspecialchars($compte['numero_compte']) ?></h3>
        </div>
        
        <div class="mt-2 mb-3 border-top border-bottom py-3 w-100 text-center">
            <span class="text-muted small d-block mb-1">Solde actuel</span>
            <h4 class="text-primary fw-bold">
                <i class="bi bi-cash me-1"></i>
                <?= number_format($compte['solde_actuel'], 2, ',', ' ') ?> 
                <span class="text-secondary"><?= htmlspecialchars($compte['devise']) ?></span>
            </h4>
        </div>
        
        <div class="mt-2">
            <?php if ($compte['est_actif'] == 1): ?>
                <span class="badge bg-success py-2 px-3">
                    <i class="bi bi-check-circle me-1"></i> Actif
                </span>
            <?php else: ?>
                <span class="badge bg-danger py-2 px-3">
                    <i class="bi bi-x-circle me-1"></i> Inactif
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Informations Détaillées</h5>
                            <div>
                                <button type="button" class="btn btn-sm btn-warning edit-account" 
                                        data-id="<?= $compte['id'] ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editBankAccountModal">
                                    <i class="bi bi-pencil"></i> Modifier
                                </button>
                                <button type="button" class="btn btn-sm btn-<?= $compte['est_actif'] == 1 ? 'danger' : 'success' ?> toggle-status" 
                                    data-id="<?= $compte['id'] ?>" 
                                    data-status="<?= $compte['est_actif'] == 1 ? 0 : 1 ?>">
                                    <i class="bi bi-<?= $compte['est_actif'] == 1 ? 'x-circle' : 'check-circle' ?>"></i> 
                                    <?= $compte['est_actif'] == 1 ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Banque</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['nom_banque']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Numéro de Compte</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['numero_compte']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Intitulé</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['intitule_compte']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Devise</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['devise']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Date d'Ouverture</div>
                            <div class="col-lg-9 col-md-8"><?= $compte['date_ouverture'] ? date('d/m/Y', strtotime($compte['date_ouverture'])) : 'Non renseignée' ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Solde Initial</div>
                            <div class="col-lg-9 col-md-8"><?= number_format($compte['solde_initial'], 2, ',', ' ') ?> <?= htmlspecialchars($compte['devise']) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Solde Actuel</div>
                            <div class="col-lg-9 col-md-8">
                                <strong><?= number_format($compte['solde_actuel'], 2, ',', ' ') ?> <?= htmlspecialchars($compte['devise']) ?></strong>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Statut</div>
                            <div class="col-lg-9 col-md-8">
                                <?php if ($compte['est_actif'] == 1): ?>
                                    <span class="badge bg-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactif</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Contact</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['contact_banque'] ?: 'Non renseigné') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Téléphone</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['telephone_banque'] ?: 'Non renseigné') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Email</div>
                            <div class="col-lg-9 col-md-8"><?= htmlspecialchars($compte['email_banque'] ?: 'Non renseigné') ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Adresse</div>
                            <div class="col-lg-9 col-md-8"><?= nl2br(htmlspecialchars($compte['adresse_banque'] ?: 'Non renseignée')) ?></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-3 col-md-4 label">Date de Création</div>
                            <div class="col-lg-9 col-md-8"><?= date('d/m/Y H:i', strtotime($compte['date_creation'])) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Transactions Récentes</h5>
                            <div>
                                <a href="finance/transactions/add_transaction&compte=<?= $compte['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Nouvelle Transaction
                                </a>
                                <a href="finance/transactions/list_transactions&compte=<?= $compte['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-list"></i> Toutes les Transactions
                                </a>
                            </div>
                        </div>

                        <?php if (empty($transactions)): ?>
                            <div class="alert alert-info">
                                Aucune transaction n'a été enregistrée pour ce compte.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Référence</th>
                                            <th>Description</th>
                                            <th>Type</th>
                                            <th>Catégorie</th>
                                            <th>Montant</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($transaction['date_transaction'])) ?></td>
                                                <td><?= htmlspecialchars($transaction['reference']) ?></td>
                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                <td>
                                                    <?php if ($transaction['type'] == 'Recette'): ?>
                                                        <span class="badge bg-success">Entrée</span>
                                                    <?php elseif ($transaction['type'] == 'Dépense'): ?>
                                                        <span class="badge bg-danger">Sortie</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Transfert</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['categorie_nom'] ?? 'N/A') ?></td>
                                                <td class="text-end <?= $transaction['type'] == 'Recette' ? 'text-success' : 'text-danger' ?>">
                                                    <?= $transaction['type'] == 'Recette' ? '+' : '-' ?>
                                                    <?= number_format($transaction['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($transaction['devise']) ?>
                                                </td>
                                                <td>
                                                    <a href="finance/transactions/view_transaction&id=<?= $transaction['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Historique des soldes -->
                <div class="card">
                    <div class="card-body pt-3">
                        <h5 class="card-title">Historique des Soldes</h5>
                        
                        <?php
                        // Récupérer l'historique des soldes du compte
                        $query_historique = "SELECT * FROM historique_soldes 
                                           WHERE type = 'Banque' AND source_id = :id_compte_bancaire 
                                           ORDER BY date DESC LIMIT 12";
                        $stmt_historique = $db->prepare($query_historique);
                        $stmt_historique->bindParam(':id_compte_bancaire', $id_compte_bancaire, PDO::PARAM_INT);
                        $stmt_historique->execute();
                        $historique = $stmt_historique->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($historique)): ?>
                            <div class="alert alert-info">
                                Aucun historique de solde n'est disponible pour ce compte.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Solde d'ouverture</th>
                                            <th>Entrées</th>
                                            <th>Sorties</th>
                                            <th>Solde de clôture</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historique as $h): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($h['date'])) ?></td>
                                                <td class="text-end"><?= number_format($h['solde_ouverture'], 2, ',', ' ') ?></td>
                                                <td class="text-end text-success">+<?= number_format($h['entrees'], 2, ',', ' ') ?></td>
                                                <td class="text-end text-danger">-<?= number_format($h['sorties'], 2, ',', ' ') ?></td>
                                                <td class="text-end fw-bold"><?= number_format($h['solde_fermeture'], 2, ',', ' ') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <a href="finance/rapports/historique_soldes&compte=<?= $compte['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-list-columns-reverse"></i> Voir l'historique complet
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal d'édition du compte -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" aria-labelledby="editBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_compte_bancaire.php" method="POST">
                <input type="hidden" name="id" id="edit_id" value="<?= $compte['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBankAccountModalLabel">Modifier le compte bancaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Première ligne -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_nom_banque" class="form-label">Nom de la banque <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom_banque" name="nom_banque" value="<?= htmlspecialchars($compte['nom_banque']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_numero_compte" class="form-label">Numéro de compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_numero_compte" name="numero_compte" value="<?= htmlspecialchars($compte['numero_compte']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_intitule_compte" class="form-label">Intitulé du compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_intitule_compte" name="intitule_compte" value="<?= htmlspecialchars($compte['intitule_compte']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_devise" class="form-label">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_devise" name="devise" required>
                                    <?php 
                                    // Récupérer les devises disponibles
                                    $stmt = $db->query("SELECT devise_principale, devise_secondaire FROM config_finance WHERE est_actif = 1 LIMIT 1");
                                    $config = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $devises = [];
                                    if ($config) {
                                        $devises[] = $config['devise_principale'];
                                        if (!empty($config['devise_secondaire'])) {
                                            $devises[] = $config['devise_secondaire'];
                                        }
                                    }
                                    // Ajouter d'autres devises courantes
                                    $devises_supplementaires = ['USD', 'EUR', 'CDF', 'GBP', 'CAD'];
                                    foreach ($devises_supplementaires as $dev) {
                                        if (!in_array($dev, $devises)) {
                                            $devises[] = $dev;
                                        }
                                    }
                                    
                                    foreach ($devises as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>" <?= $compte['devise'] == $dev ? 'selected' : '' ?>><?= htmlspecialchars($dev) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_solde_initial" class="form-label">Solde initial</label>
                                <input type="number" step="0.01" class="form-control" id="edit_solde_initial" name="solde_initial" value="<?= $compte['solde_initial'] ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_date_ouverture" class="form-label">Date d'ouverture</label>
                                <input type="date" class="form-control" id="edit_date_ouverture" name="date_ouverture" value="<?= $compte['date_ouverture'] ? date('Y-m-d', strtotime($compte['date_ouverture'])) : '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Deuxième ligne -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_contact_banque" class="form-label">Contact à la banque</label>
                                <input type="text" class="form-control" id="edit_contact_banque" name="contact_banque" value="<?= htmlspecialchars($compte['contact_banque'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_telephone_banque" class="form-label">Téléphone de la banque</label>
                                <input type="text" class="form-control" id="edit_telephone_banque" name="telephone_banque" value="<?= htmlspecialchars($compte['telephone_banque'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email_banque" class="form-label">Email de la banque</label>
                                <input type="email" class="form-control" id="edit_email_banque" name="email_banque" value="<?= htmlspecialchars($compte['email_banque'] ?? '') ?>">
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1" <?= $compte['est_actif'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="edit_est_actif">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_adresse_banque" class="form-label">Adresse de la banque</label>
                        <textarea class="form-control" id="edit_adresse_banque" name="adresse_banque" rows="3"><?= htmlspecialchars($compte['adresse_banque'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Gestion de l'activation/désactivation d'un compte
    $('.toggle-status').on('click', function() {
        const id = $(this).data('id');
        const status = $(this).data('status');
        const action = status == 1 ? 'activer' : 'désactiver';
        
        Swal.fire({
            title: 'Confirmation',
            text: `Êtes-vous sûr de vouloir ${action} ce compte bancaire ?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'controller/toggle_status_compte_bancaire.php',
                    method: 'POST',
                    data: {
                        id_compte_bancaire: id,
                        status: status
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                'Succès!',
                                response.message,
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire(
                                'Erreur',
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Erreur',
                            'Une erreur est survenue lors de la communication avec le serveur.',
                            'error'
                        );
                    }
                });
            }
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
