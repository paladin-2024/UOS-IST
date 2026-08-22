    <footer class="text-center py-4 mt-4">
        <p class="text-muted small mb-0">
            &copy; <?= date('Y') ?> — <?= sanitize($_SESSION['dossier_universite_sigle'] ?? ($_SESSION['dossier_universite_nom'] ?? 'Espace de Scolarité')) ?> — Espace de Scolarité
        </p>
    </footer>
</div><!-- /.main-wrapper -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Preloader dismiss ──
(function() {
    var preloader = document.getElementById('pagePreloader');
    if (preloader) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                preloader.classList.add('hide');
                setTimeout(function() { preloader.remove(); }, 500);
            }, 300);
        });
        // Fallback: force hide after 5s even if load event is delayed
        setTimeout(function() {
            if (preloader && !preloader.classList.contains('hide')) {
                preloader.classList.add('hide');
                setTimeout(function() { preloader.remove(); }, 500);
            }
        }, 5000);
    }
})();
</script>
<script>
(function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    }

    if (toggle) {
        toggle.addEventListener('click', function() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
})();
</script>
<!-- Modal Visualisation Documents -->
<div class="modal fade" id="docViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" id="docViewerDialog">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header py-2 px-3 docviewer-header" style="background:var(--navy);border:none;">
                <div class="docviewer-title-row">
                    <i class="fas fa-file-pdf" style="color:var(--gold);"></i>
                    <span class="fw-bold text-white docviewer-title-text" id="docViewerTitle">Document</span>
                </div>
                <div class="docviewer-actions">
                    <span id="docViewerNav" class="d-none docviewer-nav-group">
                        <button class="btn btn-sm btn-outline-light" onclick="DocViewer.prev()" id="docViewerPrev" style="border-radius:6px;padding:2px 8px;">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="text-white mx-1" style="font-size:0.8rem;" id="docViewerCounter">1/1</span>
                        <button class="btn btn-sm btn-outline-light" onclick="DocViewer.next()" id="docViewerNext" style="border-radius:6px;padding:2px 8px;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </span>
                    <button class="btn btn-sm btn-outline-light" onclick="DocViewer.toggleFullscreen()" id="docViewerFullscreenBtn" title="Plein écran" style="border-radius:6px;padding:2px 8px;">
                        <i class="fas fa-expand" id="docViewerFullscreenIcon"></i>
                    </button>
                    <a href="#" class="btn btn-sm btn-outline-light" id="docViewerDownload" title="Télécharger" target="_blank" style="border-radius:6px;padding:2px 8px;">
                        <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-dismiss="modal" style="border-radius:6px;padding:2px 8px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0" style="background:#525659;" id="docViewerBody">
                <iframe id="docViewerFrame" class="docviewer-iframe"></iframe>
            </div>
        </div>
    </div>
</div>
<style>
    /* ── DocViewer base ── */
    .docviewer-iframe {
        width: 100%;
        height: 75vh;
        border: none;
        display: block;
    }
    .docviewer-header {
        flex-wrap: wrap;
        gap: 6px;
    }
    .docviewer-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        flex: 1 1 auto;
    }
    .docviewer-title-text {
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 300px;
    }
    .docviewer-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    .docviewer-nav-group {
        display: inline-flex;
        align-items: center;
        margin-right: 4px;
    }

    /* ── Fullscreen mode ── */
    #docViewerModal .modal-dialog.fullscreen-mode {
        max-width: 100%;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
    }
    #docViewerModal .modal-dialog.fullscreen-mode .modal-content {
        height: 100vh;
        border-radius: 0;
    }
    #docViewerModal .modal-dialog.fullscreen-mode .docviewer-iframe {
        height: calc(100vh - 44px);
    }

    /* ── Mobile responsive (≤576px) ── */
    @media (max-width: 576px) {
        #docViewerModal .modal-dialog {
            margin: 0;
            max-width: 100%;
            width: 100%;
            height: 100%;
            min-height: 100vh;
        }
        #docViewerModal .modal-content {
            border-radius: 0 !important;
            height: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .docviewer-header {
            flex-wrap: nowrap;
            padding: 8px 10px !important;
        }
        .docviewer-title-text {
            font-size: 0.8rem;
            max-width: 120px;
        }
        .docviewer-actions .btn {
            padding: 4px 10px !important;
            font-size: 0.85rem;
            min-width: 34px;
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .docviewer-iframe {
            flex: 1 1 auto;
            height: calc(100vh - 52px);
            height: calc(100dvh - 52px);
        }
        #docViewerModal .modal-dialog.fullscreen-mode .docviewer-iframe {
            height: calc(100vh - 52px);
            height: calc(100dvh - 52px);
        }
    }

    /* ── Small phones (≤400px) ── */
    @media (max-width: 400px) {
        .docviewer-title-text {
            max-width: 80px;
            font-size: 0.75rem;
        }
        .docviewer-actions .btn {
            padding: 3px 7px !important;
            font-size: 0.8rem;
            min-width: 30px;
            min-height: 30px;
        }
        .docviewer-nav-group .mx-1 {
            font-size: 0.7rem !important;
        }
    }

    /* ── Tablet (≤768px) ── */
    @media (max-width: 768px) and (min-width: 577px) {
        #docViewerModal .modal-dialog {
            margin: 10px;
            max-width: calc(100% - 20px);
        }
        .docviewer-title-text {
            max-width: 200px;
        }
        .docviewer-iframe {
            height: 70vh;
        }
    }
