<?php
include "./views/include/header.php";
$_GET['action'] = 'details';
require_once "controller/journal_serveur.php";
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails du Log</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="configuration/journal_serveur">Journal Serveur</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle"></i> Information du Log
                        </h5>

                        <?php if ($log): ?>
                            <table class="table table-borderless">
                                <tr>
                                    <th>ID:</th>
                                    <td><?= htmlspecialchars($log['id_log']) ?></td>
                                </tr>
                                <tr>
                                    <th>Date/Heure:</th>
                                    <td><?= date('d/m/Y H:i:s', strtotime($log['date_creation'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Utilisateur:</th>
                                    <td><?= htmlspecialchars($log['nom_utilisateur'] ?? 'Système') ?></td>
                                </tr>
                                <tr>
                                    <th>ID Utilisateur:</th>
                                    <td><?= htmlspecialchars($log['id_utilisateur'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Type d'Action:</th>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($log['type_action']) ?></span></td>
                                </tr>
                                <tr>
                                    <th>Module:</th>
                                    <td><?= htmlspecialchars($log['module'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Statut:</th>
                                    <td>
                                        <?php 
                                        $badgeClass = $log['statut'] === 'succes' ? 'bg-success' : 
                                                      ($log['statut'] === 'erreur' ? 'bg-danger' : 'bg-warning');
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($log['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Adresse IP:</th>
                                    <td><?= htmlspecialchars($log['adresse_ip']) ?></td>
                                </tr>
                                <tr>
                                    <th>User Agent:</th>
                                    <td><small><?= htmlspecialchars($log['user_agent'] ?? '-') ?></small></td>
                                </tr>
                                <tr>
                                    <th>Table Affectée:</th>
                                    <td><?= htmlspecialchars($log['table_affectee'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>ID Enregistrement:</th>
                                    <td><?= htmlspecialchars($log['id_enregistrement'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                </tr>
                                <?php if ($log['message_erreur']): ?>
                                    <tr>
                                        <th>Message d'Erreur:</th>
                                        <td><small class="text-danger"><?= htmlspecialchars($log['message_erreur']) ?></small></td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                            <?php if ($log['donnees_avant']): ?>
                                <hr>
                                <h6>Données Avant:</h6>
                                <pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"><?= htmlspecialchars($log['donnees_avant']) ?></pre>
                            <?php endif; ?>

                            <?php if ($log['donnees_apres']): ?>
                                <hr>
                                <h6>Données Après:</h6>
                                <pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"><?= htmlspecialchars($log['donnees_apres']) ?></pre>
                            <?php endif; ?>

                            <hr>
                            <a href="configuration/journal_serveur" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle"></i> Log non trouvé
                            </div>
                            <a href="configuration/journal_serveur" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include "./views/include/footer.php"; ?>
