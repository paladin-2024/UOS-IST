<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// RÃ©cupÃ©rer les annÃ©es acadÃ©miques
$query = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$annees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// RÃ©cupÃ©rer les sessions
$query = "SELECT * FROM session ORDER BY \"designSession\"";
$stmt = $pdo->prepare($query);
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// RÃ©cupÃ©rer les sections
$query = "SELECT * FROM section ORDER BY \"designationSection\"";
$stmt = $pdo->prepare($query);
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AJOUT DE PALMARÃˆS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=enseignement/palmares">PalmarÃ¨s</a></li>
                <li class="breadcrumb-item active">Ajouter un palmarÃ¨s</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ajouter un nouveau palmarÃ¨s</h5>
                        
                        <!-- Formulaire d'ajout de palmarÃ¨s -->
                        <form id="palmaresForm" method="POST" action="controller/create_palmares.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="type_palmares" id="type_palmares_hidden" value="classique">
                            <div class="row mb-3">
    <div class="col-md-6">
        <label for="designation" class="form-label">Désignation/Titre du palmarès <span class="text-danger">*</span></label>
        <input type="text" name="designation" id="designation" class="form-control" required>
        <div class="invalid-feedback">Veuillez entrer une désignation.</div>
    </div>
    <div class="col-md-6">
        <label for="type_palmares" class="form-label">Type de palmarès <span class="text-danger">*</span></label>
        <select id="type_palmares" class="form-select" required>
            <option value="classique" selected>Classique (PADEM)</option>
            <option value="lmd">LMD</option>
        </select>
        <div class="form-text">Choisissez le système d'évaluation.</div>
    </div>
</div>
<div class="row mb-3">
    <div class="col-md-6">
        <label for="annee_academique" class="form-label">Année académique <span class="text-danger">*</span></label>
        <input type="text" name="annee_acad_idannee_acad" id="annee_academique" class="form-control" required>
        <div class="invalid-feedback">Veuillez entrer une année académique.</div>
    </div>
    <div class="col-md-6"></div>
