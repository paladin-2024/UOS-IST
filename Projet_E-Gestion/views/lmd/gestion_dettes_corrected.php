<?php
session_start();
require_once dirname(dirname(dirname(__FILE__))) . '/config/Connexion.php';

// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    header('Location: index.php');
    exit();
}

// Connexion à la base de données
$db = Connexion::getInstance()->getPDO();

// Récupérer les filtres
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : 'tous';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Récupérer les promotions
$promotions = [];
try {
    $sql = "SELECT DISTINCT p.idpromotion, p.\"designationPromotion\" 
            FROM promotion p 
            ORDER BY p.\"designationPromotion\"";
    $stmt = $db->query($sql);
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération promotions: " . $e->getMessage());
}

// Récupérer les années académiques
$annees = [];
try {
    $sql = "SELECT idannee_acad, designation 
            FROM annee_acad 
            ORDER BY designation DESC";
    $stmt = $db->query($sql);
    $annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération années: " . $e->getMessage());
}

// Récupérer les dettes selon les filtres
$dettes = [];
if ($promotionId && $anneeId) {
    try {
        $sql = "SELECT 
                    d.id_dette,
                    d.matricule,
                    CONCAT(e.noms) as nom_etudiant,
                    s.\"numeroSemestre\" as semestre,
                    ec.\"designationECUE\" as ue_designation,
                    ec.CMI + ec.TD + ec.TP as credits,
                    d.statut,
                    d.note_obtenue,
                    d.note_rachat
                FROM dette_etudiant d
                INNER JOIN etudiant e ON d.matricule = e.matricule
                INNER JOIN ecue ec ON d.\"ECUE_idECUE\" = ec.\"idECUE\"
                INNER JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
                WHERE d.promotion_idpromotion = :promotion
                AND d.annee_acad_idannee_acad = :annee";
        
        $params = [
            ':promotion' => $promotionId,
            ':annee' => $anneeId
        ];
        
        // Ajouter le filtre de statut
        if ($statut !== 'tous') {
            $sql .= " AND d.statut = :statut";
            $params[':statut'] = $statut;
        }
        
        // Ajouter la recherche
        if (!empty($search)) {
            $sql .= " AND (e.noms LIKE :search OR d.matricule LIKE :search2)";
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY e.noms, s.\"numeroSemestre\"";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $dettes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur récupération dettes: " . $e->getMessage());
    }
}

// Calculer les statistiques
$stats = [];
if ($promotionId && $anneeId) {
    try {
        $sql = "SELECT 
                    COUNT(DISTINCT d.matricule) as total_etudiants,
                    SUM(ec.CMI + ec.TD + ec.TP) as total_credits,
                    SUM(CASE WHEN d.statut = 'En cours' THEN 1 ELSE 0 END) as dettes_en_cours,
                    SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) as dettes_validees
                FROM dette_etudiant d
                INNER JOIN ecue ec ON d.\"ECUE_idECUE\" = ec.\"idECUE\"
                WHERE d.promotion_idpromotion = :promotion
                AND d.annee_acad_idannee_acad = :annee";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':promotion' => $promotionId,
            ':annee' => $anneeId
        ]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur calcul statistiques: " . $e->getMessage());
    }
}

