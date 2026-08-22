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
        <h1>LISTE DES DOCUMENTS PAR PROJET ET ACTIVITÉ</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Liste des Documents</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button onclick="printDocuments()" class="btn btn-secondary mb-3"><i class="bi bi-printer"></i> Imprimer</button>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Documents</h5>
                        <div id="printableArea">
                            <?php
                            foreach ($projects as $project) {
                                $activities = $projet->getActivitiesByProjectWithAccess($project['idProjet'], $userId, $searchQuery);
                                $totalDocuments = $projet->getTotalDocumentsByProject($project['idProjet']);
                                if (!empty($activities)) {
                                    echo "<h6>Projet: {$project['nomProjet']}</h6>";
                                    $activityNumber = 1;
                                    foreach ($activities as $activity) {
                                        $documents = $projet->getDocumentsByActivity($activity['idActivite_projet']);
                                        $activityDocumentCount = count($documents);
                                        $performanceRate = ($totalDocuments > 0) ? ($activityDocumentCount / $totalDocuments) * 100 : 0;
                                        echo "<h6>Activité {$activityNumber}: {$activity['intitule']} - Taux de Performance: " . number_format($performanceRate, 2) . "%</h6>";
                                        echo "<table class='table table-striped table-bordered'>
                                            <thead>
                                                <tr>
                                                    <th>Titre</th>
                                                    <th>Date Document</th>
                                                    <th>Auteur</th>
                                                    <th>Date Enregistrement</th>
                                                </tr>
                                            </thead>
                                            <tbody>";
                                        foreach ($documents as $document) {
                                            $author = $structure->getUserById($document['idUser'])->fetch();
                                            echo "
                                            <tr>
                                                <td>{$document['titre']}</td>
                                                <td>" . date("d/m/Y", strtotime($document['dateDocument'])) . "</td>
                                                <td>{$author['nomUser']}</td>
                                                <td>" . date("d/m/Y", strtotime($document['dateEnregistrement'])) . "</td>
                                            </tr>";
                                        }
                                        echo "</tbody></table>";
                                        $activityNumber++;
                                    }
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
function printDocuments() {
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
    newWindow.document.write('<h2 style="margin: 0;">Liste des Documents</h2>');
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