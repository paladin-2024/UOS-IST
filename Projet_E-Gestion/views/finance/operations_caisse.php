<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer les caisses auxquelles l'utilisateur a accès
$stmt = $connexion->prepare("
    SELECT DISTINCT c.* 
    FROM caisses c
    LEFT JOIN droits_acces_finances d ON (d.entite_id = c.id OR d.entite_id IS NULL) AND d.type = 'Caisse'
    WHERE d.\"idUser\" = :idUser AND d.est_actif = 1 
    AND (d.date_debut IS NULL OR d.date_debut <= CURRENT_DATE) 
    AND (d.date_fin IS NULL OR d.date_fin >= CURRENT_DATE)
    ORDER BY c.designation ASC
");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$caisses_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la caisse sélectionnée (si présente dans l'URL)
$caisse_id = isset($_GET['caisse_id']) ? intval($_GET['caisse_id']) : null;

// Vérifier si l'utilisateur a accès à cette caisse spécifique et obtenir son niveau d'accès
$a_access = false;
$niveau_acces = 'Lecture';
$caisse_selected = null;

if ($caisse_id) {
    foreach ($caisses_accessibles as $caisse) {
        if ($caisse['id'] == $caisse_id) {
            $a_access = true;
            $caisse_selected = $caisse;
            break;
        }
    }
    
    // Récupérer le niveau d'accès pour cette caisse
    $stmt = $connexion->prepare("
        SELECT niveau 
        FROM droits_acces_finances 
        WHERE \"idUser\" = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1
        ORDER BY entite_id DESC, niveau DESC
        LIMIT 1
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $droit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($droit) {
        $niveau_acces = $droit['niveau'];
    }
} elseif (!empty($caisses_accessibles)) {
    // Si aucune caisse n'est sélectionnée, utiliser la première caisse accessible
    $caisse_id = $caisses_accessibles[0]['id'];
    $caisse_selected = $caisses_accessibles[0];
    $a_access = true;
    
    // Récupérer le niveau d'accès pour cette caisse
    $stmt = $connexion->prepare("
        SELECT niveau 
        FROM droits_acces_finances 
        WHERE \"idUser\" = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1
        ORDER BY entite_id DESC, niveau DESC
        LIMIT 1
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $droit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($droit) {
        $niveau_acces = $droit['niveau'];
    }
}

// Récupérer l'idAgent de l'utilisateur connecté
$stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

// Vérifier si l'utilisateur a une session de caisse ouverte
$session_active = null;
if ($a_access && $caisse_id && $idAgent) {
    $stmt = $connexion->prepare("
        SELECT s.*, a.noms as agent_nom
        FROM sessions_caisse s
        LEFT JOIN agent a ON s.\"idAgent\" = a.\"idAgent\"
        WHERE s.caisse_id = :caisse_id AND s.\"idAgent\" = :idAgent AND s.statut = 'Ouverte'
        ORDER BY s.date_ouverture DESC
        LIMIT 1
    ");
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->bindParam(':idAgent', $idAgent);
    $stmt->execute();
    $session_active = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les catégories de transactions
// Récupérer les catégories de transactions (seulement celles qui n'ont pas de sous-catégories)
$stmt = $connexion->prepare("
    SELECT cb1.id, cb1.code, cb1.designation, cb1.type, cb1.parent_id 
    FROM categories_budget cb1
    WHERE cb1.est_actif = 1
    AND NOT EXISTS (
        SELECT 1 FROM categories_budget cb2 
        WHERE cb2.parent_id = cb1.id AND cb2.est_actif = 1
    )
    ORDER BY cb1.code ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les catégories par type
$categories_recettes = array_filter($categories, function($cat) {
    return $cat['type'] === 'Recette';
});
$categories_depenses = array_filter($categories, function($cat) {
    return $cat['type'] === 'Dépense';
});


// Récupérer les comptes bancaires pour les transferts
$stmt = $connexion->prepare("
    SELECT id, nom_banque, numero_compte, intitule_compte, devise
    FROM comptes_bancaires
    WHERE est_actif = 1
    ORDER BY nom_banque ASC
");
$stmt->execute();
$comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les autres caisses pour les transferts
$stmt = $connexion->prepare("
    SELECT id, designation, devise
    FROM caisses
    WHERE est_actif = 1 AND id != :caisse_id
    ORDER BY designation ASC
");
$stmt->bindParam(':caisse_id', $caisse_id);
$stmt->execute();
$autres_caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les transactions récentes de la caisse
$transactions = [];
if ($a_access && $caisse_id) {
    $stmt = $connexion->prepare("
        SELECT t.*, 
               a.noms as agent_nom,
               cb.designation as categorie_nom,
               sc.id as session_caisse_id
        FROM transactions t
        LEFT JOIN agent a ON t.\"idAgent\" = a.\"idAgent\"
        LEFT JOIN categories_budget cb ON t.categorie_id = cb.id
        LEFT JOIN sessions_caisse sc ON t.session_caisse_id = sc.id
        WHERE t.source = 'Caisse' 
        AND t.source_id = :caisse_id
        AND t.statut != 'Annulée'
        ORDER BY t.date_transaction DESC
        LIMIT 50
    ");
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Opérations de Caisse</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Opérations de Caisse</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!$a_access && empty($caisses_accessibles)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Accès non autorisé</h4>
                <p>Vous n'avez pas d'accès à une caisse. Veuillez contacter votre administrateur.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Sélection de caisse et informations -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Sélectionner une caisse</h5>
                            <form action="" method="GET" class="row g-3">
                                <input type="hidden" name="view" value="finance/operations_caisse">
                                <div class="col-md-8">
                                    <select name="caisse_id" id="caisse_id" class="form-select" required>
                                        <?php foreach ($caisses_accessibles as $caisse): ?>
                                            <option value="<?= $caisse['id'] ?>" <?= ($caisse_id == $caisse['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">Sélectionner</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php if ($caisse_selected): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Informations de la caisse</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nom:</strong> <?= htmlspecialchars($caisse_selected['designation']) ?></p>
                                    <p><strong>Devise:</strong> <?= htmlspecialchars($caisse_selected['devise']) ?></p>
                                    <p><strong>Solde actuel:</strong> <?= number_format($caisse_selected['solde_actuel'], 2) ?> <?= htmlspecialchars($caisse_selected['devise']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Session active:</strong> 
                                        <?php if ($session_active): ?>
                                            <span class="badge bg-success">Oui</span>
                                            <small>(<?= htmlspecialchars($session_active['agent_nom']) ?>)</small>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Non</span>
                                            <a href="?view=finance/sessions_caisse&caisse_id=<?= $caisse_id ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                Ouvrir une session
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>Votre accès:</strong> <span class="badge bg-info"><?= $niveau_acces ?></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($caisse_selected): ?>
                <!-- Actions disponibles -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Actions disponibles</h5>
                                
                                <?php if (!$session_active): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Aucune session n'est ouverte pour cette caisse. Veuillez d'abord <a href="?view=finance/sessions_caisse&caisse_id=<?= $caisse_id ?>">ouvrir une session</a>.
                                    </div>
                                <?php else: ?>
                                    <div class="btn-group" role="group" aria-label="Actions caisse">
                                        <?php if ($niveau_acces != 'Lecture'): ?>
                                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recetteModal">
                                                <i class="bi bi-plus-circle"></i> Nouvelle Recette
                                            </button>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#depenseModal">
                                                <i class="bi bi-dash-circle"></i> Nouvelle Dépense
                                            </button>
                                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#transfertModal">
                                                <i class="bi bi-arrow-left-right"></i> Transfert
                                            </button>
                                        <?php endif; ?>
                                        <a href="?view=finance/sessions_caisse&caisse_id=<?= $caisse_id ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-clock-history"></i> Gestion des Sessions
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions récentes -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Transactions récentes</h5>
                                
                                <?php if (empty($transactions)): ?>
                                <div class="alert alert-info">
                                    Aucune transaction n'a été enregistrée pour cette caisse.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover datatable" id="transactionsTable">
                                        <thead>
                                            <tr>
                                                <th>Réf.</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Catégorie</th>
                                                <th>Description</th>
                                                <th>Montant</th>
                                                <th>Agent</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                            <tr class="<?= $transaction['type'] === 'Recette' ? 'table-success' : ($transaction['type'] === 'Dépense' ? 'table-danger' : 'table-info') ?>">
                                                <td><?= htmlspecialchars($transaction['reference']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($transaction['date_transaction'])) ?></td>
                                                <td>
                                                    <?php if ($transaction['type'] === 'Recette'): ?>
                                                        <span class="badge bg-success">Recette</span>
                                                    <?php elseif ($transaction['type'] === 'Dépense'): ?>
                                                        <span class="badge bg-danger">Dépense</span>
                                                    <?php elseif ($transaction['type'] === 'Transfert'): ?>
                                                        <span class="badge bg-info">Transfert</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ajustement</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['categorie_nom'] ?? 'Non catégorisé') ?></td>
                                                <td><?= htmlspecialchars(mb_substr($transaction['description'] ?? '', 0, 50)) ?><?= (strlen($transaction['description'] ?? '') > 50) ? '...' : '' ?></td>
                                                <td class="text-end fw-bold">
                                                    <?php if ($transaction['type'] === 'Recette'): ?>
                                                        <span class="text-success">+<?= number_format($transaction['montant'], 2) ?></span>
                                                    <?php elseif ($transaction['type'] === 'Dépense' || ($transaction['type'] === 'Transfert' && !$transaction['destination_id'])): ?>
                                                        <span class="text-danger">-<?= number_format($transaction['montant'], 2) ?></span>
                                                    <?php else: ?>
                                                        <?= number_format($transaction['montant'], 2) ?>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($transaction['devise']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['agent_nom'] ?? 'Inconnu') ?></td>
                                                <td>
                                                    <?php if ($transaction['statut'] === 'Confirmée'): ?>
                                                        <span class="badge bg-success">Confirmée</span>
                                                    <?php elseif ($transaction['statut'] === 'Provisoire'): ?>
                                                        <span class="badge bg-warning">Provisoire</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($transaction['statut']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-transaction" data-id="<?= $transaction['id'] ?>" data-bs-toggle="modal" data-bs-target="#viewTransactionModal">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    
                                                    <?php if (($niveau_acces === 'Écriture' || $niveau_acces === 'Validation' || $niveau_acces === 'Administration') && $transaction['statut'] === 'Provisoire'): ?>
                                                    <button type="button" class="btn btn-sm btn-success confirm-transaction" data-id="<?= $transaction['id'] ?>">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (($niveau_acces === 'Validation' || $niveau_acces === 'Administration') && $transaction['statut'] !== 'Annulée'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger cancel-transaction" data-id="<?= $transaction['id'] ?>" data-ref="<?= htmlspecialchars($transaction['reference']) ?>">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <div class="dropdown d-inline">
                                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-printer"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="controller/generate_receipt_pdf.php?transaction_id=<?= $transaction['id'] ?>&format=1page" target="_blank">Reçu 1 page</a></li>
                                                            <li><a class="dropdown-item" href="controller/generate_receipt_pdf.php?transaction_id=<?= $transaction['id'] ?>&format=2pages" target="_blank">Reçu 2 pages</a></li>
                                                        </ul>
                                                    </div>

                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour l'ajout d'une recette -->
<div class="modal fade" id="recetteModal" tabindex="-1" aria-labelledby="recetteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/transaction_operations.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="type" value="Recette">
                <input type="hidden" name="source" value="Caisse">
                <input type="hidden" name="source_id" value="<?= $caisse_id ?>">
                <input type="hidden" name="session_caisse_id" value="<?= $session_active ? $session_active['id'] : '' ?>">
                <input type="hidden" name=idAgent value="<?= $idAgent ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="recetteModalLabel">Enregistrer une recette</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recette_date" class="form-label">Date de l'opération <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="recette_date" name="date_transaction" 
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="recette_reference" class="form-label">Référence <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recette_reference" name="reference" 
                                   value="REC-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?>" required>
                        </div>
                    </div>
                    <!-- Dans le formulaire de recette, à côté des autres champs -->
                    <div class="mb-3">
                        <label for="recette_depositaire" class="form-label">Déposé par <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="recette_depositaire" name="depositaire" required>
                    </div>

                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="recette_montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="recette_montant" name="montant" required>
                                <span class="input-group-text"><?= htmlspecialchars($caisse_selected['devise']) ?></span>
                                <input type="hidden" name="devise" value="<?= htmlspecialchars($caisse_selected['devise']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="recette_categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="recette_categorie" name="categorie_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories_recettes as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>">
                                        [<?= htmlspecialchars($categorie['code']) ?>] <?= htmlspecialchars($categorie['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recette_description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="recette_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recette_pieces_jointes" class="form-label">Pièces jointes (PDF, images)</label>
                        <input type="file" class="form-control" id="recette_pieces_jointes" name="pieces_jointes[]" multiple>
                        <small class="text-muted">Vous pouvez joindre plusieurs fichiers (max 5MB chacun)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="recette_confirmer" name="confirmer" value="1">
                        <label class="form-check-label" for="recette_confirmer">
                            Confirmer immédiatement cette transaction
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer la recette</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour l'ajout d'une dépense -->
<div class="modal fade" id="depenseModal" tabindex="-1" aria-labelledby="depenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/transaction_operations.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="type" value="Dépense">
                <input type="hidden" name="source" value="Caisse">
                <input type="hidden" name="source_id" value="<?= $caisse_id ?>">
                <input type="hidden" name="session_caisse_id" value="<?= $session_active ? $session_active['id'] : '' ?>">
                <input type="hidden" name=idAgent value="<?= $idAgent ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="depenseModalLabel">Enregistrer une dépense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="depense_date" class="form-label">Date de l'opération <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="depense_date" name="date_transaction" 
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="depense_reference" class="form-label">Référence <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="depense_reference" name="reference" 
                                   value="DEP-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="depense_montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="depense_montant" name="montant" required>
                                <span class="input-group-text"><?= htmlspecialchars($caisse_selected['devise']) ?></span>
                                <input type="hidden" name="devise" value="<?= htmlspecialchars($caisse_selected['devise']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="depense_categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="depense_categorie" name="categorie_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories_depenses as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>">
                                        [<?= htmlspecialchars($categorie['code']) ?>] <?= htmlspecialchars($categorie['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="depense_beneficiaire" class="form-label">Bénéficiaire <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="depense_beneficiaire" name="beneficiaire" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="depense_description" class="form-label">Description / Motif <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="depense_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="depense_pieces_jointes" class="form-label">Pièces jointes (PDF, images) </label>
                        <input type="file" class="form-control" id="depense_pieces_jointes" name="pieces_jointes[]" multiple>
                        <small class="text-muted">Vous pouvez joindre plusieurs fichiers (max 5MB chacun)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="depense_confirmer" name="confirmer" value="1">
                        <label class="form-check-label" for="depense_confirmer">
                            Confirmer immédiatement cette transaction
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Enregistrer la dépense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour un transfert -->
<div class="modal fade" id="transfertModal" tabindex="-1" aria-labelledby="transfertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/transaction_operations.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ajouter">
                <input type="hidden" name="type" value="Transfert">
                <input type="hidden" name="source" value="Caisse">
                <input type="hidden" name="source_id" value="<?= $caisse_id ?>">
                <input type="hidden" name="session_caisse_id" value="<?= $session_active ? $session_active['id'] : '' ?>">
                <input type="hidden" name=idAgent value="<?= $idAgent ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="transfertModalLabel">Effectuer un transfert</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="transfert_date" class="form-label">Date de l'opération <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="transfert_date" name="date_transaction" 
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="transfert_reference" class="form-label">Référence <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="transfert_reference" name="reference" 
                                   value="TR-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="transfert_montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="transfert_montant" name="montant" required>
                                <span class="input-group-text"><?= htmlspecialchars($caisse_selected['devise']) ?></span>
                                <input type="hidden" name="devise" value="<?= htmlspecialchars($caisse_selected['devise']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="transfert_destination_type" class="form-label">Transférer vers <span class="text-danger">*</span></label>
                            <select class="form-select" id="transfert_destination_type" name="destination_type" required>
                                <option value="">Sélectionner la destination</option>
                                <option value="Caisse">Une autre caisse</option>
                                <option value="Banque">Un compte bancaire</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3 destination-selector" id="caisse-selector" style="display: none;">
                        <label for="transfert_destination_caisse" class="form-label">Caisse de destination <span class="text-danger">*</span></label>
                        <select class="form-select" id="transfert_destination_caisse" name="destination_caisse_id">
                            <option value="">Sélectionner une caisse</option>
                            <?php foreach ($autres_caisses as $caisse): ?>
                                <option value="<?= $caisse['id'] ?>" data-devise="<?= htmlspecialchars($caisse['devise']) ?>">
                                    <?= htmlspecialchars($caisse['designation']) ?> (<?= htmlspecialchars($caisse['devise']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3 destination-selector" id="banque-selector" style="display: none;">
                        <label for="transfert_destination_banque" class="form-label">Compte bancaire de destination <span class="text-danger">*</span></label>
                        <select class="form-select" id="transfert_destination_banque" name="destination_banque_id">
                            <option value="">Sélectionner un compte bancaire</option>
                            <?php foreach ($comptes_bancaires as $compte): ?>
                                <option value="<?= $compte['id'] ?>" data-devise="<?= htmlspecialchars($compte['devise']) ?>">
                                    <?= htmlspecialchars($compte['intitule_compte']) ?> - <?= htmlspecialchars($compte['nom_banque']) ?> (<?= htmlspecialchars($compte['devise']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="taux-change-container" style="display: none;">
                        <label for="transfert_taux_change" class="form-label">Taux de change <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">1 <?= htmlspecialchars($caisse_selected['devise']) ?> =</span>
                            <input type="number" step="0.000001" min="0.000001" class="form-control" id="transfert_taux_change" name="taux_change">
                            <span class="input-group-text" id="devise-destination"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transfert_description" class="form-label">Description / Motif <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="transfert_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transfert_pieces_jointes" class="form-label">Pièces jointes (PDF, images)</label>
                        <input type="file" class="form-control" id="transfert_pieces_jointes" name="pieces_jointes[]" multiple>
                        <small class="text-muted">Vous pouvez joindre plusieurs fichiers (max 5MB chacun)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="transfert_confirmer" name="confirmer" value="1">
                        <label class="form-check-label" for="transfert_confirmer">
                            Confirmer immédiatement cette transaction
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-info">Effectuer le transfert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour visualiser une transaction -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTransactionModalLabel">Détails de la transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionDetailsContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour annuler une transaction -->
<div class="modal fade" id="cancelTransactionModal" tabindex="-1" aria-labelledby="cancelTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/transaction_operations.php" method="POST">
                <input type="hidden" name="action" value="annuler">
                <input type="hidden" name="transaction_id" id="cancel_transaction_id">
                <input type="hidden" name="caisse_id" value="<?= $caisse_id ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelTransactionModalLabel">Annuler la transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir annuler la transaction <span id="cancel_transaction_ref" class="fw-bold"></span>?</p>
                    <p class="text-danger">Cette action est irréversible et le solde de la caisse sera ajusté en conséquence.</p>
                    
                    <div class="mb-3">
                        <label for="motif_annulation" class="form-label">Motif d'annulation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="motif_annulation" name="motif_annulation" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer l'annulation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour confirmer une transaction -->
<div class="modal fade" id="confirmTransactionModal" tabindex="-1" aria-labelledby="confirmTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/transaction_operations.php" method="POST">
                <input type="hidden" name="action" value="confirmer">
                <input type="hidden" name="transaction_id" id="confirm_transaction_id">
                <input type="hidden" name="caisse_id" value="<?= $caisse_id ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmTransactionModalLabel">Confirmer la transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir confirmer cette transaction?</p>
                    <p>Cette action rendra la transaction définitive.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer la transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialisation de Select2
    $('.select2').select2();
    
    // Gestion des transferts - afficher le bon selecteur en fonction du type de destination
    const destinationType = document.getElementById('transfert_destination_type');
    if (destinationType) {
        // Remplacer l'écouteur d'événements standard par l'événement Select2
        $(destinationType).on('select2:select', handleDestinationTypeChange);
        // Gardez aussi l'écouteur standard pour compatibilité
        destinationType.addEventListener('change', handleDestinationTypeChange);
        
        // Fonction de gestion du changement
        function handleDestinationTypeChange() {
            // Cacher tous les sélecteurs de destination
            document.querySelectorAll('.destination-selector').forEach(el => {
                el.style.display = 'none';
            });
            
            document.getElementById('taux-change-container').style.display = 'none';
            
            // Obtenir la valeur sélectionnée (compatible avec Select2)
            const selectedValue = $(destinationType).val();
            
            // Afficher le sélecteur approprié
            if (selectedValue === 'Caisse') {
                document.getElementById('caisse-selector').style.display = 'block';
                document.getElementById('transfert_destination_caisse').setAttribute('required', 'required');
                document.getElementById('transfert_destination_banque').removeAttribute('required');
            } else if (selectedValue === 'Banque') {
                document.getElementById('banque-selector').style.display = 'block';
                document.getElementById('transfert_destination_banque').setAttribute('required', 'required');
                document.getElementById('transfert_destination_caisse').removeAttribute('required');
            }
        }
    }
    
    // Même problème pour les sélecteurs de caisse et de banque
    const caisseSelector = document.getElementById('transfert_destination_caisse');
    const banqueSelector = document.getElementById('transfert_destination_banque');
    
    if (caisseSelector) {
        // Utiliser l'événement Select2
        $(caisseSelector).on('select2:select', function() {
            handleDeviseChange(this);
        });
        // Garder aussi l'écouteur standard
        caisseSelector.addEventListener('change', function() {
            handleDeviseChange(this);
        });
    }
    
    if (banqueSelector) {
        // Utiliser l'événement Select2
        $(banqueSelector).on('select2:select', function() {
            handleDeviseChange(this);
        });
        // Garder aussi l'écouteur standard
        banqueSelector.addEventListener('change', function() {
            handleDeviseChange(this);
        });
    }
    
    function handleDeviseChange(selector) {
        const selectedOption = selector.options[selector.selectedIndex];
        if (selectedOption.value) {
            const deviseDestination = selectedOption.getAttribute('data-devise');
            const deviseCaisse = '<?= $caisse_selected ? htmlspecialchars($caisse_selected['devise']) : '' ?>';
            
            if (deviseDestination !== deviseCaisse) {
                document.getElementById('taux-change-container').style.display = 'block';
                document.getElementById('devise-destination').textContent = deviseDestination;
                document.getElementById('transfert_taux_change').setAttribute('required', 'required');
            } else {
                document.getElementById('taux-change-container').style.display = 'none';
                document.getElementById('transfert_taux_change').removeAttribute('required');
            }
        }
    }
    
    
    // Afficher les détails d'une transaction
    const viewButtons = document.querySelectorAll('.view-transaction');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.getAttribute('data-id');
            const container = document.getElementById('transactionDetailsContainer');
            
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            `;
            
            fetch(`controller/get_transaction_details.php?id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Formater la date
                    const dateTransaction = new Date(data.date_transaction);
                    const formattedDate = dateTransaction.toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Déterminer la classe CSS pour le type de transaction
                    let badgeClass = 'bg-secondary';
                    if (data.type === 'Recette') badgeClass = 'bg-success';
                    else if (data.type === 'Dépense') badgeClass = 'bg-danger';
                    else if (data.type === 'Transfert') badgeClass = 'bg-info';
                    
                    // Générer le contenu HTML
                    container.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Référence:</strong> ${data.reference}</p>
                                <p><strong>Date:</strong> ${formattedDate}</p>
                                <p><strong>Type:</strong> <span class="badge ${badgeClass}">${data.type}</span></p>
                                <p><strong>Catégorie:</strong> ${data.categorie_nom || 'Non catégorisé'}</p>
                                <p><strong>Statut:</strong> <span class="badge ${data.statut === 'Confirmée' ? 'bg-success' : (data.statut === 'Annulée' ? 'bg-danger' : 'bg-warning')}">${data.statut}</span></p>
                                
                            </div>
                            <div class="col-md-6">
                                <p><strong>Montant:</strong> ${parseFloat(data.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</p>
                                <p><strong>Agent:</strong> ${data.agent_nom || 'Inconnu'}</p>
                                <p><strong>Session de caisse:</strong> ${data.session_caisse_id ? `#${data.session_caisse_id}` : 'Non associée'}</p>
                                ${data.taux_change ? `<p><strong>Taux de change:</strong> ${parseFloat(data.taux_change).toLocaleString('fr-FR', {minimumFractionDigits: 6})}</p>` : ''}
                                
                                ${data.destination_nom ? `<p><strong>Destination:</strong> ${data.destination_nom}</p>` : ''}
                                ${data.beneficiaire ? `<p><strong>Bénéficiaire:</strong> ${data.beneficiaire}</p>` : ''}
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Description</h6>
                                <div class="alert alert-light">
                                    ${data.description || 'Aucune description fournie.'}
                                </div>
                            </div>
                        </div>
                        
                        ${data.motif_annulation ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Motif d'annulation</h6>
                                <div class="alert alert-danger">
                                    ${data.motif_annulation}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${data.pieces_jointes ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Pièces jointes</h6>
                                <div class="list-group">
                                    ${data.pieces_jointes.split(',').map(file => `
                                        <a href="uploads/transactions/${file.trim()}" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="bi bi-file-earmark"></i> ${file.trim()}
                                        </a>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    `;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    container.innerHTML = `<div class="alert alert-danger">Une erreur s'est produite lors du chargement des données.</div>`;
                });
        });
    });
    
    // Gérer l'annulation d'une transaction
    const cancelButtons = document.querySelectorAll('.cancel-transaction');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.getAttribute('data-id');
            const transactionRef = this.getAttribute('data-ref');
            
            document.getElementById('cancel_transaction_id').value = transactionId;
            document.getElementById('cancel_transaction_ref').textContent = transactionRef;
            
            // Afficher la modal de confirmation d'annulation
            const cancelModal = new bootstrap.Modal(document.getElementById('cancelTransactionModal'));
            cancelModal.show();
        });
    });
    
    // Gérer la confirmation d'une transaction
    const confirmButtons = document.querySelectorAll('.confirm-transaction');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.getAttribute('data-id');
            
            document.getElementById('confirm_transaction_id').value = transactionId;
            
            // Afficher la modal de confirmation
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmTransactionModal'));
            confirmModal.show();
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>


