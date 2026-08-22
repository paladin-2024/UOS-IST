<?php
include "./views/include/header.php";

$db = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];

// Récupérer l'année académique active
$anneeAcadActive = "SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtAnneeAcadActive = $db->prepare($anneeAcadActive);
$stmtAnneeAcadActive->execute();
$anneeAcademique = $stmtAnneeAcadActive->fetch(PDO::FETCH_ASSOC);

// Récupérer toutes les années académiques pour le sélecteur
$queryAnnees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $db->prepare($queryAnnees);
$stmtAnnees->execute();
$anneesAcademiques = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions avec leurs chefs actuels
$queryPromotions = "SELECT 
                        p.idpromotion, 
                        p.designationPromotion, 
                        o.designationOrientation,
                        s.designationSection,
                        cp.id_chef,
                        cp.idetudiant as chef_id,
                        e.noms as chef_nom,
                        e.matricule as chef_matricule,
                        cp.date_nomination,
                        cp.est_actif
                    FROM promotion p
                    JOIN orientation o ON p.orientation_idorientation = o.idorientation
                    JOIN section s ON o.section_idsection = s.idsection
                    LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion 
                        AND cp.annee_acad_idannee_acad = :idannee_acad 
                        AND cp.est_actif = 1
                    LEFT JOIN etudiant e ON cp.idetudiant = e.idetudiant
                    WHERE p.annee_acad_idannee_acad = :idannee_acad
                    ORDER BY o.designationOrientation, p.designationPromotion";
$stmtPromotions = $db->prepare($queryPromotions);
$stmtPromotions->bindParam(':idannee_acad', $anneeAcademique['idannee_acad'], PDO::PARAM_INT);
$stmtPromotions->execute();
$promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$queryStats = "SELECT 
                   COUNT(*) as total_promotions,
                   COUNT(cp.id_chef) as promotions_avec_chef,
                   COUNT(*) - COUNT(cp.id_chef) as promotions_sans_chef
               FROM promotion p
               LEFT JOIN chef_promotion cp ON p.idpromotion = cp.promotion_idpromotion 
                   AND cp.annee_acad_idannee_acad = :idannee_acad 
                   AND cp.est_actif = 1
               WHERE p.annee_acad_idannee_acad = :idannee_acad";