</div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                                    <input type="text" name="section" id="section" class="form-control" required>
                                    <div class="invalid-feedback">Veuillez entrer une section.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="promotion" class="form-label">Promotion <span class="text-danger">*</span></label>
                                    <input type="text" name="promotion_idpromotion" id="promotion" class="form-control" required>
                                    <div class="invalid-feedback">Veuillez entrer une promotion.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
                                    <input type="text" name="session_idsession" id="session" class="form-control" required>
                                    <div class="invalid-feedback">Veuillez entrer une session.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="fichier_scanne" class="form-label">Fichier scannÃ© (PDF)</label>
                                    <input type="file" name="fichier_scanne" id="fichier_scanne" class="form-control" accept=".pdf">
                                    <div class="form-text">Facultatif. TÃ©lÃ©chargez une version scannÃ©e du palmarÃ¨s au format PDF.</div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">RÃ©sultats des Ã©tudiants</h5>
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
                                                    <th width="10%" id="scoreHeader">Pourcentage</th>
                                                    <th width="15%">Mention</th>
                                                    <th width="8%">Rang</th>
                                                    <th width="10%" id="creditsObtenusHeader">Crédits obtenus</th>
                                                    <th width="10%">CrÃ©dits totaux</th>
                                                    <th width="4%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="studentResultsBody">
                                                <!-- Les lignes seront ajoutÃ©es dynamiquement -->
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" id="addStudentRow">
                                            <i class="bi bi-plus-circle"></i> Ajouter un Ã©tudiant
                                        </button>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='?view=enseignement/palmares'">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer le palmarÃ¨s
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
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer les rÃ©sultats depuis Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx, .xls, .csv" required>
                        <div class="form-text">Formats acceptÃ©s: .xlsx, .xls, .csv</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">NumÃ©ro de ligne de dÃ©but</label>
                                <input type="number" class="form-control" id="startRow" name="startRow" value="2" min="1">
                                <div class="form-text">La premiÃ¨re ligne contient gÃ©nÃ©ralement les en-tÃªtes</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Mapping des colonnes</h6>
                        <p class="mb-0">Indiquez les colonnes correspondant Ã  chaque information (ex: A, B, C...)</p>
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
                                <label class="form-label" for="colPourcentage">Pourcentage</label>
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
                                <label class="form-label" id="creditsObtenusLabel">Crédits obtenus</label>
                                <input type="text" class="form-control" id="colCreditsObtenus" name="colCreditsObtenus" value="F">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">CrÃ©dits totaux</label>
                                <input type="text" class="form-control" id="colCreditsTotaux" name="colCreditsTotaux" value="G">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="processExcelBtn">
                    <i class="bi bi-check-circle"></i> Importer les donnÃ©es
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
            
            // VÃ©rifie si au moins un Ã©tudiant a Ã©tÃ© ajoutÃ©
            const rows = document.querySelectorAll('#studentResultsBody tr');
            if (rows.length === 0) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez ajouter au moins un Ã©tudiant au palmarÃ¨s.'
                });
                return false;
            }
            
            form.classList.add('was-validated');
        }, false);
    })();
    
    // Chargement des promotions en fonction de la section
    const sectionSelect = document.getElementById('section');
    const promotionSelect = document.getElementById('promotion');
    
    sectionSelect.addEventListener('change', function() {
        const sectionId = this.value;
        promotionSelect.disabled = sectionId === '';
        
        if (sectionId === '') {
            promotionSelect.innerHTML = '<option value="">SÃ©lectionnez d\'abord une section</option>';
            return;
        }
        
        // RÃ©cupÃ©rer les promotions de cette section
        fetch(`controller/get_promotions.php?section=${sectionId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">SÃ©lectionnez une promotion</option>';
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
                    text: 'Impossible de charger les promotions. Veuillez rÃ©essayer.'
                });
            });
    });
    
    // Gestion des lignes d'Ã©tudiants
    const studentResultsBody = document.getElementById('studentResultsBody');
    const addStudentRow = document.getElementById('addStudentRow');
    let rowCounter = 0;
    
    // Fonction pour ajouter une ligne d'Ã©tudiant
    function addNewStudentRow(data = {}) {
        rowCounter++;
        
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td class="text-center">${rowCounter}</td>
            <td>
                <input type="text" name="etudiants[${rowCounter}][nom_complet]" class="form-control form-control-sm" 
                    value="${data.nom_complet || ''}" required>
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
                    <option value="TrÃ¨s Bien" ${data.mention === 'TrÃ¨s Bien' ? 'selected' : ''}>TrÃ¨s Bien</option>
                    <option value="Excellent" ${data.mention === 'Satisfaction' ? 'selected' : ''}>Excellent</option>
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
        
        // Ajouter les Ã©couteurs d'Ã©vÃ©nements pour cette ligne
        const percentageInput = newRow.querySelector('.percentage-input');
        const mentionSelect = newRow.querySelector('.mention-select');
        const deleteButton = newRow.querySelector('.delete-row');
        
        // Associer automatiquement la mention selon le type
        const type = document.getElementById("type_palmares") ? document.getElementById("type_palmares").value : "classique";
        percentageInput.addEventListener("change", function() {
            if (mentionSelect.value === "") {
                const v = parseFloat(this.value);
                mentionSelect.value = (type === "lmd") ? getMentionFromMoyenne(v) : getMentionFromPercentage(v);
            }
        });
        
        // Supprimer la ligne
        deleteButton.addEventListener('click', function() {
            newRow.remove();
            renumberRows();
        });
        
        studentResultsBody.appendChild(newRow);
    }
    
    // RenumÃ©roter les lignes aprÃ¨s suppression
    function renumberRows() {
        const rows = studentResultsBody.querySelectorAll('tr');
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
    
    // Déterminer la mention en fonction de la moyenne (LMD)
    function getMentionFromMoyenne(m) {
        if (isNaN(m)) return '';
        if (m >= 16) return 'Très Bien';
        if (m >= 14) return 'Bien';
        if (m >= 12) return 'Assez Bien';
        if (m >= 10) return 'Satisfaction';
        return '';
    }

    const typeSelect = document.getElementById('type_palmares');
    const typeHidden = document.getElementById('type_palmares_hidden');
    const scoreHeader = document.getElementById('scoreHeader');
    const colPourcentageLabel = document.querySelector('label[for="colPourcentage"]');
    const creditsObtenusHeader = document.getElementById('creditsObtenusHeader');
    const creditsObtenusLabel = document.getElementById('creditsObtenusLabel');

    function setMentionOptions(select, type) {
        const current = select.value;
        select.innerHTML = '';
        const optAuto = document.createElement('option');
        optAuto.value = '';
        optAuto.textContent = 'Automatique';
        select.appendChild(optAuto);
        if (type === 'lmd') {
            ['Satisfaction','Assez Bien','Bien','Très Bien'].forEach(v => {
                const o = document.createElement('option'); o.value = v; o.textContent = v; select.appendChild(o);
            });
        } else {
            ['Passable','Assez Bien','Bien','Très Bien','Excellent','Distinction','Grande Distinction','La Plus Grande Distinction'].forEach(v => {
                const o = document.createElement('option'); o.value = v; o.textContent = v; select.appendChild(o);
            });
        }
        if ([...select.options].some(o => o.value === current)) {
            select.value = current;
        }
    }

    function updateUIForType() {
        const type = typeSelect ? typeSelect.value : 'classique';
        if (typeHidden) typeHidden.value = type;
        if (scoreHeader) scoreHeader.textContent = (type === 'lmd') ? 'Moyenne' : 'Pourcentage';
        if (colPourcentageLabel) colPourcentageLabel.textContent = (type === 'lmd') ? 'Moyenne' : 'Pourcentage';
        if (creditsObtenusHeader) creditsObtenusHeader.textContent = 'Crédits validés';
        if (creditsObtenusLabel) creditsObtenusLabel.textContent = 'Crédits validés';
        document.querySelectorAll('#studentResultsBody .percentage-input').forEach(inp => {
            if (type === 'lmd') {
                inp.min = '0'; inp.max = '20'; inp.step = '0.01'; inp.placeholder = '0 - 20';
                inp.onchange = function(){ const s = this.closest('tr').querySelector('.mention-select'); if (s && s.value==='') s.value = getMentionFromMoyenne(parseFloat(this.value)); };
            } else {
                inp.min = '0'; inp.max = '100'; inp.step = '0.01'; inp.placeholder = '0 - 100';
                inp.onchange = function(){ const s = this.closest('tr').querySelector('.mention-select'); if (s && s.value==='') s.value = getMentionFromPercentage(parseFloat(this.value)); };
            }
        });
        document.querySelectorAll('#studentResultsBody .mention-select').forEach(sel => setMentionOptions(sel, type));
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', updateUIForType);
    }

    // DÃ©terminer la mention en fonction du pourcentage
    function getMentionFromPercentage(percentage) {
        if (isNaN(percentage)) return '';
        
        if (percentage >= 90) return 'La Plus Grande Distinction';
        if (percentage >= 85) return 'Grande Distinction';
        if (percentage >= 80) return 'Grande Distinction';
        if (percentage >= 75) return 'Distinction';
        if (percentage >= 70) return 'Distinction';
        if (percentage >= 65) return 'Bien';
        if (percentage >= 60) return 'Assez Bien';
        if (percentage >= 50) return 'Passable';
        return '';
    }
    // Ajouter une ligne d'Ã©tudiant
    addStudentRow.addEventListener('click', function() {
    // Init UI for default type
    updateUIForType();
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
                text: 'Veuillez sÃ©lectionner un fichier Excel.'
            });
            return;
        }
        
        // RÃ©cupÃ©rer les paramÃ¨tres
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
            
            // Vider le tableau actuel et ajouter les nouvelles donnÃ©es
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
            
            importExcelModal.hide();
            
            Swal.fire({
                icon: 'success',
                title: 'Importation rÃ©ussie',
                text: `${data.etudiants.length} Ã©tudiants ont Ã©tÃ© importÃ©s avec succÃ¨s.`
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
    
    // Ajouter une premiÃ¨re ligne par dÃ©faut
    addNewStudentRow(); updateUIForType();
</script>

<?php include "./views/include/footer.php"; ?>







