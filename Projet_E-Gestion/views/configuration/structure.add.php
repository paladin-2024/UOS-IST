<?php
include "./views/include/header.php";
$structure = new Structure();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>STRUCTURES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Structures</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table structure -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des structures
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createStructureModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Désignation</th>
            <th scope="col">Adresse</th>
            <th scope="col">Téléphone</th>
            <th scope="col">Site web</th>
            <th scope="col">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $listeStructure = $structure->getStructures(); // Récupération des structures
        $i = 1;

        foreach ($listeStructure as $l) {
            echo "
            <tr>
                <td>{$i}</td>
                <td>{$l['designation']}</td>
                <td>{$l['adresse']}</td>
                <td>{$l['phone1']}</td>
                <td>{$l['siteweb']}</td>
                <td>
                    <!-- Bouton pour afficher les utilisateurs -->
                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseUsers{$l['idStructure']}' aria-expanded='false' aria-controls='collapseUsers{$l['idStructure']}'>
                        <i class='bi bi-people'></i> Users
                    </button>

                    <!-- Bouton pour ajouter un utilisateur -->
                    <button class='btn btn-sm btn-primary' data-bs-toggle='modal' data-bs-target='#modalAddUser{$l['idStructure']}'>
                        <i class='bi bi-plus-circle'></i>
                    </button>

                    <!-- Bouton pour modifier la structure -->
                    <button class='btn btn-sm btn-warning' onclick='editStructure(
                        {$l['idStructure']}, 
                        \"{$l['designation']}\",
                        \"{$l['adresse']}\",
                        \"{$l['phone1']}\",
                        \"{$l['phone2']}\",
                        \"{$l['siteweb']}\",
                        \"{$l['logo']}\",
                        {$l['joursOuvrables']},
                        {$l['IPR']},
                        {$l['taux_retenu_absence']},
                        \"{$l['dateEnregistrement']}\",
                        {$l['nJoursRecouvrement']}
                    )'>
                        <i class='bi bi-pencil-square'></i> Modifier
                    </button>
                </td>
            </tr>
            <tr class='collapse-row'>
                <td colspan='6' class='p-0'>
                    <div class='collapse' id='collapseUsers{$l['idStructure']}'>
                        <table class='table table-sm table-bordered m-0'>
                            <thead>
                                <tr>
                                    <th>Nom utilisateur</th>
                                    <th>Peut tout voir</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>";
            // Récupération des utilisateurs autorisés pour la structure
            $l_user = $structure->getUserStructure($l['idStructure']);
            while ($user = $l_user->fetch()) {
                if($user['toutvoir']==0) $voir="Non";else $voir="Oui";
                echo "
                                <tr>
                                    <td>{$user['nomUser']}</td>
                                    <td>{$voir}</td>
                                    
                                    <td>
                                        <a class='btn btn-sm btn-danger' onclick='confirmDelete({$user['id_user_structure']})'>
                                            <i class='bi bi-trash'></i> Supprimer
                                        </a>
                                    </td>
                                </tr>";
            }
            echo "
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            ";

            // Modal pour ajouter un utilisateur
            ?>
            <div class="modal fade" id="modalAddUser<?php echo $l['idStructure']; ?>" tabindex="-1" aria-labelledby="modalLabelAddUser<?php echo $l['idStructure']; ?>" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelAddUser<?php echo $l['idStructure']; ?>">Ajouter un utilisateur pour <?php echo $l['designation']; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="controller/addUserStructure.php" class="needs-validation" novalidate>
                                <input type="hidden" name="idStructure" value="<?php echo $l['idStructure']; ?>" />
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="userSelect<?php echo $l['idStructure']; ?>" class="form-label">Sélectionner un utilisateur <span class="text-danger">*</span></label>
                                        <select id="userSelect<?php echo $l['idStructure']; ?>" name="idUser" class="form-control" required>
                                            <?php
                                            $util = $structure->getUsers();
                                            while ($a = $util->fetch()) {
                                                echo "<option value='{$a['idUser']}'>{$a['nomUser']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="voirSelect<?php echo $l['idStructure']; ?>" class="form-label">Peut tout voir <span class="text-danger">*</span></label>
                                        <select id="voirSelect<?php echo $l['idStructure']; ?>" name="voir" class="form-control" required>
                                            <option value="0">Non</option>
                                            <option value="1">Oui</option>
                                        </select>
                                        <div class="invalid-feedback">Veuillez indiquer si l'utilisateur peut tout voir.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $i++;
        }
        ?>
    </tbody>
