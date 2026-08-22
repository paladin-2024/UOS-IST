<?php
include "./views/include/header.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>window.location.href = 'login';</script>";
    exit();
}

// Vérifier les droits d'accès
$universite = new Universite();
$agent = new Agent();
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$isJuryPresident = $universite->isJuryPresident($agentId);
$isJuryMember = false;

// Récupérer les bureaux de jury où l'agent est membre
if ($agentId) {
    $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
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
$deliberationId = isset($_GET['deliberation_id']) ? intval($_GET['deliberation_id']) : 0;

if (!$deliberationId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants pour afficher les résultats de délibération.'
        }).then(() => {
                        window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Créer une instance de la classe Deliberation
$deliberation = new Deliberation();

// Récupérer les informations de la délibération
$deliberationInfo = $deliberation->getDeliberationInfo($deliberationId);
if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de récupérer les informations de la délibération.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Vérifier si la délibération est terminée
if ($deliberationInfo['statut'] !== 'Effectuée' && $deliberationInfo['statut'] !== 'Validée' && $deliberationInfo['statut'] !== 'Publiée') {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Délibération non terminée',
            text: 'Cette délibération n\'est pas encore terminée. Veuillez attendre la fin du processus.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les résultats de la délibération
$resultats = $deliberation->getDeliberationResults($deliberationId);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Résultats de la délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=deliberation/seances">Délibération</a></li>
                <li class="breadcrumb-item active">Résultats</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-award me-1"></i>
                            Informations sur la délibération
                        </h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Promotion:</strong></span>
                                        <span><?= htmlspecialchars($deliberationInfo['designationPromotion']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Session:</strong></span>
                                        <span><?= htmlspecialchars($deliberationInfo['session_name']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Année académique:</strong></span>
                                        <span><?= htmlspecialchars($deliberationInfo['annee_acad']) ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Date de délibération:</strong></span>
                                        <span><?= date('d/m/Y H:i', strtotime($deliberationInfo['date_deliberation'])) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Statut:</strong></span>
                                        <span class="badge bg-<?= getStatusBadgeClass($deliberationInfo['statut']) ?>"><?= $deliberationInfo['statut'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><strong>Créé par:</strong></span>
                                        <span><?= htmlspecialchars($deliberationInfo['nom_createur']) ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mb-3">
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-file-earmark-text me-1"></i> Documents
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="controller/generate_pv_deliberation.php?deliberation_id=<?= $deliberationId ?>" target="_blank">
                                            <i class="bi bi-file-earmark-text me-1"></i> PV de délibération
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="controller/generate_palmares.php?deliberation_id=<?= $deliberationId ?>" target="_blank">
                                            <i class="bi bi-trophy me-1"></i> Palmarès
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="controller/generate_releve_notes.php?deliberation_id=<?= $deliberationId ?>" target="_blank">
                                            <i class="bi bi-list-ol me-1"></i> Relevés de notes
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Tableau des résultats -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Matricule</th>
                                        <th scope="col">Nom</th>
                                        <?php 
                                        // Déterminer le nombre de semestres à afficher
                                        $nbSemestres = 0;
                                        if (!empty($resultats) && !empty($resultats[0]['moyennes_semestre'])) {
                                            $nbSemestres = count($resultats[0]['moyennes_semestre']);
                                        }
                                        
                                        // Afficher les en-têtes pour chaque semestre
                                        for ($i = 1; $i <= $nbSemestres; $i++) {
                                            echo "<th scope='col'>S{$i}</th>";
                                        }
                                        ?>
                                        <th scope="col">Moyenne</th>
                                        <th scope="col">Crédits</th>
                                        <th scope="col">Décision</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resultats)): ?>
                                    <tr>
                                        <td colspan="<?= 7 + $nbSemestres ?>" class="text-center">Aucun résultat disponible</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($resultats as $index => $resultat): ?>
                                        <tr>
                                            <th scope="row"><?= $index + 1 ?></th>
                                            <td><?= htmlspecialchars($resultat['etudiant']['matricule']) ?></td>
                                            <td><?= htmlspecialchars($resultat['etudiant']['noms']) ?></td>
                                            
                                            <?php 
                                            // Afficher les moyennes de chaque semestre
                                            for ($i = 0; $i < $nbSemestres; $i++) {
                                                $moyenne = isset($resultat['moyennes_semestre'][$i]) ? 
                                                    $resultat['moyennes_semestre'][$i]['moyenne_deliberee'] : '-';
                                                
                                                $classe = '';
                                                if (is_numeric($moyenne)) {
                                                    $classe = $moyenne >= 10 ? 'text-success' : 'text-danger';
                                                }
                                                
                                                echo "<td class='$classe'>" . (is_numeric($moyenne) ? number_format($moyenne, 2) : $moyenne) . "</td>";
                                            }
                                            ?>
                                            
                                            <!-- Moyenne annuelle -->
                                            <td class="<?= isset($resultat['moyenne_annuelle']['moyenne_deliberee']) && $resultat['moyenne_annuelle']['moyenne_deliberee'] >= 10 ? 'text-success' : 'text-danger' ?>">
                                                <?= isset($resultat['moyenne_annuelle']['moyenne_deliberee']) ? 
                                                    number_format($resultat['moyenne_annuelle']['moyenne_deliberee'], 2) : '-' ?>
                                            </td>
                                            
                                            <!-- Crédits -->
                                            <td>
                                                <?php 
                                                if (isset($resultat['moyenne_annuelle']['credits_obtenus']) && isset($resultat['moyenne_annuelle']['credits_total'])) {
                                                    echo $resultat['moyenne_annuelle']['credits_obtenus'] . '/' . $resultat['moyenne_annuelle']['credits_total'];
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            
                                            <!-- Décision -->
                                            <td>
                                                <?php 
                                                if (isset($resultat['resultat_final']['decision'])) {
                                                    $decision = $resultat['resultat_final']['decision'];
                                                    $badgeClass = '';
                                                    
                                                    switch ($decision) {
                                                        case 'Admis':
                                                            $badgeClass = 'bg-success';
                                                            break;
                                                        case 'Ajourné':
                                                            $badgeClass = 'bg-danger';
                                                            break;
                                                        case 'Admis par compensation':
                                                            $badgeClass = 'bg-warning';
                                                            break;
                                                        case 'Admis sous condition':
                                                            $badgeClass = 'bg-info';
                                                            break;
                                                        default:
                                                            $badgeClass = 'bg-secondary';
                                                    }
                                                    
                                                    echo "<span class='badge $badgeClass'>$decision</span>";
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            
                                            <!-- Actions -->
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="voirDetails('<?= $resultat['etudiant']['matricule'] ?>')">
                                                        <i class="bi bi-eye"></i> Détails
                                                    </button>
                                                    
                                                    <?php if (($isAdmin || $isJuryPresident) && $deliberationInfo['statut'] !== 'Publiée'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            onclick="modifierResultat('<?= $resultat['etudiant']['matricule'] ?>')">
                                                        <i class="bi bi-pencil"></i> Modifier
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
                        
                        <!-- Statistiques -->
                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="card-title">Statistiques de la délibération</h5>
                                
                                <?php
                                // Calculer les statistiques
                                $totalEtudiants = count($resultats);
                                $admis = 0;
                                $ajournes = 0;
                                $admisCompensation = 0;
                                $admisCondition = 0;
                                
                                foreach ($resultats as $resultat) {
                                    if (isset($resultat['resultat_final']['decision'])) {
                                        switch ($resultat['resultat_final']['decision']) {
                                            case 'Admis':
                                                $admis++;
                                                break;
                                            case 'Ajourné':
                                                $ajournes++;
                                                break;
                                            case 'Admis par compensation':
                                                $admisCompensation++;
                                                break;
                                            case 'Admis sous condition':
                                                $admisCondition++;
                                                break;
                                        }
                                    }
                                }
                                
                                // Calculer les pourcentages
                                $pourcentageAdmis = $totalEtudiants > 0 ? round(($admis / $totalEtudiants) * 100, 2) : 0;
                                $pourcentageAjournes = $totalEtudiants > 0 ? round(($ajournes / $totalEtudiants) * 100, 2) : 0;
                                $pourcentageAdmisCompensation = $totalEtudiants > 0 ? round(($admisCompensation / $totalEtudiants) * 100, 2) : 0;
                                $pourcentageAdmisCondition = $totalEtudiants > 0 ? round(($admisCondition / $totalEtudiants) * 100, 2) : 0;
                                ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card info-card sales-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Taux de réussite</h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-check-circle"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= $pourcentageAdmis ?>%</h6>
                                                        <span class="text-success small pt-1 fw-bold"><?= $admis ?></span> 
                                                        <span class="text-muted small pt-2 ps-1">étudiants admis sur <?= $totalEtudiants ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card info-card revenue-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Taux d'échec</h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-x-circle"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= $pourcentageAjournes ?>%</h6>
                                                        <span class="text-danger small pt-1 fw-bold"><?= $ajournes ?></span> 
                                                        <span class="text-muted small pt-2 ps-1">étudiants ajournés sur <?= $totalEtudiants ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="card info-card customers-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Admis par compensation</h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= $pourcentageAdmisCompensation ?>%</h6>
                                                        <span class="text-warning small pt-1 fw-bold"><?= $admisCompensation ?></span> 
                                                        <span class="text-muted small pt-2 ps-1">étudiants admis par compensation</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card info-card customers-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Admis sous condition</h5>
                                                <div class="d-flex align-items-center">
                                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-exclamation-circle"></i>
                                                    </div>
                                                    <div class="ps-3">
                                                        <h6><?= $pourcentageAdmisCondition ?>%</h6>
                                                        <span class="text-info small pt-1 fw-bold"><?= $admisCondition ?></span> 
                                                        <span class="text-muted small pt-2 ps-1">étudiants admis sous condition</span>
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
            </div>
        </div>
    </section>
</main>

<!-- Modal pour voir les détails d'un étudiant -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails des résultats</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="details_container">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="btnPrintDetails">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un résultat -->
<div class="modal fade" id="modifierResultatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le résultat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="modifierResultatForm" action="controller/update_deliberation_result.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="deliberation_id" value="<?= $deliberationId ?>">
                    <input type="hidden" name="matricule" id="matricule_modif" value="">
                    
                    <div class="mb-3">
                        <label for="decision" class="form-label">Décision</label>
                        <select name="decision" id="decision" class="form-select" required>
                            <option value="Admis">Admis</option>
                            <option value="Ajourné">Ajourné</option>
                            <option value="Admis par compensation">Admis par compensation</option>
                            <option value="Admis sous condition">Admis sous condition</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="credits_obtenus" class="form-label">Crédits obtenus</label>
                        <input type="number" class="form-control" id="credits_obtenus" name="credits_obtenus" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire / Justification</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3" required></textarea>
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

<script>
// Fonction pour voir les détails d'un étudiant
function voirDetails(matricule) {
    // Afficher le modal
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
    
    // Charger les détails de l'étudiant
    fetch(`controller/get_student_deliberation_details.php?deliberation_id=<?= $deliberationId ?>&matricule=${matricule}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('details_container');
            
            if (data.error) {
                container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            // Construire l'affichage des détails
            let html = `
                <div class="student-details">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h4>${data.etudiant.noms}</h4>
                            <p class="text-muted">Matricule: ${data.etudiant.matricule}</p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Résultat final</h5>
                                    <p class="card-text">
                                        <strong>Moyenne générale:</strong> ${data.moyenne_annuelle ? data.moyenne_annuelle.moyenne_deliberee.toFixed(2) : '-'}<br>
                                        <strong>Crédits obtenus:</strong> ${data.moyenne_annuelle ? data.moyenne_annuelle.credits_obtenus : '0'}/${data.moyenne_annuelle ? data.moyenne_annuelle.credits_total : '0'}<br>
                                        <strong>Décision:</strong> <span class="badge bg-${getDecisionBadgeClass(data.resultat_final ? data.resultat_final.decision : '')}">${data.resultat_final ? data.resultat_final.decision : '-'}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Commentaire</h5>
                                    <p class="card-text">
                                        ${data.resultat_final && data.resultat_final.commentaire ? data.resultat_final.commentaire : 'Aucun commentaire'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5>Résultats par semestre</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Semestre</th>
                                    <th>Moyenne</th>
                                    <th>Crédits</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            if (data.moyennes_semestre && data.moyennes_semestre.length > 0) {
                data.moyennes_semestre.forEach(semestre => {
                    html += `
                        <tr>
                            <td>Semestre ${semestre.numeroSemestre}</td>
                            <td class="${semestre.moyenne_deliberee >= 10 ? 'text-success' : 'text-danger'}">${semestre.moyenne_deliberee.toFixed(2)}</td>
                            <td>${semestre.credits_obtenus}/${semestre.credits_total}</td>
                            <td><span class="badge bg-${semestre.est_valide ? 'success' : 'danger'}">${semestre.est_valide ? 'Validé' : 'Non validé'}</span></td>
                        </tr>`;
                });
            } else {
                html += `<tr><td colspan="4" class="text-center">Aucun résultat par semestre disponible</td></tr>`;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <h5>Résultats par UE</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>UE</th>
                                    <th>Semestre</th>
                                    <th>Moyenne</th>
                                    <th>Crédits</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            if (data.moyennes_ue && data.moyennes_ue.length > 0) {
                data.moyennes_ue.forEach(ue => {
                    html += `
                        <tr>
                            <td>${ue.designationUE}</td>
                            <td>S${ue.numeroSemestre}</td>
                            <td class="${ue.moyenne_deliberee >= 10 ? 'text-success' : 'text-danger'}">${ue.moyenne_deliberee.toFixed(2)}</td>
                            <td>${ue.credits_obtenus}/${ue.nombre_credits}</td>
                            <td><span class="badge bg-${ue.est_validee ? 'success' : 'danger'}">${ue.est_validee ? 'Validée' : 'Non validée'}</span></td>
                        </tr>`;
                });
            } else {
                html += `<tr><td colspan="5" class="text-center">Aucun résultat par UE disponible</td></tr>`;
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>`;
            
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('details_container').innerHTML = `
                <div class="alert alert-danger">
                    Une erreur est survenue lors du chargement des détails.
                </div>
            `;
        });
}

// Fonction pour modifier un résultat
function modifierResultat(matricule) {
    document.getElementById('matricule_modif').value = matricule;
    
    // Récupérer les informations actuelles
    fetch(`controller/get_student_deliberation_details.php?deliberation_id=<?= $deliberationId ?>&matricule=${matricule}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.error
                });
                return;
            }
            
            // Pré-remplir le formulaire
            if (data.resultat_final) {
                document.getElementById('decision').value = data.resultat_final.decision;
                document.getElementById('credits_obtenus').value = data.resultat_final.credits_acquis;
                document.getElementById('commentaire').value = data.resultat_final.commentaire || '';
            } else if (data.moyenne_annuelle) {
                document.getElementById('credits_obtenus').value = data.moyenne_annuelle.credits_obtenus;
                
                // Déterminer la décision en fonction de la moyenne
                const moyenne = data.moyenne_annuelle.moyenne_deliberee;
                const decision = moyenne >= 10 ? 'Admis' : 'Ajourné';
                document.getElementById('decision').value = decision;
                
                document.getElementById('commentaire').value = '';
            } else {
                document.getElementById('credits_obtenus').value = '0';
                document.getElementById('decision').value = 'Ajourné';
                document.getElementById('commentaire').value = '';
            }
            
            // Afficher le modal
            const modal = new bootstrap.Modal(document.getElementById('modifierResultatModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la récupération des informations.'
            });
        });
}

// Fonction pour imprimer les détails
document.getElementById('btnPrintDetails').addEventListener('click', function() {
    const detailsContent = document.getElementById('details_container').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Détails des résultats</title>
            <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row mb-4">
                    <div class="col-12 text-center">
                        <h3>Détails des résultats de délibération</h3>
                        <h4>${document.title}</h4>
                        <p>Date d'impression: ${new Date().toLocaleString()}</p>
                    </div>
                </div>
                
                ${detailsContent}
                
                <div class="row mt-5 no-print">
                    <div class="col-12 text-center">
                        <button class="btn btn-primary" onclick="window.print()">Imprimer</button>
                        <button class="btn btn-secondary" onclick="window.close()">Fermer</button>
                    </div>
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    setTimeout(() => {
        printWindow.focus();
    }, 500);
});

// Fonction pour obtenir la classe de badge en fonction du statut
function getStatusBadgeClass(statut) {
    switch(statut) {
        case 'En préparation': return 'warning';
        case 'Effectuée': return 'info';
        case 'Validée': return 'success';
        case 'Publiée': return 'primary';
        default: return 'secondary';
    }
}

// Fonction pour obtenir la classe de badge en fonction de la décision
function getDecisionBadgeClass(decision) {
    switch(decision) {
        case 'Admis': return 'success';
        case 'Ajourné': return 'danger';
        case 'Admis par compensation': return 'warning';
        case 'Admis sous condition': return 'info';
        default: return 'secondary';
    }
}
</script>

<?php include "./views/include/footer.php"; ?>


