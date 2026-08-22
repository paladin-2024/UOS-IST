<?php
include "./views/include/header.php";

// Initialiser la connexion
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'ID du sujet
$sujetId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sujetId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucun sujet spécifié.'
        }).then(() => {
            window.location.href = '?view=recherche/sujets';
        });
    </script>";
    exit;
}

// Récupérer les détails du sujet
$querySujet = "SELECT s.*, 
                   a.designation as annee_designation, 
                   a.idannee_acad,
                   e.noms as etudiant_nom,
                   e.idetudiant,
                   e.matricule as etudiant_matricule,
                   sp.designation as specialisation_designation,
                   ur.designation_UR as unite_recherche,
                   direc.noms as directeur_nom,
                   direc.idAgent as directeur_id,
                   g_direc.designation as directeur_grade,
                   enc.noms as encadreur_nom,
                   enc.idAgent as encadreur_id,
                   g_enc.designation as encadreur_grade,
                   p.designationPromotion as promotion,
                   p.idpromotion
            FROM sujets s
            LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
            LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
            LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            LEFT JOIN specialisation sp ON s.idSpecialisation = sp.idSpecialisation
            LEFT JOIN unite_recherche ur ON sp.idUnite_recherche = ur.idunite_recherche
            LEFT JOIN agent direc ON s.idDirecteur = direc.idAgent
            LEFT JOIN grade g_direc ON direc.grade_id = g_direc.idgrade
            LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
            LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
            WHERE s.idsujets = :sujetId";

$stmtSujet = $connexion->prepare($querySujet);
$stmtSujet->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
$stmtSujet->execute();
$sujet = $stmtSujet->fetch(PDO::FETCH_ASSOC);

if (!$sujet) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Sujet non trouvé.'
        }).then(() => {
            window.location.href = '?view=recherche/sujets';
        });
    </script>";
    exit;
}

// Récupérer les tâches associées au sujet
$queryTaches = "SELECT t.*, 
                       s.etudiant_idetudiant,
                       e.noms as etudiant_nom, 
                       a.noms as agent_nom, 
                       a.idAgent,
                       g.designation as grade
                FROM taches t
                LEFT JOIN sujets s ON t.sujets_idsujets = s.idsujets
                LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN agent a ON t.idUser = a.idAgent
                LEFT JOIN grade g ON a.grade_id = g.idgrade
                WHERE t.sujets_idsujets = :sujetId
                ORDER BY t.dateTache DESC";


