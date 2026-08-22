<?php
/**
 * Vue: Liste des logs du journal serveur
 */
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Journal Serveur</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Configuration</a></li>
                <li class="breadcrumb-item active">Journal Serveur</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table logs -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-clock-history"></i> Suivi des Activités
                                </h5>

                                <?php if (isset($_SESSION['message_succes'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i> <?= $_SESSION['message_succes'] ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['message_succes']); ?>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Affichage de tous les logs du système. Utilisez les filtres pour affiner votre recherche.
                                </div>

                                <!-- Formulaire de filtrage -->
                                <form id="filtersForm" method="GET" action="" class="mb-3">
                                    <input type="hidden" name="view" value="journal_serveur">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <select name="type_action" class="form-control">
                                                <option value="">-- Tous --</option>
                                                <option value="CREATE" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                                                <option value="UPDATE" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                                                <option value="DELETE" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                                                <option value="LOGIN" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
                                                <option value="LOGOUT" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT</option>
                                                <option value="EXPORT" <?= isset($_GET['type_action']) && $_GET['type_action'] === 'EXPORT' ? 'selected' : '' ?>>EXPORT</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Module</label>
                                            <input type="text" name="module" class="form-control" 
                                                   value="<?= isset($_GET['module']) ? htmlspecialchars($_GET['module']) : '' ?>" 
                                                   placeholder="Ex: Etudiants">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Statut</label>
                                            <select name="statut" class="form-control">
                                                <option value="">-- Tous --</option>
                                                <option value="succes" <?= isset($_GET['statut']) && $_GET['statut'] === 'succes' ? 'selected' : '' ?>>Succès</option>
                                                <option value="erreur" <?= isset($_GET['statut']) && $_GET['statut'] === 'erreur' ? 'selected' : '' ?>>Erreur</option>
                                                <option value="avertissement" <?= isset($_GET['statut']) && $_GET['statut'] === 'avertissement' ? 'selected' : '' ?>>Avertissement</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Du</label>
                                            <input type="date" name="date_debut" class="form-control" 
                                                   value="<?= isset($_GET['date_debut']) ? htmlspecialchars($_GET['date_debut']) : '' ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Au</label>
                                            <input type="date" name="date_fin" class="form-control" 
                                                   value="<?= isset($_GET['date_fin']) ? htmlspecialchars($_GET['date_fin']) : '' ?>">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Recherche</label>
                                            <div class="input-group">
                                                <input type="text" name="recherche" class="form-control" 
                                                       value="<?= isset($_GET['recherche']) ? htmlspecialchars($_GET['recherche']) : '' ?>" 
                                                       placeholder="Utilisateur...">
                                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <!-- Boutons d'action -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <a href="configuration/journal_serveur&action=statistiques" class="btn btn-info btn-sm">
                                            <i class="bi bi-bar-chart"></i> Statistiques
                                        </a>
                                        <a href="configuration/journal_serveur&action=export" class="btn btn-success btn-sm">
                                            <i class="bi bi-download"></i> Exporter CSV
                                        </a>
                                        <a href="configuration/journal_serveur&action=nettoyer" class="btn btn-warning btn-sm">
                                            <i class="bi bi-trash"></i> Nettoyer
                                        </a>
                                    </div>
                                </div>

                                <!-- Tableau des logs -->
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date/Heure</th>
                                            <th>Utilisateur</th>
                                            <th>Type</th>
                                            <th>Module</th>
                                            <th>Description</th>
                                            <th>Statut</th>
                                            <th>IP</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($resultats['logs'])): ?>
                                            <?php $i = 1; foreach ($resultats['logs'] as $log): ?>
                                                <tr>
                                                    <td><?= $i ?></td>
                                                    <td><small><?= date('d/m/Y H:i:s', strtotime($log['date_creation'])) ?></small></td>
                                                    <td><strong><?= htmlspecialchars($log['nom_utilisateur'] ?? 'Système') ?></strong></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($log['type_action']) ?></span></td>
                                                    <td><?= htmlspecialchars($log['module'] ?? '-') ?></td>
                                                    <td><small><?= htmlspecialchars(substr($log['description'], 0, 80)) ?></small></td>
                                                    <td>
                                                        <?php 
                                                        $badgeClass = $log['statut'] === 'succes' ? 'bg-success' : 
                                                                      ($log['statut'] === 'erreur' ? 'bg-danger' : 'bg-warning');
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>">
                                                            <?= ucfirst($log['statut']) ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= htmlspecialchars($log['adresse_ip']) ?></small></td>
                                                    <td>
                                                        <a href="?view=journal_serveur&action=details&id=<?= $log['id_log'] ?>" 
                                                           class="btn btn-sm btn-info" title="Détails">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php $i++; endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="bi bi-inbox"></i> Aucun log trouvé
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <!-- Infos de pagination -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Total: <strong><?= $resultats['total'] ?? 0 ?></strong> enregistrements
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            Affichage: <strong><?= (($page - 1) * $par_page) + 1 ?></strong> - 
                                            <strong><?= min($page * $par_page, $resultats['total'] ?? 0) ?></strong>
                                        </small>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <?php if (isset($resultats['pages']) && $resultats['pages'] > 1): ?>
                                    <nav aria-label="Pagination">
                                        <ul class="pagination justify-content-center">
                                            <?php 
                                            $queryString = http_build_query(array_merge($_GET, ['view' => 'journal_serveur']));
                                            $baseUrl = "?" . $queryString . "&page=";
                                            ?>
                                            
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl ?>1">
                                                        <i class="bi bi-chevron-left"></i> Première
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . ($page - 1) ?>">Précédente</a>
                                                </li>
                                            <?php endif; ?>

                                            <?php 
                                            $debut = max(1, $page - 2);
                                            $fin = min($resultats['pages'], $page + 2);
                                            
                                            for ($i = $debut; $i <= $fin; $i++):
                                            ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $baseUrl . $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $resultats['pages']): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . ($page + 1) ?>">Suivante</a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $baseUrl . $resultats['pages'] ?>">
                                                        Dernière <i class="bi bi-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->
