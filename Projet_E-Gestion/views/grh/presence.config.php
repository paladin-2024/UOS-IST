<?php include "./views/include/header.php"; 
$agentModel = new Agent();
$cfg = $agentModel->getPresenceConfig();
$jours = isset($cfg['jours_travail']) ? array_map('intval', explode(',', $cfg['jours_travail'])) : [1,2,3,4,5];
?>

<main id="main" class="main">
  <div class="pagetitle">
    <h1>Configuration des horaires</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Accueil</a></li>
        <li class="breadcrumb-item active">Présences</li>
      </ol>
    </nav>
  </div>

  <section class="section dashboard">
  <!-- Barre d'export rapide (toujours visible en haut) -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label mb-0 small">Début</label>
          <input type="date" id="pcq_start" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label mb-0 small">Fin</label>
          <input type="date" id="pcq_end" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label mb-0 small">Structure</label>
          <select id="pcq_structure" class="form-select form-select-sm">
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
        <div class="col-6 col-md-3">
          <label class="form-label mb-0 small">Service</label>
          <select id="pcq_service" class="form-select form-select-sm">
            <option value="">Tous</option>
          </select>
        </div>
        <div class="col-12 col-md-2 text-end">
          <button type="button" id="pcq_pdf" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
          <button type="button" id="pcq_xlsx" class="btn btn-success btn-sm ms-1"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        </div>
      </div>
    </div>
  </div>
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-1">Horaires &amp; Jours de travail</h5>
            <p class="text-muted small mb-4">Définissez les heures, pauses, tolérance et jours ouvrables appliqués au pointage.</p>
            <form method="POST" action="controller/save_presence_config.php" class="needs-validation" novalidate>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Heure début</label>
                  <input type="time" class="form-control" name="heure_debut" value="<?= isset($cfg['heure_debut']) ? substr($cfg['heure_debut'],0,5) : '08:00' ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Heure fin</label>
                  <input type="time" class="form-control" name="heure_fin" value="<?= isset($cfg['heure_fin']) ? substr($cfg['heure_fin'],0,5) : '17:00' ?>" required>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Pause début</label>
                  <input type="time" class="form-control" name="pause_debut" value="<?= isset($cfg['pause_debut']) ? substr($cfg['pause_debut'],0,5) : '' ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Pause fin</label>
                  <input type="time" class="form-control" name="pause_fin" value="<?= isset($cfg['pause_fin']) ? substr($cfg['pause_fin'],0,5) : '' ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Tolérance (minutes)</label>
                <input type="number" class="form-control" name="tolerance" min="0" max="120" value="<?= isset($cfg['tolerance_minutes']) ? (int)$cfg['tolerance_minutes'] : 15 ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label d-block">Jours de travail</label>
                <?php 
                  $labels = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
                  foreach ($labels as $num=>$lib) {
                    $checked = in_array($num, $jours) ? 'checked' : '';
                    echo "<div class='form-check form-check-inline'>
                            <input class='form-check-input' type='checkbox' id='j{$num}' name='jours[]' value='{$num}' {$checked}>
                            <label class='form-check-label' for='j{$num}'>{$lib}</label>
                          </div>";
                  }
                ?>
              </div>
              <div class="text-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
      
      
  </section>
</main>