</style>
<script>
const DocViewer = {
    docs: [],
    currentIndex: 0,
    isFullscreen: false,

    openSingle: function(url, title) {
        this.docs = [{ url: url, title: title }];
        this.currentIndex = 0;
        this._render();
        document.getElementById('docViewerNav').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('docViewerModal')).show();
    },

    openAll: function(docs) {
        if (!docs || docs.length === 0) return;
        this.docs = docs;
        this.currentIndex = 0;
        this._render();
        if (docs.length > 1) {
            document.getElementById('docViewerNav').classList.remove('d-none');
        } else {
            document.getElementById('docViewerNav').classList.add('d-none');
        }
        new bootstrap.Modal(document.getElementById('docViewerModal')).show();
    },

    prev: function() {
        if (this.currentIndex > 0) { this.currentIndex--; this._render(); }
    },

    next: function() {
        if (this.currentIndex < this.docs.length - 1) { this.currentIndex++; this._render(); }
    },

    _render: function() {
        var doc = this.docs[this.currentIndex];
        document.getElementById('docViewerTitle').textContent = doc.title || 'Document';
        document.getElementById('docViewerFrame').src = doc.url;
        document.getElementById('docViewerDownload').href = doc.url;
        document.getElementById('docViewerCounter').textContent = (this.currentIndex + 1) + '/' + this.docs.length;
        document.getElementById('docViewerPrev').disabled = this.currentIndex === 0;
        document.getElementById('docViewerNext').disabled = this.currentIndex === this.docs.length - 1;
    },

    toggleFullscreen: function() {
        var dialog = document.getElementById('docViewerDialog');
        var icon = document.getElementById('docViewerFullscreenIcon');
        this.isFullscreen = !this.isFullscreen;
        if (this.isFullscreen) {
            dialog.classList.add('fullscreen-mode');
            icon.className = 'fas fa-compress';
        } else {
            dialog.classList.remove('fullscreen-mode');
            icon.className = 'fas fa-expand';
        }
    }
};

