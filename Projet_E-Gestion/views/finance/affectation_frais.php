<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer l'année académique actuelle (active)
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer le filtre d'année pour l'affectation
$annee_affectation_filter = isset($_GET['annee_affectation']) ? intval($_GET['annee_affectation']) : ($currentYear ? $currentYear['idannee_acad'] : 0);

// Récupérer la liste des frais disponibles pour affectation
$sql_frais = "
    SELECT f.*, cf.designation AS categorie_nom, aa.designation AS annee_academique, 
           aa.idannee_acad AS annee_acad_id_frais
    FROM frais f
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
";

if ($annee_affectation_filter > 0) {
    $sql_frais .= " WHERE f.annee_acad_id = :annee_affectation_filter";
}

$sql_frais .= " ORDER BY f.annee_acad_id DESC, cf.designation, f.designation";

$stmt = $connexion->prepare($sql_frais);
if ($annee_affectation_filter > 0) {
    $stmt->bindParam(':annee_affectation_filter', $annee_affectation_filter);
}
$stmt->execute();
$frais_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des promotions
$sql_promotions = "
    SELECT p.idpromotion, p.designationPromotion, o.designationOrientation, 
           s.designationSection, a.designation AS annee_academique, 
           a.idannee_acad AS annee_acad_id
    FROM promotion p
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
";

if ($annee_affectation_filter > 0) {
    $sql_promotions .= " WHERE p.annee_acad_idannee_acad = :annee_affectation_filter";
}

$sql_promotions .= " ORDER BY a.designation DESC, s.designationSection, o.designationOrientation, p.designationPromotion";

