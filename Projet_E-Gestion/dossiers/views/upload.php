<?php $pageTitle = 'Upload — ' . sanitize($typeDoc['designation']); ?>
<?php $currentPage = 'dashboard'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .upload-page-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
        padding: 28px 32px;
        margin-bottom: 28px;
    }
    .upload-page-header h1 {
        color: #fff;
        font-size: 1.35rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .upload-page-header .subtitle {
        color: var(--gray-300);
        font-size: 0.85rem;
        margin-top: 4px;
    }
    .upload-page-header .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: rgba(255,255,255,0.5);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        margin-bottom: 10px;
        transition: color 0.2s;
    }
    .upload-page-header .back-link:hover { color: #fff; }
    .upload-page-header .back-link i { font-size: 0.7rem; }

    .upload-content {
        padding: 0 32px 40px;
        max-width: 640px;
        margin: 0 auto;
    }

    .upload-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .upload-card-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .upload-card-header .type-icon {
        width: 42px;
        height: 42px;
        background: var(--blue-50);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--blue-mid);
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .upload-card-header .type-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--navy);
    }
    .upload-card-header .type-badge {
        font-size: 0.68rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        margin-left: auto;
        white-space: nowrap;
    }
    .type-badge-oblig { background: var(--danger-light); color: var(--danger); }
    .type-badge-opt { background: var(--gray-100); color: var(--gray-500); }

    .upload-card-body {
        padding: 24px;
    }

    .upload-desc {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--blue-50);
        border: 1px solid var(--blue-pale);
        border-radius: var(--radius);
        padding: 12px 14px;
        font-size: 0.84rem;
        color: var(--blue);
        margin-bottom: 20px;
        line-height: 1.5;
    }
    .upload-desc i { margin-top: 2px; flex-shrink: 0; }

    /* ── Drop zone ── */
    .drop-zone {
        border: 2px dashed var(--gray-300);
        border-radius: var(--radius-lg);
        padding: 40px 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s;
        background: var(--gray-50);
        position: relative;
    }
    .drop-zone:hover {
        border-color: var(--blue-mid);
        background: var(--blue-50);
    }
    .drop-zone.dragover {
        border-color: var(--blue-mid);
        background: var(--blue-50);
        box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
    }
    .drop-zone.has-file {
        border-style: solid;
        border-color: var(--success);
        background: var(--success-light);
    }

    .drop-zone-icon {
        width: 64px;
        height: 64px;
        background: var(--blue-pale);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        transition: all 0.25s;
    }
    .drop-zone-icon i { font-size: 1.5rem; color: var(--blue-mid); }
    .drop-zone:hover .drop-zone-icon { background: var(--blue-mid); }
    .drop-zone:hover .drop-zone-icon i { color: #fff; }
    .drop-zone.has-file .drop-zone-icon { background: var(--success); }
    .drop-zone.has-file .drop-zone-icon i { color: #fff; }

    .drop-zone-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--navy);
        margin-bottom: 4px;
    }
    .drop-zone-sub {
        font-size: 0.82rem;
        color: var(--gray-400);
    }

    /* File info (shown after selection) */
    .file-selected {
        display: none;
    }
    .file-selected.show {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        margin-top: 14px;
    }
    .file-selected-icon {
        width: 44px;
        height: 44px;
        background: #fee2e2;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .file-selected-icon i { font-size: 1.2rem; color: var(--danger); }
    .file-selected-info { flex: 1; min-width: 0; }
    .file-selected-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--navy);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .file-selected-size {
        font-size: 0.78rem;
        color: var(--gray-400);
    }
    .file-selected-remove {
        width: 30px;
        height: 30px;
        border: none;
        background: var(--gray-100);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--gray-500);
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .file-selected-remove:hover { background: var(--danger-light); color: var(--danger); }

    .upload-hint {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.76rem;
        color: var(--gray-400);
        margin-top: 10px;
    }
    .upload-hint i { font-size: 0.7rem; }

    /* Preview */
    .preview-box {
        display: none;
        margin-top: 16px;
        border-radius: var(--radius);
        overflow: hidden;
        border: 1px solid var(--gray-200);
        background: #525659;
    }
    .preview-box.show { display: block; }
    .preview-box-header {
        padding: 8px 14px;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .preview-box-header span {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--gray-500);
    }
    .preview-box embed {
        width: 100%;
        height: 350px;
        display: block;
    }

    /* Submit button */
    .btn-upload-submit {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
        color: #fff;
        border: none;
        border-radius: var(--radius);
        font-size: 0.95rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
        box-shadow: 0 4px 12px rgba(30,58,138,0.2);
    }
    .btn-upload-submit:hover:not(:disabled) {
        box-shadow: 0 6px 20px rgba(30,58,138,0.3);
        transform: translateY(-1px);
    }
    .btn-upload-submit:active:not(:disabled) { transform: translateY(0); }
    .btn-upload-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .upload-page-header { padding: 20px 16px; }
        .upload-page-header h1 { font-size: 1.15rem; }
        .upload-content { padding: 0 16px 32px; }
        .upload-card-body { padding: 18px 16px; }
        .upload-card-header { padding: 14px 16px; }
        .drop-zone { padding: 32px 16px; }
        .drop-zone-icon { width: 52px; height: 52px; }
        .drop-zone-icon i { font-size: 1.2rem; }
        .drop-zone-title { font-size: 0.9rem; }
        .preview-box embed { height: 280px; }
    }

    @media (max-width: 400px) {
        .upload-page-header { padding: 16px 14px; }
        .upload-page-header h1 { font-size: 1.05rem; }
        .upload-content { padding: 0 12px 28px; }
        .upload-card-body { padding: 14px 12px; }
        .upload-card-header { padding: 12px; gap: 8px; }
        .upload-card-header .type-icon { width: 36px; height: 36px; font-size: 0.9rem; }
        .upload-card-header .type-name { font-size: 0.88rem; }
        .drop-zone { padding: 24px 12px; }
        .drop-zone-icon { width: 44px; height: 44px; margin-bottom: 10px; }
        .drop-zone-icon i { font-size: 1rem; }
        .drop-zone-title { font-size: 0.85rem; }
        .drop-zone-sub { font-size: 0.78rem; }
        .file-selected.show { padding: 10px 12px; gap: 10px; }
        .file-selected-icon { width: 36px; height: 36px; border-radius: 8px; }
        .file-selected-icon i { font-size: 1rem; }
        .file-selected-name { font-size: 0.82rem; }
        .btn-upload-submit { padding: 12px; font-size: 0.9rem; }
        .preview-box embed { height: 220px; }
    }
