<?php
include "./views/include/header.php";

$grilleAncienne = new GrilleAncienne();
$grilleAncienne->createTablesIfNotExists();

// Récupérer les imports récents
$imports = $grilleAncienne->getAllImports();

// Récupérer les sections de l'année académique en cours
$sections = $grilleAncienne->getSectionsAnneeEnCours();
?>

<style>
    .upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s;
        background: #f8f9fa;
        margin: 20px 0;
    }
    
    .upload-area:hover {
        border-color: #0d6efd;
        background: #e7f1ff;
    }
    
    .upload-area.dragover {
        border-color: #0d6efd;
        background: #cfe2ff;
    }
    
    .step-indicator {
        display: flex;
        justify-content: space-between;
        margin: 20px 0 30px 0;
    }
    
    .step {
        flex: 1;
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        margin: 0 5px;
        position: relative;
    }
    
    .step.active {
        background: #0d6efd;
        color: white;
    }
    
    .step.completed {
        background: #198754;
        color: white;
    }
    
    .excel-preview {
        border: 1px solid #dee2e6;
        border-radius: 5px;
        max-height: 400px;
        overflow: auto;
        background: white;
    }
    
    .excel-cell {
        border: 1px solid #e0e0e0;
        padding: 4px 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        min-width: 80px;
        text-align: center;
        font-size: 12px;
    }
    
    .excel-cell:hover {
        background-color: #f0f8ff;
    }
    
    .excel-cell.selected-matricule {
        background-color: #ffeb3b !important;
        font-weight: bold;
    }
    
    .excel-cell.selected-nom {
        background-color: #4caf50 !important;
        color: white;
        font-weight: bold;
    }
    
    .excel-cell.selected-ue {
        background-color: #2196f3 !important;
        color: white;
        font-weight: bold;
    }
    
    .excel-cell.selected-ecue {
        background-color: #17a2b8 !important;
        color: white;
        font-weight: bold;
    }
    
    .excel-cell.selected-credits-ligne {
        background-color: #6c757d !important;
        color: white;
        font-weight: bold;
    }
    
    .excel-cell.selected-data-start {
        background-color: #ff9800 !important;
        color: white;
        font-weight: bold;
    }
    
    .excel-cell.deleted-row {
        background-color: #ffcdd2 !important;
        text-decoration: line-through;
        opacity: 0.6;
    }
    
    .excel-cell.deleted-col {
        background-color: #ffcdd2 !important;
        text-decoration: line-through;
        opacity: 0.6;
    }
    
    .mapping-legend {
        display: flex;
        gap: 15px;
        margin: 10px 0;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 3px;
    }
    
    .selection-mode {
        margin: 15px 0;
    }
    
    .selection-mode button {
        margin: 0 5px 5px 0;
    }
    
    .selection-mode button.active {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    
    .loading-content {
        background: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
    }
</style>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Import Grilles Anciennes - Mapping Visuel</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Import Visuel</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Indicateur d'étapes -->
                        <div class="step-indicator">
                            <div class="step active" id="step1">
                                <i class="bi bi-upload"></i>
                                <div>1. Upload du fichier</div>
                            </div>
                            <div class="step" id="step2">
                                <i class="bi bi-eye"></i>
                                <div>2. Visualisation & Mapping</div>
                            </div>
                            <div class="step" id="step3">
                                <i class="bi bi-gear"></i>
                                <div>3. Configuration</div>
                            </div>
                            <div class="step" id="step4">
                                <i class="bi bi-check-circle"></i>
                                <div>4. Import</div>
                            </div>
                        </div>
                        
                        <!-- Étape 1: Upload -->
                        <div id="uploadStep">
                            <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                    <strong>Nouveau !</strong> Utilisez notre modèle standard pour un import sans mapping manuel.
                                </div>
                                <a href="controller/generer_modele_grille.php" class="btn btn-success btn-sm" target="_blank">
                                    <i class="bi bi-download"></i> Télécharger le modèle Excel
                                </a>
                            </div>

                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-cloud-upload fa-3x mb-3 text-primary"></i>
                                <h5>Glissez-déposez votre fichier Excel ici</h5>
                                <p class="text-muted">ou</p>
                                <input type="file" id="fileInput" accept=".xlsx,.xls" style="display: none;">
                                <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                    <i class="bi bi-folder-open"></i> Parcourir
                                </button>
                                <p class="text-muted mt-3">Formats acceptés: .xlsx, .xls | Taille max: 50MB</p>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Formats acceptés</h6>
                                <ul class="mb-0">
                                    <li><strong>Modèle standard e-Gestion :</strong> mapping automatique — aucune configuration requise</li>
                                    <li><strong>Autre fichier Excel :</strong> mapping visuel manuel (sélection des colonnes)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Étape 2: Visualisation et Mapping -->
                        <div id="mappingStep" style="display: none;">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6><i class="bi bi-table"></i> Aperçu du fichier Excel</h6>
                                    <div class="selection-mode">
                                        <label>Mode de sélection :</label>
                                        <button type="button" class="btn btn-warning btn-sm" data-mode="matricule">
                                            <i class="bi bi-person-badge"></i> Matricule
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm" data-mode="nom">
                                            <i class="bi bi-person"></i> Nom
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" data-mode="ue">
                                            <i class="bi bi-book"></i> UE
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm" data-mode="ecue">
                                            <i class="bi bi-bookmark"></i> ECUE
                                        </button>
                                        <button type="button" class="btn btn-dark btn-sm" data-mode="credits_ligne">
                                            <i class="bi bi-coin"></i> Ligne crédits
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm" data-mode="data-start">
                                        <i class="bi bi-arrow-down-right"></i> Début des données
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-mode="delete-row">
                                        <i class="bi bi-dash-square"></i> Supprimer ligne
                                        </button>
                        <button type="button" class="btn btn-danger btn-sm" data-mode="delete-col">
                            <i class="bi bi-dash-square-fill"></i> Supprimer colonne
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelections()">
                            <i class="bi bi-x-circle"></i> Effacer
                        </button>
                                    </div>
                                    
                                    <div class="mapping-legend">
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #ffeb3b;"></div>
                                            <span>Matricule</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #4caf50;"></div>
                                            <span>Nom</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #2196f3;"></div>
                                            <span>UE</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #17a2b8;"></div>
                                            <span>ECUE</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #6c757d;"></div>
                                            <span>Ligne crédits</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #ff9800;"></div>
                                            <span>Début données</span>
                                        </div>
                                    </div>
                                    
                                    <div class="excel-preview" id="excelPreview" style="overflow-x: auto;">
                                        <!-- Contenu généré dynamiquement -->
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <h6><i class="bi bi-list-check"></i> Mapping détecté</h6>
                                    <div id="autoMappingBanner" style="display:none;" class="alert alert-success py-2 mb-2">
                                        <i class="bi bi-magic"></i> <strong>Modèle standard détecté !</strong><br>
                                        <small>Le mapping a été pré-rempli automatiquement. Vérifiez ci-dessous puis cliquez sur Suivant.</small>
                                    </div>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div id="mappingResults">
                                                <div class="alert alert-warning">
                                                    <small>Sélectionnez les zones dans le tableau à gauche</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <h6><i class="bi bi-info-circle"></i> Instructions</h6>
                                        <small class="text-muted">
                                            1. Sélectionnez le mode de mapping<br>
                                            2. Cliquez sur les cellules correspondantes<br>
                                            3. Pour les UE/matières, sélectionnez toute la ligne d'en-têtes<br>
                                            4. Indiquez la première cellule de données étudiant
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Étape 3: Configuration -->
                        <div id="configStep" style="display: none;">
                            <div id="autoConfigBanner" style="display:none;" class="alert alert-success mb-3">
                                <i class="bi bi-magic"></i> <strong>Configuration automatique appliquée</strong> —
                                les champs ci-dessous ont été pré-remplis depuis le fichier. Vérifiez et cliquez sur <strong>Lancer l'import</strong>.
                            </div>
                            <form id="importForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Année Académique <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="annee_academique" 
                                                   placeholder="Ex: 2018-2019, 2019-2020..." required>
                                            <div class="form-text">Saisie libre pour les années antérieures</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Session <span class="text-danger">*</span></label>
                                            <select class="form-select" name="session" required>
                                                <option value="">-- Sélectionner --</option>
                                                <option value="principale">Session Principale</option>
                                                <option value="rattrapage">Session de Rattrapage</option>
                                            </select>
                                            <div class="form-text">
                                                <small><strong>Note :</strong> En rattrapage, seules les matières non validées en session principale seront recalculées.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Promotion <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="promotion" 
                                                   placeholder="Ex: L1 Informatique, M2 Génie Civil..." required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Semestre(s)</label>
                                            <select class="form-select" name="semestre">
                                                <option value="annuel">Année complète (S1+S2)</option>
                                                <option value="S1">Semestre 1</option>
                                                <option value="S2">Semestre 2</option>
                                                <option value="S3">Semestre 3</option>
                                                <option value="S4">Semestre 4</option>
                                                <option value="S5">Semestre 5</option>
                                                <option value="S6">Semestre 6</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Section / Faculté</label>
                                            <select class="form-select" name="section_id">
                                                <option value="">-- Aucune section --</option>
                                                <?php if (!empty($sections)): ?>
                                                    <?php foreach ($sections as $section): ?>
                                                        <option value="<?= $section['idsection'] ?>">
                                                            <?= htmlspecialchars($section['designationSection']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <div class="form-text">
                                                <small><i class="bi bi-info-circle"></i> Associer à une section permet d'afficher les contacts de la section sur les documents générés</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configuration des semestres pour UE -->
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="deuxSemestres" onchange="toggleSemestreMode()">
                                        <label class="form-check-label" for="deuxSemestres">
                                            Cette grille contient les données de deux semestres
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Association UE-ECUE -->
                                <div class="mb-3">
                                    <label class="form-label">Association UE ↔ ECUE</label>
                                    <div id="ueEcueAssociation" class="border rounded p-3 bg-light">
                                        <!-- Sera rempli dynamiquement -->
                                    </div>
                                    <div class="form-text">
                                        <small>Associez chaque ECUE à son UE correspondante. Les crédits seront lus depuis la ligne sélectionnée ou saisis manuellement.</small>
                                    </div>
                                </div>
                                
                                <!-- Options d'import -->
                                <div class="mb-3">
                                    <label class="form-label">Options d'import</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="calculer_moyennes" id="calculerMoyennes" checked>
                                        <label class="form-check-label" for="calculerMoyennes">
                                            Calculer automatiquement les moyennes et validations
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ignorer_notes_vides" id="ignorerNotesVides">
                                        <label class="form-check-label" for="ignorerNotesVides">
                                            Ignorer les cellules vides (ne pas les considérer comme des 0)
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Étape 4: Validation -->
                        <div id="validationStep" style="display: none;">
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle"></i> Import terminé avec succès!</h5>
                                <div id="importResults">
                                    <!-- Résultats d'import -->
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="?view=deliberation/grilles_anciennes" class="btn btn-primary">
                                    <i class="bi bi-list"></i> Voir les grilles importées
                                </a>
                                <button class="btn btn-success" onclick="location.reload()">
                                    <i class="bi bi-plus"></i> Nouvel import
                                </button>
                            </div>
                        </div>
                        
                        <!-- Boutons de navigation -->
                        <div class="mt-4 d-flex justify-content-between">
                            <button class="btn btn-secondary" id="btnPrevious" style="display: none;" onclick="previousStep()">
                                <i class="bi bi-arrow-left"></i> Précédent
                            </button>
                            <button class="btn btn-primary" id="btnNext" style="display: none;" onclick="nextStep()">
                                Suivant <i class="bi bi-arrow-right"></i>
                            </button>
                            <button class="btn btn-success" id="btnProcess" style="display: none;" onclick="processImport()">
                                <i class="bi bi-download"></i> Lancer l'import
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Historique des imports -->
                <?php if (!empty($imports)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="bi bi-clock-history"></i> Historique des imports récents</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Année</th>
                                        <th>Session</th>
                                        <th>Promotion</th>
                                        <th>Étudiants</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($imports, 0, 10) as $import): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($import['date_import'])) ?></td>
                                        <td><?= htmlspecialchars($import['annee_academique']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $import['session'] === 'principale' ? 'primary' : 'warning' ?>">
                                                <?= htmlspecialchars($import['session']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($import['promotion']) ?></td>
                                        <td><?= $import['nombre_etudiants'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="voirDetails(<?= $import['id'] ?>)" title="Voir détails">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="supprimerImport(<?= $import['id'] ?>)" title="Supprimer">
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
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <p id="loadingMessage">Traitement en cours...</p>
    </div>
</div>

<script>
let currentStep = 1;
let uploadedFileData = null;
let excelData = null;
let mappingConfig = {
    matricule: null,
    nom: null,
    ue_columns: [],
    ecue_columns: [],
    data_start_row: null,
    data_start_col: null,
    deleted_rows: new Set(),
    deleted_cols: new Set()
};
let currentSelectionMode = null;
let ueEcueAssociation = []; // Association UE-ECUE pendant le mapping
let ueConfig = []; // Configuration des UE et ECUE

// Gestion du drag & drop
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFile(e.target.files[0]);
    }
});

function handleFile(file) {
    const validTypes = [
        'application/vnd.ms-excel', 
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!validTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Format invalide',
            text: 'Veuillez sélectionner un fichier Excel (.xls ou .xlsx)'
        });
        return;
    }
    
    if (file.size > 50 * 1024 * 1024) { // 50MB
        Swal.fire({
            icon: 'error',
            title: 'Fichier trop volumineux',
            text: 'Le fichier ne doit pas dépasser 50 MB'
        });
        return;
    }
    
    showLoading('Analyse du fichier Excel...');
    
    const formData = new FormData();
    formData.append('fichier', file);
    
    fetch('controller/analyser_excel_visuel.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            excelData = data.donnees;
            uploadedFileData = data;

            // Auto-mapping si le fichier suit le modèle standard
            if (data.suggestions && data.suggestions.auto_mapping) {
                applyAutoMapping(data.suggestions.auto_mapping);
            }

            document.getElementById('btnNext').style.display = 'block';

            const isAutoMapped = data.suggestions && data.suggestions.auto_mapping;
            Swal.fire({
                icon: 'success',
                title: isAutoMapped ? 'Modèle standard détecté !' : 'Fichier analysé',
                html: isAutoMapped
                    ? `<p>${data.lignes} lignes et ${data.colonnes} colonnes.<br><strong>Mapping automatique appliqué.</strong></p>`
                    : `${data.lignes} lignes et ${data.colonnes} colonnes détectées`,
                timer: 2500
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'analyse',
                text: data.message
            });
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors de l\'analyse du fichier'
        });
    });
}

