<?php
include "./views/include/header.php";

// Initialiser la connexion à la base de données
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'ID utilisateur de la session
$userId = $_SESSION['id'] ?? 0;

// Récupérer toutes les années académiques
$queryYears = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtYears = $pdo->prepare($queryYears);
$stmtYears->execute();
$annees = $stmtYears->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique en cours (par défaut)
$queryCurrentYear = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtCurrentYear = $pdo->prepare($queryCurrentYear);
$stmtCurrentYear->execute();
$anneeEnCours = $stmtCurrentYear->fetch(PDO::FETCH_ASSOC);

// Si aucune année active, prendre la plus récente
if (!$anneeEnCours) {
    $queryLastYear = "SELECT * FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
    $stmtLastYear = $pdo->prepare($queryLastYear);
    $stmtLastYear->execute();
    $anneeEnCours = $stmtLastYear->fetch(PDO::FETCH_ASSOC);
}

// Vérifier si l'utilisateur a sélectionné une année spécifique
$selectedYear = isset($_GET['annee']) ? intval($_GET['annee']) : ($anneeEnCours ? $anneeEnCours['idannee_acad'] : 0);

// Si l'année sélectionnée existe, l'utiliser; sinon revenir à l'année courante
$anneeId = $selectedYear;
if ($selectedYear) {
    $queryCheckYear = "SELECT * FROM annee_acad WHERE idannee_acad = ?";
    $stmtCheckYear = $pdo->prepare($queryCheckYear);
    $stmtCheckYear->execute([$selectedYear]);
    $selectedYearData = $stmtCheckYear->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedYearData) {
        $anneeEnCours = $selectedYearData;
    }
}

// Vérifier si l'utilisateur est un enseignant
$query = "SELECT a.idAgent FROM agent a 
          INNER JOIN t_users u ON a.idAgent = u.idAgent 
          WHERE u.idUser = ? AND a.type_agent = 'Enseignant'";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$isEnseignant = $stmt->rowCount() > 0;

if (!$userId || !$isEnseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

// Récupérer l'ID de l'agent (enseignant)
$query = "SELECT a.idAgent FROM agent a 
          INNER JOIN t_users u ON a.idAgent = u.idAgent 
          WHERE u.idUser = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$idEnseignant = $stmt->fetchColumn();

if (!$idEnseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les informations de l'enseignant
$query = "SELECT a.* FROM agent a 
          INNER JOIN t_users u ON a.idAgent = u.idAgent 
          WHERE u.idUser = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

$idEnseignant = $enseignant['idAgent'];

// Récupérer les spécialisations pour le formulaire de modification
$query = "SELECT * FROM specialisation ORDER BY designation";
$stmt = $pdo->prepare($query);
$stmt->execute();
$specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques pour l'année sélectionnée
$query = "SELECT 
            SUM(CASE WHEN idDirecteur = ? THEN 1 ELSE 0 END) as directeur,
            SUM(CASE WHEN idEncadreur = ? THEN 1 ELSE 0 END) as encadreur,
            SUM(CASE WHEN (statut_validation = 'Validé') THEN 1 ELSE 0 END) as valide,
            COUNT(*) as total
          FROM sujets 
          WHERE (idDirecteur = ? OR idEncadreur = ?) 
          AND annee_acad_idannee_acad = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $anneeId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les sujets validés par la commission où l'enseignant est directeur ou encadreur pour l'année sélectionnée
$query = "SELECT s.*, 
          spec.designation as specialisation,
          e.noms as etudiant
          FROM sujets s
          LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
          LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
          WHERE (s.idDirecteur = ? OR s.idEncadreur = ?)
          AND s.annee_acad_idannee_acad = ?";

if (!empty($search)) {
    $query .= " AND (s.intitule LIKE ? OR e.noms LIKE ? OR spec.designation LIKE ?)";
}

$query .= " ORDER BY s.idsujets DESC";
$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->execute([$idEnseignant, $idEnseignant, $anneeId, $searchParam, $searchParam, $searchParam]);
} else {
    $stmt->execute([$idEnseignant, $idEnseignant, $anneeId]);
}

$sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des enseignants pour le choix d'encadreur
$query = "SELECT a.*, g.designation as grade
          FROM agent a
          LEFT JOIN grade g ON a.grade_id = g.idgrade
          WHERE a.type_agent = 'Enseignant'
          ORDER BY a.noms";
$stmt = $pdo->prepare($query);
$stmt->execute();
$enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les soutenances programmées pour l'année sélectionnée
$query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
          suj.intitule, e.noms as etudiant,
          CASE 
            WHEN suj.idDirecteur = ? THEN 'Directeur'
            WHEN suj.idEncadreur = ? THEN 'Encadreur'
            WHEN js.idenseignant = ? THEN js.role
            ELSE 'Membre'
          END as role
          FROM soutenance s
          INNER JOIN sujets suj ON s.sujets_idsujets = suj.idsujets
          LEFT JOIN etudiant e ON suj.etudiant_idetudiant = e.idetudiant
          LEFT JOIN jury_soutenance js ON s.idsoutenance = js.idsoutenance AND js.idenseignant = ?
          WHERE (suj.idDirecteur = ? OR suj.idEncadreur = ? OR js.idenseignant = ?)
          AND suj.annee_acad_idannee_acad = ?
          ORDER BY s.date_soutenance DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $anneeId]);
