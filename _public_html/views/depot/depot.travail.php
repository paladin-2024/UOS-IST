<?php
include "./views/include/header.php";
$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les données nécessaires
$academicYears = $universite->getAcademicYears();
$departements = $universite->getAllDepartments();
$specialisations = $universite->getAllSpecialisations();
$travaux = $universite->getTravaux($search);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TRAVAUX SCIENTIFIQUES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Travaux Scientifiques</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des Travaux Scientifiques
                            <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#createTravailModal">
                                <i class="bi bi-plus-circle"></i> Nouveau Travail
                            </button>
                        </h5>

                        <!-- Filtres de recherche -->
                        <form method="GET" action="" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-10">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher...">
                                </div>
                                
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                                </div>
                            </div>
                        </form>

                        <!-- Table des travaux -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Titre</th>
                                        <th>Type</th>
                                        <th>Auteur</th>
                                        <th>Département</th>
                                        <th>Année</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($travaux as $index => $travail): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($travail['titre']) ?></td>
                                        <td><?= $travail['type_document'] ?></td>
                                        <td><?= htmlspecialchars($travail['nom_auteur']) ?></td>
                                        <td><?= htmlspecialchars($travail['designationDepartement']) ?></td>
                                        <td><?= $travail['annee'] ?></td>
                                        <td>
                                            <span class="badge <?= $travail['statut'] === 'Validé' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= $travail['statut'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewTravail(<?= $travail['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="<?= $travail['fichier_path'] ?>" class="btn btn-sm btn-success" target="_blank">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            
                                                <button class="btn btn-sm btn-primary" onclick='editTravail(
                                                    <?= $travail['id'] ?>, 
                                                    "<?= addslashes(htmlspecialchars($travail['titre'])) ?>",
                                                    "<?= $travail['type_document'] ?>",
                                                    "<?= $travail['type_auteur'] ?>",
                                                    "<?= addslashes(htmlspecialchars($travail['nom_auteur'])) ?>",
                                                    <?= $travail['departement_id'] ?>,
                                                    <?= $travail['specialisation_id'] ?>,
                                                    <?= $travail['annee_academique_id'] ?>,
                                                    "<?= addslashes(htmlspecialchars($travail['mots_cles'])) ?>",
                                                    "<?= addslashes(htmlspecialchars($travail['resume'])) ?>",
                                                    <?= $travail['est_public'] ?>
                                                )'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteTravail(<?= $travail['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un travail -->
