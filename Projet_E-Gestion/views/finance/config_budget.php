<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer tous les exercices budgétaires
$stmt = $connexion->query("
    SELECT * FROM exercices_budgetaires 
    ORDER BY date_debut DESC
");
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'exercice actif ou le plus récent
$exercice_id = isset($_GET['exercice_id']) ? intval($_GET['exercice_id']) : 0;

if (!$exercice_id && !empty($exercices)) {
    // Trouver l'exercice actif, sinon prendre le plus récent
    foreach ($exercices as $exercice) {
        if ($exercice['est_actif']) {
            $exercice_id = $exercice['id'];
            break;
        }
    }
    // Si aucun n'est actif, prendre le premier (le plus récent)
    if (!$exercice_id) {
        $exercice_id = $exercices[0]['id'];
    }
}

// Récupérer les catégories budgétaires
$stmt = $connexion->query("
    SELECT c.*, p.designation as parent_designation
    FROM categories_budget c
    LEFT JOIN categories_budget p ON c.parent_id = p.id
    WHERE c.est_actif = 1
    ORDER BY c.type, c.niveau, c.code
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données du budget pour l'exercice sélectionné
$budget_data = [];
if ($exercice_id) {
    try {
        $stmt = $connexion->prepare("
            SELECT b.* 
            FROM budget b
            WHERE b.exercice_id = :exercice_id
        ");
        $stmt->bindParam(':exercice_id', $exercice_id);
        $stmt->execute();
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexer les budgets par catégorie_id pour un accès facile
        foreach ($budgets as $budget) {
            $budget_data[$budget['categorie_id']] = $budget;
        }
    } catch (PDOException $e) {
        // Si la table n'existe pas encore, continuez silencieusement
        $budget_data = [];
    }
}

// Construire un tableau des catégories qui ont des enfants
$has_children = [];
foreach ($categories as $cat) {
    if (!empty($cat['parent_id'])) {
        $has_children[$cat['parent_id']] = true;
    }
}

// Fonction pour calculer récursivement les totaux budgétaires
function calculateCategoryTotals($categories, $category_id, $budget_data) {
    $totals = [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ];
    
    // Trouver toutes les sous-catégories
    $children = array_filter($categories, function($cat) use ($category_id) {
        return $cat['parent_id'] == $category_id;
    });
    
    if (empty($children)) {
        // Si c'est une catégorie terminale, retourner ses propres valeurs
        if (isset($budget_data[$category_id])) {
            $budget = $budget_data[$category_id];
            $totals['prevu'] = $budget['montant_prevu'];
            $totals['revise'] = $budget['montant_revise'] ?? $budget['montant_prevu'];
            $totals['engage'] = $budget['montant_engage'];
            $totals['realise'] = $budget['montant_realise'];
            $totals['disponible'] = $budget['disponible'];
        }
        return $totals;
    }
    
    // Sinon, additionner les totaux de toutes les sous-catégories
    foreach ($children as $child) {
        $child_totals = calculateCategoryTotals($categories, $child['id'], $budget_data);
        $totals['prevu'] += $child_totals['prevu'];
        $totals['revise'] += $child_totals['revise'];
        $totals['engage'] += $child_totals['engage'];
        $totals['realise'] += $child_totals['realise'];
        $totals['disponible'] += $child_totals['disponible'];
    }
    
    return $totals;
}

// Préparer les totaux
$totaux = [
    'recettes' => [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ],
    'depenses' => [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ]
];

// Calculer les totaux pour les catégories racines seulement
foreach ($categories as $categorie) {
    if (empty($categorie['parent_id'])) { // Seulement les catégories de niveau 1
        $type = $categorie['type'] === 'Recette' ? 'recettes' : 'depenses';
        $calculated_totals = calculateCategoryTotals($categories, $categorie['id'], $budget_data);
        
        $totaux[$type]['prevu'] += $calculated_totals['prevu'];
        $totaux[$type]['revise'] += $calculated_totals['revise'];
        $totaux[$type]['engage'] += $calculated_totals['engage'];
        $totaux[$type]['realise'] += $calculated_totals['realise'];
        $totaux[$type]['disponible'] += $calculated_totals['disponible'];
    }
}

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuration du Budget</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Configuration du Budget</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            Configuration du Budget
                        </h5>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Sélecteur d'exercice budgétaire -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form id="exerciceForm" method="GET" action="">
                                    <input type="hidden" name="view" value="finance/config_budget">
                                    <div class="input-group">
                                        <label class="input-group-text" for="exercice_id">Exercice budgétaire</label>
                                        <select class="form-select" id="exercice_id" name="exercice_id" onchange="this.form.submit()">
                                            <?php if (empty($exercices)): ?>
                                                <option value="">Aucun exercice disponible</option>
                                            <?php else: ?>
                                                <?php foreach ($exercices as $exercice): ?>
                                                    <option value="<?= $exercice['id'] ?>" <?= $exercice_id == $exercice['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($exercice['designation']) ?> 
                                                        (<?= date('d/m/Y', strtotime($exercice['date_debut'])) ?> - 
                                                        <?= date('d/m/Y', strtotime($exercice['date_fin'])) ?>)
                                                        <?= $exercice['est_actif'] ? ' - Actif' : '' ?>
                                                        <?= $exercice['est_cloture'] ? ' - Clôturé' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <?php if (!empty($exercice_id)): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importBudgetModal">
                                        <i class="bi bi-upload"></i> Importer
                                    </button>
                                    <a href="controller/export_budget.php?exercice_id=<?= $exercice_id ?>" class="btn btn-success">
                                        <i class="bi bi-download"></i> Exporter
                                    </a>
                                    <a href="controller/generate_budget_pdf.php?exercice_id=<?= $exercice_id ?>" class="btn btn-danger" target="_blank">
                                        <i class="bi bi-file-pdf"></i> Exporter en PDF
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (empty($exercices)): ?>
                            <div class="alert alert-warning">
                                Aucun exercice budgétaire n'est défini. Veuillez d'abord <a href="?view=finance/config_exercices_budgetaires">créer un exercice budgétaire</a>.
                            </div>
                        <?php elseif ($exercice_id): ?>
                            <!-- Résumé du budget -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Résumé du Budget</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Type</th>
                                                            <th class="text-end">Prévu</th>
                                                            <th class="text-end">Révisé</th>
                                                            <th class="text-end">Engagé</th>
                                                            <th class="text-end">Réalisé</th>
                                                            <th class="text-end">Disponible</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td><span class="badge bg-success">Recettes</span></td>
                                                            <td class="text-end"><?= number_format($totaux['recettes']['prevu'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['recettes']['revise'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['recettes']['engage'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['recettes']['realise'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['recettes']['disponible'], 2) ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><span class="badge bg-danger">Dépenses</span></td>
                                                            <td class="text-end"><?= number_format($totaux['depenses']['prevu'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['depenses']['revise'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['depenses']['engage'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['depenses']['realise'], 2) ?></td>
                                                            <td class="text-end"><?= number_format($totaux['depenses']['disponible'], 2) ?></td>
                                                        </tr>
                                                        <tr class="table-primary">
                                                            <td><strong>Balance</strong></td>
                                                            <td class="text-end"><strong><?= number_format($totaux['recettes']['prevu'] - $totaux['depenses']['prevu'], 2) ?></strong></td>
                                                            <td class="text-end"><strong><?= number_format($totaux['recettes']['revise'] - $totaux['depenses']['revise'], 2) ?></strong></td>
                                                            <td class="text-end"><strong><?= number_format($totaux['recettes']['engage'] - $totaux['depenses']['engage'], 2) ?></strong></td>
                                                            <td class="text-end"><strong><?= number_format($totaux['recettes']['realise'] - $totaux['depenses']['realise'], 2) ?></strong></td>
                                                            <td class="text-end"><strong><?= number_format($totaux['recettes']['disponible'] - $totaux['depenses']['disponible'], 2) ?></strong></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                                        <!-- Onglets pour les recettes et dépenses -->
                                                        <ul class="nav nav-tabs" id="budgetTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="recettes-tab" data-bs-toggle="tab" data-bs-target="#recettes" type="button" role="tab" aria-controls="recettes" aria-selected="true">Recettes</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="depenses-tab" data-bs-toggle="tab" data-bs-target="#depenses" type="button" role="tab" aria-controls="depenses" aria-selected="false">Dépenses</button>
                                </li>
                            </ul>

                            <div class="tab-content" id="budgetTabsContent">
                                <!-- Onglet Recettes -->
                                <div class="tab-pane fade show active" id="recettes" role="tabpanel" aria-labelledby="recettes-tab">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped table-bordered datatable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Catégorie</th>
                                                    <th class="text-end">Montant Prévu</th>
                                                    <th class="text-end">Montant Révisé</th>
                                                    <th class="text-end">Engagé</th>
                                                    <th class="text-end">Réalisé</th>
                                                    <th class="text-end">Disponible</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $hasRecettes = false;
                                                foreach ($categories as $categorie):
                                                    if ($categorie['type'] === 'Recette'):
                                                        $hasRecettes = true;
                                                        
                                                        // Déterminer si la catégorie a des enfants
                                                        $isParent = isset($has_children[$categorie['id']]) && $has_children[$categorie['id']];
                                                        
                                                        // Récupérer ou calculer les valeurs budgétaires
                                                        if ($isParent) {
                                                            $calculated_values = calculateCategoryTotals($categories, $categorie['id'], $budget_data);
                                                            $montant_prevu = $calculated_values['prevu'];
                                                            $montant_revise = $calculated_values['revise'];
                                                            $montant_engage = $calculated_values['engage'];
                                                            $montant_realise = $calculated_values['realise'];
                                                            $disponible = $calculated_values['disponible'];
                                                        } else {
                                                            $budget = isset($budget_data[$categorie['id']]) ? $budget_data[$categorie['id']] : null;
                                                            $montant_prevu = $budget ? $budget['montant_prevu'] : 0;
                                                            $montant_revise = $budget ? ($budget['montant_revise'] ?? $montant_prevu) : 0;
                                                            $montant_engage = $budget ? $budget['montant_engage'] : 0;
                                                            $montant_realise = $budget ? $budget['montant_realise'] : 0;
                                                            $disponible = $budget ? $budget['disponible'] : 0;
                                                        }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($categorie['code']) ?></td>
                                                    <td>
                                                        <?php if ($categorie['niveau'] > 1): ?>
                                                            <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $categorie['niveau'] - 1) ?>
                                                            <i class="bi bi-arrow-return-right"></i>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($categorie['designation']) ?>
                                                        <?php if ($isParent): ?>
                                                            <span class="badge bg-primary">Parent</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end"><?= number_format($montant_prevu, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_revise, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_engage, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_realise, 2) ?></td>
                                                    <td class="text-end"><?= number_format($disponible, 2) ?></td>
                                                    <td>
                                                        <?php if ($isParent): ?>
                                                            <button type="button" class="btn btn-sm btn-secondary" disabled 
                                                                    title="Les catégories parentes sont calculées automatiquement">
                                                                <i class="bi bi-lock"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-primary edit-budget"
                                                                    data-categorie-id="<?= $categorie['id'] ?>"
                                                                    data-categorie-nom="<?= htmlspecialchars($categorie['designation']) ?>"
                                                                    data-type="<?= $categorie['type'] ?>"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editBudgetModal">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endif;
                                                endforeach;
                                                
                                                if (!$hasRecettes): 
                                                ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Aucune catégorie de recettes définie</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Onglet Dépenses -->
                                <div class="tab-pane fade" id="depenses" role="tabpanel" aria-labelledby="depenses-tab">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped table-bordered datatable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Catégorie</th>
                                                    <th class="text-end">Montant Prévu</th>
                                                    <th class="text-end">Montant Révisé</th>
                                                    <th class="text-end">Engagé</th>
                                                    <th class="text-end">Réalisé</th>
                                                    <th class="text-end">Disponible</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $hasDepenses = false;
                                                foreach ($categories as $categorie):
                                                    if ($categorie['type'] === 'Dépense'):
                                                        $hasDepenses = true;
                                                        
                                                        // Déterminer si la catégorie a des enfants
                                                        $isParent = isset($has_children[$categorie['id']]) && $has_children[$categorie['id']];
                                                        
                                                        // Récupérer ou calculer les valeurs budgétaires
                                                        if ($isParent) {
                                                            $calculated_values = calculateCategoryTotals($categories, $categorie['id'], $budget_data);
                                                            $montant_prevu = $calculated_values['prevu'];
                                                            $montant_revise = $calculated_values['revise'];
                                                            $montant_engage = $calculated_values['engage'];
                                                            $montant_realise = $calculated_values['realise'];
                                                            $disponible = $calculated_values['disponible'];
                                                        } else {
                                                            $budget = isset($budget_data[$categorie['id']]) ? $budget_data[$categorie['id']] : null;
                                                            $montant_prevu = $budget ? $budget['montant_prevu'] : 0;
                                                            $montant_revise = $budget ? ($budget['montant_revise'] ?? $montant_prevu) : 0;
                                                            $montant_engage = $budget ? $budget['montant_engage'] : 0;
                                                            $montant_realise = $budget ? $budget['montant_realise'] : 0;
                                                            $disponible = $budget ? $budget['disponible'] : 0;
                                                        }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($categorie['code']) ?></td>
                                                    <td>
                                                        <?php if ($categorie['niveau'] > 1): ?>
                                                            <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $categorie['niveau'] - 1) ?>
                                                            <i class="bi bi-arrow-return-right"></i>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($categorie['designation']) ?>
                                                        <?php if ($isParent): ?>
                                                            <span class="badge bg-primary">Parent</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end"><?= number_format($montant_prevu, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_revise, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_engage, 2) ?></td>
                                                    <td class="text-end"><?= number_format($montant_realise, 2) ?></td>
                                                    <td class="text-end"><?= number_format($disponible, 2) ?></td>
                                                    <td>
                                                        <?php if ($isParent): ?>
                                                            <button type="button" class="btn btn-sm btn-secondary" disabled 
                                                                    title="Les catégories parentes sont calculées automatiquement">
                                                                <i class="bi bi-lock"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-primary edit-budget"
                                                                    data-categorie-id="<?= $categorie['id'] ?>"
                                                                    data-categorie-nom="<?= htmlspecialchars($categorie['designation']) ?>"
                                                                    data-type="<?= $categorie['type'] ?>"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editBudgetModal">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endif;
                                                endforeach;
                                                
                                                if (!$hasDepenses): 
                                                ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Aucune catégorie de dépenses définie</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Note d'information sur les catégories parentes -->
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Les catégories marquées comme <span class="badge bg-primary">Parent</span> sont calculées automatiquement comme la somme de leurs sous-catégories.
                                Vous ne pouvez éditer directement que les catégories qui n'ont pas de sous-catégories.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal Modification Budget -->
<div class="modal fade" id="editBudgetModal" tabindex="-1" aria-labelledby="editBudgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/update_budget.php" method="POST">
                <input type="hidden" name="exercice_id" value="<?= $exercice_id ?>">
                <input type="hidden" name="categorie_id" id="edit_categorie_id">
                <input type="hidden" name="type" id="edit_type">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editBudgetModalLabel">Configuration du Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Catégorie: <strong id="categorie_nom"></strong></p>
                    
                    <div class="mb-3">
                        <label for="montant_prevu" class="form-label">Montant Prévu</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="montant_prevu" name="montant_prevu" required>
                            <span class="input-group-text">USD</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="montant_revise" class="form-label">Montant Révisé (optionnel)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="montant_revise" name="montant_revise">
                            <span class="input-group-text">USD</span>
                        </div>
                        <small class="text-muted">Laissez vide pour utiliser le montant prévu</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
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

<!-- Modal Import Budget -->
<div class="modal fade" id="importBudgetModal" tabindex="-1" aria-labelledby="importBudgetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/import_budget.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="exercice_id" value="<?= $exercice_id ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="importBudgetModalLabel">Importer un Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="fichier_import" class="form-label">Fichier CSV ou Excel</label>
                        <input type="file" class="form-control" id="fichier_import" name="fichier_import" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                        <small class="text-muted">Le fichier doit contenir les colonnes: Code Catégorie, Montant Prévu, Montant Révisé (optionnel)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="ecraser" name="ecraser" value="1">
                        <label class="form-check-label" for="ecraser">
                            Écraser les données existantes
                        </label>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Attention: Lors de l'importation, seules les catégories sans sous-catégories seront mises à jour. Les catégories parentes sont calculées automatiquement.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Importer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du clic sur le bouton d'édition
    const editButtons = document.querySelectorAll('.edit-budget');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categorieId = this.getAttribute('data-categorie-id');
            const categorieNom = this.getAttribute('data-categorie-nom');
            const type = this.getAttribute('data-type');
            
            document.getElementById('edit_categorie_id').value = categorieId;
            document.getElementById('edit_type').value = type;
            document.getElementById('categorie_nom').textContent = categorieNom;
            
            // Vérifier d'abord si c'est une catégorie parent
            fetch(`controller/check_category_children.php?categorie_id=${categorieId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                        return;
                    }
                    
                    // Si c'est une catégorie parent, empêcher l'édition
                    if (data.has_children) {
                        Swal.fire({
                            title: 'Action non autorisée',
                            text: 'Les catégories parentes sont calculées automatiquement et ne peuvent pas être modifiées directement.',
                            icon: 'warning',
                            confirmButtonText: 'Compris'
                        });
                        $('#editBudgetModal').modal('hide');
                        return;
                    }
                    
                    // Sinon, récupérer les valeurs du budget
                    fetch(`controller/get_budget.php?exercice_id=<?= $exercice_id ?>&categorie_id=${categorieId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                console.error(data.error);
                                return;
                            }
                            
                            if (data.budget) {
                                document.getElementById('montant_prevu').value = data.budget.montant_prevu;
                                document.getElementById('montant_revise').value = data.budget.montant_revise || '';
                                document.getElementById('commentaire').value = data.budget.commentaire || '';
                            } else {
                                // Pas de budget existant
                                document.getElementById('montant_prevu').value = '0.00';
                                document.getElementById('montant_revise').value = '';
                                document.getElementById('commentaire').value = '';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                        });
                })
                .catch(error => {
                    console.error('Erreur:', error);
                });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
