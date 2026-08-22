<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id']; // Correction: idUser au lieu de id

// Récupérer les catégories de frais
$stmt = $connexion->prepare("SELECT * FROM categories_frais ORDER BY designation ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années académiques
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions avec leurs orientations et sections
$stmt = $connexion->prepare("SELECT p.idpromotion, p.designationPromotion, 
                           CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte 
                           FROM promotion p 
                           LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           LEFT JOIN section s ON o.section_idsection = s.idsection
                           ORDER BY s.designationSection, o.designationOrientation, p.designationPromotion");
$stmt->execute();
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les frais configurés avec leurs catégories
$stmt = $connexion->prepare("
    SELECT f.*, c.designation AS categorie_nom, aa.designation AS annee_academique
    FROM frais f
    LEFT JOIN categories_frais c ON f.categorie_id = c.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    ORDER BY f.id DESC
");
$stmt->execute();
$frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier les droits d'accès de l'utilisateur
$stmt = $connexion->prepare("
    SELECT niveau 
    FROM droits_acces_finances 
    WHERE idUser = :idUser AND type = 'Validation' 
    AND est_actif = 1
    ORDER BY niveau DESC
    LIMIT 1
");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$droit = $stmt->fetch(PDO::FETCH_ASSOC);

$niveau_acces = $droit ? $droit['niveau'] : 'Lecture';




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
            <!-- Gestion des catégories de frais -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Catégories de Frais
                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="bi bi-plus-circle"></i> Ajouter une catégorie
                                </button>
                            <?php endif; ?>
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Désignation</th>
                                        <th scope="col">Obligatoire</th>
                                        <th scope="col">Échelonnable</th>
                                        <th scope="col">Remboursable</th>
                                        <th scope="col">Compte Comptable</th>
                                        <?php if ($niveau_acces !== 'Lecture'): ?>
                                            <th scope="col">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $index => $categorie): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($categorie['designation']) ?></td>
                                            <td>
                                                <?php if ($categorie['est_obligatoire']): ?>
                                                    <span class="badge bg-success">Oui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($categorie['est_echelonnable']): ?>
                                                    <span class="badge bg-success">Oui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($categorie['est_remboursable']): ?>
                                                    <span class="badge bg-success">Oui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($categorie['compte_comptable'] ?? 'Non défini') ?></td>
                                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                            data-id="<?= $categorie['id'] ?>"
                                                            data-designation="<?= htmlspecialchars($categorie['designation']) ?>"
                                                            data-obligatoire="<?= $categorie['est_obligatoire'] ?>"
                                                            data-echelonnable="<?= $categorie['est_echelonnable'] ?>"
                                                            data-remboursable="<?= $categorie['est_remboursable'] ?>"
                                                            data-compte="<?= htmlspecialchars($categorie['compte_comptable'] ?? '') ?>"
                                                            data-description="<?= htmlspecialchars($categorie['description'] ?? '') ?>"
                                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <?php if ($niveau_acces === 'Administration'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger delete-category" 
                                                                data-id="<?= $categorie['id'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#deleteCategoryModal">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="<?= $niveau_acces !== 'Lecture' ? 7 : 6 ?>" class="text-center">Aucune catégorie de frais configurée</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration des frais -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Configuration des Frais
                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFraisModal">
                                    <i class="bi bi-plus-circle"></i> Ajouter un frais
                                </button>
                            <?php endif; ?>
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Désignation</th>
                                        <th scope="col">Catégorie</th>
                                        <th scope="col">Montant</th>
                                        <th scope="col">Année Académique</th>
                                        <th scope="col">Cycle/Niveau</th>
                                        <th scope="col">Obligatoire</th>
                                        <th scope="col">Échelonnable</th>
                                        <?php if ($niveau_acces !== 'Lecture'): ?>
                                            <th scope="col">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($frais as $index => $frais_item): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($frais_item['designation']) ?></td>
                                            <td><?= htmlspecialchars($frais_item['categorie_nom']) ?></td>
                                            <td><?= number_format($frais_item['montant'], 2) ?> <?= htmlspecialchars($frais_item['devise']) ?></td>
                                            <td><?= htmlspecialchars($frais_item['annee_academique'] ?? 'Non définie') ?></td>
                                            <td>
                                                <?= htmlspecialchars($frais_item['cycle']) ?>
                                                <?= $frais_item['niveau'] ? ' (' . htmlspecialchars($frais_item['niveau']) . ')' : '' ?>
                                            </td>
                                            <td>
                                                <?php if ($frais_item['est_obligatoire']): ?>
                                                    <span class="badge bg-success">Oui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($frais_item['est_echelonnable']): ?>
                                                    <span class="badge bg-success">Oui (<?= $frais_item['nb_tranches_max'] ?> tranches)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-frais" 
                                                            data-id="<?= $frais_item['id'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#viewFraisModal">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary edit-frais" 
                                                            data-id="<?= $frais_item['id'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#editFraisModal">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <?php if ($niveau_acces === 'Administration'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger delete-frais" 
                                                                data-id="<?= $frais_item['id'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#deleteFraisModal">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($frais_item['est_echelonnable']): ?>
                                                        <button type="button" class="btn btn-sm btn-success config-tranches" 
                                                                data-id="<?= $frais_item['id'] ?>"
                                                                data-designation="<?= htmlspecialchars($frais_item['designation']) ?>"
                                                                data-montant="<?= $frais_item['montant'] ?>"
                                                                data-devise="<?= htmlspecialchars($frais_item['devise']) ?>"
                                                                data-tranches="<?= $frais_item['nb_tranches_max'] ?>"                                                                 data-bs-toggle="modal" data-bs-target="#configTranchesModal">
                                                            <i class="bi bi-list-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($frais)): ?>
                                        <tr>
                                            <td colspan="<?= $niveau_acces !== 'Lecture' ? 9 : 8 ?>" class="text-center">Aucun frais configuré</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Affectation des frais aux promotions -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Affectation des Frais aux Promotions
                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#affectFraisModal">
                                    <i class="bi bi-plus-circle"></i> Nouvelle affectation
                                </button>
                            <?php endif; ?>
                        </h5>

                        <!-- Formulaire de recherche pour les affectations -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <form action="" method="GET" class="row g-3">
                                    <input type="hidden" name="view" value="finance/configuration_frais">
                                    
                                    <div class="col-md-4">
                                        <select name="annee_acad_filtre" id="annee_acad_filtre" class="form-select">
                                            <option value="">Toutes les années académiques</option>
                                            <?php foreach ($annees_academiques as $annee): ?>
                                                <option value="<?= $annee['idannee_acad'] ?>" <?= (isset($_GET['annee_acad_filtre']) && $_GET['annee_acad_filtre'] == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($annee['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <select name="promotion_filtre" id="promotion_filtre" class="form-select">
                                            <option value="">Toutes les promotions</option>
                                            <?php foreach ($promotions as $promotion): ?>
            <option value="<?= $promotion['idpromotion'] ?>" <?= (isset($_GET['promotion_filtre']) && $_GET['promotion_filtre'] == $promotion['idpromotion']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($promotion['faculte'] . ' - ' . $promotion['designationPromotion']) ?>
            </option>
        <?php endforeach; ?>

                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php
                        // Récupérer les affectations en fonction des filtres
                        $annee_acad_filtre = isset($_GET['annee_acad_filtre']) ? intval($_GET['annee_acad_filtre']) : null;
                        $promotion_filtre = isset($_GET['promotion_filtre']) ? intval($_GET['promotion_filtre']) : null;
                        $query = "
    SELECT a.*, f.designation AS frais_designation, f.montant AS frais_montant, 
        f.devise, p.designationPromotion AS promotion_nom, 
        CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte_nom,
        aa.designation AS annee_academique
    FROM affectation_frais a
    INNER JOIN frais f ON a.frais_id = f.id
    LEFT JOIN promotion p ON a.promotion_id = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    WHERE 1=1
";

                        


                        
                        // Filtres
if (isset($_GET['annee_filtre']) && !empty($_GET['annee_filtre'])) {
    $query .= " AND f.annee_acad_id = " . intval($_GET['annee_filtre']);
}

if (isset($_GET['promotion_filtre']) && !empty($_GET['promotion_filtre'])) {
    $query .= " AND a.promotion_id = " . intval($_GET['promotion_filtre']);
}

$query .= " ORDER BY a.date_affectation DESC";

$stmt = $connexion->prepare($query);
$stmt->execute();
$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Frais</th>
                                        <th scope="col">Promotion/Étudiant</th>
                                        <th scope="col">Montant</th>
                                        <th scope="col">Date d'affectation</th>
                                        <th scope="col">Statut</th>
                                        <?php if ($niveau_acces !== 'Lecture'): ?>
                                            <th scope="col">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affectations as $index => $affectation): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <?= htmlspecialchars($affectation['frais_designation']) ?>
                                                <small class="d-block text-muted"><?= htmlspecialchars($affectation['annee_academique']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($affectation['promotion_id']): ?>
                                                    <?= htmlspecialchars($affectation['faculte_nom'] . ' - ' . $affectation['promotion_nom']) ?>
                                                    <span class="badge bg-info">Promotion</span>
                                                <?php elseif ($affectation['etudiant_id'] || $affectation['matricule_etudiant']): ?>
                                                    <?= htmlspecialchars($affectation['matricule_etudiant']) ?>
                                                    <span class="badge bg-warning">Étudiant</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($affectation['montant_specifique']): ?>
                                                    <?= number_format($affectation['montant_specifique'], 2) ?> <?= htmlspecialchars($affectation['devise']) ?>
                                                    <small class="d-block text-muted">Montant spécifique</small>
                                                <?php else: ?>
                                                    <?= number_format($affectation['frais_montant'], 2) ?> <?= htmlspecialchars($affectation['devise']) ?>
                                                    <small class="d-block text-muted">Montant standard</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?></td>
                                            <td>
                                                <?php if ($affectation['est_exempte']): ?>
                                                    <span class="badge bg-warning">Exempté</span>
                                                <?php else: ?>
                                                    <?php
                                                    $statut_badge = '';
                                                    switch ($affectation['statut_paiement']) {
                                                        case 'Non payé':
                                                            $statut_badge = 'bg-danger';
                                                            break;
                                                        case 'Partiel':
                                                            $statut_badge = 'bg-warning';
                                                            break;
                                                        case 'Complet':
                                                            $statut_badge = 'bg-success';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statut_badge ?>"><?= $affectation['statut_paiement'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($niveau_acces !== 'Lecture'): ?>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info view-affectation" 
                                                            data-id="<?= $affectation['id'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#viewAffectationModal">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($niveau_acces !== 'Lecture' && $affectation['statut_paiement'] === 'Non payé'): ?>
                                                        <button type="button" class="btn btn-sm btn-warning exemption-affectation" 
                                                                data-id="<?= $affectation['id'] ?>"
                                                                data-is-exempt="<?= $affectation['est_exempte'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#exemptionModal">
                                                            <i class="bi bi-shield-fill-exclamation"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($niveau_acces === 'Administration' && $affectation['statut_paiement'] === 'Non payé'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger delete-affectation" 
                                                                data-id="<?= $affectation['id'] ?>"
                                                                data-bs-toggle="modal" data-bs-target="#deleteAffectationModal">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($affectations)): ?>
                                        <tr>
                                            <td colspan="<?= $niveau_acces !== 'Lecture' ? 7 : 6 ?>" class="text-center">Aucune affectation trouvée</td>
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

<!-- Modal pour ajouter une catégorie de frais -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="action" value="ajouter_categorie">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Ajouter une catégorie de frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="designation" name="designation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="compte_comptable" class="form-label">Compte Comptable</label>
                        <input type="text" class="form-control" id="compte_comptable" name="compte_comptable">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_obligatoire" name="est_obligatoire" value="1" checked>
                                <label class="form-check-label" for="est_obligatoire">
                                    Obligatoire
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_echelonnable" name="est_echelonnable" value="1">
                                <label class="form-check-label" for="est_echelonnable">
                                    Échelonnable
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_remboursable" name="est_remboursable" value="1">
                                <label class="form-check-label" for="est_remboursable">
                                    Remboursable
                                </label>
                            </div>
                        </div>
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

<!-- Modal pour éditer une catégorie de frais -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="modifier_categorie">
                <input type="hidden" name="categorie_id" id="edit_categorie_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Modifier une catégorie de frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_compte_comptable" class="form-label">Compte Comptable</label>
                        <input type="text" class="form-control" id="edit_compte_comptable" name="compte_comptable">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_obligatoire" name="est_obligatoire" value="1">
                                <label class="form-check-label" for="edit_est_obligatoire">
                                    Obligatoire
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_echelonnable" name="est_echelonnable" value="1">
                                <label class="form-check-label" for="edit_est_echelonnable">
                                    Échelonnable
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_remboursable" name="est_remboursable" value="1">
                                <label class="form-check-label" for="edit_est_remboursable">
                                    Remboursable
                                </label>
                            </div>
                        </div>
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

<!-- Modal pour supprimer une catégorie de frais -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="supprimer_categorie">
                <input type="hidden" name="categorie_id" id="delete_categorie_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Supprimer une catégorie de frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Attention! Cette action est irréversible.
                    </div>
                    <p>Êtes-vous sûr de vouloir supprimer cette catégorie de frais? Cette action ne peut pas être annulée et pourrait affecter les frais associés.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un frais -->
<div class="modal fade" id="addFraisModal" tabindex="-1" aria-labelledby="addFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="ajouter_frais">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addFraisModalLabel">Ajouter un frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="categorie_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorie_id" name="categorie_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>"><?= htmlspecialchars($categorie['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="designation_frais" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="designation_frais" name="designation" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="montant" name="montant" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="devise" class="form-label">Devise <span class="text-danger">*</span></label>
                            <select class="form-select" id="devise" name="devise" required>
                                <option value="USD">USD</option>
                                <option value="CDF">CDF</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="annee_acad_id" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select class="form-select" id="annee_acad_id" name="annee_acad_id" required>
                                <option value="">Sélectionner une année</option>
                                <?php foreach ($annees_academiques as $annee): ?>
                                    <option value="<?= $annee['idannee_acad'] ?>"><?= htmlspecialchars($annee['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="cycle" class="form-label">Cycle</label>
                            <select class="form-select" id="cycle" name="cycle">
                                <option value="Tous">Tous</option>
                                <option value="Licence">Licence</option>
                                <option value="Master">Master</option>
                                <option value="Doctorat">Doctorat</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="niveau" class="form-label">Niveau</label>
                            <select class="form-select" id="niveau" name="niveau">
                                <option value="">Tous les niveaux</option>
                                <option value="L1">L1</option>
                                <option value="L2">L2</option>
                                <option value="L3">L3</option>
                                <option value="M1">M1</option>
                                <option value="M2">M2</option>
                                <option value="D">Doctorat</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="date_echeance_globale" class="form-label">Date d'échéance</label>
                            <input type="date" class="form-control" id="date_echeance_globale" name="date_echeance_globale">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="frais_est_obligatoire" name="est_obligatoire" value="1" checked>
                                <label class="form-check-label" for="frais_est_obligatoire">
                                    Obligatoire
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="frais_est_echelonnable" name="est_echelonnable" value="1">
                                <label class="form-check-label" for="frais_est_echelonnable">
                                    Échelonnable
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_requis_inscription" name="est_requis_inscription" value="1" checked>
                                <label class="form-check-label" for="est_requis_inscription">
                                    Requis pour l'inscription
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_requis_examens" name="est_requis_examens" value="1">
                                <label class="form-check-label" for="est_requis_examens">
                                    Requis pour les examens
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="nb-tranches-container" style="display: none;">
                        <div class="mb-3">
                            <label for="nb_tranches_max" class="form-label">Nombre de tranches maximum</label>
                            <input type="number" min="1" max="12" class="form-control" id="nb_tranches_max" name="nb_tranches_max" value="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description_frais" class="form-label">Description</label>
                        <textarea class="form-control" id="description_frais" name="description" rows="3"></textarea>
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

<!-- Modal pour modifier un frais -->
<div class="modal fade" id="editFraisModal" tabindex="-1" aria-labelledby="editFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="modifier_frais">
                <input type="hidden" name="frais_id" id="edit_frais_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="editFraisModalLabel">Modifier un frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_categorie_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_categorie_id2" name="categorie_id" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?= $categorie['id'] ?>"><?= htmlspecialchars($categorie['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="edit_designation_frais" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_designation_frais" name="designation" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_montant" name="montant" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_devise" class="form-label">Devise <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_devise" name="devise" required>
                                <option value="USD">USD</option>
                                <option value="CDF">CDF</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_annee_acad_id" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_annee_acad_id" name="annee_acad_id" required>
                                <option value="">Sélectionner une année</option>
                                <?php foreach ($annees_academiques as $annee): ?>
                                    <option value="<?= $annee['idannee_acad'] ?>"><?= htmlspecialchars($annee['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_cycle" class="form-label">Cycle</label>
                            <select class="form-select" id="edit_cycle" name="cycle">
                                <option value="Tous">Tous</option>
                                <option value="Licence">Licence</option>
                                <option value="Master">Master</option>
                                <option value="Doctorat">Doctorat</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_niveau" class="form-label">Niveau</label>
                            <select class="form-select" id="edit_niveau" name="niveau">
                                <option value="">Tous les niveaux</option>
                                <option value="L1">L1</option>
                                <option value="L2">L2</option>
                                <option value="L3">L3</option>
                                <option value="M1">M1</option>
                                <option value="M2">M2</option>
                                <option value="D">Doctorat</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_date_echeance_globale" class="form-label">Date d'échéance</label>
                            <input type="date" class="form-control" id="edit_date_echeance_globale" name="date_echeance_globale">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_frais_est_obligatoire" name="est_obligatoire" value="1">
                                <label class="form-check-label" for="edit_frais_est_obligatoire">
                                    Obligatoire
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_frais_est_echelonnable" name="est_echelonnable" value="1">
                                <label class="form-check-label" for="edit_frais_est_echelonnable">
                                    Échelonnable
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_requis_inscription" name="est_requis_inscription" value="1">
                                <label class="form-check-label" for="edit_est_requis_inscription">
                                    Requis pour l'inscription
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_est_requis_examens" name="est_requis_examens" value="1">
                                <label class="form-check-label" for="edit_est_requis_examens">
                                    Requis pour les examens
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="edit-nb-tranches-container">
                        <div class="mb-3">
                            <label for="edit_nb_tranches_max" class="form-label">Nombre de tranches maximum</label>
                            <input type="number" min="1" max="12" class="form-control" id="edit_nb_tranches_max" name="nb_tranches_max" value="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description_frais" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description_frais" name="description" rows="3"></textarea>
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

<!-- Modal pour visualiser un frais -->
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

<!-- Modal pour supprimer un frais -->
<div class="modal fade" id="deleteFraisModal" tabindex="-1" aria-labelledby="deleteFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="supprimer_frais">
                <input type="hidden" name="frais_id" id="delete_frais_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteFraisModalLabel">Supprimer un frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Attention! Cette action est irréversible.
                    </div>
                    <p>Êtes-vous sûr de vouloir supprimer ce frais? Cette action ne peut pas être annulée et supprimera toutes les tranches de paiement associées.</p>
                    <p><strong>Note:</strong> Les affectations et paiements déjà effectués resteront en place.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour configurer les tranches de paiement -->
<div class="modal fade" id="configTranchesModal" tabindex="-1" aria-labelledby="configTranchesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="configTranchesModalLabel">Configuration des tranches de paiement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="frais-info mb-4">
                    <h6>Informations du frais</h6>
                    <p><strong>Désignation:</strong> <span id="tranche_frais_designation"></span></p>
                    <p><strong>Montant total:</strong> <span id="tranche_frais_montant"></span> <span id="tranche_frais_devise"></span></p>
                    <p><strong>Nombre de tranches configurable:</strong> <span id="tranche_frais_nb_tranches"></span></p>
                </div>
                
                <form action="controller/finance_operations.php" method="POST" id="tranchesForm">
                    <input type="hidden" name="action" value="configurer_tranches">
                    <input type="hidden" name="frais_id" id="tranche_frais_id">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tranchesTable">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Désignation</th>
                                    <th>Pourcentage (%)</th>
                                    <th>Montant</th>
                                    <th>Date d'échéance</th>
                                    <th>Requis pour</th>
                                </tr>
                            </thead>
                            <tbody id="tranchesTableBody">
                                <!-- Les tranches seront ajoutées dynamiquement ici -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> Le total des pourcentages doit être de 100%. La somme des montants doit correspondre au montant total du frais.
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-success" id="addTrancheBtn">
                            <i class="bi bi-plus-circle"></i> Ajouter une tranche
                        </button>
                        
                        <div>
                            <span class="me-3">
                                <strong>Total: </strong>
                                <span id="totalPourcentage">0</span>% / 
                                <span id="totalMontant">0.00</span> <span id="tranche_devise_total"></span>
                            </span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveTrancheBtn">Enregistrer les tranches</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour affecter des frais -->
<div class="modal fade" id="affectFraisModal" tabindex="-1" aria-labelledby="affectFraisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="affecter_frais">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="affectFraisModalLabel">Affecter des frais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="affectation_frais_id" class="form-label">Frais à affecter <span class="text-danger">*</span></label>
                            <select class="form-select" id="affectation_frais_id" name="frais_id" required>
                                <option value="">Sélectionner un frais</option>
                                <?php 
                                // Récupérer une liste des frais actifs pour l'année académique en cours
                                $stmt = $connexion->prepare("
                                    SELECT f.*, c.designation AS categorie_nom, a.intitule AS annee_academique 
                                    FROM frais f
                                    LEFT JOIN categories_frais c ON f.categorie_id = c.id
                                    LEFT JOIN annee_acad a ON f.annee_acad_id = a.id_annee
                                    ORDER BY f.annee_acad_id DESC, f.designation ASC
                                ");
                                $stmt->execute();
                                $frais_liste = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($frais_liste as $frais_item): 
                                ?>
                                    <option value="<?= $frais_item['id'] ?>">
                                        <?= htmlspecialchars($frais_item['designation']) ?> - 
                                        <?= number_format($frais_item['montant'], 2) ?> <?= $frais_item['devise'] ?> 
                                        (<?= htmlspecialchars($frais_item['annee_academique']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="type_affectation" class="form-label">Type d'affectation <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_affectation" name="type_affectation" required>
                                <option value="promotion">Promotion entière</option>
                                <option value="etudiant">Étudiant spécifique</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="promotion_container">
                        <div class="mb-3">
                            <label for="promotion_id" class="form-label">Promotion <span class="text-danger">*</span></label>
                            <select class="form-select" id="promotion_id" name="promotion_id">
                                <option value="">Sélectionner une promotion</option>
                                <?php foreach ($promotions as $promotion): ?>
    <option value="<?= $promotion['idPromotion'] ?>" <?= (isset($_GET['promotion_filtre']) && $_GET['promotion_filtre'] == $promotion['idPromotion']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($promotion['faculte'] . ' - ' . $promotion['designationPromotion']) ?>
    </option>
<?php endforeach; ?>


                            </select>
                        </div>
                    </div>
                    
                    <div id="etudiant_container" style="display: none;">
                        <div class="mb-3">
                            <label for="matricule_etudiant" class="form-label">Matricule de l'étudiant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="matricule_etudiant" name="matricule_etudiant">
                            <small class="form-text text-muted">Entrez le matricule exact de l'étudiant</small>
                        </div>
                        
                        <div id="etudiant_info" class="alert alert-info" style="display: none;">
                            Les informations de l'étudiant s'afficheront ici après vérification du matricule.
                        </div>
                    </div>
                    
                    <div class="row mb-3 mt-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="montant_specifique_check">
                                <label class="form-check-label" for="montant_specifique_check">Définir un montant spécifique</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="montant_specifique_container" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="montant_specifique" class="form-label">Montant spécifique</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="montant_specifique" name="montant_specifique">
                            </div>
                            <div class="col-md-6">
                                <label for="devise_specifique" class="form-label">Devise</label>
                                <select class="form-select" id="devise_specifique" name="devise_specifique">
                                    <option value="USD">USD</option>
                                    <option value="CDF">CDF</option>
                                    <option value="EUR">EUR</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_echeance_affectation" class="form-label">Date d'échéance (optionnelle)</label>
                        <input type="date" class="form-control" id="date_echeance_affectation" name="date_echeance">
                        <small class="form-text text-muted">Si non spécifiée, la date d'échéance globale du frais sera utilisée</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif_specifique" class="form-label">Motif spécifique (optionnel)</label>
                        <textarea class="form-control" id="motif_specifique" name="motif_specifique" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Affecter le frais</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="exemption_frais">
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
            <form action="controller/finance_operations.php" method="POST">
                <input type="hidden" name="action" value="supprimer_affectation">
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
    // Gestion de l'affichage des champs pour les tranches de paiement
    const fraisEstEchelonnable = document.getElementById('frais_est_echelonnable');
    const nbTranchesContainer = document.querySelector('.nb-tranches-container');
    
    if (fraisEstEchelonnable) {
        fraisEstEchelonnable.addEventListener('change', function() {
            nbTranchesContainer.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Gestion de l'affichage pour l'édition des tranches
    const editFraisEstEchelonnable = document.getElementById('edit_frais_est_echelonnable');
    const editNbTranchesContainer = document.querySelector('.edit-nb-tranches-container');
    
    if (editFraisEstEchelonnable) {
        editFraisEstEchelonnable.addEventListener('change', function() {
            editNbTranchesContainer.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Gestion des boutons d'édition de catégorie
    const editCategoryButtons = document.querySelectorAll('.edit-category');
    editCategoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-id');
            const designation = this.getAttribute('data-designation');
            const description = this.getAttribute('data-description');
            const compteComptable = this.getAttribute('data-compte-comptable');
            const estObligatoire = this.getAttribute('data-est-obligatoire') === '1';
            const estEchelonnable = this.getAttribute('data-est-echelonnable') === '1';
            const estRemboursable = this.getAttribute('data-est-remboursable') === '1';
            
            // Remplir le formulaire d'édition
            document.getElementById('edit_categorie_id').value = categoryId;
            document.getElementById('edit_designation').value = designation;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_compte_comptable').value = compteComptable;
            document.getElementById('edit_est_obligatoire').checked = estObligatoire;
            document.getElementById('edit_est_echelonnable').checked = estEchelonnable;
            document.getElementById('edit_est_remboursable').checked = estRemboursable;
        });
    });
    
    // Gestion des boutons de suppression de catégorie
    const deleteCategoryButtons = document.querySelectorAll('.delete-category');
    deleteCategoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-id');
            document.getElementById('delete_categorie_id').value = categoryId;
        });
    });
    
    // Gestion des boutons d'édition de frais
    const editFraisButtons = document.querySelectorAll('.edit-frais');
    editFraisButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fraisId = this.getAttribute('data-id');
            fetch(`controller/get_frais_details.php?id=${fraisId}&type=academique`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Erreur: ' + data.error);
                        return;
                    }
                    
                    // Remplir le formulaire d'édition
                    document.getElementById('edit_frais_id').value = fraisId;
                    document.getElementById('edit_categorie_id').value = data.categorie_id;
                    document.getElementById('edit_designation_frais').value = data.designation;
                    document.getElementById('edit_montant').value = data.montant;
                    document.getElementById('edit_devise').value = data.devise;
                    document.getElementById('edit_annee_acad_id').value = data.annee_acad_id;
                    document.getElementById('edit_cycle').value = data.cycle || 'Tous';
                    document.getElementById('edit_niveau').value = data.niveau || '';
                    
                    if (data.date_echeance_globale) {
                        document.getElementById('edit_date_echeance_globale').value = data.date_echeance_globale.split(' ')[0];
                    } else {
                        document.getElementById('edit_date_echeance_globale').value = '';
                    }
                    
                    document.getElementById('edit_frais_est_obligatoire').checked = data.est_obligatoire === '1';
                    document.getElementById('edit_frais_est_echelonnable').checked = data.est_echelonnable === '1';
                    document.getElementById('edit_est_requis_inscription').checked = data.est_requis_inscription === '1';
                    document.getElementById('edit_est_requis_examens').checked = data.est_requis_examens === '1';
                    document.getElementById('edit_nb_tranches_max').value = data.nb_tranches_max || 1;
                    document.getElementById('edit_description_frais').value = data.description || '';
                    
                    // Afficher/masquer le conteneur des tranches
                    editNbTranchesContainer.style.display = data.est_echelonnable === '1' ? 'block' : 'none';
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la récupération des données.');
                });
        });
    });
    
    // Gestion des boutons de visualisation de frais
    const viewFraisButtons = document.querySelectorAll('.view-frais');
    viewFraisButtons.forEach(button => {
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
            
            fetch(`controller/get_frais_details.php?id=${fraisId}&type=academique`)
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
                            <p><strong>Obligatoire:</strong> ${data.est_obligatoire === '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Échelonnable:</strong> ${data.est_echelonnable === '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour inscription:</strong> ${data.est_requis_inscription === '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour examens:</strong> ${data.est_requis_examens === '1' ? 'Oui' : 'Non'}</p>
                            <p><strong>Requis pour délibération:</strong> ${data.est_requis_deliberation === '1' ? 'Oui' : 'Non'}</p>
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
                    
                    // Si le frais est échelonnable, rechercher les configurations de tranches
                    if (data.est_echelonnable === '1') {
                        fetch(`controller/get_tranches.php?frais_id=${fraisId}`)
                            .then(response => response.json())
                            .then(tranches => {
                                html += `
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h6>Configurations des tranches</h6>
                                        ${tranches.length > 0 ? `
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
                                                            ${tranche.est_requis_deliberation === '1' ? '<span class="badge bg-success">Délibération</span>' : ''}
                                                        </td>
                                                    </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                        ` : '<div class="alert alert-info">Aucune tranche configurée pour ce frais.</div>'}
                                    </div>
                                </div>
                                `;
                                container.innerHTML = html;
                            })
                            .catch(error => {
                                console.error('Erreur:', error);
                                html += '<div class="alert alert-warning mt-3">Impossible de charger les configurations de tranches.</div>';
                                container.innerHTML = html;
                            });
                    } else {
                        container.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    container.innerHTML = `<div class="alert alert-danger">Une erreur est survenue lors du chargement des données.</div>`;
                });
        });
    });
    
    // Gestion des boutons de suppression de frais
    const deleteFraisButtons = document.querySelectorAll('.delete-frais');
    deleteFraisButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fraisId = this.getAttribute('data-id');
            document.getElementById('delete_frais_id').value = fraisId;
        });
    });
    
    // Gestion des boutons de configuration des tranches
    const configTranchesButtons = document.querySelectorAll('.config-tranches');
    configTranchesButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fraisId = this.getAttribute('data-id');
            const fraisDesignation = this.getAttribute('data-designation');
            const fraisMontant = parseFloat(this.getAttribute('data-montant'));
            const fraisDevise = this.getAttribute('data-devise');
            const fraisNbTranches = parseInt(this.getAttribute('data-nb-tranches'));
            
            // Mettre à jour les informations du frais dans le modal
            document.getElementById('tranche_frais_id').value = fraisId;
            document.getElementById('tranche_frais_designation').textContent = fraisDesignation;
            document.getElementById('tranche_frais_montant').textContent = fraisMontant.toLocaleString('fr-FR', {minimumFractionDigits: 2});
            document.getElementById('tranche_frais_devise').textContent = fraisDevise;
            document.getElementById('tranche_frais_nb_tranches').textContent = fraisNbTranches;
            document.getElementById('tranche_devise_total').textContent = fraisDevise;
            
            // Charger les tranches existantes
            loadTranches(fraisId, fraisMontant, fraisDevise);
        });
    });
    
    // Fonction pour charger les tranches existantes
    function loadTranches(fraisId, montantTotal, devise) {
        const tableBody = document.getElementById('tranchesTableBody');
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center">Chargement...</td></tr>';
        
        fetch(`controller/get_tranches.php?frais_id=${fraisId}`)
            .then(response => response.json())
            .then(tranches => {
                if (tranches.length === 0) {
                    // Pas de tranches, on ajoute une tranche par défaut à 100%
                    tableBody.innerHTML = createTrancheRow(1, 'Paiement complet', 100, montantTotal, '', false, false, false);
                } else {
                    tableBody.innerHTML = tranches.map(tranche => {
                        return createTrancheRow(
                            tranche.numero_tranche,
                            tranche.designation,
                            tranche.pourcentage,
                            tranche.montant_fixe || (montantTotal * tranche.pourcentage / 100),
                            tranche.date_echeance_fixe ? tranche.date_echeance_fixe.split(' ')[0] : '',
                            tranche.est_requis_inscription === '1',
                            tranche.est_requis_examens === '1',
                            tranche.est_requis_deliberation === '1'
                        );
                    }).join('');
                }
                
                // Calculer et afficher le total
                updateTotal();
            })
            .catch(error => {
                console.error('Erreur:', error);
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erreur lors du chargement des tranches</td></tr>';
            });
    }
    
    // Fonction pour créer une ligne de tranche
    function createTrancheRow(numero, designation, pourcentage, montant, dateEcheance, reqInscription, reqExamens, reqDeliberation) {
        return `
        <tr class="tranche-row">
            <td>
                <input type="hidden" name="tranche_numero[]" value="${numero}">
                ${numero}
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="tranche_designation[]" value="${designation}" required>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm tranche-pourcentage" name="tranche_pourcentage[]" value="${pourcentage}" min="0" max="100" step="0.01" required onchange="updateMontant(this)">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm tranche-montant" name="tranche_montant[]" value="${montant}" min="0" step="0.01" required onchange="updatePourcentage(this)">
            </td>
            <td>
                <input type="date" class="form-control form-control-sm" name="tranche_date_echeance[]" value="${dateEcheance}">
            </td>
            <td>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="tranche_requis_inscription[${numero}]" value="1" ${reqInscription ? 'checked' : ''}>
                    <label class="form-check-label small">Inscription</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="tranche_requis_examens[${numero}]" value="1" ${reqExamens ? 'checked' : ''}>
                    <label class="form-check-label small">Examens</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="tranche_requis_deliberation[${numero}]" value="1" ${reqDeliberation ? 'checked' : ''}>
                    <label class="form-check-label small">Délibération</label>
                </div>
                <button type="button" class="btn btn-sm btn-danger float-end delete-tranche-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
        `;
    }
    
    // Mettre à jour le montant en fonction du pourcentage
    window.updateMontant = function(input) {
        const row = input.closest('tr');
        const pourcentage = parseFloat(input.value) || 0;
        const montantTotal = parseFloat(document.getElementById('tranche_frais_montant').textContent.replace(/\s/g, '').replace(',', '.')) || 0;
        const montantCalcule = (montantTotal * pourcentage / 100).toFixed(2);
        
        row.querySelector('.tranche-montant').value = montantCalcule;
        updateTotal();
    };
    
    // Mettre à jour le pourcentage en fonction du montant
    window.updatePourcentage = function(input) {
        const row = input.closest('tr');
        const montant = parseFloat(input.value) || 0;
        const montantTotal = parseFloat(document.getElementById('tranche_frais_montant').textContent.replace(/\s/g, '').replace(',', '.')) || 0;
        const pourcentageCalcule = montantTotal > 0 ? ((montant / montantTotal) * 100).toFixed(2) : 0;
        
        row.querySelector('.tranche-pourcentage').value = pourcentageCalcule;
        updateTotal();
    };
    
    // Calcul du total des pourcentages et montants
    function updateTotal() {
        const rows = document.querySelectorAll('.tranche-row');
        let totalPourcentage = 0;
        let totalMontant = 0;
        
        rows.forEach(row => {
            totalPourcentage += parseFloat(row.querySelector('.tranche-pourcentage').value) || 0;
            totalMontant += parseFloat(row.querySelector('.tranche-montant').value) || 0;
        });
        
        document.getElementById('totalPourcentage').textContent = totalPourcentage.toFixed(2);
        document.getElementById('totalMontant').textContent = totalMontant.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Vérifier si le total est correct (100%)
        const totalElement = document.getElementById('totalPourcentage');
        if (Math.abs(totalPourcentage - 100) > 0.1) {
            totalElement.classList.add('text-danger');
            totalElement.classList.remove('text-success');
        } else {
            totalElement.classList.add('text-success');
            totalElement.classList.remove('text-danger');
        }
    }
    
    // Ajouter une nouvelle tranche
    document.getElementById('addTrancheBtn').addEventListener('click', function() {
        const tableBody = document.getElementById('tranchesTableBody');
        const rows = tableBody.querySelectorAll('tr');
        const newNumero = rows.length + 1;
        
        // Calculer le montant restant si possible
        const totalPourcentage = parseFloat(document.getElementById('totalPourcentage').textContent) || 0;
        const pourcentageRestant = Math.max(0, 100 - totalPourcentage).toFixed(2);
        
        const montantTotal = parseFloat(document.getElementById('tranche_frais_montant').textContent.replace(/\s/g, '').replace(',', '.')) || 0;
        const montantCalcule = (montantTotal * pourcentageRestant / 100).toFixed(2);
        
        const newRow = createTrancheRow(newNumero, `Tranche ${newNumero}`, pourcentageRestant, montantCalcule, '', false, false, false);
        tableBody.insertAdjacentHTML('beforeend', newRow);
        
        // Attacher les écouteurs d'événement pour supprimer
        attachDeleteTrancheListeners();
        
        // Mettre à jour les totaux
        updateTotal();
    });
    
    // Supprimer une tranche
    function attachDeleteTrancheListeners() {
        document.querySelectorAll('.delete-tranche-btn').forEach(button => {
            button.addEventListener('click', function() {
                if (document.querySelectorAll('.tranche-row').length <= 1) {
                    alert('Vous devez conserver au moins une tranche.');
                    return;
                }
                
                if (confirm('Êtes-vous sûr de vouloir supprimer cette tranche?')) {
                    const row = this.closest('tr');
                    row.remove();
                    
                    // Renuméroter les tranches
                    const rows = document.querySelectorAll('.tranche-row');
                    rows.forEach((row, index) => {
                        const numero = index + 1;
                        row.querySelector('td:first-child').innerHTML = `
                            <input type="hidden" name="tranche_numero[]" value="${numero}">
                            ${numero}
                        `;
                        
                        // Mettre à jour les indices des checkboxes
                        const checkboxes = row.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            const name = checkbox.getAttribute('name');
                            checkbox.setAttribute('name', name.replace(/\[\d+\]/, `[${numero}]`));
                        });
                    });
                    
                    // Mettre à jour les totaux
                    updateTotal();
                }
            });
        });
    }
    
    // Sauvegarder les tranches
    document.getElementById('saveTrancheBtn').addEventListener('click', function() {
        const totalPourcentage = parseFloat(document.getElementById('totalPourcentage').textContent) || 0;
        
        if (Math.abs(totalPourcentage - 100) > 0.1) {
            alert('Le total des pourcentages doit être égal à 100%.');
            return;
        }
        
        // Soumettre le formulaire
        document.getElementById('tranchesForm').submit();
    });
    
    // Gestion du type d'affectation
    const typeAffectation = document.getElementById('type_affectation');
    const promotionContainer = document.getElementById('promotion_container');
    const etudiantContainer = document.getElementById('etudiant_container');
    
    if (typeAffectation) {
        typeAffectation.addEventListener('change', function() {
            if (this.value === 'promotion') {
                promotionContainer.style.display = 'block';
                etudiantContainer.style.display = 'none';
                document.getElementById('promotion_id').setAttribute('required', 'required');
                document.getElementById('matricule_etudiant').removeAttribute('required');
            } else {
                promotionContainer.style.display = 'none';
                etudiantContainer.style.display = 'block';
                document.getElementById('promotion_id').removeAttribute('required');
                document.getElementById('matricule_etudiant').setAttribute('required', 'required');
            }
        });
    }
    
    // Gestion du montant spécifique
    const montantSpecifiqueCheck = document.getElementById('montant_specifique_check');
    const montantSpecifiqueContainer = document.getElementById('montant_specifique_container');
    
    if (montantSpecifiqueCheck) {
        montantSpecifiqueCheck.addEventListener('change', function() {
            montantSpecifiqueContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                document.getElementById('montant_specifique').value = '';
            }
        });
    }
    
    // Vérification du matricule étudiant
    const matriculeInput = document.getElementById('matricule_etudiant');
    const etudiantInfo = document.getElementById('etudiant_info');
    
    if (matriculeInput) {
        matriculeInput.addEventListener('blur', function() {
            const matricule = this.value.trim();
            if (matricule.length > 3) {
                etudiantInfo.style.display = 'block';
                etudiantInfo.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Vérification...';
                
                fetch(`controller/get_etudiant_info.php?matricule=${matricule}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            etudiantInfo.className = 'alert alert-danger';
                            etudiantInfo.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${data.error}`;
                        } else {
                            etudiantInfo.className = 'alert alert-success';
                            etudiantInfo.innerHTML = `
                                <p><strong>Nom:</strong> ${data.nom}</p>
                                <p><strong>Promotion:</strong> ${data.promotion}</p>
                                <p><strong>Faculté:</strong> ${data.faculte}</p>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        etudiantInfo.className = 'alert alert-danger';
                        etudiantInfo.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> Erreur lors de la vérification.`;
                    });
            } else if (matricule.length > 0) {
                etudiantInfo.style.display = 'block';
                etudiantInfo.className = 'alert alert-warning';
                etudiantInfo.innerHTML = 'Veuillez saisir un matricule complet.';
            } else {
                etudiantInfo.style.display = 'none';
            }
        });
    }
    
    // Gestion des exemptions
    const exemptionButtons = document.querySelectorAll('.exemption-btn');
    exemptionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            const isExempted = this.getAttribute('data-exempted') === '1';
            const container = document.getElementById('exemption_status_container');
            
            document.getElementById('exemption_affectation_id').value = affectationId;
            document.getElementById('current_exemption').value = isExempted ? '1' : '0';
            
            if (isExempted) {
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
                    <i class="bi bi-info-circle-fill"></i> Cette affectation n'est actuellement <strong>pas
 exemptée</strong>.
                </div>`;
                document.getElementById('exemptionSubmitBtn').textContent = 'Appliquer l\'exemption';
            }
            
            // Réinitialiser les champs
            document.getElementById('motif_exemption').value = '';
            document.getElementById('reference_decision').value = '';
        });
    });
    
    // Gestion des boutons de visualisation d'une affectation
    const viewAffectationButtons = document.querySelectorAll('.view-affectation');
    viewAffectationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            const container = document.getElementById('affectationDetailsContainer');
            
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            `;
            
            fetch(`controller/get_affectation_details.php?id=${affectationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Formater les données
                    const dateAffectation = new Date(data.date_affectation).toLocaleDateString('fr-FR');
                    const dateEcheance = data.date_echeance ? new Date(data.date_echeance).toLocaleDateString('fr-FR') : 'Non définie';
                    const dateDernierPaiement = data.date_dernier_paiement ? new Date(data.date_dernier_paiement).toLocaleDateString('fr-FR') : 'Aucun paiement';
                    
                    // Construction du HTML
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Frais</h6>
                            <p><strong>Désignation:</strong> ${data.frais_designation}</p>
                            <p><strong>Catégorie:</strong> ${data.categorie_nom}</p>
                            <p><strong>Montant:</strong> ${parseFloat(data.montant_specifique || data.frais_montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</p>
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
                            <p><strong>Dernier paiement:</strong> ${dateDernierPaiement}</p>
                        </div>
                    </div>`;
                    
                    // Afficher les informations spécifiques selon le type d'affectation
                    if (data.promotion_id) {
                        // Dans la partie qui construit l'HTML des détails d'affectation
html += `
<div class="row mt-3">
    <div class="col-12">
        <h6>Promotion affectée</h6>
        <p><strong>Promotion:</strong> ${data.promotion_nom}</p>
        <p><strong>Orientation/Section:</strong> ${data.faculte_nom}</p>
    </div>
</div>`;


                    } else if (data.etudiant_id || data.matricule_etudiant) {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Étudiant affecté</h6>
                                <p><strong>Matricule:</strong> ${data.matricule_etudiant}</p>
                                <p><strong>Nom:</strong> ${data.etudiant_nom || 'Non disponible'}</p>
                                <p><strong>Promotion:</strong> ${data.etudiant_promotion || 'Non disponible'}</p>
                            </div>
                        </div>`;
                    }
                    
                    // Afficher les informations d'exemption si applicable
                    if (data.est_exempte === '1') {
                        html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle-fill"></i> Exemption</h6>
                                    <p><strong>Motif:</strong> ${data.motif_exemption}</p>
                                    ${data.reference_decision ? `<p><strong>Référence de décision:</strong> ${data.reference_decision}</p>` : ''}
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
                                    <table class="table table-sm table-bordered">
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
                    
                    // Afficher les informations de tranche si applicable
                    if (data.tranches && data.tranches.length > 0) {
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
                                                <th>Montant payé</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.tranches.map(tranche => `
                                            <tr>
                                                <td>${tranche.numero_tranche}</td>
                                                <td>${tranche.designation}</td>
                                                <td>${parseFloat(tranche.montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</td>
                                                <td>${tranche.date_echeance ? new Date(tranche.date_echeance).toLocaleDateString('fr-FR') : '-'}</td>
                                                <td>
                                                    <span class="badge ${tranche.statut_paiement === 'Complet' ? 'bg-success' : (tranche.statut_paiement === 'Partiel' ? 'bg-warning' : 'bg-danger')}">
                                                        ${tranche.statut_paiement}
                                                    </span>
                                                </td>
                                                <td>${parseFloat(tranche.montant_paye || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</td>
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
                    container.innerHTML = `<div class="alert alert-danger">Une erreur est survenue lors du chargement des données.</div>`;
                });
        });
    });
    
    // Gestion des boutons de suppression d'une affectation
    const deleteAffectationButtons = document.querySelectorAll('.delete-affectation');
    deleteAffectationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const affectationId = this.getAttribute('data-id');
            document.getElementById('delete_affectation_id').value = affectationId;
        });
    });
    
    // Initialiser les écouteurs d'événement pour la suppression des tranches
    attachDeleteTrancheListeners();
});
</script>

<?php include "./views/include/footer.php"; ?>



