<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des identifiants utilisateur et de son rôle
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; 
$isAdmin = ($userRole == 1); 

// Récupération de l'ID de l'inventaire depuis l'URL
$inventaireId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($inventaireId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID d\'inventaire invalide'
        }).then(() => {
            window.location.href = 'stock/inventaire.list';
        });
    </script>";
    exit;
}

// Récupération des données de l'inventaire
$queryInventaire = "SELECT i.*, d.libelle_depot, 
                    CONCAT(u.nomUser) as nom_createur,
                    CONCAT(uv.nomUser) as nom_validateur
                    FROM inventaire i
                    INNER JOIN depot d ON i.id_depot = d.id_depot
                    INNER JOIN t_users u ON i.id_user_creation = u.idUser
                    LEFT JOIN t_users uv ON i.id_user_validation = uv.idUser
                    WHERE i.id_inventaire = :id";
$stmtInventaire = $db->prepare($queryInventaire);
$stmtInventaire->bindParam(':id', $inventaireId, PDO::PARAM_INT);
$stmtInventaire->execute();
$inventaire = $stmtInventaire->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Inventaire non trouvé'
        }).then(() => {
            window.location.href = 'stock/inventaire.list';
        });
    </script>";
    exit;
}

// Vérification des droits d'accès au dépôt
if (!$isAdmin) {
    $queryAccess = "SELECT * FROM autorisation_depot 
                   WHERE id_user = :user_id AND id_depot = :depot_id 
                   AND peut_consulter = 1";
    $stmtAccess = $db->prepare($queryAccess);
    $stmtAccess->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtAccess->bindParam(':depot_id', $inventaire['id_depot'], PDO::PARAM_INT);
    $stmtAccess->execute();
    
    if ($stmtAccess->rowCount() == 0) {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Accès refusé',
                text: 'Vous n\'avez pas l\'autorisation de consulter cet inventaire.'
            }).then(() => {
                window.location.href = 'stock/inventaire.list';
            });
        </script>";
        exit;
    }
    
    // Vérifier si l'utilisateur peut valider
    $queryValidation = "SELECT * FROM autorisation_depot 
                       WHERE id_user = :user_id AND id_depot = :depot_id 
                       AND peut_valider = 1";
    $stmtValidation = $db->prepare($queryValidation);
    $stmtValidation->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtValidation->bindParam(':depot_id', $inventaire['id_depot'], PDO::PARAM_INT);
    $stmtValidation->execute();
    $canValidate = ($stmtValidation->rowCount() > 0);
} else {
    $canValidate = true; // L'administrateur peut toujours valider
}

// Récupération des détails de l'inventaire
$queryDetails = "SELECT di.*, p.code_produit, p.libelle_produit, l.numero_lot, l.date_peremption
                FROM detail_inventaire di
                INNER JOIN produit p ON di.id_produit = p.id_produit
                INNER JOIN lot_produit l ON di.id_lot = l.id_lot
                WHERE di.id_inventaire = :id
                ORDER BY p.libelle_produit, l.numero_lot";
$stmtDetails = $db->prepare($queryDetails);
$stmtDetails->bindParam(':id', $inventaireId, PDO::PARAM_INT);
$stmtDetails->execute();
$details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

// Calcul des totaux
$totalTheorique = 0;
$totalPhysique = 0;
$totalEcartPositif = 0;
$totalEcartNegatif = 0;

foreach ($details as $detail) {
    $totalTheorique += $detail['stock_theorique'] * $detail['prix_unitaire'];
    $totalPhysique += $detail['stock_physique'] * $detail['prix_unitaire'];
    
    if ($detail['ecart'] > 0) {
        $totalEcartPositif += $detail['ecart'] * $detail['prix_unitaire'];
    } else {
        $totalEcartNegatif += abs($detail['ecart']) * $detail['prix_unitaire'];
    }
}

// Convertir la date pour l'affichage
$dateInventaire = new DateTime($inventaire['date_inventaire']);
$dateCreation = new DateTime($inventaire['date_creation']);
$dateValidation = $inventaire['date_validation'] ? new DateTime($inventaire['date_validation']) : null;

