<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'ID du palmarès
$idPalmares = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPalmares <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Palmarès non spécifié.'
        }).then(() => {
            window.location.href = '?view=academique/palmares';
        });
    </script>";
    exit;
}

// Récupérer les données du palmarès
$query = "SELECT * FROM palmares_archive WHERE id_palmares = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$idPalmares]);
$palmares = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$palmares) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Palmarès non trouvé.'
        }).then(() => {
            window.location.href = '?view=academique/palmares';
        });
    </script>";
    exit;
}

// Récupérer les étudiants associés à ce palmarès
$query = "SELECT * FROM palmares_etudiant WHERE id_palmares = ? ORDER BY rang";
$stmt = $pdo->prepare($query);
$stmt->execute([$idPalmares]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>MODIFICATION DE PALMARÈS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=academique/palmares">Palmarès</a></li>
                <li class="breadcrumb-item active">Modifier un palmarès</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Modifier le palmarès</h5>
                        
                        <!-- Formulaire de modification de palmarès -->
                        <form id="palmaresForm" method="POST" action="controller/update_palmares.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="id_palmares" value="<?= $idPalmares ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="designation" class="form-label">Désignation/Titre du palmarès <span class="text-danger">*</span></label>
                                    <input type="text" name="designation" id="designation" class="form-control" value="<?= htmlspecialchars($palmares['designation']) ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer une désignation.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="annee_academique" class="form-label">Année académique <span class="text-danger">*</span></label>
                                    <input type="text" name="annee_academique" id="annee_academique" class="form-control" value="<?= htmlspecialchars($palmares['annee_academique']) ?>" required>
                                    <input type="hidden" name="annee_acad_idannee_acad" value="<?= $palmares['annee_acad_idannee_acad'] ?>">
                                    <div class="invalid-feedback">Veuillez entrer une année académique.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                    <input type="text" name="section" id="section" class="form-control" value="<?= htmlspecialchars(explode(' - ', $palmares['promotion'])[0] ?? '') ?>" required>
                                    <div class="invalid-feedback">Veuillez entrer une section.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="promotion" class="form-label">Promotion <span class="text-danger">*</span></label>
                                    <input type="text" name="promotion" id="promotion" class="form-control" value="<?= htmlspecialchars($palmares['promotion']) ?>" required>
                                    <input type="hidden" name="promotion_idpromotion" value="<?= $palmares['promotion_idpromotion'] ?>">
                                    <div class="invalid-feedback">Veuillez entrer une promotion.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
                                    <input type="text" name="session" id="session" class="form-control" value="<?= htmlspecialchars($palmares['session']) ?>" required>
                                    <input type="hidden" name="session_idsession" value="<?= $palmares['session_idsession'] ?>">
                                    <div class="invalid-feedback">Veuillez entrer une session.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="fichier_scanne" class="form-label">Fichier scanné (PDF)</label>
                                    <input type="file" name="fichier_scanne" id="fichier_scanne" class="form-control" accept=".pdf">
                                    <?php if (!empty($palmares['fichier_scanne'])): ?>
                                        <div class="form-text">
                                            Fichier actuel: <a href="<?= $palmares['fichier_scanne'] ?>" target="_blank"><?= basename($palmares['fichier_scanne']) ?></a>
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="supprimer_fichier" id="supprimer_fichier">
                                                <label class="form-check-label" for="supprimer_fichier">
                                                    Supprimer le fichier existant
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-text">Facultatif. Téléchargez une version scannée du palmarès au format PDF.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($palmares['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Résultats des étudiants</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        
                                        <button type="button" class="btn btn-sm btn-success" id="importExcelBtn">
                                            <i class="bi bi-file-excel"></i> Importer depuis Excel
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover" id="studentResultsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="3%">#</th>
                                                    <th width="25%">Nom complet</th>
                                                    <th width="15%">Matricule (opt.)</th>
                                                    <th width="10%">Pourcentage</th>
                                                    <th width="15%">Mention</th>
                                                    <th width="8%">Rang</th>
                                                    <th width="10%">Crédits obtenus</th>
                                                    <th width="10%">Crédits totaux</th>
                                                    <th width="4%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="studentResultsBody">
                                                <!-- Les lignes seront ajoutées dynamiquement -->
                                            </tbody>
                                        </table>
                                        <button type="button" class="btn btn-sm btn-primary" id="addStudentRow">
                                            <i class="bi bi-plus-circle"></i> Ajouter un étudiant
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='?view=academique/palmares'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour importation Excel (identique à ajouter_palmares.php) -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer les résultats depuis Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx, .xls, .csv" required>
                        <div class="form-text">Formats acceptés: .xlsx, .xls, .csv</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Numéro de ligne de début</label>
                                <input type="number" class="form-control" id="startRow" name="startRow" value="2" min="1">
                                <div class="form-text">La première ligne contient généralement les en-têtes</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Mapping des colonnes</h6>
                        <p class="mb-0">Indiquez les colonnes correspondant à chaque information (ex: A, B, C...)</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="colNom" name="colNom" value="A">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Matricule</label>
                                <input type="text" class="form-control" id="colMatricule" name="colMatricule" value="B">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Pourcentage</label>
                                <input type="text" class="form-control" id="colPourcentage" name="colPourcentage" value="C">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Mention</label>
                                <input type="text" class="form-control" id="colMention" name="colMention" value="D">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Rang</label>
                                <input type="text" class="form-control" id="colRang" name="colRang" value="E">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Crédits obtenus</label>
                                <input type="text" class="form-control" id="colCreditsObtenus" name="colCreditsObtenus" value="F">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Crédits totaux</label>
                                <input type="text" class="form-control" id="colCreditsTotaux" name="colCreditsTotaux" value="G">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="processExcelBtn">
                    <i class="bi bi-check-circle"></i> Importer les données
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du formulaire
    const form = document.getElementById('palmaresForm');
    
    // Validation du formulaire Bootstrap
    (function() {
        'use strict';
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Vérifie si au moins un étudiant a été ajouté
            const rows = document.querySelectorAll('#studentResultsBody tr');
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
        }, false);
    })();
    
    // Gestion des lignes d'étudiants
    const studentResultsBody = document.getElementById('studentResultsBody');
    const addStudentRow = document.getElementById('addStudentRow');
    let rowCounter = 0;
    
    // Fonction pour ajouter une ligne d'étudiant
    function addNewStudentRow(data = {}) {
        rowCounter++;
        
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td class="text-center">${rowCounter}</td>
            <td>
                <input type="text" name="etudiants[${rowCounter}][nom_complet]" class="form-control form-control-sm" 
                    value="${data.nom_complet || ''}" required>
                <input type="hidden" name="etudiants[${rowCounter}][id_palmares_etudiant]" value="${data.id_palmares_etudiant || ''}">
            </td>
            <td>
                <input type="text" name="etudiants[${rowCounter}][matricule]" class="form-control form-control-sm"
                    value="${data.matricule || ''}">
            </td>
            <td>
                <input type="number" name="etudiants[${rowCounter}][pourcentage]" class="form-control form-control-sm percentage-input"
                    min="0" max="100" step="0.01" value="${data.pourcentage || ''}" required>
            </td>
            <td>
                <select name="etudiants[${rowCounter}][mention]" class="form-select form-select-sm mention-select">
                    <option value="">Automatique</option>
                    <option value="Passable" ${data.mention === 'Passable' ? 'selected' : ''}>Passable</option>
                    <option value="Assez Bien" ${data.mention === 'Assez Bien' ? 'selected' : ''}>Assez Bien</option>
                    <option value="Bien" ${data.mention === 'Bien' ? 'selected' : ''}>Bien</option>
                    <option value="Très Bien" ${data.mention === 'Très Bien' ? 'selected' : ''}>Très Bien</option>
                    <option value="Excellent" ${data.mention === 'Excellent' ? 'selected' : ''}>Excellent</option>
                    <option value="Satisfaction" ${data.mention === 'Satisfaction' ? 'selected' : ''}>Satisfaction</option>
                    <option value="Distinction" ${data.mention === 'Distinction' ? 'selected' : ''}>Distinction</option>
                    <option value="Grande Distinction" ${data.mention === 'Grande Distinction' ? 'selected' : ''}>Grande Distinction</option>
                    <option value="La Plus Grande Distinction" ${data.mention === 'La Plus Grande Distinction' ? 'selected' : ''}>La Plus Grande Distinction</option>
                </select>
            </td>
            <td>
                <input type="number" name="etudiants[${rowCounter}][rang]" class="form-control form-control-sm"
                    min="1" value="${data.rang || rowCounter}" required>
            </td>
            <td>
                <input type="number" name="etudiants[${rowCounter}][credit_obtenu]" class="form-control form-control-sm"
                    min="0" value="${data.credit_obtenu || ''}">
            </td>
            <td>
                <input type="number" name="etudiants[${rowCounter}][credit_total]" class="form-control form-control-sm"
                    min="0" value="${data.credit_total || ''}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm delete-row" title="Supprimer">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        
        // Ajouter les écouteurs d'événements pour cette ligne
        const percentageInput = newRow.querySelector('.percentage-input');
        const mentionSelect = newRow.querySelector('.mention-select');
        const deleteButton = newRow.querySelector('.delete-row');
        
        // Associer automatiquement la mention en fonction du pourcentage
        percentageInput.addEventListener('change', function() {
            if (mentionSelect.value === '') {
                const percentage = parseFloat(this.value);
                mentionSelect.value = getMentionFromPercentage(percentage);
            }
        });
        
        // Supprimer la ligne
        deleteButton.addEventListener('click', function() {
            newRow.remove();
            renumberRows();
        });
        
        studentResultsBody.appendChild(newRow);
    }
    
    // Renuméroter les lignes après suppression
    function renumberRows() {
        const rows = studentResultsBody.querySelectorAll('tr');
        rowCounter = rows.length;
        
        rows.forEach((row, index) => {
            const rowNum = index + 1;
            row.cells[0].textContent = rowNum;
            
            // Mettre à jour les noms des champs pour maintenir l'ordre des indices
            row.querySelectorAll('input, select').forEach(input => {
                const name = input.getAttribute('name');
                if (name && name.includes('[')) {
                    const newName = name.replace(/\[\d+\]/, `[${rowNum}]`);
                    input.setAttribute('name', newName);
                }
            });
        });
    }
    
    // Déterminer la mention en fonction du pourcentage
    function getMentionFromPercentage(percentage) {
        if (isNaN(percentage)) return '';
        
        if (percentage >= 90) return 'La Plus Grande Distinction';
        if (percentage >= 85) return 'Grande Distinction';
        if (percentage >= 80) return 'Grande Distinction';
        if (percentage >= 75) return 'Distinction';
        if (percentage >= 70) return 'Distinction';
        if (percentage >= 65) return 'Satisfaction';
        if (percentage >= 60) return 'Satisfaction';
        if (percentage >= 50) return 'Satisfaction';
        return '';
    }
    
    // Ajouter une ligne d'étudiant
    addStudentRow.addEventListener('click', function() {
        addNewStudentRow();
    });
    
    // Importer depuis Excel
    const importExcelBtn = document.getElementById('importExcelBtn');
    const importExcelModal = new bootstrap.Modal(document.getElementById('importExcelModal'));
    const processExcelBtn = document.getElementById('processExcelBtn');
    
    importExcelBtn.addEventListener('click', function() {
        importExcelModal.show();
    });
    
    processExcelBtn.addEventListener('click', function() {
        const excelFile = document.getElementById('excelFile').files[0];
        if (!excelFile) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez sélectionner un fichier Excel.'
            });
            return;
        }
        
        // Récupérer les paramètres
        const formData = new FormData();
        formData.append('excelFile', excelFile);
        formData.append('startRow', document.getElementById('startRow').value);
        formData.append('colNom', document.getElementById('colNom').value);
        formData.append('colMatricule', document.getElementById('colMatricule').value);
        formData.append('colPourcentage', document.getElementById('colPourcentage').value);
        formData.append('colMention', document.getElementById('colMention').value);
        formData.append('colRang', document.getElementById('colRang').value);
        formData.append('colCreditsObtenus', document.getElementById('colCreditsObtenus').value);
        formData.append('colCreditsTotaux', document.getElementById('colCreditsTotaux').value);
        
        // Envoyer au serveur pour traitement
        fetch('controller/process_excel_palmares.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Demander confirmation avant de remplacer les données existantes
            Swal.fire({
                title: 'Comment souhaitez-vous importer?',
                text: `${data.etudiants.length} étudiants trouvés dans le fichier.`,
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Remplacer tout',
                denyButtonText: 'Ajouter à la liste',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Remplacer les données existantes
                    studentResultsBody.innerHTML = '';
                    rowCounter = 0;
                    
                    data.etudiants.forEach(etudiant => {
                        addNewStudentRow({
                            nom_complet: etudiant.nom_complet,
                            matricule: etudiant.matricule,
                            pourcentage: etudiant.pourcentage,
                            mention: etudiant.mention,
                            rang: etudiant.rang,
                            credit_obtenu: etudiant.credit_obtenu,
                            credit_total: etudiant.credit_total
                        });
                    });
                    
                    Swal.fire('Remplacé!', 'Les données ont été remplacées.', 'success');
                } else if (result.isDenied) {
                    // Ajouter à la liste existante
                    data.etudiants.forEach(etudiant => {
                        addNewStudentRow({
                            nom_complet: etudiant.nom_complet,
                            matricule: etudiant.matricule,
                            pourcentage: etudiant.pourcentage,
                            mention: etudiant.mention,
                            rang: etudiant.rang,
                            credit_obtenu: etudiant.credit_obtenu,
                            credit_total: etudiant.credit_total
                        });
                    });
                    
                    Swal.fire('Ajouté!', 'Les données ont été ajoutées à la liste existante.', 'success');
                }
                
                if (result.isConfirmed || result.isDenied) {
                    renumberRows();
                    importExcelModal.hide();
                }
            });
        })
        .catch(error => {
            console.error('Erreur d\'importation:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'importation',
                text: error.message || 'Une erreur est survenue lors de l\'importation du fichier Excel.'
            });
        });
    });
    
    // Charger les étudiants existants
    <?php if (!empty($etudiants)): ?>
        <?php foreach ($etudiants as $etudiant): ?>
            addNewStudentRow({
                id_palmares_etudiant: <?= $etudiant['id_palmares_etudiant'] ?>,
                nom_complet: "<?= addslashes(htmlspecialchars($etudiant['nom_complet'])) ?>",
                matricule: "<?= addslashes(htmlspecialchars($etudiant['matricule'] ?? '')) ?>",
                pourcentage: <?= floatval($etudiant['pourcentage']) ?>,
                mention: "<?= addslashes(htmlspecialchars($etudiant['mention'] ?? '')) ?>",
                rang: <?= intval($etudiant['rang']) ?>,
                credit_obtenu: <?= !empty($etudiant['credit_obtenu']) ? intval($etudiant['credit_obtenu']) : "null" ?>,
                credit_total: <?= !empty($etudiant['credit_total']) ? intval($etudiant['credit_total']) : "null" ?>
            });
        <?php endforeach; ?>
    <?php else: ?>
        // Ajouter une première ligne par défaut si aucun étudiant existant
        addNewStudentRow();
    <?php endif; ?>
});
</script>

<?php include "./views/include/footer.php"; ?>

