<?php
include "./views/include/header.php";
$structure = new Structure();

$projet = new Projet();
$userId = $_SESSION['id'];

// Fetch projects the user has access to
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = $projet->getProjetByUserStructure($userId, $searchQuery, 20);

// Group projects by structure
$projectsByStructure = [];
foreach ($projects as $project) {
    $structureName = $project['designation']; // Assuming 'structureName' is a field in the project data
    if (!isset($projectsByStructure[$structureName])) {
        $projectsByStructure[$structureName] = [];
    }
    $projectsByStructure[$structureName][] = $project;
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>LISTE DES PROJETS PAR STRUCTURE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Liste des Projets</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button onclick="printProjects()" class="btn btn-secondary mb-3"><i class="bi bi-printer"></i> Imprimer</button>
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="projet/projet.view">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher un projet...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Projets par Structure</h5>
                        <?php foreach ($projectsByStructure as $structureName => $projects): ?>
                            <h6><?= htmlspecialchars($structureName) ?></h6>
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nom du Projet</th>
                                        <th>Description</th>
                                        <th>Date Début</th>
                                        <th>Date Fin</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <?php
                                        $dd = date('d/m/Y', strtotime($project['dateDebut']));
                                        $df = date('d/m/Y', strtotime($project['dateFin']));
                                        $currentDate = date('Y-m-d');
                                        $status = (strtotime($project['dateFin']) < strtotime($currentDate)) ? 'Terminé' : 'En cours';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($project['nomProjet']) ?></td>
                                            <td><?= htmlspecialchars($project['description']) ?></td>
                                            <td><?= $dd ?></td>
                                            <td><?= $df ?></td>
                                            <td><?= $status ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
function printProjects() {
    const printContents = document.querySelector('.card-body').innerHTML;
    const newWindow = window.open('', '', 'width=800,height=600');
    newWindow.document.write('<html><head><title>Liste des Projets</title><style>');
    newWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }');
    newWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }');
    newWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    newWindow.document.write('th { background-color: #f4f4f4; }');
    newWindow.document.write('</style></head><body>');
    newWindow.document.write('<div class="header">');
    newWindow.document.write('<img src="../uploads/bdom_bukavu_logo.jpg" alt="Logo" style="width: 100px; height: auto; float: left; margin-right: 20px;">');
    newWindow.document.write('<h2 style="margin: 0;">Liste des Projets</h2>');
    newWindow.document.write('<p style="clear: both;"></p>');
    newWindow.document.write('</div>');
    newWindow.document.write(printContents);
    newWindow.document.write('<p>Imprimé par: <?php echo $_SESSION["nom"]; ?> le <?php echo date("d/m/Y"); ?></p>');
    newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
    newWindow.document.write('</body></html>');
    newWindow.document.close();
}
</script>

<?php include "./views/include/footer.php"; ?>