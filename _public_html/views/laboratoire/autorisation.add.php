<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'ID du laboratoire
$idLabo = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idLabo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Récupérer les informations du laboratoire
$queryLabo = "SELECT * FROM laboratoire WHERE idlabo = :idLabo AND annee_acad_id = :anneeId";
$stmtLabo = $db->prepare($queryLabo);
$stmtLabo->bindParam(':idLabo', $idLabo);
$stmtLabo->bindParam(':anneeId', $anneeId);
$stmtLabo->execute();
$labo = $stmtLabo->fetch(PDO::FETCH_ASSOC);

if (!$labo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer les utilisateurs actuels du laboratoire
$queryUsers = "SELECT al.*, a.noms, a.matricule
               FROM autorisation_labo al
               JOIN agent a ON al.idAgent = a.idAgent
               WHERE al.idlabo = :idLabo
               ORDER BY al.date_creation DESC";
$stmtUsers = $db->prepare($queryUsers);
$stmtUsers->bindParam(':idLabo', $idLabo);
$stmtUsers->execute();
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des agents pour l'ajout
$queryAgents = "SELECT a.idAgent, a.noms, a.matricule 
                FROM agent a 
                WHERE a.idAgent NOT IN (
                    SELECT al.idAgent FROM autorisation_labo al WHERE al.idlabo = :idLabo
                )
                ORDER BY a.noms";
$stmtAgents = $db->prepare($queryAgents);
$stmtAgents->bindParam(':idLabo', $idLabo);
$stmtAgents->execute();
$agents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des utilisateurs du laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item active">Autorisations</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Laboratoire: <?= htmlspecialchars($labo['nom']) ?></h5>
                        
                        <!-- Formulaire d'ajout d'utilisateur -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Ajouter un utilisateur</h5>
                                <form action="controller/add_autorisation_labo.php" method="POST" class="row g-3">
                                    <input type="hidden" name="idlabo" value="<?= $idLabo ?>">
                                    
                                    <div class="col-md-6">
                                        <label for="idAgent" class="form-label">Agent</label>
                                        <select class="form-select" id="idAgent" name="idAgent" required>
                                            <option value="">Sélectionner un agent</option>
                                            <?php foreach ($agents as $agent): ?>
                                                <option value="<?= $agent['idAgent'] ?>">
                                                    <?= htmlspecialchars($agent['noms']) ?> 
                                                    <?= $agent['matricule'] ? '(' . htmlspecialchars($agent['matricule']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="niveau_autorisation" class="form-label">Niveau d'autorisation</label>
                                        <select class="form-select" id="niveau_autorisation" name="niveau_autorisation" required>
                                            <option value="Utilisateur">Utilisateur</option>
                                            <option value="Admin">Administrateur</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="date_debut" class="form-label">Date de début</label>
                                        <input type="date" class="form-control" id="date_debut" name="date_debut" required 
                                            value="<?= date('Y-m-d') ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="date_fin" class="form-label">Date de fin (optionnel)</label>
                                        <input type="date" class="form-control" id="date_fin" name="date_fin">
                                                                        </div>
                                    
                                    <div class="col-12">
                                        <label for="commentaire" class="form-label">Commentaire</label>
                                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-person-plus"></i> Ajouter
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Liste des utilisateurs actuels -->
                        <h5 class="card-title">Utilisateurs actuels</h5>
                        <div class="table-responsive">
                            <table class="table table-striped datatable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Agent</th>
                                        <th>Niveau d'autorisation</th>
                                        <th>Date de début</th>
                                        <th>Date de fin</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($users as $user): 
                                        $now = new DateTime();
                                        $dateDebut = new DateTime($user['date_debut']);
                                        $dateFin = $user['date_fin'] ? new DateTime($user['date_fin']) : null;
                                        
                                        $actif = true;
                                        if ($user['est_active'] == 0) {
                                            $actif = false;
                                        } elseif ($dateDebut > $now) {
                                            $actif = false;
                                        } elseif ($dateFin && $dateFin < $now) {
                                            $actif = false;
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <?= htmlspecialchars($user['noms']) ?>
                                            <?php if ($user['matricule']): ?>
                                                <div class="small text-muted"><?= htmlspecialchars($user['matricule']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['niveau_autorisation'] == 'Admin'): ?>
                                                <span class="badge bg-primary">Administrateur</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Utilisateur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $dateDebut->format('d/m/Y') ?></td>
                                        <td><?= $dateFin ? $dateFin->format('d/m/Y') : '-' ?></td>
                                        <td>
                                            <?php if ($actif): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editAutorisation(<?= $user['idautorisation'] ?>, 
                                                                                '<?= $user['niveau_autorisation'] ?>', 
                                                                                '<?= $user['date_debut'] ?>', 
                                                                                '<?= $user['date_fin'] ?? '' ?>', 
                                                                                '<?= addslashes($user['commentaire'] ?? '') ?>', 
                                                                                <?= $user['est_active'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($actif): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="desactiverAutorisation(<?= $user['idautorisation'] ?>)">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="activerAutorisation(<?= $user['idautorisation'] ?>)">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
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
</main>

<!-- Modal d'édition -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" action="controller/update_autorisation_labo.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Modifier l'autorisation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_idautorisation" name="idautorisation">
                    
                    <div class="mb-3">
                        <label for="edit_niveau_autorisation" class="form-label">Niveau d'autorisation</label>
                        <select class="form-select" id="edit_niveau_autorisation" name="niveau_autorisation" required>
                            <option value="Utilisateur">Utilisateur</option>
                            <option value="Admin">Administrateur</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date_debut" class="form-label">Date de début</label>
                        <input type="date" class="form-control" id="edit_date_debut" name="date_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date_fin" class="form-label">Date de fin (optionnel)</label>
                        <input type="date" class="form-control" id="edit_date_fin" name="date_fin">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="edit_commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_est_active" name="est_active" value="1">
                        <label class="form-check-label" for="edit_est_active">Autorisation active</label>
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
    $(document).ready(function() {
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            }
        });
        
        // Améliorer le select avec select2
        $('#idAgent').select2({
            placeholder: "Sélectionner un agent",
            width: '100%'
        });
    });
    
    // Fonctions pour gérer les autorisations
    function editAutorisation(id, niveau, dateDebut, dateFin, commentaire, estActive) {
        $('#edit_idautorisation').val(id);
        $('#edit_niveau_autorisation').val(niveau);
        $('#edit_date_debut').val(dateDebut);
        $('#edit_date_fin').val(dateFin);
        $('#edit_commentaire').val(commentaire);
        document.getElementById('edit_est_active').checked = estActive == 1;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    
    function desactiverAutorisation(id) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir désactiver cette autorisation ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, désactiver',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/toggle_autorisation_labo.php?id=${id}&action=desactiver`;
            }
        });
    }
    
    function activerAutorisation(id) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir activer cette autorisation ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, activer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/toggle_autorisation_labo.php?id=${id}&action=activer`;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
