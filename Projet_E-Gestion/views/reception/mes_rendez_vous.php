<?php
include "./views/include/header.php";

// Récupération de l'agent connecté
$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer l'agent connecté
$stmtAgent = $db->prepare("SELECT idAgent FROM agent WHERE idAgent = (SELECT idAgent FROM t_users WHERE idUser = ?)");
$stmtAgent->execute([$userId]);
$agentConnecte = $stmtAgent->fetch(PDO::FETCH_ASSOC);

if (!$agentConnecte) {
    echo "<script>alert('Vous n\'êtes pas autorisé à accéder à cette page.'); window.location.href = '../';</script>";
    exit();
}

$idAgent = $agentConnecte['idAgent'];

// Statistiques des rendez-vous
$statsQuery = $db->prepare("
    SELECT 
        statut_rendez_vous,
        COUNT(*) as nombre
    FROM rendez_vous 
    WHERE Agent_idAgent = ? 
    AND date_rendez_vous >= CURDATE()
    GROUP BY statut_rendez_vous
");
$statsQuery->execute([$idAgent]);
$stats = $statsQuery->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau des statistiques
$statistiques = [
    'planifie' => 0,
    'confirme' => 0,
    'reporte' => 0,
    'annule' => 0,
    'termine' => 0
];

foreach ($stats as $stat) {
    $statistiques[$stat['statut_rendez_vous']] = $stat['nombre'];
}

// Rendez-vous du jour
$rdvJourQuery = $db->prepare("
    SELECT rv.*, s.designation as nom_service
    FROM rendez_vous rv
    LEFT JOIN service s ON rv.Service_idService = s.idService
    WHERE rv.Agent_idAgent = ? 
    AND rv.date_rendez_vous = CURDATE()
    ORDER BY rv.heure_debut
");
$rdvJourQuery->execute([$idAgent]);
$rdvJour = $rdvJourQuery->fetchAll(PDO::FETCH_ASSOC);

// Prochains rendez-vous (7 prochains jours)
$prochainsRdvQuery = $db->prepare("
    SELECT rv.*, s.designation as nom_service
    FROM rendez_vous rv
    LEFT JOIN service s ON rv.Service_idService = s.idService
    WHERE rv.Agent_idAgent = ? 
    AND rv.date_rendez_vous BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND rv.statut_rendez_vous NOT IN ('annule', 'termine')
    ORDER BY rv.date_rendez_vous, rv.heure_debut
    LIMIT 10
");
$prochainsRdvQuery->execute([$idAgent]);
$prochainsRdv = $prochainsRdvQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Mes Rendez-vous</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Mes Rendez-vous</li>
            </ol>
        </nav>
    </div>

    <!-- Dashboard Cards -->
    <section class="section dashboard">
        <div class="row">
            <!-- Statistiques des rendez-vous -->
            <div class="col-lg-8">
                <div class="row">
                    <div class="col-xxl-4 col-md-6">
                        <div class="card info-card blue-card">
                            <div class="card-body">
                                <h5 class="card-title">Planifiés</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $statistiques['planifie'] ?></h6>
                                        <span class="text-muted small pt-2 ps-1">rendez-vous</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4 col-md-6">
                        <div class="card info-card green-card">
                            <div class="card-body">
                                <h5 class="card-title">Confirmés</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $statistiques['confirme'] ?></h6>
                                        <span class="text-muted small pt-2 ps-1">rendez-vous</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4 col-md-6">
                        <div class="card info-card orange-card">
                            <div class="card-body">
                                <h5 class="card-title">Reportés</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $statistiques['reporte'] ?></h6>
                                        <span class="text-muted small pt-2 ps-1">rendez-vous</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rendez-vous du jour -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Aujourd'hui <span>| <?= date('d/m/Y') ?></span></h5>
                        <div class="activity">
                            <?php if (empty($rdvJour)): ?>
                                <div class="activity-item d-flex">
                                    <div class="activity-content">
                                        <span class="text-muted">Aucun rendez-vous aujourd'hui</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($rdvJour as $rdv): ?>
                                    <div class="activity-item d-flex">
                                        <div class="activite-label"><?= date('H:i', strtotime($rdv['heure_debut'])) ?></div>
                                        <i class="bi bi-circle-fill activity-badge text-<?= getStatusColor($rdv['statut_rendez_vous']) ?> align-self-start"></i>
                                        <div class="activity-content">
                                            <strong><?= htmlspecialchars($rdv['objet']) ?></strong><br>
                                            <small class="text-muted">
                                                <?= !empty($rdv['contact_externe']) ? htmlspecialchars($rdv['contact_externe']) : 'Contact interne' ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des rendez-vous -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Prochains Rendez-vous</h5>
                        
                        <!-- Filtres -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select id="filterStatut" class="form-select" onchange="filterRendezVous()">
                                    <option value="">Tous les statuts</option>
                                    <option value="planifie">Planifié</option>
                                    <option value="confirme">Confirmé</option>
                                    <option value="reporte">Reporté</option>
                                    <option value="annule">Annulé</option>
                                    <option value="termine">Terminé</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" id="filterDate" class="form-control" onchange="filterRendezVous()">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped" id="rendezVousTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Objet</th>
                                        <th>Contact</th>
                                        <th>Service</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prochainsRdv as $rdv): ?>
                                        <tr data-statut="<?= $rdv['statut_rendez_vous'] ?>" data-date="<?= $rdv['date_rendez_vous'] ?>">
                                            <td><?= date('d/m/Y', strtotime($rdv['date_rendez_vous'])) ?></td>
                                            <td><?= date('H:i', strtotime($rdv['heure_debut'])) ?> - <?= date('H:i', strtotime($rdv['heure_fin'])) ?></td>
                                            <td><?= htmlspecialchars($rdv['objet']) ?></td>
                                            <td>
                                                <?php if (!empty($rdv['contact_externe'])): ?>
                                                    <?= htmlspecialchars($rdv['contact_externe']) ?>
                                                    <?php if (!empty($rdv['telephone_externe'])): ?>
                                                        <br><small class="text-muted"><?= $rdv['telephone_externe'] ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em class="text-muted">Contact interne</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($rdv['nom_service']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getStatusColor($rdv['statut_rendez_vous']) ?>">
                                                    <?= ucfirst($rdv['statut_rendez_vous']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" onclick="voirDetails(<?= $rdv['idRendez_vous'] ?>)">
                                                            <i class="bi bi-eye"></i> Voir détails</a></li>
                                                        <?php if ($rdv['statut_rendez_vous'] == 'planifie'): ?>
                                                            <li><a class="dropdown-item"  onclick="changerStatut(<?= $rdv['idRendez_vous'] ?>, 'confirme')">
                                                                <i class="bi bi-check-circle"></i> Confirmer</a></li>
                                                            <li><a class="dropdown-item" onclick="changerStatut(<?= $rdv['idRendez_vous'] ?>, 'reporte')">
                                                                <i class="bi bi-clock-history"></i> Reporter</a></li>
                                                        <?php endif; ?>
                                                        <?php if (in_array($rdv['statut_rendez_vous'], ['planifie', 'confirme'])): ?>
                                                            <li><a class="dropdown-item" onclick="changerStatut(<?= $rdv['idRendez_vous'] ?>, 'termine')">
                                                                <i class="bi bi-check2-all"></i> Marquer terminé</a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" onclick="changerStatut(<?= $rdv['idRendez_vous'] ?>, 'annule')">
                                                            <i class="bi bi-x-circle"></i> Annuler</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal pour les détails du rendez-vous -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails du Rendez-vous</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Contenu chargé dynamiquement -->
                                     </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour reporter un rendez-vous -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reporter le Rendez-vous</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="reportForm">
                        <input type="hidden" id="reportRdvId" name="rdvId">
                        <div class="mb-3">
                            <label for="nouvelleDate" class="form-label">Nouvelle date</label>
                            <input type="date" class="form-control" id="nouvelleDate" name="nouvelleDate" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nouvelleHeureDebut" class="form-label">Heure début</label>
                                <input type="time" class="form-control" id="nouvelleHeureDebut" name="nouvelleHeureDebut" required>
                            </div>
                            <div class="col-md-6">
                                <label for="nouvelleHeureFin" class="form-label">Heure fin</label>
                                <input type="time" class="form-control" id="nouvelleHeureFin" name="nouvelleHeureFin" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="motifReport" class="form-label">Motif du report</label>
                            <textarea class="form-control" id="motifReport" name="motifReport" rows="3" placeholder="Expliquez la raison du report..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" onclick="confirmerReport()">Reporter</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour filtrer les rendez-vous
        function filterRendezVous() {
            const statutFilter = document.getElementById('filterStatut').value;
            const dateFilter = document.getElementById('filterDate').value;
            const rows = document.querySelectorAll('#rendezVousTable tbody tr');

            rows.forEach(row => {
                const statut = row.getAttribute('data-statut');
                const date = row.getAttribute('data-date');
                
                let showRow = true;
                
                if (statutFilter && statut !== statutFilter) {
                    showRow = false;
                }
                
                if (dateFilter && date !== dateFilter) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }

        // Fonction pour voir les détails d'un rendez-vous
        async function voirDetails(rdvId) {
            try {
                const response = await fetch(`controller/getRendezVousDetails.php?id=${rdvId}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('detailsContent').innerHTML = data.html;
                    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                    modal.show();
                } else {
                    Swal.fire('Erreur', 'Impossible de charger les détails', 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Erreur de communication avec le serveur', 'error');
            }
        }

        // Fonction pour changer le statut d'un rendez-vous
        function changerStatut(rdvId, nouveauStatut) {
            if (nouveauStatut === 'reporte') {
                // Ouvrir le modal de report
                document.getElementById('reportRdvId').value = rdvId;
                const modal = new bootstrap.Modal(document.getElementById('reportModal'));
                modal.show();
                return;
            }

            let message = '';
            let icon = 'question';
            
            switch (nouveauStatut) {
                case 'confirme':
                    message = 'Confirmer ce rendez-vous ?';
                    icon = 'question';
                    break;
                case 'termine':
                    message = 'Marquer ce rendez-vous comme terminé ?';
                    icon = 'success';
                    break;
                case 'annule':
                    message = 'Annuler ce rendez-vous ?';
                    icon = 'warning';
                    break;
            }

            Swal.fire({
                title: 'Confirmation',
                text: message,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non'
            }).then((result) => {
                if (result.isConfirmed) {
                    updateStatutRendezVous(rdvId, nouveauStatut);
                }
            });
        }

        // Fonction pour confirmer le report
        function confirmerReport() {
            const form = document.getElementById('reportForm');
            const formData = new FormData(form);
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            fetch('controller/reporterRendezVous.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Succès', 'Rendez-vous reporté avec succès', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erreur', data.message || 'Erreur lors du report', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Erreur de communication', 'error');
            });

            bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
        }

        // Fonction pour mettre à jour le statut
        async function updateStatutRendezVous(rdvId, statut) {
            try {
                const response = await fetch('controller/updateStatutRendezVous.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        rdvId: rdvId,
                        statut: statut
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    Swal.fire('Succès', 'Statut mis à jour avec succès', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erreur', data.message || 'Erreur lors de la mise à jour', 'error');
                }
            } catch (error) {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Erreur de communication avec le serveur', 'error');
            }
        }

        // Définir la date minimale pour le report
        document.getElementById('nouvelleDate').min = new Date().toISOString().split('T')[0];
        
        // Validation des heures
        document.getElementById('nouvelleHeureDebut').addEventListener('change', function() {
            const heureDebut = this.value;
            const heureFin = document.getElementById('nouvelleHeureFin').value;
            
            if (heureDebut && heureFin && heureDebut >= heureFin) {
                document.getElementById('nouvelleHeureFin').value = '';
                Swal.fire('Attention', 'L\'heure de fin doit être postérieure à l\'heure de début', 'warning');
            }
        });
    </script>
</main>

<?php
// Fonction pour obtenir la couleur du badge selon le statut
function getStatusColor($statut) {
    switch ($statut) {
        case 'planifie': return 'info';
        case 'confirme': return 'success';
        case 'reporte': return 'warning';
        case 'annule': return 'danger';
        case 'termine': return 'secondary';
        default: return 'light';
    }
}

include "./views/include/footer.php";
?>
