<?php
include "./views/include/header.php";

$connexion = Connexion::getInstance()->getPDO();

$stmt = $connexion->prepare("SELECT * FROM indicateur ORDER BY nom ASC");
$stmt->execute();
$indicateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$idIndicateur = isset($_GET['idIndicateur']) && is_numeric($_GET['idIndicateur']) ? intval($_GET['idIndicateur']) : null;
if ($idIndicateur === null && !empty($indicateurs)) {
    $idIndicateur = intval($indicateurs[0]['idIndicateur']);
}

$valeurs = [];
$indicateurSelectionne = null;
if ($idIndicateur) {
    foreach ($indicateurs as $ind) {
        if ((int) $ind['idIndicateur'] === $idIndicateur) {
            $indicateurSelectionne = $ind;
            break;
        }
    }

    $stmt = $connexion->prepare("
        SELECT v.*, s.designation AS \"nomStructure\"
        FROM valeur_indicateur v
        JOIN structure s ON s.\"idStructure\" = v.\"idStructure\"
        WHERE v.\"idIndicateur\" = :id
        ORDER BY v.\"dateEnregistrement\" ASC
    ");
    $stmt->bindParam(':id', $idIndicateur);
    $stmt->execute();
    $valeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$chartLabels = array_map(function ($v) {
    return date('d/m/Y', strtotime($v['dateEnregistrement']));
}, $valeurs);
$chartValues = array_map(function ($v) {
    return (float) $v['valeur'];
}, $valeurs);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Rapport indicateurs</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Indicateurs de Gestion</li>
                <li class="breadcrumb-item active">Rapport</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (empty($indicateurs)): ?>
            <div class="alert alert-warning">
                Aucun indicateur n'existe encore. <a href="?view=indicateur/indicateur.add">Créez-en un</a> pour voir un rapport.
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="index.php" class="row g-2 align-items-end">
                        <input type="hidden" name="view" value="indicateur/rapport.indicateur">
                        <div class="col-md-6">
                            <label for="idIndicateur" class="form-label">Indicateur</label>
                            <select class="form-select" id="idIndicateur" name="idIndicateur" onchange="this.form.submit()">
                                <?php foreach ($indicateurs as $indicateur): ?>
                                    <option value="<?= $indicateur['idIndicateur'] ?>" <?= $indicateur['idIndicateur'] == $idIndicateur ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($indicateur['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($indicateurSelectionne): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($indicateurSelectionne['nom']) ?></h5>
                        <?php if (!empty($indicateurSelectionne['description'])): ?>
                            <p class="text-muted"><?= htmlspecialchars($indicateurSelectionne['description']) ?></p>
                        <?php endif; ?>

                        <?php if (empty($valeurs)): ?>
                            <div class="alert alert-info">Aucune valeur n'a encore été encodée pour cet indicateur.</div>
                        <?php else: ?>
                            <canvas id="indicateurChart" height="90"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($valeurs)): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Historique des valeurs</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Structure</th>
                                            <th>Valeur</th>
                                            <th>Observation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_reverse($valeurs) as $valeur): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($valeur['dateEnregistrement'])) ?></td>
                                                <td><?= htmlspecialchars($valeur['nomStructure']) ?></td>
                                                <td><?= htmlspecialchars((string) $valeur['valeur']) ?></td>
                                                <td><?= htmlspecialchars($valeur['observation'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php if (!empty($valeurs)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new DataTable('.datatable', { responsive: true, pageLength: 10 });

    const ctx = document.getElementById('indicateurChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: <?= json_encode($indicateurSelectionne['nom']) ?>,
                    data: <?= json_encode($chartValues) ?>,
                    borderColor: '#4154f1',
                    backgroundColor: 'rgba(65, 84, 241, 0.15)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include "./views/include/footer.php"; ?>
