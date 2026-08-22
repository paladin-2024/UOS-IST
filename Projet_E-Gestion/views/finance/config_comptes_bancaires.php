<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer tous les comptes bancaires
$stmt = $connexion->query("SELECT * FROM comptes_bancaires ORDER BY est_actif DESC, nom_banque ASC");
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);

// Récupérer les devises disponibles depuis la configuration finance
$stmt = $connexion->query("SELECT devise_principale, devise_secondaire FROM config_finance WHERE est_actif = 1 LIMIT 1");
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
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Comptes Bancaires</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Comptes Bancaires</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des Comptes Bancaires
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">
                                <i class="bi bi-plus-circle"></i> Nouveau Compte
                            </button>
                        </h5>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Banque</th>
                                        <th>Numéro de compte</th>
                                        <th>Intitulé</th>
                                        <th>Devise</th>
                                        <th>Solde actuel</th>
                                        <th>Date d'ouverture</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($comptes)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucun compte bancaire enregistré</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($comptes as $compte): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($compte['nom_banque']) ?></td>
                                            <td><?= htmlspecialchars($compte['numero_compte']) ?></td>
                                            <td><?= htmlspecialchars($compte['intitule_compte']) ?></td>
                                            <td><?= htmlspecialchars($compte['devise']) ?></td>
                                            <td class="text-end"><?= number_format($compte['solde_actuel'], 2) ?></td>
                                            <td><?= $compte['date_ouverture'] ? date('d/m/Y', strtotime($compte['date_ouverture'])) : '-' ?></td>
                                            <td>
                                                <?php if ($compte['est_actif']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="finance/comptes/compte_detail.view&id=<?= $compte['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> 
                                                </a>

                                                <button type="button" class="btn btn-sm btn-info edit-account" 
                                                        data-id="<?= $compte['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editBankAccountModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-account" 
                                                        data-id="<?= $compte['id'] ?>"
                                                        data-name="<?= htmlspecialchars($compte['intitule_compte']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

<!-- Modal Ajout Compte Bancaire -->
<div class="modal fade" id="addBankAccountModal" tabindex="-1" aria-labelledby="addBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_compte_bancaire.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBankAccountModalLabel">Ajouter un compte bancaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom_banque" class="form-label">Nom de la banque <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom_banque" name="nom_banque" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="numero_compte" class="form-label">Numéro de compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="numero_compte" name="numero_compte" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="intitule_compte" class="form-label">Intitulé du compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="intitule_compte" name="intitule_compte" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="devise" class="form-label">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" id="devise" name="devise" required>
                                    <?php foreach ($devises as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>"><?= htmlspecialchars($dev) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="solde_initial" class="form-label">Solde initial</label>
                                <input type="number" step="0.01" class="form-control" id="solde_initial" name="solde_initial" value="0.00">
                            </div>
                            
                            <div class="mb-3">
                                <label for="date_ouverture" class="form-label">Date d'ouverture</label>
                                <input type="date" class="form-control" id="date_ouverture" name="date_ouverture">
                            </div>
                            
                            <div class="mb-3">
                                <label for="contact_banque" class="form-label">Contact à la banque</label>
                                <input type="text" class="form-control" id="contact_banque" name="contact_banque">
                            </div>
                            
                            <div class="mb-3">
                                <label for="telephone_banque" class="form-label">Téléphone de la banque</label>
                                <input type="text" class="form-control" id="telephone_banque" name="telephone_banque">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email_banque" class="form-label">Email de la banque</label>
                                <input type="email" class="form-control" id="email_banque" name="email_banque">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1" checked>
                                <label class="form-check-label" for="est_actif">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adresse_banque" class="form-label">Adresse de la banque</label>
                        <textarea class="form-control" id="adresse_banque" name="adresse_banque" rows="3"></textarea>
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

<!-- Modal Modification Compte Bancaire -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" aria-labelledby="editBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_compte_bancaire.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBankAccountModalLabel">Modifier un compte bancaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_nom_banque" class="form-label">Nom de la banque <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom_banque" name="nom_banque" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_numero_compte" class="form-label">Numéro de compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_numero_compte" name="numero_compte" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_intitule_compte" class="form-label">Intitulé du compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_intitule_compte" name="intitule_compte" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_devise" class="form-label">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_devise" name="devise" required>
                                    <?php foreach ($devises as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>"><?= htmlspecialchars($dev) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_solde_initial" class="form-label">Solde initial</label>
                                <input type="number" step="0.01" class="form-control" id="edit_solde_initial" name="solde_initial">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_date_ouverture" class="form-label">Date d'ouverture</label>
                                <input type="date" class="form-control" id="edit_date_ouverture" name="date_ouverture">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_contact_banque" class="form-label">Contact à la banque</label>
                                <input type="text" class="form-control" id="edit_contact_banque" name="contact_banque">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_telephone_banque" class="form-label">Téléphone de la banque</label>
                                <input type="text" class="form-control" id="edit_telephone_banque" name="telephone_banque">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email_banque" class="form-label">Email de la banque</label>
                                <input type="email" class="form-control" id="edit_email_banque" name="email_banque">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1">
                                <label class="form-check-label" for="edit_est_actif">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_adresse_banque" class="form-label">Adresse de la banque</label>
                        <textarea class="form-control" id="edit_adresse_banque" name="adresse_banque" rows="3"></textarea>
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
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du chargement des données pour l'édition
    const editButtons = document.querySelectorAll('.edit-account');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Requête AJAX pour récupérer les données du compte
            fetch(`controller/get_compte_bancaire.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Remplir le formulaire avec les données
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_nom_banque').value = data.nom_banque;
                    document.getElementById('edit_numero_compte').value = data.numero_compte;
                    document.getElementById('edit_intitule_compte').value = data.intitule_compte;
                    document.getElementById('edit_devise').value = data.devise;
                    document.getElementById('edit_solde_initial').value = data.solde_initial;
                    
                    if (data.date_ouverture) {
                        document.getElementById('edit_date_ouverture').value = data.date_ouverture.split(' ')[0]; // Garder seulement la date
                    } else {
                        document.getElementById('edit_date_ouverture').value = '';
                    }
                    
                    document.getElementById('edit_contact_banque').value = data.contact_banque || '';
                    document.getElementById('edit_telephone_banque').value = data.telephone_banque || '';
                    document.getElementById('edit_email_banque').value = data.email_banque || '';
                    document.getElementById('edit_adresse_banque').value = data.adresse_banque || '';
                    document.getElementById('edit_est_actif').checked = data.est_actif === '1';
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des données:', error);
                    alert('Une erreur est survenue lors de la récupération des données du compte.');
                });
        });
    });
    
    // Gestion de la suppression avec SweetAlert
    const deleteButtons = document.querySelectorAll('.delete-account');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: `Voulez-vous vraiment supprimer le compte "${name}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_compte_bancaire.php?id=${id}`;
                }
            });
        });
    });
});
</script>


<?php include "./views/include/footer.php"; ?>

