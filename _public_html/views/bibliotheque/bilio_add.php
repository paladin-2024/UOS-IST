<?php
include "./views/include/header.php";
require_once dirname(__DIR__) . '/../config/Connexion.php';
$db = Connexion::getInstance()->getPDO();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les années académiques
$queryAcademicYears = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAcademicYears = $db->prepare($queryAcademicYears);
$stmtAcademicYears->execute();
$academicYears = $stmtAcademicYears->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les spécialisations pour référence
$queryAllSpecialisations = "SELECT * FROM specialisation ORDER BY designation";
$stmtAllSpecialisations = $db->prepare($queryAllSpecialisations);
$stmtAllSpecialisations->execute();
$allSpecialisations = $stmtAllSpecialisations->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les travaux avec recherche si nécessaire
$queryTravaux = "SELECT t.*, 
                o.designationOrientation, 
                s.designation as specialisation,
                aa.designation as annee
            FROM travaux_scientifiques t
            LEFT JOIN orientation o ON t.orientation_id = o.idorientation
            LEFT JOIN specialisation s ON t.specialisation_id = s.idSpecialisation
            LEFT JOIN annee_acad aa ON t.annee_academique_id = aa.idannee_acad
            WHERE 1=1";

if (!empty($search)) {
    $queryTravaux .= " AND (t.titre LIKE :search 
                   OR t.mots_cles LIKE :search 
                   OR t.resume LIKE :search
                   OR t.nom_auteur LIKE :search
                   OR o.designationOrientation LIKE :search
                   OR t.universiteThese LIKE :search
                   OR t.faculteThese LIKE :search
                   OR t.specialisationThese LIKE :search)";
}

$queryTravaux .= " ORDER BY t.date_depot DESC";

$stmtTravaux = $db->prepare($queryTravaux);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmtTravaux->bindParam(':search', $searchTerm);
}

