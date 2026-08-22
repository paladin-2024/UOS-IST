<?php
include "./views/include/header.php";
$structure = new Structure();

$projet = new Projet();
$userId = $_SESSION['id'];
$userName = $_SESSION['username']; // Assuming username is stored in session

// Fetch projects the user has access to
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = $projet->getProjetByUserAccess($userId, '', 200);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES ACTIVITÉS DE PROJET</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Activités</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="projet/activite.list">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher une activité...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
                <button onclick="printActivities()" class="btn btn-secondary mb-3"><i class="bi bi-printer"></i> Imprimer</button>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Activités</h5>
                        <div id="printableArea">
                            <?php
                            $projectNumber = 1;
                            foreach ($projects as $project) {
                                $activities = $projet->getActivitiesByProjectWithAccess($project['idProjet'], $userId, $searchQuery);
                                if (!empty($activities)) {
                                    echo "<h6>Projet {$projectNumber}: {$project['nomProjet']}</h6>";
                                    echo "<table class='table table-striped table-bordered'>
                                        <thead>
                                            <tr>
                                                <th scope='col'>#</th>
                                                <th scope='col'>Intitulé</th>
                                                <th scope='col'>Date Début</th>
                                                <th scope='col'>Date Fin</th>
                                                <th scope='col'>Budget</th>
                                                <th scope='col'>État</th>
                                            </tr>
                                        </thead>
                                        <tbody>";
                                    $i = 1;
                                    foreach ($activities as $activity) {
                                        $dd = date('d/m/Y', strtotime($activity['dateDebut']));
                                        $df = date('d/m/Y', strtotime($activity['dateFin']));
                                        $currentDate = date('Y-m-d');
                                        $status = (strtotime($activity['dateFin']) < strtotime($currentDate)) ? 'Terminé' : 'En cours';
                                        echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$activity['intitule']}</td>
                                            <td>{$dd}</td>
                                            <td>{$df}</td>
                                            <td>{$activity['budget']}</td>
                                            <td>{$status}</td>
                                        </tr>";
                                        $i++;
                                    }
                                    echo "</tbody></table>";
                                    $projectNumber++;
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
function printActivities() {
    const printContents = document.getElementById('printableArea').innerHTML;
    const newWindow = window.open('', '', 'width=800,height=600');
    newWindow.document.write('<html><head><title>Liste des Activités</title><style>');
    newWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }');
    newWindow.document.write('h6 { color: #333; }');
    newWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }');
    newWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    newWindow.document.write('th { background-color: #f4f4f4; }');
    newWindow.document.write('</style></head><body>');
    newWindow.document.write('<div class="header">');
    newWindow.document.write('<img src="../uploads/bdom_bukavu_logo.jpg" alt="Logo" style="width: 100px; height: auto; float: left; margin-right: 20px;">');
    newWindow.document.write('<h2 style="margin: 0;">Liste des Activités</h2>');
    newWindow.document.write('<p style="clear: both;"></p>');
    newWindow.document.write('</div>');
    newWindow.document.write(printContents);
    newWindow.document.write('<p>Imprimé par: <?php echo $_SESSION['nom']; ?> le <?php echo date("d/m/Y"); ?></p>');
    newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
    newWindow.document.write('</body></html>');
    newWindow.document.close();
}
</script>

<?php include "./views/include/footer.php"; ?>