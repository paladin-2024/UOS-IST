<?php
include "./views/include/header.php";

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
if (!$isAdmin) {
    echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\'avez pas les droits pour accéder à cette page.'
                }).then(() => {
                    window.location.href = 'index';
                });
            </script>";
    exit();
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Import Grilles Anciennes - Mapping Visuel</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Import Mapping</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            
            <!-- ÉTAPE 1: Upload du fichier -->
            <div class="col-lg-12" id="etape1">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-1-circle-fill me-2 text-primary"></i>
                            Télécharger le fichier Excel
                        </h5>
                        
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="file" class="form-control" id="fichierExcel" 
                                           accept=".xlsx,.xls" required>
                                    <div class="form-text">
                                        Formats acceptés: .xlsx, .xls | Taille max: 50MB
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload me-2"></i>
                                        Analyser le fichier
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 2: Configuration des métadonnées -->
            <div class="col-lg-12" id="etape2" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-2-circle-fill me-2 text-warning"></i>
                            Configuration des métadonnées
                        </h5>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Module indépendant :</strong> Définissez librement vos propres données, 
                            sans contrainte du système existant.
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Année Académique</label>
                                <input type="text" class="form-control" id="meta_annee" 
                                       placeholder="Ex: 2019-2020">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Session</label>
                                <input type="text" class="form-control" id="meta_session" 
                                       placeholder="Ex: Principale">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Semestre</label>
                                <input type="text" class="form-control" id="meta_semestre" 
                                       placeholder="Ex: Semestre 1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Promotion</label>
                                <input type="text" class="form-control" id="meta_promotion" 
                                       placeholder="Ex: L1 INFO 2019">
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Établissement</label>
                                <input type="text" class="form-control" id="meta_etablissement" 
                                       placeholder="Ex: Université de Yaoundé I">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes supplémentaires</label>
                                <input type="text" class="form-control" id="meta_notes" 
                                       placeholder="Informations complémentaires">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 3: Prévisualisation et mapping -->
            <div class="col-lg-12" id="etape3" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-3-circle-fill me-2 text-success"></i>
                            Mapping des colonnes
                        </h5>
                        
                        <div class="row">
                            <!-- Colonnes détectées -->
                            <div class="col-md-6">
                                <h6>Colonnes détectées dans le fichier :</h6>
                                <div id="colonnesDetectees" class="border p-3 rounded bg-light">
                                    <!-- Sera rempli dynamiquement -->
                                </div>
                            </div>
                            
                            <!-- Mapping -->
                            <div class="col-md-6">
                                <h6>Assignation des colonnes :</h6>
                                <div class="mapping-container">
                                    
                                    <!-- Colonnes obligatoires -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-danger">Matricule étudiant *</label>
                                        <select class="form-select mapping-select" data-type="matricule">
                                            <option value="">Sélectionner une colonne</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-danger">Nom étudiant *</label>
                                        <select class="form-select mapping-select" data-type="nom">
                                            <option value="">Sélectionner une colonne</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Colonnes optionnelles -->
                                    <div class="mb-3">
                                        <label class="form-label">Prénoms</label>
                                        <select class="form-select mapping-select" data-type="prenoms">
                                            <option value="">Sélectionner une colonne (optionnel)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Date de naissance</label>
                                        <select class="form-select mapping-select" data-type="datenaissance">
                                            <option value="">Sélectionner une colonne (optionnel)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Zone pour les colonnes de notes -->
                                    <div class="border-top pt-3">
                                        <h6>Colonnes de notes :</h6>
                                        <div id="mappingNotes">
                                            <!-- Sera rempli dynamiquement -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterMappingNote()">
                                            <i class="bi bi-plus-circle me-1"></i>
                                            Ajouter une colonne de note
                                        </button>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                        
                        <!-- Prévisualisation des données mappées -->
                        <div class="mt-4">
                            <h6>Prévisualisation des données (5 premières lignes) :</h6>
                            <div id="previewData" class="table-responsive">
                                <!-- Sera rempli dynamiquement -->
                            </div>
                        </div>
                        
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-success" onclick="finaliserImport()">
                                <i class="bi bi-check-circle me-2"></i>
                                Finaliser l'import
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ÉTAPE 4: Confirmation -->
            <div class="col-lg-12" id="etape4" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-4-circle-fill me-2 text-info"></i>
                            Confirmation et import
                        </h5>
                        
                        <div id="confirmationDetails">
                            <!-- Sera rempli dynamiquement -->
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary me-2" onclick="procederImport()">
                                <i class="bi bi-database-fill-add me-2"></i>
                                Importer définitivement
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="retourEtape3()">
                                <i class="bi bi-arrow-left me-2"></i>
                                Retour au mapping
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<!-- Modal de chargement -->
<div class="modal fade" id="loadingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p id="loadingMessage">Traitement en cours...</p>
                <div class="progress">
                    <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let donneesBrutes = null;