$stmtTravaux->execute();
$travaux = $stmtTravaux->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>BIBLIOTHEQUE NUMERIQUE</h1>
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
                                        <th>Orientation</th>
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
                                            <td><?= htmlspecialchars($travail['designationOrientation'] ?? '') ?></td>
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

                                                <button class="btn btn-sm btn-primary" onclick="editTravail(
    <?= $travail['id'] ?>, 
    '<?= addslashes(htmlspecialchars($travail['titre'], ENT_QUOTES, 'UTF-8')) ?>', 
    '<?= $travail['type_document'] ?>', 
    '<?= $travail['type_auteur'] ?>', 
    '<?= addslashes(htmlspecialchars($travail['nom_auteur'], ENT_QUOTES, 'UTF-8')) ?>', 
    <?= $travail['orientation_id'] ?? 'null' ?>, 
    <?= $travail['specialisation_id'] ?? 'null' ?>, 
    <?= $travail['annee_academique_id'] ?? 'null' ?>, 
    '<?= addslashes(htmlspecialchars($travail['mots_cles'] ?? '', ENT_QUOTES, 'UTF-8')) ?>', 
    '<?= addslashes(htmlspecialchars($travail['resume'] ?? '', ENT_QUOTES, 'UTF-8')) ?>', 
    <?= $travail['est_public'] ? 1 : 0 ?>, 
    '<?= isset($travail['anneeThese']) ? addslashes(htmlspecialchars($travail['anneeThese'], ENT_QUOTES, 'UTF-8')) : '' ?>', 
    '<?= isset($travail['universiteThese']) ? addslashes(htmlspecialchars($travail['universiteThese'], ENT_QUOTES, 'UTF-8')) : '' ?>', 
    '<?= isset($travail['faculteThese']) ? addslashes(htmlspecialchars($travail['faculteThese'], ENT_QUOTES, 'UTF-8')) : '' ?>', 
    '<?= isset($travail['specialisationThese']) ? addslashes(htmlspecialchars($travail['specialisationThese'], ENT_QUOTES, 'UTF-8')) : '' ?>'
)">
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
                <form id="travailForm" action="controller/create_biblio.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <!-- Titre -->
                        <div class="col-md-12">
                            <label class="form-label">Titre du travail</label>
                            <input type="text" name="titre" class="form-control" required>
                        </div>

                        <!-- Type de document -->
                        <div class="col-md-6">
                            <label class="form-label">Type de document</label>
                            <select name="type_document" id="type_document" class="form-select" required onchange="toggleTheseFields()">
                                <option value="">Sélectionner...</option>
                                <option value="Mémoire">Mémoire M2</option>
                                <option value="Mémoire Master Complémentaire">Mémoire Master Complémentaire</option>
                                <option value="Thèse">Thèse</option>
                                <option value="Rapport de stage">Rapport de stage</option>
                                <option value="Article scientifique">Article scientifique</option>
                                <option value="Projet tutoré">Projet tutoré</option>
                                <option value="Livre">Livre</option>
                                <option value="Cours">Cours</option>
                            </select>
                        </div>

                        <!-- Type d'auteur et nom de l'auteur -->
                        <div class="col-md-6">
                            <label class="form-label">Type d'auteur</label>
                            <select name="type_auteur" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Etudiant">Étudiant</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nom de l'auteur</label>
                            <input type="text" name="nom_auteur" class="form-control" required>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="annee_academique_id" id="annee_academique_id" class="form-select" required onchange="loadSections(this.value)">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Section -->
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <select name="section_id" id="section_id" class="form-select" required onchange="loadOrientations(this.value)" disabled>
                                <option value="">Sélectionner d'abord une année académique</option>
                            </select>
                        </div>

                        <!-- Orientation -->
                        <div class="col-md-6">
                            <label class="form-label">Orientation</label>
                            <select name="orientation_id" id="orientation_id" class="form-select" required onchange="loadSpecialisations(this.value)" disabled>
                                <option value="">Sélectionner d'abord une section</option>
                            </select>
                        </div>

                        <!-- Spécialisation -->
                        <div class="col-md-6">
                            <label class="form-label">Spécialisation</label>
                            <select name="specialisation_id" id="specialisation_id" class="form-select" required disabled>
                                <option value="">Sélectionner d'abord une orientation</option>
                            </select>
                        </div>

                        <!-- Champs spécifiques aux thèses - initialement cachés -->
                        <div id="these_fields" style="display: none;">
                            <div class="col-md-6">
                                <label class="form-label">Année de soutenance de la thèse</label>
                                <input type="text" name="anneeThese" class="form-control" placeholder="Ex: 2023">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Université de soutenance</label>
                                <input type="text" name="universiteThese" class="form-control" placeholder="Ex: Université de Paris">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Faculté/École pour la thèse</label>
                                <input type="text" name="faculteThese" class="form-control" placeholder="Ex: Faculté des Sciences">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Spécialisation de la thèse</label>
                                <input type="text" name="specialisationThese" class="form-control" placeholder="Ex: Informatique">
                            </div>
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
                            <input type="file" name="document" class="form-control" accept=".pdf">
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
                <form id="editTravailForm" action="controller/update_biblio.php" method="POST" enctype="multipart/form-data">
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
                            <select name="type_document" id="edit_type_document" class="form-select" required onchange="toggleEditTheseFields()">
                                <option value="">Sélectionner...</option>
                                <option value="Mémoire">Mémoire M2</option>
                                <option value="Mémoire Master Complémentaire">Mémoire Master Complémentaire</option>
                                <option value="Thèse">Thèse</option>
                                <option value="Rapport de stage">Rapport de stage</option>
                                <option value="Article scientifique">Article scientifique</option>
                                <option value="Projet tutoré">Projet tutoré</option>
                                <option value="Livre">Livre</option>
                                <option value="Cours">Cours</option>
                            </select>
                        </div>

                        <!-- Type d'auteur et nom de l'auteur -->
                        <div class="col-md-6">
                            <label class="form-label">Type d'auteur</label>
                            <select name="type_auteur" id="edit_type_auteur" class="form-select" required>
                                <option value="">Sélectionner...</option>
                                <option value="Etudiant">Étudiant</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nom de l'auteur</label>
                            <input type="text" name="nom_auteur" id="edit_nom_auteur" class="form-control" required>
                        </div>

                        <!-- Année académique -->
                        <div class="col-md-6">
                            <label class="form-label">Année académique</label>
                            <select name="annee_academique_id" id="edit_annee_academique_id" class="form-select" required onchange="loadEditSections(this.value)">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Section -->
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <select name="section_id" id="edit_section_id" class="form-select" required onchange="loadEditOrientations(this.value)">
                                <option value="">Sélectionner d'abord une année académique</option>
                            </select>
                        </div>

                        <!-- Orientation -->
                        <div class="col-md-6">
                            <label class="form-label">Orientation</label>
                            <select name="orientation_id" id="edit_orientation_id" class="form-select" required onchange="loadEditSpecialisations(this.value)">
                                <option value="">Sélectionner d'abord une section</option>
                            </select>
                        </div>

                        <!-- Spécialisation -->
                        <div class="col-md-6">
                            <label class="form-label">Spécialisation</label>
                            <select name="specialisation_id" id="edit_specialisation_id" class="form-select" required>
                                <option value="">Sélectionner d'abord une orientation</option>
                            </select>
                        </div>

                        <!-- Champs spécifiques aux thèses - visibilité contrôlée par JavaScript -->
                        <div id="edit_these_fields" style="display: none;">
                            <div class="col-md-6">
                                <label class="form-label">Année de soutenance de la thèse</label>
                                <input type="text" name="anneeThese" id="edit_anneeThese" class="form-control" placeholder="Ex: 2023">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Université de soutenance</label>
                                <input type="text" name="universiteThese" id="edit_universiteThese" class="form-control" placeholder="Ex: Université de Paris">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Faculté/École</label>
                                <input type="text" name="faculteThese" id="edit_faculteThese" class="form-control" placeholder="Ex: Faculté des Sciences">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Spécialisation de la thèse</label>
                                <input type="text" name="specialisationThese" id="edit_specialisationThese" class="form-control" placeholder="Ex: Informatique">
                            </div>
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
<div class="modal fade" id="viewTravailModal" tabindex="-1" data-bs-backdrop="static">
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
                        <p><strong>Orientation :</strong> <span id="view_orientation"></span></p>
                        <p><strong>Spécialisation :</strong> <span id="view_specialisation"></span></p>
                    </div>

                    <div class="col-md-6">
                        <p><strong>Année académique :</strong> <span id="view_annee"></span></p>
                        <p><strong>Date de dépôt :</strong> <span id="view_date_depot"></span></p>
                        <p><strong>Statut :</strong> <span id="view_statut"></span></p>
                        <p><strong>Visibilité :</strong> <span id="view_visibilite"></span></p>
                    </div>

                    <!-- Informations spécifiques aux thèses -->
                    <div id="view_these_info" class="col-md-12" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informations sur la thèse</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Année de soutenance :</strong> <span id="view_anneeThese"></span></p>
                                        <p><strong>Université :</strong> <span id="view_universiteThese"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Faculté/École :</strong> <span id="view_faculteThese"></span></p>
                                        <p><strong>Spécialisation :</strong> <span id="view_specialisationThese"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
