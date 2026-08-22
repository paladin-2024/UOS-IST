<?php
include "./views/include/header.php";
$universite = new Universite();

// Récupérer les filtres
$anneeId = isset($_GET['annee']) ? $_GET['annee'] : ($currentYear ? $currentYear['idannee_acad'] : 0);
$type = isset($_GET['type']) ? $_GET['type'] : '';
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les données pour les filtres
$academicYears = $universite->getAcademicYears();
$departements = []; // $universite->getAllDepartments(); // Table departement n'existe pas

// Construire les filtres pour la requête
$filters = [
'annee_academique_id' => $anneeId,
'type_document' => $type,
'statut' => $statut
];

// Récupérer les travaux avec les filtres
$travaux = $universite->getTravaux($search, $filters);

// Calculer les statistiques
// Modifier le calcul des statistiques pour inclure les livres et cours
$stats = [
    'total_travaux' => count($travaux),
    'total_valides' => count(array_filter($travaux, fn($t) => $t['statut'] === 'Validé')),
    'total_en_attente' => count(array_filter($travaux, fn($t) => $t['statut'] === 'En attente')),
    'total_theses' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Thèse')),
    'total_memoires' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Mémoire')),
    'total_articles' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Article scientifique')),
    'total_rapports' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Rapport de stage')),
    'total_projets' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Projet tutoré')),
    'total_livres' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Livre')),
    'total_cours' => count(array_filter($travaux, fn($t) => $t['type_document'] === 'Cours'))
];
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>VALIDATION DES TRAVAUX DE LA BIBLIOTHEQUE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Suivi des travaux</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistiques en cards -->
        <div class="row">
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total des travaux</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['total_travaux'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux validés</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['total_valides'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">En attente</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['total_en_attente'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filtres de recherche</h5>
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <select name="annee" class="form-select">
                            <option value="">Toutes les années</option>
                            <?php foreach ($academicYears as $year): ?>
                            <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId == $year['idannee_acad'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['designation']) ?>
                                <?php if ($currentYear && $year['idannee_acad'] == $currentYear['idannee_acad']): ?>
                                        (En cours)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    

                    <div class="col-md-3">
                    <select name="type" class="form-select">
                            <option value="">Tous les types</option>
                            <option value="Thèse" <?= $type === 'Thèse' ? 'selected' : '' ?>>Thèses</option>
                            <option value="Mémoire" <?= $type === 'Mémoire' ? 'selected' : '' ?>>Mémoires</option>
                            <option value="Article scientifique" <?= $type === 'Article scientifique' ? 'selected' : '' ?>>Articles</option>
                            <option value="Rapport de stage" <?= $type === 'Rapport de stage' ? 'selected' : '' ?>>Rapports</option>
                            <option value="Projet tutoré" <?= $type === 'Projet tutoré' ? 'selected' : '' ?>>Projets</option>
                            <option value="Livre" <?= $type === 'Livre' ? 'selected' : '' ?>>Livres</option>
                            <option value="Cours" <?= $type === 'Cours' ? 'selected' : '' ?>>Cours</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                    <select name="statut" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="Validé" <?= $statut === 'Validé' ? 'selected' : '' ?>>Validés</option>
                            <option value="En attente" <?= $statut === 'En attente' ? 'selected' : '' ?>>En attente</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des travaux -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Liste des travaux déposés</h5>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date dépôt</th>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Auteur</th>
                                <th>Orientation</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($travaux as $travail): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($travail['date_depot'])) ?></td>
                                    <td>
                                        <span class="badge <?= $travail['type_class'] ?>">
                                            <?= $travail['type_document'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($travail['titre']) ?></td>
                                    <td><?= htmlspecialchars($travail['nom_auteur']) ?></td>
                                    <td><?= htmlspecialchars($travail['designationOrientation'] ?? 'Non spécifiée') ?></td>
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
                                        <?php if ($travail['statut'] === 'En attente'): ?>
                                            <button class="btn btn-sm btn-primary" onclick="validerTravail(<?= $travail['id'] ?>)">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>


<!-- Modal pour visualiser un travail -->
<div class="modal fade" id="viewTravailModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Détails du Travail Scientifique</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="row g-3">
<div class="col-md-6">
<h4 id="view_titre" class="text-primary"></h4>

                        <p><strong>Type de document :</strong> <span id="view_type_document"></span></p>
    <p><strong>Auteur :</strong> <span id="view_nom_auteur"></span> (<span id="view_type_auteur"></span>)</p>
<p><strong>Orientation :</strong> <span id="view_orientation"></span></p>
<p><strong>Spécialisation :</strong> <span id="view_specialisation"></span></p>
<p><strong>Année académique :</strong> <span id="view_annee"></span></p>
<p><strong>Date de dépôt :</strong> <span id="view_date_depot"></span></p>
    <p><strong>Statut :</strong> <span id="view_statut"></span></p>
                        <p><strong>Visibilité :</strong> <span id="view_visibilite"></span></p>

<p><strong>Mots-clés :</strong></p>
<div id="view_mots_cles" class="mb-3"></div>

<p><strong>Résumé :</strong></p>
    <div id="view_resume" class="p-3 bg-light rounded"></div>

    <div class="d-flex justify-content-between align-items-center mt-3">
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

<div class="col-md-6">
<div class="border rounded p-2" style="height: 600px;">
<embed id="view_pdf_embed" src="" type="application/pdf" width="100%" height="100%" />
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
function validerTravail(id) {
    Swal.fire({
        title: 'Validation du travail',
        text: "Voulez-vous valider ce travail scientifique ?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, valider',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('controller/valider_travail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Le travail a été validé avec succès.'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
            });
        }
    });
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
            document.getElementById('view_orientation').textContent = data.designationOrientation || 'Non spécifiée';
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
            
            // Lien de téléchargement et embed PDF
            const documentLink = document.getElementById('view_document_link');
            documentLink.href = data.fichier_path;
            const pdfEmbed = document.getElementById('view_pdf_embed');
            pdfEmbed.src = data.fichier_path + '#view=FitH,top';
            
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

</script>

<?php include "./views/include/footer.php"; ?>