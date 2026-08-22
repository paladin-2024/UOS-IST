<?php
include "./views/include/header.php";

// Ajouter le CSS personnalisé
echo '<link href="assets/css/declarations_paiements.css" rel="stylesheet">';

// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer l'idAgent de l'utilisateur connecté
$stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

// Vérifier si l'utilisateur a des responsabilités dans des sections
$stmt_sections = $connexion->prepare("
    SELECT DISTINCT section_idsection 
    FROM responsable_section 
    WHERE \"idUser\" = :idUser
");
$stmt_sections->bindParam(':idUser', $idUser);
$stmt_sections->execute();
$user_sections = $stmt_sections->fetchAll(PDO::FETCH_COLUMN);
$has_section_responsibility = !empty($user_sections);

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

// Récupérer les caisses actives
$stmt = $connexion->prepare("SELECT id, designation, devise FROM caisses WHERE est_actif = 1 ORDER BY designation");
$stmt->execute();
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les comptes bancaires actifs
$stmt = $connexion->prepare("SELECT id, nom_banque, intitule_compte, numero_compte, devise FROM comptes_bancaires WHERE est_actif = 1 ORDER BY nom_banque, intitule_compte");
$stmt->execute();
$comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtres
$statut_filtre = isset($_GET['statut']) ? $_GET['statut'] : 'en_attente';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$matricule_filtre = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';
$frais_filtre = isset($_GET['frais']) ? (int)$_GET['frais'] : null;

// Récupérer la liste des frais pour le filtre
$stmt = $connexion->prepare("
    SELECT f.id, f.designation, aa.designation AS annee_academique 
    FROM frais f 
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    ORDER BY aa.designation DESC, f.designation ASC
");
$stmt->execute();
$liste_frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les déclarations de paiement
$sql = "
    SELECT dp.*, 
           e.noms AS etudiant_nom,
           f.designation AS frais_designation,
           af.id AS affectation_id,
           af.montant_specifique,
           f.montant AS montant_frais,
           f.devise AS devise_frais,
           aa.designation AS annee_academique,
           p.\"designationPromotion\" AS promotion_nom,
           u.\"nomUser\" AS validateur_nom,
           (SELECT COALESCE(SUM(pf.montant), 0) 
            FROM paiements_frais pf 
            WHERE pf.affectation_id = af.id 
            AND pf.matricule_etudiant = dp.matricule_etudiant) AS montant_deja_paye,
           CASE 
               WHEN dp.statut_validation = 'validé' THEN 'success'
               WHEN dp.statut_validation = 'rejeté' THEN 'danger'
               ELSE 'warning'
           END AS badge_color
    FROM declarations_paiement dp
    INNER JOIN etudiant e ON dp.matricule_etudiant = e.matricule
    INNER JOIN affectation_frais af ON dp.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN t_users u ON dp.valide_par = u.\"idUser\"";

// Si l'utilisateur a des responsabilités de section, filtrer par ces sections
if ($has_section_responsibility) {
    $sql .= "
    INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
    INNER JOIN section s ON o.section_idsection = s.idsection
    WHERE s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
} else {
    $sql .= " WHERE 1=1";
}

// Appliquer les filtres
if (!empty($statut_filtre)) {
    $sql .= " AND dp.statut_validation = :statut";
}
if (!empty($date_debut)) {
    $sql .= " AND DATE(dp.date_declaration) >= :date_debut";
}
if (!empty($date_fin)) {
    $sql .= " AND DATE(dp.date_declaration) <= :date_fin";
}
if (!empty($matricule_filtre)) {
    $sql .= " AND dp.matricule_etudiant LIKE :matricule";
}
if (!empty($frais_filtre)) {
    $sql .= " AND f.id = :frais_id";
}

$sql .= " GROUP BY dp.id ORDER BY dp.date_declaration DESC";

$stmt = $connexion->prepare($sql);

if (!empty($statut_filtre)) {
    $stmt->bindParam(':statut', $statut_filtre);
}
if (!empty($date_debut)) {
    $stmt->bindParam(':date_debut', $date_debut);
}
if (!empty($date_fin)) {
    $stmt->bindParam(':date_fin', $date_fin);
}
if (!empty($matricule_filtre)) {
    $matricule_like = "%$matricule_filtre%";
    $stmt->bindParam(':matricule', $matricule_like);
}
if (!empty($frais_filtre)) {
    $stmt->bindParam(':frais_id', $frais_filtre);
}

$stmt->execute();
$declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques globales (avec filtres)
$sql_stats = "
    SELECT 
        COUNT(DISTINCT dp.id) as total,
        COUNT(DISTINCT CASE WHEN dp.statut_validation = 'en_attente' THEN dp.id END) as en_attente,
        COUNT(DISTINCT CASE WHEN dp.statut_validation = 'validé' THEN dp.id END) as valide,
        COUNT(DISTINCT CASE WHEN dp.statut_validation = 'rejeté' THEN dp.id END) as rejete
    FROM declarations_paiement dp
    INNER JOIN etudiant e ON dp.matricule_etudiant = e.matricule
    INNER JOIN affectation_frais af ON dp.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion";

// Si l'utilisateur a des responsabilités de section, filtrer les statistiques aussi
if ($has_section_responsibility) {
    $sql_stats .= "
    INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
    INNER JOIN section s ON o.section_idsection = s.idsection
    WHERE s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
} else {
    $sql_stats .= " WHERE 1=1";
}

// Appliquer le filtre par frais aux statistiques
if (!empty($frais_filtre)) {
    $sql_stats .= " AND f.id = :frais_id_stats";
}

$stmt_stats = $connexion->prepare($sql_stats);
if (!empty($frais_filtre)) {
    $stmt_stats->bindParam(':frais_id_stats', $frais_filtre);
}
$stmt_stats->execute();
$stats_result = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$stats = [
    'en_attente' => (int)$stats_result['en_attente'],
    'validé' => (int)$stats_result['valide'],
    'rejeté' => (int)$stats_result['rejete'],
    'total' => (int)$stats_result['total']
];
?>

<main id="main" class="main">
    <div class="pagetitle mb-3">
        <h1 class="h5">Déclarations de Paiements</h1>
        <nav class="mb-0">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Déclarations</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show py-2" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close py-0" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($has_section_responsibility): 
            // Récupérer les noms des sections
            $sections_names_sql = "SELECT \"designationSection\" FROM section WHERE idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
            $stmt_names = $connexion->prepare($sections_names_sql);
            $stmt_names->execute();
            $sections_names = $stmt_names->fetchAll(PDO::FETCH_COLUMN);
        ?>
            <div class="alert alert-info alert-dismissible fade show py-2 mb-3" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Filtrage actif:</strong> Sections: <strong><?= implode(', ', $sections_names) ?></strong>
                <button type="button" class="btn-close py-0" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body py-2">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-0">
                                    En attente
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['en_attente'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-clock-history fa-lg text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body py-2">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-0">
                                    Validées
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['validé'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-check-circle fa-lg text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body py-2">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-0">
                                    Rejetées
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['rejeté'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-x-circle fa-lg text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body py-2">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-0">
                                    Total
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?= $stats['total'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-file-earmark-text fa-lg text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <h5 class="card-title mb-2">Filtres</h5>
                        <form action="" method="GET" class="row g-2">
                            <input type="hidden" name="view" value="finance/declarations_paiements_etudiants">

                            <div class="col-md-2">
                                <label for="statut" class="form-label mb-1">Statut</label>
                                <select class="form-select form-select-sm" id="statut" name="statut">
                                    <option value="">Tous</option>
                                    <option value="en_attente" <?= $statut_filtre === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="validé" <?= $statut_filtre === 'validé' ? 'selected' : '' ?>>Validées</option>
                                    <option value="rejeté" <?= $statut_filtre === 'rejeté' ? 'selected' : '' ?>>Rejetées</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="frais" class="form-label mb-1">Frais</label>
                                <select class="form-select form-select-sm" id="frais" name="frais">
                                    <option value="">Tous les frais</option>
                                    <?php foreach ($liste_frais as $frais): ?>
                                        <option value="<?= $frais['id'] ?>" <?= $frais_filtre == $frais['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($frais['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="date_debut" class="form-label mb-1">Date début</label>
                                <input type="date" class="form-control form-control-sm" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                            </div>

                            <div class="col-md-2">
                                <label for="date_fin" class="form-label mb-1">Date fin</label>
                                <input type="date" class="form-control form-control-sm" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                            </div>

                            <div class="col-md-2">
                                <label for="matricule" class="form-label mb-1">Matricule</label>
                                <input type="text" class="form-control form-control-sm" id="matricule" name="matricule" value="<?= htmlspecialchars($matricule_filtre) ?>" placeholder="Matricule">
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-search"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des déclarations -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="card-title mb-0">Déclarations (<?= count($declarations) ?>)</h5>
                            <form action="controller/supprimer_doublons_declarations.php" method="POST" 
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les déclarations en doublon (en attente) ? Seule la première déclaration de chaque frais sera conservée.');">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Supprimer les doublons
                                </button>
                            </form>
                        </div>

                        <?php if (empty($declarations)): ?>
                            <div class="alert alert-info py-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucune déclaration trouvée.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped datatable mb-0">
                                    <thead class="small">
                                        <tr>
                                            <th>Date</th>
                                            <th>Étudiant</th>
                                            <th>Matricule</th>
                                            <th>Frais</th>
                                            <th>Montant</th>
                                            <th>Mode</th>
                                            <th>Référence</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($declarations as $declaration): 
                                            $montant_total_frais = $declaration['montant_specifique'] > 0 ? $declaration['montant_specifique'] : $declaration['montant_frais'];
                                            $montant_restant = $montant_total_frais - $declaration['montant_deja_paye'];
                                        ?>
                                        <tr class="align-middle">
                                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($declaration['date_declaration'])) ?></td>
                                            <td><?= htmlspecialchars($declaration['etudiant_nom']) ?></td>
                                            <td><?= htmlspecialchars($declaration['matricule_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($declaration['frais_designation']) ?></td>
                                            <td>
                                                <strong><?= number_format($declaration['montant'], 2) ?> <?= htmlspecialchars($declaration['devise']) ?></strong>
                                                <br><small class="text-muted">Rest: <?= number_format($montant_restant, 2) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($declaration['mode_paiement']) ?></span>
                                                <br><small class="text-muted"><?= htmlspecialchars($declaration['lieu_paiement']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($declaration['reference_paiement']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $declaration['badge_color'] ?>">
                                                    <?= $declaration['statut_validation'] === 'en_attente' ? 'En attente' : 
                                                        ($declaration['statut_validation'] === 'validé' ? 'Validé' : 'Rejeté') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info mb-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detailsModal"
                                                        onclick="afficherDetails(<?= htmlspecialchars(json_encode($declaration)) ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($declaration['statut_validation'] === 'en_attente'): ?>
                                                    <button type="button" class="btn btn-sm btn-success mb-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#validerModal"
                                                            onclick="prepareValidation(<?= htmlspecialchars(json_encode($declaration)) ?>)">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger mb-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejeterModal"
                                                            onclick="prepareRejet(<?= $declaration['id'] ?>, '<?= htmlspecialchars($declaration['etudiant_nom']) ?>', '<?= htmlspecialchars($declaration['frais_designation']) ?>')">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php endif; ?>
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

<!-- Modal Détails -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-text me-2"></i>Détails de la déclaration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations de l'étudiant</h6>
                        <p><strong>Nom:</strong> <span id="detail_etudiant_nom"></span></p>
                        <p><strong>Matricule:</strong> <span id="detail_matricule"></span></p>
                        <p><strong>Promotion:</strong> <span id="detail_promotion"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations du frais</h6>
                        <p><strong>Frais:</strong> <span id="detail_frais"></span></p>
                        <p><strong>Année académique:</strong> <span id="detail_annee"></span></p>
                        <p><strong>Montant total du frais:</strong> <span id="detail_montant_total"></span></p>
                        <p><strong>Déjà payé:</strong> <span id="detail_deja_paye"></span></p>
                        <p><strong>Reste à payer:</strong> <span id="detail_restant"></span></p>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-primary">Détails du paiement déclaré</h6>
                        <p><strong>Montant:</strong> <span id="detail_montant"></span></p>
                        <p><strong>Date du paiement:</strong> <span id="detail_date_paiement"></span></p>
                        <p><strong>Mode:</strong> <span id="detail_mode"></span></p>
                        <p><strong>Lieu:</strong> <span id="detail_lieu"></span></p>
                        <p><strong>Référence:</strong> <span id="detail_reference"></span></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Preuve de paiement</h6>
                        <div id="detail_preuve_container"></div>
                    </div>
                </div>

                <div class="row mb-3" id="detail_commentaire_container" style="display: none;">
                    <div class="col-12">
                        <h6 class="text-primary">Commentaire de l'étudiant</h6>
                        <p id="detail_commentaire"></p>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary">Statut de validation</h6>
                        <p><strong>Statut:</strong> <span id="detail_statut"></span></p>
                        <div id="detail_validation_info" style="display: none;">
                            <p><strong>Date de validation:</strong> <span id="detail_date_validation"></span></p>
                            <p><strong>Validé par:</strong> <span id="detail_validateur"></span></p>
                            <div id="detail_commentaire_validation_container" style="display: none;">
                                <p><strong>Commentaire:</strong> <span id="detail_commentaire_validation"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Validation -->
<div class="modal fade" id="validerModal" tabindex="-1" aria-labelledby="validerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/valider_declaration_paiement.php" method="POST">
                <input type="hidden" name="action" value="valider">
                <input type="hidden" name="declaration_id" id="valider_declaration_id">
                <input type="hidden" name="affectation_id" id="valider_affectation_id">
                <input type="hidden" name="matricule_etudiant" id="valider_matricule">
                <input type="hidden" name="devise_frais" id="valider_devise_frais">

                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title mb-0" id="validerModalLabel">
                        <i class="bi bi-check-circle me-1"></i>Valider la déclaration
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-2">
                    <!-- Informations récapitulatives -->
                    <div class="alert alert-info mb-2 py-1 px-2" style="font-size: 0.85rem;">
                        <div class="row g-1">
                            <div class="col-md-6">
                                <strong>Étudiant:</strong> <span id="valider_etudiant_nom"></span><br>
                                <strong>Frais:</strong> <span id="valider_frais"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Montant déclaré:</strong> <span id="valider_montant_declare"></span><br>
                                <strong>Reste:</strong> <span id="valider_restant"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Montant à valider -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="montant_valide" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-cash me-1"></i>Montant <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" class="form-control" 
                                       id="montant_valide" name="montant" required>
                                <span class="input-group-text" id="valider_devise_input"></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="date_valeur" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-calendar-alt me-1"></i>Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control form-control-sm" id="date_valeur" 
                                   name="date_valeur" required>
                        </div>
                    </div>

                    <!-- Mode et Référence -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-credit-card me-1"></i>Mode <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="reference_externe" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-hashtag me-1"></i>Référence
                            </label>
                            <input type="text" class="form-control form-control-sm" id="reference_externe" 
                                   name="reference_externe" placeholder="N° transaction...">
                        </div>
                    </div>

                    <!-- Source et Caisse/Banque -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="source_paiement" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-building me-1"></i>Source <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="source_paiement" name="source_paiement" 
                                    required onchange="toggleValidationSourceOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Caisse">Caisse</option>
                                <option value="Banque">Banque</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="caisse_validation_container" style="display: none;">
                            <label for="caisse_id" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-cash-stack me-1"></i>Caisse <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="caisse_id" name="caisse_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($caisses as $caisse): ?>
                                    <option value="<?= $caisse['id'] ?>" data-devise="<?= $caisse['devise'] ?>">
                                        <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6" id="banque_validation_container" style="display: none;">
                            <label for="compte_bancaire_id" class="form-label mb-0" style="font-size: 0.85rem;">
                                <i class="bi bi-bank me-1"></i>Compte <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="compte_bancaire_id" name="compte_bancaire_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($comptes_bancaires as $compte): ?>
                                    <option value="<?= $compte['id'] ?>" data-devise="<?= $compte['devise'] ?>">
                                        <?= htmlspecialchars($compte['nom_banque'] . ' - ' . $compte['intitule_compte']) ?> (<?= $compte['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-2">
                        <label for="commentaire_validation" class="form-label mb-0" style="font-size: 0.85rem;">
                            <i class="bi bi-chat-left-text me-1"></i>Commentaire
                        </label>
                        <textarea class="form-control form-control-sm" id="commentaire_validation" 
                                  name="commentaire" rows="1" style="resize: vertical;"
                                  placeholder="Commentaire..."></textarea>
                    </div>

                    <div class="alert alert-warning mb-0 py-1 px-2" style="font-size: 0.8rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Important:</strong> Le paiement sera enregistré automatiquement.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Valider et enregistrer le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rejet -->
<div class="modal fade" id="rejeterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/valider_declaration_paiement.php" method="POST">
                <input type="hidden" name="action" value="rejeter">
                <input type="hidden" name="declaration_id" id="rejeter_declaration_id">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle me-2"></i>Rejeter la déclaration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Étudiant:</strong> <span id="rejeter_etudiant_nom"></span><br>
                        <strong>Frais:</strong> <span id="rejeter_frais"></span>
                    </div>

                    <div class="mb-3">
                        <label for="motif_rejet" class="form-label required">
                            <i class="bi bi-chat-left-text me-1"></i>Motif du rejet
                        </label>
                        <textarea class="form-control" id="motif_rejet" name="commentaire" 
                                  rows="4" required 
                                  placeholder="Expliquez pourquoi cette déclaration est rejetée..."></textarea>
                        <small class="text-muted">Ce commentaire sera visible par l'étudiant</small>
                    </div>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention:</strong> L'étudiant sera notifié du rejet et devra soumettre 
                        une nouvelle déclaration avec les corrections nécessaires.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Confirmer le rejet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fonction pour afficher les détails d'une déclaration
function afficherDetails(declaration) {
    // Informations étudiant
    document.getElementById('detail_etudiant_nom').textContent = declaration.etudiant_nom;
    document.getElementById('detail_matricule').textContent = declaration.matricule_etudiant;
    document.getElementById('detail_promotion').textContent = declaration.promotion_nom || 'Non spécifiée';

    // Informations frais
    document.getElementById('detail_frais').textContent = declaration.frais_designation;
    document.getElementById('detail_annee').textContent = declaration.annee_academique || 'Non spécifiée';
    
    const montantTotal = parseFloat(declaration.montant_specifique) > 0 ? 
        parseFloat(declaration.montant_specifique) : parseFloat(declaration.montant_frais);
    const montantDejaPaye = parseFloat(declaration.montant_deja_paye);
    const montantRestant = montantTotal - montantDejaPaye;
    
    document.getElementById('detail_montant_total').textContent = 
        montantTotal.toFixed(2) + ' ' + declaration.devise;
    document.getElementById('detail_deja_paye').textContent = 
        montantDejaPaye.toFixed(2) + ' ' + declaration.devise;
    document.getElementById('detail_restant').textContent = 
        montantRestant.toFixed(2) + ' ' + declaration.devise;

    // Détails paiement
    document.getElementById('detail_montant').textContent = 
        parseFloat(declaration.montant).toFixed(2) + ' ' + declaration.devise;
    document.getElementById('detail_date_paiement').textContent = 
        new Date(declaration.date_paiement).toLocaleDateString('fr-FR');
    document.getElementById('detail_mode').textContent = declaration.mode_paiement;
    document.getElementById('detail_lieu').textContent = declaration.lieu_paiement;
    document.getElementById('detail_reference').textContent = declaration.reference_paiement;

    // Preuve de paiement
    const preuveContainer = document.getElementById('detail_preuve_container');
    if (declaration.preuve_paiement) {
        const ext = declaration.preuve_paiement.split('.').pop().toLowerCase();
        const filePath = `uploads/preuves_paiement/${declaration.preuve_paiement}`;
        
        if (ext === 'pdf') {
            preuveContainer.innerHTML = `
                <div style="width: 100%; height: 400px; border: 1px solid #ddd; border-radius: 4px;">
                    <iframe src="${filePath}" 
                            style="width: 100%; height: 100%; border: none;" 
                            title="Preuve de paiement PDF">
                    </iframe>
                </div>
                <div class="mt-2">
                    <a href="${filePath}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right"></i> Ouvrir dans un nouvel onglet
                    </a>
                </div>`;
        } else {
            preuveContainer.innerHTML = `
                <div class="text-center">
                    <img src="${filePath}" 
                         alt="Preuve de paiement" 
                         class="img-fluid" 
                         style="max-height: 400px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;"
                         onclick="window.open('${filePath}', '_blank')">
                    <div class="mt-2">
                        <small class="text-muted">Cliquez sur l'image pour l'agrandir</small>
                    </div>
                </div>`;
        }
    } else {
        preuveContainer.innerHTML = '<p class="text-muted">Aucune preuve jointe</p>';
    }

    // Commentaire étudiant
    if (declaration.commentaire) {
        document.getElementById('detail_commentaire_container').style.display = 'block';
        document.getElementById('detail_commentaire').textContent = declaration.commentaire;
    } else {
        document.getElementById('detail_commentaire_container').style.display = 'none';
    }

    // Statut
    let statutText = '';
    let statutClass = '';
    if (declaration.statut_validation === 'en_attente') {
        statutText = 'En attente';
        statutClass = 'badge bg-warning';
    } else if (declaration.statut_validation === 'validé') {
        statutText = 'Validé';
        statutClass = 'badge bg-success';
    } else {
        statutText = 'Rejeté';
        statutClass = 'badge bg-danger';
    }
    document.getElementById('detail_statut').innerHTML = `<span class="${statutClass}">${statutText}</span>`;

    // Info validation
    if (declaration.date_validation) {
        document.getElementById('detail_validation_info').style.display = 'block';
        document.getElementById('detail_date_validation').textContent = 
            new Date(declaration.date_validation).toLocaleDateString('fr-FR');
        document.getElementById('detail_validateur').textContent = 
            declaration.validateur_nom || 'Non spécifié';
        
        if (declaration.commentaire_validation) {
            document.getElementById('detail_commentaire_validation_container').style.display = 'block';
            document.getElementById('detail_commentaire_validation').textContent = 
                declaration.commentaire_validation;
        } else {
            document.getElementById('detail_commentaire_validation_container').style.display = 'none';
        }
    } else {
        document.getElementById('detail_validation_info').style.display = 'none';
    }
}

// Fonction pour préparer la validation
function prepareValidation(declaration) {
    document.getElementById('valider_declaration_id').value = declaration.id;
    document.getElementById('valider_affectation_id').value = declaration.affectation_id;
    document.getElementById('valider_matricule').value = declaration.matricule_etudiant;
    document.getElementById('valider_devise_frais').value = declaration.devise;
    
    document.getElementById('valider_etudiant_nom').textContent = declaration.etudiant_nom;
    document.getElementById('valider_frais').textContent = declaration.frais_designation;
    document.getElementById('valider_montant_declare').textContent = 
        parseFloat(declaration.montant).toFixed(2) + ' ' + declaration.devise;
    
    const montantTotal = parseFloat(declaration.montant_specifique) > 0 ? 
        parseFloat(declaration.montant_specifique) : parseFloat(declaration.montant_frais);
    const montantRestant = montantTotal - parseFloat(declaration.montant_deja_paye);
    
    document.getElementById('valider_restant').textContent = 
        montantRestant.toFixed(2) + ' ' + declaration.devise;
    document.getElementById('valider_devise_input').textContent = declaration.devise;
    
    // Pré-remplir les champs
    document.getElementById('montant_valide').value = parseFloat(declaration.montant).toFixed(2);
    document.getElementById('montant_valide').setAttribute('max', montantRestant);
    document.getElementById('date_valeur').value = declaration.date_paiement;
    document.getElementById('mode_paiement').value = declaration.mode_paiement;
    document.getElementById('reference_externe').value = declaration.reference_paiement;
}

// Fonction pour préparer le rejet
function prepareRejet(declarationId, etudiantNom, fraisDesignation) {
    document.getElementById('rejeter_declaration_id').value = declarationId;
    document.getElementById('rejeter_etudiant_nom').textContent = etudiantNom;
    document.getElementById('rejeter_frais').textContent = fraisDesignation;
    document.getElementById('motif_rejet').value = '';
}

// Fonction pour basculer entre les options de source
function toggleValidationSourceOptions() {
    const sourcePaiement = document.getElementById('source_paiement').value;
    const caisseContainer = document.getElementById('caisse_validation_container');
    const banqueContainer = document.getElementById('banque_validation_container');

    if (sourcePaiement === 'Caisse') {
        caisseContainer.style.display = 'block';
        banqueContainer.style.display = 'none';
        document.getElementById('caisse_id').setAttribute('required', 'required');
        document.getElementById('compte_bancaire_id').removeAttribute('required');
    } else if (sourcePaiement === 'Banque') {
        caisseContainer.style.display = 'none';
        banqueContainer.style.display = 'block';
        document.getElementById('caisse_id').removeAttribute('required');
        document.getElementById('compte_bancaire_id').setAttribute('required', 'required');
    } else {
        caisseContainer.style.display = 'none';
        banqueContainer.style.display = 'none';
        document.getElementById('caisse_id').removeAttribute('required');
        document.getElementById('compte_bancaire_id').removeAttribute('required');
    }
}

// Validation des devises
document.addEventListener('DOMContentLoaded', function() {
    const caisseSelect = document.getElementById('caisse_id');
    if (caisseSelect) {
        caisseSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const caisseDevise = selectedOption.getAttribute('data-devise');
                const fraisDevise = document.getElementById('valider_devise_frais').value;

                if (caisseDevise !== fraisDevise) {
                    alert(`Attention: La devise de la caisse (${caisseDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir une caisse avec la même devise.`);
                    this.value = '';
                }
            }
        });
    }

    const compteSelect = document.getElementById('compte_bancaire_id');
    if (compteSelect) {
        compteSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const compteDevise = selectedOption.getAttribute('data-devise');
                const fraisDevise = document.getElementById('valider_devise_frais').value;

                if (compteDevise !== fraisDevise) {
                    alert(`Attention: La devise du compte bancaire (${compteDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir un compte avec la même devise.`);
                    this.value = '';
                }
            }
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
