<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

$lien_id = isset($_GET['lien_id']) ? intval($_GET['lien_id']) : null;

// Récupérer les inscriptions externes
$where_clause = "";
$params = [];

if ($lien_id) {
    $where_clause = "WHERE ie.lien_inscription_id = ?";
    $params[] = $lien_id;
}

$stmt = $connexion->prepare("    SELECT ie.*, 
           lie.titre as lien_titre,
           lie.reference as lien_reference,
           p.\"designationPromotion\",
           o.\"designationOrientation\",
           s.\"designationSection\",
           COUNT(die.id) as nb_documents
    FROM inscriptions_externes ie
    LEFT JOIN liens_inscription_externe lie ON ie.lien_inscription_id = lie.id
    LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN documents_inscription_externe die ON ie.id = die.inscription_externe_id
   $where_clause
    GROUP BY ie.id
    ORDER BY ie.date_soumission DESC
");
$stmt->execute($params);
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les liens pour le filtre
$stmt = $connexion->query("
    SELECT lie.id, lie.titre, lie.reference,
           p.\"designationPromotion\",
           COUNT(ie.id) as nb_inscriptions
    FROM liens_inscription_externe lie
    LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
    LEFT JOIN inscriptions_externes ie ON lie.id = ie.lien_inscription_id
    GROUP BY lie.id
    ORDER BY lie.date_creation DESC
");
$liens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 15;
$total = count($inscriptions);
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$inscriptionsPage = array_slice($inscriptions, $offset, $perPage);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Inscriptions Externes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item">Étudiants</li>
                <li class="breadcrumb-item"><a href="?view=etudiants/liens_inscription_externe">Liens d'Inscription</a></li>
                <li class="breadcrumb-item active">Inscriptions Reçues</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">
                                Inscriptions Reçues
                                <?php if ($lien_id): ?>
                                    <span class="badge bg-primary"><?= count($inscriptions) ?> inscription(s)</span>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex gap-2">
                                <select class="form-select" onchange="filtrerParLien(this.value)" style="width: auto;">
                                    <option value="">Tous les liens</option>
                                    <?php foreach ($liens as $lien): ?>
                                        <option value="<?= $lien['id'] ?>" <?= $lien_id == $lien['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lien['reference']) ?> - <?= htmlspecialchars($lien['titre']) ?>
                                            (<?= $lien['nb_inscriptions'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-success" onclick="exporterInscriptions()">
                                    <i class="bi bi-download me-1"></i> Exporter
                                </button>
                            </div>
                        </div>

                        <?php if (empty($inscriptions)): ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucune inscription externe n'a été reçue.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Référence</th>
                                            <th>Candidat</th>
                                            <th>Contact</th>
                                            <th>Lien d'inscription</th>
                                            <th>Documents</th>
                                            <th>Date</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inscriptionsPage as $index => $inscription): ?>
                                            <tr>
                                                <td><?= $offset + $index + 1 ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($inscription['reference_inscription']) ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($inscription['nom'] . ' ' . $inscription['postnom'] . ' ' . $inscription['prenom']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($inscription['lieu_naissance']) ?>, 
                                                        <?= date('d/m/Y', strtotime($inscription['date_naissance'])) ?>
                                                    </small><br>
                                                    <span class="badge bg-<?= $inscription['sexe'] == 'M' ? 'primary' : 'pink' ?>"><?= $inscription['sexe'] == 'M' ? 'Masculin' : 'Féminin' ?></span>
                                                </td>
                                                <td>
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($inscription['email']) ?><br>
                                                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($inscription['telephone']) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($inscription['lien_reference']) ?></small><br>
                                                    <strong><?= htmlspecialchars($inscription['designationSection']) ?></strong><br>
                                                    <?= htmlspecialchars($inscription['designationOrientation']) ?><br>
                                                    <span class="badge bg-info"><?= htmlspecialchars($inscription['designationPromotion']) ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $inscription['nb_documents'] ?></span>
                                                    <br><small>document(s)</small>
                                                </td>
                                                <td>
                                                    <?= date('d/m/Y H:i', strtotime($inscription['date_soumission'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $badges = [
                                                        'En cours' => 'warning',
                                                        'Complète' => 'info',
                                                        'Validée' => 'success',
                                                        'Rejetée' => 'danger'
                                                    ];
                                                    $badge_class = $badges[$inscription['statut']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $badge_class ?>"><?= htmlspecialchars($inscription['statut']) ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detailsModal" 
                                                            onclick="afficherDetails(this)" 
                                                            data-nom="<?= htmlspecialchars($inscription['nom'] . ' ' . $inscription['postnom'] . ' ' . $inscription['prenom']) ?>"
                                                            data-email="<?= htmlspecialchars($inscription['email']) ?>"
                                                            data-telephone="<?= htmlspecialchars($inscription['telephone']) ?>"
                                                            data-lieu-naissance="<?= htmlspecialchars($inscription['lieu_naissance']) ?>"
                                                            data-date-naissance="<?= date('d/m/Y', strtotime($inscription['date_naissance'])) ?>"
                                                            data-sexe="<?= $inscription['sexe'] == 'M' ? 'Masculin' : 'Féminin' ?>"
                                                            data-nationalite="<?= htmlspecialchars($inscription['nationalite']) ?>"
                                                            data-adresse="<?= htmlspecialchars($inscription['adresse_complete']) ?>"
                                                            data-personne-contact="<?= htmlspecialchars($inscription['personne_contact'] ?? '') ?>"
                                                            data-telephone-contact="<?= htmlspecialchars($inscription['telephone_contact'] ?? '') ?>"
                                                            data-reference="<?= htmlspecialchars($inscription['reference_inscription']) ?>"
                                                            data-date-soumission="<?= date('d/m/Y H:i', strtotime($inscription['date_soumission'])) ?>"
                                                            data-statut="<?= htmlspecialchars($inscription['statut']) ?>"
                                                            title="Voir les détails">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#documentsModal" 
                                                            onclick="afficherDocuments(<?= $inscription['id'] ?>)" title="Voir les documents">
                                                            <i class="bi bi-file-earmark-text"></i>
                                                        </button>
                                                        <?php if ($inscription['statut'] != 'Validée'): ?>
                                                            <button type="button" class="btn btn-success" onclick="validerInscription(<?= $inscription['id'] ?>)" title="Valider">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($inscription['statut'] != 'Rejetée'): ?>
                                                            <button type="button" class="btn btn-danger" onclick="rejeterInscription(<?= $inscription['id'] ?>)" title="Rejeter">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
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
                                            <a class="page-link" href="?view=etudiants/inscriptions_externes&lien_id=<?= $lien_id ?>&page=<?= $page - 1 ?>" aria-label="Précédent">
                                                <span aria-hidden="true">«</span>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?view=etudiants/inscriptions_externes&lien_id=<?= $lien_id ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?view=etudiants/inscriptions_externes&lien_id=<?= $lien_id ?>&page=<?= $page + 1 ?>" aria-label="Suivant">
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

<!-- Modal pour voir les détails -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de l'inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations personnelles</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Nom complet :</strong></td><td id="detail_nom"></td></tr>
                            <tr><td><strong>Email :</strong></td><td id="detail_email"></td></tr>
                            <tr><td><strong>Téléphone :</strong></td><td id="detail_telephone"></td></tr>
                            <tr><td><strong>Date de naissance :</strong></td><td id="detail_date_naissance"></td></tr>
                            <tr><td><strong>Lieu de naissance :</strong></td><td id="detail_lieu_naissance"></td></tr>
                            <tr><td><strong>Sexe :</strong></td><td id="detail_sexe"></td></tr>
                            <tr><td><strong>Nationalité :</strong></td><td id="detail_nationalite"></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations de contact</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Adresse :</strong></td><td id="detail_adresse"></td></tr>
                            <tr><td><strong>Personne à contacter :</strong></td><td id="detail_personne_contact"></td></tr>
                            <tr><td><strong>Téléphone contact :</strong></td><td id="detail_telephone_contact"></td></tr>
                        </table>
                        
                        <h6 class="text-primary mt-3">Informations d'inscription</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Référence :</strong></td><td id="detail_reference"></td></tr>
                            <tr><td><strong>Date de soumission :</strong></td><td id="detail_date_soumission"></td></tr>
                            <tr><td><strong>Statut :</strong></td><td id="detail_statut"></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les documents -->
<div class="modal fade" id="documentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Documents soumis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="documentsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des documents...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filtrerParLien(lienId) {
    const url = new URL(window.location);
    if (lienId) {
        url.searchParams.set('lien_id', lienId);
    } else {
        url.searchParams.delete('lien_id');
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function afficherDetails(button) {
    // Récupérer les données depuis les attributs data-*
    const nom = button.getAttribute('data-nom');
    const email = button.getAttribute('data-email');
    const telephone = button.getAttribute('data-telephone');
    const lieuNaissance = button.getAttribute('data-lieu-naissance');
    const dateNaissance = button.getAttribute('data-date-naissance');
    const sexe = button.getAttribute('data-sexe');
    const nationalite = button.getAttribute('data-nationalite');
    const adresse = button.getAttribute('data-adresse');
    const personneContact = button.getAttribute('data-personne-contact');
    const telephoneContact = button.getAttribute('data-telephone-contact');
    const reference = button.getAttribute('data-reference');
    const dateSoumission = button.getAttribute('data-date-soumission');
    const statut = button.getAttribute('data-statut');
    
    // Remplir le modal avec les données
    document.getElementById('detail_nom').textContent = nom;
    document.getElementById('detail_email').textContent = email;
    document.getElementById('detail_telephone').textContent = telephone;
    document.getElementById('detail_lieu_naissance').textContent = lieuNaissance;
    document.getElementById('detail_date_naissance').textContent = dateNaissance;
    document.getElementById('detail_sexe').textContent = sexe;
    document.getElementById('detail_nationalite').textContent = nationalite;
    document.getElementById('detail_adresse').textContent = adresse;
    document.getElementById('detail_personne_contact').textContent = personneContact || 'Non renseigné';
    document.getElementById('detail_telephone_contact').textContent = telephoneContact || 'Non renseigné';
    document.getElementById('detail_reference').textContent = reference;
    document.getElementById('detail_date_soumission').textContent = dateSoumission;
    document.getElementById('detail_statut').innerHTML = '<span class="badge bg-info">' + statut + '</span>';
}

function afficherDocuments(inscriptionId) {
    // Afficher un message de chargement
    document.getElementById('documentsContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des documents...</p>
        </div>
    `;
    
    // Charger les documents via AJAX
    fetch('controller/get_inscription_externe_documents.php?id=' + inscriptionId)
        .then(response => response.text())
        .then(html => {
            document.getElementById('documentsContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('documentsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des documents.
                </div>
            `;
        });
}

function validerInscription(inscriptionId) {
    Swal.fire({
        title: 'Valider cette inscription?',
        text: "Cette action marquera l'inscription comme validée.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, valider',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/valider_inscription_externe.php?id=' + inscriptionId;
        }
    });
}

function rejeterInscription(inscriptionId) {
    Swal.fire({
        title: 'Rejeter cette inscription?',
        input: 'textarea',
        inputLabel: 'Motif du rejet',
        inputPlaceholder: 'Expliquez pourquoi cette inscription est rejetée...',
        inputAttributes: {
            'aria-label': 'Motif du rejet'
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Rejeter',
        cancelButtonText: 'Annuler',
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'Vous devez fournir un motif de rejet!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const motif = encodeURIComponent(result.value.trim());
            window.location.href = 'controller/rejeter_inscription_externe.php?id=' + inscriptionId + '&motif=' + motif;
        }
    });
}

function exporterInscriptions() {
    const lienId = <?= $lien_id ? $lien_id : 'null' ?>;
    const url = lienId ? 'controller/exporter_inscriptions_externes.php?lien_id=' + lienId : 'controller/exporter_inscriptions_externes.php';
    window.open(url, '_blank');
}
</script>

<?php include "./views/include/footer.php"; ?>