// Définir les classes de badge selon l'état
$badgeClass = '';
switch ($inventaire['etat']) {
    case 'En cours':
        $badgeClass = 'bg-warning';
        break;
    case 'Validé':
        $badgeClass = 'bg-success';
        break;
    case 'Annulé':
        $badgeClass = 'bg-danger';
        break;
    default:
        $badgeClass = 'bg-secondary';
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>DÉTAILS DE L'INVENTAIRE</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                        <li class="breadcrumb-item">Stock</li>
                        <li class="breadcrumb-item"><a href="stock/inventaire.list">Inventaires</a></li>
                        <li class="breadcrumb-item active">Détails</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="stock/inventaire.list" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour à la liste
                </a>
                <a href="javascript:window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Imprimer
                </a>
                <?php if ($inventaire['etat'] === 'En cours' && $canValidate): ?>
                <button id="validateBtn" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Valider l'inventaire
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Inventaire N° <?= htmlspecialchars($inventaire['numero_inventaire']) ?>
                            <span class="badge <?= $badgeClass ?> float-end"><?= htmlspecialchars($inventaire['etat']) ?></span>
                        </h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Numéro d'inventaire:</th>
                                        <td><?= htmlspecialchars($inventaire['numero_inventaire']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date d'inventaire:</th>
                                        <td><?= $dateInventaire->format('d/m/Y') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Dépôt:</th>
                                        <td><?= htmlspecialchars($inventaire['libelle_depot']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Observation:</th>
                                        <td><?= htmlspecialchars($inventaire['observation'] ?: 'Aucune') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Créé par:</th>
                                        <td><?= htmlspecialchars($inventaire['nom_createur']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date de création:</th>
                                        <td><?= $dateCreation->format('d/m/Y H:i') ?></td>
                                    </tr>
                                    <?php if ($dateValidation): ?>
                                    <tr>
                                        <th>Validé par:</th>
                                        <td><?= htmlspecialchars($inventaire['nom_validateur']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date de validation:</th>
                                        <td><?= $dateValidation->format('d/m/Y H:i') ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <h5 class="card-title">Détails des produits inventoriés</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">N°</th>
                                        <th style="width: 10%">Code</th>
                                        <th style="width: 25%">Produit</th>
                                        <th style="width: 10%">Lot</th>
                                        <th style="width: 10%">Date exp.</th>
                                        <th style="width: 10%">Stock théorique</th>
                                        <th style="width: 10%">Stock physique</th>
                                        <th style="width: 10%">Écart</th>
                                        <th style="width: 10%">Prix unitaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($details)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Aucun détail disponible pour cet inventaire</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php $counter = 1; foreach ($details as $detail): ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($detail['code_produit']) ?></td>
                                        <td><?= htmlspecialchars($detail['libelle_produit']) ?></td>
                                        <td><?= htmlspecialchars($detail['numero_lot']) ?></td>
                                        <td>
                                            <?php if ($detail['date_peremption']): ?>
                                                <?= (new DateTime($detail['date_peremption']))->format('d/m/Y') ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= number_format($detail['stock_theorique'], 2, ',', ' ') ?></td>
                                        <td class="text-end"><?= number_format($detail['stock_physique'], 2, ',', ' ') ?></td>
                                        <td class="text-end <?= $detail['ecart'] < 0 ? 'text-danger' : ($detail['ecart'] > 0 ? 'text-success' : '') ?>">
                                            <?= number_format($detail['ecart'], 2, ',', ' ') ?>
                                        </td>
                                        <td class="text-end"><?= number_format($detail['prix_unitaire'], 2, ',', ' ') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="5" class="text-end">Totaux:</td>
                                        <td class="text-end"><?= number_format($totalTheorique, 2, ',', ' ') ?></td>
                                        <td class="text-end"><?= number_format($totalPhysique, 2, ',', ' ') ?></td>
                                        <td colspan="2" class="text-end">
                                            <div>Excédent: <span class="text-success"><?= number_format($totalEcartPositif, 2, ',', ' ') ?></span></div>
                                            <div>Déficit: <span class="text-danger"><?= number_format($totalEcartNegatif, 2, ',', ' ') ?></span></div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Résumé de l'inventaire -->
                        <div class="row mt-4">
                            <div class="col-md-6 offset-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Résumé de l'inventaire</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Valeur stock théorique:</th>
                                                <td class="text-end"><?= number_format($totalTheorique, 2, ',', ' ') ?> $</td>
                                            </tr>
                                            <tr>
                                                <th>Valeur stock physique:</th>
                                                <td class="text-end"><?= number_format($totalPhysique, 2, ',', ' ') ?> $</td>
                                            </tr>
                                            <tr>
                                                <th>Valeur des excédents:</th>
                                                <td class="text-end text-success"><?= number_format($totalEcartPositif, 2, ',', ' ') ?> $</td>
                                            </tr>
                                            <tr>
                                                <th>Valeur des déficits:</th>
                                                <td class="text-end text-danger"><?= number_format($totalEcartNegatif, 2, ',', ' ') ?> $</td>
                                            </tr>
                                            <tr class="fw-bold">
                                                <th>Écart total:</th>
                                                <td class="text-end <?= ($totalEcartPositif - $totalEcartNegatif) < 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= number_format($totalEcartPositif - $totalEcartNegatif, 2, ',', ' ') ?> $
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du bouton de validation
    const validateBtn = document.getElementById('validateBtn');
    
    if (validateBtn) {
        validateBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Confirmer la validation',
                text: "Êtes-vous sûr de vouloir valider cet inventaire ? Cette action mettra à jour les stocks réels et ne pourra pas être annulée.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, valider',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Envoyer la requête de validation
                    fetch('controller/validate_inventaire.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=<?= $inventaire['id_inventaire'] ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Validé !',
                                text: data.message,
                                icon: 'success'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Erreur',
                                text: data.message || 'Une erreur s\'est produite lors de la validation.',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            title: 'Erreur',
                            text: 'Une erreur s\'est produite lors de la communication avec le serveur.',
                            icon: 'error'
                        });
                    });
                }
            });
        });
    }
    
    // Ajout de styles spécifiques pour l'impression
    const style = document.createElement('style');
    style.textContent = `
        @media print {
            .btn, .main-title, .breadcrumb, .pagetitle h1 {
                display: none !important;
            }
            body {
                font-size: 12px !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .card-title {
                font-size: 16px !important;
                border-bottom: 1px solid #ddd !important;
                padding-bottom: 10px !important;
            }
            table {
                width: 100% !important;
            }
            @page {
                size: landscape;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include "./views/include/footer.php"; ?>
