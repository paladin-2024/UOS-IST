<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer toutes les catégories de frais
$stmt = $connexion->prepare("
    SELECT * FROM categories_frais
    ORDER BY designation ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les années académiques
$stmt = $connexion->prepare("
    SELECT * FROM annee_acad
    ORDER BY designation DESC
");
$stmt->execute();
$annees_acad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année académique en cours
$annee_en_cours = null;
foreach ($annees_acad as $annee) {
    if ($annee['est_active'] == 1) {
        $annee_en_cours = $annee['idannee_acad'];
        break;
    }
}
// Si aucune année n'est active, prendre la plus récente
if (!$annee_en_cours && !empty($annees_acad)) {
    $annee_en_cours = $annees_acad[0]['idannee_acad'];
}

// Récupérer le filtre d'année académique (par défaut : année en cours)
$filtre_annee = isset($_GET['filtre_annee']) ? $_GET['filtre_annee'] : $annee_en_cours;

// Récupérer tous les frais avec les informations de catégorie et d'année académique
$query = "
    SELECT f.*, c.designation as categorie_nom, a.designation as annee_nom
    FROM frais f
    LEFT JOIN categories_frais c ON f.categorie_id = c.id
    LEFT JOIN annee_acad a ON f.annee_acad_id = a.idannee_acad
";

// Ajouter le filtre si une année est sélectionnée
if ($filtre_annee && $filtre_annee != 'toutes') {
    $query .= " WHERE f.annee_acad_id = :filtre_annee";
}

$query .= " ORDER BY f.date_creation DESC";

$stmt = $connexion->prepare($query);
if ($filtre_annee && $filtre_annee != 'toutes') {
    $stmt->bindParam(':filtre_annee', $filtre_annee, PDO::PARAM_INT);
}
$stmt->execute();
$frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les informations d'un frais spécifique pour l'édition
$frais_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    
    $stmt = $connexion->prepare("
        SELECT * FROM frais 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $frais_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les tranches de paiement pour ce frais
    if ($frais_edit && $frais_edit['est_echelonnable']) {
        $stmt = $connexion->prepare("
            SELECT * FROM tranches_paiement_config
            WHERE frais_id = :frais_id
            ORDER BY numero_tranche ASC
        ");
        $stmt->bindParam(':frais_id', $edit_id);
        $stmt->execute();
        $tranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Configuration des Frais Académiques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Configuration des Frais</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulaire d'ajout/modification -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= $frais_edit ? 'Modifier un frais' : 'Ajouter un frais' ?></h5>
                        
                        <form action="controller/frais_operations.php" method="POST" id="fraisForm">
                            <input type="hidden" name="action" value="<?= $frais_edit ? 'modifier' : 'ajouter' ?>">
                            <?php if ($frais_edit): ?>
                                <input type="hidden" name="id" value="<?= $frais_edit['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="categorie_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="categorie_id" name="categorie_id" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?= $categorie['id'] ?>" 
                                                    <?= ($frais_edit && $frais_edit['categorie_id'] == $categorie['id']) ? 'selected' : '' ?>
                                                    data-obligatoire="<?= $categorie['est_obligatoire'] ?>"
                                                    data-echelonnable="<?= $categorie['est_echelonnable'] ?>">
                                                <?= htmlspecialchars($categorie['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="annee_acad_id" class="form-label">Année académique <span class="text-danger">*</span></label>
                                    <select class="form-select" id="annee_acad_id" name="annee_acad_id" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php foreach ($annees_acad as $annee): ?>
                                            <option value="<?= $annee['idannee_acad'] ?>" 
                                                    <?= ($frais_edit && $frais_edit['annee_acad_id'] == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($annee['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" 
                                       value="<?= $frais_edit ? htmlspecialchars($frais_edit['designation']) : '' ?>" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="montant" name="montant" 
                                           value="<?= $frais_edit ? $frais_edit['montant'] : '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="devise" class="form-label">Devise</label>
                                    <select class="form-select" id="devise" name="devise">
                                        <option value="USD" <?= (!$frais_edit || $frais_edit['devise'] == 'USD') ? 'selected' : '' ?>>USD</option>
                                        <option value="CDF" <?= ($frais_edit && $frais_edit['devise'] == 'CDF') ? 'selected' : '' ?>>CDF</option>
                                        <option value="EUR" <?= ($frais_edit && $frais_edit['devise'] == 'EUR') ? 'selected' : '' ?>>EUR</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="cycle" class="form-label">Cycle</label>
                                    <select class="form-select" id="cycle" name="cycle">
                                        <option value="Tous" <?= (!$frais_edit || $frais_edit['cycle'] == 'Tous') ? 'selected' : '' ?>>Tous les cycles</option>
                                        <option value="Licence" <?= ($frais_edit && $frais_edit['cycle'] == 'Licence') ? 'selected' : '' ?>>Licence</option>
                                        <option value="Master" <?= ($frais_edit && $frais_edit['cycle'] == 'Master') ? 'selected' : '' ?>>Master</option>
                                        <option value="Doctorat" <?= ($frais_edit && $frais_edit['cycle'] == 'Doctorat') ? 'selected' : '' ?>>Doctorat</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="niveau" class="form-label">Niveau</label>
                                    <input type="text" class="form-control" id="niveau" name="niveau" 
                                           placeholder="L1, L2, M1, etc." 
                                           value="<?= $frais_edit ? htmlspecialchars($frais_edit['niveau']) : '' ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_obligatoire" name="est_obligatoire" value="1" 
                                           <?= (!$frais_edit || $frais_edit['est_obligatoire']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_obligatoire">
                                        Frais obligatoire
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_echelonnable" name="est_echelonnable" value="1" 
                                           <?= ($frais_edit && $frais_edit['est_echelonnable']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_echelonnable">
                                        Paiement échelonnable
                                    </label>
                                </div>
                            </div>
                            
                            <div id="echelonnement_options" class="border rounded p-3 mb-3" style="<?= ($frais_edit && $frais_edit['est_echelonnable']) ? '' : 'display:none;' ?>">
                                <h6>Options d'échelonnement</h6>
                                
                                <div class="mb-3">
                                    <label for="nb_tranches_max" class="form-label">Nombre maximum de tranches</label>
                                    <input type="number" min="1" max="12" class="form-control" id="nb_tranches_max" name="nb_tranches_max" 
                                           value="<?= $frais_edit ? $frais_edit['nb_tranches_max'] : '1' ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date_echeance_globale" class="form-label">Date d'échéance globale</label>
                                    <input type="date" class="form-control" id="date_echeance_globale" name="date_echeance_globale" 
                                    value="<?= $frais_edit && $frais_edit['date_echeance_globale'] ? date('Y-m-d', strtotime($frais_edit['date_echeance_globale'])) : '' ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Requis pour</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_requis_inscription" name="est_requis_inscription" value="1" 
                                           <?= (!$frais_edit || $frais_edit['est_requis_inscription']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_requis_inscription">
                                        Inscription
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_requis_examens" name="est_requis_examens" value="1" 
                                           <?= ($frais_edit && $frais_edit['est_requis_examens']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_requis_examens">
                                        Accès aux examens
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_requis_deliberation" name="est_requis_deliberation" value="1" 
                                           <?= ($frais_edit && $frais_edit['est_requis_deliberation']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_requis_deliberation">
                                        Délibération
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lieu_paiement" class="form-label">Lieu de paiement</label>
                                <select class="form-select" id="lieu_paiement" name="lieu_paiement">
                                    <option value="Caisse centrale" <?= (!$frais_edit || $frais_edit['lieu_paiement'] == 'Caisse centrale') ? 'selected' : '' ?>>Caisse centrale</option>
                                    <option value="Faculté" <?= ($frais_edit && $frais_edit['lieu_paiement'] == 'Faculté') ? 'selected' : '' ?>>Faculté</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= $frais_edit ? htmlspecialchars($frais_edit['description']) : '' ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $frais_edit ? 'Mettre à jour' : 'Enregistrer' ?>
                                </button>
                                <?php if ($frais_edit): ?>
                                    <a href="?view=finance/creation_frais" class="btn btn-secondary">
                                        <i class="bi bi-plus-circle"></i> Nouveau frais
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($frais_edit && $frais_edit['est_echelonnable']): ?>
                <!-- Configuration des tranches pour un frais existant -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Configuration des tranches</h5>
                        
                        <form action="controller/frais_operations.php" method="POST" id="tranchesForm">
                            <input type="hidden" name="action" value="configurer_tranches">
                            <input type="hidden" name="frais_id" value="<?= $frais_edit['id'] ?>">
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                Montant total: <strong><?= number_format($frais_edit['montant'], 2) ?> <?= $frais_edit['devise'] ?></strong><br>
                                Nombre max. de tranches: <strong><?= $frais_edit['nb_tranches_max'] ?></strong>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tranchesTable">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Désignation</th>
                                            <th>Pourcentage (%)</th>
                                            <th>Montant</th>
                                            <th>Date échéance</th>
                                            <th>Requis pour</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tranchesTableBody">
                                        <?php if (isset($tranches) && !empty($tranches)): ?>
                                            <?php foreach($tranches as $tranche): ?>
                                                <tr class="tranche-row">
                                                    <td>
                                                        <input type="hidden" name="tranche_numero[]" value="<?= $tranche['numero_tranche'] ?>">
                                                        <?= $tranche['numero_tranche'] ?>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" name="tranche_designation[]" value="<?= htmlspecialchars($tranche['designation']) ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm tranche-pourcentage" name="tranche_pourcentage[]" value="<?= $tranche['pourcentage'] ?>" min="0" max="100" step="0.01" required onchange="updateMontant(this)">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm tranche-montant" name="tranche_montant[]" value="<?= $tranche['montant_fixe'] ?>" min="0" step="0.01" required onchange="updatePourcentage(this)">
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control form-control-sm" name="tranche_date_echeance[]" value="<?= $tranche['date_echeance_fixe'] ? date('Y-m-d', strtotime($tranche['date_echeance_fixe'])) : '' ?>">
                                                    </td>
                                                    <td>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" name="tranche_requis_inscription[<?= $tranche['numero_tranche'] ?>]" value="1" <?= $tranche['est_requis_inscription'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">Insc.</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" name="tranche_requis_examens[<?= $tranche['numero_tranche'] ?>]" value="1" <?= $tranche['est_requis_examens'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">Exam.</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" name="tranche_requis_deliberation[<?= $tranche['numero_tranche'] ?>]" value="1" <?= $tranche['est_requis_deliberation'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label small">Délib.</label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger delete-tranche-btn">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr class="tranche-row">
                                                <td>
                                                    <input type="hidden" name="tranche_numero[]" value="1">
                                                    1
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" name="tranche_designation[]" value="Paiement complet" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm tranche-pourcentage" name="tranche_pourcentage[]" value="100" min="0" max="100" step="0.01" required onchange="updateMontant(this)">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm tranche-montant" name="tranche_montant[]" value="<?= $frais_edit['montant'] ?>" min="0" step="0.01" required onchange="updatePourcentage(this)">
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control form-control-sm" name="tranche_date_echeance[]" value="">
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="tranche_requis_inscription[1]" value="1" checked>
                                                        <label class="form-check-label small">Insc.</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="tranche_requis_examens[1]" value="1">
                                                        <label class="form-check-label small">Exam.</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="tranche_requis_deliberation[1]" value="1">
                                                        <label class="form-check-label small">Délib.</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger delete-tranche-btn">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-3">
                                <div>
                                    <button type="button" class="btn btn-success" id="addTrancheBtn">
                                        <i class="bi bi-plus-circle"></i> Ajouter une tranche
                                    </button>
                                    <button type="button" class="btn btn-warning ms-2" id="redistributeBtn" title="Répartir équitablement les pourcentages">
                                        <i class="bi bi-arrow-repeat"></i> Répartir
                                    </button>
                                </div>
                                
                                <div>
                                    <span class="me-3">
                                        <strong>Total: </strong>
                                        <span id="totalPourcentage">100</span>% / 
                                        <span id="totalMontant"><?= number_format($frais_edit['montant'], 2) ?></span> <?= $frais_edit['devise'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary" id="saveTrancheBtn">
                                    <i class="bi bi-save"></i> Enregistrer les tranches
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Liste des frais -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des frais académiques</h5>
                        
                        <!-- Filtre par année académique -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="filtre_annee" class="form-label">Filtrer par année académique</label>
                                <select class="form-select" id="filtre_annee" name="filtre_annee" onchange="window.location.href='?view=finance/creation_frais&filtre_annee=' + this.value">
                                    <option value="toutes" <?= (!$filtre_annee || $filtre_annee == 'toutes') ? 'selected' : '' ?>>Toutes les années</option>
                                    <?php foreach ($annees_acad as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= ($filtre_annee == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                            <?= ($annee['est_active'] == 1) ? ' (Année en cours)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (empty($frais)): ?>
                            <div class="alert alert-info">
                                Aucun frais académique n'a été défini<?= ($filtre_annee && $filtre_annee != 'toutes') ? ' pour cette année académique' : '' ?>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>Désignation</th>
                                            <th>Catégorie</th>
                                            <th>Montant</th>
                                            <th>Année académique</th>
                                            <th>Cycle</th>
                                            <th>Lieu de paiement</th>
                                            <th>Options</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($frais as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['designation']) ?></td>
                                                <td><?= htmlspecialchars($item['categorie_nom']) ?></td>
                                                <td><?= number_format($item['montant'], 2) ?> <?= $item['devise'] ?></td>
                                                <td><?= htmlspecialchars($item['annee_nom']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($item['cycle']) ?>
                                                    <?= $item['niveau'] ? '<br><small>'.htmlspecialchars($item['niveau']).'</small>' : '' ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= ($item['lieu_paiement'] == 'Faculté') ? 'bg-primary' : 'bg-success' ?>" title="Lieu de paiement">
                                                        <?= htmlspecialchars($item['lieu_paiement'] ?? 'Caisse centrale') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $item['est_obligatoire'] ? 'bg-success' : 'bg-secondary' ?> me-1" title="Obligatoire">
                                                        <i class="bi bi-check-circle-fill"></i>
                                                    </span>
                                                    <span class="badge <?= $item['est_echelonnable'] ? 'bg-info' : 'bg-secondary' ?> me-1" title="Échelonnable">
                                                        <i class="bi bi-calendar3"></i>
                                                    </span>
                                                    <span class="badge <?= $item['est_requis_inscription'] ? 'bg-primary' : 'bg-secondary' ?> me-1" title="Requis pour inscription">
                                        <i class="bi bi-person-vcard"></i>
                                    </span>
                                    <span class="badge <?= $item['est_requis_examens'] ? 'bg-warning' : 'bg-secondary' ?> me-1" title="Requis pour examens">
                                        <i class="bi bi-journal-text"></i>
                                    </span>
                                    <span class="badge <?= $item['est_requis_deliberation'] ? 'bg-danger' : 'bg-secondary' ?>" title="Requis pour délibération">
                                        <i class="bi bi-mortarboard"></i>
                                    </span>
                                </td>
                                <td>
                                    <a href="?view=finance/creation_frais&edit_id=<?= $item['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-info view-frais" 
                                            data-id="<?= $item['id'] ?>"
                                            title="Voir les détails">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($item['est_echelonnable']): ?>
                                    <a href="?view=finance/creation_frais&edit_id=<?= $item['id'] ?>#tranchesTable" class="btn btn-sm btn-success" title="Configurer les tranches">
                                        <i class="bi bi-list-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger delete-frais" 
                                            data-id="<?= $item['id'] ?>"
                                            data-designation="<?= htmlspecialchars($item['designation']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
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
</section>
</main>

<!-- Modal pour voir les détails d'un frais -->
<div class="modal fade" id="viewFraisModal" tabindex="-1" aria-labelledby="viewFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewFraisModalLabel">Détails du frais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="fraisDetailsContainer">
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

<!-- Modal pour confirmer la suppression d'un frais -->
<div class="modal fade" id="deleteFraisModal" tabindex="-1" aria-labelledby="deleteFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/frais_operations.php" method="POST">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" id="delete_frais_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteFraisModalLabel">Supprimer le frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le frais <span id="delete_frais_designation" class="fw-bold"></span>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> Attention:
                        <ul>
                            <li>Cette action supprimera également toutes les tranches de paiement configurées pour ce frais.</li>
                            <li>Les affectations et paiements déjà effectués ne seront pas supprimés mais pourraient devenir incohérents.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Gestion de l'affichage des options d'échelonnement
    const estEchelonnableCheck = document.getElementById('est_echelonnable');
    const echelonnementOptions = document.getElementById('echelonnement_options');
    
    if (estEchelonnableCheck && echelonnementOptions) {
        estEchelonnableCheck.addEventListener('change', function() {
            echelonnementOptions.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Gestion de la suppression
    const deleteButtons = document.querySelectorAll('.delete-frais');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fraisId = this.getAttribute('data-id');
            const fraisDesignation = this.getAttribute('data-designation');
            
            document.getElementById('delete_frais_id').value = fraisId;
            document.getElementById('delete_frais_designation').textContent = fraisDesignation;
            
            // Afficher la modal de confirmation
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteFraisModal'));
            deleteModal.show();
        });
    });
    
    // Gestion des détails du frais
    const viewButtons = document.querySelectorAll('.view-frais');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fraisId = this.getAttribute('data-id');
            const container = document.getElementById('fraisDetailsContainer');
            
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            `;
            
            // Afficher la modal
            const viewModal = new bootstrap.Modal(document.getElementById('viewFraisModal'));
            viewModal.show();
            
            // Charger les détails via AJAX
            fetch(`controller/get_frais_details.php?id=${fraisId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Construire l'affichage des détails
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informations générales</h6>
                            <p><strong>Catégorie:</strong> ${data.categorie_nom || 'Non spécifiée'}</p>
                            <p><strong>Désignation:</strong> ${data.designation}</p>
                            <p><strong>Montant:</strong> ${parseFloat(data.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</p>
                            <p><strong>Année académique:</strong> ${data.annee_academique || 'Non spécifiée'}</p>
                            <p><strong>Cycle:</strong> ${data.cycle || 'Tous'}</p>
                            <p><strong>Niveau:</strong> ${data.niveau || 'Tous'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Paramètres</h6>
                            <p><strong>Obligatoire:</strong> ${data.est_obligatoire == '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Échelonnable:</strong> ${data.est_echelonnable == '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour inscription:</strong> ${data.est_requis_inscription == '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour examens:</strong> ${data.est_requis_examens == '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour délibération:</strong> ${data.est_requis_deliberation == '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Nombre de tranches max:</strong> ${data.nb_tranches_max || '1'}</p>
                        </div>
                    </div>
                    
                    ${data.description ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Description</h6>
                            <div class="p-3 bg-light rounded">
                                ${data.description}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    `;
                    
                    // Si le frais est échelonnable, récupérer les tranches configurées
                    if (data.est_echelonnable === '1') {
                        fetch(`controller/get_tranches.php?frais_id=${fraisId}`)
                            .then(response => response.json())
                            .then(tranches => {
                                if (tranches.length > 0) {
                                    html += `
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <h6>Tranches de paiement configurées</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>N°</th>
                                                            <th>Désignation</th>
                                                            <th>Pourcentage</th>
                                                            <th>Montant</th>
                                                            <th>Date échéance</th>
                                                            <th>Requis pour</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ${tranches.map(tranche => `
                                                        <tr>
                                                            <td>${tranche.numero_tranche}</td>
                                                            <td>${tranche.designation}</td>
                                                            <td>${tranche.pourcentage}%</td>
                                                            <td>${parseFloat(tranche.montant_fixe || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</td>
                                                            <td>${tranche.date_echeance_fixe ? new Date(tranche.date_echeance_fixe).toLocaleDateString('fr-FR') : 'Non définie'}</td>
                                                            <td>
                                                                ${tranche.est_requis_inscription === '1' ? '<span class="badge bg-primary">Inscription</span> ' : ''}
                                                                ${tranche.est_requis_examens === '1' ? '<span class="badge bg-warning">Examens</span> ' : ''}
                                                                ${tranche.est_requis_deliberation === '1' ? '<span class="badge bg-danger">Délibération</span>' : ''}
                                                            </td>
                                                        </tr>
                                                        `).join('')}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    `;
                                } else {
                                    html += `
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i> Aucune tranche de paiement n'a été configurée pour ce frais.
                                            </div>
                                        </div>
                                    </div>
                                    `;
                                }
                                container.innerHTML = html;
                            })
                            .catch(error => {
                                html += `
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="alert alert-danger">
                                            <i class="bi bi-exclamation-triangle"></i> Erreur lors du chargement des tranches: ${error.message}
                                        </div>
                                    </div>
                                </div>
                                `;
                                container.innerHTML = html;
                            });
                    } else {
                        container.innerHTML = html;
                    }
                })
                .catch(error => {
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> Erreur lors du chargement des détails: ${error.message}
                        </div>
                    `;
                });
        });
    });
    
    // Gestion des tranches de paiement
     if (document.getElementById('tranchesForm')) {
         // Bouton pour redistribuer les pourcentages
         const redistributeBtn = document.getElementById('redistributeBtn');
         if (redistributeBtn) {
             redistributeBtn.addEventListener('click', function() {
                 if (confirm('Êtes-vous sûr de vouloir répartir équitablement les pourcentages entre toutes les tranches?')) {
                     redistributePercentages();
                 }
             });
         }
         
         // Ajouter une nouvelle tranche
         document.getElementById('addTrancheBtn').addEventListener('click', function() {
            const tableBody = document.getElementById('tranchesTableBody');
            const rows = tableBody.querySelectorAll('tr');
            const newNumero = rows.length + 1;
            
            // Créer la nouvelle ligne avec des valeurs par défaut
            const newRow = `
                <tr class="tranche-row">
                    <td>
                        <input type="hidden" name="tranche_numero[]" value="${newNumero}">
                        ${newNumero}
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm" name="tranche_designation[]" value="Tranche ${newNumero}" required>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm tranche-pourcentage" name="tranche_pourcentage[]" value="0" min="0" max="100" step="0.01" required onchange="updateMontant(this)">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm tranche-montant" name="tranche_montant[]" value="0" min="0" step="0.01" required onchange="updatePourcentage(this)">
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm" name="tranche_date_echeance[]" value="">
                    </td>
                    <td>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="tranche_requis_inscription[${newNumero}]" value="1">
                            <label class="form-check-label small">Insc.</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="tranche_requis_examens[${newNumero}]" value="1">
                            <label class="form-check-label small">Exam.</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="tranche_requis_deliberation[${newNumero}]" value="1">
                            <label class="form-check-label small">Délib.</label>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger delete-tranche-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            
            tableBody.insertAdjacentHTML('beforeend', newRow);
            
            // Attacher les événements à la nouvelle ligne
            attachDeleteTrancheEvent();
            
            // Redistribuer automatiquement les pourcentages de manière équitable
            redistributePercentages();
         });
        
        // Fonction pour attacher l'événement de suppression de tranche
        function attachDeleteTrancheEvent() {
            document.querySelectorAll('.delete-tranche-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const rows = document.querySelectorAll('.tranche-row');
                    if (rows.length <= 1) {
                        alert('Vous devez avoir au moins une tranche.');
                        return;
                    }
                    
                    if (confirm('Êtes-vous sûr de vouloir supprimer cette tranche?')) {
                        const row = this.closest('tr');
                        row.remove();
                        
                        // Renuméroter les tranches
                        const updatedRows = document.querySelectorAll('.tranche-row');
                        updatedRows.forEach((row, index) => {
                            const numero = index + 1;
                            row.querySelector('td:first-child').innerHTML = `
                                <input type="hidden" name="tranche_numero[]" value="${numero}">
                                ${numero}
                            `;
                            
                            // Mettre à jour les noms des checkboxes
                            const checkboxes = row.querySelectorAll('input[type="checkbox"]');
                            checkboxes.forEach(checkbox => {
                                const name = checkbox.getAttribute('name');
                                if (name) {
                                    checkbox.setAttribute('name', name.replace(/\[\d+\]/, `[${numero}]`));
                                }
                            });
                        });
                        
                        // Mettre à jour les totaux
                        updateTotals();
                    }
                });
            });
        }
        
        // Attacher les événements de suppression initiaux
        attachDeleteTrancheEvent();
        
        // Calculer le total des pourcentages et montants
        function calculerTotal() {
            const rows = document.querySelectorAll('.tranche-row');
            let totalPourcentage = 0;
            let totalMontant = 0;
            
            rows.forEach(row => {
                totalPourcentage += parseFloat(row.querySelector('.tranche-pourcentage').value) || 0;
                totalMontant += parseFloat(row.querySelector('.tranche-montant').value) || 0;
            });
            
            return {
                pourcentage: totalPourcentage,
                montant: totalMontant
            };
        }
        
        // Mettre à jour les totaux affichés
        function updateTotals() {
            const totals = calculerTotal();
            const montantTotal = parseFloat(document.getElementById('montant').value) || 0;
            const devise = document.getElementById('devise').value;
            
            document.getElementById('totalPourcentage').textContent = totals.pourcentage.toFixed(2);
            document.getElementById('totalMontant').textContent = totals.montant.toFixed(2);
            
            // Vérifier si le total est correct
            const totalElement = document.getElementById('totalPourcentage');
            if (Math.abs(totals.pourcentage - 100) > 0.1) {
                totalElement.classList.add('text-danger');
                totalElement.classList.remove('text-success');
            } else {
                totalElement.classList.add('text-success');
                totalElement.classList.remove('text-danger');
            }
        }
        
        // Mettre à jour le montant en fonction du pourcentage
        window.updateMontant = function(input) {
            const row = input.closest('tr');
            const pourcentage = parseFloat(input.value) || 0;
            const montantTotal = parseFloat(document.getElementById('montant').value) || 0;
            const montantCalcule = (montantTotal * pourcentage / 100).toFixed(2);
            
            row.querySelector('.tranche-montant').value = montantCalcule;
            updateTotals();
        };
        
        // Mettre à jour le pourcentage en fonction du montant
         window.updatePourcentage = function(input) {
             const row = input.closest('tr');
             const montant = parseFloat(input.value) || 0;
             const montantTotal = parseFloat(document.getElementById('montant').value) || 0;
             const pourcentageCalcule = montantTotal > 0 ? ((montant / montantTotal) * 100).toFixed(2) : 0;
             
             row.querySelector('.tranche-pourcentage').value = pourcentageCalcule;
             updateTotals();
         };
         
         // Fonction pour redistribuer automatiquement les pourcentages
         window.redistributePercentages = function() {
             const rows = document.querySelectorAll('.tranche-row');
             const nombreTranches = rows.length;
             
             if (nombreTranches === 0) return;
             
             const pourcentageParTranche = (100 / nombreTranches).toFixed(2);
             const montantTotal = parseFloat(document.getElementById('montant').value) || 0;
             let totalAffecte = 0;
             
             // Répartir équitablement les pourcentages et montants
             rows.forEach((row, index) => {
                 const pourcentage = index === nombreTranches - 1 
                     ? (100 - totalAffecte).toFixed(2) // Dernière tranche récupère le reste
                     : pourcentageParTranche;
                 
                 const montant = (montantTotal * pourcentage / 100).toFixed(2);
                 
                 row.querySelector('.tranche-pourcentage').value = pourcentage;
                 row.querySelector('.tranche-montant').value = montant;
                 
                 totalAffecte += parseFloat(pourcentage);
             });
             
             updateTotals();
         };
        
        // Validation du formulaire de tranches
        document.getElementById('tranchesForm').addEventListener('submit', function(e) {
            const totals = calculerTotal();
            
            if (Math.abs(totals.pourcentage - 100) > 0.1) {
                e.preventDefault();
                alert('Le total des pourcentages doit être égal à 100%.');
                return false;
            }
            
            return true;
        });
        
        // Calculer les totaux au chargement
        updateTotals();
    }
    
    // Sélection automatique des options en fonction de la catégorie
    const categorieSelect = document.getElementById('categorie_id');
    if (categorieSelect) {
        categorieSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption) {
                const estObligatoire = selectedOption.getAttribute('data-obligatoire') === '1';
                const estEchelonnable = selectedOption.getAttribute('data-echelonnable') === '1';
                
                document.getElementById('est_obligatoire').checked = estObligatoire;
                document.getElementById('est_echelonnable').checked = estEchelonnable;
                
                // Mettre à jour l'affichage des options d'échelonnement
                if (echelonnementOptions) {
                    echelonnementOptions.style.display = estEchelonnable ? 'block' : 'none';
                }
            }
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>