$stmtTaches = $connexion->prepare($queryTaches);
$stmtTaches->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
$stmtTaches->execute();
$taches = $stmtTaches->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour obtenir l'historique des validations du sujet
function getHistoriqueValidations($connexion, $sujetId) {
    $query = "SELECT h.*, u.loginUser as validateur
              FROM sujet_validation_history h
              LEFT JOIN t_users u ON h.idUser = u.idUser
              WHERE h.idsujets = :sujetId
              ORDER BY h.date_action DESC";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



$historiqueValidations = getHistoriqueValidations($connexion, $sujetId);

// Fonction pour formater une date
function formatDate($date) {
    return date('d/m/Y à H:i', strtotime($date));
}

// Fonction pour obtenir la classe de badge selon l'état
function getBadgeClass($statut) {
    switch ($statut) {
        case 'Validé':
            return 'success';
        case 'En attente':
            return 'warning';
        case 'Rejeté':
            return 'danger';
        case 'Modifié':
            return 'info';
        case 'En cours':
            return 'primary';
        case 'Terminé':
            return 'success';
        case 'Abandonné':
            return 'secondary';
        default:
            return 'secondary';
    }
}

// Vérifier si l'utilisateur actuel est le directeur ou l'encadreur du sujet
$isDirecteur = isset($_SESSION['agent_id']) && $_SESSION['agent_id'] == $sujet['directeur_id'];
$isEncadreur = isset($_SESSION['agent_id']) && $_SESSION['agent_id'] == $sujet['encadreur_id'];
$canEdit = $isDirecteur || $isEncadreur || isset($_SESSION['isAdmin']);

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU SUJET</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=recherche/sujets">Sujets</a></li>
                <li class="breadcrumb-item active">Détails du sujet</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Informations du sujet -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Informations du sujet</h5>
                            <div>
                                <?php if ($canEdit): ?>
                                <a href="?view=recherche/edit_sujet&id=<?= $sujetId ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil-square"></i> Modifier
                                </a>
                                <?php endif; ?>
                                <a href="controller/export_sujet_pdf.php?id=<?= $sujetId ?>" class="btn btn-success btn-sm" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                                </a>
                            </div>
                        </div>

                        <!-- Informations générales -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3">Intitulé du sujet</h6>
                            <p class="fs-5 fw-bold"><?= htmlspecialchars($sujet['intitule']) ?></p>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6 class="fw-bold text-muted mb-1">Spécialisation</h6>
                                        <p><?= htmlspecialchars($sujet['specialisation_designation'] ?? 'Non définie') ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6 class="fw-bold text-muted mb-1">Unité de recherche</h6>
                                        <p><?= htmlspecialchars($sujet['unite_recherche'] ?? 'Non définie') ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6 class="fw-bold text-muted mb-1">Année académique</h6>
                                        <p><?= htmlspecialchars($sujet['annee_designation']) ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h6 class="fw-bold text-muted mb-1">Statut</h6>
                                        <span class="badge bg-<?= getBadgeClass($sujet['statut_validation']) ?>">
                                            <?= htmlspecialchars($sujet['statut_validation']) ?>
                                        </span>
                                        <?php if ($sujet['etatSujet']): ?>
                                        <span class="badge bg-<?= getBadgeClass($sujet['etatSujet']) ?> ms-2">
                                            <?= htmlspecialchars($sujet['etatSujet']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Encadrement -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 fw-bold">Encadrement</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-bold text-muted mb-1">Directeur</h6>
                                            <p>
                                                <?php if ($sujet['directeur_id']): ?>
                                                    <?= htmlspecialchars($sujet['directeur_grade'] . ' ' . $sujet['directeur_nom']) ?>
                                                    <a href="?view=recherche/travaux_enseignant&id=<?= $sujet['directeur_id'] ?>" class="badge bg-info text-decoration-none ms-2">
                                                        <i class="bi bi-eye-fill"></i> Voir ses travaux
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assigné</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-bold text-muted mb-1">Co-encadreur</h6>
                                            <p>
                                                <?php if ($sujet['encadreur_id']): ?>
                                                    <?= htmlspecialchars($sujet['encadreur_grade'] . ' ' . $sujet['encadreur_nom']) ?>
                                                    <a href="?view=recherche/travaux_enseignant&id=<?= $sujet['encadreur_id'] ?>" class="badge bg-info text-decoration-none ms-2">
                                                        <i class="bi bi-eye-fill"></i> Voir ses travaux
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assigné</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Étudiant -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 fw-bold">Étudiant</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($sujet['etudiant_nom']): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-muted mb-1">Nom</h6>
                                                <p>
                                                    <?= htmlspecialchars($sujet['etudiant_nom']) ?>
                                                    <a href="?view=recherche/etudiant_detail&id=<?= $sujet['idetudiant'] ?>" class="badge bg-info text-decoration-none ms-2">
                                                        <i class="bi bi-person"></i> Profil
                                                    </a>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <h6 class="fw-bold text-muted mb-1">Matricule</h6>
                                                <p><?= htmlspecialchars($sujet['etudiant_matricule'] ?? 'Non disponible') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                                                            <h6 class="fw-bold text-muted mb-1">Promotion</h6>
                                            <p><?= htmlspecialchars($sujet['promotion'] ?? 'Non disponible') ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Aucun étudiant associé à ce sujet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tâches associées -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                                <h6 class="mb-0 fw-bold">Tâches associées</h6>
                                <?php if ($canEdit): ?>
                                <a href="?view=recherche/add_tache&sujet_id=<?= $sujetId ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i> Ajouter une tâche
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($taches)): ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Aucune tâche associée à ce sujet.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Description</th>
                                                    <th>Auteur</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($taches as $tache): ?>
                                                    <tr>
                                                        <td><?= formatDate($tache['dateTache']) ?></td>
                                                        <td><?= htmlspecialchars(substr($tache['description'], 0, 50)) ?><?= strlen($tache['description']) > 50 ? '...' : '' ?></td>
                                                        <td>
                                                            <?php if ($tache['agent_nom']): ?>
                                                                <span class="badge bg-primary">
                                                                    <?= htmlspecialchars($tache['grade'] ? $tache['grade'] . ' ' : '') ?>
                                                                    <?= htmlspecialchars($tache['agent_nom']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-info">
                                                                    <?= htmlspecialchars($tache['etudiant_nom'] ?? 'Inconnu') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= getBadgeClass($tache['validation']) ?>">
                                                                <?= htmlspecialchars($tache['validation']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="?view=recherche/tache_detail&id=<?= $tache['idtaches'] ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($canEdit): ?>
                                                            <a href="?view=recherche/edit_tache&id=<?= $tache['idtaches'] ?>" class="btn btn-sm btn-warning">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
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
            </div>

            <!-- Sidebar d'informations supplémentaires -->
            <div class="col-lg-12">
                <!-- Historique des validations -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Historique des validations</h5>
                        <?php if (empty($historiqueValidations)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun historique de validation disponible.
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($historiqueValidations as $validation): ?>
                                    <div class="timeline-item pb-4">
                                        <div class="timeline-marker bg-<?= getBadgeClass($validation['statut']) ?>"></div>
                                        <div class="timeline-content">
                                            <h6 class="fw-bold mb-0">
                                                <span class="badge bg-<?= getBadgeClass($validation['statut']) ?>">
                                                    <?= htmlspecialchars($validation['statut']) ?>
                                                </span>
                                                <small class="text-muted ms-2"><?= formatDate($validation['date_validation']) ?></small>
                                            </h6>
                                            <p class="mb-2 mt-1">
                                                Par: <?= htmlspecialchars($validation['validateur']) ?>
                                            </p>
                                            <?php if (!empty($validation['commentaire'])): ?>
                                                <p class="mb-0 bg-light p-2 rounded">
                                                    <?= nl2br(htmlspecialchars($validation['commentaire'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Actions rapides</h5>
                        <div class="d-grid gap-2">
                            <?php if ($canEdit): ?>
                                <?php if ($sujet['etatSujet'] !== 'Terminé'): ?>
                                    <button class="btn btn-success" onclick="marquerTermine(<?= $sujetId ?>)">
                                        <i class="bi bi-check-circle"></i> Marquer comme terminé
                                    </button>
                                <?php endif; ?>
                                <?php if ($sujet['statut_validation'] !== 'Validé'): ?>
                                    <button class="btn btn-primary" onclick="validerSujet(<?= $sujetId ?>)">
                                        <i class="bi bi-check-all"></i> Valider le sujet
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-secondary" onclick="changerEncadrement(<?= $sujetId ?>)">
                                    <i class="bi bi-people"></i> Modifier l'encadrement
                                </button>
                            <?php endif; ?>
                            <a href="controller/export_sujet_pdf.php?id=<?= $sujetId ?>" class="btn btn-info" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
                            </a>
                            <a href="?view=recherche/sujets" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Retour à la liste
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progression (si applicable) -->
                <?php if ($sujet['etudiant_nom']): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Progression</h5>
                        <?php 
                        // Calculer un statut de progression en fonction des tâches
                        $totalTaches = count($taches);
                        $tachesTerminees = 0;
                        $tachesValidees = 0;
                        
                        foreach ($taches as $tache) {
                            if ($tache['validation'] === 'Validé') {
                                $tachesValidees++;
                                $tachesTerminees++;
                            } elseif ($tache['validation'] === 'Terminé') {
                                $tachesTerminees++;
                            }
                        }
                        
                        $pourcentageProgression = $totalTaches > 0 ? ($tachesValidees / $totalTaches) * 100 : 0;
                        ?>
                        
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pourcentageProgression ?>%" 
                                 aria-valuenow="<?= $pourcentageProgression ?>" aria-valuemin="0" aria-valuemax="100">
                                <?= round($pourcentageProgression) ?>%
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="mb-0"><strong>Tâches validées:</strong> <?= $tachesValidees ?>/<?= $totalTaches ?></p>
                                <p class="mb-0"><strong>Tâches en attente:</strong> <?= $totalTaches - $tachesTerminees ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Modals pour les actions -->
<div class="modal fade" id="marquerTermineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Marquer comme terminé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/update_sujet_status.php" method="POST">
                <input type="hidden" name="sujet_id" id="termineId" value="">
                <input type="hidden" name="action" value="termine">
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir marquer ce sujet comme terminé?</p>
                    <div class="mb-3">
                        <label for="commentaireTermine" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaireTermine" name="commentaire" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="validerSujetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valider le sujet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/update_sujet_status.php" method="POST">
                <input type="hidden" name="sujet_id" id="validerId" value="">
                <input type="hidden" name="action" value="valider">
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir valider ce sujet?</p>
                    <div class="mb-3">
                        <label for="commentaireValider" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaireValider" name="commentaire" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="changerEncadrementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'encadrement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/update_encadrement.php" method="POST">
                <input type="hidden" name="sujet_id" id="encadrementId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="directeur_id" class="form-label">Directeur</label>
                        <select class="form-select" id="directeur_id" name="directeur_id">
                            <option value="">Sélectionnez un directeur</option>
                            <?php
                            // Récupérer tous les enseignants
                            $queryEnseignants = "SELECT a.idAgent, a.noms, g.designation as grade 
                                               FROM agent a 
                                               LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                               WHERE a.type_agent = 'Enseignant' 
                                               ORDER BY a.noms";
                            $stmtEnseignants = $connexion->prepare($queryEnseignants);
                            $stmtEnseignants->execute();
                                                        $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($enseignants as $enseignant) {
                                $selected = ($enseignant['idAgent'] == $sujet['directeur_id']) ? 'selected' : '';
                                echo '<option value="' . $enseignant['idAgent'] . '" ' . $selected . '>';
                                echo htmlspecialchars(($enseignant['grade'] ? $enseignant['grade'] . ' ' : '') . $enseignant['noms']);
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="encadreur_id" class="form-label">Co-encadreur (optionnel)</label>
                        <select class="form-select" id="encadreur_id" name="encadreur_id">
                            <option value="">Aucun co-encadreur</option>
                            <?php
                            foreach ($enseignants as $enseignant) {
                                $selected = ($enseignant['idAgent'] == $sujet['encadreur_id']) ? 'selected' : '';
                                echo '<option value="' . $enseignant['idAgent'] . '" ' . $selected . '>';
                                echo htmlspecialchars(($enseignant['grade'] ? $enseignant['grade'] . ' ' : '') . $enseignant['noms']);
                                echo '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="commentaireEncadrement" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaireEncadrement" name="commentaire" rows="3"></textarea>
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

<style>
/* Style pour la timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -23px;
    top: 16px;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-content {
    padding-bottom: 15px;
}
</style>

<script>
// Fonction pour marquer un sujet comme terminé
function marquerTermine(sujetId) {
    document.getElementById('termineId').value = sujetId;
    var modal = new bootstrap.Modal(document.getElementById('marquerTermineModal'));
    modal.show();
}

// Fonction pour valider un sujet
function validerSujet(sujetId) {
    document.getElementById('validerId').value = sujetId;
    var modal = new bootstrap.Modal(document.getElementById('validerSujetModal'));
    modal.show();
}

// Fonction pour changer l'encadrement
function changerEncadrement(sujetId) {
    document.getElementById('encadrementId').value = sujetId;
    var modal = new bootstrap.Modal(document.getElementById('changerEncadrementModal'));
    modal.show();
}

// Validation pour s'assurer que le directeur et l'encadreur sont différents
document.addEventListener('DOMContentLoaded', function() {
    var encadrementForm = document.querySelector('#changerEncadrementModal form');
    if (encadrementForm) {
        encadrementForm.addEventListener('submit', function(event) {
            var directeurId = document.getElementById('directeur_id').value;
            var encadreurId = document.getElementById('encadreur_id').value;
            
            if (directeurId && encadreurId && directeurId === encadreurId) {
                event.preventDefault();
                alert('Le directeur et le co-encadreur ne peuvent pas être la même personne.');
                return false;
            }
        });
    }
    
    // Activer les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>