</style>

<!-- Page Header -->
<div class="upload-page-header">
    <a href="index.php?action=upload_list" class="back-link">
        <i class="fas fa-arrow-left"></i> Retour aux documents
    </a>
    <h1>
        <i class="fas fa-cloud-upload-alt"></i>
        Soumettre un document
    </h1>
    <div class="subtitle">Téléversez votre fichier PDF pour compléter votre dossier</div>
</div>

<div class="upload-content">

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:var(--radius);border:none;font-size:0.88rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="upload-card">
        <div class="upload-card-header">
            <div class="type-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div>
                <div class="type-name"><?= sanitize($typeDoc['designation']) ?></div>
            </div>
            <span class="type-badge <?= $typeDoc['est_obligatoire'] ? 'type-badge-oblig' : 'type-badge-opt' ?>">
                <?= $typeDoc['est_obligatoire'] ? 'Obligatoire' : 'Optionnel' ?>
            </span>
        </div>

        <div class="upload-card-body">
            <?php if (!empty($typeDoc['description'])): ?>
            <div class="upload-desc">
                <i class="fas fa-info-circle"></i>
                <span><?= sanitize($typeDoc['description']) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="index.php?action=upload_process" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="dossier_id" value="<?= $dossier['id'] ?>">
                <input type="hidden" name="type_document_id" value="<?= $typeDoc['id'] ?>">

                <!-- Drop zone -->
                <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <div id="dropDefault">
                        <div class="drop-zone-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="drop-zone-title">Glissez votre fichier ici</div>
                        <div class="drop-zone-sub">ou cliquez pour sélectionner un PDF</div>
                    </div>
                    <div id="dropSuccess" style="display:none;">
                        <div class="drop-zone-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="drop-zone-title" style="color:var(--success);">Fichier sélectionné</div>
                        <div class="drop-zone-sub">Cliquez pour changer de fichier</div>
                    </div>
                    <input type="file" id="fileInput" name="document" class="d-none" accept=".pdf" required>
                </div>

                <!-- File info bar -->
                <div class="file-selected" id="fileSelectedBar">
                    <div class="file-selected-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="file-selected-info">
                        <div class="file-selected-name" id="fileName"></div>
                        <div class="file-selected-size" id="fileSize"></div>
                    </div>
                    <button type="button" class="file-selected-remove" onclick="clearFile(event)" title="Supprimer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="upload-hint">
                    <i class="fas fa-shield-alt"></i>
                    Format accepté : PDF uniquement — Taille max : <?= formatFileSize(MAX_FILE_SIZE) ?>
                </div>

                <!-- Preview -->
                <div class="preview-box" id="previewBox">
                    <div class="preview-box-header">
                        <span><i class="fas fa-eye me-1"></i>Aperçu du document</span>
                    </div>
                    <embed id="previewPdf" type="application/pdf">
                </div>

                <button type="submit" class="btn-upload-submit" id="submitBtn" disabled>
                    <i class="fas fa-upload"></i> Envoyer le document
                </button>
            </form>
        </div>
    </div>

