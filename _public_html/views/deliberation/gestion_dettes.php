<?php
include "./views/include/header.php";

require_once './models/Dette.php';
require_once './models/Universite.php';
require_once './models/Deliberation.php';

$dette = new Dette();
$universite = new Universite();
$deliberation = new Deliberation();

// Vérifier les droits d'accès (admin ou cellule LMD)
if (!isset($_SESSION['idRole']) || !in_array($_SESSION['idRole'], [1, 2])) { // Ajuster selon vos rôles
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$action = isset($_GET['action']) ? $_GET['action'] : 'liste';
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : '';

// Récupérer les données pour les sélecteurs
$promotions = $universite->getPromotions();
$annees = $universite->getAnneeAcademiques();
$sessions = $universite->getSessions();
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Dettes Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Gestion des dettes</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <!-- Carte de sélection des paramètres -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-filter me-2"></i>
                            Filtres de recherche
                        </h5>

                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="deliberation/gestion_dettes">
                            
                            <div class="col-md-3">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select name="promotion" id="promotion" class="form-select" onchange="this.form.submit()">
                                    <option value="">Toutes les promotions</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>" <?= $promotionId == $promo['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                            <?= $promo['est_terminale'] ? ' (Terminale)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select" onchange="this.form.submit()">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $anneeId == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="session" class="form-label">Session</label>
                                <select name="session" id="session" class="form-select" onchange="this.form.submit()">
                                    <option value="">Toutes les sessions</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= $sessionId == $session['idsession'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="matricule" class="form-label">Rechercher un étudiant</label>
                                <input type="text" class="form-control" id="matricule" name="matricule" 
                                       placeholder="Matricule ou nom" value="<?= htmlspecialchars($matricule) ?>">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Rechercher
                                </button>
                                <a href="?view=deliberation/gestion_dettes" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                </a>
                                <?php if ($promotionId && $anneeId): ?>
                                    <button type="button" class="btn btn-success" onclick="exporterListeDettes()">
                                        <i class="bi bi-file-pdf me-1"></i> Exporter PDF
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($promotionId && $anneeId): ?>
                    <?php
                    // Récupérer les statistiques
                    $stats = $dette->getStatistiquesDettes($promotionId, $anneeId);
                    ?>
                    
                    <!-- Carte des statistiques -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up me-2"></i>
                                Statistiques des dettes
                            </h5>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h3><?= $stats['nombre_etudiants_avec_dettes'] ?? 0 ?></h3>
                                            <p>Étudiants avec dettes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h3><?= $stats['nombre_total_dettes'] ?? 0 ?></h3>
                                            <p>Total des dettes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h3><?= $stats['dettes_validees'] ?? 0 ?></h3>
                                            <p>Dettes validées</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger text-white">
                                        <div class="card-body text-center">
                                            <h3><?= $stats['dettes_en_cours'] ?? 0 ?></h3>
                                            <p>Dettes en cours</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste des étudiants avec dettes -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-people me-2"></i>
                                Liste des étudiants avec dettes
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tableDettes">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Matricule</th>
                                            <th>Nom de l'étudiant</th>
                                            <th>Nombre de dettes</th>
                                            <th>Crédits en dette</th>
                                            <th>Dettes validées</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Récupérer la liste des étudiants avec dettes
                                        $sql = "SELECT 
                                                    e.matricule,
                                                    e.noms,
                                                    COUNT(d.id_dette) as nombre_dettes,
                                                    SUM(d.credits_ecue) as total_credits,
                                                    SUM(CASE WHEN d.statut = 'Validée' THEN 1 ELSE 0 END) as dettes_validees
                                                FROM dette_etudiant d
                                                JOIN etudiant e ON d.matricule = e.matricule
                                                WHERE d.promotion_idpromotion = :promotion
                                                AND d.annee_acad_idannee_acad = :annee";
                                        
                                        $params = [
                                            'promotion' => $promotionId,
                                            'annee' => $anneeId
                                        ];
                                        
                                        if ($sessionId) {
                                            $sql .= " AND d.session_idsession = :session";
                                            $params['session'] = $sessionId;
                                        }
                                        
                                        if ($matricule) {
                                            $sql .= " AND (e.matricule LIKE :search OR e.noms LIKE :search)";
                                            $params['search'] = "%$matricule%";
                                        }
                                        
                                        $sql .= " GROUP BY e.matricule, e.noms ORDER BY e.noms";
                                        
                                        $stmt = Connexion::getInstance()->getPDO()->prepare($sql);
                                        $stmt->execute($params);
                                        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        $index = 1;
                                        foreach ($etudiants as $etudiant):
                                        ?>
                                            <tr>
                                                <td><?= $index++ ?></td>
                                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning"><?= $etudiant['nombre_dettes'] ?></span>
                                                </td>
                                                <td class="text-center"><?= $etudiant['total_credits'] ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-success"><?= $etudiant['dettes_validees'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info" onclick="voirDetailsDettes('<?= $etudiant['matricule'] ?>')">
                                                        <i class="bi bi-eye"></i> Détails
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="encoderNotes('<?= $etudiant['matricule'] ?>')">
                                                        <i class="bi bi-pencil"></i> Encoder
                                                    </button>
                                                    <a href="controller/export_bulletin_dettes.php?matricule=<?= $etudiant['matricule'] ?><?= $anneeId ? '&annee=' . $anneeId : '' ?>" 
                                                       class="btn btn-sm btn-success" target="_blank">
                                                        <i class="bi bi-file-pdf"></i> Bulletin
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour voir les détails des dettes -->
<div class="modal fade" id="modalDetailsDettes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails des dettes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsDettesContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<!-- Modal pour encoder les notes -->
<div class="modal fade" id="modalEncoderNotes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Encoder les notes de rachat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="encoderNotesContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour voir les détails des dettes
function voirDetailsDettes(matricule) {
    $('#detailsDettesContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
    $('#modalDetailsDettes').modal('show');
    
    $.ajax({
        url: 'controller/ajax_dette_details.php',
        type: 'GET',
        data: {
            matricule: matricule,
            annee: <?= $anneeId ?>
        },
        success: function(response) {
            $('#detailsDettesContent').html(response);
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de charger les détails', 'error');
        }
    });
}

// Fonction pour encoder les notes
function encoderNotes(matricule) {
    $('#encoderNotesContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
    $('#modalEncoderNotes').modal('show');
    
    $.ajax({
        url: 'controller/ajax_dette_encoder.php',
        type: 'GET',
        data: {
            matricule: matricule,
            annee: <?= $anneeId ?>,
            session: <?= $sessionId ?>
        },
        success: function(response) {
            $('#encoderNotesContent').html(response);
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de charger le formulaire', 'error');
        }
    });
}

// Fonction pour exporter la liste des dettes
function exporterListeDettes() {
    window.open('controller/export_liste_dettes.php?promotion=<?= $promotionId ?>&annee=<?= $anneeId ?>&session=<?= $sessionId ?>', '_blank');
}

// DataTable
$(document).ready(function() {
    $('#tableDettes').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json'
        },
        pageLength: 25,
        order: [[2, 'asc']]
    });
});
</script>

<?php include "./views/include/footer.php"; ?>