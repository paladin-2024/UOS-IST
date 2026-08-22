<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer les liens d'inscription externe
$stmt = $connexion->query("
    SELECT lie.*, 
           p.designationPromotion,
           o.designationOrientation,
           s.designationSection,
           aa.designation as annee_academique,
           COUNT(ie.id) as nb_inscriptions
    FROM liens_inscription_externe lie
    LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN annee_acad aa ON lie.annee_acad_id = aa.idannee_acad
    LEFT JOIN inscriptions_externes ie ON lie.id = ie.lien_inscription_id
    GROUP BY lie.id
    ORDER BY lie.date_creation DESC
");
$liens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions pour le formulaire
$stmt = $connexion->query("
    SELECT p.idpromotion, p.designationPromotion, p.cycle,
           o.designationOrientation,
           s.designationSection,
           aa.designation as annee_academique
    FROM promotion p
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
    WHERE aa.est_active = 1
    ORDER BY s.designationSection, o.designationOrientation, p.designationPromotion
");
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années académiques
$stmt = $connexion->query("SELECT * FROM annee_acad WHERE est_active = 1 ORDER BY designation DESC");
$annees_acad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les documents obligatoires par cycle
$stmt = $connexion->query("
    SELECT * FROM documents_obligatoires 
    ORDER BY cycle, designation
");
$documents_obligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$total = count($liens);
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$liensPage = array_slice($liens, $offset, $perPage);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Liens d'Inscription Externe</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item">Étudiants</li>
                <li class="breadcrumb-item active">Liens d'Inscription Externe</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <!-- Messages de succès et d'erreur -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['lien_url'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-link-45deg me-2"></i>
                        <strong>Lien créé :</strong> 
                        <a href="<?= htmlspecialchars($_SESSION['lien_url']) ?>" target="_blank" class="alert-link">
                            <?= htmlspecialchars($_SESSION['lien_url']) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="copierLien('<?= htmlspecialchars($_SESSION['lien_url']) ?>')">
                            <i class="bi bi-clipboard"></i> Copier
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['lien_url']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Liste des Liens d'Inscription</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLienModal">
                                <i class="bi bi-plus-circle me-1"></i> Créer un lien
                            </button>
                        </div>

                        <?php if (empty($liens)): ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun lien d'inscription externe n'a été créé. Commencez par en créer un.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Référence</th>
                                            <th>Titre</th>
                                            <th>Promotion</th>
                                            <th>Période</th>
                                            <th>Inscriptions</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($liensPage as $index => $lien): ?>
                                            <tr>
                                                <td><?= $offset + $index + 1 ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($lien['reference']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($lien['titre']) ?></td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($lien['designationSection']) ?></small><br>
                                                    <strong><?= htmlspecialchars($lien['designationOrientation']) ?></strong><br>
                                                    <span class="badge bg-info"><?= htmlspecialchars($lien['designationPromotion']) ?></span>
                                                </td>
                                                <td>
                                                    <small>Du <?= date('d/m/Y H:i', strtotime($lien['date_debut'])) ?></small><br>
                                                    <small>Au <?= date('d/m/Y H:i', strtotime($lien['date_fin'])) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $lien['nb_inscriptions'] ?></span>
                                                    <?php if ($lien['max_inscriptions']): ?>
                                                        / <?= $lien['max_inscriptions'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $now = new DateTime();
                                                    $debut = new DateTime($lien['date_debut']);
                                                    $fin = new DateTime($lien['date_fin']);
                                                    
                                                    if (!$lien['est_actif']): ?>
                                                        <span class="badge bg-danger">Désactivé</span>
                                                    <?php elseif ($now < $debut): ?>
                                                        <span class="badge bg-warning">Programmé</span>
                                                    <?php elseif ($now > $fin): ?>
                                                        <span class="badge bg-secondary">Expiré</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Actif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-info" onclick="voirLien('<?= $lien['url_complete'] ?>')" title="Voir le lien">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-success" onclick="copierLien('<?= $lien['url_complete'] ?>')" title="Copier le lien">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editLienModal" 
                                                            onclick="chargerLienPourEdition(<?= $lien['id'] ?>)" title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-warning" onclick="voirInscriptions(<?= $lien['id'] ?>)" title="Voir les inscriptions">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger" onclick="confirmerSuppression(<?= $lien['id'] ?>)" title="Supprimer">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($pages > 1): ?>
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?view=etudiants/liens_inscription_externe&page=<?= $page - 1 ?>" aria-label="Précédent">
                                                <span aria-hidden="true">«</span>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?view=etudiants/liens_inscription_externe&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?view=etudiants/liens_inscription_externe&page=<?= $page + 1 ?>" aria-label="Suivant">
                                                <span aria-hidden="true">»</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour créer un lien -->
<div class="modal fade" id="addLienModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Créer un lien d'inscription externe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/creer_lien_inscription.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="titre" class="form-label">Titre du lien</label>
                                <input type="text" class="form-control" id="titre" name="titre" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reference" class="form-label">Référence</label>
                                <input type="text" class="form-control" id="reference" name="reference" required>
                                <div class="form-text">Identifiant unique pour ce lien</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="promotion_id" class="form-label">Promotion</label>
                                <select class="form-select" id="promotion_id" name="promotion_id" required>
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>">
                                            <?= htmlspecialchars($promo['designationSection']) ?> - 
                                            <?= htmlspecialchars($promo['designationOrientation']) ?> - 
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                            (<?= htmlspecialchars($promo['cycle']) ?> cycle)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="annee_acad_id" class="form-label">Année académique</label>
                                <select class="form-select" id="annee_acad_id" name="annee_acad_id" required>
                                    <?php foreach ($annees_acad as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>"><?= htmlspecialchars($annee['designation']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="datetime-local" class="form-control" id="date_debut" name="date_debut" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Date de fin</label>
                                <input type="datetime-local" class="form-control" id="date_fin" name="date_fin" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_inscriptions" class="form-label">Nombre max d'inscriptions</label>
                                <input type="number" class="form-control" id="max_inscriptions" name="max_inscriptions" min="1">
                                <div class="form-text">Laissez vide pour illimité</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="couleur_theme" class="form-label">Couleur du thème</label>
                                <input type="color" class="form-control form-control-color" id="couleur_theme" name="couleur_theme" value="#007bff">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="utiliser_docs_defaut" name="utiliser_docs_defaut" value="1" checked>
                            <label class="form-check-label" for="utiliser_docs_defaut">
                                Utiliser les documents obligatoires par défaut
                            </label>
                        </div>
                    </div>
                    
                    <div id="documents_personnalises" style="display: none;">
                        <h6>Documents requis personnalisés</h6>
                        <div id="liste_documents_personnalises">
                            <!-- Les documents personnalisés seront ajoutés ici -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterDocumentPersonnalise()">
                            <i class="bi bi-plus"></i> Ajouter un document
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_accueil" class="form-label">Message d'accueil</label>
                        <textarea class="form-control" id="message_accueil" name="message_accueil" rows="3" placeholder="Message affiché sur la page d'inscription"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_succes" class="form-label">Message de succès</label>
                        <textarea class="form-control" id="message_succes" name="message_succes" rows="2" placeholder="Message affiché après inscription réussie"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo_personnalise" class="form-label">Logo personnalisé</label>
                        <input type="file" class="form-control" id="logo_personnalise" name="logo_personnalise" accept="image/*">
                        <div class="form-text">Optionnel - Logo affiché sur la page d'inscription</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer le lien</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour modifier un lien -->
<div class="modal fade" id="editLienModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le lien d'inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/modifier_lien_inscription.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <!-- Contenu similaire au modal de création, avec les champs pré-remplis -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_titre" class="form-label">Titre du lien</label>
                                <input type="text" class="form-control" id="edit_titre" name="titre" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_reference" class="form-label">Référence</label>
                                <input type="text" class="form-control" id="edit_reference" name="reference" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_debut" class="form-label">Date de début</label>
                                <input type="datetime-local" class="form-control" id="edit_date_debut" name="date_debut" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_fin" class="form-label">Date de fin</label>
                                <input type="datetime-local" class="form-control" id="edit_date_fin" name="date_fin" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_max_inscriptions" class="form-label">Nombre max d'inscriptions</label>
                                <input type="number" class="form-control" id="edit_max_inscriptions" name="max_inscriptions" min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1">
                                    <label class="form-check-label" for="edit_est_actif">
                                        Lien actif
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'affichage des documents personnalisés
    const checkboxDocsDefaut = document.getElementById('utiliser_docs_defaut');
    const divDocsPersonnalises = document.getElementById('documents_personnalises');
    
    checkboxDocsDefaut.addEventListener('change', function() {
        if (this.checked) {
            divDocsPersonnalises.style.display = 'none';
        } else {
            divDocsPersonnalises.style.display = 'block';
        }
    });
});

function voirLien(url) {
    window.open(url, '_blank');
}

function copierLien(url) {
    navigator.clipboard.writeText(url).then(function() {
        Swal.fire({
            title: 'Succès!',
            text: 'Le lien a été copié dans le presse-papiers',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

function voirInscriptions(lienId) {
    window.location.href = `?view=etudiants/inscriptions_externes&lien_id=${lienId}`;
}

function confirmerSuppression(id) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera définitivement ce lien et toutes les inscriptions associées!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/supprimer_lien_inscription.php?id=${id}`;
        }
    });
}

function chargerLienPourEdition(id) {
    // Charger les données du lien via AJAX pour pré-remplir le formulaire de modification
    fetch(`controller/get_lien_inscription.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lien = data.lien;
                document.getElementById('edit_id').value = lien.id;
                document.getElementById('edit_titre').value = lien.titre;
                document.getElementById('edit_reference').value = lien.reference;
                document.getElementById('edit_description').value = lien.description || '';
                document.getElementById('edit_date_debut').value = lien.date_debut.replace(' ', 'T');
                document.getElementById('edit_date_fin').value = lien.date_fin.replace(' ', 'T');
                document.getElementById('edit_max_inscriptions').value = lien.max_inscriptions || '';
                document.getElementById('edit_est_actif').checked = lien.est_actif == 1;
            }
        });
}

let compteurDocsPersonnalises = 0;

function ajouterDocumentPersonnalise() {
    compteurDocsPersonnalises++;
    const html = `
        <div class="card mb-2" id="doc_${compteurDocsPersonnalises}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="docs_personnalises[${compteurDocsPersonnalises}][designation]" placeholder="Nom du document" required>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="docs_personnalises[${compteurDocsPersonnalises}][est_obligatoire]">
                            <option value="1">Obligatoire</option>
                            <option value="0">Optionnel</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="supprimerDocumentPersonnalise(${compteurDocsPersonnalises})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="docs_personnalises[${compteurDocsPersonnalises}][description]" placeholder="Description (optionnel)">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control" name="docs_personnalises[${compteurDocsPersonnalises}][delai_jours]" placeholder="Délai (jours)" min="0">
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('liste_documents_personnalises').insertAdjacentHTML('beforeend', html);
}

function supprimerDocumentPersonnalise(id) {
    document.getElementById(`doc_${id}`).remove();
}
</script>

<?php include "./views/include/footer.php"; ?>