</div>

<script>
(function() {
    var dropZone = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');
    var maxSize = <?= MAX_FILE_SIZE ?>;

    ['dragover','dragenter'].forEach(function(e) {
        dropZone.addEventListener(e, function(ev) { ev.preventDefault(); dropZone.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(function(e) {
        dropZone.addEventListener(e, function(ev) { ev.preventDefault(); dropZone.classList.remove('dragover'); });
    });
    dropZone.addEventListener('drop', function(e) { fileInput.files = e.dataTransfer.files; handleFile(); });
    fileInput.addEventListener('change', handleFile);

    window.handleFile = handleFile;
    window.clearFile = clearFile;

    function handleFile() {
        var file = fileInput.files[0];
        if (!file) return;

        if (file.size > maxSize) {
            Swal.fire({ icon: 'error', title: 'Fichier trop volumineux', text: 'Taille maximale : <?= formatFileSize(MAX_FILE_SIZE) ?>', confirmButtonColor: '#1e3a8a' });
            fileInput.value = '';
            return;
        }

        var ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'pdf') {
            Swal.fire({ icon: 'error', title: 'Format non autorisé', text: 'Seuls les fichiers PDF sont acceptés.', confirmButtonColor: '#1e3a8a' });
            fileInput.value = '';
            return;
        }

        // Show file info
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1048576).toFixed(2) + ' Mo';
        document.getElementById('fileSelectedBar').classList.add('show');

        // Update drop zone state
        document.getElementById('dropDefault').style.display = 'none';
        document.getElementById('dropSuccess').style.display = 'block';
        dropZone.classList.add('has-file');

        // Enable submit
        document.getElementById('submitBtn').disabled = false;

        // Preview
        var previewBox = document.getElementById('previewBox');
        var previewPdf = document.getElementById('previewPdf');
        previewPdf.src = URL.createObjectURL(file);
        previewBox.classList.add('show');
    }

    function clearFile(e) {
        e.stopPropagation();
        fileInput.value = '';

        document.getElementById('fileSelectedBar').classList.remove('show');
        document.getElementById('dropDefault').style.display = '';
        document.getElementById('dropSuccess').style.display = 'none';
        dropZone.classList.remove('has-file');
        document.getElementById('submitBtn').disabled = true;

        var previewBox = document.getElementById('previewBox');
        previewBox.classList.remove('show');
        document.getElementById('previewPdf').src = '';
    }
})();
</script>

<?php require 'views/layout/footer.php'; ?>
