<?php include "./views/include/header.php";

$agentModel = new Agent();
$structure = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
// Restreindre aux agents Administratifs uniquement
$agents = $agentModel->getAgentsByType('Administratif', $search, 1000);
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Présences Journalières</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Accueil</a></li>
        <li class="breadcrumb-item active">Présences</li>
      </ol>
    </nav>
  </div>

  <section class="section dashboard">
    <div class="row">
      <div class="col-lg-12">
        <div class="card overflow-auto">
          <div class="card-body">
            <h5 class="card-title">Gestion des présences (journalier)
              <span>
                | <a class="btnPage" data-bs-toggle="modal" data-bs-target="#bulkDailyPresenceModal">
                    <i class="bi bi-list-check"></i> Encodage journalier (liste)
                  </a>
              </span>
            </h5>

            <form method="GET" action="" class="mb-3">
              <div class="input-group">
                <input type="hidden" name="view" value="grh/agent.pres.add">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                <button type="submit" class="btn btn-primary">Rechercher</button>
              </div>
            </form>

            <!-- Filtres Structure / Service pour la liste principale -->
            <div class="row g-2 align-items-end mb-3">
              <div class="col-md-6">
                <label class="form-label">Structure</label>
                <select id="listStructureFilter" class="form-select">
                  <option value="">Toutes</option>
                  <?php 
                    $structureModel2 = new Structure();
                    $structures2 = $structureModel2->getStructures();
                    foreach ($structures2 as $st) {
                      echo "<option value='".(int)$st['idStructure']."'>".htmlspecialchars($st['designation'])."</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Service</label>
                <select id="listServiceFilter" class="form-select">
                  <option value="">Tous</option>
                </select>
              </div>
            </div>

            <table id="agentsTable" class="table table-striped table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nom</th>
                  <th>Sexe</th>
                  <th>Campus</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php 
                $userId = $_SESSION['id'];
                $i = 1; $hasResults = false;
                foreach ($agents as $agent) {
                  $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                  if ($ver1->fetch()) {
                    $hasResults = true;
                    ?>
                    <tr class="agent-row" data-structure-id="<?= (int)$agent['idStructure'] ?>" data-service-id="<?= isset($agent['idService']) ? (int)$agent['idService'] : 0 ?>">
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($agent['noms']) ?></td>
                      <td><?= htmlspecialchars($agent['sexe']) ?></td>
                      <td><?= htmlspecialchars($agent['designationStructure']) ?></td>
                      <td>
                        <button type="button" class="btn btn-info btn-sm add-daily-presence-btn" data-agent-id="<?= $agent['idAgent'] ?>" data-bs-toggle="modal" data-bs-target="#addDailyPresenceModal"><i class="bi bi-calendar-plus"></i></button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#presences<?= $agent['idAgent'] ?>">
                          <i class="bi bi-eye-fill"></i>
                        </button>
                      </td>
                    </tr>
                    <tr class="collapse" id="presences<?= $agent['idAgent'] ?>">
                      <td colspan="5">
                        <table class="table table-sm">
                          <thead>
                            <tr>
                              <th>Date</th>
                              <th>Arrivée</th>
                              <th>Sortie</th>
                              <th>Durée</th>
                              <th>Commentaire</th>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                          <?php 
                            $daily = $agentModel->getDailyPresencesByAgent($agent['idAgent']);
                            foreach ($daily as $d) {
                              $date = htmlspecialchars($d['date_presence']);
                              $arr = $d['heure_arrivee'] ? date('H:i', strtotime($d['heure_arrivee'])) : '-';
                              $dep = $d['heure_depart'] ? date('H:i', strtotime($d['heure_depart'])) : '-';
                              $duree = '-';
                              if ($d['heure_arrivee'] && $d['heure_depart']) {
                                $diffSec = strtotime($d['heure_depart']) - strtotime($d['heure_arrivee']);
                                if ($diffSec > 0) { $duree = gmdate('H\h i\m', $diffSec); }
                              }
                              $comment = htmlspecialchars($d['commentaire'] ?? '');
                              ?>
                              <tr>
                                <td><?= $date ?></td>
                                <td><?= $arr ?></td>
                                <td><?= $dep ?></td>
                                <td><?= $duree ?></td>
                                <td><?= $comment ?></td>
                                <td>
                                  <button class="btn btn-warning btn-sm edit-daily-presence-btn"
                                          data-id="<?= $d['idpresence_agent_daily'] ?>"
                                          data-date="<?= $date ?>"
                                          data-arr="<?= $arr !== '-' ? $arr : '' ?>"
                                          data-dep="<?= $dep !== '-' ? $dep : '' ?>"
                                          data-comment="<?= $comment ?>"
                                          data-bs-toggle="modal" data-bs-target="#editDailyPresenceModal">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>
                                  <form action="controller/delete_presence_daily.php" method="POST" class="d-inline delete-daily-presence-form">
                                    <input type="hidden" name="idPresence" value="<?= $d['idpresence_agent_daily'] ?>">
                                    <button type="button" class="btn btn-danger btn-sm delete-daily-presence-btn">Supprimer</button>
                                  </form>
                                </td>
                              </tr>
                              <?php 
                            }
                          ?>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    <?php 
                  }
                }
                if (!$hasResults) {
                  echo '<tr><td colspan="5" class="text-center">Aucun agent trouvé.</td></tr>';
                }
              ?>
              </tbody>
            </table>

            <!-- Modal: Add Daily Presence -->
            <div class="modal fade" id="addDailyPresenceModal" tabindex="-1" aria-labelledby="addDailyPresenceModalLabel" aria-hidden="true" data-bs-backdrop="static">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="addDailyPresenceModalLabel">Ajouter présence (journalier)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <form id="addDailyPresenceForm" action="controller/create_presence_daily.php" method="POST" class="needs-validation" novalidate>
                      <input type="hidden" name="agentId" id="presenceAgentId">
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label for="datePresence" class="form-label">Date <span class="text-danger">*</span></label>
                          <input type="date" class="form-control" id="datePresence" name="datePresence" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                          <label for="heureArrivee" class="form-label">Heure d'arrivée</label>
                          <input type="time" class="form-control" id="heureArrivee" name="heureArrivee">
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label for="heureDepart" class="form-label">Heure de sortie</label>
                          <input type="time" class="form-control" id="heureDepart" name="heureDepart">
                        </div>
                        <div class="col-md-6">
                          <label for="commentaire" class="form-label">Commentaire</label>
                          <input type="text" class="form-control" id="commentaire" name="commentaire" placeholder="Justification si besoin">
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal: Edit Daily Presence -->
            <div class="modal fade" id="editDailyPresenceModal" tabindex="-1" aria-labelledby="editDailyPresenceModalLabel" aria-hidden="true" data-bs-backdrop="static">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="editDailyPresenceModalLabel">Modifier présence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <form id="editDailyPresenceForm" action="controller/update_presence_daily.php" method="POST">
                      <input type="hidden" name="idPresence" id="editDailyId">
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label class="form-label">Date</label>
                          <input type="date" class="form-control" id="editDailyDate" name="datePresence" readonly>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Heure d'arrivée</label>
                          <input type="time" class="form-control" id="editDailyArr" name="heureArrivee">
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label class="form-label">Heure de sortie</label>
                          <input type="time" class="form-control" id="editDailyDep" name="heureDepart">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label">Commentaire</label>
                          <input type="text" class="form-control" id="editDailyComment" name="commentaire">
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <script>
              document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.add-daily-presence-btn').forEach(btn => {
                  btn.addEventListener('click', function() {
                    const agentId = this.getAttribute('data-agent-id');
                    document.getElementById('presenceAgentId').value = agentId;
                    const dateField = document.getElementById('datePresence');
                    if (dateField) {
                      const d = new Date();
                      const pad = (n) => String(n).padStart(2,'0');
                      const local = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
                      dateField.value = local;
                    }
                  });
                });

                document.querySelectorAll('.edit-daily-presence-btn').forEach(btn => {
                  btn.addEventListener('click', function() {
                    document.getElementById('editDailyId').value = this.getAttribute('data-id');
                    document.getElementById('editDailyDate').value = this.getAttribute('data-date');
                    document.getElementById('editDailyArr').value = this.getAttribute('data-arr') || '';
                    document.getElementById('editDailyDep').value = this.getAttribute('data-dep') || '';
                    document.getElementById('editDailyComment').value = this.getAttribute('data-comment') || '';
                  });
                });

                document.querySelectorAll('.delete-daily-presence-btn').forEach(btn => {
                  btn.addEventListener('click', function() {
                    const form = this.closest('form');
                    Swal.fire({
                      title: 'Êtes-vous sûr ?',
                      text: "Cette action est irréversible !",
                      icon: 'warning',
                      showCancelButton: true,
                      confirmButtonColor: '#3085d6',
                      cancelButtonColor: '#d33',
                      confirmButtonText: 'Oui, supprimer!',
                      cancelButtonText: 'Annuler'
                    }).then((result) => {
                      if (result.isConfirmed) form.submit();
                    });
                  });
                });
              });
            </script>

            <!-- Modal: Bulk Daily Presence -->
            <div class="modal fade" id="bulkDailyPresenceModal" tabindex="-1" aria-labelledby="bulkDailyPresenceModalLabel" aria-hidden="true" data-bs-backdrop="static">
              <div class="modal-dialog modal-xl">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="bulkDailyPresenceModalLabel">Encodage journalier (liste)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <form id="bulkDailyPresenceForm" action="controller/bulk_create_presence_daily.php" method="POST">
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <label for="bulkDate" class="form-label">Date <span class="text-danger">*</span></label>
                          <input type="date" class="form-control" id="bulkDate" name="datePresence" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-8 d-flex align-items-end justify-content-end">
                          <div class="form-check me-3">
                            <input class="form-check-input" type="checkbox" id="bulkSelectAll">
                            <label class="form-check-label" for="bulkSelectAll">Sélectionner tout</label>
                          </div>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulkFillTimes">
                            <label class="form-check-label" for="bulkFillTimes">Appliquer les heures de la config</label>
                          </div>
                        </div>
                      </div>

                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label for="bulkStructure" class="form-label">Structure</label>
                          <select id="bulkStructure" class="form-select">
                            <option value="">Toutes</option>
                            <?php 
                              $structureModel = new Structure();
                              $structures = $structureModel->getStructures();
                              foreach ($structures as $st) {
                                echo "<option value='".(int)$st['idStructure']."'>".htmlspecialchars($st['designation'])."</option>";
                              }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label for="bulkService" class="form-label">Service</label>
                          <select id="bulkService" class="form-select">
                            <option value="">Tous</option>
                          </select>
                        </div>
                      </div>

                      <div class="table-responsive" style="max-height: 60vh; overflow:auto;">
                        <table class="table table-bordered table-sm align-middle">
                          <thead class="table-light">
                            <tr>
                              <th>Présent</th>
                              <th>Nom</th>
                              <th>Sexe</th>
                              <th>Campus</th>
                              <th>Arrivée</th>
                              <th>Sortie</th>
                              <th>Commentaire</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php 
                              $userId = $_SESSION['id'];
                              foreach ($agents as $agent) {
                                $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                if (!$ver1->fetch()) continue;
                                ?>
                                <tr data-structure-id="<?= (int)$agent['idStructure'] ?>" data-service-id="<?= isset($agent['idService']) ? (int)$agent['idService'] : 0 ?>">
                                  <td class="text-center">
                                    <input type="checkbox" class="form-check-input bulk-present" name="present[<?= (int)$agent['idAgent'] ?>]" value="1">
                                  </td>
                                  <td><?= htmlspecialchars($agent['noms']) ?></td>
                                  <td><?= htmlspecialchars($agent['sexe']) ?></td>
                                  <td><?= htmlspecialchars($agent['designationStructure']) ?></td>
                                  <td><input type="time" class="form-control form-control-sm bulk-arrivee" name="heureArrivee[<?= (int)$agent['idAgent'] ?>]"></td>
                                  <td><input type="time" class="form-control form-control-sm bulk-depart" name="heureDepart[<?= (int)$agent['idAgent'] ?>]"></td>
                                  <td><input type="text" class="form-control form-control-sm" name="commentaire[<?= (int)$agent['idAgent'] ?>]" placeholder="Optionnel"></td>
                                </tr>
                                <?php
                              }
                            ?>
                          </tbody>
                        </table>
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <script>
              document.addEventListener('DOMContentLoaded', function() {
                const bulkDate = document.getElementById('bulkDate');
                if (bulkDate) {
                  const d = new Date();
                  const pad = (n) => String(n).padStart(2,'0');
                  bulkDate.value = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
                }

                const selectAll = document.getElementById('bulkSelectAll');
                if (selectAll) {
                  selectAll.addEventListener('change', function() {
                    document.querySelectorAll('#bulkDailyPresenceModal .bulk-present').forEach(cb => {
                      const row = cb.closest('tr');
                      if (row && row.style.display !== 'none') {
                        cb.checked = selectAll.checked;
                      }
                    });
                  });
                }

                const fillTimes = document.getElementById('bulkFillTimes');
                if (fillTimes) {
                  fillTimes.addEventListener('change', function() {
                    if (!fillTimes.checked) return;
                    // Fetch config via embedded PHP values
                    <?php $cfgModel = new Agent(); $cfg = $cfgModel->getPresenceConfig(); ?>
                    const hdeb = '<?= isset($cfg['heure_debut']) ? substr($cfg['heure_debut'],0,5) : '08:00' ?>';
                    const hfin = '<?= isset($cfg['heure_fin']) ? substr($cfg['heure_fin'],0,5) : '17:00' ?>';
                    document.querySelectorAll('#bulkDailyPresenceModal .bulk-arrivee').forEach(i => { if (!i.value) i.value = hdeb; });
                    document.querySelectorAll('#bulkDailyPresenceModal .bulk-depart').forEach(i => { if (!i.value) i.value = hfin; });
                  });
                }

                // Filters
                const bulkStructure = document.getElementById('bulkStructure');
                const bulkService = document.getElementById('bulkService');
                function filterRows() {
                  const sid = bulkStructure ? bulkStructure.value : '';
                  const ser = bulkService ? bulkService.value : '';
                  document.querySelectorAll('#bulkDailyPresenceModal tbody tr').forEach(tr => {
                    let show = true;
                    if (sid && tr.getAttribute('data-structure-id') !== sid) show = false;
                    if (ser && tr.getAttribute('data-service-id') !== ser) show = false;
                    tr.style.display = show ? '' : 'none';
                  });
                }
                // Lors de l'ouverture du modal, assurer un état cohérent + (re)charger les services si besoin
                const bulkModal = document.getElementById('bulkDailyPresenceModal');
                if (bulkModal) {
                  bulkModal.addEventListener('shown.bs.modal', function() {
                    try {
                      // Déclencher le change pour charger les services
                      if (bulkStructure) {
                        bulkStructure.dispatchEvent(new Event('change'));
                      }
                    } catch (e) {
                      console.warn('bulk modal init error', e);
                    }
                  });
                }
                function handleStructureChange() {
                  const s = bulkStructure ? bulkStructure.value : '';
                  console.log('Structure change ->', s);
                  bulkService.innerHTML = '<option value="">Tous</option>';
                  if (s) {
                    fetch('controller/get_services_by_structure.php?structure=' + encodeURIComponent(s))
                      .then(r => r.json())
                      .then(items => {
                        console.log('Services chargés pour structure', s, items);
                        (items || []).forEach(it => {
                          const opt = document.createElement('option');
                          opt.value = it.idService;
                          opt.textContent = it.designationService;
                          bulkService.appendChild(opt);
                        });
                        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                          try {
                            jQuery(bulkService).select2('destroy');
                            jQuery(bulkService).select2({
                              width: '100%',
                              placeholder: 'Tous',
                              allowClear: true,
                              dropdownParent: jQuery('#bulkDailyPresenceModal .modal-body')
                            });
                          } catch (e) {
                            try { jQuery(bulkService).trigger('change.select2'); } catch (e2) {}
                          }
                        }
                        filterRows();
                      })
                      .catch((err) => { console.error('Erreur chargement services', err); filterRows(); });
                  } else {
                    filterRows();
                  }
                }
                if (bulkStructure) {
                  // Plain listener
                  bulkStructure.addEventListener('change', handleStructureChange);
                  // jQuery delegated + select2 event if present
                  if (window.jQuery) {
                    jQuery(document).on('change', '#bulkStructure', handleStructureChange);
                    jQuery(document).on('select2:select', '#bulkStructure', handleStructureChange);
                  }
                }
                if (bulkService) {
                  // Native change
                  bulkService.addEventListener('change', filterRows);
                  // jQuery + Select2 change events
                  if (window.jQuery) {
                    jQuery(document).on('change', '#bulkService', filterRows);
                    jQuery(document).on('select2:select', '#bulkService', filterRows);
                    jQuery(document).on('select2:clear', '#bulkService', filterRows);
                  }
                }
                // Initial filter
                filterRows();

                // ===== Filtres liste principale =====
                const listStructure = document.getElementById('listStructureFilter');
                const listService = document.getElementById('listServiceFilter');
                function filterMain() {
                  const sid = listStructure ? listStructure.value : '';
                  const ser = listService ? listService.value : '';
                  document.querySelectorAll('#agentsTable tbody tr.agent-row').forEach(tr => {
                    let show = true;
                    if (sid && tr.getAttribute('data-structure-id') !== sid) show = false;
                    if (ser && tr.getAttribute('data-service-id') !== ser) show = false;
                    tr.style.display = show ? '' : 'none';
                  });
                }
                function reloadListServices() {
                  const sid = listStructure ? listStructure.value : '';
                  listService.innerHTML = '<option value="">Tous</option>';
                  if (!sid) { filterMain(); return; }
                  fetch('controller/get_services_by_structure.php?structure=' + encodeURIComponent(sid))
                    .then(r => r.json())
                    .then(items => {
                      (items || []).forEach(it => {
                        const opt = document.createElement('option');
                        opt.value = it.idService;
                        opt.textContent = it.designationService;
                        listService.appendChild(opt);
                      });
                      if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                        try {
                          jQuery(listService).select2('destroy');
                          jQuery(listService).select2({ width:'100%', allowClear:true, placeholder:'Tous' });
                        } catch(e) { try { jQuery(listService).trigger('change.select2'); } catch(e2){} }
                      }
                      filterMain();
                    })
                    .catch(() => filterMain());
                }
                if (listStructure) {
                  listStructure.addEventListener('change', reloadListServices);
                  if (window.jQuery) {
                    jQuery(document).on('change', '#listStructureFilter', reloadListServices);
                    jQuery(document).on('select2:select', '#listStructureFilter', reloadListServices);
                  }
                }
                if (listService) {
                  listService.addEventListener('change', filterMain);
                }
                // Init select2 if present
                if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
                  try {
                    jQuery('#listStructureFilter').select2({ width:'100%', allowClear:true, placeholder:'Toutes' });
                    jQuery('#listServiceFilter').select2({ width:'100%', allowClear:true, placeholder:'Tous' });
                  } catch(e){}
                }
                // Lancement initial filtre liste principale
                filterMain();
              });
            </script>

          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include "./views/include/footer.php"; ?>