<div class="modal fade" id="createTravailModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Déposer un Travail Scientifique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="travailForm" action="controller/create_travail.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <!-- Titre -->
                        <div class="col-md-12">
                            <label class="form-label">Titre du travail</label>
                            <input type="text" name="titre" class="form-control" required>
                        </div>

                        <!-- Type de document -->
                        <div class="col-md-6">
                            <label class="form-label">Type de document</label>
                            <select name="type_document" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Mémoire">Mémoire</option>
                                <option value="Thèse">Thèse</option>
                                <option value="Rapport de stage">Rapport de stage</option>
                                <option value="Article scientifique">Article scientifique</option>
                                <option value="Projet tutoré">Projet tutoré</option>
                            </select>
                        </div>

                        <!-- Type d'auteur et nom de l'auteur -->
                        <div class="col-md-6">
                            <label class="form-label">Type d'auteur</label>
                            <select name="type_auteur" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Etudiant">Étudiant</option>
                                <option value="Enseignant">Enseignant</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nom de l'auteur</label>
                            <input type="text" name="nom_auteur" class="form-control" required>
                        </div>

                        <!-- Département -->
                        <div class="col-md-6">
                            <label class="form-label">Département</label>
                            <select name="departement_id" class="form-select" required onchange="loadSpecialisations(this.value)">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($departements as $dept): ?>
                                    <option value="<?= $dept['iddepartement'] ?>"><?= $dept['designationDepartement'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Spécialisation -->
                        <div class="col-md-6">
                            <label class="form-label">Spécialisation</label>
                            <select name="specialisation_id" id="specialisation_id" class="form-select" required>
                                <option value="">Sélectionner d'abord un département</option>
                            </select>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="annee_academique_id" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>


                        <!-- Mots-clés -->
                        <div class="col-md-12">
                            <label class="form-label">Mots-clés</label>
                            <input type="text" name="mots_cles" class="form-control" required>
                            <small class="text-muted">Séparez les mots-clés par des virgules</small>
                        </div>

                        <!-- Résumé -->
                        <div class="col-md-12">
                            <label class="form-label">Résumé</label>
                            <textarea name="resume" class="form-control" rows="4" required></textarea>
                        </div>

                        <!-- Document PDF -->
                        <div class="col-md-12">
                            <label class="form-label">Document PDF</label>
                            <input type="file" name="document" class="form-control" accept=".pdf" required>
                            <small class="text-muted">Taille maximale : 10 MB</small>
                        </div>

                        <!-- Visibilité publique -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="est_public" id="est_public" value="1">
                                <label class="form-check-label" for="est_public">
                                    Rendre ce travail public
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Déposer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier un travail -->
<div class="modal fade" id="editTravailModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Travail Scientifique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editTravailForm" action="controller/update_travail.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <!-- Titre -->
                        <div class="col-md-12">
                            <label class="form-label">Titre du travail</label>
                            <input type="text" name="titre" id="edit_titre" class="form-control" required>
                        </div>

                        <!-- Type de document -->
                        <div class="col-md-6">
                            <label class="form-label">Type de document</label>
                            <select name="type_document" id="edit_type_document" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Mémoire">Mémoire</option>
                                <option value="Thèse">Thèse</option>
                                <option value="Rapport de stage">Rapport de stage</option>
                                <option value="Article scientifique">Article scientifique</option>
                                <option value="Projet tutoré">Projet tutoré</option>
                            </select>
                        </div>

                        <!-- Type d'auteur et nom de l'auteur -->
                        <div class="col-md-6">
                            <label class="form-label">Type d'auteur</label>
                            <select name="type_auteur" id="edit_type_auteur" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Etudiant">Étudiant</option>
                                <option value="Enseignant">Enseignant</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nom de l'auteur</label>
                            <input type="text" name="nom_auteur" id="edit_nom_auteur" class="form-control" required>
                        </div>

                        <!-- Département -->
                        <div class="col-md-6">
                            <label class="form-label">Département</label>
                            <select name="departement_id" id="edit_departement_id" class="form-select" required onchange="loadSpecialisationsEdit(this.value)">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($departements as $dept): ?>
                                    <option value="<?= $dept['iddepartement'] ?>"><?= $dept['designationDepartement'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Spécialisation -->
                        <div class="col-md-6">
                            <label for="editIdSpecialisation" class="form-label">Spécialisation</label>
                            <select name="specialisation_id" id="edit_specialisation_id" class="form-control" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>"><?= $specialisation['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="annee_academique_id" id="edit_annee_academique_id" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Mots-clés -->
                        <div class="col-md-12">
                            <label class="form-label">Mots-clés</label>
                            <input type="text" name="mots_cles" id="edit_mots_cles" class="form-control" required>
                            <small class="text-muted">Séparez les mots-clés par des virgules</small>
                        </div>

                        <!-- Résumé -->
                        <div class="col-md-12">
                            <label class="form-label">Résumé</label>
                            <textarea name="resume" id="edit_resume" class="form-control" rows="4" required></textarea>
                        </div>

                        <!-- Document PDF -->
                        <div class="col-md-12">
                            <label class="form-label">Document PDF (optionnel)</label>
                            <input type="file" name="document" class="form-control" accept=".pdf">
                            <small class="text-muted">Laissez vide pour conserver le document existant</small>
                        </div>

                        <!-- Visibilité publique -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="est_public" id="edit_est_public" value="1">
                                <label class="form-check-label" for="edit_est_public">
                                    Rendre ce travail public
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour visualiser un travail -->
<div class="modal fade" id="viewTravailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Travail Scientifique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <h4 id="view_titre" class="text-primary"></h4>
                    </div>

                    <div class="col-md-6">
                        <p><strong>Type de document :</strong> <span id="view_type_document"></span></p>
                        <p><strong>Auteur :</strong> <span id="view_nom_auteur"></span> (<span id="view_type_auteur"></span>)</p>
                        <p><strong>Département :</strong> <span id="view_departement"></span></p>
                        <p><strong>Spécialisation :</strong> <span id="view_specialisation"></span></p>
                    </div>

                    <div class="col-md-6">
                        <p><strong>Année académique :</strong> <span id="view_annee"></span></p>
                        <p><strong>Date de dépôt :</strong> <span id="view_date_depot"></span></p>
                        <p><strong>Statut :</strong> <span id="view_statut"></span></p>
                        <p><strong>Visibilité :</strong> <span id="view_visibilite"></span></p>
                    </div>

                    <div class="col-md-12">
                        <p><strong>Mots-clés :</strong></p>
                        <div id="view_mots_cles" class="mb-3"></div>
                    </div>

                    <div class="col-md-12">
                        <p><strong>Résumé :</strong></p>
                        <div id="view_resume" class="p-3 bg-light rounded"></div>
                    </div>

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Document :</strong>
                                <a id="view_document_link" href="" target="_blank" class="btn btn-sm btn-primary ms-2">
                                    <i class="bi bi-download"></i> Télécharger le document
                                </a>
                            </div>
                            <div>
                                <span class="text-muted">
                                    <i class="bi bi-eye"></i> <span id="view_consultations">0</span> consultations
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>


<script>
function editTravail(id, titre, typeDocument, typeAuteur, nomAuteur, departementId, specialisationId, anneeAcademiqueId, motsCles, resume, estPublic) {
    // Remplir le formulaire avec les données
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_titre').value = titre;
    document.getElementById('edit_type_document').value = typeDocument;
    document.getElementById('edit_type_auteur').value = typeAuteur;
    document.getElementById('edit_nom_auteur').value = nomAuteur;
    document.getElementById('edit_departement_id').value = departementId;
    
    // Charger les spécialisations puis sélectionner celle du travail
    loadSpecialisationsEdit(departementId).then(() => {
        document.getElementById('edit_specialisation_id').value = specialisationId;
    });
    
    document.getElementById('edit_annee_academique_id').value = anneeAcademiqueId;
    document.getElementById('edit_mots_cles').value = motsCles;
    document.getElementById('edit_resume').value = resume;
    document.getElementById('edit_est_public').checked = estPublic == 1;

    // Afficher le modal
    new bootstrap.Modal(document.getElementById('editTravailModal')).show();
}

// Charger les spécialisations en fonction du département
function loadSpecialisations(departementId) {
    if (!departementId) return;
    
    fetch(`controller/get_specialisations.php?departement_id=${departementId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('specialisation_id');
            select.innerHTML = '<option value="">Sélectionner une spécialisation...</option>';
            data.forEach(spec => {
                select.innerHTML += `<option value="${spec.idSpecialisation}">${spec.designation}</option>`;
            });
        })
        .catch(error => console.error('Erreur:', error));
}

// Ajoutez cette fonction après votre fonction loadSpecialisations
function loadSpecialisationsEdit(departementId) {
    if (!departementId) return Promise.resolve();
    
    return fetch(`controller/get_specialisations.php?departement_id=${departementId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('edit_specialisation_id');
            select.innerHTML = '<option value="">Sélectionner une spécialisation...</option>';
            data.forEach(spec => {
                select.innerHTML += `<option value="${spec.idSpecialisation}">${spec.designation}</option>`;
            });
        })
        .catch(error => {
            console.error('Erreur:', error);
            return Promise.reject(error);
        });
}
</script>

<script>
function viewTravail(id) {
    // Charger les détails du travail via une requête AJAX
    fetch(`controller/get_travail_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Remplir les champs de la modal avec les données
            document.getElementById('view_titre').textContent = data.titre;
            document.getElementById('view_type_document').textContent = data.type_document;
            document.getElementById('view_nom_auteur').textContent = data.nom_auteur;
            document.getElementById('view_type_auteur').textContent = data.type_auteur;
            document.getElementById('view_departement').textContent = data.designationDepartement;
            document.getElementById('view_specialisation').textContent = data.specialisation;
            document.getElementById('view_annee').textContent = data.annee;
            document.getElementById('view_date_depot').textContent = new Date(data.date_depot).toLocaleDateString('fr-FR');
            
            // Statut avec badge
            const statutElement = document.getElementById('view_statut');
            statutElement.innerHTML = `<span class="badge ${data.statut === 'Validé' ? 'bg-success' : 'bg-warning'}">${data.statut}</span>`;
            
            // Visibilité
            document.getElementById('view_visibilite').textContent = data.est_public ? 'Public' : 'Privé';
            
            // Mots-clés avec badges
            const motsClesElement = document.getElementById('view_mots_cles');
            motsClesElement.innerHTML = data.mots_cles.split(',')
                .map(mot => `<span class="badge bg-secondary me-1">${mot.trim()}</span>`)
                .join('');
            
            document.getElementById('view_resume').textContent = data.resume;
            
            // Lien de téléchargement
            const documentLink = document.getElementById('view_document_link');
            documentLink.href = data.fichier_path;
            
            // Statistiques de consultation
            if (data.stats_consultations) {
                document.getElementById('view_consultations').textContent = data.stats_consultations.total || 0;
            }

            // Afficher la modal
            new bootstrap.Modal(document.getElementById('viewTravailModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les détails du travail'
            });
        });
}


function deleteTravail(id) {
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
            window.location.href = 'controller/delete_travail.php?id=' + id;
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>