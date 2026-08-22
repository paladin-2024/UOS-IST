<?php
include "./views/include/header.php";
$agent = new Agent();
$structure = new Structure();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Agents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Agents</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des agents</h5>

                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="grh/agent.dossier">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <div id="loadingIndicator" style="display: none; text-align: center; margin-bottom: 20px;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Noms</th>
                                    <th scope="col">Lieu de Naissance</th>
                                    <th scope="col">Date de Naissance</th>
                                    <th scope="col">Sexe</th>
                                    <th scope="col">Structure</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $listeAgent = $agent->getAgents($search);
                                $i = 1;

                                foreach ($listeAgent as $l) {
                                    $ver1 = $structure->getUserPermissionStructure($_SESSION['id'], $l['idStructure']);
                                    if ($ver1->fetch()) {
                                        $formattedId = sprintf("BDOM-%05d", $l['idAgent']);
                                        $idAgent = $l['idAgent'];
                                        echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$l['noms']}</td>
                                            <td>{$l['lieuNaissance']}</td>
                                            <td>{$l['dateNaissance']}</td>
                                            <td>{$l['sexe']}</td>
                                            <td>{$l['designationStructure']}</td>
                                            <td>
                                                <button class='btn btn-sm btn-primary' onclick='printAgentDetails(
                                                    \"{$idAgent}\", 
                                                    \"{$l['noms']}\",
                                                    \"{$l['lieuNaissance']}\",
                                                    \"{$l['dateNaissance']}\",
                                                    \"{$l['sexe']}\",
                                                    \"{$l['designationStructure']}\",
                                                    \"{$formattedId}\",
                                                    \"{$l['photo']}\"
                                                )'>
                                                    <i class='bi bi-printer'></i> Imprimer Dossier
                                                </button>
                                            </td>
                                        </tr>";
                                        $i++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<script>
    function formatDate(dateString) {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are zero-based
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function printAgentDetails(formattedId, noms, lieuNaissance, dateNaissance, sexe, structure, formate, photo) {
        const loadingIndicator = document.getElementById('loadingIndicator');
        loadingIndicator.style.display = 'block'; // Show loading indicator

        fetch(`controller/getAgentDetails.php?idAgent=${formattedId}`)
            .then(response => response.json())
            .then(data => {
                loadingIndicator.style.display = 'none'; // Hide loading indicator
                let newWindow = window.open('', '', 'width=800,height=600');
                newWindow.document.write('<html><head><title>Dossier de l\'Agent</title><style>');
                newWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }');
                newWindow.document.write('h2, h3 { color: #333; }');
                newWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }');
                newWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
                newWindow.document.write('th { background-color: #f4f4f4; }');
                newWindow.document.write('.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }');
                newWindow.document.write('.logo { width: 100px; height: auto; }');
                newWindow.document.write('.agent-photo { width: 100px; height: auto; border-radius: 50%; }');
                newWindow.document.write('</style></head><body>');
                newWindow.document.write('<div class="header">');
                newWindow.document.write('<img src="../uploads/bdom_bukavu_logo.jpg" alt="Logo" class="logo">');
                if (photo) {
                    newWindow.document.write('<img src="../uploads/agents/' + photo + '" alt="Photo de l\'agent" class="agent-photo">');
                }
                newWindow.document.write('</div>');
                newWindow.document.write('<center><h2>DOSSIER DE L\'AGENT</h2></center>');
                newWindow.document.write('<p><strong>ID :</strong> ' + formate + '</p>');
                newWindow.document.write('<p><strong>Noms :</strong> ' + noms + '</p>');
                newWindow.document.write('<p><strong>Lieu de Naissance :</strong> ' + lieuNaissance + '</p>');
                newWindow.document.write('<p><strong>Date de Naissance :</strong> ' + formatDate(dateNaissance) + '</p>');
                newWindow.document.write('<p><strong>Sexe :</strong> ' + sexe + '</p>');
                newWindow.document.write('<p><strong>Affectation :</strong> ' + structure + '</p>');

                // Add contracts
                newWindow.document.write('<h3>Contrats</h3>');
                newWindow.document.write('<table><thead><tr><th>Designation</th><th>Type</th><th>Date Début</th><th>Date Fin</th><th>Fonction</th><th>Service</th></tr></thead><tbody>');
                data.contracts.forEach(contract => {
                    newWindow.document.write('<tr><td>' + contract.designation + '</td><td>' + contract.typeContrat + '</td><td>' + formatDate(contract.dateDebut) + '</td><td>' + formatDate(contract.dateFin) + '</td><td>' + contract.fonction + '</td><td>' + contract.service + '</td></tr>');
                });
                newWindow.document.write('</tbody></table>');

                // Add family members
                newWindow.document.write('<h3>Membres de la Famille</h3>');
                newWindow.document.write('<table><thead><tr><th>Nom</th><th>Sexe</th><th>Date de Naissance</th><th>Type de Liaison</th></tr></thead><tbody>');
                data.familyMembers.forEach(member => {
                    newWindow.document.write('<tr><td>' + member.noms + '</td><td>' + member.sexe + '</td><td>' + formatDate(member.dateNaissance) + '</td><td>' + member.typeLiaison + '</td></tr>');
                });
                newWindow.document.write('</tbody></table>');

                // Add documents
                newWindow.document.write('<h3>Documents</h3>');
                newWindow.document.write('<table><thead><tr><th>Titre</th><th>Description</th><th>Fichier</th></tr></thead><tbody>');
                data.documents.forEach(document => {
                    newWindow.document.write('<tr><td>' + document.titre + '</td><td>' + document.description + '</td><td>' + document.fichier + '</td></tr>');
                });
                newWindow.document.write('</tbody></table>');

                // Add print date
                const printDate = new Date();
                newWindow.document.write('<p><strong>Date d\'impression :</strong> ' + formatDate(printDate.toISOString()) + '</p>');

                newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
                newWindow.document.write('</body></html>');
                newWindow.document.close();
            })
            .catch(error => {
                loadingIndicator.style.display = 'none'; // Hide loading indicator on error
                console.error('Error fetching agent details:', error);
            });
    }
</script>

<?php include "./views/include/footer.php"; ?>