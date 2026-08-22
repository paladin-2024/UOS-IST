<?php
include "./views/include/header.php";
$universite = new Universite();
$ecue = new Ecue();
$agent = new Agent();
$enseignant = new Enseignant();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$ueId = isset($_GET['ue']) ? intval($_GET['ue']) : 0;

// Vérifier si l'UE existe
if ($ueId <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Unité d\'enseignement non spécifiée.'
        }).then(() => {
            window.location.href = '?view=enseignement/unites_enseignement';
        });
    </script>";
    exit;
}

// Récupérer les informations de l'UE
$ueInfo = $universite->getUEById($ueId);
if (!$ueInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Unité d\'enseignement non trouvée.'
        }).then(() => {
            window.location.href = '?view=enseignement/unites_enseignement';
        });
    </script>";
    exit;
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les ECUE pour cette UE
$ecues = $ecue->getEcuesByUE($ueId, $search);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES ECUE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/unites_enseignement">Unités d'Enseignement</a></li>
                <li class="breadcrumb-item active">ECUE</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Unité d'Enseignement: <?= htmlspecialchars($ueInfo['designationUE']) ?> (<?= htmlspecialchars($ueInfo['codeUE']) ?>)</h5>
                        <p><strong>Semestre:</strong> <?= htmlspecialchars($ueInfo['numeroSemestre']) ?></p>
                        <p><strong>Promotion:</strong> <?= htmlspecialchars($ueInfo['designationPromotion']) ?></p>
                        <?php if (!empty($ueInfo['description'])): ?>
                            <p><strong>Description:</strong> <?= htmlspecialchars($ueInfo['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table des ECUE -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des ECUE
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createEcueModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter un ECUE
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importEcueModal" class="btnPage">
                                            <i class="bi bi-upload"></i> Importer des ECUE
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="enseignement/ecues">
                                        <input type="hidden" name="ue" value="<?= $ueId ?>">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un ECUE...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Volume horaire</th>
                                            <th scope="col">Enseignants</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($ecues as $e) {
                                            // Récupérer les enseignants affectés
                                            $enseignantsAffectes = $enseignant->getEnseignantsAffectesByCours($e['idECUE'], $currentYear['idannee_acad']);
                                            $enseignantsStr = '';
                                            foreach ($enseignantsAffectes as $ens) {
                                                $enseignantsStr .= '<span class="badge bg-info">' . $ens['poste'] . '</span> ' . $ens['noms'] . '<br>';
                                            }

                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$e['designationECUE']}</td>
                                                <td>
                                                    CMI: {$e['CMI']}h<br>
                                                    TD: {$e['TD']}h<br>
                                                    TP: {$e['TP']}h
                                                </td>
                                                <td>{$enseignantsStr}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-primary' onclick='window.location.href=\"?view=enseignement/cours.details&id={$e['idECUE']}\"'>
                                                        <i class='bi bi-book'></i> Contenu
                                                    </button>
                                                    <button class='btn btn-sm btn-warning' onclick='openEditEcueModal({$e['idECUE']}, \"{$e['designationECUE']}\", {$e['CMI']}, {$e['TD']}, {$e['TP']})'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-success' onclick='openAffectationModal({$e['idECUE']})'>
                                                        <i class='bi bi-person-plus'></i> Affecter
                                                    </button>
                                                    <button class='btn btn-sm btn-info' onclick='window.location.href=\"?view=enseignement/evaluations&ecue={$e['idECUE']}\"'>
                                                        <i class='bi bi-clipboard-check'></i> Évaluations
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='deleteEcue({$e['idECUE']})'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                </td>
                                            </tr>";
                                            $i++;
                                        }

                                        if (empty($ecues)) {
                                            echo "<tr><td colspan='5' class='text-center'>Aucun ECUE trouvé pour cette unité d'enseignement</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un ECUE -->
<!-- Remplacer le modal d'ajout d'ECUE par celui-ci -->
<div class="modal fade" id="createEcueModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un ECUE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="ecueForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create_multiple">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                        <div class="col-md-2">
                            <label for="cmi" class="form-label">CMI (heures)</label>
                            <input type="number" name="cmi" id="cmi" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                        <div class="col-md-2">
                            <label for="td" class="form-label">TD (heures)</label>
                            <input type="number" name="td" id="td" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                        <div class="col-md-2">
                            <label for="tp" class="form-label">TP (heures)</label>
                            <input type="number" name="tp" id="tp" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Sélection des UE</label>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Sélectionnez les UE dans lesquelles vous souhaitez ajouter cet ECUE.
                                <strong>L'UE actuelle est présélectionnée.</strong>
                            </div>
                            
                            <div class="card">
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" id="ueSearch" 
                                                placeholder="Rechercher une UE...">
                                        </div>
                                    </div>
                                    
                                    <div class="btn-group mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllUEs">
                                            Tout sélectionner
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllUEs">
                                            Tout désélectionner
                                        </button>
                                    </div>
                                    
                                    <div id="ueContainer" class="border rounded p-3">
                                    <?php
                                    // Récupérer les sections de l'année académique en cours
                                    $sections = $universite->getSections('', $currentYear['idannee_acad']);
                                        foreach ($sections as $section) {
                                            echo "<h6 class='mt-2'>{$section['designationSection']}</h6>";
                                            $promotions = $universite->getPromotionsBySection($section['idsection']);
                                            
                                            if (!empty($promotions)) {
                                                echo "<div class='ms-3 mb-3'>";
                                                foreach ($promotions as $promotion) {
                                                    echo "<div class='mb-2'><strong>{$promotion['designationPromotion']} ({$promotion['anneeDesignation']})</strong></div>";
                                                    
                                                    // Récupérer les UE pour cette promotion
                                                    $ues = $universite->getUEsByPromotion($promotion['idpromotion']);
                                                    
                                                    if (!empty($ues)) {
                                                        echo "<div class='ms-3 mb-3 row'>";
                                                        foreach ($ues as $ue) {
                                                            $checked = ($ue['idUE'] == $ueId) ? 'checked' : '';
                                                            echo "
                                                            <div class='form-check col-md-4 mb-2'>
                                                                <input class='form-check-input' type='checkbox' name='ues[]' value='{$ue['idUE']}' id='ue_{$ue['idUE']}' {$checked}>
                                                                <label class='form-check-label' for='ue_{$ue['idUE']}'>
                                                                    {$ue['codeUE']} - {$ue['designationUE']}
                                                                </label>
                                                            </div>";
                                                        }
                                                        echo "</div>";
                                                    } else {
                                                        echo "<div class='ms-3 text-muted'>Aucune UE disponible</div>";
                                                    }
                                                }
                                                echo "</div>";
                                            } else {
                                                echo "<div class='ms-3 text-muted'>Aucune promotion disponible</div>";
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="invalid-feedback">Veuillez sélectionner au moins une UE.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addEcueBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un ECUE -->
<div class="modal fade" id="editEcueModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title">Modifier un ECUE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEcueForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="idEcue" id="edit_idEcue">
                    <input type="hidden" name="ueId" value="<?= $ueId ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="edit_designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_cmi" class="form-label">CMI (heures)</label>
                            <input type="number" name="cmi" id="edit_cmi" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_td" class="form-label">TD (heures)</label>
                            <input type="number" name="td" id="edit_td" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_tp" class="form-label">TP (heures)</label>
                            <input type="number" name="tp" id="edit_tp" min="0" step="0.5" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre valide.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editEcueBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour affecter un enseignant -->
<div class="modal fade" id="affectationModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Affecter un enseignant à l'ECUE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="affectationForm" method="POST" action="controller/affectation_enseignant.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idEcue" id="affectation_idEcue">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="enseignant" class="form-label">Enseignant</label>
                            <select name="enseignant" id="enseignant" class="form-select" required>
                                <option value="">Sélectionnez un enseignant</option>
                                <?php
                                $enseignants = $agent->getAgentsByType('Enseignant');
                                foreach ($enseignants as $e) {
                                    echo "<option value='{$e['idAgent']}'>{$e['noms']} ({$e['gradeDesignation']})</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un enseignant.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="poste" class="form-label">Poste</label>
                            <select name="poste" id="poste" class="form-select" required>
                                <option value="">Sélectionnez un poste</option>
                                <option value="Titulaire">Titulaire</option>
                                <option value="Assistant">Assistant</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un poste.</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="affectationBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer l'affectation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour importer des ECUE -->
<div class="modal fade" id="importEcueModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des ECUE</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="ueId" value="<?= $ueId ?>">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichier_import" class="form-label">Fichier Excel/CSV</label>
                            <input type="file" name="fichier_import" id="fichier_import" class="form-control" required accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Le fichier doit contenir les colonnes suivantes: Désignation, CMI, TD, TP.
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="colonne_designation" class="form-label">Colonne Désignation</label>
                            <input type="text" name="colonne_designation" id="colonne_designation" class="form-control" value="A" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="colonne_cmi" class="form-label">Colonne CMI</label>
                            <input type="text" name="colonne_cmi" id="colonne_cmi" class="form-control" value="B" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="colonne_td" class="form-label">Colonne TD</label>
                            <input type="text" name="colonne_td" id="colonne_td" class="form-control" value="C" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="colonne_tp" class="form-label">Colonne TP</label>
                            <input type="text" name="colonne_tp" id="colonne_tp" class="form-control" value="D" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skip_header" name="skip_header" value="1" checked>
                                <label class="form-check-label" for="skip_header">
                                    Ignorer la première ligne (en-têtes)
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal de modification d'un ECUE
function openEditEcueModal(idEcue, designation, cmi, td, tp) {
    document.getElementById('edit_idEcue').value = idEcue;
    document.getElementById('edit_designation').value = designation;
    document.getElementById('edit_cmi').value = cmi;
    document.getElementById('edit_td').value = td;
    document.getElementById('edit_tp').value = tp;
    
    new bootstrap.Modal(document.getElementById('editEcueModal')).show();
}

// Fonction pour ouvrir le modal d'affectation
function openAffectationModal(idEcue) {
    document.getElementById('affectation_idEcue').value = idEcue;
    new bootstrap.Modal(document.getElementById('affectationModal')).show();
}

// Fonction pour supprimer un ECUE
function deleteEcue(idEcue) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera l'ECUE et ne peut pas être annulée!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/ecue_controller.php?action=delete&id=${idEcue}&ue=<?= $ueId ?>`;
        }
    });
}

// Validation des formulaires Bootstrap
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()


// Gestion de la recherche et de la sélection des UE
document.addEventListener('DOMContentLoaded', function() {
    // Recherche d'UE
    const ueSearch = document.getElementById('ueSearch');
    if (ueSearch) {
        ueSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            // Rechercher dans les UE
            document.querySelectorAll('#ueContainer .form-check-label').forEach(label => {
                const ueText = label.textContent.toLowerCase();
                const ueItem = label.closest('.form-check');
                const promotionContainer = ueItem.closest('.ms-3.mb-3.row');
                const promotionTitle = promotionContainer ? promotionContainer.previousElementSibling : null;
                const sectionContainer = promotionTitle ? promotionTitle.closest('.ms-3.mb-3') : null;
                
                const visible = ueText.includes(searchTerm);
                
                // Afficher/masquer les UE
                ueItem.style.display = visible ? 'block' : 'none';
                
                // Gérer la visibilité des promotions et sections
                if (promotionContainer && promotionTitle) {
                    // Vérifier si au moins une UE est visible dans cette promotion
                    const hasVisibleUE = Array.from(promotionContainer.querySelectorAll('.form-check'))
                        .some(item => item.style.display !== 'none');
                    
                    promotionTitle.style.display = hasVisibleUE ? 'block' : 'none';
                    promotionContainer.style.display = hasVisibleUE ? 'flex' : 'none';
                    
                    if (sectionContainer) {
                        // Vérifier si au moins une promotion est visible dans cette section
                        const hasVisiblePromotion = Array.from(sectionContainer.querySelectorAll('.mb-2'))
                            .some(item => item.style.display !== 'none');
                        
                        sectionContainer.style.display = hasVisiblePromotion ? 'block' : 'none';
                    }
                }
            });
        });
    }
    
    // Sélection/désélection de toutes les UE
    const selectAllBtn = document.getElementById('selectAllUEs');
    const deselectAllBtn = document.getElementById('deselectAllUEs');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="ues[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('input[name="ues[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
    
    // Validation du formulaire
    document.getElementById('ecueForm').addEventListener('submit', function(event) {
        // Vérifier si au moins une UE est sélectionnée
        const uesChecked = document.querySelectorAll('input[name="ues[]"]:checked');
        if (uesChecked.length === 0) {
            event.preventDefault();
            event.stopPropagation();
            
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez sélectionner au moins une UE.'
            });
            
            return false;
        }
    });
});

</script>

<?php include "./views/include/footer.php"; ?>

