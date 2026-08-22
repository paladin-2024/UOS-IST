<?php
include "./views/include/header.php";

$agentModel = new Agent();
$structure = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : ''; // Get search term from query
$agents = $agentModel->getAgents($search); // Retrieve agents based on search term

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

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des membres de la famille</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <!-- Conserver le paramètre `view` -->
                                <input type="hidden" name="view" value="grh/agent.famille.edit">

                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Lieu de Naissance</th>
                                <th>Date de Naissance</th>
                                <th>Sexe</th>
                                <th>Structure</th>
                                <th>Total</th> <!-- New column -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $userId = $_SESSION['id']; // Assuming user ID is stored in session
                            $hasResults = false;

                            foreach ($agents as $agent) {
                                $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                if ($ver1->fetch()) {
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$agent['idAgent']}</td>
                                            <td>{$agent['noms']}</td>
                                            <td>{$agent['lieuNaissance']}</td>
                                            <td>{$agent['dateNaissance']}</td>
                                            <td>{$agent['sexe']}</td>
                                            <td>{$agent['designationStructure']}</td>
                                            <td>{$agent['totalFamilyMembers']}</td>
                                            <td>
                                                
                                                <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#familyMembers{$agent['idAgent']}'>
                                                    <i class='bi bi-eye-fill'></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class='collapse' id='familyMembers{$agent['idAgent']}'>
                                            <td colspan='10'>
                                                <table class='table table-sm'>
                                                    <thead>
                                                        <tr>
                                                            <th>Nom</th>
                                                            <th>Sexe</th>
                                                            <th>Date de Naissance</th>
                                                            <th>Âge</th>
                                                            <th>Type de Liaison</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                    ";

                                    // Fetch family members for the current agent
                                    $familyMembers = $agentModel->getFamilyMembersByAgent($agent['idAgent']);
                                    foreach ($familyMembers as $member) {
                                        $age = date_diff(date_create($member['dateNaissance']), date_create('today'))->y;
                                        echo "
                                            <tr>
                                                <td>{$member['noms']}</td>
                                                <td>{$member['sexe']}</td>
                                                <td>{$member['dateNaissance']}</td>
                                                <td>{$age}</td>
                                                <td>{$member['typeLiaison']}</td>
                                                
                                            </tr>
                                        ";
                                    }

                                    echo "
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    ";
                                }
                            }

                            if (!$hasResults) {
                                echo "<tr><td colspan='10' class='text-center'>Aucun résultat trouvé</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>

               
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-family-member-btn').forEach(button => {
        button.addEventListener('click', function () {
            const agentId = this.getAttribute('data-agent-id');
            document.getElementById('agentId').value = agentId;
        });
    });

    document.querySelectorAll('.delete-family-member-btn').forEach(button => {
        button.addEventListener('click', function () {
            const form = this.closest('.delete-family-member-form');
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action est irréversible!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('[data-bs-target="#editFamilyMemberModal"]').forEach(button => {
        button.addEventListener('click', function () {
            const memberId = this.getAttribute('data-member-id');
            const noms = this.getAttribute('data-noms');
            const sexe = this.getAttribute('data-sexe');
            const dateNaissance = this.getAttribute('data-date-naissance');
            const lieuNaissance = this.getAttribute('data-lieu-naissance');
            const typeLiaison = this.getAttribute('data-type-liaison');

            document.getElementById('editIdDossierFamille').value = memberId;
            document.getElementById('editNoms').value = noms;
            document.getElementById('editSexe').value = sexe;
            document.getElementById('editDateNaissance').value = dateNaissance;
            document.getElementById('editLieuNaissance').value = lieuNaissance;

            // Set the selected option for typeLiaison
            const typeLiaisonSelect = document.getElementById('editTypeLiaison');
            typeLiaisonSelect.value = typeLiaison;
        });
    });
});
</script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>