// Fonction pour afficher/masquer les champs spécifiques aux thèses
function toggleTheseFields() {
    const typeDocument = document.getElementById('type_document').value;
    const theseFields = document.getElementById('these_fields');
    
    if (typeDocument === 'Thèse') {
        theseFields.style.display = 'block';
        // Rendre les champs obligatoires
        document.querySelectorAll('#these_fields input').forEach(input => {
            input.setAttribute('required', 'required');
        });
    } else {
        theseFields.style.display = 'none';
        // Supprimer l'attribut required
        document.querySelectorAll('#these_fields input').forEach(input => {
            input.removeAttribute('required');
        });
    }
}

// Fonction pour afficher/masquer les champs spécifiques aux thèses dans le formulaire d'édition
function toggleEditTheseFields() {
    const typeDocument = document.getElementById('edit_type_document').value;
    const theseFields = document.getElementById('edit_these_fields');
    
    if (typeDocument === 'Thèse') {
        theseFields.style.display = 'block';
        // Rendre les champs obligatoires
        document.querySelectorAll('#edit_these_fields input').forEach(input => {
            input.setAttribute('required', 'required');
        });
    } else {
        theseFields.style.display = 'none';
        // Supprimer l'attribut required
        document.querySelectorAll('#edit_these_fields input').forEach(input => {
            input.removeAttribute('required');
        });
    }
}