</table>


                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter une structure -->
<div class="modal fade" id="createStructureModal" tabindex="-1" role="dialog" aria-labelledby="createStructureModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_structure.php" class="needs-validation" novalidate enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="adresse" class="form-label">Adresse</label>
                            <input type="text" name="adresse" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone1" class="form-label">Téléphone 1</label>
                            <input type="text" name="phone1" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="phone2" class="form-label">Téléphone 2</label>
                            <input type="text" name="phone2" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="siteweb" class="form-label">Site web</label>
                            <input type="text" name="siteweb" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="logo" class="form-label">Logo (image)</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="joursOuvrables" class="form-label">Jours ouvrables</label>
                            <input type="number" name="joursOuvrables" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="IPR" class="form-label">IPR</label>
                            <input type="number" step="0.01" name="IPR" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="taux_retenu_absence" class="form-label">Taux retenu pour absence</label>
                            <input type="number" step="0.01" name="taux_retenu_absence" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="nJoursRecouvrement" class="form-label">Nombre de jours de recouvrement</label>
                            <input type="number" name="nJoursRecouvrement" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addStructureBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une structure -->
<div class="modal fade" id="editStructureModal" tabindex="-1" role="dialog" aria-labelledby="editStructureModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_structure.php" class="needs-validation" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="idStructure" id="editStructureId">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editStructureDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="adresse" class="form-label">Adresse</label>
                            <input type="text" name="adresse" id="editStructureAdresse" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="phone1" class="form-label">Téléphone 1</label>
                            <input type="text" name="phone1" id="editStructurePhone1" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="phone2" class="form-label">Téléphone 2</label>
                            <input type="text" name="phone2" id="editStructurePhone2" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="siteweb" class="form-label">Site web</label>
                            <input type="text" name="siteweb" id="editStructureSiteweb" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="logo" class="form-label">Logo (image)</label>
                            <input type="file" name="logo" id="editStructureLogo" class="form-control" accept="image/*">
                            <div id="logoPreviewContainer">
                                <img id="logoPreview" src="" alt="Logo actuel" style="max-width: 100px; margin-top: 10px;" />
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="joursOuvrables" class="form-label">Jours ouvrables</label>
                            <input type="number" name="joursOuvrables" id="editStructureJoursOuvrables" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="IPR" class="form-label">IPR</label>
                            <input type="number" step="0.01" name="IPR" id="editStructureIPR" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="taux_retenu_absence" class="form-label">Taux retenu pour absence</label>
                            <input type="number" step="0.01" name="taux_retenu_absence" id="editStructureTauxRetenuAbsence" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="nJoursRecouvrement" class="form-label">Nombre de jours de recouvrement</label>
                            <input type="number" name="nJoursRecouvrement" id="editStructureNJoursRecouvrement" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editStructureBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour pré-remplir le formulaire de modification
    function editStructure(id, designation, adresse, phone1, phone2, siteweb, logo, joursOuvrables, IPR, tauxRetenuAbsence, dateEnregistrement, nJoursRecouvrement) {
        document.getElementById('editStructureId').value = id;
        document.getElementById('editStructureDesignation').value = designation;
        document.getElementById('editStructureAdresse').value = adresse;
        document.getElementById('editStructurePhone1').value = phone1;
        document.getElementById('editStructurePhone2').value = phone2;
        document.getElementById('editStructureSiteweb').value = siteweb;
        
        // Afficher l'image du logo si elle existe
        const logoPreview = document.getElementById('logoPreview');
        const logoPreviewContainer = document.getElementById('logoPreviewContainer');
        if (logo) {
            logoPreview.src = 'uploads/' + logo;
            logoPreviewContainer.style.display = 'block';  // Afficher l'image
        } else {
            logoPreviewContainer.style.display = 'none';  // Masquer l'image si aucun logo
        }

        document.getElementById('editStructureJoursOuvrables').value = joursOuvrables;
        document.getElementById('editStructureIPR').value = IPR;
        document.getElementById('editStructureTauxRetenuAbsence').value = tauxRetenuAbsence;
        document.getElementById('editStructureNJoursRecouvrement').value = nJoursRecouvrement;

        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('editStructureModal'));
        modal.show();
    }

    function confirmDelete(id_user_structure) {
        Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
        }).then((result) => {
        if (result.isConfirmed) {
            // Redirection vers le script de suppression PHP
            window.location.href = 'controller/deleteUserStructure.php?id=' + id_user_structure;
        }
        })
    }
</script>
<?php include "./views/include/footer.php"; ?>