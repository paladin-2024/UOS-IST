<?php
include "./views/include/header.php";
$universite = new Universite();
$db = Connexion::getInstance()->getPDO();

$currentUserId = $_SESSION['id'];

// Récupérer toutes les années académiques
$allYears = $universite->getAcademicYears();
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeActiveId = $anneeEnCours ? $anneeEnCours['idannee_acad'] : null;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Statistiques de présences</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item"><a href="cours/seances.list">Séances de cours</a></li>
                <li class="breadcrumb-item active">Statistiques</li>
            </ol>
        </nav>
    </div>

    <style>
        .stats-compact .card { margin-bottom: 0.4rem; }
        .stats-compact .card-body { padding: 0.4rem 0.6rem; }
        .stats-compact .card-title { font-size: 0.82rem; margin-bottom: 0.2rem; padding-top: 0.3rem; }
        .stats-compact .form-label { font-size: 0.78rem; margin-bottom: 0.15rem; }
        .stats-compact .form-select, .stats-compact .form-control { font-size: 0.78rem; padding: 0.2rem 0.4rem; }
        .stats-compact .select2-container { font-size: 0.78rem; }
        .stats-compact .select2-container .select2-selection--single { height: 28px; min-height: 28px; padding: 0.1rem 0.3rem; }
        .stats-compact .select2-container .select2-selection--single .select2-selection__rendered { line-height: 26px; font-size: 0.78rem; padding-left: 4px; }
        .stats-compact .select2-container .select2-selection--single .select2-selection__arrow { height: 26px; }
        .stats-compact .btn { font-size: 0.78rem; padding: 0.25rem 0.5rem; }
        .stats-compact .table { font-size: 0.75rem; margin-bottom: 0; }
        .stats-compact .table th, .stats-compact .table td { padding: 0.15rem 0.35rem; vertical-align: middle; }
        .stats-compact .badge { font-size: 0.68rem; padding: 0.15rem 0.35rem; }
        .stats-compact .info-card .card-icon { width: 2rem; height: 2rem; font-size: 1rem; }
        .stats-compact .info-card .card-icon i { font-size: 1rem !important; }
        .stats-compact .info-card h6 { font-size: 1rem; margin-bottom: 0; }
        .stats-compact .info-card .card-title { font-size: 0.72rem; padding-top: 0.2rem; margin-bottom: 0.1rem; }
        .stats-compact .info-card .card-body { padding: 0.3rem 0.5rem; }
        .stats-compact .dataTable-wrapper .dataTable-top,
        .stats-compact .dataTable-wrapper .dataTable-bottom { font-size: 0.75rem; padding: 0.2rem 0; }
        .stats-compact .dataTable-wrapper .dataTable-input { font-size: 0.75rem; padding: 0.15rem 0.3rem; }
        .stats-compact .row.g-3 { --bs-gutter-y: 0.4rem; }
    </style>

    <section class="section stats-compact">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label for="selectAnnee" class="form-label">Année académique</label>
                                <select id="selectAnnee" class="form-select">
                                    <option value="">-- Choisir une année --</option>
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= ($year['idannee_acad'] == $anneeActiveId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                            <?= ($year['idannee_acad'] == $anneeActiveId) ? ' (En cours)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="selectEcue" class="form-label">Cours (ECUE)</label>
                                <select id="selectEcue" class="form-select" disabled>
                                    <option value="">-- Sélectionnez d'abord une année --</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button id="btnChargerStats" class="btn btn-primary w-100" disabled>
                                    <i class="bi bi-bar-chart-line"></i> Afficher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone de résultats -->
        <div id="statsContainer" style="display:none;">
            <!-- Résumé global -->
            <div class="row g-2">
                <div class="col-lg-3 col-6">
                    <div class="card info-card">
                        <div class="card-body">
                            <h5 class="card-title">Séances <span>| Total</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background:#e0f7fa;">
                                    <i class="bi bi-calendar-event" style="color:#00bcd4;"></i>
                                </div>
                                <div class="ps-2"><h6 id="statTotalSeances">0</h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="card info-card">
                        <div class="card-body">
                            <h5 class="card-title">Étudiants <span>| Inscrits</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background:#e8f5e9;">
                                    <i class="bi bi-people" style="color:#4caf50;"></i>
                                </div>
                                <div class="ps-2"><h6 id="statTotalEtudiants">0</h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="card info-card">
                        <div class="card-body">
                            <h5 class="card-title">Présences <span>| Total</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background:#fff3e0;">
                                    <i class="bi bi-check2-all" style="color:#ff9800;"></i>
                                </div>
                                <div class="ps-2"><h6 id="statTotalPresences">0</h6></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="card info-card">
                        <div class="card-body">
                            <h5 class="card-title">Taux moyen <span>| Présence</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center" style="background:#fce4ec;">
                                    <i class="bi bi-graph-up" style="color:#e91e63;"></i>
                                </div>
                                <div class="ps-2"><h6 id="statTauxMoyen">0%</h6></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des séances -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Statistiques par séance</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm" id="tableSeances">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Titre</th>
                                            <th>Date</th>
                                            <th>Horaire</th>
                                            <th>Salle</th>
                                            <th>Présents</th>
                                            <th>Taux</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bodySeances"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau par étudiant -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Statistiques par étudiant
                                <span>| <a id="btnExportPdf" href="#" target="_blank" class="btn btn-warning btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                                </a></span>
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm" id="tableEtudiants">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Matricule</th>
                                            <th>Nom & Prénom</th>
                                            <th>Présences</th>
                                            <th>Total séances</th>
                                            <th>Taux</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bodyEtudiants"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spinner de chargement -->
        <div id="statsLoading" style="display:none;" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-1 small">Chargement des statistiques...</p>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>

<script>
    $(document).ready(function() {
        var etudiantsDataTable = null;

        // Initialiser Select2 sur les deux selects
        $('#selectAnnee').select2({
            placeholder: '-- Choisir une année --',
            allowClear: true,
            width: '100%'
        });
        $('#selectEcue').select2({
            placeholder: '-- Sélectionnez d\'abord une année --',
            allowClear: true,
            width: '100%'
        });

        // Charger les ECUEs quand on change l'année
        $(document).on('change', '#selectAnnee', function() {
            var anneeId = $(this).val();
            var $selectEcue = $('#selectEcue');
            var $btnCharger = $('#btnChargerStats');

            // Réinitialiser le select ECUE
            $selectEcue.empty();
            $selectEcue.append('<option value="">Chargement...</option>');
            $selectEcue.prop('disabled', true);
            $selectEcue.trigger('change.select2');
            $btnCharger.prop('disabled', true);
            $('#statsContainer').hide();

            if (!anneeId) {
                $selectEcue.empty().append('<option value="">-- Sélectionnez d\'abord une année --</option>');
                $selectEcue.trigger('change.select2');
                return;
            }

            $.ajax({
                url: 'controller/get_ecues_for_stats.php',
                type: 'GET',
                data: { annee_id: anneeId },
                dataType: 'json',
                success: function(data) {
                    $selectEcue.empty();
                    if (data.success && data.ecues.length > 0) {
                        $selectEcue.append('<option value="">-- Choisir un cours --</option>');
                        $.each(data.ecues, function(index, e) {
                            $selectEcue.append(
                                '<option value="' + e.idECUE + '">' +
                                e.designationECUE + ' (' + e.designationPromotion + ' - Sem. ' + e.numeroSemestre + ' | ' + e.designationSection + ')' +
                                '</option>'
                            );
                        });
                        $selectEcue.prop('disabled', false);
                    } else {
                        $selectEcue.append('<option value="">Aucun cours trouvé</option>');
                    }
                    $selectEcue.trigger('change.select2');
                },
                error: function() {
                    $selectEcue.empty().append('<option value="">Erreur de chargement</option>');
                    $selectEcue.trigger('change.select2');
                }
            });
        });

        // Activer le bouton quand un ECUE est sélectionné
        $(document).on('change', '#selectEcue', function() {
            $('#btnChargerStats').prop('disabled', !$(this).val());
        });

        // Charger les statistiques
        $(document).on('click', '#btnChargerStats', function() {
            var ecueId = $('#selectEcue').val();
            var anneeId = $('#selectAnnee').val();
            if (!ecueId || !anneeId) return;

            $('#statsContainer').hide();
            $('#statsLoading').show();

            $.ajax({
                url: 'controller/get_stats_presences.php',
                type: 'GET',
                data: { ecue_id: ecueId, annee_id: anneeId },
                dataType: 'json',
                success: function(data) {
                    $('#statsLoading').hide();
                    if (!data.success) {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message });
                        return;
                    }

                    // Remplir les cartes résumé
                    $('#statTotalSeances').text(data.global.total_seances);
                    $('#statTotalEtudiants').text(data.global.total_etudiants);
                    $('#statTotalPresences').text(data.global.total_presences);
                    $('#statTauxMoyen').text(data.global.taux_moyen + '%');

                    // Remplir le tableau des séances
                    var bodySeances = '';
                    $.each(data.global.seances, function(idx, s) {
                        var dateParts = s.date_seance.split('-');
                        var dateFormatted = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
                        var taux = s.total_etudiants > 0 ? Math.round((s.nb_presents / s.total_etudiants) * 100) : 0;
                        var tauxClass = taux >= 75 ? 'bg-success' : (taux >= 50 ? 'bg-warning' : 'bg-danger');
                        bodySeances +=
                            '<tr>' +
                            '<td>' + (idx + 1) + '</td>' +
                            '<td>' + s.titre + '</td>' +
                            '<td>' + dateFormatted + '</td>' +
                            '<td>' + s.heure_debut.substring(0, 5) + ' - ' + s.heure_fin.substring(0, 5) + '</td>' +
                            '<td>' + (s.salle || '-') + '</td>' +
                            '<td><span class="badge bg-info">' + s.nb_presents + '/' + s.total_etudiants + '</span></td>' +
                            '<td><span class="badge ' + tauxClass + '">' + taux + '%</span></td>' +
                            '</tr>';
                    });
                    $('#bodySeances').html(bodySeances);

                    // Remplir le tableau des étudiants
                    var bodyEtudiants = '';
                    $.each(data.etudiants, function(idx, e) {
                        var tauxClass = e.taux_presence >= 75 ? 'bg-success' : (e.taux_presence >= 50 ? 'bg-warning' : 'bg-danger');
                        bodyEtudiants +=
                            '<tr>' +
                            '<td>' + (idx + 1) + '</td>' +
                            '<td>' + e.matricule + '</td>' +
                            '<td>' + e.noms + '</td>' +
                            '<td>' + e.nb_present + '</td>' +
                            '<td>' + e.total_seances + '</td>' +
                            '<td><span class="badge ' + tauxClass + '">' + e.taux_presence + '%</span></td>' +
                            '</tr>';
                    });
                    $('#bodyEtudiants').html(bodyEtudiants);

                    // Mettre à jour le lien d'export PDF
                    $('#btnExportPdf').attr('href', 'controller/export_stats_presences_pdf.php?ecue_id=' + ecueId + '&annee_id=' + anneeId);

                    // Détruire l'ancienne instance DataTable si elle existe
                    if (etudiantsDataTable) {
                        etudiantsDataTable.destroy();
                        etudiantsDataTable = null;
                    }

                    // Réinitialiser DataTable sur le tableau des étudiants
                    etudiantsDataTable = new simpleDatatables.DataTable('#tableEtudiants', {
                        searchable: true,
                        sortable: true,
                        paging: true,
                        perPage: 10
                    });

                    $('#statsContainer').show();
                },
                error: function() {
                    $('#statsLoading').hide();
                    Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de charger les statistiques' });
                }
            });
        });

        // Charger les ECUEs automatiquement si une année est déjà sélectionnée
        if ($('#selectAnnee').val()) {
            $('#selectAnnee').trigger('change');
        }
    });
</script>
