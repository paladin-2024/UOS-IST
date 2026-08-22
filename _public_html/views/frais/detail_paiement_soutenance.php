<?php
include "./views/include/header.php";
$fraisModel = new Frais();

// Récupérer l'ID du paiement
$idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les informations du paiement
$paiement = $idPaiement > 0 ? $fraisModel->getPaiementSoutenanceById($idPaiement) : null;

if (!$paiement) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paiement non trouvé.'
        }).then(() => {
            window.location.href = '?view=frais/paiement_soutenance';
        });
    </script>";
    exit();
}
?>

<!-- Main content -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails du paiement de frais de soutenance</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=home">Accueil</a></li>
                <li class="breadcrumb-item">Frais</li>
                <li class="breadcrumb-item"><a href="?view=frais/paiement_soutenance">Paiement des frais de soutenance</a></li>
                <li class="breadcrumb-item active">Détails du paiement</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Détails du paiement
                            <a href="?view=frais/paiement_soutenance&etudiant=<?= $paiement['etudiant_id'] ?>" class="btn btn-sm btn-outline-secondary float-end">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                        </h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Informations générales</h6>
                                <hr>
                                <!-- Informations générales -->
                                <p><strong>Étudiant:</strong> <?= htmlspecialchars($paiement['nom_etudiant']) ?> (<?= htmlspecialchars($paiement['matricule']) ?>)</p>
                                <p><strong>Frais:</strong> <?= htmlspecialchars($paiement['designation_frais']) ?></p>
                                <p><strong>Date du paiement:</strong> <?= date('d/m/Y H:i', strtotime($paiement['datePaiement'])) ?></p>
                                <p><strong>Utilisateur:</strong> <?= htmlspecialchars($paiement['nom_utilisateur']) ?></p>

                            </div>
                            <div class="col-md-6">
                                <h6>Informations financières</h6>
                                <hr>
                                <!-- Informations financières -->
                                    <p><strong>Montant total:</strong> <?= number_format($paiement['montant_total'], 2) ?> <?= htmlspecialchars($paiement['devise']) ?></p>
                                    <p><strong>Montant payé:</strong> <?= number_format($paiement['montantPaye'], 2) ?> <?= htmlspecialchars($paiement['devise']) ?></p>
                                    <p><strong>Mode de paiement:</strong> <?= htmlspecialchars($paiement['modePaiement']) ?></p>
                                    <p><strong>Référence:</strong> <?= htmlspecialchars($paiement['referencePaiement']) ?></p>
                                    <p>
                                        <strong>Statut:</strong> 
                                        <?php if ($paiement['estComplet']): ?>
                                            <span class="badge bg-success">Complet</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Partiel</span>
                                        <?php endif; ?>
                                    </p>

                            </div>
                        </div>
                        
                        <?php if (!empty($paiement['commentaire'])): ?>
                        <div class="row">
                            <div class="col-12">
                                <h6>Commentaire</h6>
                                <hr>
                                <div class="alert alert-info">
                                    <?= nl2br(htmlspecialchars($paiement['commentaire'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mt-4">
                            <div class="col-12 text-end">
                                <button class="btn btn-primary" onclick="printReceipt()">
                                    <i class="bi bi-printer"></i> Imprimer le reçu
                                </button>
                                <button class="btn btn-warning" onclick="editPayment(<?= $paiement['idpaiement_soutenance'] ?>)">
                                    <i class="bi bi-pencil"></i> Modifier
                                </button>
                                <button class="btn btn-danger" onclick="confirmDeletePayment(<?= $paiement['idpaiement_soutenance'] ?>, <?= $paiement['etudiant_id'] ?>)">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal d'édition du paiement -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editPaymentForm" method="POST" action="controller/frais_controller.php">
                    <input type="hidden" name="action" value="update_paiement_soutenance">
                    <input type="hidden" name="idPaiement" value="<?= $paiement['idpaiement_soutenance'] ?>">
                    <input type="hidden" name="etudiantId" value="<?= $paiement['idetudiant'] ?>">
                    
                    <div class="mb-3">
                        <label for="montantPaye" class="form-label">Montant payé</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="montantPaye" id="montantPaye" step="0.01" min="0.01" required value="<?= $paiement['montant_paye'] ?>">
                            <span class="input-group-text"><?= htmlspecialchars($paiement['devise']) ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referencePaiement" class="form-label">Référence du paiement</label>
                        <input type="text" class="form-control" name="referencePaiement" id="referencePaiement" required value="<?= htmlspecialchars($paiement['reference_paiement']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="modePaiement" class="form-label">Mode de paiement</label>
                        <select class="form-select" name="modePaiement" id="modePaiement" required>
                            <option value="">Sélectionner un mode de paiement</option>
                            <option value="Espèces" <?= $paiement['mode_paiement'] == 'Espèces' ? 'selected' : '' ?>>Espèces</option>
                            <option value="Chèque" <?= $paiement['mode_paiement'] == 'Chèque' ? 'selected' : '' ?>>Chèque</option>
                            <option value="Carte bancaire" <?= $paiement['mode_paiement'] == 'Carte bancaire' ? 'selected' : '' ?>>Carte bancaire</option>
                            <option value="Virement bancaire" <?= $paiement['mode_paiement'] == 'Virement bancaire' ? 'selected' : '' ?>>Virement bancaire</option>
                            <option value="Mobile Money" <?= $paiement['mode_paiement'] == 'Mobile Money' ? 'selected' : '' ?>>Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" name="commentaire" id="commentaire" rows="3"><?= htmlspecialchars($paiement['commentaire']) ?></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal d'édition
function editPayment(paiementId) {
    new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
}

// Fonction pour confirmer la suppression
function confirmDeletePayment(paiementId, etudiantId) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera ce paiement. Cette action est irréversible!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/frais_controller.php?action=delete_paiement_soutenance&id=${paiementId}&etudiant=${etudiantId}`;
        }
    });
}

// Fonction pour imprimer le reçu
function printReceipt() {
    // Créer une fenêtre d'impression
    const printWindow = window.open('', '', 'height=600,width=800');
    
    // Le contenu du reçu
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reçu de paiement</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 40px;
                    line-height: 1.6;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .receipt-title {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .receipt-number {
                    font-size: 14px;
                    margin-bottom: 20px;
                }
                .section {
                    margin-bottom: 20px;
                }
                .section-title {
                    font-weight: bold;
                    border-bottom: 1px solid #ddd;
                    padding-bottom: 5px;
                    margin-bottom: 10px;
                }
                .row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 5px;
                }
                .label {
                    font-weight: bold;
                    width: 40%;
                }
                .value {
                    width: 60%;
                }
                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 12px;
                }
                .signature {
                    margin-top: 80px;
                    display: flex;
                    justify-content: space-between;
                }
                .signature-line {
                    width: 40%;
                    border-top: 1px solid #000;
                    text-align: center;
                    padding-top: 5px;
                }
                @media print {
                    button {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="receipt-title">REÇU DE PAIEMENT</div>
                <div class="receipt-number">N° ${<?= $paiement['reference_paiement'] ?>}</div>
            </div>
            
            <div class="section">
                <div class="section-title">Informations générales</div>
                <div class="row">
                    <div class="label">Date:</div>
                    <div class="value">${<?= date('d/m/Y H:i', strtotime($paiement['date_paiement'])) ?>}</div>
                </div>
                <div class="row">
                    <div class="label">Étudiant:</div>
                    <div class="value">${<?= htmlspecialchars($paiement['nom_etudiant']) ?>} (${<?= htmlspecialchars($paiement['matricule']) ?>})</div>
                </div>
                <div class="row">
                    <div class="label">Frais:</div>
                    <div class="value">${<?= htmlspecialchars($paiement['designation_frais']) ?>}</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Détails du paiement</div>
                <div class="row">
                    <div class="label">Montant total:</div>
                    <div class="value">${<?= number_format($paiement['montant_total'], 2) ?>} ${<?= htmlspecialchars($paiement['devise']) ?>}</div>
                </div>
                <div class="row">
                    <div class="label">Montant payé:</div>
                    <div class="value">${<?= number_format($paiement['montant_paye'], 2) ?>} ${<?= htmlspecialchars($paiement['devise']) ?>}</div>
                </div>
                <div class="row">
                    <div class="label">Mode de paiement:</div>
                    <div class="value">${<?= htmlspecialchars($paiement['mode_paiement']) ?>}</div>
                </div>
                <div class="row">
                    <div class="label">Statut:</div>
                    <div class="value">${<?= $paiement['est_complet'] ? 'Complet' : 'Partiel' ?>}</div>
                </div>
            </div>
            
            <div class="signature">
                <div class="signature-line">Signature du caissier</div>
                <div class="signature-line">Signature de l'étudiant</div>
            </div>
            
            <div class="footer">
                <p>Ce reçu est une preuve de paiement. Veuillez le conserver soigneusement.</p>
                <button onclick="window.print()">Imprimer</button>
                <button onclick="window.close()">Fermer</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
}
</script>

<?php include "./views/include/footer.php"; ?>

