<?php
// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    header('Location: index.php');
    exit();
}

require_once './models/Dette.php';
require_once './models/Universite.php';
require_once './models/Etudiant.php';

$dette = new Dette();
$universite = new Universite();
$etudiant = new Etudiant();

// Récupérer les filtres
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Récupérer les données pour les filtres
$promotions = $universite->getAllPromotions();
$annees = $universite->getAnneeAcademiques();

// Récupérer les étudiants avec dettes
$etudiants = [];
if ($promotionId && $anneeId) {
    $etudiants = $dette->getEtudiantsAvecDettes($promotionId, $anneeId, $search);
}

include "./views/include/header.php";
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Bulletins de Dettes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item">LMD</li>
                <li class="breadcrumb-item active">Bulletins de dettes</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Filtres -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rechercher un étudiant</h5>
                        
                        <form method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="lmd/bulletins_dettes">
                            
                            <div class="col-md-4">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select name="promotion" id="promotion" class="form-select" required>
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?= $promo['idpromotion'] ?>" <?= $promotionId == $promo['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="annee" class="form-label">Année académique</label>
                                <select name="annee" id="annee" class="form-select" required>
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $anneeId == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nom ou matricule" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des étudiants -->
            <?php if ($promotionId && $anneeId): ?>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Étudiants avec dettes
                            <?php if (!empty($etudiants)): ?>
                                <button class="btn btn-danger btn-sm float-end" onclick="genererTousBulletins()">
                                    <i class="bi bi-file-pdf"></i> Générer tous les bulletins
                                </button>
                            <?php endif; ?>
                        </h5>

                        <?php if (empty($etudiants)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Aucun étudiant avec dettes trouvé.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tableEtudiants">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Matricule</th>
                                            <th>Nom complet</th>
                                            <th>Nombre de dettes</th>
                                            <th>Total crédits</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $etud): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="select-etudiant" 
                                                       value="<?= $etud['matricule'] ?>">
                                            </td>
                                            <td><?= htmlspecialchars($etud['matricule']) ?></td>
                                            <td><?= htmlspecialchars($etud['nom_complet']) ?></td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    <?= $etud['nombre_dettes'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $etud['total_credits'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="previsualiserBulletin('<?= $etud['matricule'] ?>')">
                                                    <i class="bi bi-eye"></i> Prévisualiser
                                                </button>
                                                <a href="controller/export_bulletin_dettes.php?matricule=<?= $etud['matricule'] ?>&annee=<?= $anneeId ?>&promotion=<?= $promotionId ?>" 
                                                   class="btn btn-sm btn-danger" target="_blank">
                                                    <i class="bi bi-file-pdf"></i> Générer PDF
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Modal de prévisualisation -->
<div class="modal fade" id="modalPreview" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Prévisualisation du bulletin de dettes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-danger" id="btnGenererPDF">
                    <i class="bi bi-file-pdf"></i> Générer PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialiser DataTable
$(document).ready(function() {
    $('#tableEtudiants').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json',
        }
    });
});

// Sélectionner/désélectionner tous
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.select-etudiant');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// Prévisualiser un bulletin
function previsualiserBulletin(matricule) {
    $('#previewContent').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
    $('#modalPreview').modal('show');
    
    // Charger le contenu via iframe
    const iframe = document.createElement('iframe');
    iframe.src = `controller/export_bulletin_dettes.php?matricule=${matricule}&annee=<?= $anneeId ?>&promotion=<?= $promotionId ?>&preview=1`;
    iframe.style.width = '100%';
    iframe.style.height = '600px';
    iframe.style.border = 'none';
    
    $('#previewContent').html('');
    $('#previewContent').append(iframe);
    
    // Configurer le bouton de génération PDF
    $('#btnGenererPDF').off('click').on('click', function() {
        window.open(`controller/export_bulletin_dettes.php?matricule=${matricule}&annee=<?= $anneeId ?>&promotion=<?= $promotionId ?>`, '_blank');
    });
}

// Générer tous les bulletins sélectionnés
function genererTousBulletins() {
    const selected = [];
    document.querySelectorAll('.select-etudiant:checked').forEach(checkbox => {
        selected.push(checkbox.value);
    });
    
    if (selected.length === 0) {
        Swal.fire('Attention', 'Veuillez sélectionner au moins un étudiant', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Confirmation',
        text: `Voulez-vous générer ${selected.length} bulletin(s) ?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, générer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Créer un formulaire pour envoyer les matricules
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'controller/export_bulletins_multiples.php';
            form.target = '_blank';
            
            // Ajouter les paramètres
            const params = {
                'matricules': selected.join(','),
                'annee': <?= $anneeId ?>,
                'promotion': <?= $promotionId ?>
            };
            
            for (const key in params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            Swal.fire('Succès', 'Les bulletins sont en cours de génération', 'success');
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>