$soutenances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour obtenir la classe de badge pour le statut de soutenance
function getSoutenanceStatusClass($statut) {
    switch ($statut) {
        case 'Programmée':
            return 'bg-info';
        case 'Terminée':
            return 'bg-success';
        case 'Reportée':
            return 'bg-warning';
        case 'Annulée':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUPERVISION DES TRAVAUX DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Travaux à Superviser</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Filtre Année Académique -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtrer par année académique</h5>
                        
                        <form method="GET" action="" class="row g-3 align-items-center">
                            <input type="hidden" name="view" value="recherche/projet.recherche">
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <select name="annee" id="annee" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($annee['idannee_acad'] == $anneeId) ? 'selected' : '' ?>>
                                            <?= $annee['designation'] ?> <?= ($annee['est_active'] == 1) ? '(Année en cours)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques pour l'année académique <?= $anneeEnCours['designation'] ?></h5>
                        
                        <div class="row">
                            <!-- Statistique Directeur -->
                            <div class="col-md-3">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Directeur</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-check-fill"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['directeur'] ?? 0 ?></h6>
                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= ($stats['total'] > 0) ? round(($stats['directeur']/$stats['total'])*100, 1) : 0 ?>%
                                                </span> 
                                                <span class="text-muted small pt-2 ps-1">des projets</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Encadreur -->
                            <div class="col-md-3">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Encadreur</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-lines-fill"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['encadreur'] ?? 0 ?></h6>
                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= ($stats['total'] > 0) ? round(($stats['encadreur']/$stats['total'])*100, 1) : 0 ?>%
                                                </span> 
                                                                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= ($stats['total'] > 0) ? round(($stats['encadreur']/$stats['total'])*100, 1) : 0 ?>%
                                                </span> 
                                                <span class="text-muted small pt-2 ps-1">des projets</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Validés -->
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Validés</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-check-circle"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['valide'] ?? 0 ?></h6>
                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= ($stats['total'] > 0) ? round(($stats['valide']/$stats['total'])*100, 1) : 0 ?>%
                                                </span> 
                                                <span class="text-muted small pt-2 ps-1">des projets</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Total -->
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['total'] ?? 0 ?></h6>
                                                <span class="text-muted small pt-2 ps-1">projets supervisés</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des sujets -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Travaux de Recherche à Superviser (<?= count($sujets) ?> travaux)</h5>

                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="recherche/projet.recherche">
                                <input type="hidden" name="annee" value="<?= $anneeId ?>">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un sujet ou un étudiant...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <?php if (empty($sujets)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun travail de recherche trouvé pour l'année académique sélectionnée.
                            </div>
                        <?php else: ?>
                            <table class="table table-striped table-bordered datatable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Intitulé</th>
                                        <th>Cycle</th>
                                        <th>Spécialisation</th>
                                        <th>État</th>
                                        <th>Étudiant</th>
                                        <th>Rôle</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $i = 1;
                                    foreach ($sujets as $sujet) {
                                        // Déterminer le rôle de l'enseignant pour ce sujet
                                        $role = '';
                                        if ($idEnseignant == $sujet['idDirecteur']) {
                                            $role = '<span class="badge bg-primary">Directeur</span>';
                                        } elseif ($idEnseignant == $sujet['idEncadreur']) {
                                            $role = '<span class="badge bg-success">Encadreur</span>';
                                        }
                                        
                                        // Déterminer la classe CSS pour le statut du sujet
                                        $badgeClass = '';
                                        switch ($sujet['statut_validation']) {
                                            case 'Validé':
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'En attente':
                                                $badgeClass = 'bg-warning';
                                                break;
                                            case 'Rejeté':
                                                $badgeClass = 'bg-danger';
                                                break;
                                            case 'Modifié':
                                                $badgeClass = 'bg-info';
                                                break;
                                            default:
                                                $badgeClass = 'bg-secondary';
                                        }
                                        
                                        // Titre échappé pour JavaScript
                                        $intitule_escaped = htmlspecialchars(addslashes($sujet['intitule']), ENT_QUOTES);
                                        
                                        echo "<tr>
                                            <td>{$i}</td>
                                            <td>{$sujet['intitule']}</td>
                                            <td>{$sujet['cycle']}</td>
                                            <td>{$sujet['specialisation']}</td>
                                            <td><span class='badge {$badgeClass}'>{$sujet['statut_validation']}</span></td>
                                            <td>{$sujet['etudiant']}</td>
                                            <td>{$role}</td>
                                            <td>
                                                <button class='btn btn-sm btn-info' onclick='viewDetails({$sujet['idsujets']})'>
                                                    <i class='bi bi-eye'></i>
                                                </button>";
                                                
                                        // Montrer le bouton de modification seulement si l'enseignant est le directeur
                                        if ($idEnseignant == $sujet['idDirecteur']) {
                                            echo "<button class='btn btn-sm btn-primary' onclick='editSujet({$sujet['idsujets']}, \"{$intitule_escaped}\", {$sujet['idSpecialisation']}, " . ($sujet['idEncadreur'] ? $sujet['idEncadreur'] : 'null') . ")'>
                                                    <i class='bi bi-pencil'></i>
                                                </button>";
                                        }
                                                
                                        
                                                
                                        echo "</td>
                                        </tr>";
                                        $i++;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Soutenances Programmées -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Soutenances Programmées pour <?= $anneeEnCours['designation'] ?></h5>
                        
                        <?php if (empty($soutenances)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucune soutenance n'est programmée pour cette année académique.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date & Heure</th>
                                            <th>Sujet</th>
                                            <th>Étudiant</th>
                                            <th>Lieu</th>
                                            <th>Statut</th>
                                            <th>Rôle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($soutenances as $soutenance): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])) ?></td>
                                                <td><?= $soutenance['intitule'] ?></td>
                                                <td><?= $soutenance['etudiant'] ?></td>
                                                <td><?= $soutenance['lieu'] ?></td>
                                                <td>
                                                    <span class="badge <?= getSoutenanceStatusClass($soutenance['statut']) ?>">
                                                        <?= $soutenance['statut'] ?>
                                                    </span>
                                                </td>
                                                <td><span class="badge bg-primary"><?= $soutenance['role'] ?></span></td>
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

<!-- Modal Détails du Sujet -->
<div class="modal fade" id="sujetDetailsModal" tabindex="-1" role="dialog" aria-labelledby="sujetDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sujetDetailsModalLabel">Détails du Sujet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sujetDetailsContent">
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

<!-- Modal Modification Sujet -->
<div class="modal fade" id="editSujetModal" tabindex="-1" role="dialog" aria-labelledby="editSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSujetForm" method="POST" action="controller/sujet_controller.php">
                    <input type="hidden" name="action" value="update2">
                    <input type="hidden" name="idsujets" id="edit_idsujets">
                    <input type="hidden" name="annee" value="<?= $anneeId ?>"> <!-- Conserver l'année sélectionnée -->

                    <div class="mb-3">
                        <label for="edit_intitule" class="form-label">Intitulé du sujet</label>
                        <input type="text" class="form-control" id="edit_intitule" name="intitule" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_idSpecialisation" class="form-label">Spécialisation</label>
                        <select class="form-select" id="edit_idSpecialisation" name="idSpecialisation" required>
                            <option value="">Sélectionner une spécialisation</option>
                            <?php foreach ($specialisations as $spec): ?>
                                <option value="<?= $spec['idSpecialisation'] ?>">
                                    <?= $spec['designation'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_encadreur" class="form-label">Encadreur</label>
                        <select class="form-select" id="edit_encadreur" name="encadreur">
                            <option value="">Sélectionner un encadreur</option>
                            <?php foreach ($enseignants as $e): ?>
                                <?php if ($e['idAgent'] != $idEnseignant): // Ne pas s'afficher soi-même ?>
                                    <option value="<?= $e['idAgent'] ?>">
                                        <?= $e['grade'] ? $e['grade'] . ' ' : '' ?><?= $e['noms'] ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
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
    // Fonction pour afficher les détails d'un sujet
    function viewDetails(sujetId) {
        // Réinitialiser le contenu du modal
        document.getElementById('sujetDetailsContent').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p>Chargement des détails...</p>
            </div>`;
        
        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('sujetDetailsModal'));
        modal.show();
        
        // Charger les détails via AJAX
        fetch(`controller/get_sujet_detail.php?id=${sujetId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('sujetDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            ${data.error}
                        </div>`;
                    return;
                }
                
                // Construire le contenu du modal avec les détails du sujet
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5>${data.intitule}</h5>
                            <span class="badge ${getBadgeClass(data.statut_validation)}">${data.statut_validation}</span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Cycle:</strong> ${data.cycle}</p>
                            <p><strong>Spécialisation:</strong> ${data.specialisation}</p>
                            <p><strong>Année académique:</strong> ${data.annee}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Étudiant:</strong> ${data.etudiant || 'Non assigné'}</p>
                            <p><strong>Directeur:</strong> ${data.grade_directeur ? data.grade_directeur + ' ' : ''}${data.directeur || 'Non assigné'}</p>
                            <p><strong>Encadreur:</strong> ${data.grade_encadreur ? data.grade_encadreur + ' ' : ''}${data.encadreur || 'Non assigné'}</p>
                        </div>
                    </div>`;
                
                // Afficher les commentaires de validation si disponibles
                if (data.commentaire_commission) {
                    html += `
                        <div class="alert alert-info">
                            <h6>Commentaire de la commission:</h6>
                            <p>${data.commentaire_commission}</p>
                            ${data.validateur ? `<small class="text-muted">Par ${data.validateur}</small>` : ''}
                        </div>`;
                }
                
                // Afficher l'historique des validations si disponible
                if (data.historique && data.historique.length > 0) {
                    html += `
                        <h6>Historique des validations:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Commentaire</th>
                                        <th>Utilisateur</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    data.historique.forEach(h => {
                        html += `
                            <tr>
                                <td>${h.date_formatee}</td>
                                <td><span class="badge ${getBadgeClass(h.status)}">${h.status}</span></td>
                                <td>${h.commentaire || ''}</td>
                                <td>${h.nom_utilisateur}</td>
                            </tr>`;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>`;
                }
                
                document.getElementById('sujetDetailsContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('sujetDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Une erreur est survenue lors du chargement des détails.
                    </div>`;
            });
    }
    
    // Fonction pour obtenir la classe CSS correspondant au statut
    function getBadgeClass(statut) {
        switch(statut) {
            case 'Validé':
                return 'bg-success';
            case 'En attente':
                return 'bg-warning';
            case 'Rejeté':
                return 'bg-danger';
            case 'Modifié':
                return 'bg-info';
            default:
                return 'bg-secondary';
        }
    }
    
    // Fonction pour ouvrir le modal de modification d'un sujet
    function editSujet(idSujet, intitule, idSpecialisation, idEncadreur) {
        // Remplir les champs du formulaire avec les valeurs existantes
        document.getElementById('edit_idsujets').value = idSujet;
        document.getElementById('edit_intitule').value = intitule;
        document.getElementById('edit_idSpecialisation').value = idSpecialisation;
        
        // Si un encadreur est assigné, le sélectionner dans le dropdown
        if (idEncadreur && idEncadreur !== 'null') {
            document.getElementById('edit_encadreur').value = idEncadreur;
        } else {
            document.getElementById('edit_encadreur').value = '';
        }
        
        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('editSujetModal'));
        modal.show();
    }
    
    // Initialisation des select2 pour une meilleure expérience utilisateur
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les selects avec select2 s'il est disponible
        if (typeof($.fn.select2) !== 'undefined') {
            $('.form-select').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }
        
        // Validation du formulaire de modification
        const editForm = document.getElementById('editSujetForm');
        if (editForm) {
            editForm.addEventListener('submit', function(event) {
                if (!editForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur de validation',
                        text: 'Veuillez remplir tous les champs obligatoires.'
                    });
                }
                editForm.classList.add('was-validated');
            });
        }

        // Mettre en évidence l'année académique active
        const anneeSelect = document.getElementById('annee');
        if (anneeSelect) {
            // Styliser l'option de l'année académique active
            Array.from(anneeSelect.options).forEach(option => {
                if (option.text.includes('(Année en cours)')) {
                    option.classList.add('fw-bold', 'text-success');
                }
            });
        }
    });
</script>

<?php include "./views/include/footer_file.php"; ?>