let colonnesDetectees = [];
let mappingActuel = {};
let metadonneesActuelles = {};

document.addEventListener('DOMContentLoaded', function() {
    // Gestionnaire du formulaire d'upload
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        analyserFichier();
    });
});

/**
 * Analyser le fichier Excel uploadé
 */
function analyserFichier() {
    const fichier = document.getElementById('fichierExcel').files[0];
    
    if (!fichier) {
        Swal.fire({
            icon: 'error',
            title: 'Aucun fichier sélectionné',
            text: 'Veuillez sélectionner un fichier Excel.'
        });
        return;
    }
    
    // Vérifier la taille
    if (fichier.size > 50 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'Fichier trop volumineux',
            text: 'Le fichier ne doit pas dépasser 50 MB.'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('fichier', fichier);
    
    // Afficher le modal de chargement
    document.getElementById('loadingMessage').textContent = 'Analyse du fichier Excel...';
    updateProgress(0);
    $('#loadingModal').modal('show');
    
    // Envoyer pour analyse
    fetch('controller/analyser_excel_mapping.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        $('#loadingModal').modal('hide');
        
        if (data.success) {
            donneesBrutes = data.donnees;
            colonnesDetectees = data.colonnes;
            
            // Passer à l'étape 2
            afficherEtape2();
            
            Swal.fire({
                icon: 'success',
                title: 'Fichier analysé',
                text: `${data.nombreLignes} lignes et ${data.colonnes.length} colonnes détectées.`,
                timer: 2000
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'analyse',
                text: data.message
            });
        }
    })
    .catch(error => {
        $('#loadingModal').modal('hide');
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur lors de l\'analyse du fichier.'
        });
    });
}

/**
 * Afficher l'étape 2 (métadonnées)
 */
function afficherEtape2() {
    document.getElementById('etape1').style.display = 'none';
    document.getElementById('etape2').style.display = 'block';
    
    // Auto-suggestion basée sur le nom du fichier
    const nomFichier = document.getElementById('fichierExcel').files[0].name;
    suggererMetadonnees(nomFichier);
}

/**
 * Suggérer des métadonnées basées sur le nom du fichier
 */
function suggererMetadonnees(nomFichier) {
    // Patterns pour détecter des informations dans le nom du fichier
    const patterns = {
        annee: /(\d{4}[-\/]\d{4})/,
        session: /(principale|rattrapage|session\s*\d+)/i,
        semestre: /(semestre\s*\d+|s\d+)/i,
        promotion: /(l\d+|m\d+|licence|master)/i
    };
    
    // Appliquer les suggestions
    for (const [type, pattern] of Object.entries(patterns)) {
        const match = nomFichier.match(pattern);
        if (match) {
            const element = document.getElementById(`meta_${type}`);
            if (element && !element.value) {
                element.value = match[0];
            }
        }
    }
    
    // Continuer automatiquement vers l'étape 3 après 3 secondes si pas de saisie
    setTimeout(() => {
        if (document.getElementById('etape2').style.display !== 'none') {
            afficherEtape3();
        }
    }, 3000);
}

/**
 * Afficher l'étape 3 (mapping)
 */
