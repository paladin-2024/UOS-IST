<?php
include "./views/include/header.php";
$universite = new Universite();
$fraisModel = new Frais();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
$selectedType = isset($_GET['type_frais']) ? $_GET['type_frais'] : 'academique';

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer toutes les sections accessibles à l'utilisateur
$sections = [];
if ($_SESSION['idRole'] == 1) { // Si administrateur
    $sections = $universite->getSections();
} else {
    // Pour les autres utilisateurs, vérifier les sections associées
    $userSections = $universite->getUserSections($_SESSION['id']);
    foreach ($userSections as $sectionId) {
        $sectionData = $universite->getSectionById($sectionId);
        if ($sectionData) {
            $sections[] = $sectionData;
        }
    }
}

// Récupérer tous les frais pour l'année en cours
$frais = [];
if (!empty($sections)) {
    $sectionIds = array_column($sections, 'idsection');
    
    // Si c'est un administrateur et aucune section n'est sélectionnée, récupérer tous les frais
    if ($_SESSION['idRole'] == 1 && $selectedSection == 0) {
        if ($selectedType == 'academique') {
            $frais = $fraisModel->getAllFrais($currentYear['idannee_acad'], $search);
        } else {
            $frais = $fraisModel->getAllFraisSoutenance($currentYear['idannee_acad'], $search);
        }
    } 
    // Si une section spécifique est sélectionnée
    elseif ($selectedSection > 0 && in_array($selectedSection, $sectionIds)) {
        if ($selectedType == 'academique') {
            $frais = $fraisModel->getAllFraisBySection($selectedSection, $currentYear['idannee_acad'], $search);
        } else {
            $frais = $fraisModel->getAllFraisSoutenanceBySection($selectedSection, $currentYear['idannee_acad'], $search);
        }
    } 
    // Sinon, utiliser la première section accessible
    else {
        if ($selectedType == 'academique') {
            $frais = $fraisModel->getAllFraisBySection($sectionIds[0], $currentYear['idannee_acad'], $search);
        } else {
            $frais = $fraisModel->getAllFraisSoutenanceBySection($sectionIds[0], $currentYear['idannee_acad'], $search);
        }
    }
}

