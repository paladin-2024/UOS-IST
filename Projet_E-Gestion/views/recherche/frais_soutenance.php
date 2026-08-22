<?php
include "./views/include/header.php";
$universite = new Universite();
$soutenanceModel = new Soutenance();

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les frais de soutenance pour l'année académique en cours
$fraisSoutenance = $soutenanceModel->getFraisSoutenance($currentYear['idannee_acad']);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>FRAIS DE SOUTENANCE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=recherche/soutenances">Soutenances</a></li>
                <li class="breadcrumb-item active">Frais de soutenance</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Frais de soutenance
                            <span>
                                | <a data-bs-toggle="modal" data-bs-target="#addFraisModal" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Ajouter un frais
                                </a>
                            </span>
                        </h5>

                        <?php if (empty($fraisSoutenance)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun frais de soutenance n'a été défini pour cette année académique.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Description</th>
                                            <th scope="col">Date de création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($fraisSoutenance as $frais):
                                        ?>
                                            <tr>
                                                <td><?= $i ?></td>
                                                <td><?= htmlspecialchars($frais['designation']) ?></td>
                                                <td><?= number_format($frais['montant'], 2) ?> <?= htmlspecialchars($frais['devise']) ?></td>
                                                <td><?= htmlspecialchars($frais['description']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($frais['date_creation'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editFrais(<?= $frais['idfrais_soutenance'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteFrais(<?= $frais['idfrais_soutenance'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php
                                            $i++;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Liste des étudiants ayant payé -->
            <div class="col-lg-12 mt-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Paiements des frais de soutenance
                            <span>
                                | <a data-bs-toggle="modal" data-bs-target="#addPaiementModal" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Enregistrer un paiement
                                </a>
                                | <a data-bs-toggle="modal" data-bs-target="#importPaiementsModal" class="btnPage">
                                    <i class="bi bi-upload"></i> Importer des paiements
                                </a>
                            </span>
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Étudiant</th>
                                        <th scope="col">Matricule</th>
                                        <th scope="col">Frais</th>
                                        <th scope="col">Montant payé</th>
                                        <th scope="col">Référence</th>
                                        <th scope="col">Date du paiement</th>
                                        <th scope="col">Statut</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $paiements = $soutenanceModel->getPaiementsSoutenance($currentYear['idannee_acad']);
                                    $i = 1;
                                    foreach ($paiements as $p):
                                    ?>
                                        <tr>
                                            <td><?= $i ?></td>
                                            <td><?= htmlspecialchars($p['noms']) ?></td>
                                            <td><?= htmlspecialchars($p['matricule']) ?></td>
                                            <td><?= htmlspecialchars($p['designation']) ?></td>
                                            <td><?= number_format($p['montant_paye'], 2) ?> <?= htmlspecialchars($p['devise']) ?></td>
                                            <td><?= htmlspecialchars($p['reference_paiement']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $p['est_complet'] ? 'success' : 'warning' ?>">
                                                    <?= $p['est_complet'] ? 'Complet' : 'Partiel' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editPaiement(<?= $p['idpaiement_soutenance'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePaiement(<?= $p['idpaiement_soutenance'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="printRecu(<?= $p['idpaiement_soutenance'] ?>)">
                                                    <i class="bi bi-printer"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                        $i++;
                                    endforeach;
                                    
                                    if (empty($paiements)):
                                    ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Aucun paiement enregistré pour le moment.</td>
                                        </tr>
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

<!-- Modal pour ajouter un frais -->
<div class="modal fade" id="addFraisModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un frais de soutenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="fraisForm" method="POST" action="controller/soutenance_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_frais_soutenance">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant</label>
                            <input type="number" name="montant" id="montant" class="form-control" step="0.01" min="0" required>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="devise" class="form-label">Devise</label>
                            <select name="devise" id="devise" class="form-select" required>
                                <option value="USD">USD</option>
                                <option value="CDF">CDF</option>
                                <option value="EUR">EUR</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une devise.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un paiement -->
<div class="modal fade" id="addPaiementModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enregistrer un paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paiementForm" method="POST" action="controller/paiement_soutenance.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_paiement">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="etudiant" class="form-label">Étudiant</label>
                            <select name="etudiant" id="etudiant" class="form-select" required>
                                <option value="">Sélectionnez un étudiant</option>
                                <?php
                                $etudiants = $universite->getEtudiantsByAnnee($currentYear['idannee_acad']);
                                foreach ($etudiants as $e):
                                ?>
                                    <option value="<?= $e['idetudiant'] ?>"><?= htmlspecialchars($e['noms']) ?> (<?= htmlspecialchars($e['matricule']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un étudiant.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="idFrais" class="form-label">Frais</label>
                            <select name="idFrais" id="idFrais" class="form-select" required>
                                <option value="">Sélectionnez un frais</option>
                                <?php foreach ($fraisSoutenance as $f): ?>
                                    <option value="<?= $f['idfrais_soutenance'] ?>" data-montant="<?= $f['montant'] ?>" data-devise="<?= $f['devise'] ?>">
                                        <?= htmlspecialchars($f['designation']) ?> (<?= number_format($f['montant'], 2) ?> <?= htmlspecialchars($f['devise']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un frais.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montantPaye" class="form-label">Montant payé</label>
                            <input type="number" name="montantPaye" id="montantPaye" class="form-control" step="0.01" min="0" required>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="referencePaiement" class="form-label">Référence du paiement</label>
                            <input type="text" name="referencePaiement" id="referencePaiement" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une référence.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modePaiement" class="form-label">Mode de paiement</label>
                            <select name="modePaiement" id="modePaiement" class="form-select" required>
                                <option value="">Sélectionnez un mode</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Virement bancaire">Virement bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte bancaire</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un mode de paiement.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="estComplet" class="form-label">Statut du paiement</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="estComplet" name="estComplet" value="1">
                                <label class="form-check-label" for="estComplet">
                                    Paiement complet
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea name="commentaire" id="commentaire" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer le paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour importer des paiements -->
<div class="modal fade" id="importPaiementsModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des paiements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" method="POST" action="controller/import_paiements_soutenance.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="idFraisImport" class="form-label">Frais concerné</label>
                            <select name="idFraisImport" id="idFraisImport" class="form-select" required>
                                <option value="">Sélectionnez un frais</option>
                                <?php foreach ($fraisSoutenance as $f): ?>
                                    <option value="<?= $f['idfrais_soutenance'] ?>">
                                        <?= htmlspecialchars($f['designation']) ?> (<?= number_format($f['montant'], 2) ?> <?= htmlspecialchars($f['devise']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un frais.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichierImport" class="form-label">Fichier Excel/CSV</label>
                            <input type="file" name="fichierImport" id="fichierImport" class="form-control" required accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                            <small class="form-text text-muted">
                                Le fichier doit contenir une colonne avec les matricules des étudiants.
                            </small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="colonnePaiement" class="form-label">Colonne des matricules</label>
                            <input type="text" name="colonnePaiement" id="colonnePaiement" class="form-control" value="A" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="dateImport" class="form-label">Date de paiement</label>
                            <input type="date" name="dateImport" id="dateImport" class="form-control" required value="<?= date('Y-m-d') ?>">
                            <div class="invalid-feedback">Veuillez indiquer une date.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="estCompletImport" name="estCompletImport" value="1" checked>
                                <label class="form-check-label" for="estCompletImport">
                                    Marquer tous les paiements comme complets
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Assurez-vous que les matricules dans le fichier correspondent bien aux matricules enregistrés dans le système.
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Limiter le montant de paiement au montant du frais
document.addEventListener('DOMContentLoaded', function() {
    const fraisSelect = document.getElementById('idFrais');
    const montantInput = document.getElementById('montantPaye');
    
    fraisSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const montantMax = parseFloat(selectedOption.dataset.montant);
            montantInput.setAttribute('max', montantMax);
            montantInput.value = montantMax;
        }
    });
    
    // Fonctions pour éditer/supprimer les frais et paiements
    window.editFrais = function(idFrais) {
        window.location.href = `?view=recherche/frais_soutenance.edit&id=${idFrais}`;
    };
    
    window.deleteFrais = function(idFrais) {
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
                window.location.href = `controller/soutenance_controller.php?action=delete_frais&id=${idFrais}`;
            }
        });
    };
    
    window.editPaiement = function(idPaiement) {
        window.location.href = `?view=recherche/paiement_soutenance.edit&id=${idPaiement}`;
    };
    
    window.deletePaiement = function(idPaiement) {
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
                window.location.href = `controller/paiement_soutenance.php?action=delete_paiement&id=${idPaiement}`;
            }
        });
    };
    
    window.printRecu = function(idPaiement) {
        window.open(`controller/generate_recu_soutenance.php?id=${idPaiement}`, '_blank');
    };
});
</script>

<?php include "./views/include/footer.php"; ?>

