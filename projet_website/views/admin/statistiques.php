<?php
// Définir la page actuelle pour le menu actif
$currentPage = 'statistiques';

// Récupérer les données de la base
$db = Connexion::getInstance()->getPDO();

// Traitement des actions
if (isset($_POST['action'])) {
    try {
        // Action d'ajout d'une statistique
        if ($_POST['action'] === 'add_stat' && isset($_POST['stat_key'], $_POST['stat_value'])) {
            // Insérer la statistique dans la base de données
            $stmt = $db->prepare("INSERT INTO site_stats (stat_key, stat_value, stat_icon, description, 
                                is_featured, order_index) 
                                VALUES (:stat_key, :stat_value, :stat_icon, :description, 
                                :is_featured, :order_index)");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $statIcon = !empty($_POST['stat_icon']) ? $_POST['stat_icon'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':stat_key', $_POST['stat_key']);
            $stmt->bindParam(':stat_value', $_POST['stat_value']);
            $stmt->bindParam(':stat_icon', $statIcon);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La statistique a été ajoutée avec succès.";
        }
        
        // Action de mise à jour d'une statistique
        else if ($_POST['action'] === 'update_stat' && isset($_POST['stat_id'], $_POST['stat_key'], $_POST['stat_value'])) {
            // Mettre à jour la statistique
            $stmt = $db->prepare("UPDATE site_stats SET stat_key = :stat_key, stat_value = :stat_value, 
                                stat_icon = :stat_icon, description = :description, 
                                is_featured = :is_featured, order_index = :order_index 
                                WHERE id = :id");
            
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $description = !empty($_POST['description']) ? $_POST['description'] : null;
            $statIcon = !empty($_POST['stat_icon']) ? $_POST['stat_icon'] : null;
            $orderIndex = !empty($_POST['order_index']) ? intval($_POST['order_index']) : 0;
            
            $stmt->bindParam(':id', $_POST['stat_id']);
            $stmt->bindParam(':stat_key', $_POST['stat_key']);
            $stmt->bindParam(':stat_value', $_POST['stat_value']);
            $stmt->bindParam(':stat_icon', $statIcon);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':is_featured', $isFeatured);
            $stmt->bindParam(':order_index', $orderIndex);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La statistique a été mise à jour avec succès.";
        }
        
        // Action de suppression d'une statistique
        else if ($_POST['action'] === 'delete_stat' && isset($_POST['stat_id'])) {
            // Supprimer la statistique
            $stmt = $db->prepare("DELETE FROM site_stats WHERE id = :id");
            $stmt->bindParam(':id', $_POST['stat_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "La statistique a été supprimée avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur: " . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: statistiques');
    exit;
}

// Récupérer toutes les statistiques
$statsStmt = $db->query("SELECT * FROM site_stats ORDER BY order_index ASC, stat_key ASC");
$statsList = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données de fréquentation (exemple fictif)
// Récupérer les données de fréquentation réelles
$visitorsData = [
    'total' => compter_visiteurs_total(),
    'today' => compter_visiteurs_jour(),
    'pages_viewed' => compter_pages_vues(),
    'new_users' => compter_nouveaux_utilisateurs()
];

// Récupérer les données pour le graphique
$chartData = obtenir_donnees_graphique_visites(30);


// Inclure le header
include_once './views/admin/include/header.php';
?>

<!-- Contenu de la page de gestion des statistiques -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-chart-line me-2"></i>Gestion des statistiques</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStatModal">
        <i class="fas fa-plus me-2"></i>Ajouter une statistique
    </button>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <p class="text-muted">
                    Gérez les statistiques mises en avant sur votre site, comme le nombre d'étudiants, d'enseignants, de laboratoires, etc.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Aperçu des statistiques de fréquentation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Statistiques de fréquentation</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Visiteurs totaux</h6>
                                        <h3 class="mb-0"><?php echo number_format($visitorsData['total']); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-users fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Visiteurs aujourd'hui</h6>
                                        <h3 class="mb-0"><?php echo number_format($visitorsData['today']); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-clock fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-white-50">Pages vues</h6>
                                        <h3 class="mb-0"><?php echo number_format($visitorsData['pages_viewed']); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-file-alt fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-dark-50">Nouveaux utilisateurs</h6>
                                        <h3 class="mb-0"><?php echo number_format($visitorsData['new_users']); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-plus fa-2x opacity-50"></i>
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

<!-- Tableau des statistiques -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des statistiques</h5>
    </div>
    <div class="card-body">
        <?php if (empty($statsList)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Aucune statistique n'a été ajoutée.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Icône</th>
                            <th>Clé</th>
                            <th>Valeur</th>
                            <th>Description</th>
                            <th>Ordre</th>
                            <th>Mise en avant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statsList as $stat): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($stat['stat_icon'])): ?>
                                        <i class="<?php echo htmlspecialchars($stat['stat_icon']); ?> fa-2x"></i>
                                    <?php else: ?>
                                        <div class="text-muted"><i class="fas fa-chart-bar fa-2x"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($stat['stat_key']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($stat['stat_value']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($stat['description'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo $stat['order_index']; ?>
                                </td>
                                <td>
                                    <?php if ($stat['is_featured']): ?>
                                        <span class="badge bg-success">Mise en avant</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary edit-stat-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editStatModal"
                                                data-id="<?php echo $stat['id']; ?>"
                                                data-key="<?php echo htmlspecialchars($stat['stat_key']); ?>"
                                                data-value="<?php echo htmlspecialchars($stat['stat_value']); ?>"
                                                data-icon="<?php echo htmlspecialchars($stat['stat_icon'] ?? ''); ?>"
                                                data-description="<?php echo htmlspecialchars($stat['description'] ?? ''); ?>"
                                                data-order-index="<?php echo $stat['order_index']; ?>"
                                                data-is-featured="<?php echo $stat['is_featured']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger delete-stat-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteStatModal"
                                                data-id="<?php echo $stat['id']; ?>"
                                                data-key="<?php echo htmlspecialchars($stat['stat_key']); ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter une statistique -->
<div class="modal fade" id="addStatModal" tabindex="-1" aria-labelledby="addStatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStatModalLabel">Ajouter une nouvelle statistique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="stat-key" class="form-label">Clé de la statistique</label>
                        <input type="text" class="form-control" id="stat-key" name="stat_key" required>
                        <div class="form-text">
                            Identifiant unique pour cette statistique (ex: "students", "teachers", "labs").
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="stat-value" class="form-label">Valeur</label>
                        <input type="text" class="form-control" id="stat-value" name="stat_value" required>
                        <div class="form-text">
                            La valeur à afficher (ex: "2500+", "80", "12").
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="stat-icon" class="form-label">Icône</label>
                        <input type="text" class="form-control" id="stat-icon" name="stat_icon" placeholder="fas fa-users">
                        <div class="form-text">
                            Classe d'icône FontAwesome (ex: "fas fa-user-graduate", "fas fa-chalkboard-teacher").
                            <a href="https://fontawesome.com/icons" target="_blank">Voir les icônes disponibles</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="stat-description" class="form-label">Description</label>
                        <textarea class="form-control" id="stat-description" name="description" rows="2"></textarea>
                        <div class="form-text">
                            Description explicative de cette statistique.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="stat-order" class="form-label">Ordre d'affichage</label>
                        <input type="number" class="form-control" id="stat-order" name="order_index" value="0" min="0">
                        <div class="form-text">
                            Les statistiques avec un ordre plus bas s'afficheront en premier.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="stat-featured" name="is_featured">
                        <label class="form-check-label" for="stat-featured">
                            Mettre en avant sur la page d'accueil
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="add_stat">Ajouter la statistique</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier une statistique -->
<div class="modal fade" id="editStatModal" tabindex="-1" aria-labelledby="editStatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStatModalLabel">Modifier la statistique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit-stat-id" name="stat_id">
                    <div class="mb-3">
                        <label for="edit-stat-key" class="form-label">Clé de la statistique</label>
                        <input type="text" class="form-control" id="edit-stat-key" name="stat_key" required>
                        <div class="form-text">
                            Identifiant unique pour cette statistique.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-stat-value" class="form-label">Valeur</label>
                        <input type="text" class="form-control" id="edit-stat-value" name="stat_value" required>
                        <div class="form-text">
                            La valeur à afficher.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-stat-icon" class="form-label">Icône</label>
                        <input type="text" class="form-control" id="edit-stat-icon" name="stat_icon" placeholder="fas fa-users">
                        <div class="form-text">
                            Classe d'icône FontAwesome.
                            <a href="https://fontawesome.com/icons" target="_blank">Voir les icônes disponibles</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-stat-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-stat-description" name="description" rows="2"></textarea>
                        <div class="form-text">
                            Description explicative de cette statistique.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-stat-order" class="form-label">Ordre d'affichage</label>
                        <input type="number" class="form-control" id="edit-stat-order" name="order_index" value="0" min="0">
                        <div class="form-text">
                            Les statistiques avec un ordre plus bas s'afficheront en premier.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit-stat-featured" name="is_featured">
                        <label class="form-check-label" for="edit-stat-featured">
                            Mettre en avant sur la page d'accueil
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" name="action" value="update_stat">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Supprimer une statistique -->
<div class="modal fade" id="deleteStatModal" tabindex="-1" aria-labelledby="deleteStatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteStatModalLabel">Supprimer une statistique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete-stat-id" name="stat_id">
                    <p>Êtes-vous sûr de vouloir supprimer la statistique <strong id="delete-stat-key"></strong> ?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span>Cette action est irréversible et supprimera définitivement cette statistique.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger" name="action" value="delete_stat">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Graphique des visites (exemple) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Évolution des visites</h5>
            </div>
            <div class="card-body">
                <canvas id="visitsChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Script pour gérer les formulaires et interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal d'édition
    const editStatModal = document.getElementById('editStatModal');
    if (editStatModal) {
        editStatModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            // Remplir le formulaire avec les données de la statistique
            document.getElementById('edit-stat-id').value = button.getAttribute('data-id');
            document.getElementById('edit-stat-key').value = button.getAttribute('data-key');
            document.getElementById('edit-stat-value').value = button.getAttribute('data-value');
            document.getElementById('edit-stat-icon').value = button.getAttribute('data-icon');
            document.getElementById('edit-stat-description').value = button.getAttribute('data-description');
            document.getElementById('edit-stat-order').value = button.getAttribute('data-order-index');
            
            // Mettre à jour la checkbox
            document.getElementById('edit-stat-featured').checked = button.getAttribute('data-is-featured') === '1';
        });
    }
    
    // Gestion du modal de suppression
    const deleteStatModal = document.getElementById('deleteStatModal');
    if (deleteStatModal) {
        deleteStatModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            
            const statId = button.getAttribute('data-id');
            const statKey = button.getAttribute('data-key');
            
            const idField = this.querySelector('#delete-stat-id');
            const keySpan = this.querySelector('#delete-stat-key');
            
            if (idField) idField.value = statId;
            if (keySpan) keySpan.textContent = statKey;
        });
    }
    
    // Création du graphique d'exemple
    // Création du graphique avec des données réelles
const ctx = document.getElementById('visitsChart').getContext('2d');
const chartData = <?php echo json_encode($chartData); ?>;
const visitsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.labels,
        datasets: [{
            label: 'Visiteurs uniques',
            data: chartData.uniqueVisitors,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            tension: 0.3
        }, {
            label: 'Pages vues',
            data: chartData.pageViews,
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Statistiques de visites des 30 derniers jours'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

});
</script>

<?php
// Inclure le footer
include_once 'views/admin/include/footer.php';
?>
