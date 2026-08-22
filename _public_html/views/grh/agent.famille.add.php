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
                                <input type="hidden" name="view" value="grh/agent.famille.add">

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
                            $i=1;

                            foreach ($agents as $agent) {
                                $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                if ($ver1->fetch()) {
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$agent['noms']}</td>
                                            <td>{$agent['lieuNaissance']}</td>
                                            <td>{$agent['dateNaissance']}</td>
                                            <td>{$agent['sexe']}</td>
                                            <td>{$agent['designationStructure']}</td>
                                            <td>{$agent['totalFamilyMembers']}</td>
                                            <td>
                                                <button type='button' class='btn btn-primary btn-sm add-family-member-btn' data-agent-id='{$agent['idAgent']}' data-bs-toggle='modal' data-bs-target='#addFamilyMemberModal'>
                                                    <i class='bi bi-plus-circle-fill'></i>
                                                </button>
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
                                                            <th>Actions</th>
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
                                                <td>{$member['\"dateNaissance\"']}</td>
                                                <td>{$age}</td>
                                                <td>{$member['typeLiaison']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-warning btn-sm' 
                                                            data-bs-toggle='modal' 
                                                            data-bs-target='#editFamilyMemberModal' 
                                                            data-member-id='{$member['\"idDossier_famille\"']}'
                                                            data-noms='{$member['noms']}'
                                                            data-sexe='{$member['sexe']}'
                                                            data-date-naissance='{$member['\"dateNaissance\"']}'
                                                            data-lieu-naissance='{$member['\"lieuNaissance\"']}'
                                                            data-type-liaison='{$member['typeLiaison']}'>
                                                        Modifier
                                                    </button>
                                                    <form action='controller/delete_membre.php' method='POST' class='delete-family-member-form' style='display:inline;'>
                                                        <input type='hidden' name='idDossierFamille' value='{$member['\"idDossier_famille\"']}'>
                                                        <button type='button' class='btn btn-danger btn-sm delete-family-member-btn'>Supprimer</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        ";
                                    }

                                    echo "
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    ";
                                    $i++;
                                }
                            }

                            if (!$hasResults) {
                                echo "<tr><td colspan='10' class='text-center'>Aucun résultat trouvé</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>

                    <!-- Modal pour ajouter un membre de la famille -->
                    <div class="modal fade" id="addFamilyMemberModal" tabindex="-1" aria-labelledby="addFamilyMemberModalLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Increased size of the modal -->
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addFamilyMemberModalLabel">Ajouter un membre de la famille</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="addFamilyMemberForm" action="controller/create_membre.php" method="POST">
                                        <input type="hidden" name="agentId" id="agentId">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="noms" class="form-label">Nom</label>
                                                <input type="text" class="form-control" id="noms" name="noms" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="sexe" class="form-label">Sexe</label>
                                                <select class="form-select" id="sexe" name="sexe" required>
                                                    <option value="M">Masculin</option>
                                                    <option value="F">Féminin</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for=dateNaissance class="form-label">Date de Naissance</label>
                                                <input type="date" class="form-select" id=dateNaissance name=dateNaissance required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for=lieuNaissance class="form-label">Lieu de Naissance</label>
                                                <input type="text" class="form-control" id=lieuNaissance name=lieuNaissance required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="typeLiaison" class="form-label">Type de Liaison</label>
                                            <select class="form-control" id="typeLiaison" name="typeLiaison" required>
                                                <option value="">Sélectionnez un type de liaison</option>
                                                <option value="Père">Père</option>
                                                <option value="Mère">Mère</option>
                                                <option value="Mari">Mari</option>
                                                <option value="Femme">Femme</option>
                                                <option value="Enfant">Enfant</option>
                                                <option value="Autre">Autre</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Ajouter</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

<!-- Modal for editing a family member -->
<div class="modal fade" id="editFamilyMemberModal" tabindex="-1" aria-labelledby="editFamilyMemberModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Increased size of the modal -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFamilyMemberModalLabel">Modifier un membre de la famille</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editFamilyMemberForm" action="controller/update_membre.php" method="POST">
                    <input type="hidden" name="idDossierFamille" id="editIdDossierFamille">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editNoms" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="editNoms" name="noms" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editSexe" class="form-label">Sexe</label>
                            <select class="form-control" id="editSexe" name="sexe" required>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateNaissance" class="form-label">Date de Naissance</label>
                            <input type="date" class="form-control" id="editDateNaissance" name=dateNaissance required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLieuNaissance" class="form-label">Lieu de Naissance</label>
                            <input type="text" class="form-control" id="editLieuNaissance" name=lieuNaissance required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editTypeLiaison" class="form-label">Type de Liaison</label>
                        <select class="form-control" id="editTypeLiaison" name="typeLiaison" required>
                            <option value="Père">Père</option>
                            <option value="Mère">Mère</option>
                            <option value="Mari">Mari</option>
                            <option value="Femme">Femme</option>
                            <option value="Enfant">Enfant</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <button type="submit" name="action" value="add" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

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