include "./views/include/header.php";
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Dettes Étudiantes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item">LMD</li>
                <li class="breadcrumb-item active">Gestion des dettes</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres de recherche</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="lmd/gestion_dettes">
                            
                            <div class="col-md-3">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select name="promotion" id="promotion" class="form-select" required>
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>" <?= $promotionId == $promo['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="annee" class="form-label">Année académique</label>
                                <select name="annee" id="annee" class="form-select" required>
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $anneeId == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="statut" class="form-label">Statut</label>
                                <select name="statut" id="statut" class="form-select">
                                    <option value="tous" <?= $statut == 'tous' ? 'selected' : '' ?>>Tous</option>
                                    <option value="En cours" <?= $statut == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="Validée" <?= $statut == 'Validée' ? 'selected' : '' ?>>Validées</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nom ou matricule" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats)): ?>
            <!-- Statistiques -->
            <div class="col-lg-12">
                <div class="row">
                    <div class="col-xxl-3 col-md-6">
                        <div class="card info-card sales-card">
                            <div class="card-body">
                                <h5 class="card-title">Total étudiants avec dettes</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $stats['total_etudiants'] ?? 0 ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-6">
                        <div class="card info-card revenue-card">
                            <div class="card-body">
                                <h5 class="card-title">Total crédits en dette</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-credit-card"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $stats['total_credits'] ?? 0 ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-6">
                        <div class="card info-card customers-card">
                            <div class="card-body">
                                <h5 class="card-title">Dettes en cours</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $stats['dettes_en_cours'] ?? 0 ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-6">
                        <div class="card info-card customers-card">
                            <div class="card-body">
                                <h5 class="card-title">Dettes validées</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $stats['dettes_validees'] ?? 0 ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Liste des étudiants avec dettes -->
            <?php if ($promotionId && $anneeId): ?>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des étudiants avec dettes
                            <?php if (!empty($dettes)): ?>
                                <button class="btn btn-success btn-sm float-end" onclick="exporterListeDettes()">
                                    <i class="bi bi-file-excel"></i> Exporter Excel
                                </button>
                                <button class="btn btn-danger btn-sm float-end me-2" onclick="exporterListeDettesPDF()">
                                    <i class="bi bi-file-pdf"></i> Exporter PDF
                                </button>
                            <?php endif; ?>
                        </h5>

                        <?php if (empty($dettes)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Aucune dette trouvée pour les critères sélectionnés.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tableDettes">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Matricule</th>
                                            <th>Nom complet</th>
                                            <th>Semestre</th>
                                            <th>UE en dette</th>
                                            <th>Crédits</th>
                                            <th>Note obtenue</th>
                                            <th>Note rachat</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $index = 1;
                                        foreach ($dettes as $detteItem): 
                                        ?>
                                        <tr>
                                            <td><?= $index++ ?></td>
                                            <td><?= htmlspecialchars($detteItem['matricule']) ?></td>
                                            <td><?= htmlspecialchars($detteItem['nom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($detteItem['semestre']) ?></td>
                                            <td><?= htmlspecialchars($detteItem['ue_designation']) ?></td>
                                            <td><?= $detteItem['credits'] ?></td>
                                            <td><?= number_format($detteItem['note_obtenue'], 2) ?></td>
                                            <td>
                                                <?php if ($detteItem['note_rachat']): ?>
                                                    <?= number_format($detteItem['note_rachat'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($detteItem['statut'] == 'En cours'): ?>
                                                    <span class="badge bg-warning">En cours</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Validée</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="voirDetails('<?= $detteItem['matricule'] ?>')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($detteItem['statut'] == 'En cours'): ?>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="encoderNotes('<?= $detteItem['id_dette'] ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="controller/export_bulletin_dettes.php?matricule=<?= $detteItem['matricule'] ?>&annee=<?= $anneeId ?>&promotion=<?= $promotionId ?>" 
                                                   class="btn btn-sm btn-danger" target="_blank">
                                                    <i class="bi bi-file-pdf"></i>
                                                </a>
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
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Modal pour voir les détails -->
<div class="modal fade" id="modalDetails" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails des dettes de l'étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal pour encoder les notes -->
<div class="modal fade" id="modalEncoder" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Encoder les notes de rachat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="encoderContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script>
// Initialiser DataTable
$(document).ready(function() {
    $('#tableDettes').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json',
        }
    });
});

// Voir les détails d'un étudiant
function voirDetails(matricule) {
    $.ajax({
        url: 'controller/ajax_dette_details.php',
        type: 'GET',
        data: {
            matricule: matricule,
            annee: <?= $anneeId ?>,
            promotion: <?= $promotionId ?>
        },
        success: function(response) {
            $('#detailsContent').html(response);
            $('#modalDetails').modal('show');
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de charger les détails', 'error');
        }
    });
}

// Encoder les notes
function encoderNotes(idDette) {
    $.ajax({
        url: 'controller/ajax_dette_encoder.php',
        type: 'GET',
        data: {
            id_dette: idDette
        },
        success: function(response) {
            $('#encoderContent').html(response);
            $('#modalEncoder').modal('show');
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de charger le formulaire', 'error');
        }
    });
}

// Exporter la liste en Excel
function exporterListeDettes() {
    window.location.href = 'controller/export_liste_dettes.php?format=excel&promotion=<?= $promotionId ?>&annee=<?= $anneeId ?>&statut=<?= $statut ?>';
}

// Exporter la liste en PDF
function exporterListeDettesPDF() {
    window.open('controller/export_liste_dettes.php?format=pdf&promotion=<?= $promotionId ?>&annee=<?= $anneeId ?>&statut=<?= $statut ?>', '_blank');
}
</script>

<?php include "./views/include/footer.php"; ?>