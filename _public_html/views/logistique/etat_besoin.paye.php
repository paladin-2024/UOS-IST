<?php
include "./views/include/header.php";
$structure = new Structure();
$banque = new Banque();
$userId = $_SESSION['id'];

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$etatDeBesoins = $structure->getEtatDeBesoinsByUserAccess($userId, $searchQuery, 50);
$banks = $banque->getBanksByUserAccess($userId);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>PAIEMENT DES ÉTATS DE BESOIN</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Paiement États de Besoin</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un état de besoin..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Paiement des états de besoin validés</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Numéro</th>
                                    <th>Libellé</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($etatDeBesoins as $etat) {
                                    if ($etat['validation1'] !== null) {
                                        $uniqueNumber = sprintf("EDB-%05d", $etat['idEtat_de_besoin']);
                                        $dateE = date('d-m-Y', strtotime($etat['dateElaboration']));
                                        $isPaid = $etat['statut'] === 'Paye';
                                        $statusBadge = $isPaid ? 
                                            "<span class='badge bg-success'>Payé</span>" : 
                                            "<span class='badge bg-warning'>Validé</span>";
                                
                                        echo "<tr>
                                            <td>{$i}</td>
                                            <td>{$uniqueNumber}</td>
                                            <td>{$etat['libelle']}</td>
                                            <td>USD {$etat['montant']}</td>
                                            <td>{$dateE}</td>
                                            <td>{$statusBadge}</td>
                                            <td>
                                                <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseLignes{$etat['idEtat_de_besoin']}'>
                                                    <i class='bi bi-list'></i> Détails
                                                </button>";
                                        
                                        if ($isPaid) {
                                            // Bouton d'impression pour les états payés
                                            echo "<a href='comptabilite/generate_depense_print?depenseId={$etat['idDepense']}' class='btn btn-sm btn-success' target='_blank'>
                                                    <i class='bi bi-printer'></i> Imprimer
                                                </a>";
                                        } else {
                                            // Bouton de paiement pour les états non payés
                                            echo "<button class='btn btn-sm btn-primary' onclick='showPaymentModal({$etat['idEtat_de_besoin']}, \"{$etat['montant']}\", \"{$etat['libelle']}\")'>
                                                    <i class='bi bi-cash'></i> Payer
                                                </button>";
                                        }
                                        
                                        echo "</td></tr>
                                        <tr class='collapse-row'>
                                            <td colspan='7'>
                                                <div class='collapse' id='collapseLignes{$etat['idEtat_de_besoin']}'>
                                                    <table class='table table-sm table-bordered m-0'>
                                                        <thead>
                                                            <tr>
                                                                <th>Désignation</th>
                                                                <th>Quantité</th>
                                                                <th>Prix Unitaire</th>
                                                                <th>Prix Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>";
                                        $lignes = $structure->getLignesEtatBesoinByEtat($etat['idEtat_de_besoin']);
                                        foreach ($lignes as $ligne) {
                                            $pt = $ligne['quantite'] * $ligne['prixUnitaire'];
                                            echo "<tr>
                                                    <td>{$ligne['designation']}</td>
                                                    <td>{$ligne['quantite']}</td>
                                                    <td>USD {$ligne['prixUnitaire']}</td>
                                                    <td>USD {$pt}</td>
                                                </tr>";
                                        }
                                        echo "</tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>";
                                        $i++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal de Paiement -->
<div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Effectuer le paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="etatBesoinId" name="etatBesoinId">
                    <div class="mb-3">
                        <label for="montant" class="form-label">Montant</label>
                        <input type="text" class="form-control" id="montant" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé</label>
                        <input type="text" class="form-control" id="libelle" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="bankId" class="form-label">Sélectionner la banque</label>
                        <select class="form-select" id="bankId" name="bankId" required>
                            <option value="">Choisir une banque...</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank['idBanque']; ?>">
                                    <?php echo htmlspecialchars($bank['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="beneficiaire" class="form-label">Bénéficiaire</label>
                        <input type="text" class="form-control" id="beneficiaire" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="processPayment()">Confirmer le paiement</button>
            </div>
        </div>
    </div>
</div>

<script>
function showPaymentModal(etatId, montant, libelle) {
    document.getElementById('etatBesoinId').value = etatId;
    document.getElementById('montant').value = montant;
    document.getElementById('libelle').value = libelle;
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function processPayment() {
    const etatId = document.getElementById('etatBesoinId').value;
    const bankId = document.getElementById('bankId').value;
    const beneficiaire = document.getElementById('beneficiaire').value;
    
    if (!bankId) {
        Swal.fire('Erreur', 'Veuillez sélectionner une banque', 'error');
        return;
    }

    Swal.fire({
        title: 'Confirmation',
        text: 'Voulez-vous vraiment effectuer ce paiement ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, payer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'controller/payerEtatBesoin.php',
                type: 'POST',
                data: {
                    etatId: etatId,
                    bankId: bankId,
                    beneficiaire: beneficiaire
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Succès', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Erreur', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Erreur', 'Une erreur est survenue lors du paiement.', 'error');
                }
            });
        }
    });
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        window.location.href = 'logistique/etat_besoin.paye?search=' + encodeURIComponent(this.value.trim());
    }
});
</script>

<?php include "./views/include/footer.php"; ?>