<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer les années académiques
$query = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$annees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les sessions
$query = "SELECT * FROM session ORDER BY description";
$stmt = $pdo->prepare($query);
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les sections
$query = "SELECT * FROM section ORDER BY \"designationSection\"";
$stmt = $pdo->prepare($query);
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    /* Styles pour l'upload de fichiers */
    .file-upload-wrapper {
        margin-bottom: 1.5rem;
    }

    .file-upload-card {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .file-upload-card:hover {
        border-color: #6c757d;
    }

    .file-upload-area {
        padding: 1.5rem;
        cursor: pointer;
        position: relative;
    }

    .file-upload-area.drag-over {
        background-color: rgba(13, 110, 253, 0.05);
        border-color: #0d6efd;
    }

    .file-upload-message {
        padding: 1.5rem 0;
    }

    /* Styles pour l'importation Excel */
    .excel-import-section {
        margin-bottom: 2rem;
    }

    .excel-icon-container {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(25, 135, 84, 0.1);
        border-radius: 50%;
    }

    .excel-dropzone {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .excel-dropzone:hover {
        border-color: #198754;
    }

    .excel-dropzone.drag-over {
        background-color: rgba(25, 135, 84, 0.05);
        border-color: #198754;
    }

    /* Styles pour les étapes d'importation */
    .step-indicator {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 1;
    }

    .step-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #f8f9fa;
        border: 2px solid #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }

    .step.active .step-icon {
        background-color: #198754;
        border-color: #198754;
        color: white;
    }

    .step.completed .step-icon {
        background-color: #198754;
        border-color: #198754;
        color: white;
    }

    .step-line {
        flex-grow: 1;
        height: 2px;
        background-color: #dee2e6;
        margin: 0 10px;
    }

    .step.active+.step-line,
    .step.completed+.step-line {
        background-color: #198754;
    }

    .preview-table-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    /* Animation pour le chargement */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    /* Styles pour l'importation Excel */
.step-indicator {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.step-label {
    font-size: 0.875rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.step-line {
    flex: 1;
    height: 3px;
    background-color: #e9ecef;
    margin: 0 10px;
    position: relative;
    top: -20px;
    z-index: 0;
}

.step.active .step-icon {
    background-color: #0d6efd;
    color: white;
}

.step.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.step.completed .step-icon {
    background-color: #198754;
    color: white;
}

.step.completed .step-label {
    color: #198754;
}

.step.completed + .step-line {
    background-color: #198754;
}

.excel-dropzone {
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
}

.excel-dropzone.drag-over {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Styles pour le dropzone PDF */
#pdfDropzone {
    border: 2px dashed #dee2e6;
    border-radius: 0.375rem;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

#pdfDropzone.drag-over {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.file-preview-area {
    display: none;
}

</style>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AJOUT DE PALMARÈS D'ARCHIVES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=academique/palmares_archives">Palmarès</a></li>
                <li class="breadcrumb-item active">Ajouter un palmarès d'archive</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ajouter un nouveau palmarès d'archives</h5>

                        <!-- Formulaire d'ajout de palmarès -->
                        <form id="palmaresForm" method="POST" action="controller/create_palmares_archive.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="type_palmares" id="type_palmares_hidden" value="classique">
                            
                            <!-- Section 1: Informations générales -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations générales</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="type_palmares" class="form-label">Type de palmarès <span class="text-danger">*</span></label>
                                            <select id="type_palmares" class="form-select" required>
                                                <option value="classique" selected>Classique (PADEM)</option>
                                                <option value="lmd">LMD</option>
                                            </select>
                                            <div class="form-text">Choisissez le système d'évaluation.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="annee_academique" class="form-label">Année académique <span class="text-danger">*</span></label>
                                            <input type="text" name="annee_academique" id="annee_academique" class="form-control" required placeholder="ex: 2018-2019">
                                            <div class="invalid-feedback">Veuillez entrer une année académique.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
                                            <select name="session" id="session" class="form-select" required>
                                                <option value="">Sélectionner une session</option>
                                                <?php foreach ($sessions as $session): ?>
                                                    <option value="<?= $session['designSession'] ?>"><?= $session['description'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Veuillez sélectionner une session.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                            <input type="text" name="section" id="section" class="form-control" required placeholder="ex: Informatique">
                                            <div class="invalid-feedback">Veuillez entrer une section.</div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="promotion" class="form-label">Promotion <span class="text-danger">*</span></label>
                                            <input type="text" name="promotion" id="promotion" class="form-control" required placeholder="ex: L1">
                                            <div class="invalid-feedback">Veuillez entrer une promotion.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea name="description" id="description" class="form-control" rows="2" placeholder="Description optionnelle du palmarès"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Document PDF -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-file-pdf me-2"></i>Document scanné (Facultatif)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="file-upload-wrapper">
                                        <div class="file-upload-card">
                                            <div class="file-upload-area" id="pdfDropzone">
                                                <input type="file" name="fichier_scanne" id="fichier_scanne" class="file-upload-input" accept=".pdf" hidden>
                                                <div class="file-upload-message text-center">
                                                    <i class="bi bi-file-earmark-pdf display-4 text-primary mb-3"></i>
                                                    <h5 class="mb-2">Glissez et déposez votre fichier PDF ici</h5>
                                                    <p class="text-muted mb-3">ou</p>
                                                    <button type="button" class="btn btn-primary btn-browse">
                                                        <i class="bi bi-folder2-open me-2"></i>Parcourir les fichiers
                                                    </button>
                                                    <p class="mt-3 text-muted small">Format accepté: PDF. Taille max: 10 MB</p>
                                                </div>
                                                <div class="file-preview-area" style="display: none;">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <i class="bi bi-file-earmark-pdf fs-1 text-primary me-3"></i>
                                                        <div class="flex-grow-1">
                                                            <h6 class="file-name mb-1">document.pdf</h6>
                                                            <div class="small text-muted file-size">0 KB</div>
                                                            <div class="progress mt-2" style="height: 5px;">
                                                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-danger ms-3 remove-file">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Résultats des étudiants - Pleine largeur -->
                            <div class="card mt-4">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Résultats des étudiants</h5>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-success btn-sm" id="importExcelBtn">
                                            <i class="bi bi-file-excel me-2"></i> Importer depuis Excel
                                        </button>
                                        <a href="controller/download_template_palmares.php" id="templatePademMain" data-template-type="classique" class="btn btn-outline-primary btn-sm" download>
                                            <i class="bi bi-download me-2"></i> Modèle PADEM
                                        </a>
                                        <a href="controller/download_template_palmares_lmd.php" id="templateLmdMain" data-template-type="lmd" class="btn btn-outline-primary btn-sm" style="display:none;" download>
                                            <i class="bi bi-download me-2"></i> Modèle LMD
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0" id="studentResultsTable">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th width="5%" class="text-center">#</th>
                                                    <th width="30%">Nom complet</th>
                                                    <th width="20%">Matricule</th>
                                                    <th width="15%" class="text-center" id="scoreHeaderInline">Pourcentage</th>
                                                    <th width="15%" class="text-center" id="creditsHeaderInline" style="display:none;">Crédits validés</th>
                                                    <th width="20%">Décision</th>
                                                    <th width="10%" class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="studentResultsBody">
                                                <!-- Les lignes seront ajoutées dynamiquement -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-3 bg-light border-top">
                                        <button type="button" class="btn btn-primary btn-sm" id="addStudentRow">
                                            <i class="bi bi-plus-circle me-2"></i> Ajouter un étudiant
                                        </button>
                                        <span class="ms-3 text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Vous pouvez importer les données depuis Excel ou les saisir manuellement
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='?view=enseignement/palmares_archives'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer le palmarès
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<!-- Modal pour importation Excel -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer les résultats depuis Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <!-- Indicateur d'étapes -->
                    <div class="step-indicator mb-4">
                        <div class="step active" id="step1">
                            <div class="step-icon">1</div>
                            <div class="step-label">Fichier</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" id="step2">
                            <div class="step-icon">2</div>
                            <div class="step-label">Mapping</div>
                        </div>
                        <div class="step-line"></div>
                        <div class="step" id="step3">
                            <div class="step-icon">3</div>
                            <div class="step-label">Aperçu</div>
                        </div>
                    </div>
                    
                    <!-- Étape 1: Sélection du fichier -->
                    <div class="step-content" id="step1-content">
                        <div class="excel-import-section">
                            <div class="d-flex align-items-center mb-3">
                                <div class="excel-icon-container me-3">
                                    <i class="bi bi-file-earmark-excel fs-3 text-success"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Sélectionnez votre fichier Excel</h6>
                                    <p class="text-muted mb-0">Formats acceptés: .xlsx, .xls, .csv</p>
                                </div>
                            </div>
                            
                            <div class="excel-dropzone p-4" id="excelDropzone">
                                <div class="excel-upload-message text-center">
                                    <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-2"></i>
                                    <h6>Glissez-déposez votre fichier ici</h6>
                                    <p class="text-muted mb-3">ou</p>
                                    <button type="button" class="btn btn-outline-primary btn-browse-excel">
                                        <i class="bi bi-folder2-open me-2"></i> Parcourir
                                    </button>
                                    <input type="file" class="d-none" id="excelFile" name="excelFile" accept=".xlsx, .xls, .csv">
                                </div>
                                
                                <div class="excel-preview p-3 border rounded" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-excel fs-4 text-success me-2"></i>
                                            <div>
                                                <span class="excel-file-name fw-bold"></span>
                                                <br>
                                                <small class="text-muted excel-file-size"></small>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-excel">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="controller/download_template_palmares.php" id="templatePademModal" data-template-type="classique" class="btn btn-outline-primary btn-sm" download>
                                    <i class="bi bi-download me-2"></i> Télécharger le modèle (PADEM)
                                </a>
                                <a href="controller/download_template_palmares_lmd.php" id="templateLmdModal" data-template-type="lmd" class="btn btn-outline-primary btn-sm" style="display:none;">
                                    <i class="bi bi-download me-2"></i> Télécharger le modèle (LMD)
                                </a>
                                <small class="text-muted ms-2 d-block mt-2">Utilisez notre modèle pour éviter les erreurs d'importation</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-primary" id="goToStep2">
                                Continuer <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Ã‰tape 2: Mapping des colonnes -->
                    <div class="step-content" id="step2-content" style="display: none;">
                        <div class="alert alert-info">
                            <div class="d-flex">
                                <i class="bi bi-info-circle fs-5 me-2"></i>
                                <div>
                                    <h6 class="alert-heading mb-1">Mapping des colonnes</h6>
                                    <p class="mb-0">Indiquez les colonnes correspondant Ã  chaque information (ex: A, B, C...)</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Numéro de ligne de début <i class="bi bi-question-circle text-muted" data-bs-toggle="tooltip" title="La première ligne contient généralement les en-têtes"></i></label>
                                    <input type="number" class="form-control" id="startRow" name="startRow" value="2" min="1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Matricule <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="colMatricule" name="colMatricule" value="A" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="colNom" name="colNom" value="B" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" id="scoreMapLabel" for="colPourcentage">Pourcentage <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="colPourcentage" name="colPourcentage" value="C" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Décision</label>
                                    <input type="text" class="form-control" id="colDecision" name="colDecision" value="D">
                                    <small class="form-text text-muted">Si vide, la décision sera déterminée automatiquement selon le pourcentage</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-secondary" id="backToStep1">
                                <i class="bi bi-arrow-left me-2"></i> Retour
                            </button>
                            <button type="button" class="btn btn-primary" id="goToStep3">
                                Aperçu <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Ã‰tape 3: AperÃ§u des données -->
                    <div class="step-content" id="step3-content" style="display: none;">
                        <div class="preview-container">
                            <h6 class="mb-3">AperÃ§u des données</h6>
                            
                            <div id="previewLoading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="mt-2">Génération de l'aperÃ§u en cours...</p>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="previewTable" style="display: none;">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="15%" id="scoreHeader">Pourcentage</th>
                                            <th width="20%" id="creditsValidesHeader" style="display:none;">Crédits validés</th>
                                            <th width="25%">Décision</th>
                                            <th width="15%" id="scoreHeader">Pourcentage</th>\r\n                                            <th width="20%" id="creditsValidesHeader" style="display:none;">Crédits validés</th>\r\n                                            <th width="25%">Décision</th>
                                            <th width="25%">Décision</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                        <!-- Les données seront ajoutées dynamiquement -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <div class="d-flex">
                                    <i class="bi bi-exclamation-triangle fs-5 me-2"></i>
                                    <div>
                                        <p class="mb-0">Vérifiez que les données sont correctes avant de procéder Ã  l'importation. Seuls les 10 premiers enregistrements sont affichés dans l'aperÃ§u.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-secondary" id="backToStep2">
                                <i class="bi bi-arrow-left me-2"></i> Retour
                            </button>
                            <button type="button" class="btn btn-success" id="processExcelBtn">
                                <i class="bi bi-check-circle me-2"></i> Importer les données
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>




<script>
    // Variables globales
let rowCounter = 0;

// Cette fonction sera redéfinie plus bas dans le code avec toutes les fonctionnalités

// Fonction pour renuméroter les lignes aprÃ¨s suppression
function renumberRows() {
    const rows = document.getElementById('studentResultsBody').querySelectorAll('tr');
    rowCounter = rows.length;

    rows.forEach((row, index) => {
        const rowNum = index + 1;
        row.cells[0].textContent = rowNum;

        // Mettre Ã  jour les noms des champs pour maintenir l'ordre des indices
        row.querySelectorAll('input, select').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                const newName = name.replace(/\[\d+\]/, `[${rowNum}]`);
                input.setAttribute('name', newName);
            }
        });
    });
}

// Déterminer la décision en fonction du pourcentage
function getDecisionFromPercentage(percentage) {
    if (isNaN(percentage)) return '';

    if (percentage >= 90) return 'TrÃ¨s grande distinction';
    if (percentage >= 80) return 'Grande Distinction';
    if (percentage >= 70) return 'Distinction';
    if (percentage >= 50) return 'Satisfaction';
    if (percentage < 50) return 'Ajournée';
    return '';
}

// Fonction utilitaire pour formater la taille du fichier
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Fonction pour générer l'aperÃ§u des données Excel
function generatePreview() {
    const previewLoading = document.getElementById('previewLoading');
    const previewTable = document.getElementById('previewTable');
    const previewTableBody = document.getElementById('previewTableBody');
    const excelFileInput = document.getElementById('excelFile');

    previewLoading.style.display = 'block';
    previewTable.style.display = 'none';

    // Créer un FormData pour envoyer le fichier
    const formData = new FormData();
    formData.append('excelFile', excelFileInput.files[0]);
    formData.append('startRow', document.getElementById('startRow').value);
    formData.append('colNom', document.getElementById('colNom').value);
    formData.append('colMatricule', document.getElementById('colMatricule').value);
    formData.append('colPourcentage', document.getElementById('colPourcentage').value);
        formData.append('colCreditsValides', document.getElementById('colCreditsValides') ? document.getElementById('colCreditsValides').value : '');
        formData.append('type_palmares', (document.getElementById('type_palmares')||{value:'classique'}).value);
    formData.append('preview', 'true');

    // Envoyer la requÃªte pour obtenir un aperÃ§u
    fetch('controller/preview_excel_palmares.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }

        // Remplir le tableau d'aperÃ§u
        previewTableBody.innerHTML = '';

        data.etudiants.slice(0, 10).forEach((etudiant, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${etudiant.matricule || '<em class="text-muted">Non spécifié</em>'}</td>
                <td>${etudiant.nom_complet}</td>
                <td>${etudiant.pourcentage}%</td>
                <td>${etudiant.decision || '<em class="text-muted">Auto</em>'}</td>
            `;
            previewTableBody.appendChild(row);
        });

        // Ajouter une ligne indiquant le nombre total d'étudiants
        if (data.etudiants.length > 10) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="5" class="text-center text-muted">
                    <em>+ ${data.etudiants.length - 10} autres étudiants (total: ${data.etudiants.length})</em>
                </td>
            `;
            previewTableBody.appendChild(row);
        }

        // Afficher le tableau d'aperÃ§u
        previewLoading.style.display = 'none';
        previewTable.style.display = 'table';
    })
    .catch(error => {
        console.error('Erreur d\'aperÃ§u:', error);
        previewLoading.style.display = 'none';

        // Afficher un message d'erreur dans le tableau
        previewTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur lors de la génération de l'aperÃ§u: ${error.message}
                </td>
            </tr>
        `;
        previewTable.style.display = 'table';
    });
}

// Initialisation au chargement du document
document.addEventListener('DOMContentLoaded', function() {
    // Références aux éléments du DOM
    const form = document.getElementById('palmaresForm');
    const studentResultsBody = document.getElementById('studentResultsBody');
    const addStudentRowBtn = document.getElementById('addStudentRow');
    const importExcelBtn = document.getElementById('importExcelBtn');
    const importExcelModal = new bootstrap.Modal(document.getElementById('importExcelModal'));
    
    // Configuration de l'upload de fichier PDF
    const pdfDropzone = document.getElementById('pdfDropzone');
    const fileInput = document.getElementById('fichier_scanne');
    const filePreviewArea = pdfDropzone.querySelector('.file-preview-area');
    const fileUploadMessage = pdfDropzone.querySelector('.file-upload-message');
    const browseBtn = pdfDropzone.querySelector('.btn-browse');
    const removeFileBtn = pdfDropzone.querySelector('.remove-file');
    
    // Configuration de l'importation Excel
    const excelDropzone = document.getElementById('excelDropzone');
    const excelFileInput = document.getElementById('excelFile');
    const excelPreview = excelDropzone.querySelector('.excel-preview');
    const excelUploadMessage = excelDropzone.querySelector('.excel-upload-message');
    const browseBtnExcel = excelDropzone.querySelector('.btn-browse-excel');
    const removeExcelBtn = excelDropzone.querySelector('.remove-excel');
    
    // Gestion des étapes d'importation
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    
    const step1Content = document.getElementById('step1-content');
    const step2Content = document.getElementById('step2-content');
    const step3Content = document.getElementById('step3-content');
    
    const goToStep2Btn = document.getElementById('goToStep2');
    const backToStep1Btn = document.getElementById('backToStep1');
    const goToStep3Btn = document.getElementById('goToStep3');
    const backToStep2Btn = document.getElementById('backToStep2');
    const processExcelBtn = document.getElementById('processExcelBtn');
    const typeSelect = document.getElementById('type_palmares');
    const typeHiddenInput = document.getElementById('type_palmares_hidden');
    const scoreHeader = document.getElementById('scoreHeader');
    const scoreHeaderInline = document.getElementById('scoreHeaderInline');
    const creditsHeaderInline = document.getElementById('creditsHeaderInline');
    const scoreMapLabel = document.getElementById('scoreMapLabel');
    const creditsHeader = document.getElementById('creditsValidesHeader');
    const creditsMappingField = document.getElementById('mapCreditsValidesCol');

    const decisionSelects = () => document.querySelectorAll('select[name*="[decision]"]');
    const percentageInputs = () => document.querySelectorAll('.percentage-input');
    
    // Initialiser Select2 sur le select type_palmares si disponible
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#type_palmares').select2({
            minimumResultsForSearch: -1, // Désactiver la recherche pour ce petit select
            width: '100%'
        });
        
        // Utiliser l'événement Select2 pour détecter les changements
        $('#type_palmares').on('select2:select', function (e) {
            // Mettre à jour l'UI et les champs crédits lors du changement via Select2
            syncTypeUi();
            if (typeof updateCreditsInputsVisibility === 'function') {
                updateCreditsInputsVisibility();
            }
        });
    }

    const buildDecisionOptions = (type) => {
        if (type === 'lmd') {
            return ['', 'Satisfaction', 'Assez Bien', 'Bien', 'Très Bien']
                .map(value => value ? `<option value="${value}">${value}</option>` : '<option value="">Sélectionner...</option>')
                .join('');
        }

        const options = ['', 'Très grande distinction', 'Grande Distinction', 'Distinction', 'Satisfaction', 'Ajournée', 'Assimilé aux ajournées', 'Abandon'];
        return options
            .map(value => value ? `<option value="${value}">${value}</option>` : '<option value="">Sélectionner...</option>')
            .join('');
    };

    const syncTypeUi = () => {
        const type = typeSelect ? typeSelect.value : 'classique';

        if (typeHiddenInput) {
            typeHiddenInput.value = type;
        }

        const scoreLabel = type === 'lmd' ? 'Moyenne' : 'Pourcentage';
        if (scoreHeader) {
            scoreHeader.textContent = scoreLabel;
        }
        if (scoreHeaderInline) {
            scoreHeaderInline.textContent = scoreLabel;
        }
        if (scoreMapLabel) {
            scoreMapLabel.textContent = `${scoreLabel} *`;
        }

        const showCredits = type === 'lmd';
        if (creditsHeader) {
            creditsHeader.style.display = showCredits ? '' : 'none';
        }
        if (creditsHeaderInline) {
            creditsHeaderInline.style.display = showCredits ? '' : 'none';
        }
        if (creditsMappingField) {
            creditsMappingField.style.display = showCredits ? '' : 'none';
        }

        percentageInputs().forEach((input) => {
            if (type === 'lmd') {
                input.min = '0';
                input.max = '20';
                input.placeholder = '0 - 20';
            } else {
                input.min = '0';
                input.max = '100';
                input.placeholder = '0 - 100';
            }
        });

        decisionSelects().forEach((select) => {
            const previousValue = select.value;
            select.innerHTML = buildDecisionOptions(type);

            for (const option of select.options) {
                if (option.value === previousValue) {
                    select.value = previousValue;
                    break;
                }
            }
        });

        document.querySelectorAll('[data-role="credits-valide-col"]').forEach((element) => {
            element.style.display = showCredits ? '' : 'none';
        });
        
        // Mettre à jour toutes les cellules de crédits existantes
        document.querySelectorAll('.credits-cell').forEach((element) => {
            element.style.display = showCredits ? '' : 'none';
        });
        
        // Gérer les lignes existantes qui n'ont pas de colonne crédits
        const rows = studentResultsBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            // Si la ligne a 6 colonnes (sans crédits), on doit ajouter la colonne crédits
            if (cells.length === 6) {
                const rowNum = index + 1;
                const creditsCell = document.createElement('td');
                creditsCell.className = 'credits-cell';
                creditsCell.style.display = showCredits ? '' : 'none';
                
                if (showCredits) {
                    creditsCell.innerHTML = `
                        <input type="number" name="etudiants[${rowNum}][credits_valides]" 
                               class="form-control" 
                               min="0" step="1"
                               value="" 
                               placeholder="Crédits">
                    `;
                } else {
                    creditsCell.innerHTML = `
                        <input type="hidden" name="etudiants[${rowNum}][credits_valides]" value="">
                    `;
                }
                
                // Insérer la cellule avant la colonne décision (5ème position)
                row.insertBefore(creditsCell, cells[4]);
            }
        });
    };

    // Fonction pour ajouter une nouvelle ligne d'étudiant
    // Assure que les cellules "crédits" contiennent le bon type de champ
    function updateCreditsInputsVisibility() {
        const type = typeSelect ? typeSelect.value : 'classique';
        const showCredits = type === 'lmd';

        // Convertir les inputs hidden <-> number selon le type
        document.querySelectorAll('.credits-cell').forEach((cell) => {
            const input = cell.querySelector('input');
            if (!input) return;

            const currentName = input.getAttribute('name') || '';
            const currentValue = input.value || '';

            if (showCredits && input.type === 'hidden') {
                const numberInput = document.createElement('input');
                numberInput.type = 'number';
                numberInput.className = 'form-control';
                numberInput.name = currentName;
                numberInput.min = '0';
                numberInput.step = '1';
                numberInput.value = currentValue;
                numberInput.placeholder = 'Crédits';
                cell.innerHTML = '';
                cell.appendChild(numberInput);
            } else if (!showCredits && input.type !== 'hidden') {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = currentName;
                hiddenInput.value = currentValue;
                cell.innerHTML = '';
                cell.appendChild(hiddenInput);
            }
        });

        // Si une ligne n'a pas de cellule crédits (cas rares), l'ajouter en LMD
        if (showCredits) {
            const rows = studentResultsBody.querySelectorAll('tr');
            rows.forEach((row) => {
                if (!row.querySelector('.credits-cell')) {
                    // Essayer de déduire l'index de la ligne depuis un autre champ
                    let rowIndex = null;
                    const anyInput = row.querySelector('input[name^="etudiants["]') || row.querySelector('select[name^="etudiants["]');
                    if (anyInput && anyInput.name) {
                        const m = anyInput.name.match(/etudiants\[(\d+)\]/);
                        if (m) rowIndex = m[1];
                    }

                    const creditsCell = document.createElement('td');
                    creditsCell.className = 'credits-cell';
                    const numberInput = document.createElement('input');
                    numberInput.type = 'number';
                    numberInput.className = 'form-control';
                    numberInput.name = rowIndex ? `etudiants[${rowIndex}][credits_valides]` : 'credits_valides[]';
                    numberInput.min = '0';
                    numberInput.step = '1';
                    numberInput.placeholder = 'Crédits';
                    creditsCell.appendChild(numberInput);

                    const decisionSelect = row.querySelector('select[name*="[decision]"]');
                    const decisionCell = decisionSelect ? decisionSelect.closest('td') : null;
                    if (decisionCell) {
                        row.insertBefore(creditsCell, decisionCell);
                    } else {
                        row.appendChild(creditsCell);
                    }
                }
            });
        }
    }

    // Corriger les caractères mal encodés dans le modal d'import Excel
    function fixModalTexts() {
        const modal = document.getElementById('importExcelModal');
        if (!modal) return;

        // Remplacements ciblés
        const replacements = {
            'rǸsultats': 'résultats',
            "Indicateur d'Ǹtapes": "Indicateur d'étapes",
            'Aper��u': 'Aperçu',
            'AperÃ§u': 'Aperçu',
            'Sélectionnez': 'Sélectionnez',
            'acceptés': 'acceptés',
            'Glissez-déposez': 'Glissez-déposez',
            'Télécharger': 'Télécharger',
            'TǸlǸcharger': 'Télécharger',
            'modéle': 'modèle',
            'mod��le': 'modèle',
            'éviter': 'éviter',
            'Numéro': 'Numéro',
            'début': 'début',
            'premiére': 'première',
            'généralement': 'généralement',
            'en-tétes': 'en-têtes',
            'Décision': 'Décision',
            'DǸcision': 'Décision',
            'déterminée': 'déterminée',
            'données': 'données',
            "Génération": 'Génération',
            "l'aperéu": "l'aperçu",
            'CrǸdits validǸs': 'Crédits validés',
            'Vérifiez': 'Vérifiez',
            'procéder é': 'procéder à',
            'affichés': 'affichés',
            'Ajournée': 'Ajournée',
            'Trés': 'Très',
            'SǸlectionner': 'Sélectionner',
        };

        // Parcourir tous les noeuds texte pour corriger le contenu sans casser les icônes
        const walker = document.createTreeWalker(modal, NodeFilter.SHOW_TEXT, null);
        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);
        textNodes.forEach(node => {
            let val = node.nodeValue;
            for (const [bad, good] of Object.entries(replacements)) {
                if (val.includes(bad)) val = val.split(bad).join(good);
            }
            node.nodeValue = val;
        });

        // Corriger attributs de titre/aria
        modal.querySelectorAll('[title], [aria-label]').forEach(el => {
            if (el.hasAttribute('title')) {
                let t = el.getAttribute('title');
                for (const [bad, good] of Object.entries(replacements)) {
                    if (t.includes(bad)) t = t.split(bad).join(good);
                }
                el.setAttribute('title', t);
            }
            if (el.hasAttribute('aria-label')) {
                let a = el.getAttribute('aria-label');
                for (const [bad, good] of Object.entries(replacements)) {
                    if (a.includes(bad)) a = a.split(bad).join(good);
                }
                el.setAttribute('aria-label', a);
            }
        });

        // Passes supplémentaires pour corriger d'autres séquences courantes (ex: Sélectionnez)
        const extraPairs = [
            ['Sélectionnez', 'Sélectionnez'], ['Sélectionner', 'Sélectionner'],
            ['AperÃ§u', 'Aperçu'], ['résultats', 'résultats'],
            ['Télécharger', 'Télécharger'], ['modÃ¨le', 'modèle'],
            ['Vérifiez', 'Vérifiez'], ['procéder Ã ', 'procéder à '],
            ['affichés', 'affichés'], ['PremiÃ¨re', 'Première'],
            ['généralement', 'généralement'], ['en-tÃªtes', 'en-têtes'],
            ['début', 'début'], ['Décision', 'Décision'],
            ['Crédits', 'Crédits'], ['validés', 'validés'],
            ['données', 'données'], ['déterminée', 'déterminée'],
            ['TrÃ¨s', 'Très'], ['Ajournée', 'Ajournée'],
            // caractères unitaires
            ['é', 'é'], ['Ã¨', 'è'], ['Ãª', 'ê'], ['Ã«', 'ë'],
            ['Ã ', 'à'], ['Ã¢', 'â'], ['Ã¹', 'ù'], ['Ã»', 'û'], ['Ã´', 'ô'],
            ['Ã§', 'ç'], ['Ã‰', 'É'], ['Ã€', 'À'], ['Ã‡', 'Ç'],
            ['Ã¯', 'ï'], ['Ã¶', 'ö'], ['Ã¼', 'ü'],
            ['Ã³', 'ó'], ['Ã¡', 'á'], ['Ã­', 'í'], ['Ãº', 'ú'], ['Ã±', 'ñ'],
            ['Ã‚Â', ''],
        ];
        const modalTextNodes = [];
        const walker2 = document.createTreeWalker(modal, NodeFilter.SHOW_TEXT);
        while (walker2.nextNode()) modalTextNodes.push(walker2.currentNode);
        modalTextNodes.forEach(node => {
            let val = node.nodeValue;
            for (const [bad, good] of extraPairs) {
                if (val.includes(bad)) val = val.split(bad).join(good);
            }
            node.nodeValue = val;
        });
    }

    // Ajouter le champ de mapping des crédits si absent (étape 2 du modal)
    function ensureCreditsMappingField() {
        const step2 = document.getElementById('step2-content');
        if (!step2) return;
        if (document.getElementById('mapCreditsValidesCol')) return;

        const buttons = step2.querySelector('.d-flex.justify-content-between.mt-4');

        const wrapper = document.createElement('div');
        wrapper.className = 'row';
        wrapper.id = 'mapCreditsValidesCol';
        wrapper.style.display = 'none';
        wrapper.innerHTML = `
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label" for="colCreditsValides">Crédits validés</label>
                    <input type="text" class="form-control" id="colCreditsValides" name="colCreditsValides" value="">
                </div>
            </div>
        `;

        if (buttons && buttons.parentNode === step2) {
            step2.insertBefore(wrapper, buttons);
        } else {
            step2.appendChild(wrapper);
        }
    }

    let rowCounter = 0;
    function addNewStudentRow(data = {}) {
        rowCounter++;
        const type = typeSelect ? typeSelect.value : 'classique';
        
        // Créer les options de décision
        const decisionOptions = buildDecisionOptions(type);
        
        // Déterminer le placeholder et les limites pour le score
        const scoreLabel = type === 'lmd' ? 'Moyenne' : 'Pourcentage';
        const scorePlaceholder = type === 'lmd' ? '0 - 20' : '0 - 100';
        const scoreMin = '0';
        const scoreMax = type === 'lmd' ? '20' : '100';
        
        // Ajouter la colonne crédits validés pour LMD
        const creditsColumn = type === 'lmd' ? 
            `<td class="credits-cell">
                <input type="number" name="etudiants[${rowCounter}][credits_valides]" 
                       class="form-control" 
                       min="0" step="1"
                       value="${data.credits_valides || ''}" 
                       placeholder="Crédits">
            </td>` : 
            `<td class="credits-cell" style="display:none;">
                <input type="hidden" name="etudiants[${rowCounter}][credits_valides]" value="">
            </td>`;
        
        const row = `
            <tr id="studentRow${rowCounter}">
                <td class="text-center">${rowCounter}</td>
                <td>
                    <input type="text" name="etudiants[${rowCounter}][nom_complet]" 
                           class="form-control" required 
                           value="${data.nom_complet || ''}" 
                           placeholder="Nom et prénom de l'étudiant">
                </td>
                <td>
                    <input type="text" name="etudiants[${rowCounter}][matricule]" 
                           class="form-control" required 
                           value="${data.matricule || ''}" 
                           placeholder="Matricule">
                </td>
                <td>
                    <input type="number" name="etudiants[${rowCounter}][pourcentage]" 
                           class="form-control percentage-input" 
                           step="0.01" min="${scoreMin}" max="${scoreMax}"
                           value="${data.pourcentage || ''}" 
                           placeholder="${scorePlaceholder}" required>
                </td>
                ${creditsColumn}
                <td>
                    <select name="etudiants[${rowCounter}][decision]" class="form-select">
                        ${decisionOptions}
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="removeStudentRow(${rowCounter})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        
        studentResultsBody.insertAdjacentHTML('beforeend', row);
        
        // Pré-sélectionner la décision si fournie
        if (data.decision) {
            const select = document.querySelector(`select[name="etudiants[${rowCounter}][decision]"]`);
            if (select) {
                select.value = data.decision;
            }
        }
        
        // Ajouter l'événement pour auto-déterminer la décision
        const newRow = document.getElementById(`studentRow${rowCounter}`);
        const percentageInput = newRow.querySelector('.percentage-input');
        
        percentageInput.addEventListener('change', function() {
            const value = parseFloat(this.value);
            const decisionSelect = newRow.querySelector('select[name*="[decision]"]');
            if (decisionSelect && decisionSelect.value === '') {
                const currentType = typeSelect ? typeSelect.value : 'classique';
                decisionSelect.value = getDecisionFromScore(value, currentType);
            }
        });
    }
    
    // Fonction pour déterminer la décision selon le score et le type
    function getDecisionFromScore(score, type) {
        if (isNaN(score)) return '';
        
        if (type === 'lmd') {
            // Système LMD (sur 20)
            if (score >= 16) return 'Très Bien';
            if (score >= 14) return 'Bien';
            if (score >= 12) return 'Assez Bien';
            if (score >= 10) return 'Satisfaction';
            return '';
        } else {
            // Système PADEM (sur 100)
            if (score >= 90) return 'Très grande distinction';
            if (score >= 80) return 'Grande Distinction';
            if (score >= 70) return 'Distinction';
            if (score >= 50) return 'Satisfaction';
            if (score < 50) return 'Ajournée';
            return '';
        }
    }
    
    // Fonction pour supprimer une ligne d'étudiant
    window.removeStudentRow = function(rowId) {
        const row = document.getElementById(`studentRow${rowId}`);
        if (row) {
            row.remove();
            updateRowNumbers();
        }
    };
    
    // Fonction pour mettre à jour les numéros de ligne
    function updateRowNumbers() {
        const rows = studentResultsBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            const numberCell = row.querySelector('td:first-child');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }
    
    // Fonction pour formatter la taille de fichier
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            syncTypeUi();
            updateCreditsInputsVisibility();
            fixModalTexts();
        });
    }

    // S'assurer que le champ de mapping des crédits existe et corriger les textes du modal
    ensureCreditsMappingField();
    fixModalTexts();

    syncTypeUi();
    updateCreditsInputsVisibility();
    // Validation du formulaire Bootstrap
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Vérifie si au moins un étudiant a été ajouté
        const rows = studentResultsBody.querySelectorAll('tr');
        if (rows.length === 0) {
            event.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez ajouter au moins un étudiant au palmarès.'
            });
            return false;
        }
        
        form.classList.add('was-validated');
    });

    // Ajouter une ligne d'étudiant
    addStudentRowBtn.addEventListener('click', function() {
        addNewStudentRow();
    });

    // Événement pour le bouton "Parcourir" PDF
    browseBtn.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Événement pour le glisser-déposer PDF
    pdfDropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        pdfDropzone.classList.add('drag-over');
    });
    
    pdfDropzone.addEventListener('dragleave', function() {
        pdfDropzone.classList.remove('drag-over');
    });
    
    pdfDropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        pdfDropzone.classList.remove('drag-over');
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelection();
        }
    });
    
    // Événement pour la sélection de fichier PDF
    fileInput.addEventListener('change', handleFileSelection);
    
    function handleFileSelection() {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            
            // Vérifier le type de fichier
            if (file.type !== 'application/pdf') {
                Swal.fire({
                    icon: 'error',
                    title: 'Type de fichier non valide',
                    text: 'Veuillez sélectionner un fichier PDF.'
                });
                fileInput.value = '';
                return;
            }
            
            // Vérifier la taille du fichier (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Fichier trop volumineux',
                    text: 'La taille du fichier ne doit pas dépasser 10 MB.'
                });
                fileInput.value = '';
                return;
            }
            
            // Afficher l'aperçu du fichier
            const fileName = pdfDropzone.querySelector('.file-name');
            const fileSize = pdfDropzone.querySelector('.file-size');
            const progressBar = pdfDropzone.querySelector('.progress-bar');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            // Simuler un chargement
            let progress = 0;
            const interval = setInterval(function() {
                progress += 10;
                progressBar.style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    fileUploadMessage.style.display = 'none';
                    filePreviewArea.style.display = 'block';
                }
            }, 50);
        }
    }
    
    // Événement pour supprimer le fichier PDF
    removeFileBtn.addEventListener('click', function() {
        fileInput.value = '';
        filePreviewArea.style.display = 'none';
        fileUploadMessage.style.display = 'block';
    });
    
    // Ã‰vénement pour le bouton "Parcourir" Excel
    browseBtnExcel.addEventListener('click', function() {
        excelFileInput.click();
    });
    
    // Ã‰vénement pour le glisser-déposer Excel
    excelDropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        excelDropzone.classList.add('drag-over');
    });
    
    excelDropzone.addEventListener('dragleave', function() {
        excelDropzone.classList.remove('drag-over');
    });
    
    excelDropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        excelDropzone.classList.remove('drag-over');
        
        if (e.dataTransfer.files.length) {
            excelFileInput.files = e.dataTransfer.files;
            handleExcelFileSelection();
        }
    });
    
    // Ã‰vénement pour la sélection de fichier Excel
    excelFileInput.addEventListener('change', handleExcelFileSelection);
    
    function handleExcelFileSelection() {
        if (excelFileInput.files.length > 0) {
            const file = excelFileInput.files[0];
            
            // Vérifier le type de fichier
            const validTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
            if (!validTypes.includes(file.type) && 
                !file.name.endsWith('.xlsx') && 
                !file.name.endsWith('.xls') && 
                !file.name.endsWith('.csv')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Type de fichier non valide',
                    text: 'Veuillez sélectionner un fichier Excel (.xlsx, .xls) ou CSV.'
                });
                excelFileInput.value = '';
                return;
            }
            
            // Vérifier la taille du fichier (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Fichier trop volumineux',
                    text: 'La taille du fichier ne doit pas dépasser 10 MB.'
                });
                excelFileInput.value = '';
                return;
            }
            
            // Afficher l'aperÃ§u du fichier
            const fileName = excelDropzone.querySelector('.excel-file-name');
            const fileSize = excelDropzone.querySelector('.excel-file-size');
            
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            excelUploadMessage.style.display = 'none';
            excelPreview.style.display = 'block';
        }
    }
    
    // Ã‰vénement pour supprimer le fichier Excel
    removeExcelBtn.addEventListener('click', function() {
        excelFileInput.value = '';
        excelPreview.style.display = 'none';
        excelUploadMessage.style.display = 'block';
    });
    
    // Navigation entre les étapes
    goToStep2Btn.addEventListener('click', function() {
        if (!excelFileInput.files.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Fichier requis',
                text: 'Veuillez sélectionner un fichier Excel avant de continuer.'
            });
            return;
        }
        
        step1.classList.remove('active');
        step1.classList.add('completed');
        step2.classList.add('active');
        
        step1Content.style.display = 'none';
        step2Content.style.display = 'block';
        step2Content.classList.add('fade-in');
    });
    
    backToStep1Btn.addEventListener('click', function() {
        step2.classList.remove('active');
        step1.classList.remove('completed');
        step1.classList.add('active');
        
        step2Content.style.display = 'none';
        step1Content.style.display = 'block';
        step1Content.classList.add('fade-in');
    });
    
    goToStep3Btn.addEventListener('click', function() {
        // Vérifier que les champs obligatoires sont remplis
        const colMatricule = document.getElementById('colMatricule').value;
        const colNom = document.getElementById('colNom').value;
        const colPourcentage = document.getElementById('colPourcentage').value;
        
        if (!colMatricule || !colNom || !colPourcentage) {
            Swal.fire({
                icon: 'warning',
                title: 'Champs requis',
                text: 'Veuillez remplir tous les champs obligatoires.'
            });
            return;
        }
        
        step2.classList.remove('active');
        step2.classList.add('completed');
        step3.classList.add('active');
        
        step2Content.style.display = 'none';
        step3Content.style.display = 'block';
        step3Content.classList.add('fade-in');
        
        // Générer l'aperÃ§u des données
        generatePreview();
    });
    
    backToStep2Btn.addEventListener('click', function() {
        step3.classList.remove('active');
        step2.classList.remove('completed');
        step2.classList.add('active');
        
        step3Content.style.display = 'none';
        step2Content.style.display = 'block';
        step2Content.classList.add('fade-in');
    });
    
    // Ã‰vénement pour le bouton d'importation final
    processExcelBtn.addEventListener('click', function() {
        // Désactiver le bouton pendant le traitement
        processExcelBtn.disabled = true;
        processExcelBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importation en cours...';
        
        // Créer un FormData pour envoyer le fichier
        const formData = new FormData();
        formData.append('excelFile', excelFileInput.files[0]);
        formData.append('startRow', document.getElementById('startRow').value);
        formData.append('colNom', document.getElementById('colNom').value);
        formData.append('colMatricule', document.getElementById('colMatricule').value);
        formData.append('colPourcentage', document.getElementById('colPourcentage').value);
        formData.append('colDecision', document.getElementById('colDecision').value);
        
        // Envoyer la requÃªte pour traiter le fichier
        fetch('controller/process_excel_palmares_archive.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Fermer la modal
            importExcelModal.hide();
            
            // Vider le tableau actuel et ajouter les nouvelles données
            studentResultsBody.innerHTML = '';
            rowCounter = 0;
            
            data.etudiants.forEach(etudiant => {
                addNewStudentRow({
                    nom_complet: etudiant.nom_complet,
                    matricule: etudiant.matricule,
                    pourcentage: etudiant.pourcentage,
                    decision: etudiant.decision,
                    session: etudiant.session || 'PremiÃ¨re session'
                });
            });
            
            // Afficher un message de succÃ¨s
            Swal.fire({
                icon: 'success',
                title: 'Importation réussie',
                text: `${data.etudiants.length} étudiants ont été importés avec succÃ¨s.`
            });
        })
        .catch(error => {
            console.error('Erreur d\'importation:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'importation',
                text: error.message || 'Une erreur est survenue lors de l\'importation du fichier Excel.'
            });
        })
        .finally(() => {
            // Réactiver le bouton
            processExcelBtn.disabled = false;
            processExcelBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Importer les données';
        });
    });
    
    // Initialisation de la modal d'importation Excel
    importExcelBtn.addEventListener('click', function() {
        // Réinitialiser la modal
        document.getElementById('importExcelForm').reset();
        
        // Réinitialiser les étapes
        step1.classList.add('active');
        step1.classList.remove('completed');
        step2.classList.remove('active');
        step2.classList.remove('completed');
        step3.classList.remove('active');
        
        step1Content.style.display = 'block';
        step2Content.style.display = 'none';
        step3Content.style.display = 'none';
        
        // Réinitialiser l'aperÃ§u du fichier Excel
        excelPreview.style.display = 'none';
        excelUploadMessage.style.display = 'block';
        
        // Afficher la modal
        importExcelModal.show();
    });
    
    // Ajouter une premiÃ¨re ligne par défaut
    addNewStudentRow();
    
    // Chargement des promotions en fonction de la section
    const sectionSelect = document.getElementById('section');
    const promotionSelect = document.getElementById('promotion');
    
    if (sectionSelect && promotionSelect) {
        sectionSelect.addEventListener('change', function() {
            const sectionId = this.value;
            promotionSelect.disabled = sectionId === '';
            
            if (sectionId === '') {
                promotionSelect.innerHTML = '<option value="">Sélectionnez d\'abord une section</option>';
                return;
            }
            
            // Récupérer les promotions de cette section
            fetch(`controller/get_promotions.php?section=${sectionId}`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Sélectionnez une promotion</option>';
                    data.forEach(promotion => {
                        options += `<option value="${promotion.idpromotion}">${promotion.designationPromotion}</option>`;
                    });
                    promotionSelect.innerHTML = options;
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des promotions:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de charger les promotions. Veuillez réessayer.'
                    });
                });
        });
    }
});


</script>

<?php include "./views/include/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const typeSel = document.getElementById("type_palmares");
  function toggleTemplateButtons() {
    const type = (typeSel && typeSel.value) || "classique";
    document.querySelectorAll("[data-template-type]").forEach(function(el){
      const t = el.getAttribute("data-template-type");
      el.style.display = ((t === "lmd" && type === "lmd") || (t === "classique" && type !== "lmd")) ? "" : "none";
    });
  }
  if (typeSel) { typeSel.addEventListener("change", toggleTemplateButtons); }
  toggleTemplateButtons();
});
</script>
