<?php
include "./views/include/header.php";

// Récupérer l'idAgent à partir de la requête GET
$idAgent = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;

$search = isset($_GET['search']) ? $_GET['search'] : ''; // Requête de recherche

// Vérifiez si l'ID est valide, sinon redirigez ou affichez un message d'erreur
if ($idAgent == 0) {
    echo "<script>
        window.history.back();
        </script>";
    exit;
}

$agentModel = new Agent();
$agentData = $agentModel->getFamilyMembersByAgent($idAgent, $search); // Get family members with search

?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= mb_strtoupper($nomAgent) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Famille</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashbord -->
    <section class="section dashboard">
        <div class="row">
            <!-- Table data -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table service -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des Membres de la Famille pour l'agent
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createFamilyMemberModal" class="btnPage"><i class="bi bi-plus-circle-fill"></i> Ajouter</a>
                                        | <a href="index.php?view=agents" class="btnPageReturn"><i class="bi bi-arrow-return-left"></i> Retour</a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <form method="GET" action="" class="d-flex">
                                            <!-- Conserver le paramètre `view` -->
                                            <input type="hidden" name="view" value="agent/family">
                                            <!-- Conserver l'identifiant de l'agent -->
                                            <input type="hidden" name="agent_id" value="<?= htmlspecialchars($idAgent) ?>">
                                            
                                            <!-- Champ de recherche -->
                                            <input 
                                                type="text" 
                                                name="search" 
                                                value="<?= htmlspecialchars($search ?? '') ?>" 
                                                class="form-control me-2" 
                                                placeholder="Rechercher..."
                                            >
                                            
                                            <!-- Bouton de recherche -->
                                            <button type="submit" class="btn btn-primary">Rechercher</button>
                                        </form>
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Nom</th>
                                            <th>Sexe</th>
                                            <th>Date de Naissance</th>
                                            <th>Lieu de Naissance</th>
                                            <th>Type de Liaison</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                        $i = 1;
                                        foreach ($agentData as $member) {
                                            echo "
                                                <tr>
                                                    <td>{$i}</td>
                                                    <td>{$member['noms']}</td>
                                                    <td>{$member['sexe']}</td>
                                                    <td>{$member['dateNaissance']}</td>
                                                    <td>{$member['lieuNaissance']}</td>
                                                    <td>{$member['typeLiaison']}</td>
                                                    <td>
                                                        <button class='btn btn-primary btn-sm me-1' 
                                                                onclick=\"editFamilyMember({$member['idDossier_famille']}, '{$member['noms']}', '{$member['sexe']}', '{$member['dateNaissance']}', '{$member['lieuNaissance']}', '{$member['typeLiaison']}')\">
                                                            <span class='bi bi-pencil-square'></span> Modifier
                                                        </button>
                                                    </td>
                                                </tr>
                                            ";
                                            $i++;
                                        }
                                        if ($i === 1) {
                                            echo "<tr><td colspan='7' class='text-center'>Aucun résultat trouvé</td></tr>";
                                        }
                                    ?>

                                    </tbody>
                                </table>

                            </div>

                        </div>
                    </div><!-- End Recent messages -->

                </div>
            </div><!-- End table data -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un membre de la famille -->
<div class="modal fade" id="createFamilyMemberModal" tabindex="-1" role="dialog" aria-labelledby="createFamilyMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un membre de la famille</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_family_member.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="noms" class="form-label">Nom</label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="agent_id" value="<?= $idAgent ?>">
                                <input type="text" name="noms" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="sexe" class="form-label">Sexe</label>
                            <div class="input-group has-validation">
                                <select name="sexe" class="form-control" required>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une option SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="dateNaissance" class="form-label">Date de Naissance</label>
                            <div class="input-group has-validation">
                                <input type="date" name="dateNaissance" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir une date SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="lieuNaissance" class="form-label">Lieu de Naissance</label>
                            <div class="input-group has-validation">
                                <input type="text" name="lieuNaissance" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="typeLiaison" class="form-label">Type de Liaison</label>
                            <div class="input-group has-validation">
                                <input type="text" name="typeLiaison" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addFamilyMemberBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <div class="ladda-label">Enregistrer</div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un membre de la famille -->
<div class="modal fade" id="editFamilyMemberModal" tabindex="-1" role="dialog" aria-labelledby="editFamilyMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un membre de la famille</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form method="POST" action="controller/edit_family_member.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="noms" class="form-label">Nom</label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idDossier_famille" id="editFamilyMemberId">
                                <input type="text" name="noms" id="editNoms" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="sexe" class="form-label">Sexe</label>
                            <div class="input-group has-validation">
                                <select name="sexe" id="editSexe" class="form-control" required>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner une option SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="dateNaissance" class="form-label">Date de Naissance</label>
                            <div class="input-group has-validation">
                                <input type="date" name="dateNaissance" id="editDateNaissance" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir une date SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="lieuNaissance" class="form-label">Lieu de Naissance</label>
                            <div class="input-group has-validation">
                                <input type="text" name="lieuNaissance" id="editLieuNaissance" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="typeLiaison" class="form-label">Type de Liaison</label>
                            <div class="input-group has-validation">
                                <input type="text" name="typeLiaison" id="editTypeLiaison" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editFamilyMemberBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <div class="ladda-label">Enregistrer</div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour ouvrir la modale de modification avec les données actuelles du membre de la famille
    function editFamilyMember(id, noms, sexe, dateNaissance, lieuNaissance, typeLiaison) {
        document.getElementById('editFamilyMemberId').value = id;
        document.getElementById('editNoms').value = noms;
        document.getElementById('editSexe').value = sexe;
        document.getElementById('editDateNaissance').value = dateNaissance;
        document.getElementById('editLieuNaissance').value = lieuNaissance;
        document.getElementById('editTypeLiaison').value = typeLiaison;
        $('#editFamilyMemberModal').modal('show');
    }
</script>

<?php include "./views/include/footer.php"; ?>