$stmtStats = $db->prepare($queryStats);
$stmtStats->bindParam(':idannee_acad', $anneeAcademique['idannee_acad'], PDO::PARAM_INT);
$stmtStats->execute();
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Chefs de Promotion</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Réception</li>
                <li class="breadcrumb-item active">Chefs de Promotion</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Promotions</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['total_promotions'] ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Avec Chef</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-check"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['promotions_avec_chef'] ?></h6>
                                <span class="text-success small pt-1 fw-bold">
                                    <?= $stats['total_promotions'] > 0 ? round(($stats['promotions_avec_chef'] / $stats['total_promotions']) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Sans Chef</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-x"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $stats['promotions_sans_chef'] ?></h6>
                                <span class="text-danger small pt-1 fw-bold">
                                    <?= $stats['total_promotions'] > 0 ? round(($stats['promotions_sans_chef'] / $stats['total_promotions']) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Gestion des Chefs de Promotion</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChefModal">
                                <i class="bi bi-plus-circle"></i> Affecter un Chef
                            </button>
                        </div>

                        <!-- Filtre par année académique -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="filterAnneeAcad" class="form-label">Année Académique</label>
                                <select class="form-select" id="filterAnneeAcad" onchange="changerAnneeAcademique()">
                                    <?php foreach ($anneesAcademiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= $annee['idannee_acad'] == $anneeAcademique['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="filterSection" class="form-label">Section</label>
                                <select class="form-select" id="filterSection" onchange="filtrerPromotions()">
                                    <option value="">Toutes les sections</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="filterStatut" class="form-label">Statut</label>
                                <select class="form-select" id="filterStatut" onchange="filtrerPromotions()">
                                    <option value="">Tous</option>
                                    <option value="avec_chef">Avec Chef</option>
                                    <option value="sans_chef">Sans Chef</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tableau des promotions -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Section</th>
                                        <th>Orientation</th>
                                        <th>Promotion</th>
                                        <th>Chef Actuel</th>
                                        <th>Matricule</th>
                                        <th>Date Nomination</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="promotionsTableBody">
                                    <?php foreach ($promotions as $promotion): ?>
                                        <tr data-section="<?= htmlspecialchars($promotion['designationSection']) ?>" 
                                            data-statut="<?= $promotion['chef_id'] ? 'avec_chef' : 'sans_chef' ?>">
                                            <td><?= htmlspecialchars($promotion['designationSection']) ?></td>
                                            <td><?= htmlspecialchars($promotion['designationOrientation']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($promotion['designationPromotion']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($promotion['chef_nom']): ?>
                                                    <?= htmlspecialchars($promotion['chef_nom']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non affecté</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($promotion['chef_matricule']): ?>
                                                    <code><?= htmlspecialchars($promotion['chef_matricule']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($promotion['date_nomination']): ?>
                                                    <?= date('d/m/Y', strtotime($promotion['date_nomination'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($promotion['chef_id']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle"></i> Affecté
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-exclamation-circle"></i> Non affecté
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($promotion['chef_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="modifierChef(<?= $promotion['idpromotion'] ?>, <?= $promotion['chef_id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="retirerChef(<?= $promotion['id_chef'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="affecterChef(<?= $promotion['idpromotion'] ?>)">
                                                        <i class="bi bi-person-plus"></i> Affecter
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
            </div>
        </div>
    </section>

        <!-- Modal Ajouter Chef -->
    <div class="modal fade" id="addChefModal" tabindex="-1" aria-labelledby="addChefModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="addChefForm" method="POST" action="controller/addChefPromotion.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addChefModalLabel">Affecter un Chef de Promotion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="add_promotion_id" class="form-label">Promotion <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_promotion_id" name="promotion_id" required onchange="chargerEtudiants()">
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <?php if (!$promotion['chef_id']): // Seules les promotions sans chef ?>
                                            <option value="<?= $promotion['idpromotion'] ?>" 
                                                    data-orientation="<?= htmlspecialchars($promotion['designationOrientation']) ?>"
                                                    data-section="<?= htmlspecialchars($promotion['designationSection']) ?>">
                                                <?= htmlspecialchars($promotion['designationSection'] . ' - ' . $promotion['designationOrientation'] . ' - ' . $promotion['designationPromotion']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="add_etudiant_id" class="form-label">Étudiant <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_etudiant_id" name="etudiant_id" required>
                                    <option value="">Sélectionner d'abord une promotion</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="add_date_nomination" class="form-label">Date de Nomination <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_date_nomination" name="date_nomination" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_annee_acad" class="form-label">Année Académique</label>
                                <input type="text" class="form-control" readonly 
                                       value="<?= htmlspecialchars($anneeAcademique['designation']) ?>">
                                <input type="hidden" name="annee_acad_id" value="<?= $anneeAcademique['idannee_acad'] ?>">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Note :</strong> Un seul chef de promotion peut être actif par promotion et par année académique.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Affecter le Chef
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Chef -->
    <div class="modal fade" id="editChefModal" tabindex="-1" aria-labelledby="editChefModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="editChefForm" method="POST" action="controller/updateChefPromotion.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editChefModalLabel">Modifier le Chef de Promotion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="editChefContent">
                        <!-- Contenu chargé dynamiquement -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Charger les étudiants d'une promotion
        function chargerEtudiants() {
            const promotionSelect = document.getElementById('add_promotion_id');
            const etudiantSelect = document.getElementById('add_etudiant_id');
            const promotionId = promotionSelect.value;
            
            etudiantSelect.innerHTML = '<option value="">Chargement...</option>';
            
            if (promotionId) {
                fetch(`controller/getEtudiantsPromotion.php?promotion_id=${promotionId}`)
                    .then(response => response.json())
                    .then(data => {
                        etudiantSelect.innerHTML = '<option value="">Sélectionner un étudiant</option>';
                        if (data.success && data.etudiants) {
                            data.etudiants.forEach(etudiant => {
                                etudiantSelect.innerHTML += `<option value="${etudiant.idetudiant}">
                                    ${etudiant.matricule} - ${etudiant.noms}
                                </option>`;
                            });
                        } else {
                            etudiantSelect.innerHTML = '<option value="">Aucun étudiant trouvé</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        etudiantSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    });
            } else {
                etudiantSelect.innerHTML = '<option value="">Sélectionner d\'abord une promotion</option>';
            }
        }

        // Affecter un chef pour une promotion spécifique
        function affecterChef(promotionId) {
            document.getElementById('add_promotion_id').value = promotionId;
            chargerEtudiants();
            new bootstrap.Modal(document.getElementById('addChefModal')).show();
        }

        // Modifier un chef existant
        function modifierChef(promotionId, chefId) {
            fetch(`controller/getChefDetails.php?chef_id=${chefId}&promotion_id=${promotionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editChefContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editChefModal')).show();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Erreur lors du chargement des détails'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Erreur lors du chargement des détails'
                    });
                });
        }

        // Retirer un chef
        function retirerChef(chefId) {
            Swal.fire({
                title: 'Retirer ce chef de promotion ?',
                text: "Cette action va désactiver l'affectation du chef pour cette promotion.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, retirer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/removeChefPromotion.php?chef_id=${chefId}`;
                }
            });
        }

        // Changer d'année académique
        function changerAnneeAcademique() {
            const anneeSelect = document.getElementById('filterAnneeAcad');
            const anneeId = anneeSelect.value;
            if (anneeId) {
                window.location.href = `?annee_acad=${anneeId}`;
            }
        }

        // Filtrer les promotions
        function filtrerPromotions() {
            const sectionFilter = document.getElementById('filterSection').value.toLowerCase();
            const statutFilter = document.getElementById('filterStatut').value;
            const tableBody = document.getElementById('promotionsTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            // Construire la liste des sections pour le filtre
            const sections = new Set();
            Array.from(rows).forEach(row => {
                const section = row.getAttribute('data-section');
                if (section) sections.add(section);
            });

            // Mettre à jour le sélecteur de sections
            const sectionSelect = document.getElementById('filterSection');
            const currentValue = sectionSelect.value;
            sectionSelect.innerHTML = '<option value="">Toutes les sections</option>';
            Array.from(sections).sort().forEach(section => {
                const option = document.createElement('option');
                option.value = section;
                option.textContent = section;
                if (section === currentValue) option.selected = true;
                sectionSelect.appendChild(option);
            });

            // Appliquer les filtres
            Array.from(rows).forEach(row => {
                const rowSection = row.getAttribute('data-section')?.toLowerCase() || '';
                const rowStatut = row.getAttribute('data-statut') || '';

                const sectionMatch = !sectionFilter || rowSection.includes(sectionFilter);
                const statutMatch = !statutFilter || rowStatut === statutFilter;

                row.style.display = (sectionMatch && statutMatch) ? '' : 'none';
            });
        }

        // Initialiser les filtres au chargement
        document.addEventListener('DOMContentLoaded', function() {
            filtrerPromotions();
        });

        // Gestion des formulaires
        document.getElementById('addChefForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('controller/addChefPromotion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: data.message
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue'
                });
            });
        });
    </script>
</main>

<?php include "./views/include/footer_file.php"; ?>
