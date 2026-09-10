<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();

$stmt = $connexion->prepare("SELECT * FROM indicateur ORDER BY nom ASC");
$stmt->execute();
$indicateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $connexion->prepare("SELECT * FROM structure ORDER BY designation ASC");
$stmt->execute();
$structures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

$stmt = $connexion->prepare("
    SELECT v.*, i.nom AS \"nomIndicateur\", s.designation AS \"nomStructure\"
    FROM valeur_indicateur v
    JOIN indicateur i ON i.\"idIndicateur\" = v.\"idIndicateur\"
    JOIN structure s ON s.\"idStructure\" = v.\"idStructure\"
    ORDER BY v.\"dateEnregistrement\" DESC
    LIMIT 50
");
$stmt->execute();
$valeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Encoder un indicateur</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Indicateurs de Gestion</li>
                <li class="breadcrumb-item active">Encoder</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($indicateurs)): ?>
            <div class="alert alert-warning">
                Aucun indicateur n'existe encore. <a href="?view=indicateur/indicateur.add">Créez-en un</a> avant d'encoder une valeur.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Nouvelle valeur</h5>

                        <form action="controller/save_valeur_indicateur.php" method="POST">
                            <div class="mb-3">
                                <label for="idIndicateur" class="form-label">Indicateur <span class="text-danger">*</span></label>
                                <select class="form-select" id="idIndicateur" name="idIndicateur" required>
                                    <option value="">-- Choisir --</option>
                                    <?php foreach ($indicateurs as $indicateur): ?>
                                        <option value="<?= $indicateur['idIndicateur'] ?>"><?= htmlspecialchars($indicateur['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                                <select class="form-select" id="idStructure" name="idStructure" required>
                                    <option value="">-- Choisir --</option>
                                    <?php foreach ($structures as $structure): ?>
                                        <option value="<?= $structure['idStructure'] ?>"><?= htmlspecialchars($structure['designation']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="dateEnregistrement" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="dateEnregistrement" name="dateEnregistrement"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="valeur" class="form-label">Valeur <span class="text-danger">*</span></label>
                                <input type="number" step="any" class="form-control" id="valeur" name="valeur" required>
                            </div>

                            <div class="mb-3">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" id="observation" name="observation" rows="2"></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Dernières valeurs encodées</h5>

                        <?php if (empty($valeurs)): ?>
                            <div class="alert alert-info">Aucune valeur n'a encore été encodée.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Indicateur</th>
                                            <th>Structure</th>
                                            <th>Valeur</th>
                                            <th>Observation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($valeurs as $valeur): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($valeur['dateEnregistrement'])) ?></td>
                                                <td><?= htmlspecialchars($valeur['nomIndicateur']) ?></td>
                                                <td><?= htmlspecialchars($valeur['nomStructure']) ?></td>
                                                <td><?= htmlspecialchars((string) $valeur['valeur']) ?></td>
                                                <td><?= htmlspecialchars($valeur['observation'] ?? '') ?></td>
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
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new DataTable('.datatable', { responsive: true, pageLength: 10 });
});
</script>

<?php include "./views/include/footer.php"; ?>
