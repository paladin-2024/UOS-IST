<?php
include "./views/include/header.php";

$roleModel = new Role();
$roles = $roleModel->getAllRoles();
$roles2 = $roleModel->getAllRoles();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>UTILISATEURS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Utilisateurs</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashbord -->
    <section class="section dashboard">
        <div class="row">
            <!-- TAbele data -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table service -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des Utilisateurs
                                    <span>
                                        | <a href="grh/agent.add" class="btnPage"><i class="bi bi-plus-circle-fill"></i> Ajouter</a>
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
                                            <th>N°</th>
                                            <th>Nom</th>
                                            <th>Login</th>
                                            <th>Rôle</th>
                                            <th>Image</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <!-- Les données seront chargées ici dynamiquement -->
                                    </tbody>
                                </table>
                                <div id="loading" class="text-center d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </div>

                                <div id="loadingButton" class="text-center">
                                    <button type="button" id="loadMoreButton" class="btnPage"><i class="bi bi-arrow-clockwise"></i> Voir plus des données</button>
                                </div>

                            </div>

                        </div>
                    </div><!-- End Recent messages -->

                </div>
            </div><!-- End table data -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un utilisateur -->
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_user.php" enctype="multipart/form-data" class="needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nomUser" class="form-label">Nom de l'utilisateur <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" id="nomUser" name="nomUser" class="form-control" required />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="loginUser" class="form-label">Login <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" id="loginUser" name="loginUser" class="form-control" required />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="pw" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="password" id="pw" name="pw" class="form-control" required />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="imageUser" class="form-label">Image</label>
                            <div class="input-group has-validation">
                                <input type="file" id="imageUser" name="imageUser" class="form-control imageUser" />
                                <div class="invalid-feedback">Veuillez choisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="idRole" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select id="idRole" class="form-select" name="idRole" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= htmlspecialchars($role['idRole']) ?>">
                                        <?= htmlspecialchars($role['nomRole']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="btn" class="btnModSave ladda-button" data-style="zoom-out">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_user.php" enctype="multipart/form-data" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editNomUser" class="form-label">Nom de l'utilisateur <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idUser" id="editUserId">
                                <input type="text" name="nomUser" id="editNomUser" class="form-control" required />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editLoginUser" class="form-label">Login <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="loginUser" id="editLoginUser" class="form-control" required />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editPw" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group has-validation">
                                <input type="password" name="pw" id="editPw" class="form-control" placeholder="Laissez vide pour conserver l'ancien" />
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="currentImage" class="form-label">Image actuelle</label>
                            <img id="currentImage" width="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="imageUser" class="form-label">Changer l'image</label>
                            <div class="input-group has-validation">
                                <input type="file" id="imageUser" name="imageUser" class="form-control imageUserTo" />
                                <div class="invalid-feedback">Veuillez choisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEtatUser" class="form-label">Statut <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <select class="form-select" id="editEtatUser" name="etatUser" required>
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                                <div class="invalid-feedback">Veuillez choisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editIdRole" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select id="editIdRole" class="form-select" name="idRole" required>
                                <?php foreach ($roles2 as $role): ?>
                                    <option value="<?= htmlspecialchars($role['idRole']) ?>">
                                        <?= htmlspecialchars($role['nomRole']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="btnUser" class="btnModSave ladda-button" data-style="zoom-out">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    

    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser le loader pour les utilisateurs
        const userLoader = new DataLoader({
            tableBodyId: 'usersTableBody',
            loadingIndicatorId: 'loading',
            searchInputId: 'searchInput',
            loadMoreButtonId: 'loadMoreButton',
            endpoint: `${APP_CONFIG.baseUrl}${APP_CONFIG.apiEndpoint}?type=users`,
            columns: [{
                    field: 'index',
                    render: (value) => value
                },
                {
                    field: 'nomUser'
                },
                {
                    field: 'loginUser'
                },
                {
                    field: 'nomRole',
                    render: value => `<strong>${value.toUpperCase()}</strong>`
                },
                {
                    field: 'imageUser',
                    render: (value, item) => `
                            <img src="${APP_CONFIG.uploadPath}/${value}" 
                                 width="50" 
                                 height="50" 
                                 alt="Photo de ${item.nomUser}" 
                                 class="rounded-circle">
                        `
                }
            ],
            actions: [{
                render: (item) => `
                            <button class="btn btn-primary btn-sm me-1" 
                                    onclick="editUser(${item.idUser}, '${item.nomUser}', '${item.loginUser}', 
                                                   ${item.idRole}, ${item.etatUser}, '${item.imageUser}')">
                                <span class="bi bi-pencil-square"></span> Modifier
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteUser(${item.idUser})">
                                <span class="bi bi-person-x"></span> Supprimer
                            </button>
                        `
            }],
            dataKey: 'user',
        });
    });

    // Fonction pour gérer l'affichage de l'image
    function handleUserImage(imageElement, imagePath) {
        if (imagePath && imagePath.trim() !== '') {
            imageElement.src = APP_CONFIG.uploadPath + '/' + imagePath;
            imageElement.onerror = function() {
                console.log('Erreur de chargement de l\'image:', imagePath);
                imageElement.src = APP_CONFIG.uploadPath + '/user.png';
            };
        } else {
            imageElement.src = APP_CONFIG.uploadPath + '/user.png';
        }
    }

    

    // Fonction pour ouvrir la modale de modification
    function editUser(id, nomUser, loginUser, idRole, etatUser, imageUrl) {
        // Remplir les champs avec les données de l'utilisateur
        document.getElementById('editUserId').value = id;
        document.getElementById('editNomUser').value = nomUser;
        document.getElementById('editLoginUser').value = loginUser;
        document.getElementById('editEtatUser').value = etatUser;

        // Gérer l'affichage de l'image
        const imageElement = document.getElementById('currentImage');
        handleUserImage(imageElement, imageUrl);

        // Attendre que la modale soit complètement ouverte
        $('#editUserModal').on('shown.bs.modal', function() {
            const roleSelect = document.getElementById('editIdRole');

            // Mettre à jour la valeur dans le select natif d'abord
            roleSelect.value = idRole;

            // Mettre à jour dselect
            if (roleSelect.dselect) {
                roleSelect.dselect.setValue(idRole);

                // Forcer la mise à jour de l'interface dselect
                const dselectValue = roleSelect.dselect.value;
                console.log('Valeur dselect après mise à jour:', dselectValue);
            }
        });

        // Afficher la modale
        var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    }

    document.querySelector('input[name="imageUser"]').addEventListener('change', function(e) {
        console.log('Fichier sélectionné :', this.files[0]);
        console.log('Taille :', this.files[0].size);
        console.log('Type :', this.files[0].type);

        if (this.files[0].size > APP_CONFIG.maxFileSize) {
            console.log('Erreur : Taille trop grande');
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'image est trop grande. La taille maximum est de 5MB.'
            });
            this.value = '';
            return;
        }

        if (!APP_CONFIG.allowedFileTypes.includes(this.files[0].type)) {
            console.log('Erreur : Type non autorisé');
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Seuls les fichiers JPG, PNG et GIF sont autorisés.'
            });
            this.value = '';
            return;
        }
    });

    document.querySelector('.imageUserTo').addEventListener('change', function(e) {
        console.log('Fichier sélectionné :', this.files[0]);
        console.log('Taille :', this.files[0].size);
        console.log('Type :', this.files[0].type);

        if (this.files[0].size > APP_CONFIG.maxFileSize) {
            console.log('Erreur : Taille trop grande');
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'L\'image est trop grande. La taille maximum est de 5MB.'
            });
            this.value = '';
            return;
        }

        if (!APP_CONFIG.allowedFileTypes.includes(this.files[0].type)) {
            console.log('Erreur : Type non autorisé');
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Seuls les fichiers JPG, PNG et GIF sont autorisés.'
            });
            this.value = '';
            return;
        }

    });

    // Fonction pour la suppression avec confirmation Swal
    function deleteUser(idUser) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`controller/delete_user.php?idUser=${idUser}`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        return response.text(); // Lire le contenu brut de la réponse
                    })
                    .then(text => {

                        // Tenter de parser le texte en JSON
                        try {
                            const data = JSON.parse(text);
                            Swal.fire({
                                icon: data.status === "success" ? "success" : "error",
                                title: data.status === "success" ? "Succès" : "Erreur",
                                text: data.message
                            }).then(() => {
                                if (data.status === "success") {
                                    window.location.reload();
                                }
                            });
                        } catch (e) {
                            console.error('Erreur de parsing JSON :', e);
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: "La réponse du serveur n'est pas valide."
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur attrapée :', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: "Une erreur s'est produite lors de la communication avec le serveur."
                        });
                    });
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>