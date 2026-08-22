<?php
include "./views/include/header.php";

$grilleAncienne = new GrilleAncienne();
$grilleAncienne->createTablesIfNotExists();
//$imports = $grilleAncienne->getImports(10); // Derniers 10 imports

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
    
    .file-info {
        margin-top: 20px;
        padding: 15px;
        background: #e7f3ff;
        border-radius: 5px;
        display: none;
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
        <h1>Import Grilles Anciennes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Archives</li>
                <li class="breadcrumb-item active">Import Grilles</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
    <!-- 1. CARTE D'IMPORT -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Indicateur d'étapes -->
                        <div class="step-indicator">
                            <div class="step active" id="step1">
                                <i class="fas fa-upload"></i>
                                <div>1. Upload du fichier</div>
                            </div>
                            <div class="step" id="step2">
                                <i class="fas fa-cog"></i>
                                <div>2. Configuration</div>
                            </div>
                            <div class="step" id="step3">
                                <i class="fas fa-check"></i>
                                <div>3. Validation</div>
                            </div>
                        </div>
                        
                        <!-- Étape 1: Upload -->
                        <div id="uploadStep">
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                <h5>Glissez-déposez votre fichier Excel ici</h5>
                                <p class="text-muted">ou</p>
                                <input type="file" id="fileInput" accept=".xlsx,.xls" style="display: none;">
                                <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open"></i> Parcourir
                                </button>
                                <p class="text-muted mt-3">Formats acceptés: .xlsx, .xls</p>
                            </div>
                            
                            <div class="file-info" id="fileInfo">
                                <h6><i class="fas fa-file-excel text-success"></i> Fichier sélectionné:</h6>
                                <p id="fileName"></p>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">Lignes détectées:</small>
                                        <p class="mb-0" id="rowCount">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Colonnes détectées:</small>
                                        <p class="mb-0" id="colCount">-</p>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Étudiants détectés:</small>
                                        <p class="mb-0" id="studentCount">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Étape 2: Configuration -->
                        <div id="configStep" style="display: none;">
                            <form id="importForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Année Académique <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="annee_academique" 
                                                   placeholder="Ex: 2020-2021" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Session <span class="text-danger">*</span></label>
                                            <select class="form-select" name="session" required>
                                                <option value="">-- Sélectionner --</option>
                                                <option value="Première session">Première session</option>
                                                <option value="Deuxième session">Deuxième session</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Promotion <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="promotion" 
                                                   placeholder="Ex: L1 Informatique" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Semestre(s)</label>
                                            <select class="form-select" name="semestre">
                                                <option value="">Année complète</option>
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
                                                <option value="">-- Sélectionner une section (optionnel) --</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?php echo $section['idsection']; ?>">
                                                        <?php echo htmlspecialchars($section['designationSection']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">
                                                Associer cette importation à une section permettra d'afficher les informations de contact dans les exports
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Aperçu de la structure détectée -->
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Structure détectée</h6>
                                    <div id="structurePreview"></div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Étape 3: Validation -->
                        <div id="validationStep" style="display: none;">
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> Import terminé avec succès!</h5>
                                <p class="mb-2">Résumé de l'import:</p>
                                <ul class="mb-0">
                                    <li>Étudiants importés: <strong id="importedStudents">0</strong></li>
                                    <li>Notes importées: <strong id="importedNotes">0</strong></li>
                                </ul>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="liste_etudiants.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> Voir les étudiants
                                </a>
                                <button class="btn btn-success" onclick="location.reload()">
                                    <i class="fas fa-plus"></i> Nouvel import
                                </button>
                            </div>
                        </div>
                        
                        <!-- Boutons de navigation -->
                        <div class="mt-4 d-flex justify-content-between">
                            <button class="btn btn-secondary" id="btnPrevious" style="display: none;" onclick="previousStep()">
                                <i class="fas fa-arrow-left"></i> Précédent
                            </button>
                            <button class="btn btn-primary" id="btnNext" style="display: none;" onclick="nextStep()">
                                Suivant <i class="fas fa-arrow-right"></i>
                            </button>
                            <button class="btn btn-success" id="btnProcess" style="display: none;" onclick="processImport()">
                                <i class="fas fa-check"></i> Lancer l'import
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Historique des imports -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Historique des imports</h5>
                    </div>
                    <div class="card-body">
                        <div class="import-history">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Année</th>
                                        <th>Session</th>
                                        <th>Promotion</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($imports as $import): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($import['date_import'])); ?></td>
                                        <td><?php echo htmlspecialchars($import['annee_academique']); ?></td>
                                        <td><?php echo htmlspecialchars($import['session']); ?></td>
                                        <td><?php echo htmlspecialchars($import['promotion']); ?></td>
                                        <td>
                                            <?php if ($import['statut'] == 'complete'): ?>
                                                <span class="badge bg-success">Complété</span>
                                            <?php elseif ($import['statut'] == 'en_cours'): ?>
                                                <span class="badge bg-warning">En cours</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Erreur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="liste_etudiants.php?import_id=<?php echo $import['id']; ?>" 
                                               class="btn btn-sm btn-info" title="Voir les étudiants">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deleteImport(<?php echo $import['id']; ?>)"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($imports)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Aucun import trouvé
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p id="loadingMessage">Traitement en cours...</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentStep = 1;
        let uploadedFileData = null;
        
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
            const validTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            
            if (!validTypes.includes(file.type)) {
                alert('Veuillez sélectionner un fichier Excel (.xls ou .xlsx)');
                return;
            }
            
            showLoading('Upload du fichier en cours...');
            
            const formData = new FormData();
            formData.append('grille_file', file);
            formData.append('action', 'upload');
            
            fetch('controller/import_grille_ancienne.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    uploadedFileData = data.data;
                    
                    // Afficher les informations du fichier
                    document.getElementById('fileInfo').style.display = 'block';
                    document.getElementById('fileName').textContent = file.name;
                    document.getElementById('rowCount').textContent = data.data.rows;
                    document.getElementById('colCount').textContent = data.data.columns;
                    document.getElementById('studentCount').textContent = data.data.structure.detected_students || '0';
                    
                    // Afficher le bouton suivant
                    document.getElementById('btnNext').style.display = 'block';
                    
                    // Préparer l'aperçu de la structure
                    let structureHtml = '<ul>';
                    if (data.data.structure.columns.matricule) {
                        structureHtml += '<li>Colonne Matricule: ' + data.data.structure.columns.matricule + '</li>';
                    }
                    if (data.data.structure.columns.nom) {
                        structureHtml += '<li>Colonne Nom: ' + data.data.structure.columns.nom + '</li>';
                    }
                    if (data.data.structure.columns.notes) {
                        structureHtml += '<li>Colonnes de notes détectées: ' + data.data.structure.columns.notes.length + '</li>';
                    }
                    structureHtml += '</ul>';
                    document.getElementById('structurePreview').innerHTML = structureHtml;
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                alert('Erreur lors de l\'upload: ' + error);
            });
        }
        
        function nextStep() {
            if (currentStep === 1) {
                // Passer à la configuration
                document.getElementById('uploadStep').style.display = 'none';
                document.getElementById('configStep').style.display = 'block';
                document.getElementById('btnNext').style.display = 'none';
                document.getElementById('btnPrevious').style.display = 'block';
                document.getElementById('btnProcess').style.display = 'block';
                
                document.getElementById('step1').classList.remove('active');
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('active');
                
                currentStep = 2;
            }
        }
        
        function previousStep() {
            if (currentStep === 2) {
                // Retour à l'upload
                document.getElementById('configStep').style.display = 'none';
                document.getElementById('uploadStep').style.display = 'block';
                document.getElementById('btnNext').style.display = 'block';
                document.getElementById('btnPrevious').style.display = 'none';
                document.getElementById('btnProcess').style.display = 'none';
                
                document.getElementById('step2').classList.remove('active');
                document.getElementById('step1').classList.remove('completed');
                document.getElementById('step1').classList.add('active');
                
                currentStep = 1;
            }
        }
        
        function processImport() {
            const form = document.getElementById('importForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            showLoading('Import en cours... Cela peut prendre quelques minutes.');
            
            const formData = new FormData(form);
            formData.append('action', 'process');
            
            fetch('controller/import_grille_ancienne.php', {
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
                    
                    document.getElementById('step2').classList.remove('active');
                    document.getElementById('step2').classList.add('completed');
                    document.getElementById('step3').classList.add('active');
                    
                    // Afficher les résultats
                    document.getElementById('importedStudents').textContent = data.data.students_imported;
                    document.getElementById('importedNotes').textContent = data.data.notes_imported;
                    
                    currentStep = 3;
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                alert('Erreur lors du traitement: ' + error);
            });
        }
        
        function deleteImport(importId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet import et toutes ses données ?')) {
                return;
            }
            
            showLoading('Suppression en cours...');
            
            fetch('controller/import_grille_ancienne.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete&import_id=' + importId
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                alert('Erreur lors de la suppression: ' + error);
            });
        }
        
        function showLoading(message) {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    </script>
<?php include "./views/include/footer.php"; ?>