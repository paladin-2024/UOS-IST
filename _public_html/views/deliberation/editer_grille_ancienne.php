<?php
include "./views/include/header.php";

$grilleAncienne = new GrilleAncienne();
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

if ($importId <= 0) {
    header('Location: deliberation/grilles_anciennes');
    exit;
}

// Récupérer l'import
$importInfo = $grilleAncienne->getImportById($importId);
if (!$importInfo) {
    header('Location: deliberation/grilles_anciennes');
    exit;
}

// Récupérer les UEs de cet import
$ues = $grilleAncienne->getUEsByImport($importId);

// Récupérer les ECUEs groupées par UE
$ecuesByUE = [];
$allEcues = $grilleAncienne->getECUEsByImport($importId);
foreach ($allEcues as $ecue) {
    $ecuesByUE[$ecue['ue_id']][] = $ecue;
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Éditer la Grille Ancienne</h1>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="deliberation/grilles_anciennes">Grilles Anciennes</a></li>
                        <li class="breadcrumb-item active">Édition</li>
                    </ol>
                </nav>
            </div>
            <a href="deliberation/grilles_anciennes" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Retour
            </a>
        </div>
    </div>

    <section class="section">
        <div class="row">
            <!-- Informations de base -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>
                            Informations de la Grille
                        </h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Année Académique</label>
                                <input type="text" class="form-control" id="annee_academique" value="<?= htmlspecialchars($importInfo['annee_academique']) ?>" disabled style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Session</label>
                                <input type="text" class="form-control" id="session" value="<?= htmlspecialchars(ucfirst($importInfo['session'])) ?>" disabled style="background-color: #e9ecef;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Promotion</label>
                                <input type="text" class="form-control" id="promotion" name="promotion" value="<?= htmlspecialchars($importInfo['promotion']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Semestre</label>
                                <input type="text" class="form-control" id="semestre" name="semestre" value="<?= htmlspecialchars($importInfo['semestre']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Édition des UEs et ECUEs -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-pencil-square me-2"></i>
                            Modifier les UEs et ECUEs
                        </h5>

                        <form id="formEditionGrille">
                            <input type="hidden" name="import_id" value="<?= $importId ?>">
                            
                            <div class="accordion" id="accordionUEs">
                                <?php foreach ($ues as $index => $ue): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= $ue['id'] ?>">
                                            <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?= $ue['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                                                    aria-controls="collapse<?= $ue['id'] ?>">
                                                <strong><?= htmlspecialchars($ue['code_ue']) ?></strong>
                                                <span class="ms-2 text-muted"><?= htmlspecialchars($ue['designation_ue']) ?></span>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $ue['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                                             aria-labelledby="heading<?= $ue['id'] ?>" data-bs-parent="#accordionUEs">
                                            <div class="accordion-body">
                                                <!-- Édition UE -->
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Code UE</label>
                                                        <input type="text" class="form-control ue-code" 
                                                               data-ue-id="<?= $ue['id'] ?>" 
                                                               value="<?= htmlspecialchars($ue['code_ue']) ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Désignation UE</label>
                                                        <input type="text" class="form-control ue-designation" 
                                                               data-ue-id="<?= $ue['id'] ?>" 
                                                               value="<?= htmlspecialchars($ue['designation_ue']) ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Semestre</label>
                                                        <input type="text" class="form-control ue-semestre" 
                                                               data-ue-id="<?= $ue['id'] ?>" 
                                                               value="<?= htmlspecialchars($ue['semestre'] ?? '') ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Crédits</label>
                                                        <input type="number" class="form-control ue-credits" 
                                                               data-ue-id="<?= $ue['id'] ?>" 
                                                               value="<?= htmlspecialchars($ue['credits'] ?? '') ?>" 
                                                               step="0.5">
                                                    </div>
                                                </div>

                                                <hr>

                                                <!-- Table ECUEs -->
                                                <h6 class="mb-3">ECUEs</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Code</th>
                                                                <th>Désignation</th>
                                                                <th>Coefficient</th>
                                                                <th>Affecter à UE</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (isset($ecuesByUE[$ue['id']])): ?>
                                                                <?php foreach ($ecuesByUE[$ue['id']] as $ecue): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <input type="text" class="form-control form-control-sm ecue-code" 
                                                                                   data-ecueid="<?= $ecue['id'] ?>" 
                                                                                   value="<?= htmlspecialchars($ecue['code_ecue']) ?>">
                                                                        </td>
                                                                        <td>
                                                                            <input type="text" class="form-control form-control-sm ecue-designation" 
                                                                                   data-ecueid="<?= $ecue['id'] ?>" 
                                                                                   value="<?= htmlspecialchars($ecue['designation_ecue']) ?>">
                                                                        </td>
                                                                        <td style="width: 100px;">
                                                                            <input type="number" class="form-control form-control-sm ecue-coefficient" 
                                                                                   data-ecueid="<?= $ecue['id'] ?>" 
                                                                                   value="<?= htmlspecialchars($ecue['coefficient']) ?>" 
                                                                                   step="0.5">
                                                                        </td>
                                                                        <td style="width: 150px;">
                                                                            <select class="form-select form-select-sm ecue-ue-id" 
                                                                                    data-ecueid="<?= $ecue['id'] ?>">
                                                                                <?php foreach ($ues as $selectUe): ?>
                                                                                    <option value="<?= $selectUe['id'] ?>" 
                                                                                            <?= $selectUe['id'] == $ue['id'] ? 'selected' : '' ?>>
                                                                                        <?= htmlspecialchars($selectUe['code_ue'] . ' - ' . $selectUe['designation_ue']) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                                </button>
                                <a href="deliberation/grilles_anciennes" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.getElementById('formEditionGrille').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Préparer les données
    const formData = new FormData();
    formData.append('import_id', <?= $importId ?>);
    formData.append('promotion', document.getElementById('promotion').value);
    formData.append('semestre', document.getElementById('semestre').value);
    
    // Collecter les modifications des UEs
    const uesModifications = {};
    document.querySelectorAll('.ue-code').forEach(input => {
        const ueId = input.dataset.ueId;
        if (!uesModifications[ueId]) {
            uesModifications[ueId] = {};
        }
        uesModifications[ueId]['code_ue'] = input.value;
    });
    
    document.querySelectorAll('.ue-designation').forEach(input => {
        const ueId = input.dataset.ueId;
        if (!uesModifications[ueId]) {
            uesModifications[ueId] = {};
        }
        uesModifications[ueId]['designation_ue'] = input.value;
    });

    document.querySelectorAll('.ue-semestre').forEach(input => {
        const ueId = input.dataset.ueId;
        if (!uesModifications[ueId]) {
            uesModifications[ueId] = {};
        }
        uesModifications[ueId]['semestre'] = input.value;
    });

    document.querySelectorAll('.ue-credits').forEach(input => {
        const ueId = input.dataset.ueId;
        if (!uesModifications[ueId]) {
            uesModifications[ueId] = {};
        }
        uesModifications[ueId]['credits'] = input.value;
    });
    
    // Collecter les modifications des ECUEs
    const ecuésModifications = {};
    document.querySelectorAll('.ecue-code').forEach(input => {
        const ecuéId = input.dataset.ecueid;
        if (!ecuésModifications[ecuéId]) {
            ecuésModifications[ecuéId] = {};
        }
        ecuésModifications[ecuéId]['code_ecue'] = input.value;
    });
    
    document.querySelectorAll('.ecue-designation').forEach(input => {
        const ecuéId = input.dataset.ecueid;
        if (!ecuésModifications[ecuéId]) {
            ecuésModifications[ecuéId] = {};
        }
        ecuésModifications[ecuéId]['designation_ecue'] = input.value;
    });
    
    document.querySelectorAll('.ecue-coefficient').forEach(input => {
        const ecuéId = input.dataset.ecueid;
        if (!ecuésModifications[ecuéId]) {
            ecuésModifications[ecuéId] = {};
        }
        ecuésModifications[ecuéId]['coefficient'] = input.value;
    });
    
    document.querySelectorAll('.ecue-ue-id').forEach(select => {
        const ecuéId = select.dataset.ecueid;
        if (!ecuésModifications[ecuéId]) {
            ecuésModifications[ecuéId] = {};
        }
        ecuésModifications[ecuéId]['ue_id'] = select.value;
    });
    
    formData.append('ues', JSON.stringify(uesModifications));
    formData.append('ecues', JSON.stringify(ecuésModifications));
    
    try {
        const response = await fetch('controller/update_grille_ancienne.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'La grille a été mise à jour avec succès.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'deliberation/grilles_anciennes';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur s\'est produite.'
            });
        }
    } catch (error) {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur s\'est produite lors de la mise à jour.'
        });
    }
});
</script>

<?php include "./views/include/footer_file.php"; ?>
