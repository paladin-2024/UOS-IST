<?php
include "./views/include/header.php";
$_GET['action'] = 'nettoyer';
require_once "controller/journal_serveur.php";
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Nettoyer le Journal</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=configuration/journal_serveur">Journal Serveur</a></li>
                <li class="breadcrumb-item active">Nettoyage</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-trash"></i> Supprimer les Logs Anciens
                        </h5>

                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= $_SESSION['message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['message']); ?>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Attention:</strong> Cette action supprimera définitivement les logs sélectionnés. 
                            Cette action ne peut pas être annulée.
                        </div>

                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ces logs ?');">
                            <div class="mb-3">
                                <label for="jours" class="form-label">Supprimer les logs de plus de:</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="jours" name="jours" value="90" min="1" max="3650" required>
                                    <span class="input-group-text">jours</span>
                                </div>
                                <small class="form-text text-muted">
                                    Les logs plus anciens que la date spécifiée seront supprimés.
                                </small>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                Par exemple, si vous spécifiez 90 jours, tous les logs créés il y a plus de 90 jours seront supprimés.
                            </div>

                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Supprimer les Logs
                            </button>
                            <a href="?view=configuration/journal_serveur" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Annuler
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations</h5>
                        <p>
                            Le nettoyage du journal permet de supprimer les entrées anciennes pour maintenir 
                            la base de données performante.
                        </p>
                        <p>
                            <strong>Recommandations:</strong>
                        </p>
                        <ul>
                            <li>Exporter les logs avant de les supprimer</li>
                            <li>Nettoyer tous les 3-6 mois</li>
                            <li>Conserver les logs importants</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include "./views/include/footer.php"; ?>