// Charger les sections en fonction de l'année académique
function loadSections(anneeId) {
    if (!anneeId) {
        document.getElementById('section_id').innerHTML = '<option value="">Sélectionner d\'abord une année académique</option>';
        document.getElementById('section_id').disabled = true;
        document.getElementById('orientation_id').innerHTML = '<option value="">Sélectionner d\'abord une section</option>';
        document.getElementById('orientation_id').disabled = true;
        document.getElementById('specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_sections.php?annee_id=${anneeId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('section_id');
            select.innerHTML = '<option value="">Sélectionner une section...</option>';
            data.forEach(section => {
                select.innerHTML += `<option value="${section.idsection}">${section.designationSection}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

// Charger les orientations en fonction de la section
function loadOrientations(sectionId) {
    if (!sectionId) {
        document.getElementById('orientation_id').innerHTML = '<option value="">Sélectionner d\'abord une section</option>';
        document.getElementById('orientation_id').disabled = true;
        document.getElementById('specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_orientations.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('orientation_id');
            select.innerHTML = '<option value="">Sélectionner une orientation...</option>';
            data.forEach(orientation => {
                select.innerHTML += `<option value="${orientation.idorientation}">${orientation.designationOrientation}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

// Charger les spécialisations en fonction de l'orientation
function loadSpecialisations(orientationId) {
    if (!orientationId) {
        document.getElementById('specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_specialisations.php?orientation_id=${orientationId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('specialisation_id');
            select.innerHTML = '<option value="">Sélectionner une spécialisation...</option>';
            data.forEach(spec => {
                select.innerHTML += `<option value="${spec.idSpecialisation}">${spec.designation}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

// Fonctions équivalentes pour le formulaire d'édition
function loadEditSections(anneeId) {
    if (!anneeId) {
        document.getElementById('edit_section_id').innerHTML = '<option value="">Sélectionner d\'abord une année académique</option>';
        document.getElementById('edit_section_id').disabled = true;
        document.getElementById('edit_orientation_id').innerHTML = '<option value="">Sélectionner d\'abord une section</option>';
        document.getElementById('edit_orientation_id').disabled = true;
        document.getElementById('edit_specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('edit_specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_sections.php?annee_id=${anneeId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('edit_section_id');
            select.innerHTML = '<option value="">Sélectionner une section...</option>';
            data.forEach(section => {
                select.innerHTML += `<option value="${section.idsection}">${section.designationSection}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

function loadEditOrientations(sectionId) {
    if (!sectionId) {
        document.getElementById('edit_orientation_id').innerHTML = '<option value="">Sélectionner d\'abord une section</option>';
        document.getElementById('edit_orientation_id').disabled = true;
        document.getElementById('edit_specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('edit_specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_orientations.php?section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('edit_orientation_id');
            select.innerHTML = '<option value="">Sélectionner une orientation...</option>';
            data.forEach(orientation => {
                select.innerHTML += `<option value="${orientation.idorientation}">${orientation.designationOrientation}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

function loadEditSpecialisations(orientationId) {
    if (!orientationId) {
        document.getElementById('edit_specialisation_id').innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
        document.getElementById('edit_specialisation_id').disabled = true;
        return;
    }
    
    fetch(`controller/get_specialisations.php?orientation_id=${orientationId}`)
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('edit_specialisation_id');
            select.innerHTML = '<option value="">Sélectionner une spécialisation...</option>';
            data.forEach(spec => {
                select.innerHTML += `<option value="${spec.idSpecialisation}">${spec.designation}</option>`;
            });
            select.disabled = false;
        })
        .catch(error => console.error('Erreur:', error));
}

function editTravail(id, titre, typeDocument, typeAuteur, nomAuteur, orientationId, specialisationId, anneeAcademiqueId, motsCles, resume, estPublic, anneeThese, universiteThese, faculteThese, specialisationThese) {
    // Remplir le formulaire avec les données
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_titre').value = titre;
    document.getElementById('edit_type_document').value = typeDocument;
    document.getElementById('edit_type_auteur').value = typeAuteur;
    document.getElementById('edit_nom_auteur').value = nomAuteur;
    document.getElementById('edit_mots_cles').value = motsCles;
    document.getElementById('edit_resume').value = resume;
    document.getElementById('edit_est_public').checked = estPublic == 1;

    // Gérer les champs spécifiques aux thèses
    if (typeDocument === 'Thèse') {
        document.getElementById('edit_anneeThese').value = anneeThese || '';
        document.getElementById('edit_universiteThese').value = universiteThese || '';
        document.getElementById('edit_faculteThese').value = faculteThese || '';
        document.getElementById('edit_specialisationThese').value = specialisationThese || '';
        toggleEditTheseFields(); // Appeler cette fonction pour afficher les champs
    } else {
        document.getElementById('edit_these_fields').style.display = 'none';
    }

    // Charger l'année académique
    document.getElementById('edit_annee_academique_id').value = anneeAcademiqueId;
    
    // Charger les sections de manière synchrone
    loadEditSections(anneeAcademiqueId);
    
    // Utiliser setTimeout pour permettre aux sections de se charger
    setTimeout(function() {
        // Récupérer la section correspondante à l'orientation
        fetch(`controller/get_section_by_orientation.php?orientation_id=${orientationId}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.idsection) {
                    document.getElementById('edit_section_id').value = data.idsection;
                    
                    // Charger les orientations de cette section
                    loadEditOrientations(data.idsection);
                    
                    // Attendre que les orientations soient chargées
                    setTimeout(function() {
                        document.getElementById('edit_orientation_id').value = orientationId;
                        
                        // Charger les spécialisations de cette orientation
                        loadEditSpecialisations(orientationId);
                        
                        // Attendre que les spécialisations soient chargées
                        setTimeout(function() {
                            document.getElementById('edit_specialisation_id').value = specialisationId;
                            
                            // Afficher le modal une fois que tout est chargé
                            const editModal = new bootstrap.Modal(document.getElementById('editTravailModal'));
                            editModal.show();
                        }, 300);
                    }, 300);
                } else {
                    console.error('Section non trouvée pour cette orientation');
                    // Afficher quand même le modal en cas d'erreur
                    const editModal = new bootstrap.Modal(document.getElementById('editTravailModal'));
                    editModal.show();
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                // Afficher quand même le modal en cas d'erreur
                const editModal = new bootstrap.Modal(document.getElementById('editTravailModal'));
                editModal.show();
            });
    }, 100);
}


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
            document.getElementById('view_orientation').textContent = data.designationOrientation;
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

            // Informations spécifiques aux thèses
            const theseInfoSection = document.getElementById('view_these_info');
            if (data.type_document === 'Thèse' && (data.anneeThese || data.universiteThese || data.faculteThese || data.specialisationThese)) {
                document.getElementById('view_anneeThese').textContent = data.anneeThese || 'Non spécifié';
                document.getElementById('view_universiteThese').textContent = data.universiteThese || 'Non spécifié';
                document.getElementById('view_faculteThese').textContent = data.faculteThese || 'Non spécifié';
                document.getElementById('view_specialisationThese').textContent = data.specialisationThese || 'Non spécifié';
                theseInfoSection.style.display = 'block';
            } else {
                theseInfoSection.style.display = 'none';
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
            window.location.href = 'controller/delete_biblio.php?id=' + id;
        }
    });
}

// Initialiser les champs au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    toggleTheseFields();
});
</script>

<?php include "./views/include/footer.php"; ?>