function nextStep() {
    if (currentStep === 1) {
        // Passer au mapping
        document.getElementById('uploadStep').style.display = 'none';
        document.getElementById('mappingStep').style.display = 'block';
        document.getElementById('btnNext').textContent = 'Suivant ';
        document.getElementById('btnNext').innerHTML += '<i class="bi bi-arrow-right"></i>';
        document.getElementById('btnPrevious').style.display = 'block';
        
        document.getElementById('step1').classList.remove('active');
        document.getElementById('step1').classList.add('completed');
        document.getElementById('step2').classList.add('active');
        
        // Afficher le contenu Excel
        displayExcelPreview();

        // Si auto-mapping disponible, colorer les cellules et afficher la bannière
        if (uploadedFileData && uploadedFileData.suggestions && uploadedFileData.suggestions.auto_mapping) {
            document.getElementById('autoMappingBanner').style.display = 'block';
            highlightAutoMappedCells();
        }

        currentStep = 2;
    } else if (currentStep === 2) {
        // Vérifier que le mapping est complet
        if (!validateMapping()) {
            return;
        }
        
        // Passer à la configuration
        document.getElementById('mappingStep').style.display = 'none';
        document.getElementById('configStep').style.display = 'block';
        document.getElementById('btnNext').style.display = 'none';
        document.getElementById('btnProcess').style.display = 'block';
        
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.add('completed');
        document.getElementById('step3').classList.add('active');
        
        // Préparer la configuration
        prepareConfiguration();

        // Afficher la bannière si auto-mapping actif
        if (uploadedFileData && uploadedFileData.suggestions && uploadedFileData.suggestions.auto_mapping) {
            document.getElementById('autoConfigBanner').style.display = 'block';
        }

        currentStep = 3;
    }
}

