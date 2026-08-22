<?php
include "./views/include/header.php";

$journal = new JournalServeur();

// Statistiques globales
$_GET['action'] = 'statistiques';
require_once "controller/journal_serveur.php";

// Statistiques du jour
$today = date('Y-m-d');
$statistiques_jour = $journal->obtenirLogs([
    'date_debut' => $today,
    'date_fin' => $today
], 1, 10000);

$logs_jour = $statistiques_jour['logs'] ?? [];
$total_jour = count($logs_jour);
$succes_jour = 0;
$erreurs_jour = 0;
$avertissements_jour = 0;
$utilisateurs_jour = [];
$modules_jour = [];
$types_jour = [];

foreach ($logs_jour as $log) {
    if ($log['statut'] === 'succes') $succes_jour++;
    elseif ($log['statut'] === 'erreur') $erreurs_jour++;
    elseif ($log['statut'] === 'avertissement') $avertissements_jour++;
    
    $utilisateurs_jour[$log['nom_utilisateur'] ?? 'Système'] = 
        ($utilisateurs_jour[$log['nom_utilisateur'] ?? 'Système'] ?? 0) + 1;
    
    $modules_jour[$log['module'] ?? '-'] = 
        ($modules_jour[$log['module'] ?? '-'] ?? 0) + 1;
    
    $types_jour[$log['type_action']] = 
        ($types_jour[$log['type_action']] ?? 0) + 1;
}

arsort($utilisateurs_jour);
arsort($modules_jour);
arsort($types_jour);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Statistiques du Journal</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="configuration/journal_serveur">Journal Serveur</a></li>
                <li class="breadcrumb-item active">Statistiques</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
         <div class="row">
             <!-- STATISTIQUES DU JOUR -->
             <div class="col-12 mb-4">
                 <h6 class="text-muted"><i class="bi bi-calendar-today"></i> Statistiques du Jour (<?= date('d/m/Y') ?>)</h6>
             </div>

             <div class="col-lg-3 col-md-6">
                 <div class="card info-card sales-card">
                     <div class="card-body">
                         <h5 class="card-title">Total (Jour)</h5>
                         <div class="d-flex align-items-center">
                             <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                 <i class="bi bi-file-text"></i>
                             </div>
                             <div class="ps-3">
                                 <h6><?= $total_jour ?></h6>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <div class="col-lg-3 col-md-6">
                 <div class="card info-card revenue-card">
                     <div class="card-body">
                         <h5 class="card-title">Succès (Jour)</h5>
                         <div class="d-flex align-items-center">
                             <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                 <i class="bi bi-check-circle"></i>
                             </div>
                             <div class="ps-3">
                                 <h6><?= $succes_jour ?></h6>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <div class="col-lg-3 col-md-6">
                 <div class="card info-card customers-card">
                     <div class="card-body">
                         <h5 class="card-title">Erreurs (Jour)</h5>
                         <div class="d-flex align-items-center">
                             <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                 <i class="bi bi-x-circle"></i>
                             </div>
                             <div class="ps-3">
                                 <h6><?= $erreurs_jour ?></h6>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <div class="col-lg-3 col-md-6">
                 <div class="card info-card customers-card">
                     <div class="card-body">
                         <h5 class="card-title">Avertissements (Jour)</h5>
                         <div class="d-flex align-items-center">
                             <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                 <i class="bi bi-exclamation-circle"></i>
                             </div>
                             <div class="ps-3">
                                 <h6><?= $avertissements_jour ?></h6>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <!-- DÉTAILS DU JOUR -->
             <?php if ($total_jour > 0): ?>
                 <div class="col-lg-6 mt-3">
                     <div class="card">
                         <div class="card-body">
                             <h5 class="card-title">Modules du Jour</h5>
                             <table class="table table-sm table-borderless">
                                 <tbody>
                                     <?php foreach (array_slice($modules_jour, 0, 5) as $module => $count): ?>
                                         <tr>
                                             <td><strong><?= htmlspecialchars($module) ?></strong></td>
                                             <td class="text-end"><?= $count ?></td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         </div>
                     </div>
                 </div>

                 <div class="col-lg-6 mt-3">
                     <div class="card">
                         <div class="card-body">
                             <h5 class="card-title">Utilisateurs du Jour</h5>
                             <table class="table table-sm table-borderless">
                                 <tbody>
                                     <?php foreach (array_slice($utilisateurs_jour, 0, 5) as $user => $count): ?>
                                         <tr>
                                             <td><strong><?= htmlspecialchars($user) ?></strong></td>
                                             <td class="text-end"><?= $count ?></td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         </div>
                     </div>
                 </div>

                 <div class="col-lg-12 mt-3">
                     <div class="card">
                         <div class="card-body">
                             <h5 class="card-title">Types d'Actions du Jour</h5>
                             <table class="table table-sm table-striped">
                                 <tbody>
                                     <?php foreach ($types_jour as $type => $count): ?>
                                         <tr>
                                             <td><strong><?= htmlspecialchars($type) ?></strong></td>
                                             <td class="text-end"><?= $count ?></td>
                                             <td class="text-end text-muted">
                                                 <?php echo number_format(($count / $total_jour) * 100, 1) . '%'; ?>
                                             </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         </div>
                     </div>
                 </div>
             <?php else: ?>
                 <div class="col-12 mt-3">
                     <div class="alert alert-info">
                         <i class="bi bi-info-circle"></i> Aucune activité enregistrée pour aujourd'hui
                     </div>
                 </div>
             <?php endif; ?>

             <!-- SÉPARATEUR -->
             <div class="col-12 mt-4 mb-3">
                 <hr>
                 <h6 class="text-muted"><i class="bi bi-graph-up"></i> Statistiques Globales</h6>
             </div>

             <?php if ($statistiques): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card info-card sales-card">
                        <div class="card-body">
                            <h5 class="card-title">Total Logs</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-file-text"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $statistiques['total_logs'] ?? 0 ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card info-card revenue-card">
                        <div class="card-body">
                            <h5 class="card-title">Succès</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $statistiques['succes'] ?? 0 ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card info-card customers-card">
                        <div class="card-body">
                            <h5 class="card-title">Erreurs</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $statistiques['erreurs'] ?? 0 ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card info-card customers-card">
                        <div class="card-body">
                            <h5 class="card-title">Avertissements</h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-exclamation-circle"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $statistiques['avertissements'] ?? 0 ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Résumé</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th>Utilisateurs uniques:</th>
                                    <td><?= $statistiques['utilisateurs_uniques'] ?? 0 ?></td>
                                </tr>
                                <tr>
                                    <th>Modules utilisés:</th>
                                    <td><?= $statistiques['modules_utilises'] ?? 0 ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if (!empty($par_type)): ?>
                    <div class="col-lg-12 mt-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Actions par Type</h5>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Nombre</th>
                                            <th>Pourcentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($par_type as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['type_action']) ?></td>
                                                <td><?= $item['nombre'] ?></td>
                                                <td>
                                                    <?php 
                                                    $pct = ($item['nombre'] / $statistiques['total_logs']) * 100;
                                                    echo number_format($pct, 2) . '%';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($utilisateurs_actifs)): ?>
                    <div class="col-lg-12 mt-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Top 10 Utilisateurs</h5>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Utilisateur</th>
                                            <th>Nombre d'Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($utilisateurs_actifs as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['nom_utilisateur']) ?></td>
                                                <td><?= $user['nombre_actions'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="col-lg-12">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle"></i> Aucune donnée de statistiques disponible
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-lg-12 mt-3">
                <a href="configuration/journal_serveur" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </section>

</main>

<?php include "./views/include/footer.php"; ?>