<?php include "./views/include/footer.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const start = document.getElementById('pc_start');
  const end = document.getElementById('pc_end');
  const structure = document.getElementById('pc_structure');
  const service = document.getElementById('pc_service');
  const exportPdf = document.getElementById('pc_export_pdf');
  const exportXlsx = document.getElementById('pc_export_xlsx');

  // Préremplir période: ce mois
  const d = new Date();
  const pad = n => String(n).padStart(2,'0');
  const first = new Date(d.getFullYear(), d.getMonth(), 1);
  const last = new Date(d.getFullYear(), d.getMonth()+1, 0);
  if (start && !start.value) start.value = first.getFullYear()+'-'+pad(first.getMonth()+1)+'-'+pad(first.getDate());
  if (end && !end.value) end.value = last.getFullYear()+'-'+pad(last.getMonth()+1)+'-'+pad(last.getDate());

  // Charger services selon structure
  function reloadServices() {
    if (!service) return;
    service.innerHTML = '<option value="">Tous</option>';
    if (!structure || !structure.value) return;
    fetch('controller/get_services_by_structure.php?structure=' + encodeURIComponent(structure.value))
      .then(r=>r.json())
      .then(items => {
        (items||[]).forEach(it => {
          const opt = document.createElement('option');
          opt.value = it.idService;
          opt.textContent = it.designationService;
          service.appendChild(opt);
        });
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
          try { jQuery(service).select2('destroy'); jQuery(service).select2({ width:'100%', allowClear:true }); } catch(e){}
        }
      })
      .catch(()=>{});
  }
  if (structure) {
    structure.addEventListener('change', reloadServices);
    if (window.jQuery) {
      jQuery(document).on('select2:select', '#pc_structure', reloadServices);
      jQuery(document).on('change', '#pc_structure', reloadServices);
    }
  }

  function buildQuery() {
    const params = new URLSearchParams();
    params.set('start', start.value || '');
    params.set('end', end.value || '');
    if (structure && structure.value) params.set('structureId', structure.value);
    if (service && service.value) params.set('serviceId', service.value);
    return params.toString();
  }

  if (exportPdf) {
    exportPdf.addEventListener('click', function(){
      const q = buildQuery();
      window.open('controller/export_presence_pdf.php?'+q, '_blank');
    });
  }
  if (exportXlsx) {
    exportXlsx.addEventListener('click', function(){
      const q = buildQuery();
      window.open('controller/export_presence_excel.php?'+q, '_blank');
    });
  }
});
</script>


<script>
(function(){
  const qStart = document.getElementById('pcq_start');
  const qEnd = document.getElementById('pcq_end');
  const qStruct = document.getElementById('pcq_structure');
  const qServ = document.getElementById('pcq_service');
  const qPdf = document.getElementById('pcq_pdf');
  const qXlsx = document.getElementById('pcq_xlsx');
  if (!qStart || !qEnd) return;
  const d=new Date(), pad=n=>String(n).padStart(2,'0');
  const first=new Date(d.getFullYear(),d.getMonth(),1);
  const last=new Date(d.getFullYear(),d.getMonth()+1,0);
  if(!qStart.value) qStart.value = first.getFullYear()+"-"+pad(first.getMonth()+1)+"-"+pad(first.getDate());
  if(!qEnd.value) qEnd.value = last.getFullYear()+"-"+pad(last.getMonth()+1)+"-"+pad(last.getDate());
  function reloadQuickServices(){
    if(!qServ) return; qServ.innerHTML='<option value="">Tous</option>';
    if(!qStruct || !qStruct.value) return;
    fetch('controller/get_services_by_structure.php?structure='+encodeURIComponent(qStruct.value))
    .then(r=>r.json()).then(items=>{
      (items||[]).forEach(it=>{const o=document.createElement('option');o.value=it.idService;o.textContent=it.designationService;qServ.appendChild(o);});
      if(window.jQuery && jQuery.fn && jQuery.fn.select2){try{jQuery(qServ).select2('destroy'); jQuery(qServ).select2({width:'100%',allowClear:true});}catch(e){}}
    }).catch(()=>{});
  }
  if(qStruct){ qStruct.addEventListener('change', reloadQuickServices); if(window.jQuery){ jQuery(document).on('change','#pcq_structure',reloadQuickServices); jQuery(document).on('select2:select','#pcq_structure',reloadQuickServices);} }
  function buildQuickQuery(){ const p=new URLSearchParams(); p.set('start',qStart.value||''); p.set('end',qEnd.value||''); if(qStruct&&qStruct.value) p.set('structureId',qStruct.value); if(qServ&&qServ.value) p.set('serviceId',qServ.value); return p.toString(); }
  if(qPdf) qPdf.addEventListener('click',()=>{ window.open('controller/export_presence_pdf.php?'+buildQuickQuery(),'_blank'); });
  if(qXlsx) qXlsx.addEventListener('click',()=>{ window.open('controller/export_presence_excel.php?'+buildQuickQuery(),'_blank'); });
})();
</script>