document.getElementById('docViewerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('docViewerFrame').src = '';
    var dialog = document.getElementById('docViewerDialog');
    dialog.classList.remove('fullscreen-mode');
    document.getElementById('docViewerFullscreenIcon').className = 'fas fa-expand';
    DocViewer.isFullscreen = false;
});
</script>
<script>
const ExportExcel = {
    start: function(url) {
        // Show modal with progress steps
        Swal.fire({
            title: '<i class="fas fa-file-excel" style="color:#198754;font-size:1.5rem;"></i>',
            html: '<div id="exportProgress">' +
                '<div style="font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:16px;">Exportation en cours...</div>' +
                '<div style="margin-bottom:20px;">' +
                    '<div style="background:#e9ecef;border-radius:8px;height:8px;overflow:hidden;">' +
                        '<div id="exportBar" style="width:0%;height:100%;background:linear-gradient(90deg,#198754,#20c997);border-radius:8px;transition:width 0.4s ease;"></div>' +
                    '</div>' +
                '</div>' +
                '<div id="exportSteps" style="text-align:left;max-width:280px;margin:0 auto;">' +
                    '<div class="export-step" id="step1" style="display:flex;align-items:center;gap:10px;padding:6px 0;color:#6c757d;font-size:0.85rem;">' +
                        '<i class="fas fa-circle-notch fa-spin" style="color:#2E86AB;width:16px;"></i>' +
                        '<span>Connexion à la base de données...</span>' +
                    '</div>' +
                    '<div class="export-step" id="step2" style="display:flex;align-items:center;gap:10px;padding:6px 0;color:#adb5bd;font-size:0.85rem;">' +
                        '<i class="far fa-circle" style="width:16px;"></i>' +
                        '<span>Récupération des données étudiants</span>' +
                    '</div>' +
                    '<div class="export-step" id="step3" style="display:flex;align-items:center;gap:10px;padding:6px 0;color:#adb5bd;font-size:0.85rem;">' +
                        '<i class="far fa-circle" style="width:16px;"></i>' +
                        '<span>Chargement des photos</span>' +
                    '</div>' +
                    '<div class="export-step" id="step4" style="display:flex;align-items:center;gap:10px;padding:6px 0;color:#adb5bd;font-size:0.85rem;">' +
                        '<i class="far fa-circle" style="width:16px;"></i>' +
                        '<span>Génération du fichier Excel</span>' +
                    '</div>' +
                    '<div class="export-step" id="step5" style="display:flex;align-items:center;gap:10px;padding:6px 0;color:#adb5bd;font-size:0.85rem;">' +
                        '<i class="far fa-circle" style="width:16px;"></i>' +
                        '<span>Préparation du téléchargement</span>' +
                    '</div>' +
                '</div>' +
            '</div>',
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: { popup: 'export-modal-popup' },
            didOpen: function() {
                ExportExcel._runExport(url);
            }
        });
    },

    _activateStep: function(stepNum, totalSteps) {
        var bar = document.getElementById('exportBar');
        var pct = Math.round((stepNum / totalSteps) * 100);
        if (bar) bar.style.width = pct + '%';

        for (var i = 1; i <= totalSteps; i++) {
            var step = document.getElementById('step' + i);
            if (!step) continue;
            var icon = step.querySelector('i');
            if (i < stepNum) {
                step.style.color = '#198754';
                icon.className = 'fas fa-check-circle';
                icon.style.color = '#198754';
            } else if (i === stepNum) {
                step.style.color = '#0f172a';
                step.style.fontWeight = '600';
                icon.className = 'fas fa-circle-notch fa-spin';
                icon.style.color = '#2E86AB';
            } else {
                step.style.color = '#adb5bd';
                step.style.fontWeight = '400';
                icon.className = 'far fa-circle';
                icon.style.color = '#adb5bd';
            }
        }
    },

    _runExport: function(url) {
        var self = this;
        var totalSteps = 5;

        self._activateStep(1, totalSteps);

        setTimeout(function() {
            self._activateStep(2, totalSteps);
        }, 600);

        setTimeout(function() {
            self._activateStep(3, totalSteps);
        }, 1200);

        setTimeout(function() {
            self._activateStep(4, totalSteps);
        }, 1800);

        // Start actual fetch after visual delay
        setTimeout(function() {
            fetch(url)
                .then(function(response) {
                    if (!response.ok) throw new Error('Erreur serveur');
                    self._activateStep(5, totalSteps);
                    var disposition = response.headers.get('Content-Disposition');
                    var filename = 'Rapport_Dossiers.xlsx';
                    if (disposition) {
                        var match = disposition.match(/filename="?([^";\n]+)"?/);
                        if (match) filename = match[1];
                    }
                    return response.blob().then(function(blob) {
                        return { blob: blob, filename: filename };
                    });
                })
                .then(function(result) {
                    // Mark all steps done
                    for (var i = 1; i <= totalSteps; i++) {
                        var step = document.getElementById('step' + i);
                        if (step) {
                            step.style.color = '#198754';
                            step.style.fontWeight = '400';
                            var icon = step.querySelector('i');
                            icon.className = 'fas fa-check-circle';
                            icon.style.color = '#198754';
                        }
                    }
                    var bar = document.getElementById('exportBar');
                    if (bar) bar.style.width = '100%';

                    // Trigger download
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(result.blob);
                    a.download = result.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(a.href);

                    // Show success after small delay
                    setTimeout(function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Exportation terminée',
                            html: '<div style="color:#6c757d;font-size:0.9rem;">Le fichier <strong>' + result.filename + '</strong> a été téléchargé.</div>',
                            confirmButtonColor: '#198754',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>OK',
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }, 500);
                })
                .catch(function(err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur d\'exportation',
                        html: '<div style="color:#6c757d;font-size:0.9rem;">Une erreur est survenue lors de la génération du fichier Excel.</div>',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '<i class="fas fa-times me-1"></i>Fermer'
                    });
                });
        }, 2000);
    }
};
</script>
<script>
// ── Auto-spinner on all form submit buttons ──
(function() {
    function wrapBtn(btn) {
        if (btn.querySelector('.btn-spinner')) return;
        var spinner = document.createElement('span');
        spinner.className = 'btn-spinner';
        var label = document.createElement('span');
        label.className = 'btn-label';
        while (btn.firstChild) label.appendChild(btn.firstChild);
        btn.style.position = 'relative';
        btn.appendChild(spinner);
        btn.appendChild(label);
    }

    function activateSpinner(btn) {
        wrapBtn(btn);
        btn.classList.add('btn-loading');
    }

    // Forms with type="submit" buttons
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (btn && btn.tagName === 'BUTTON') activateSpinner(btn);
        });
    });

    // SweetAlert confirmations that call .submit() — intercept via hidden form submits
    var origSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        var btn = this.querySelector('button[type="submit"], button[type="button"]');
        if (btn && btn.tagName === 'BUTTON' && !btn.classList.contains('btn-loading')) {
            wrapBtn(btn);
            btn.classList.add('btn-loading');
        }
        origSubmit.call(this);
    };
})();
</script>
</body>
</html>