$stmt = $connexion->prepare($sql_promotions);
if ($annee_affectation_filter > 0) {
    $stmt->bindParam(':annee_affectation_filter', $annee_affectation_filter);
}
$stmt->execute();
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années académiques pour le filtre
$stmt = $connexion->prepare("
    SELECT idannee_acad, designation 
    FROM annee_acad 
    ORDER BY designation DESC
");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les affectations récentes
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$annee_filter = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : ($currentYear ? $currentYear['idannee_acad'] : 0);
$categorie_filter = isset($_GET['categorie_id']) ? intval($_GET['categorie_id']) : 0;
$type_filter = isset($_GET['type_affectation']) ? $_GET['type_affectation'] : '';
$promotion_affectee_filter = isset($_GET['promotion_affectee']) ? intval($_GET['promotion_affectee']) : 0;

$params = [];
$where_clauses = [];

// Construire la requête avec les filtres
$sql = "
    SELECT af.*, 
           f.designation AS frais_designation, f.montant AS frais_standard,
           f.devise AS frais_devise, f.est_echelonnable,
           cf.designation AS categorie_nom,
           aa.designation AS annee_academique,
           p.designationPromotion,
           o.designationOrientation,
           s.designationSection,
           e.noms AS nom_etudiant
    FROM affectation_frais af
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN etudiant e ON af.matricule_etudiant = e.matricule
";

if (!empty($search_term)) {
    $where_clauses[] = "(f.designation LIKE :search OR af.matricule_etudiant LIKE :search OR e.nom LIKE :search OR e.postnom LIKE :search OR e.prenom LIKE :search)";
    $params[':search'] = "%$search_term%";
}

if ($annee_filter > 0) {
    $where_clauses[] = "f.annee_acad_id = :annee_id";
    $params[':annee_id'] = $annee_filter;
}

if ($categorie_filter > 0) {
    $where_clauses[] = "f.categorie_id = :categorie_id";
    $params[':categorie_id'] = $categorie_filter;
}

if ($type_filter === 'promotion') {
    $where_clauses[] = "af.promotion_id IS NOT NULL";
} elseif ($type_filter === 'etudiant') {
    $where_clauses[] = "af.matricule_etudiant IS NOT NULL";
}

if ($promotion_affectee_filter > 0) {
    $where_clauses[] = "af.promotion_id = :promotion_affectee_id";
    $params[':promotion_affectee_id'] = $promotion_affectee_filter;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY af.date_affectation DESC LIMIT 100";

$stmt = $connexion->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories de frais pour le filtre
$stmt = $connexion->prepare("
    SELECT id, designation 
    FROM categories_frais 
    ORDER BY designation
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Affectation des Frais Académiques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Affectation des Frais</li>
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
            <!-- Formulaire d'affectation -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Nouvelle Affectation</h5>
                        
                        <!-- Filtre d'année académique -->
                        <div class="mb-3">
                            <label for="filtre_annee_affectation" class="form-label">Année académique</label>
                            <select class="form-select" id="filtre_annee_affectation" name="filtre_annee_affectation" onchange="window.location.href='?view=finance/affectation_frais&annee_affectation=' + this.value">
                                <option value="">Toutes les années</option>
                                <?php 
                                $annee_affectation_filter = isset($_GET['annee_affectation']) ? intval($_GET['annee_affectation']) : ($currentYear ? $currentYear['idannee_acad'] : 0);
                                foreach ($annees_academiques as $annee): 
                                ?>
                                    <option value="<?= $annee['idannee_acad'] ?>" <?= ($annee_affectation_filter == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($annee['designation']) ?>
                                        <?php if ($currentYear && $annee['idannee_acad'] == $currentYear['idannee_acad']): ?>
                                            (En cours)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Filtre les frais et promotions par année académique.</div>
                        </div>
                        
                        <form action="controller/affecter_frais.php" method="POST">
                            
                            <!-- Sélection du frais -->
                            <div class="mb-3">
                                <label for="frais_id" class="form-label">Frais à affecter <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="frais_id" name="frais_id" required>
                                    <option value="">Sélectionnez un frais</option>
                                    <?php foreach ($frais_list as $frais): ?>
                                        <option value="<?= $frais['id'] ?>" 
                                                data-montant="<?= $frais['montant'] ?>" 
                                                data-devise="<?= $frais['devise'] ?>"
                                                data-annee-id="<?= $frais['annee_acad_id_frais'] ?? '' ?>">
                                            [<?= $frais['annee_academique'] ?>] <?= htmlspecialchars($frais['designation']) ?> - 
                                            <?= number_format($frais['montant'], 2) ?> <?= $frais['devise'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="frais_details" class="mt-2 alert alert-info d-none">
                                    <!-- Les détails du frais sélectionné seront affichés ici -->
                                </div>
                            </div>
                            
                            <!-- Type d'affectation -->
                            <div class="mb-3">
                                <label for="type_affectation" class="form-label">Type d'affectation <span class="text-danger">*</span></label>
                                <select class="form-select" id="type_affectation" name="type_affectation" required>
                                    <option value="">Sélectionnez un type</option>
                                    <option value="promotion">Affectation à une promotion</option>
                                    <option value="promotions_multiples">Affectation à plusieurs promotions</option>
                                    <option value="etudiant">Affectation à un étudiant</option>
                                    <option value="annee_academique">Affectation à toutes les promotions d'une année académique</option>
                                </select>
                            </div>

                            <!-- Sélection d'année académique (conditionnelle) -->
                            <div id="annee_academique_container" class="mb-3 d-none">
                                <label for="annee_academique_id" class="form-label">Année académique <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="annee_academique_id" name="annee_academique_id">
                                    <option value="">Sélectionnez une année académique</option>
                                    <?php foreach ($annees_academiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>">
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Sélection de promotion (conditionnelle) -->
                            <div id="promotion_container" class="mb-3 d-none">
                                <label for="promotion_id" class="form-label">Promotion <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="promotion_id" name="promotion_id">
                                    <option value="">Sélectionnez une promotion</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" data-annee="<?= $promotion['annee_academique'] ?>" data-annee-id="<?= $promotion['annee_acad_id'] ?>">
                                            [<?= $promotion['annee_academique'] ?>] <?= $promotion['designationSection'] ?> - 
                                            <?= $promotion['designationOrientation'] ?> - <?= $promotion['designationPromotion'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Sélection de plusieurs promotions (conditionnelle) -->
                            <div id="promotions_multiples_container" class="mb-3 d-none">
                                <label for="promotions_multiples" class="form-label">Promotions <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="promotions_multiples" name="promotions_multiples[]" multiple>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" data-annee="<?= $promotion['annee_academique'] ?>" data-annee-id="<?= $promotion['annee_acad_id'] ?>">
                                            [<?= $promotion['annee_academique'] ?>] <?= $promotion['designationSection'] ?> - 
                                            <?= $promotion['designationOrientation'] ?> - <?= $promotion['designationPromotion'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs promotions.</div>
                            </div>
                            
                            <!-- Matricule étudiant (conditionnel) -->
                            <div id="etudiant_container" class="mb-3 d-none">
                                <label for="matricule_etudiant" class="form-label">Matricule de l'étudiant <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="matricule_etudiant" name="matricule_etudiant" placeholder="Entrez le matricule">
                                <div id="etudiant_info" class="mt-2 d-none">
                                    <!-- Les informations de l'étudiant seront affichées ici -->
                                </div>
                            </div>
                            
                            <!-- Date d'échéance -->
                            <div class="mb-3">
                                <label for="date_echeance" class="form-label">Date d'échéance</label>
                                <input type="date" class="form-control" id="date_echeance" name="date_echeance">
                                <div class="form-text">Laissez vide pour utiliser la date d'échéance globale du frais.</div>
                            </div>
                            
                            <!-- Montant spécifique (optionnel) -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="montant_specifique_check">
                                <label class="form-check-label" for="montant_specifique_check">
                                    Définir un montant spécifique
                                </label>
                            </div>
                            
                            <div id="montant_specifique_container" class="mb-3 d-none">
                                <label for="montant_specifique" class="form-label">Montant spécifique</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" id="montant_specifique" name="montant_specifique">
                                    <select class="form-select" id="devise_specifique" name="devise_specifique">
                                        <option value="USD">USD</option>
                                        <option value="CDF">CDF</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Motif spécifique -->
                            <div class="mb-3">
                                <label for="motif_specifique" class="form-label">Motif spécifique</label>
                                <textarea class="form-control" id="motif_specifique" name="motif_specifique" rows="3"></textarea>
                                <div class="form-text">Optionnel: Précisez un motif pour cette affectation spécifique.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Affecter le frais</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Liste des affectations existantes -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Affectations Existantes</h5>
                        
                        <!-- Filtres -->
                         <form action="" method="GET" class="row g-3 mb-4">
                             <input type="hidden" name="view" value="finance/affectation_frais">
                             
                             <div class="col-md-3">
                            <label for="annee_id" class="form-label">Année académique</label>
                            <select class="form-select" id="annee_id" name="annee_id">
                            <option value="">Toutes les années</option>
                            <?php foreach ($annees_academiques as $annee): ?>
                            <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_filter == $annee['idannee_acad'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($annee['designation']) ?>
                                <?php if ($currentYear && $annee['idannee_acad'] == $currentYear['idannee_acad']): ?>
                                        (En cours)
                                        <?php endif; ?>
                                        </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                            
                            <div class="col-md-3">
                                <label for="categorie_id" class="form-label">Catégorie</label>
                                <select class="form-select" id="categorie_id" name="categorie_id">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= $categorie['id'] ?>" <?= $categorie_filter == $categorie['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categorie['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                 <label for="type_affectation_filter" class="form-label">Type</label>
                                 <select class="form-select" id="type_affectation_filter" name="type_affectation">
                                     <option value="">Tous</option>
                                     <option value="promotion" <?= $type_filter === 'promotion' ? 'selected' : '' ?>>Promotion</option>
                                     <option value="etudiant" <?= $type_filter === 'etudiant' ? 'selected' : '' ?>>Étudiant</option>
                                 </select>
                             </div>
                             
                             <div class="col-md-3">
                                 <label for="promotion_affectee" class="form-label">Promotion affectée</label>
                                 <select class="form-select" id="promotion_affectee" name="promotion_affectee">
                                     <option value="">Toutes les promotions</option>
                                     <?php foreach ($promotions as $promotion): ?>
                                         <option value="<?= $promotion['idpromotion'] ?>" <?= $promotion_affectee_filter == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                             [<?= htmlspecialchars($promotion['annee_academique']) ?>] <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                             
                             <div class="col-12">
                                 <button type="submit" class="btn btn-primary">Filtrer</button>
                                 <a href="?view=finance/affectation_frais" class="btn btn-secondary">Réinitialiser</a>
                             </div>
                        </form>
                        
                        <!-- Liste des affectations -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Frais</th>
                                        <th>Affectation</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($affectations)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Aucune affectation trouvée</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($affectations as $affectation): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($affectation['frais_designation']) ?></strong>
                                                    <div class="text-muted small"><?= htmlspecialchars($affectation['categorie_nom']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($affectation['annee_academique']) ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($affectation['promotion_id']): ?>
                                                        <span class="badge bg-info">Promotion</span>
                                                        <div><?= htmlspecialchars($affectation['designationPromotion']) ?></div>
                                                        <div class="text-muted small"><?= htmlspecialchars($affectation['designationSection'] . ' - ' . $affectation['designationOrientation']) ?></div>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Étudiant</span>
                                                        <div><?= htmlspecialchars($affectation['nom_etudiant']) ?></div>
                                                        <div class="text-muted small">Mat: <?= htmlspecialchars($affectation['matricule_etudiant']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($affectation['montant_specifique']): ?>
                                                        <strong><?= number_format($affectation['montant_specifique'], 2) ?> <?= $affectation['devise_specifique'] ?></strong>
                                                        <div class="text-muted small">(Spécifique)</div>
                                                    <?php else: ?>
                                                        <strong><?= number_format($affectation['frais_standard'], 2) ?> <?= $affectation['frais_devise'] ?></strong>
                                                        <div class="text-muted small">(Standard)</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?>
                                                    <?php if ($affectation['date_echeance']): ?>
                                                        <div class="text-muted small">Échéance: <?= date('d/m/Y', strtotime($affectation['date_echeance'])) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($affectation['est_exempte']): ?>
                                                        <span class="badge bg-warning">Exempté</span>
                                                    <?php else: ?>
                                                        <?php
                                                        $statut_class = [
                                                            'Non payé' => 'bg-danger',
                                                            'Partiel' => 'bg-warning',
                                                            'Complet' => 'bg-success'
                                                        ];
                                                        ?>
                                                        <span class="badge <?= $statut_class[$affectation['statut_paiement']] ?>">
                                                            <?= $affectation['statut_paiement'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-info view-affectation" 
                                                                data-id="<?= $affectation['id'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#viewAffectationModal">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($affectation['statut_paiement'] === 'Non payé'): ?>
                                                            <button type="button" class="btn btn-warning exemption-affectation" 
                                                                    data-id="<?= $affectation['id'] ?>"
                                                                    data-is-exempt="<?= $affectation['est_exempte'] ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#exemptionModal">
                                                                <i class="bi bi-shield-fill-exclamation"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-danger delete-affectation" 
                                                                    data-id="<?= $affectation['id'] ?>"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteAffectationModal">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($affectations) >= 100): ?>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i> Seules les 100 affectations les plus récentes sont affichées. Utilisez les filtres pour affiner votre recherche.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour visualiser une affectation -->
<div class="modal fade" id="viewAffectationModal" tabindex="-1" aria-labelledby="viewAffectationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAffectationModalLabel">Détails de l'affectation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="affectationDetailsContainer">
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

<!-- Modal pour exemption de frais -->
<div class="modal fade" id="exemptionModal" tabindex="-1" aria-labelledby="exemptionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/exemption_frais.php" method="POST">
                <input type="hidden" name="affectation_id" id="exemption_affectation_id">
                <input type="hidden" name="current_exemption" id="current_exemption" value="0">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="exemptionModalLabel">Gestion de l'exemption</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="exemption_status_container">
                        <!-- Contenu dynamique: statut actuel d'exemption -->
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif_exemption" class="form-label">Motif de l'exemption <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="motif_exemption" name="motif_exemption" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_decision" class="form-label">Référence de la décision</label>
                        <input type="text" class="form-control" id="reference_decision" name="reference_decision">
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        L'exemption d'un frais signifie que l'étudiant ne sera pas tenu de le payer. 
                        Cette action devrait être effectuée avec l'approbation des autorités compétentes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="exemptionSubmitBtn">Appliquer l'exemption</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour supprimer une affectation -->
<div class="modal fade" id="deleteAffectationModal" tabindex="-1" aria-labelledby="deleteAffectationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/supprimer_affectation.php" method="POST">
                <input type="hidden" name="affectation_id" id="delete_affectation_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAffectationModalLabel">Supprimer une affectation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Attention! Cette action est irréversible.
                    </div>
                    <p>Êtes-vous sûr de vouloir supprimer cette affectation de frais? Cette action ne peut pas être annulée.</p>
                    <p><strong>Note:</strong> La suppression n'est possible que pour les frais non payés.</p>
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
    // Initialiser Select2 pour les longs selects
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            width: '100%',
            dropdownParent: $('.select2').closest('.card')
        });
    }
    
    // Déclaration des variables pour utilisation dans les autres fonctions
    const fraisSelect = document.getElementById('frais_id');
    
    // Gestion du type d'affectation
const typeAffectation = document.getElementById('type_affectation');
const promotionContainer = document.getElementById('promotion_container');
const promotionsMultiplesContainer = document.getElementById('promotions_multiples_container');
const etudiantContainer = document.getElementById('etudiant_container');
const anneeAcademiqueContainer = document.getElementById('annee_academique_container');

if (typeAffectation) {
    $(typeAffectation).on('select2:select change', function() {
        if (this.value === 'promotion') {
            promotionContainer.classList.remove('d-none');
            promotionsMultiplesContainer.classList.add('d-none');
            etudiantContainer.classList.add('d-none');
            anneeAcademiqueContainer.classList.add('d-none');
            document.getElementById('promotion_id').setAttribute('required', 'required');
            document.getElementById('matricule_etudiant').removeAttribute('required');
            document.getElementById('annee_academique_id').removeAttribute('required');
        } else if (this.value === 'promotions_multiples') {
            promotionContainer.classList.add('d-none');
            promotionsMultiplesContainer.classList.remove('d-none');
            etudiantContainer.classList.add('d-none');
            anneeAcademiqueContainer.classList.add('d-none');
            document.getElementById('promotion_id').removeAttribute('required');
            document.getElementById('matricule_etudiant').removeAttribute('required');
            document.getElementById('annee_academique_id').removeAttribute('required');
        } else if (this.value === 'etudiant') {
            promotionContainer.classList.add('d-none');
            promotionsMultiplesContainer.classList.add('d-none');
            etudiantContainer.classList.remove('d-none');
            anneeAcademiqueContainer.classList.add('d-none');
            document.getElementById('promotion_id').removeAttribute('required');
            document.getElementById('matricule_etudiant').setAttribute('required', 'required');
            document.getElementById('annee_academique_id').removeAttribute('required');
        } else if (this.value === 'annee_academique') {
            promotionContainer.classList.add('d-none');
            promotionsMultiplesContainer.classList.add('d-none');
            etudiantContainer.classList.add('d-none');
            anneeAcademiqueContainer.classList.remove('d-none');
            document.getElementById('promotion_id').removeAttribute('required');
            document.getElementById('matricule_etudiant').removeAttribute('required');
            document.getElementById('annee_academique_id').setAttribute('required', 'required');
        } else {
            promotionContainer.classList.add('d-none');
            promotionsMultiplesContainer.classList.add('d-none');
            etudiantContainer.classList.add('d-none');
            anneeAcademiqueContainer.classList.add('d-none');
            document.getElementById('promotion_id').removeAttribute('required');
            document.getElementById('matricule_etudiant').removeAttribute('required');
            document.getElementById('annee_academique_id').removeAttribute('required');
        }
    });
}

    // Gestion de l'affichage des détails du frais
    const fraisDetails = document.getElementById('frais_details');
    
    if (fraisSelect && fraisDetails) {
        fraisSelect.addEventListener('change', function() {
            if (this.value) {
                // Récupérer les données du frais via AJAX
                fetch(`controller/get_frais_details.php?id=${this.value}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            fraisDetails.classList.remove('d-none');
                            fraisDetails.classList.replace('alert-info', 'alert-danger');
                            fraisDetails.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${data.error}`;
                            return;
                        }
                        
                        // Afficher les détails du frais
                        fraisDetails.classList.remove('d-none');
                        fraisDetails.classList.replace('alert-danger', 'alert-info');
                        
                        let estEchelonnable = data.est_echelonnable == 1 ? 'Oui' : 'Non';
                        let estObligatoire = data.est_obligatoire == 1 ? 'Oui' : 'Non';
                        
                        fraisDetails.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Catégorie:</strong> ${data.categorie_nom || 'Non spécifiée'}</p>
                                    <p><strong>Montant standard:</strong> ${parseFloat(data.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</p>
                                    <p><strong>Année académique:</strong> ${data.annee_academique || 'Non spécifiée'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Obligatoire:</strong> ${estObligatoire}</p>
                                    <p><strong>Échelonnable:</strong> ${estEchelonnable}</p>
                                    <p><strong>Cycle/Niveau:</strong> ${data.cycle || 'Tous'} ${data.niveau ? `(${data.niveau})` : ''}</p>
                                </div>
                            </div>
                            ${data.description ? `<p class="mt-2"><strong>Description:</strong> ${data.description}</p>` : ''}
                        `;
                        
                        // Mettre à jour le champ montant spécifique avec le montant du frais
                        document.getElementById('montant_specifique').value = data.montant;
                        document.getElementById('devise_specifique').value = data.devise;
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        fraisDetails.classList.remove('d-none');
                        fraisDetails.classList.replace('alert-info', 'alert-danger');
                        fraisDetails.innerHTML = '<i class="bi bi-exclamation-circle"></i> Erreur lors de la récupération des détails du frais.';
                    });
            } else {
                fraisDetails.classList.add('d-none');
            }
        });
    }
    
    // Gestion du montant spécifique
    const montantSpecifiqueCheck = document.getElementById('montant_specifique_check');
    const montantSpecifiqueContainer = document.getElementById('montant_specifique_container');
    
    if (montantSpecifiqueCheck && montantSpecifiqueContainer) {
        montantSpecifiqueCheck.addEventListener('change', function() {
            if (this.checked) {
                montantSpecifiqueContainer.classList.remove('d-none');
            } else {
                montantSpecifiqueContainer.classList.add('d-none');
                document.getElementById('montant_specifique').value = '';
            }
        });
    }
    
    // Vérification du matricule étudiant
    // Vérification du matricule étudiant
const matriculeInput = document.getElementById('matricule_etudiant');
const etudiantInfo = document.getElementById('etudiant_info');

if (matriculeInput && etudiantInfo) {
    matriculeInput.addEventListener('blur', function() {
        const matricule = this.value.trim();
        if (matricule.length >= 4) {
            // Afficher indicateur de chargement
            etudiantInfo.classList.remove('d-none');
            etudiantInfo.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span>Vérification du matricule...</span>
                </div>
            `;
            
            // Vérifier le matricule via AJAX
            fetch(`controller/get_etudiant_info.php?matricule=${encodeURIComponent(matricule)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erreur HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        etudiantInfo.className = 'mt-2 alert alert-danger';
                        etudiantInfo.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${data.error}`;
                        return;
                    }
                    
                    // Afficher les informations de l'étudiant
                    etudiantInfo.className = 'mt-2 alert alert-success';
                    etudiantInfo.innerHTML = `
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-person-check fs-3"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0"><strong>${data.nom}</strong></p>
                                <p class="mb-0 small">Promotion: ${data.promotion || 'Non spécifiée'}</p>
                                <p class="mb-0 small">Faculté: ${data.faculte || 'Non spécifiée'}</p>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    etudiantInfo.className = 'mt-2 alert alert-danger';
                    etudiantInfo.innerHTML = `<i class="bi bi-exclamation-circle"></i> Erreur lors de la vérification du matricule: ${error.message}`;
                });
        } else if (matricule.length > 0) {
            etudiantInfo.classList.remove('d-none');
            etudiantInfo.className = 'mt-2 alert alert-warning';
            etudiantInfo.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Veuillez saisir un matricule complet.';
        } else {
            etudiantInfo.classList.add('d-none');
        }
    });
}

    
    // Gestion des boutons de visualisation d'une affectation
    const viewAffectationButtons = document.querySelectorAll('.view-affectation');
    viewAffectationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            const container = document.getElementById('affectationDetailsContainer');
            
            // Afficher indicateur de chargement
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            `;
            
            // Récupérer les détails via AJAX
            fetch(`controller/get_affectation_details.php?id=${affectationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Formater les dates
                    const dateAffectation = new Date(data.date_affectation).toLocaleDateString('fr-FR');
                    const dateEcheance = data.date_echeance ? new Date(data.date_echeance).toLocaleDateString('fr-FR') : 'Non définie';
                    
                    // Construire l'affichage des détails
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Frais</h6>
                            <p><strong>Désignation:</strong> ${data.frais_designation}</p>
                            <p><strong>Catégorie:</strong> ${data.categorie_nom}</p>
                            <p><strong>Montant:</strong> ${parseFloat(data.montant_specifique || data.frais_montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise_specifique || data.devise}</p>
                            <p><strong>Année académique:</strong> ${data.annee_academique}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Affectation</h6>
                            <p><strong>Date d'affectation:</strong> ${dateAffectation}</p>
                            <p><strong>Date d'échéance:</strong> ${dateEcheance}</p>
                            <p><strong>Statut de paiement:</strong> 
                                <span class="badge ${data.statut_paiement === 'Complet' ? 'bg-success' : (data.statut_paiement === 'Partiel' ? 'bg-warning' : 'bg-danger')}">
                                    ${data.statut_paiement}
                                </span>
                            </p>
                        </div>
                    </div>`;
                    
                    // Afficher les informations spécifiques selon le type d'affectation
                    if (data.promotion_id) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Promotion affectée</h6>
                                <p><strong>Promotion:</strong> ${data.promotion_nom || data.designationPromotion}</p>
                                <p><strong>Orientation/Section:</strong> ${data.faculte_nom || data.designationSection + ' - ' + data.designationOrientation}</p>
                            </div>
                        </div>`;
                    } else if (data.matricule_etudiant) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Étudiant affecté</h6>
                                <p><strong>Matricule:</strong> ${data.matricule_etudiant}</p>
                                <p><strong>Nom:</strong> ${data.nom_etudiant || data.etudiant_nom_complet || 'Non disponible'}</p>
                            </div>
                        </div>`;
                    }
                    
                    // Afficher les informations d'exemption si applicable
                    if (data.est_exempte == 1) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle-fill"></i> Exemption</h6>
                                    <p><strong>Motif:</strong> ${data.motif_exemption || 'Non spécifié'}</p>
                                    ${data.reference_decision ? `<p><strong>Référence de décision:</strong> ${data.reference_decision}</p>` : ''}
                                </div>
                            </div>
                        </div>`;
                    }
                    
                    // Afficher le motif spécifique si défini
                    if (data.motif_specifique) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Motif spécifique</h6>
                                <div class="p-2 bg-light rounded">
                                    ${data.motif_specifique}
                                </div>
                            </div>
                        </div>`;
                    }
                    
                    // Afficher les paiements si disponibles
                    if (data.paiements && data.paiements.length > 0) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Historique des paiements</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered datatable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Montant</th>
                                                <th>Mode</th>
                                                <th>Référence</th>
                                                <th>Reçu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.paiements.map(paiement => `
                                            <tr>
                                                <td>${new Date(paiement.date_paiement).toLocaleDateString('fr-FR')}</td>
                                                                                                    <td>${parseFloat(paiement.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${paiement.devise}</td>
                                                <td>${paiement.mode_paiement}</td>
                                                <td>${paiement.reference_externe || '-'}</td>
                                                <td>${paiement.recu_numero || '-'}</td>
                                            </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                    }
                    
                    // Afficher les tranches si le frais est échelonnable
                    if (data.est_echelonnable == 1 && data.tranches && data.tranches.length > 0) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Tranches de paiement</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>N°</th>
                                                <th>Désignation</th>
                                                <th>Montant</th>
                                                <th>Échéance</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.tranches.map(tranche => `
                                            <tr>
                                                <td>${tranche.numero_tranche}</td>
                                                <td>${tranche.designation}</td>
                                                <td>${parseFloat(tranche.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise_specifique || data.devise}</td>
                                                <td>${tranche.date_echeance ? new Date(tranche.date_echeance).toLocaleDateString('fr-FR') : '-'}</td>
                                                <td>
                                                    <span class="badge ${tranche.statut_paiement === 'Complet' ? 'bg-success' : (tranche.statut_paiement === 'Partiel' ? 'bg-warning' : 'bg-danger')}">
                                                        ${tranche.statut_paiement}
                                                    </span>
                                                </td>
                                            </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                    }
                    
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    container.innerHTML = `<div class="alert alert-danger">Une erreur est survenue lors du chargement des détails.</div>`;
                });
        });
    });
    
    // Gestion des exemptions
    const exemptionButtons = document.querySelectorAll('.exemption-affectation');
    exemptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            const isExempt = this.getAttribute('data-is-exempt') === '1';
            const container = document.getElementById('exemption_status_container');
            
            document.getElementById('exemption_affectation_id').value = affectationId;
            document.getElementById('current_exemption').value = isExempt ? '1' : '0';
            
            if (isExempt) {
                container.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> Cette affectation est actuellement <strong>exemptée</strong>.
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="remove_exemption" name="remove_exemption" value="1">
                    <label class="form-check-label" for="remove_exemption">Supprimer l'exemption</label>
                </div>`;
                
                // Cacher le champ de motif si on veut supprimer l'exemption
                const removeExemptionCheck = document.getElementById('remove_exemption');
                removeExemptionCheck.addEventListener('change', function() {
                    document.getElementById('motif_exemption').closest('.mb-3').style.display = this.checked ? 'none' : 'block';
                    document.getElementById('reference_decision').closest('.mb-3').style.display = this.checked ? 'none' : 'block';
                    document.getElementById('exemptionSubmitBtn').textContent = this.checked ? 'Supprimer l\'exemption' : 'Modifier l\'exemption';
                });
            } else {
                container.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i> Cette affectation n'est actuellement <strong>pas exemptée</strong>.
                </div>`;
                document.getElementById('exemptionSubmitBtn').textContent = 'Appliquer l\'exemption';
            }
            
            // Réinitialiser les champs
            document.getElementById('motif_exemption').value = '';
            document.getElementById('reference_decision').value = '';
        });
    });
    
    // Gestion de la suppression d'une affectation
    const deleteAffectationButtons = document.querySelectorAll('.delete-affectation');
    deleteAffectationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            document.getElementById('delete_affectation_id').value = affectationId;
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>