?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES FRAIS ACADÉMIQUES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Configuration des frais</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table des frais -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des frais
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createFraisModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter un frais
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importFraisModal" class="btnPage">
                                            <i class="bi bi-upload"></i> Importer des frais
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="input-group">
                                                <input type="hidden" name="view" value="frais/configuration_frais">
                                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un frais...">
                                                <button type="submit" class="btn btn-primary">Rechercher</button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="type_frais" class="form-select" onchange="this.form.submit()">
                                                <option value="academique" <?= $selectedType == 'academique' ? 'selected' : '' ?>>Frais académiques</option>
                                                <option value="soutenance" <?= $selectedType == 'soutenance' ? 'selected' : '' ?>>Frais de soutenance</option>
                                            </select>
                                        </div>
                                        <?php if ($_SESSION['idRole'] == 1): ?>
                                        <div class="col-md-4">
                                            <select name="section" class="form-select" onchange="this.form.submit()">
                                                <option value="0">Toutes les sections</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($section['designationSection']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <!-- Table des frais -->
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Montant</th>
                                            <th scope="col">Devise</th>
                                            <?php if ($selectedType == 'academique'): ?>
                                            <th scope="col">Promotion</th>
                                            <?php else: ?>
                                            <th scope="col">Section</th>
                                            <?php endif; ?>
                                            <th scope="col">Obligatoire</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($frais as $f) {
                                            echo "<tr>
                                                <td>{$i}</td>
                                                <td>{$f['designation']}</td>
                                                <td>" . number_format($f['montant'], 2) . "</td>
                                                <td>{$f['devise']}</td>";
                                            
                                            if ($selectedType == 'academique') {
                                                echo "<td>{$f['designationPromotion']}</td>";
                                            } else {
                                                echo "<td>{$f['designationSection']}</td>";
                                            }
                                            
                                            $obligatoire = $f['estObligatoire'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>';
                                            echo "<td>{$obligatoire}</td>";
                                            
                                            echo "<td>
                                                <button class='btn btn-sm btn-warning' onclick='openEditFraisModal(\"" . 
                                                    ($selectedType == 'academique' ? $f['idfrais'] : $f['idfrais_soutenance']) . "\", \"" . 
                                                    addslashes($f['designation']) . "\", \"" . 
                                                    $f['montant'] . "\", \"" . 
                                                    $f['devise'] . "\", \"" . 
                                                    ($selectedType == 'academique' ? $f['promotion_idpromotion'] : $f['section_id']) . "\", \"" . 
                                                    $f['estObligatoire'] . "\")'>
                                                    <i class='bi bi-pencil-square'></i>
                                                </button>
                                                <button class='btn btn-sm btn-danger' onclick='deleteFrais(\"" . 
                                                    ($selectedType == 'academique' ? $f['idfrais'] : $f['idfrais_soutenance']) . "\", \"" . 
                                                    $selectedType . "\")'>
                                                    <i class='bi bi-trash'></i>
                                                </button>
                                                <button class='btn btn-sm btn-info' title='Voir les paiements' onclick='window.location.href=\"?view=frais/" . 
                                                    ($selectedType == 'academique' ? "paiement" : "paiement_soutenance") . "&frais=" . 
                                                    ($selectedType == 'academique' ? $f['idfrais'] : $f['idfrais_soutenance']) . "&type=" . 
                                                    $selectedType . "\"'>
                                                    <i class='bi bi-currency-dollar'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                            $i++;
                                        }

                                        if (empty($frais)) {
                                            $colspan = 7;
                                            echo "<tr><td colspan='{$colspan}' class='text-center'>Aucun frais trouvé</td></tr>";
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

<!-- Modal pour ajouter un frais -->
<div class="modal fade" id="createFraisModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $selectedType == 'academique' ? 'Ajouter un frais académique' : 'Ajouter un frais de soutenance' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="fraisForm" method="POST" action="controller/frais_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="type_frais" value="<?= $selectedType ?>">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant</label>
                            <input type="number" name="montant" id="montant" step="0.01" min="0" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="devise" class="form-label">Devise</label>
                            <select name="devise" id="devise" class="form-select" required>
                                <option value="USD">USD</option>
                                <option value="CDF">CDF</option>
                                <option value="EUR">EUR</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une devise.</div>
                        </div>
                    </div>
                    
                    <?php if ($selectedType == 'academique'): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="promotion" class="form-label">Promotion</label>
                            <select name="promotion" id="promotion" class="form-select" required>
                                <option value="">Sélectionnez une promotion</option>
                                <?php 
                                foreach ($sections as $section) {
                                    $promotions = $universite->getPromotionsBySection($section['idsection'], $currentYear['idannee_acad']);
                                    if (!empty($promotions)) {
                                        echo "<optgroup label='{$section['designationSection']}'>";
                                        foreach ($promotions as $promotion) {
                                            echo "<option value='{$promotion['idpromotion']}'>{$promotion['designationPromotion']} ({$promotion['cycle']})</option>";
                                        }
                                        echo "</optgroup>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="section" class="form-label">Section</label>
                            <select name="section" id="section" class="form-select" required>
                                <option value="">Sélectionnez une section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= htmlspecialchars($section['designationSection']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="estObligatoire" name="estObligatoire" value="1" checked>
                                <label class="form-check-label" for="estObligatoire">
                                    Frais obligatoire
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addFraisBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier un frais -->
<div class="modal fade" id="editFraisModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $selectedType == 'academique' ? 'Modifier un frais académique' : 'Modifier un frais de soutenance' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editFraisForm" method="POST" action="controller/frais_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="type_frais" value="<?= $selectedType ?>">
                    <input type="hidden" name="idFrais" id="edit_idFrais">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_designation" class="form-label">Désignation</label>
                            <input type="text" name="designation" id="edit_designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_montant" class="form-label">Montant</label>
                            <input type="number" name="montant" id="edit_montant" step="0.01" min="0" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un montant valide.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_devise" class="form-label">Devise</label>
                            <select name="devise" id="edit_devise" class="form-select" required>
                                <option value="USD">USD</option>
                                <option value="CDF">CDF</option>
                                <option value="EUR">EUR</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une devise.</div>
                        </div>
                    </div>
                    
                    <?php if ($selectedType == 'academique'): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_promotion" class="form-label">Promotion</label>
                            <select name="promotion" id="edit_promotion" class="form-select" required>
                                <option value="">Sélectionnez une promotion</option>
                                <?php 
                                foreach ($sections as $section) {
                                    $promotions = $universite->getPromotionsBySection($section['idsection'], $currentYear['idannee_acad']);
                                    if (!empty($promotions)) {
                                        echo "<optgroup label='{$section['designationSection']}'>";
                                        foreach ($promotions as $promotion) {
                                            echo "<option value='{$promotion['idpromotion']}'>{$promotion['designationPromotion']} ({$promotion['cycle']})</option>";
                                        }
                                        echo "</optgroup>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_section" class="form-label">Section</label>
                            <select name="section" id="edit_section" class="form-select" required>
                                <option value="">Sélectionnez une section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= htmlspecialchars($section['designationSection']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_estObligatoire" name="estObligatoire" value="1">
                                <label class="form-check-label" for="edit_estObligatoire">
                                    Frais obligatoire
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editFraisBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour importer des frais -->
<div class="modal fade" id="importFraisModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des frais</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" method="POST" action="controller/frais_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="import">
                    <input type="hidden" name="type_frais" value="<?= $selectedType ?>">
                    <input type="hidden" name="idAnneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <?php if ($selectedType == 'academique'): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="section_import" class="form-label">Section</label>
                            <select name="section_import" id="section_import" class="form-select" required onchange="loadPromotionsImport(this.value)">
                                <option value="">Sélectionnez une section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= htmlspecialchars($section['designationSection']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="section_import" class="form-label">Section</label>
                            <select name="section_import" id="section_import" class="form-select" required>
                                <option value="">Sélectionnez une section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= htmlspecialchars($section['designationSection']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($selectedType == 'academique'): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="promotion_import" class="form-label">Promotion</label>
                            <select name="promotion_import" id="promotion_import" class="form-select" required>
                                <option value="">Sélectionnez d'abord une section</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichier_import" class="form-label">Fichier Excel/CSV</label>
                            <input type="file" name="fichier_import" id="fichier_import" class="form-control" required accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Le fichier doit contenir les colonnes suivantes: Désignation, Montant, Devise, Description (optionnelle).
                        <?php if ($selectedType == 'academique'): ?>
                        <br>Pour les frais académiques, vous pouvez également spécifier si le frais est obligatoire (Oui/Non).
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="colonne_designation" class="form-label">Colonne Désignation</label>
                            <input type="text" name="colonne_designation" id="colonne_designation" class="form-control" value="A" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="colonne_montant" class="form-label">Colonne Montant</label>
                            <input type="text" name="colonne_montant" id="colonne_montant" class="form-control" value="B" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="colonne_devise" class="form-label">Colonne Devise</label>
                            <input type="text" name="colonne_devise" id="colonne_devise" class="form-control" value="C" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="colonne_description" class="form-label">Colonne Description (optionnelle)</label>
                            <input type="text" name="colonne_description" id="colonne_description" class="form-control" value="D">
                        </div>
                    </div>
                    
                    <?php if ($selectedType == 'academique'): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="colonne_obligatoire" class="form-label">Colonne Obligatoire (optionnelle)</label>
                            <input type="text" name="colonne_obligatoire" id="colonne_obligatoire" class="form-control" value="E">
                            <small class="form-text text-muted">Utilisez "Oui" ou "Non" pour indiquer si le frais est obligatoire.</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
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
// Fonction pour charger les promotions pour l'importation
function loadPromotionsImport(sectionId) {
    if (!sectionId) {
        document.getElementById('promotion_import').innerHTML = '<option value="">Sélectionnez d\'abord une section</option>';
        return;
    }
    
    fetch(`controller/get_promotions.php?section=${sectionId}&annee=<?= $currentYear['idannee_acad'] ?>`)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">Sélectionnez une promotion</option>';
            data.forEach(promotion => {
                options += `<option value="${promotion.idpromotion}">${promotion.designationPromotion} (${promotion.cycle})</option>`;
            });
            document.getElementById('promotion_import').innerHTML = options;
        })
        .catch(error => console.error('Erreur:', error));
}

// Fonction pour ouvrir le modal de modification d'un frais
function openEditFraisModal(idFrais, designation, montant, devise, idEntite, estObligatoire) {
    document.getElementById('edit_idFrais').value = idFrais;
    document.getElementById('edit_designation').value = designation;
    document.getElementById('edit_montant').value = montant;
    document.getElementById('edit_devise').value = devise;
    
    <?php if ($selectedType == 'academique'): ?>
    if (document.getElementById('edit_promotion')) {
        document.getElementById('edit_promotion').value = idEntite;
    }
    <?php else: ?>
    if (document.getElementById('edit_section')) {
        document.getElementById('edit_section').value = idEntite;
    }
    <?php endif; ?>
    
    if (document.getElementById('edit_estObligatoire')) {
        document.getElementById('edit_estObligatoire').checked = (estObligatoire == '1');
    }
    
    // Récupérer la description du frais par une requête AJAX
    fetch(`controller/get_frais_details.php?id=${idFrais}&type=<?= $selectedType ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.description) {
                document.getElementById('edit_description').value = data.description;
            }
        })
        .catch(error => console.error('Erreur:', error));
    
    new bootstrap.Modal(document.getElementById('editFraisModal')).show();
}

// Fonction pour supprimer un frais
function deleteFrais(idFrais, typeFrais) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera le frais et ne peut pas être annulée!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/frais_controller.php?action=delete&id=${idFrais}&type=${typeFrais}`;
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
</script>

<?php include "./views/include/footer.php"; ?>

