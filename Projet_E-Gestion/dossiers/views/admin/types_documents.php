<?php $pageTitle = 'Types de Documents — Espace de Scolarité'; ?>
<?php require 'views/layout/header.php'; ?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
        padding: 32px;
        margin-bottom: 28px;
    }
    .page-header h1 {
        color: #fff;
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
    }
    .page-header .subtitle {
        color: var(--gray-300);
        font-size: 0.88rem;
        margin-top: 4px;
    }
    .content-body {
        padding: 0 32px 40px;
    }
    .types-card {
        background: #fff;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .types-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .types-card-header h6 {
        font-weight: 700;
        color: var(--navy);
        margin: 0;
        font-size: 0.95rem;
    }
    .types-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .types-table th {
        background: var(--gray-50);
        padding: 10px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--gray-500);
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
        overflow: hidden;
    }
    .types-table td {
        padding: 10px 10px;
        font-size: 0.84rem;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .types-table tr:hover td {
        background: var(--blue-50);
    }
    /* Column widths */
    .col-ordre { width: 50px; }
    .col-code { width: 14%; }
    .col-designation { /* flex: takes remaining space */ }
    .col-cycles { width: 14%; }
    .col-statut { width: 12%; }
    .col-docs { width: 60px; }
    .col-actions { width: 120px; }

    .badge-cycle {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.68rem;
        font-weight: 600;
        margin: 1px 1px;
        white-space: nowrap;
    }
    .badge-cycle-premier { background: var(--blue-pale); color: var(--blue); }
    .badge-cycle-deuxieme { background: var(--gold-pale); color: var(--warning); }
    .badge-cycle-troisieme { background: var(--success-light); color: var(--success); }
    .badge-oui { background: var(--success-light); color: var(--success); padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
    .badge-non { background: var(--gray-100); color: var(--gray-400); padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
    .badge-inactif { background: var(--danger-light); color: var(--danger); padding: 2px 7px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
    .statut-stack { display: flex; flex-direction: column; align-items: center; gap: 3px; }

    /* ── Action buttons ── */
    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 6px rgba(30,58,138,0.25);
    }
    .btn-add:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30,58,138,0.35);
    }
    .btn-add:active { transform: translateY(0); }

    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid transparent;
        background: none;
        cursor: pointer;
        width: 32px;
        height: 32px;
        padding: 0;
        border-radius: 7px;
        transition: all 0.2s;
        font-size: 0.82rem;
        font-family: inherit;
    }
    .btn-action-edit {
        color: var(--blue-mid);
        background: var(--blue-50);
        border-color: var(--blue-pale);
    }
    .btn-action-edit:hover {
        background: var(--blue-pale);
        border-color: var(--blue-mid);
        box-shadow: 0 2px 6px rgba(37,99,235,0.15);
    }
    .btn-action-delete {
        color: var(--danger);
        background: #fff5f5;
        border-color: #fed7d7;
    }
    .btn-action-delete:hover {
        background: var(--danger-light);
        border-color: var(--danger);
        box-shadow: 0 2px 6px rgba(220,38,38,0.15);
    }
    .actions-cell { display: flex; align-items: center; justify-content: center; gap: 4px; }

    .code-badge {
        font-family: 'Courier New', monospace;
        background: var(--gray-100);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--gray-700);
    }
    .doc-count {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .empty-state {
        text-align: center;
        padding: 60px 24px;
        color: var(--gray-400);
    }
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 16px;
        color: var(--gray-300);
    }

    /* ── Pagination ── */
    .pagination-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 24px;
        border-top: 1px solid var(--gray-200);
        background: var(--gray-50);
        flex-wrap: wrap;
        gap: 10px;
    }
    .pagination-info {
        font-size: 0.8rem;
        color: var(--gray-500);
        font-weight: 500;
    }
    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .pagination-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 8px;
        border: 1.5px solid var(--gray-200);
        background: #fff;
        color: var(--gray-600);
        border-radius: 7px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.15s;
    }
    .pagination-btn:hover:not(:disabled):not(.active) {
        border-color: var(--blue-mid);
        color: var(--blue-mid);
        background: var(--blue-50);
    }
    .pagination-btn.active {
        background: var(--blue-mid);
        border-color: var(--blue-mid);
        color: #fff;
        box-shadow: 0 2px 6px rgba(37,99,235,0.25);
    }
    .pagination-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .pagination-perpage {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pagination-perpage label {
        font-size: 0.8rem;
        color: var(--gray-500);
        font-weight: 500;
        white-space: nowrap;
    }
    .pagination-perpage select {
        padding: 4px 8px;
        border: 1.5px solid var(--gray-200);
        border-radius: 6px;
        font-size: 0.8rem;
        font-family: inherit;
        color: var(--gray-700);
        background: #fff;
        cursor: pointer;
    }

    @media (max-width: 992px) {
        .col-code { width: 16%; }
        .col-cycles { width: 12%; }
        .col-actions { width: 90px; }
        .code-badge { font-size: 0.72rem; padding: 2px 5px; }
    }

    @media (max-width: 768px) {
        .page-header { padding: 24px 16px; }
        .page-header h1 { font-size: 1.25rem; }
        .content-body { padding: 0 12px 32px; }
        .types-card-header { padding: 16px; }
        .pagination-bar { padding: 12px 16px; flex-direction: column; align-items: stretch; }
        .pagination-controls { justify-content: center; }
        .pagination-info { text-align: center; }
        .pagination-perpage { justify-content: center; }

        /* Card layout on mobile */
        .types-table { table-layout: auto; }
        .types-table thead { display: none; }
        .types-table tbody tr {
            display: block;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            margin: 8px 12px;
            padding: 14px;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }
        .types-table tbody tr:hover td { background: transparent; }
        .types-table td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 0;
            border: none;
            font-size: 0.84rem;
            overflow: visible;
        }
        .types-table td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-400);
            flex-shrink: 0;
            margin-right: 10px;
        }
        .types-table td div[style*="white-space:nowrap"] {
            white-space: normal !important;
        }
        .statut-stack { flex-direction: row; gap: 4px; }
        .actions-cell { justify-content: flex-end; }
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-file-invoice me-2"></i>Types de Documents</h1>
    <div class="subtitle">Gérer les types de documents requis pour les dossiers de scolarité</div>
