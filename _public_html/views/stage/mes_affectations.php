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
    $queryLastYear = 'SELECT * FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
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
$query = 'SELECT a."idAgent" FROM agent a
          INNER JOIN t_users u ON a."idAgent" = u."idAgent"
          WHERE u."idUser" = ? AND a.type_agent = \'Enseignant\'';
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$isEnseignant = $stmt->rowCount() > 0;

if (!$userId || !$isEnseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

// Récupérer l'ID de l'agent (enseignant)
$query = 'SELECT a."idAgent" FROM agent a
          INNER JOIN t_users u ON a."idAgent" = u."idAgent"
          WHERE u."idUser" = ?';
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$idEnseignant = $stmt->fetchColumn();

if (!$idEnseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

// Récupérer les informations de l'enseignant
$query = 'SELECT a.* FROM agent a
          INNER JOIN t_users u ON a."idAgent" = u."idAgent"
          WHERE u."idUser" = ?';
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enseignant) {
    echo "<meta http-equiv='refresh' content='0;URL=index'>";
    exit();
}

// Récupérer les statistiques pour l'année sélectionnée
$query = "SELECT 
            SUM(CASE WHEN sa.idencadreur = ? THEN 1 ELSE 0 END) as encadreur,
            SUM(CASE WHEN sa.idlecteur = ? THEN 1 ELSE 0 END) as lecteur,
            SUM(CASE WHEN sa.idencadreur = ? AND sa.cote_entreprise IS NOT NULL THEN 1 ELSE 0 END) as notes_encadreur,
            SUM(CASE WHEN sa.idlecteur = ? AND sa.cote_lecteur IS NOT NULL THEN 1 ELSE 0 END) as notes_lecteur,
            COUNT(DISTINCT sa.idstage) as total
          FROM stage_assignments sa
          INNER JOIN etudiant e ON sa.idetudiant = e.idetudiant
          WHERE (sa.idencadreur = ? OR sa.idlecteur = ?) 
          AND e.annee_acad_idannee_acad = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $idEnseignant, $anneeId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les étudiants affectés comme encadreur
$queryEncadreur = "SELECT 
                    sa.idstage,
                    sa.idetudiant,
                    sa.lieu_stage,
                    sa.date_debut,
                    sa.date_fin,
                    sa.rapport_path,
                    sa.cote_entreprise,
                    sa.cote_lecteur,
                    e.noms as nom_etudiant,
                    e.matricule,
                    p.designationPromotion as promotion,
                    lect.noms as lecteur_nom
                   FROM stage_assignments sa
                   INNER JOIN etudiant e ON sa.idetudiant = e.idetudiant
                   INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   LEFT JOIN agent lect ON sa.idlecteur = lect.idAgent
                   WHERE sa.idencadreur = ?
                   AND e.annee_acad_idannee_acad = ?
                   ORDER BY e.noms";
$stmt = $pdo->prepare($queryEncadreur);
$stmt->execute([$idEnseignant, $anneeId]);
$etudiantsEncadres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les étudiants affectés comme lecteur
$queryLecteur = "SELECT 
                  sa.idstage,
                  sa.idetudiant,
                  sa.lieu_stage,
                  sa.date_debut,
                  sa.date_fin,
                  sa.rapport_path,
                  sa.cote_entreprise,
                  sa.cote_lecteur,
                  e.noms as nom_etudiant,
                  e.matricule,
                  p.designationPromotion as promotion,
                  enc.noms as encadreur_nom
                 FROM stage_assignments sa
                 INNER JOIN etudiant e ON sa.idetudiant = e.idetudiant
                 INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                 LEFT JOIN agent enc ON sa.idencadreur = enc.idAgent
                 WHERE sa.idlecteur = ?
                 AND e.annee_acad_idannee_acad = ?
                 ORDER BY e.noms";
$stmt = $pdo->prepare($queryLecteur);
$stmt->execute([$idEnseignant, $anneeId]);
$etudiantsLecture = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les taux de réussite pour les étudiants encadrés
$tauxReussiteEncadreur = 0;
if (count($etudiantsEncadres) > 0) {
    $etudiantsAvecNote = array_filter($etudiantsEncadres, function($e) {
        return $e['cote_entreprise'] !== null && $e['cote_lecteur'] !== null;
    });
    $etudiantsReussis = array_filter($etudiantsAvecNote, function($e) {
        $moyenne = ($e['cote_entreprise'] + $e['cote_lecteur']) / 2;
        return $moyenne >= 10;
    });
    if (count($etudiantsAvecNote) > 0) {
        $tauxReussiteEncadreur = round((count($etudiantsReussis) / count($etudiantsAvecNote)) * 100, 1);
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MES AFFECTATIONS DE STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Mes Affectations de Stage</li>
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
                            <input type="hidden" name="view" value="stage/mes_affectations">
                            
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
                            <!-- Statistique Encadreur -->
                            <div class="col-md-3">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Encadreur</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-check-fill"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['encadreur'] ?? 0 ?></h6>
                                                <span class="text-muted small pt-2 ps-1">étudiants encadrés</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Lecteur -->
                            <div class="col-md-3">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Lecteur</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-book"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $stats['lecteur'] ?? 0 ?></h6>
                                                <span class="text-muted small pt-2 ps-1">rapports à évaluer</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Notes Attribuées -->
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Notes Attribuées</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-clipboard-check"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= ($stats['notes_lecteur'] ?? 0) + ($stats['notes_encadreur'] ?? 0) ?></h6>
                                                <span class="text-muted small pt-2 ps-1">notes enregistrées</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statistique Taux de Réussite -->
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Taux de Réussite</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-graph-up-arrow"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $tauxReussiteEncadreur ?>%</h6>
                                                <span class="text-muted small pt-2 ps-1">étudiants encadrés</span>
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
        
        <!-- Onglets pour séparer Encadreur et Lecteur -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs nav-tabs-bordered" id="affectationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="encadreur-tab" data-bs-toggle="tab" data-bs-target="#encadreur" type="button" role="tab" aria-controls="encadreur" aria-selected="true">
                                    <i class="bi bi-person-check-fill me-1"></i>
                                    Étudiants Encadrés (<?= count($etudiantsEncadres) ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="lecteur-tab" data-bs-toggle="tab" data-bs-target="#lecteur" type="button" role="tab" aria-controls="lecteur" aria-selected="false">
                                    <i class="bi bi-book me-1"></i>
                                    Rapports à Évaluer (<?= count($etudiantsLecture) ?>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3" id="affectationTabsContent">
                            <!-- Onglet Encadreur -->
                            <div class="tab-pane fade show active" id="encadreur" role="tabpanel" aria-labelledby="encadreur-tab">
                                <?php if (empty($etudiantsEncadres)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Aucun étudiant à encadrer pour l'année académique sélectionnée.
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-success" onclick="showBulkPointsModal()">
                                            <i class="bi bi-plus-circle"></i> Ajouter des points à tout le monde
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="exportEncadreurExcel()">
                                            <i class="bi bi-file-earmark-excel"></i> Exporter en Excel
                                        </button>
                                    </div>

                                    <table class="table table-striped table-bordered datatable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom Étudiant</th>
                                                <th>Promotion</th>
                                                <th>Lieu de Stage</th>
                                                <th>Lecteur</th>
                                                <th>Note Encadreur</th>
                                                <th>Note Lecteur</th>
                                                <th>Moyenne</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $i = 1;
                                            foreach ($etudiantsEncadres as $etudiant) {
                                                $moyenne = '';
                                                if ($etudiant['cote_entreprise'] !== null && $etudiant['cote_lecteur'] !== null) {
                                                    $moyenne = round(($etudiant['cote_entreprise'] + $etudiant['cote_lecteur']) / 2, 2);
                                                }
                                                
                                                echo "<tr>
                                                    <td>{$i}</td>
                                                    <td>{$etudiant['matricule']}</td>
                                                    <td>{$etudiant['nom_etudiant']}</td>
                                                    <td>{$etudiant['promotion']}</td>
                                                    <td>" . ($etudiant['lieu_stage'] ?? 'Non spécifié') . "</td>
                                                    <td>" . ($etudiant['lecteur_nom'] ?? '<span class="badge bg-warning">Non assigné</span>') . "</td>
                                                    <td>" . ($etudiant['cote_entreprise'] !== null ? $etudiant['cote_entreprise'] . '/20' : '<span class="badge bg-secondary">Non noté</span>') . "</td>
                                                    <td>" . ($etudiant['cote_lecteur'] !== null ? $etudiant['cote_lecteur'] . '/20' : '<span class="badge bg-secondary">Non noté</span>') . "</td>
                                                    <td>" . ($moyenne !== '' ? '<strong>' . $moyenne . '/20</strong>' : '-') . "</td>
                                                    <td>
                                                        <button class='btn btn-sm btn-primary' onclick='editNoteEncadreur({$etudiant['idstage']}, " . ($etudiant['cote_entreprise'] ?? 'null') . ")' title='Modifier la note'>
                                                            <i class='bi bi-pencil'></i>
                                                        </button>";
                                                
                                                if ($etudiant['rapport_path']) {
                                                    echo "<a href='{$etudiant['rapport_path']}' target='_blank' class='btn btn-sm btn-info' title='Voir le rapport'>
                                                            <i class='bi bi-file-earmark-pdf'></i>
                                                        </a>";
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

                            <!-- Onglet Lecteur -->
                            <div class="tab-pane fade" id="lecteur" role="tabpanel" aria-labelledby="lecteur-tab">
                                <?php if (empty($etudiantsLecture)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Aucun rapport à évaluer pour l'année académique sélectionnée.
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-primary" onclick="exportLecteurExcel()">
                                            <i class="bi bi-file-earmark-excel"></i> Exporter en Excel
                                        </button>
                                    </div>

                                    <table class="table table-striped table-bordered datatable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom Étudiant</th>
                                                <th>Promotion</th>
                                                <th>Lieu de Stage</th>
                                                <th>Encadreur</th>
                                                <th>Rapport</th>
                                                <th>Note Lecteur</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $i = 1;
                                            foreach ($etudiantsLecture as $etudiant) {
                                                echo "<tr>
                                                    <td>{$i}</td>
                                                    <td>{$etudiant['matricule']}</td>
                                                    <td>{$etudiant['nom_etudiant']}</td>
                                                    <td>{$etudiant['promotion']}</td>
                                                    <td>" . ($etudiant['lieu_stage'] ?? 'Non spécifié') . "</td>
                                                    <td>" . ($etudiant['encadreur_nom'] ?? '<span class="badge bg-warning">Non assigné</span>') . "</td>
                                                    <td>";
                                                
                                                if ($etudiant['rapport_path']) {
                                                    echo "<a href='{$etudiant['rapport_path']}' target='_blank' class='btn btn-sm btn-success'>
                                                            <i class='bi bi-file-earmark-pdf'></i> Voir
                                                        </a>";
                                                } else {
                                                    echo "<span class='badge bg-secondary'>Non déposé</span>";
                                                }
                                                
                                                echo "</td>
                                                    <td>" . ($etudiant['cote_lecteur'] !== null ? $etudiant['cote_lecteur'] . '/20' : '<span class="badge bg-secondary">Non noté</span>') . "</td>
                                                    <td>
                                                        <button class='btn btn-sm btn-primary' onclick='editNoteLecteur({$etudiant['idstage']}, " . ($etudiant['cote_lecteur'] ?? 'null') . ")' title='Attribuer/Modifier la note'>
                                                            <i class='bi bi-clipboard-check'></i> Noter
                                                        </button>
                                                    </td>
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
            </div>
        </div>
    </section>
</main>

<!-- Modal Modification Note Encadreur -->
<div class="modal fade" id="editNoteEncadreurModal" tabindex="-1" role="dialog" aria-labelledby="editNoteEncadreurModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attribuer/Modifier la Note Encadreur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editNoteEncadreurForm">
                <div class="modal-body">
                    <input type="hidden" name="idstage" id="edit_idstage_encadreur">
                    <div class="mb-3">
                        <label for="edit_note_encadreur" class="form-label">Note sur 20</label>
                        <input type="number" class="form-control" id="edit_note_encadreur" name="note" min="0" max="20" step="0.5" required>
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

<!-- Modal Modification Note Lecteur -->
<div class="modal fade" id="editNoteLecteurModal" tabindex="-1" role="dialog" aria-labelledby="editNoteLecteurModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attribuer/Modifier la Note Lecteur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editNoteLecteurForm">
                <div class="modal-body">
                    <input type="hidden" name="idstage" id="edit_idstage_lecteur">
                    <div class="mb-3">
                        <label for="edit_note_lecteur" class="form-label">Note sur 20</label>
                        <input type="number" class="form-control" id="edit_note_lecteur" name="note" min="0" max="20" step="0.5" required>
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

<!-- Modal Ajout de Points en Masse -->
<div class="modal fade" id="bulkPointsModal" tabindex="-1" role="dialog" aria-labelledby="bulkPointsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter des Points à Tous les Étudiants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkPointsForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Les points seront ajoutés à la note actuelle de chaque étudiant (maximum 20).
                    </div>
                    <div class="mb-3">
                        <label for="bulk_points" class="form-label">Points à ajouter</label>
                        <input type="number" class="form-control" id="bulk_points" name="points" min="0" max="20" step="0.5" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Ajouter les Points</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fonction pour éditer la note de l'encadreur
function editNoteEncadreur(idstage, note) {
    document.getElementById('edit_idstage_encadreur').value = idstage;
    document.getElementById('edit_note_encadreur').value = note !== null ? note : '';
    
    const modal = new bootstrap.Modal(document.getElementById('editNoteEncadreurModal'));
    modal.show();
}

// Fonction pour éditer la note du lecteur
function editNoteLecteur(idstage, note) {
    document.getElementById('edit_idstage_lecteur').value = idstage;
    document.getElementById('edit_note_lecteur').value = note !== null ? note : '';
    
    const modal = new bootstrap.Modal(document.getElementById('editNoteLecteurModal'));
    modal.show();
}

// Fonction pour afficher le modal d'ajout de points en masse
function showBulkPointsModal() {
    const modal = new bootstrap.Modal(document.getElementById('bulkPointsModal'));
    modal.show();
}

// Fonction pour exporter en Excel (Encadreur)
function exportEncadreurExcel() {
    window.location.href = 'controller/export_stage_encadreur.php?annee=<?= $anneeId ?>';
}

// Fonction pour exporter en Excel (Lecteur)
function exportLecteurExcel() {
    window.location.href = 'controller/export_stage_lecteur.php?annee=<?= $anneeId ?>';
}

// Soumission du formulaire de modification de note encadreur
document.getElementById('editNoteEncadreurForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_note_encadreur');
    
    fetch('controller/update_note_stage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Note enregistrée',
                text: data.message || 'La note a été enregistrée avec succès.'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de communiquer avec le serveur.'
        });
    });
});

// Soumission du formulaire de modification de note lecteur
document.getElementById('editNoteLecteurForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_note_lecteur');
    
    fetch('controller/update_note_stage.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Note enregistrée',
                text: data.message || 'La note a été enregistrée avec succès.'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de communiquer avec le serveur.'
        });
    });
});

// Soumission du formulaire d'ajout de points en masse
document.getElementById('bulkPointsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'bulk_add_points');
    formData.append('annee', '<?= $anneeId ?>');
    
    Swal.fire({
        title: 'Confirmation',
        text: 'Ajouter ces points à tous vos étudiants encadrés ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, ajouter',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('controller/update_note_stage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Points ajoutés',
                        text: data.message || 'Les points ont été ajoutés avec succès.'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de communiquer avec le serveur.'
                });
            });
        }
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>