function previousStep() {
    if (currentStep === 2) {
        // Retour à l'upload
        document.getElementById('mappingStep').style.display = 'none';
        document.getElementById('uploadStep').style.display = 'block';
        document.getElementById('btnNext').style.display = 'block';
        document.getElementById('btnPrevious').style.display = 'none';
        
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step1').classList.remove('completed');
        document.getElementById('step1').classList.add('active');
        
        currentStep = 1;
    } else if (currentStep === 3) {
        // Retour au mapping
        document.getElementById('configStep').style.display = 'none';
        document.getElementById('mappingStep').style.display = 'block';
        document.getElementById('btnNext').style.display = 'block';
        document.getElementById('btnProcess').style.display = 'none';
        
        document.getElementById('step3').classList.remove('active');
        document.getElementById('step2').classList.remove('completed');
        document.getElementById('step2').classList.add('active');
        
        currentStep = 2;
    }
}

function displayExcelPreview() {
    if (!excelData || excelData.length === 0) return;
    
    const previewContainer = document.getElementById('excelPreview');
    
    // Afficher toutes les colonnes mais limiter les lignes pour la performance
    const maxRows = Math.min(25, excelData.length);
    const maxCols = excelData[0].length; // Toutes les colonnes
    
    let html = '<table class="table table-sm table-bordered" style="white-space: nowrap; min-width: 100%;">';
    
    // En-tête avec numéros de colonnes
    html += '<thead class="table-light"><tr><th>#</th>';
    for (let col = 0; col < maxCols; col++) {
        html += `<th class="text-center" style="min-width: 100px;">Col ${col + 1}</th>`;
    }
    html += '</tr></thead><tbody>';
    
    for (let row = 0; row < maxRows; row++) {
        html += `<tr><td class="fw-bold">${row + 1}</td>`;
        for (let col = 0; col < maxCols; col++) {
            const cellValue = excelData[row][col] || '';
            const cellId = `cell_${row}_${col}`;
            
            html += `<td class="excel-cell" id="${cellId}" data-row="${row}" data-col="${col}" 
                        onclick="selectCell(${row}, ${col})" 
                        style="cursor: pointer; max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
                        ${cellValue}
                     </td>`;
        }
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    
    if (excelData.length > maxRows) {
        html += `<div class="text-muted text-center mt-2">Aperçu limité aux ${maxRows} premières lignes - ${excelData.length} lignes au total</div>`;
    }
    
    previewContainer.innerHTML = html;
    
    // Ajouter les gestionnaires de mode de sélection
    document.querySelectorAll('[data-mode]').forEach(btn => {
        btn.addEventListener('click', function() {
            setSelectionMode(this.dataset.mode);
        });
    });
}

function setSelectionMode(mode) {
    currentSelectionMode = mode;
    
    // Mettre à jour l'apparence des boutons
    document.querySelectorAll('[data-mode]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
    
    // Changer le curseur
    document.getElementById('excelPreview').style.cursor = 'crosshair';
}

function selectCell(row, col) {
    if (!currentSelectionMode) {
        Swal.fire({
            icon: 'warning',
            title: 'Mode de sélection',
            text: 'Veuillez d\'abord sélectionner un mode de sélection'
        });
        return;
    }
    
    const cellId = `cell_${row}_${col}`;
    const cell = document.getElementById(cellId);
    
    switch (currentSelectionMode) {
        case 'matricule':
            // Effacer la sélection précédente
            document.querySelectorAll('.selected-matricule').forEach(c => {
                c.classList.remove('selected-matricule');
            });
            cell.classList.add('selected-matricule');
            mappingConfig.matricule = { row: row, col: col };
            break;
            
        case 'nom':
            document.querySelectorAll('.selected-nom').forEach(c => {
                c.classList.remove('selected-nom');
            });
            cell.classList.add('selected-nom');
            mappingConfig.nom = { row: row, col: col };
            break;
            
        case 'ue':
            cell.classList.toggle('selected-ue');
            
            // Gérer la liste des UE
            const existingUEIndex = mappingConfig.ue_columns.findIndex(ue => ue.row === row && ue.col === col);
            if (existingUEIndex >= 0) {
                mappingConfig.ue_columns.splice(existingUEIndex, 1);
            } else {
                mappingConfig.ue_columns.push({
                    row: row,
                    col: col,
                    nom: excelData[row][col] || `UE_${col}`,
                    id: 'ue_' + Date.now()
                });
            }
            break;
            
        case 'ecue':
            cell.classList.toggle('selected-ecue');
            
            // Gérer la liste des ECUE
            const existingECUEIndex = mappingConfig.ecue_columns.findIndex(ecue => ecue.row === row && ecue.col === col);
            if (existingECUEIndex >= 0) {
                mappingConfig.ecue_columns.splice(existingECUEIndex, 1);
            } else {
                mappingConfig.ecue_columns.push({
                    row: row,
                    col: col,
                    nom: excelData[row][col] || `ECUE_${col}`,
                    id: 'ecue_' + Date.now(),
                    ue_associee: null // Sera définie lors de l'association
                });
            }
            break;
            
        case 'credits_ligne':
            document.querySelectorAll('.selected-credits-ligne').forEach(c => {
                c.classList.remove('selected-credits-ligne');
            });
            cell.classList.add('selected-credits-ligne');
            mappingConfig.credits_ligne_row = row;
            break;
            
        case 'data-start':
            document.querySelectorAll('.selected-data-start').forEach(c => {
                c.classList.remove('selected-data-start');
            });
            cell.classList.add('selected-data-start');
            mappingConfig.data_start_row = row;
            mappingConfig.data_start_col = col;
            break;
            
        case 'delete-row':
            if (mappingConfig.deleted_rows.has(row)) {
                mappingConfig.deleted_rows.delete(row);
                // Retirer la classe de suppression de toute la ligne
                for (let c = 0; c < excelData[0].length && c < 15; c++) {
                    const cellToUpdate = document.getElementById(`cell_${row}_${c}`);
                    if (cellToUpdate) {
                        cellToUpdate.classList.remove('deleted-row');
                    }
                }
            } else {
                mappingConfig.deleted_rows.add(row);
                // Ajouter la classe de suppression à toute la ligne
                for (let c = 0; c < excelData[0].length && c < 15; c++) {
                    const cellToUpdate = document.getElementById(`cell_${row}_${c}`);
                    if (cellToUpdate) {
                        cellToUpdate.classList.add('deleted-row');
                    }
                }
            }
            break;
            
        case 'delete-col':
            if (mappingConfig.deleted_cols.has(col)) {
                mappingConfig.deleted_cols.delete(col);
                // Retirer la classe de suppression de toute la colonne
                for (let r = 0; r < excelData.length && r < 20; r++) {
                    const cellToUpdate = document.getElementById(`cell_${r}_${col}`);
                    if (cellToUpdate) {
                        cellToUpdate.classList.remove('deleted-col');
                    }
                }
            } else {
                mappingConfig.deleted_cols.add(col);
                // Ajouter la classe de suppression à toute la colonne
                for (let r = 0; r < excelData.length && r < 20; r++) {
                    const cellToUpdate = document.getElementById(`cell_${r}_${col}`);
                    if (cellToUpdate) {
                        cellToUpdate.classList.add('deleted-col');
                    }
                }
            }
            break;
    }
    
    updateMappingResults();
}

function clearSelections() {
    document.querySelectorAll('.excel-cell').forEach(cell => {
        cell.className = 'excel-cell';
    });
    
    mappingConfig = {
        matricule: null,
        nom: null,
        ue_columns: [],
        ecue_columns: [],
        credits_ligne_row: null,
        data_start_row: null,
        data_start_col: null,
        deleted_rows: new Set(),
        deleted_cols: new Set()
    };
    
    updateMappingResults();
}

function updateMappingResults() {
    const container = document.getElementById('mappingResults');
    
    let html = '<div class="mapping-summary">';
    
    if (mappingConfig.matricule) {
        html += `<div class="alert alert-success p-2 mb-2">
                    <small><strong>Matricule :</strong> Ligne ${mappingConfig.matricule.row + 1}, Colonne ${mappingConfig.matricule.col + 1}</small>
                 </div>`;
    }
    
    if (mappingConfig.nom) {
        html += `<div class="alert alert-success p-2 mb-2">
                    <small><strong>Nom :</strong> Ligne ${mappingConfig.nom.row + 1}, Colonne ${mappingConfig.nom.col + 1}</small>
                 </div>`;
    }
    
    if (mappingConfig.ue_columns.length > 0) {
        html += `<div class="alert alert-primary p-2 mb-2">
                    <small><strong>UE :</strong> ${mappingConfig.ue_columns.length} colonnes sélectionnées</small>
                    <ul class="mb-0 mt-1">`;
        mappingConfig.ue_columns.forEach(ue => {
            html += `<li><small>${ue.nom}</small></li>`;
        });
        html += `</ul></div>`;
    }
    
    if (mappingConfig.ecue_columns.length > 0) {
        html += `<div class="alert alert-info p-2 mb-2">
                    <small><strong>ECUE :</strong> ${mappingConfig.ecue_columns.length} colonnes sélectionnées</small>
                    <ul class="mb-0 mt-1">`;
        mappingConfig.ecue_columns.forEach(ecue => {
            html += `<li><small>${ecue.nom} ${ecue.ue_associee ? '→ ' + ecue.ue_associee : ''}</small></li>`;
        });
        html += `</ul></div>`;
    }
    
    if (mappingConfig.data_start_row !== null) {
        html += `<div class="alert alert-warning p-2 mb-2">
                    <small><strong>Début données :</strong> Ligne ${mappingConfig.data_start_row + 1}, Colonne ${mappingConfig.data_start_col + 1}</small>
                 </div>`;
    }
    
    html += '</div>';
    
    if (!mappingConfig.matricule || !mappingConfig.nom || mappingConfig.ecue_columns.length === 0 || mappingConfig.data_start_row === null) {
        html += '<div class="alert alert-warning p-2"><small>Mapping incomplet - Il faut au moins: Matricule, Nom, ECUE et Début des données</small></div>';
    } else {
        html += '<div class="alert alert-success p-2"><small><i class="bi bi-check"></i> Mapping complet</small></div>';
    }
    
    container.innerHTML = html;
}

function validateMapping() {
    if (!mappingConfig.matricule || !mappingConfig.nom || mappingConfig.ecue_columns.length === 0 || mappingConfig.data_start_row === null) {
        Swal.fire({
            icon: 'error',
            title: 'Mapping incomplet',
            text: 'Veuillez sélectionner au minimum : Matricule, Nom, ECUE et le début des données'
        });
        return false;
    }
    return true;
}

function prepareConfiguration() {
    displayUEEcueAssociation();

    // Si auto-mapping : charger les crédits et recalculer automatiquement
    if (uploadedFileData && uploadedFileData.suggestions && uploadedFileData.suggestions.auto_mapping) {
        loadCreditsFromExcel();
        recalculerTousLesCreditsUE();
    }
}

function displayUEEcueAssociation() {
    const container = document.getElementById('ueEcueAssociation');
    
    if (mappingConfig.ue_columns.length === 0 || mappingConfig.ecue_columns.length === 0) {
        container.innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> 
                Veuillez d'abord sélectionner des UE et des ECUE dans l'étape précédente.
            </div>
        `;
        return;
    }
    
    let html = '<div class="row">';
    
    // Colonne UE
    html += `
        <div class="col-md-5">
            <h6 class="text-primary"><i class="bi bi-book"></i> UE disponibles</h6>
            <div class="list-group" id="ueList">
    `;
    
    mappingConfig.ue_columns.forEach((ue, index) => {
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>${ue.nom}</strong>
                        <br><small class="text-muted">Ligne ${ue.row + 1}, Col ${ue.col + 1}</small>
                    </div>
                    <input type="text" class="form-control form-control-sm" style="width: 80px;"
                           placeholder="Crédits" value="0" id="credits_ue_${index}" readonly>
                </div>
                <div>
                    <label class="form-label mb-1"><small>Semestre de cette UE :</small></label>
                    <select class="form-select form-select-sm" onchange="updateUESemestre(${index}, this.value)">
                        <option value="S1">Semestre 1</option>
                        <option value="S2">Semestre 2</option>
                        <option value="S3">Semestre 3</option>
                        <option value="S4">Semestre 4</option>
                        <option value="S5">Semestre 5</option>
                        <option value="S6">Semestre 6</option>
                    </select>
                </div>
            </div>`;
    });
    
    html += `
            </div>
        </div>
        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-left-right fs-1 text-muted"></i>
        </div>
        <div class="col-md-5">
            <h6 class="text-info"><i class="bi bi-bookmark"></i> ECUE à associer</h6>
            <div class="list-group" id="ecueList">
    `;
    
    mappingConfig.ecue_columns.forEach((ecue, index) => {
        html += `
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>${ecue.nom}</strong>
                        <br><small class="text-muted">Ligne ${ecue.row + 1}, Col ${ecue.col + 1}</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label text-xs">UE parente:</label>
                        <select class="form-select form-select-sm" onchange="associerEcueAUe(${index}, this.value)">
                            <option value="">-- Choisir UE --</option>
        `;
        
        mappingConfig.ue_columns.forEach((ue, ueIndex) => {
            const sel = (ecue.ue_associee !== null && ecue.ue_associee !== undefined && ecue.ue_associee === ueIndex) ? 'selected' : '';
            html += `<option value="${ueIndex}" ${sel}>${ue.nom}</option>`;
        });
        
        html += `
                        </select>
                    </div>
                    <div class="col-6">
                    <label class="form-label text-xs">Crédits:</label>
                    <input type="number" class="form-control form-control-sm" 
                    value="" min="0.5" max="10" step="0.5" id="credits_ecue_${index}" 
                               placeholder="Auto si ligne crédits">
                         <small class="text-muted">Laissez vide pour lecture automatique</small>
                     </div>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
        </div>
    </div>
    `;
    
    container.innerHTML = html;
}

function displayUEEcueConfiguration() {
    const container = document.getElementById('ueEcueConfiguration');
    
    let html = '';
    
    ueConfig.forEach((ue, ueIndex) => {
        html += `
            <div class="card mb-3" id="ue_card_${ueIndex}">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label mb-0">Code UE:</label>
                            <input type="text" class="form-control form-control-sm" 
                                   value="${ue.code}" 
                                   onchange="updateUECode(${ueIndex}, this.value)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-0">Nom UE:</label>
                            <input type="text" class="form-control form-control-sm" 
                                   value="${ue.nom}" 
                                   onchange="updateUENom(${ueIndex}, this.value)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0">Crédits totaux:</label>
                            <input type="number" class="form-control form-control-sm" 
                            value="${ue.credits_total}" 
                            readonly style="background-color: #e9ecef;">
                            </div>
                            </div>
                     <div class="row mt-2" id="semestreRow_${ueIndex}" style="display: none;">
                         <div class="col-md-6">
                             <label class="form-label mb-0">Semestre:</label>
                             <select class="form-select form-select-sm" onchange="updateUESemestre(${ueIndex}, this.value)">
                                 <option value="S1" ${ue.semestre === 'S1' ? 'selected' : ''}>Semestre 1</option>
                                 <option value="S2" ${ue.semestre === 'S2' ? 'selected' : ''}>Semestre 2</option>
                             </select>
                         </div>
                     </div>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">ECUE de cette UE:</h6>
                    <div id="ecues_container_${ueIndex}">
        `;
        
        ue.ecues.forEach((ecue, ecueIndex) => {
            html += `
                <div class="row mb-2 align-items-center ecue-row" id="ecue_row_${ueIndex}_${ecueIndex}">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" 
                               placeholder="Code ECUE" 
                               value="${ecue.code}"
                               onchange="updateECUECode(${ueIndex}, ${ecueIndex}, this.value)">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" 
                               placeholder="Nom ECUE" 
                               value="${ecue.nom}"
                               onchange="updateECUENom(${ueIndex}, ${ecueIndex}, this.value)">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" 
                        placeholder="Crédit" 
                        value="${ecue.credit}" 
                        min="0.5" max="10" step="0.5"
                        onchange="updateECUECredit(${ueIndex}, ${ecueIndex}, this.value)">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" 
                               placeholder="Crédits" 
                               value="${ecue.credits}" 
                               min="1" max="20"
                               onchange="updateECUECredits(${ueIndex}, ${ecueIndex}, this.value)">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="supprimerECUE(${ueIndex}, ${ecueIndex})"
                                ${ue.ecues.length <= 1 ? 'disabled' : ''}>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            onclick="ajouterECUE(${ueIndex})">
                        <i class="bi bi-plus-circle"></i> Ajouter ECUE
                    </button>
                </div>
            </div>
        `;
    });
    
    if (ueConfig.length === 0) {
        html = '<div class="alert alert-info">Aucune UE configurée. Veuillez d\'abord sélectionner des colonnes de matières dans l\'étape précédente.</div>';
    }
    
    container.innerHTML = html;
}

function ajouterUE() {
    const nouvelleUE = {
        id: 'ue_' + ueConfig.length,
        nom: 'Nouvelle UE',
        code: 'NOUV' + (ueConfig.length + 1),
        ecues: [{
            id: 'ecue_' + ueConfig.length + '_0',
            nom: 'Nouvelle ECUE',
            code: 'NOUV' + (ueConfig.length + 1) + '_01',
            coefficient: 1,
            credits: 3,
            colonne_excel: null
        }],
        credits_total: 3
    };
    
    ueConfig.push(nouvelleUE);
    displayUEEcueConfiguration();
}

function ajouterECUE(ueIndex) {
    const nouveauECUE = {
        id: 'ecue_' + ueIndex + '_' + ueConfig[ueIndex].ecues.length,
        nom: 'Nouvelle ECUE',
        code: ueConfig[ueIndex].code + '_0' + (ueConfig[ueIndex].ecues.length + 1),
        coefficient: 1,
        credits: 3,
        colonne_excel: null
    };
    
    ueConfig[ueIndex].ecues.push(nouveauECUE);
    calculerCreditsUE(ueIndex);
    displayUEEcueConfiguration();
}

function supprimerECUE(ueIndex, ecueIndex) {
    if (ueConfig[ueIndex].ecues.length > 1) {
        ueConfig[ueIndex].ecues.splice(ecueIndex, 1);
        calculerCreditsUE(ueIndex);
        displayUEEcueConfiguration();
    }
}

function updateUECode(ueIndex, value) {
    ueConfig[ueIndex].code = value;
}

function updateUENom(ueIndex, value) {
    ueConfig[ueIndex].nom = value;
}

function updateECUECode(ueIndex, ecueIndex, value) {
    ueConfig[ueIndex].ecues[ecueIndex].code = value;
}

function updateECUENom(ueIndex, ecueIndex, value) {
    ueConfig[ueIndex].ecues[ecueIndex].nom = value;
}

function updateECUECoeff(ueIndex, ecueIndex, value) {
    ueConfig[ueIndex].ecues[ecueIndex].coefficient = parseFloat(value) || 1;
}

function updateECUECredits(ueIndex, ecueIndex, value) {
    ueConfig[ueIndex].ecues[ecueIndex].credits = parseInt(value) || 1;
    calculerCreditsUE(ueIndex);
}

function calculerCreditsUE(ueIndex) {
    const creditsTotal = ueConfig[ueIndex].ecues.reduce((sum, ecue) => sum + (ecue.credits || 0), 0);
    ueConfig[ueIndex].credits_total = creditsTotal;
    
    // Mettre à jour l'affichage
    const creditInput = document.querySelector(`#ue_card_${ueIndex} input[readonly]`);
    if (creditInput) {
        creditInput.value = creditsTotal;
    }
}

function genererCodeUE(nom) {
    return nom.toUpperCase()
              .replace(/[^A-Z0-9\s]/g, '')
              .split(' ')
              .map(word => word.substring(0, 3))
              .join('')
              .substring(0, 6);
}

function processImport() {
    const form = document.getElementById('importForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Vérifier les associations ECUE-UE
    const ecuesNonAssociees = mappingConfig.ecue_columns.filter(ecue => 
        ecue.ue_associee === null || ecue.ue_associee === undefined
    );
    
    if (ecuesNonAssociees.length > 0) {
        Swal.fire({
            title: 'ECUE non associées détectées',
            html: `<p><strong>${ecuesNonAssociees.length} ECUE ne sont pas associées à une UE :</strong></p>
                   <ul style="text-align: left;">
                   ${ecuesNonAssociees.map(ecue => `<li>${ecue.nom}</li>`).join('')}
                   </ul>
                   <p>Elles seront automatiquement placées dans une UE par défaut. Voulez-vous continuer ?</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Continuer',
            cancelButtonText: 'Corriger'
        }).then((result) => {
            if (result.isConfirmed) {
                proceedWithImport();
            }
        });
        return;
    }
    
    proceedWithImport();
}

function proceedWithImport() {
    
    showLoading('Import en cours... Cela peut prendre quelques minutes.');
    
    // Préparer les données d'import
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    // Convertir les Sets en arrays pour JSON
    const mappingConfigForJSON = {
        ...mappingConfig,
        deleted_rows: Array.from(mappingConfig.deleted_rows),
        deleted_cols: Array.from(mappingConfig.deleted_cols)
    };
    
    // Ajouter le mapping et les données Excel
    formData.append('mapping_config', JSON.stringify(mappingConfigForJSON));
    formData.append('excel_data', JSON.stringify(excelData)); // Gardé pour compatibilité
    
    // Ajouter le tempId pour récupérer les données complètes
    if (uploadedFileData && uploadedFileData.tempId) {
        formData.append('tempId', uploadedFileData.tempId);
    }
    
    // Construire la configuration UE/ECUE depuis le mapping
    const ueConfiguration = buildUEConfiguration();
    formData.append('ue_configuration', JSON.stringify(ueConfiguration));
    
    fetch('controller/importer_grille_visuelle.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            // Passer à l'étape de validation
            document.getElementById('configStep').style.display = 'none';
            document.getElementById('validationStep').style.display = 'block';
            document.getElementById('btnPrevious').style.display = 'none';
            document.getElementById('btnProcess').style.display = 'none';
            
            document.getElementById('step3').classList.remove('active');
            document.getElementById('step3').classList.add('completed');
            document.getElementById('step4').classList.add('active');
            
            // Afficher les résultats
            document.getElementById('importResults').innerHTML = `
                <p><strong>Résumé de l'import :</strong></p>
                <ul>
                    <li>Étudiants importés : <strong>${data.statistiques.etudiants}</strong></li>
                    <li>UE créées : <strong>${data.statistiques.ues}</strong></li>
                    <li>ECUE créées : <strong>${data.statistiques.ecues}</strong></li>
                    <li>Notes importées : <strong>${data.statistiques.notes}</strong></li>
                </ul>
            `;
            
            currentStep = 4;
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'import',
                text: data.message
            });
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors de l\'import'
        });
    });
}

// Appliquer le mapping automatique détecté depuis le modèle standard
function applyAutoMapping(autoMapping) {
    mappingConfig.matricule         = autoMapping.matricule;
    mappingConfig.nom               = autoMapping.nom;
    mappingConfig.ue_columns        = autoMapping.ue_columns;
    mappingConfig.ecue_columns      = autoMapping.ecue_columns;
    mappingConfig.credits_ligne_row = autoMapping.credits_ligne_row;
    mappingConfig.data_start_row    = autoMapping.data_start_row;
    mappingConfig.data_start_col    = autoMapping.data_start_col;
    mappingConfig.deleted_rows      = new Set();
    mappingConfig.deleted_cols      = new Set();

    // Pré-remplir le formulaire de configuration depuis les métadonnées
    if (autoMapping.metadata) {
        const meta = autoMapping.metadata;
        const f = document.getElementById('importForm');
        if (meta.annee_academique) f.querySelector('[name="annee_academique"]').value = meta.annee_academique;
        if (meta.promotion)        f.querySelector('[name="promotion"]').value        = meta.promotion;
        if (meta.session) {
            const sel = f.querySelector('[name="session"]');
            // Chercher l'option correspondante (principale ou rattrapage)
            for (const opt of sel.options) {
                if (opt.value === meta.session.toLowerCase().trim()) { sel.value = opt.value; break; }
            }
        }
        if (meta.semestre) {
            const sel = f.querySelector('[name="semestre"]');
            for (const opt of sel.options) {
                if (opt.value === meta.semestre.toLowerCase().trim()) { sel.value = opt.value; break; }
            }
        }
    }
}

// Colorier les cellules après affichage du tableau (auto-mapping)
function highlightAutoMappedCells() {
    // Matricule
    const mCell = document.getElementById(`cell_${mappingConfig.matricule.row}_${mappingConfig.matricule.col}`);
    if (mCell) mCell.classList.add('selected-matricule');

    // Nom
    const nCell = document.getElementById(`cell_${mappingConfig.nom.row}_${mappingConfig.nom.col}`);
    if (nCell) nCell.classList.add('selected-nom');

    // UE
    mappingConfig.ue_columns.forEach(ue => {
        const c = document.getElementById(`cell_${ue.row}_${ue.col}`);
        if (c) c.classList.add('selected-ue');
    });

    // ECUE
    mappingConfig.ecue_columns.forEach(ecue => {
        const c = document.getElementById(`cell_${ecue.row}_${ecue.col}`);
        if (c) c.classList.add('selected-ecue');
    });

    // Ligne crédits
    if (mappingConfig.credits_ligne_row !== null && excelData[mappingConfig.credits_ligne_row]) {
        for (let col = 0; col < excelData[mappingConfig.credits_ligne_row].length; col++) {
            const c = document.getElementById(`cell_${mappingConfig.credits_ligne_row}_${col}`);
            if (c) c.classList.add('selected-credits-ligne');
        }
    }

    // Début des données
    const dCell = document.getElementById(`cell_${mappingConfig.data_start_row}_${mappingConfig.data_start_col}`);
    if (dCell) dCell.classList.add('selected-data-start');

    updateMappingResults();
}

// Fonctions utilitaires
function showLoading(message) {
    document.getElementById('loadingMessage').textContent = message;
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function voirDetails(importId) {
    window.open(`?view=deliberation/grilles_anciennes&details=${importId}`, '_blank');
}

function supprimerImport(importId) {
    Swal.fire({
        title: 'Supprimer cet import ?',
        text: 'Cette action supprimera définitivement toutes les données associées.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`controller/delete_grille_ancienne.php?import_id=${importId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Supprimé',
                        text: 'L\'import a été supprimé avec succès'
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
            });
        }
    });
}

// Nouvelles fonctions pour les semestres et crédits
function toggleSemestreMode() {
    const deuxSemestres = document.getElementById('deuxSemestres').checked;
    
    // Afficher/masquer les sélecteurs de semestre pour chaque UE
    document.querySelectorAll('[id^="semestreRow_"]').forEach(row => {
        row.style.display = deuxSemestres ? 'block' : 'none';
    });
    
    // Mettre à jour l'interface si elle existe déjà
    if (document.getElementById('ueEcueAssociation').innerHTML.trim() !== '') {
        displayUEEcueAssociation();
    }
}

function updateUESemestre(ueIndex, semestre) {
    if (ueConfig[ueIndex]) {
        ueConfig[ueIndex].semestre = semestre;
    }
}

function updateECUECredit(ueIndex, ecueIndex, credit) {
    if (ueConfig[ueIndex] && ueConfig[ueIndex].ecues[ecueIndex]) {
        ueConfig[ueIndex].ecues[ecueIndex].credit = parseFloat(credit) || 1;
        recalculerCreditsUE(ueIndex);
    }
}

function recalculerCreditsUE(ueIndex) {
    if (!ueConfig[ueIndex]) return;
    
    let totalCredits = 0;
    ueConfig[ueIndex].ecues.forEach(ecue => {
        totalCredits += parseFloat(ecue.credit) || 0;
    });
    
    ueConfig[ueIndex].credits_total = totalCredits;
    
    // Mettre à jour l'affichage
    const creditInput = document.querySelector(`#ue_card_${ueIndex} input[readonly]`);
    if (creditInput) {
        creditInput.value = totalCredits;
    }
}

function loadCreditsFromExcel() {
    const creditsLigneRow = mappingConfig.credits_ligne_row;
    if (creditsLigneRow === null || !excelData[creditsLigneRow]) {
        return;
    }
    
    // Parcourir les ECUE et essayer de lire leurs crédits depuis Excel
    mappingConfig.ecue_columns.forEach((ecue, index) => {
        const creditValue = excelData[creditsLigneRow][ecue.col];
        if (creditValue && !isNaN(parseFloat(creditValue))) {
            const creditInput = document.getElementById(`credits_ecue_${index}`);
            if (creditInput && !creditInput.value) {
                creditInput.value = parseFloat(creditValue);
            }
        }
    });
    
    // Recalculer les crédits UE après avoir chargé les crédits ECUE
    recalculerTousLesCreditsUE();
}

function recalculerTousLesCreditsUE() {
    // Recalculer les crédits pour chaque UE dans l'interface
    mappingConfig.ue_columns.forEach((ue, ueIndex) => {
        let totalCredits = 0;
        
        // Additionner les crédits des ECUE associées à cette UE
        mappingConfig.ecue_columns.forEach((ecue, ecueIndex) => {
            if (ecue.ue_associee === ueIndex) {
                const creditInput = document.getElementById(`credits_ecue_${ecueIndex}`);
                if (creditInput && creditInput.value) {
                    totalCredits += parseFloat(creditInput.value) || 0;
                }
            }
        });
        
        // Mettre à jour l'affichage du crédit UE (dans l'input readonly)
        const creditUEInput = document.getElementById(`credits_ue_${ueIndex}`);
        if (creditUEInput) {
            creditUEInput.value = totalCredits;
        }
    });
}

// Appeler cette fonction après la configuration UE-ECUE
function associerEcueAUe(ecueIndex, ueIndex) {
    if (!mappingConfig.ecue_columns[ecueIndex]) return;
    
    mappingConfig.ecue_columns[ecueIndex].ue_associee = ueIndex !== '' ? parseInt(ueIndex) : null;
    
    // Charger automatiquement les crédits si ligne sélectionnée
    loadCreditsFromExcel();
    
    // Recalculer les crédits UE automatiquement
    recalculerTousLesCreditsUE();
}

// Construire la configuration UE/ECUE pour l'envoi au serveur
function buildUEConfiguration() {
    const ueConfiguration = [];
    
    // Créer un mapping UE basé sur les colonnes UE sélectionnées
    mappingConfig.ue_columns.forEach((ue, ueIndex) => {
        // Lire le semestre depuis le sélecteur si disponible
        const semestreSelect = document.querySelector(`select[onchange="updateUESemestre(${ueIndex}, this.value)"]`);
        const semestre = semestreSelect ? semestreSelect.value : 'S1';
        
        const ueConfig = {
            code: `UE${ueIndex + 1}`,
            nom: ue.nom,
            credits_total: 0,
            semestre: semestre,
            ecues: []
        };
        
        // Trouver les ECUE associées à cette UE
        mappingConfig.ecue_columns.forEach((ecue, ecueIndex) => {
            if (ecue.ue_associee === ueIndex) {
                // Lire le crédit depuis l'input si disponible
                const creditInput = document.getElementById(`credits_ecue_${ecueIndex}`);
                const credit = creditInput ? parseFloat(creditInput.value) || 1 : 1;
                
                const ecueConfig = {
                    code: `ECUE${ecueIndex + 1}`,
                    nom: ecue.nom,
                    credit: credit,
                    colonne_excel: ecue.col
                };
                
                ueConfig.ecues.push(ecueConfig);
                ueConfig.credits_total += credit;
            }
        });
        
        // Les crédits UE sont automatiquement la somme des crédits ECUE
        // (on ne lit plus depuis l'input, c'est calculé automatiquement)
        
        ueConfiguration.push(ueConfig);
    });
    
    // 🔥 CORRECTION: Créer une UE par défaut pour les ECUE non associées
    const ecuesNonAssociees = [];
    mappingConfig.ecue_columns.forEach((ecue, ecueIndex) => {
        if (ecue.ue_associee === null || ecue.ue_associee === undefined) {
            const creditInput = document.getElementById(`credits_ecue_${ecueIndex}`);
            const credit = creditInput ? parseFloat(creditInput.value) || 1 : 1;
            
            ecuesNonAssociees.push({
                code: `ECUE${ecueIndex + 1}`,
                nom: ecue.nom,
                credit: credit,
                colonne_excel: ecue.col
            });
        }
    });
    
    // Si il y a des ECUE non associées, créer une UE par défaut
    if (ecuesNonAssociees.length > 0) {
        const ueDefaut = {
            code: `UE_DEFAUT`,
            nom: 'UE Non Associées',
            credits_total: ecuesNonAssociees.reduce((sum, ecue) => sum + ecue.credit, 0),
            semestre: 'S1',
            ecues: ecuesNonAssociees
        };
        
        ueConfiguration.push(ueDefaut);
        
        console.warn(`⚠️ ${ecuesNonAssociees.length} ECUE non associées ajoutées à une UE par défaut`);
    }
    
    return ueConfiguration;
}
</script>

<?php include "./views/include/footer.php"; ?>