</div>

<div class="content-body">

    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:var(--radius);border:none;font-size:0.88rem;">
        <i class="fas fa-check-circle me-2"></i><?= sanitize($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:var(--radius);border:none;font-size:0.88rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?= sanitize($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="types-card">
        <div class="types-card-header">
            <h6><i class="fas fa-list me-2" style="color:var(--blue-mid);"></i>Liste des types de documents (<span id="visibleCount"><?= count($types) ?></span>)</h6>
            <button type="button" class="btn-add" onclick="TypeDoc.openForm()">
                <i class="fas fa-plus"></i> Ajouter un type
            </button>
        </div>

        <?php if (empty($types)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <div style="font-weight:600;font-size:1rem;color:var(--gray-600);margin-bottom:4px;">Aucun type de document</div>
            <div style="font-size:0.85rem;">Cliquez sur "Ajouter un type" pour commencer.</div>
        </div>
        <?php else: ?>
        <div class="types-table-wrapper">
            <table class="types-table" id="typesTable">
                <thead>
                    <tr>
                        <th class="col-ordre" style="text-align:center;">#</th>
                        <th class="col-code">Code</th>
                        <th class="col-designation">Désignation</th>
                        <th class="col-cycles" style="text-align:center;">Cycles</th>
                        <th class="col-statut" style="text-align:center;">Statut</th>
                        <th class="col-docs" style="text-align:center;">Docs</th>
                        <th class="col-actions" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="typesBody">
                    <?php foreach ($types as $index => $type): ?>
                    <?php $docCount = $model->countDocumentsByType($type['id']); ?>
                    <tr class="type-row" data-index="<?= $index ?>">
                        <td data-label="Ordre" style="text-align:center;font-weight:600;color:var(--gray-400);font-size:0.8rem;"><?= $type['ordre_affichage'] ?></td>
                        <td data-label="Code"><span class="code-badge"><?= sanitize($type['code']) ?></span></td>
                        <td data-label="Désignation">
                            <div style="font-weight:600;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($type['designation']) ?></div>
                            <?php if (!empty($type['description'])): ?>
                            <div style="font-size:0.73rem;color:var(--gray-400);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($type['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Cycles" style="text-align:center;">
                            <?php
                            $cycles = explode(',', $type['cycle_requis']);
                            foreach ($cycles as $c):
                                $c = trim($c);
                                $class = 'badge-cycle-' . strtolower($c);
                                $label = $c === 'Premier' ? '1er' : ($c === 'Deuxieme' ? '2è' : '3è');
                            ?>
                            <span class="badge-cycle <?= $class ?>"><?= $label ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td data-label="Statut" style="text-align:center;">
                            <div class="statut-stack">
                                <span class="<?= $type['est_obligatoire'] ? 'badge-oui' : 'badge-non' ?>">
                                    <?= $type['est_obligatoire'] ? 'Oblig.' : 'Optionnel' ?>
                                </span>
                                <?php if (!$type['est_actif']): ?>
                                <span class="badge-inactif">Inactif</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Docs" style="text-align:center;">
                            <span class="doc-count">
                                <i class="fas fa-file"></i> <?= $docCount ?>
                            </span>
                        </td>
                        <td data-label="">
                            <div class="actions-cell">
                                <button class="btn-action btn-action-edit" title="Modifier" onclick='TypeDoc.openForm(<?= json_encode($type) ?>)'>
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn-action btn-action-delete" title="Supprimer" onclick="TypeDoc.confirmDelete(<?= $type['id'] ?>, '<?= sanitize(addslashes($type['designation'])) ?>', <?= $docCount ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination bar -->
        <div class="pagination-bar" id="paginationBar">
            <div class="pagination-info" id="paginationInfo"></div>
            <div class="pagination-controls" id="paginationControls"></div>
            <div class="pagination-perpage">
                <label for="perPageSelect">Lignes par page :</label>
                <select id="perPageSelect" onchange="Pager.changePerPage(this.value)">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Hidden forms for POST submissions -->
<form id="formSaveType" method="POST" action="index.php?action=admin_type_document_save" style="display:none;">
    <input type="hidden" name="id" id="formTypeId">
    <input type="hidden" name="code" id="formTypeCode">
    <input type="hidden" name="designation" id="formTypeDesignation">
    <input type="hidden" name="description" id="formTypeDescription">
    <div id="formTypeCyclesContainer"></div>
    <input type="hidden" name="est_obligatoire" id="formTypeObligatoire">
    <input type="hidden" name="ordre_affichage" id="formTypeOrdre">
    <input type="hidden" name="est_actif" id="formTypeActif">
</form>

<form id="formDeleteType" method="POST" action="index.php?action=admin_type_document_delete" style="display:none;">
    <input type="hidden" name="id" id="formDeleteTypeId">
</form>

<script>
/* ── Pagination ── */
var Pager = {
    currentPage: 1,
    perPage: 10,
    rows: [],

    init: function() {
        this.rows = Array.from(document.querySelectorAll('.type-row'));
        if (this.rows.length === 0) return;
        this.render();
    },

    totalPages: function() {
        return Math.max(1, Math.ceil(this.rows.length / this.perPage));
    },

    render: function() {
        var total = this.rows.length;
        var totalPages = this.totalPages();
        if (this.currentPage > totalPages) this.currentPage = totalPages;

        var start = (this.currentPage - 1) * this.perPage;
        var end = start + this.perPage;

        this.rows.forEach(function(row, i) {
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });

        var showStart = total > 0 ? start + 1 : 0;
        var showEnd = Math.min(end, total);
        document.getElementById('paginationInfo').textContent =
            'Affichage ' + showStart + ' – ' + showEnd + ' sur ' + total + ' type(s)';
        document.getElementById('visibleCount').textContent = total;

        this._renderControls(totalPages);
    },

    _renderControls: function(totalPages) {
        var container = document.getElementById('paginationControls');
        var html = '';
        var self = this;

        html += '<button class="pagination-btn" onclick="Pager.goTo(1)" ' + (this.currentPage === 1 ? 'disabled' : '') + ' title="Première page"><i class="fas fa-angle-double-left"></i></button>';
        html += '<button class="pagination-btn" onclick="Pager.goTo(' + (this.currentPage - 1) + ')" ' + (this.currentPage === 1 ? 'disabled' : '') + ' title="Précédent"><i class="fas fa-angle-left"></i></button>';

        var startPage = Math.max(1, this.currentPage - 2);
        var endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        for (var p = startPage; p <= endPage; p++) {
            html += '<button class="pagination-btn' + (p === this.currentPage ? ' active' : '') + '" onclick="Pager.goTo(' + p + ')">' + p + '</button>';
        }

        html += '<button class="pagination-btn" onclick="Pager.goTo(' + (this.currentPage + 1) + ')" ' + (this.currentPage === totalPages ? 'disabled' : '') + ' title="Suivant"><i class="fas fa-angle-right"></i></button>';
        html += '<button class="pagination-btn" onclick="Pager.goTo(' + totalPages + ')" ' + (this.currentPage === totalPages ? 'disabled' : '') + ' title="Dernière page"><i class="fas fa-angle-double-right"></i></button>';

        container.innerHTML = html;
    },

    goTo: function(page) {
        var totalPages = this.totalPages();
        if (page < 1 || page > totalPages) return;
        this.currentPage = page;
        this.render();
    },

    changePerPage: function(val) {
        this.perPage = parseInt(val) || 10;
        this.currentPage = 1;
        this.render();
    }
};

document.addEventListener('DOMContentLoaded', function() { Pager.init(); });

/* ── Type Document CRUD ── */
var TypeDoc = {
    openForm: function(data) {
        var isEdit = !!data;
        var title = isEdit ? 'Modifier le type de document' : 'Nouveau type de document';

        Swal.fire({
            title: '<i class="fas fa-file-invoice" style="color:var(--blue-mid);font-size:1.3rem;margin-right:8px;"></i>' + title,
            html: TypeDoc._buildFormHtml(data),
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-save me-1"></i>' + (isEdit ? 'Enregistrer' : 'Créer'),
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#1e3a8a',
            cancelButtonColor: '#6c757d',
            width: '560px',
            customClass: { popup: 'swal-type-doc' },
            focusConfirm: false,
            preConfirm: function() {
                var code = document.getElementById('swalTypeCode').value.trim();
                var designation = document.getElementById('swalTypeDesignation').value.trim();
                var cycles = [];
                document.querySelectorAll('.swal-cycle-cb:checked').forEach(function(cb) { cycles.push(cb.value); });

                if (!code || !designation || cycles.length === 0) {
                    Swal.showValidationMessage('Veuillez remplir le Code, la Désignation et au moins un Cycle.');
                    return false;
                }
                return { code: code, designation: designation, cycles: cycles };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('formTypeId').value = isEdit ? data.id : 0;
                document.getElementById('formTypeCode').value = document.getElementById('swalTypeCode').value.trim().toUpperCase();
                document.getElementById('formTypeDesignation').value = document.getElementById('swalTypeDesignation').value.trim();
                document.getElementById('formTypeDescription').value = document.getElementById('swalTypeDescription').value.trim();

                var container = document.getElementById('formTypeCyclesContainer');
                container.innerHTML = '';
                document.querySelectorAll('.swal-cycle-cb:checked').forEach(function(cb) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'cycle_requis[]';
                    input.value = cb.value;
                    container.appendChild(input);
                });

                document.getElementById('formTypeObligatoire').value = document.getElementById('swalTypeObligatoire').checked ? 1 : 0;
                document.getElementById('formTypeOrdre').value = document.getElementById('swalTypeOrdre').value || 0;
                document.getElementById('formTypeActif').value = document.getElementById('swalTypeActif').checked ? 1 : 0;

                document.getElementById('formSaveType').submit();
            }
        });
    },

    _buildFormHtml: function(data) {
        var d = data || {};
        var cycles = (d.cycle_requis || '').split(',');
        return '<div style="text-align:left;font-size:0.88rem;">' +
            '<div class="row g-3">' +
                '<div class="col-md-6">' +
                    '<label class="form-label fw-bold" style="font-size:0.82rem;">Code <span style="color:var(--danger);">*</span></label>' +
                    '<input type="text" class="form-control form-control-sm" id="swalTypeCode" value="' + TypeDoc._esc(d.code || '') + '" placeholder="EX: DIPLOME_ETAT" style="text-transform:uppercase;">' +
                '</div>' +
                '<div class="col-md-6">' +
                    '<label class="form-label fw-bold" style="font-size:0.82rem;">Ordre d\'affichage</label>' +
                    '<input type="number" class="form-control form-control-sm" id="swalTypeOrdre" value="' + (d.ordre_affichage || 0) + '" min="0">' +
                '</div>' +
                '<div class="col-12">' +
                    '<label class="form-label fw-bold" style="font-size:0.82rem;">Désignation <span style="color:var(--danger);">*</span></label>' +
                    '<input type="text" class="form-control form-control-sm" id="swalTypeDesignation" value="' + TypeDoc._esc(d.designation || '') + '" placeholder="Ex: Diplôme d\'État">' +
                '</div>' +
                '<div class="col-12">' +
                    '<label class="form-label fw-bold" style="font-size:0.82rem;">Description</label>' +
                    '<textarea class="form-control form-control-sm" id="swalTypeDescription" rows="2" placeholder="Description optionnelle...">' + TypeDoc._esc(d.description || '') + '</textarea>' +
                '</div>' +
                '<div class="col-12">' +
                    '<label class="form-label fw-bold" style="font-size:0.82rem;">Cycles requis <span style="color:var(--danger);">*</span></label>' +
                    '<div class="d-flex gap-3 flex-wrap">' +
                        '<div class="form-check"><input class="form-check-input swal-cycle-cb" type="checkbox" value="Premier" id="swalCycle1"' + (cycles.indexOf('Premier') >= 0 ? ' checked' : '') + '><label class="form-check-label" for="swalCycle1" style="font-size:0.85rem;">1er Cycle</label></div>' +
                        '<div class="form-check"><input class="form-check-input swal-cycle-cb" type="checkbox" value="Deuxieme" id="swalCycle2"' + (cycles.indexOf('Deuxieme') >= 0 ? ' checked' : '') + '><label class="form-check-label" for="swalCycle2" style="font-size:0.85rem;">2ème Cycle</label></div>' +
                        '<div class="form-check"><input class="form-check-input swal-cycle-cb" type="checkbox" value="Troisieme" id="swalCycle3"' + (cycles.indexOf('Troisieme') >= 0 ? ' checked' : '') + '><label class="form-check-label" for="swalCycle3" style="font-size:0.85rem;">3ème Cycle</label></div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-6">' +
                    '<div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="swalTypeObligatoire"' + (d.est_obligatoire === undefined || d.est_obligatoire == 1 ? ' checked' : '') + '><label class="form-check-label fw-bold" for="swalTypeObligatoire" style="font-size:0.82rem;">Obligatoire</label></div>' +
                '</div>' +
                '<div class="col-6">' +
                    '<div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="swalTypeActif"' + (d.est_actif === undefined || d.est_actif == 1 ? ' checked' : '') + '><label class="form-check-label fw-bold" for="swalTypeActif" style="font-size:0.82rem;">Actif</label></div>' +
                '</div>' +
            '</div>' +
        '</div>';
    },

    _esc: function(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML.replace(/"/g, '&quot;');
    },

    confirmDelete: function(id, name, docCount) {
        if (docCount > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Suppression impossible',
                html: '<div style="font-size:0.9rem;">Le type <strong>' + name + '</strong> est utilisé par <strong>' + docCount + '</strong> document(s) uploadé(s).<br>Vous pouvez le désactiver à la place.</div>',
                confirmButtonColor: '#1e3a8a',
                confirmButtonText: 'Compris'
            });
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Confirmer la suppression',
            html: '<div style="font-size:0.9rem;">Voulez-vous vraiment supprimer le type <strong>' + name + '</strong> ?</div>',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i>Supprimer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('formDeleteTypeId').value = id;
                document.getElementById('formDeleteType').submit();
            }
        });
    }
};
</script>

<?php require 'views/layout/footer.php'; ?>