function afficherEtape3() {
    // Récupérer les métadonnées
    metadonneesActuelles = {
        annee: document.getElementById('meta_annee').value || 'Non spécifiée',
        session: document.getElementById('meta_session').value || 'Non spécifiée',
        semestre: document.getElementById('meta_semestre').value || 'Non spécifié',
        promotion: document.getElementById('meta_promotion').value || 'Non spécifiée',
        etablissement: document.getElementById('meta_etablissement').value || 'Non spécifié',
        notes: document.getElementById('meta_notes').value || ''
    };
    
    document.getElementById('etape2').style.display = 'none';
    document.getElementById('etape3').style.display = 'block';
    
    // Afficher les colonnes détectées
    afficherColonnesDetectees();
    
    // Remplir les selects de mapping
    remplirSelectsMapping();
    
    // Auto-mapping intelligent
    effectuerAutoMapping();
}

/**
 * Afficher les colonnes détectées
 */
function afficherColonnesDetectees() {
    const container = document.getElementById('colonnesDetectees');
    
    let html = '<div class="row">';
    colonnesDetectees.forEach((colonne, index) => {
        html += `
            <div class="col-md-6 mb-2">
                <span class="badge bg-primary me-2">${index}</span>
                <strong>${colonne}</strong>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

/**
 * Remplir les selects de mapping
 */
function remplirSelectsMapping() {
    const selects = document.querySelectorAll('.mapping-select');
    
    selects.forEach(select => {
        // Vider le select (garder seulement la première option)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        // Ajouter les colonnes
        colonnesDetectees.forEach((colonne, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${index}: ${colonne}`;
            select.appendChild(option);
        });
    });
}

/**
 * Auto-mapping intelligent basé sur les noms de colonnes
 */
function effectuerAutoMapping() {
    const mappings = {
        matricule: ['matricule', 'numero', 'id', 'identifiant', 'code'],
        nom: ['nom', 'surname', 'family'],
        prenoms: ['prenom', 'prenoms', 'firstname', 'given'],
        datenaissance: ['naissance', 'birth', 'date']
    };
    
    Object.entries(mappings).forEach(([type, mots]) => {
        const select = document.querySelector(`[data-type="${type}"]`);
        if (!select) return;
        
        for (let i = 0; i < colonnesDetectees.length; i++) {
            const colonne = colonnesDetectees[i].toLowerCase();
            
            if (mots.some(mot => colonne.includes(mot))) {
                select.value = i;
                mappingActuel[type] = i;
                break;
            }
        }
    });
    
    // Auto-détection des colonnes de notes
    autoDetecterColonnesNotes();
    
    // Mettre à jour la prévisualisation
    mettreAJourPreview();
}

/**
 * Auto-détecter les colonnes de notes
 */
function autoDetecterColonnesNotes() {
    const containerNotes = document.getElementById('mappingNotes');
    containerNotes.innerHTML = '';
    
    colonnesDetectees.forEach((colonne, index) => {
        const colonneLower = colonne.toLowerCase();
        
        // Détecter si c'est une colonne de note
        if (colonneLower.includes('note') || 
            colonneLower.includes('cc') || 
            colonneLower.includes('ex') || 
            colonneLower.includes('mf') ||
            /\d+/.test(colonne)) { // Contient des chiffres
            
            ajouterMappingNote(index, colonne);
        }
    });
}

/**
 * Ajouter un mapping de note
 */
function ajouterMappingNote(colonneIndex = '', nomSuggere = '') {
    const container = document.getElementById('mappingNotes');
    const id = 'note_' + Date.now();
    
    const html = `
        <div class="row mb-2 mapping-note" id="${id}">
            <div class="col-md-5">
                <select class="form-select" data-note-colonne>
                    <option value="">Sélectionner une colonne</option>
                    ${colonnesDetectees.map((col, i) => 
                        `<option value="${i}" ${i == colonneIndex ? 'selected' : ''}>${i}: ${col}</option>`
                    ).join('')}
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" placeholder="Nom UE/ECUE" 
                       data-note-nom value="${nomSuggere}">
            </div>
            <div class="col-md-2">
                <select class="form-select" data-note-type>
                    <option value="note_finale">Note finale</option>
                    <option value="cc">Contrôle continu</option>
                    <option value="examen">Examen</option>
                    <option value="tp">TP</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-danger" 
                        onclick="supprimerMappingNote('${id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    mettreAJourPreview();
}

/**
 * Supprimer un mapping de note
 */
function supprimerMappingNote(id) {
    document.getElementById(id).remove();
    mettreAJourPreview();
}

/**
 * Mettre à jour la prévisualisation
 */
function mettreAJourPreview() {
    if (!donneesBrutes || donneesBrutes.length === 0) return;
    
    // Récupérer le mapping actuel
    const mapping = obtenirMappingActuel();
    
    // Créer le tableau de prévisualisation
    let html = '<table class="table table-sm table-bordered">';
    
    // En-têtes
    html += '<thead class="table-dark"><tr>';
    html += '<th>Matricule</th><th>Nom</th><th>Prénoms</th>';
    
    // Colonnes de notes
    document.querySelectorAll('[data-note-colonne]').forEach(select => {
        if (select.value) {
            const row = select.closest('.mapping-note');
            const nom = row.querySelector('[data-note-nom]').value || 'Note';
            const type = row.querySelector('[data-note-type]').value;
            html += `<th>${nom} (${type})</th>`;
        }
    });
    
    html += '</tr></thead>';
    
    // Données (5 premières lignes)
    html += '<tbody>';
    const maxLignes = Math.min(5, donneesBrutes.length);
    
    for (let i = 0; i < maxLignes; i++) {
        const ligne = donneesBrutes[i];
        html += '<tr>';
        
        // Colonnes de base
        html += `<td>${ligne[mapping.matricule] || ''}</td>`;
        html += `<td>${ligne[mapping.nom] || ''}</td>`;
        html += `<td>${ligne[mapping.prenoms] || ''}</td>`;
        
        // Colonnes de notes
        document.querySelectorAll('[data-note-colonne]').forEach(select => {
            if (select.value) {
                const valeur = ligne[select.value] || '';
                html += `<td>${valeur}</td>`;
            }
        });
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    
    if (donneesBrutes.length > 5) {
        html += `<small class="text-muted">... et ${donneesBrutes.length - 5} autres lignes</small>`;
    }
    
    document.getElementById('previewData').innerHTML = html;
}

/**
 * Obtenir le mapping actuel
 */
function obtenirMappingActuel() {
    const mapping = {};
    
    // Mapping des colonnes de base
    document.querySelectorAll('.mapping-select').forEach(select => {
        const type = select.dataset.type;
        if (select.value) {
            mapping[type] = parseInt(select.value);
        }
    });
    
    return mapping;
}

/**
 * Finaliser l'import (passer à l'étape 4)
 */
function finaliserImport() {
    const mapping = obtenirMappingActuel();
    
    // Validation
    if (!mapping.matricule || !mapping.nom) {
        Swal.fire({
            icon: 'error',
            title: 'Mapping incomplet',
            text: 'Le matricule et le nom sont obligatoires.'
        });
        return;
    }
    
    // Vérifier qu'il y a au moins une colonne de note
    const mappingNotes = [];
    document.querySelectorAll('[data-note-colonne]').forEach(select => {
        if (select.value) {
            const row = select.closest('.mapping-note');
            mappingNotes.push({
                colonne: parseInt(select.value),
                nom: row.querySelector('[data-note-nom]').value || 'Note',
                type: row.querySelector('[data-note-type]').value
            });
        }
    });
    
    if (mappingNotes.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Aucune note mappée',
            text: 'Voulez-vous continuer sans colonnes de notes ?',
            showCancelButton: true,
            confirmButtonText: 'Continuer',
            cancelButtonText: 'Retour'
        }).then((result) => {
            if (result.isConfirmed) {
                afficherEtape4(mapping, mappingNotes);
            }
        });
    } else {
        afficherEtape4(mapping, mappingNotes);
    }
}

/**
 * Afficher l'étape 4 (confirmation)
 */
function afficherEtape4(mapping, mappingNotes) {
    document.getElementById('etape3').style.display = 'none';
    document.getElementById('etape4').style.display = 'block';
    
    // Créer le résumé
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Métadonnées :</h6>
                <ul class="list-unstyled">
                    <li><strong>Année :</strong> ${metadonneesActuelles.annee}</li>
                    <li><strong>Session :</strong> ${metadonneesActuelles.session}</li>
                    <li><strong>Semestre :</strong> ${metadonneesActuelles.semestre}</li>
                    <li><strong>Promotion :</strong> ${metadonneesActuelles.promotion}</li>
                    <li><strong>Établissement :</strong> ${metadonneesActuelles.etablissement}</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Données à importer :</h6>
                <ul class="list-unstyled">
                    <li><strong>Nombre d'étudiants :</strong> ${donneesBrutes.length}</li>
                    <li><strong>Colonnes de notes :</strong> ${mappingNotes.length}</li>
                    <li><strong>Matricule :</strong> Colonne ${mapping.matricule}</li>
                    <li><strong>Nom :</strong> Colonne ${mapping.nom}</li>
                </ul>
            </div>
        </div>
    `;
    
    if (mappingNotes.length > 0) {
        html += `
            <div class="mt-3">
                <h6>Colonnes de notes :</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Colonne</th><th>Nom</th><th>Type</th></tr>
                        </thead>
                        <tbody>
        `;
        
        mappingNotes.forEach(note => {
            html += `
                <tr>
                    <td>${note.colonne}: ${colonnesDetectees[note.colonne]}</td>
                    <td>${note.nom}</td>
                    <td>${note.type}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div></div>';
    }
    
    document.getElementById('confirmationDetails').innerHTML = html;
    
    // Stocker les données pour l'import final
    window.mappingFinal = { mapping, mappingNotes };
}

/**
 * Retour à l'étape 3
 */
function retourEtape3() {
    document.getElementById('etape4').style.display = 'none';
    document.getElementById('etape3').style.display = 'block';
}

/**
 * Procéder à l'import définitif
 */
function procederImport() {
    if (!window.mappingFinal) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Données de mapping non trouvées.'
        });
        return;
    }
    
    const donneesFinal = {
        metadonnees: metadonneesActuelles,
        mapping: window.mappingFinal.mapping,
        mappingNotes: window.mappingFinal.mappingNotes,
        donneesBrutes: donneesBrutes
    };
    
    document.getElementById('loadingMessage').textContent = 'Import en cours...';
    updateProgress(0);
    $('#loadingModal').modal('show');
    
    fetch('controller/importer_grille_mapping.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(donneesFinal)
    })
    .then(response => response.json())
    .then(data => {
        $('#loadingModal').modal('hide');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Import réussi !',
                html: `
                    <p>La grille a été importée avec succès.</p>
                    <ul class="text-start">
                        <li><strong>${data.statistiques.etudiants}</strong> étudiants importés</li>
                        <li><strong>${data.statistiques.notes}</strong> notes importées</li>
                        <li><strong>ID Import :</strong> ${data.importId}</li>
                    </ul>
                `,
                confirmButtonText: 'Voir les résultats'
            }).then(() => {
                window.location.href = `index.php?view=deliberation/grilles_anciennes&import=${data.importId}`;
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur d\'import',
                text: data.message
            });
        }
    })
    .catch(error => {
        $('#loadingModal').modal('hide');
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur s\'est produite lors de l\'import.'
        });
    });
}

/**
 * Mettre à jour la barre de progression
 */
function updateProgress(percent) {
    document.getElementById('progressBar').style.width = percent + '%';
}

// Gestionnaires d'événements pour le mapping en temps réel
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('mapping-select') || 
        e.target.dataset.noteColonne !== undefined ||
        e.target.dataset.noteNom !== undefined ||
        e.target.dataset.noteType !== undefined) {
        mettreAJourPreview();
    }
});
</script>

<?php include "./views/include/footer_file.php"; ?>
