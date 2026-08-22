
        <!-- JavaScript Dependencies -->
    <!-- Ajout de jQuery avant les autres bibliothèques -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Inclure Select2 après jQuery -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- FullCalendar -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
// Ne s'exécuter que sur les pages avec onglets
const hasTabs = document.querySelector('.tab-content') !== null;
    if (!hasTabs) return;

// Définir une variable globale pour indiquer si l'étudiant est en promotion terminale
const estPromotionTerminale = <?= isset($estPromotionTerminale) && $estPromotionTerminale ? 'true' : 'false' ?>;

// Vérifier les paramètres URL pour activer l'onglet approprié
const urlParams = new URLSearchParams(window.location.search);
const tabParam = urlParams.get('tab');

if (tabParam === 'schedule') {
// Activer l'onglet horaire
const scheduleTab = document.getElementById('schedule-tab');
if (scheduleTab) {
    scheduleTab.click();
}

// Activer l'élément de navigation correspondant
    document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
});
document.querySelector('.nav-item[data-page="schedule"]')?.classList.add('active');
} else if (tabParam === 'evaluations') {
// Activer l'onglet évaluations
const evaluationsTab = document.getElementById('evaluations-tab');
if (evaluationsTab) {
    evaluationsTab.click();
}

// Activer l'élément de navigation correspondant
    document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
});
document.querySelector('.nav-item[data-page="evaluations"]')?.classList.add('active');
} else if (tabParam === 'recours' && document.getElementById('recours-tab')) {
// Activer l'onglet recours si disponible
document.getElementById('recours-tab').click();

// Activer l'élément de navigation correspondant
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector('.nav-item[data-page="recours"]')?.classList.add('active');
}

// Activer la validation des formulaires Bootstrap
const forms = document.querySelectorAll('.needs-validation');
Array.from(forms).forEach(form => {
form.addEventListener('submit', event => {
if (!form.checkValidity()) {
        event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});

// Ajouter une classe pour l'animation des cours en cours
const pulseElements = document.querySelectorAll('.pulse');
pulseElements.forEach(el => {
    el.style.animation = 'pulse 2s infinite';
});

// Ajouter une feuille de style pour l'animation pulse
const style = document.createElement('style');
style.textContent = `
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 68, 148, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0, 68, 148, 0); }
100% { box-shadow: 0 0 0 0 rgba(0, 68, 148, 0); }
}

    .bg-primary-light {
            background-color: rgba(0, 68, 148, 0.1);
        }
    `;
    document.head.appendChild(style);
});
</script>


    <!-- Validation côté client pour les formulaires de choix de sujet -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fonction pour initialiser Bootstrap
        function initializeBootstrapComponents() {
            // Initialiser les tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialiser les popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }

                // Validation des formulaires de choix de sujet
                <?php if (!empty($sujetsDisponibles)): ?>
                    <?php foreach ($sujetsDisponibles as $sujet): ?>
        const form<?= $sujet['idsujets'] ?> = document.getElementById('formChoixSujet<?= $sujet['idsujets'] ?>');
        if (form<?= $sujet['idsujets'] ?>) {
            form<?= $sujet['idsujets'] ?>.addEventListener('submit', function(e) {
                const directeurId = this.querySelector('[name="directeur_id"]').value;
                const encadreurId = this.querySelector('[name="encadreur_id"]').value;

                if (directeurId === encadreurId && encadreurId !== '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur et le co-encadreur doivent être différents.',
                        confirmButtonColor: '#004494'
                    });
                    return false;
                }
            });
        }
                    <?php endforeach; ?>
                <?php endif; ?>

        // Validation du formulaire de proposition de sujet
        const proposerSujetForm = document.getElementById('proposerSujetForm');
        if (proposerSujetForm) {
            proposerSujetForm.addEventListener('submit', function(e) {
                const directeurId = this.querySelector('[name="directeur_id"]').value;
                const encadreurId = this.querySelector('[name="encadreur_id"]').value;

                if (directeurId === encadreurId && encadreurId !== '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur et le co-encadreur doivent être différents.',
                        confirmButtonColor: '#004494'
                    });
                    return false;
                }
            });
        }

        // Initialiser les composants Bootstrap
        initializeBootstrapComponents();
    });

    // Fonction pour afficher le modal de réponse
    function showReplyModal(tacheId) {
        document.getElementById('reply_tache_id').value = tacheId;
        document.getElementById('commentaire').value = '';
        document.getElementById('fichier').value = '';
        const replyModal = new bootstrap.Modal(document.getElementById('replyModal'));
        replyModal.show();
    }
    </script>

    <!-- Script principal pour l'interface utilisateur -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM chargé - initialisation des composants UI');

        // Détecter le type de page (avec tabs ou page simple)
        const hasTabs = document.querySelector('.tab-content') !== null;
        console.log('Type de page détecté:', hasTabs ? 'avec onglets' : 'page simple');

        // 1. Menu latéral - handled by main_scripts.php (sidebarToggle + overlay)
        // Legacy compatibility: if menuToggle exists, bind it too
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
            });
        }
        
        // 2. Menu de profil
        const profileIcon = document.getElementById('profileIcon');
        const profileMenu = document.getElementById('profileMenu');
        
        if (profileIcon && profileMenu) {
            console.log('Éléments du profil trouvés');
            
            profileIcon.addEventListener('click', function(e) {
                e.preventDefault();
                profileMenu.classList.toggle('show');
                console.log('Profile toggled:', profileMenu.classList.contains('show'));
            });
            
            // Fermer le menu au clic à l'extérieur
            document.addEventListener('click', function(event) {
                if (!event.target.closest('#profileIcon') && !event.target.closest('#profileMenu')) {
                    profileMenu.classList.remove('show');
                }
            });
        } else {
            console.error('Éléments du profil manquants:', { profileIcon, profileMenu });
        }
        
        // Dans le gestionnaire d'événements existant
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                const page = this.dataset.page;
                switch(page) {
                    case 'home':
                        if (estPromotionTerminale) {
                            document.querySelector('#subjects-tab')?.click();
                        } else {
                            document.querySelector('#courses-tab')?.click();
                        }
                        break;
                    case 'tasks':
                        if (estPromotionTerminale) {
                            document.querySelector('#tasks-tab')?.click();
                        }
                        break;
                    case 'schedule':
                        document.querySelector('#schedule-tab')?.click();
                        break;
                    case 'evaluations':
                        document.querySelector('#evaluations-tab')?.click();
                        break;
                    case 'profile':
                        window.location.href = 'profile';
                        break;
                    case 'recours':
                        document.querySelector('#recours-tab')?.click();
                        break;
                }
            });
        });

        
        // 4. Gestion du bouton proposer sujet
        const proposerSujetBtn = document.getElementById('proposerSujetBtn');
        const proposerSujetSidebar = document.getElementById('proposerSujetSidebar');
        const proposerSujetModal = document.getElementById('proposerSujetModal');
        
        if (proposerSujetModal) {
            const bsModal = new bootstrap.Modal(proposerSujetModal);
            
            if (proposerSujetBtn) {
                proposerSujetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    bsModal.show();
                });
            }
            
            if (proposerSujetSidebar) {
                proposerSujetSidebar.addEventListener('click', function(e) {
                    e.preventDefault();
                    bsModal.show();
                });
            }
        }
        
        // 5. Gestion des liens du menu latéral
        document.querySelectorAll('.sidebar-menu a[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                
                // Update active state in sidebar
                document.querySelectorAll('.sidebar-menu .nav-link').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                // Map data-page to tab button id
                const tabMap = {
                    'subjects': '#subjects-tab',
                    'tasks': '#tasks-tab',
                    'courses': '#courses-tab',
                    'evaluations': '#evaluations-tab',
                    'schedule': '#schedule-tab',
                    'recours': '#recours-tab',
                    'suivi-enseignements': '#suivi-enseignements-tab',
                    'messages': '#messages-tab',
                    'plan': '#plan-tab'
                };
                
                if (page === 'profile') {
                    window.location.href = 'profile';
                    return;
                }
                
                const tabSelector = tabMap[page];
                if (tabSelector) {
                    const tabBtn = document.querySelector(tabSelector);
                    if (tabBtn) {
                        tabBtn.click();
                    }
                }
                
                // Close sidebar on mobile
                if (window.innerWidth < 992) {
                    if (typeof closeSidebar === 'function') {
                        closeSidebar();
                    } else {
                        const sb = document.getElementById('sidebar');
                        const so = document.getElementById('sidebarOverlay');
                        if (sb) sb.classList.remove('show');
                        if (so) so.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                }
            });
        });
        
        // Vérification de validation des formulaires
        document.querySelectorAll('form').forEach(form => {
            console.log('Formulaire trouvé:', form.id || 'sans id');
        });
        
        console.log('Initialisation UI terminée avec succès');
    });



// Fonction pour charger les détails d'un cours
function loadCourseDetails(idEcue) {
    const modal = new bootstrap.Modal(document.getElementById('courseDetailsModal'));
    modal.show();
    
    const courseContent = document.getElementById('courseContent');
    courseContent.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-3">Chargement des détails du cours...</p>
        </div>
    `;
    
    // Charger les détails du cours via AJAX
    fetch(`../controller/get_course_details_student.php?id=${idEcue}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Mettre à jour le titre du modal
            document.getElementById('courseTitle').innerHTML = `
                <i class="fas fa-book-open me-2"></i>${data.course.designationECUE}
            `;
            
            // Créer le contenu HTML pour les détails du cours
            let html = `
                <div class="course-details">
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>Informations générales
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-layer-group text-primary me-2"></i>
                                        <strong>UE:</strong> ${data.course.designationUE}
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                                        <strong>Semestre:</strong> ${data.course.numeroSemestre}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        <strong>Volume horaire:</strong>
                                    </p>
                                    <div class="d-flex flex-wrap gap-2 ms-4">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-chalkboard-teacher me-1"></i>CMI: ${data.course.CMI}h
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-users me-1"></i>TD: ${data.course.TD}h
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-flask me-1"></i>TP: ${data.course.TP}h
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-user-tie me-2"></i>Enseignants
                            </h5>
                            <ul class="list-group list-group-flush">
            `;
            
            if (data.enseignants && data.enseignants.length > 0) {
                data.enseignants.forEach(ens => {
                    html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-${ens.poste === 'Titulaire' ? 'user-tie' : 'user-graduate'} text-primary me-3"></i>
                                ${ens.noms}
                            </div>
                            <span class="badge bg-${ens.poste === 'Titulaire' ? 'primary' : 'info'} rounded-pill">
                                ${ens.poste}
                            </span>
                        </li>
                    `;
                });
            } else {
                html += `
                    <li class="list-group-item bg-transparent">
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-user-slash mb-2" style="font-size: 2rem;"></i>
                            <p class="mb-0">Aucun enseignant assigné</p>
                        </div>
                    </li>
                `;
            }
            
            html += `
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-file-pdf me-2"></i>Supports de cours
                            </h5>
            `;
            
            if (data.supports && data.supports.length > 0) {
                html += `<div class="list-group list-group-flush">`;
                data.supports.forEach(support => {
                    html += `
                        <div class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                    ${support.titre}
                                </h6>
                                ${support.est_payant ? 
                                    `<span class="badge bg-${support.access_granted ? 'success' : 'warning'}">
                                        <i class="fas fa-${support.access_granted ? 'unlock' : 'lock'} me-1"></i>
                                        ${support.access_granted ? 'Accès autorisé' : 'Accès payant'}
                                    </span>` : 
                                    '<span class="badge bg-info"><i class="fas fa-unlock me-1"></i>Gratuit</span>'
                                }
                            </div>
                                                                                    ${support.description ?
                                `<p class="mb-3 small text-muted">${formatDescription(support.description)}</p>` : 
                                ''
                            }
                            <div class="d-flex justify-content-end">
                                ${support.est_payant && !support.access_granted ?
                                    `<button class="btn btn-sm btn-warning" onclick="accessPayment(${support.idsupport}, 'support')">
                                        <i class="fas fa-lock me-2"></i> Obtenir l'accès
                                    </button>` :
                                    `<a href="../uploads/supports/${support.fichier}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-download me-2"></i> Télécharger
                                    </a>`
                                }
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            } else {
                html += `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-file-excel mb-3" style="font-size: 2rem;"></i>
                        <p class="mb-0">Aucun support disponible</p>
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                    
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-list-ol me-2"></i>Chapitres
                            </h5>
            `;
            
            if (data.chapters && data.chapters.length > 0) {
                html += `<div class="accordion" id="chaptersAccordion">`;
                data.chapters.forEach((chapter, index) => {
                    html += `
                        <div class="accordion-item border-0 mb-3 shadow-sm">
                            <h2 class="accordion-header" id="heading${chapter.idpartie}">
                                <button class="accordion-button ${index > 0 ? 'collapsed' : ''}" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#collapse${chapter.idpartie}" 
                                        aria-expanded="${index === 0 ? 'true' : 'false'}" 
                                        aria-controls="collapse${chapter.idpartie}">
                                    <div class="d-flex align-items-center w-100">
                                        <i class="fas fa-book me-3 text-primary"></i>
                                        <div>
                                            <strong>${chapter.titre}</strong>
                                        </div>
                                        <span class="ms-auto badge bg-secondary">
                                            Chapitre ${chapter.ordre}
                                        </span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse${chapter.idpartie}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" 
                                 aria-labelledby="heading${chapter.idpartie}" data-bs-parent="#chaptersAccordion">
                                <div class="accordion-body">
                                    <div class="chapter-content mb-4">
                                        ${chapter.description ? formatDescription(chapter.description) : 'Aucune description disponible'}
                                    </div>
                                    
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-paperclip me-2"></i>Ressources
                                    </h6>
                                    <div class="list-group">
                    `;
                    
                    if (chapter.ressources && chapter.ressources.length > 0) {
                        chapter.ressources.forEach(res => {
                            html += `
                                <div class="list-group-item border-0 mb-2 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0">
                                            <i class="${getResourceIcon(res.type_ressource)} text-primary me-2"></i>
                                            ${res.titre}
                                        </h6>
                                        <span class="badge bg-${
                                            res.est_payant ?
                                            (res.access_granted ? 'success' : 'warning') :
                                            'info'
                                        }">
                                            <i class="fas fa-${
                                                res.est_payant ?
                                                (res.access_granted ? 'unlock' : 'lock') :
                                                'unlock'
                                            } me-1"></i>
                                            ${
                                                res.est_payant ?
                                                (res.access_granted ? 'Accès autorisé' : 'Accès payant') :
                                                'Gratuit'
                                            }
                                        </span>
                                    </div>
                                    ${res.description ? 
                                        `<p class="mb-3 small text-muted">${formatDescription(res.description)}</p>` : 
                                        ''
                                    }
                                    <div class="d-flex justify-content-end">
                                        ${
                                            res.est_payant && !res.access_granted ?
                                            `<button class="btn btn-sm btn-warning" onclick="accessPayment(${res.idressource}, 'ressource')">
                                                <i class="fas fa-lock me-2"></i> Obtenir l'accès
                                            </button>` :
                                            (res.fichier ?
                                                `<a href="../uploads/ressources/${res.fichier}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download me-2"></i> Télécharger
                                                </a>` :
                                                (res.lien_externe ?
                                                    `<a href="${res.lien_externe}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-external-link-alt me-2"></i> Accéder
                                                    </a>` :
                                                    ''
                                                )
                                            )
                                        }
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html += `
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-folder-open mb-2" style="font-size: 1.5rem;"></i>
                                <p class="mb-0">Aucune ressource disponible pour ce chapitre</p>
                            </div>
                        `;
                    }
                    
                    html += `
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            } else {
                html += `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-book-open mb-3" style="font-size: 2rem;"></i>
                        <p class="mb-0">Aucun chapitre disponible pour ce cours</p>
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                    
                    <!-- Devoirs -->
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-3">
                                <i class="fas fa-tasks me-2"></i>Devoirs
                            </h5>
            `;
            
            if (data.devoirs && data.devoirs.length > 0) {
                html += `<div class="list-group">`;
                data.devoirs.forEach(assignment => {
                    const today = new Date();
                    const deadline = new Date(assignment.date_limite);
                    const isExpired = today > deadline;
                    const isGroupWork = assignment.type_travail === 'groupe' || assignment.is_group_work === true;
                    
                    // Afficher les travaux pratiques (type_travail défini)
                    const workType = isGroupWork ? 
                        '<span class="badge bg-info"><i class="fas fa-users me-1"></i>Groupe</span>' :
                        (assignment.type_travail ? '<span class="badge bg-primary"><i class="fas fa-user me-1"></i>Individuel</span>' : '');
                    
                    html += `
                        <div class="list-group-item border-0 mb-3 ${isExpired ? 'bg-light' : 'bg-white shadow-sm'}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-clipboard-list text-primary me-2"></i>
                                    ${assignment.titre}
                                </h6>
                                <div>
                                    ${workType}
                                    <span class="badge bg-${isExpired ? 'danger' : 'info'} ms-1">
                                        <i class="fas fa-${isExpired ? 'calendar-times' : 'calendar-alt'} me-1"></i>
                                        ${isExpired ? 'Expiré' : formatDate(assignment.date_limite)}
                                    </span>
                                </div>
                            </div>
                            ${assignment.description ? 
                                `<p class="mb-3 small">${formatDescription(assignment.description)}</p>` : 
                                ''
                            }
                            
                            ${isGroupWork ? `
                            <div class="mb-3 p-3 bg-info-subtle rounded">
                                <h6 class="mb-2"><i class="fas fa-users me-2"></i>Travail de groupe</h6>
                                <p class="small mb-2">Maximum ${assignment.max_etudiants_groupe} étudiants par groupe</p>
                                <button class="btn btn-sm btn-primary" onclick="gererGroupeTravail(${assignment.iddevoir})">
                                    <i class="fas fa-users me-2"></i>${assignment.groupe_formé ? 'Gérer mon groupe' : 'Constituer mon groupe'}
                                </button>
                            </div>
                            ` : ''}
                            
                            <div class="d-flex justify-content-between align-items-center">
                                ${isGroupWork ?
                                    (assignment.groupe_formé ?
                                        (assignment.groupe_paye ?
                                            (() => {
                                                // Si fichier par groupe, utiliser le fichier du groupe
                                                if (assignment.fichier_par_groupe == 1 || assignment.fichier_par_groupe === true) {
                                                    if (assignment.fichier_groupe) {
                                                        return `<a href="../uploads/travaux_cours/${assignment.fichier_groupe}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="fas fa-download me-2"></i> Télécharger mon devoir (Groupe ${assignment.groupe_info ? assignment.groupe_info.numero_groupe : ''})
                                                        </a>`;
                                                    } else {
                                                        return `<span class="badge bg-warning text-dark p-2"><i class="fas fa-hourglass-half me-1"></i>Fichier non encore disponible</span>`;
                                                    }
                                                } else {
                                                    return `<a href="../uploads/travaux_cours/${assignment.fichier}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-download me-2"></i> Télécharger le devoir
                                                    </a>`;
                                                }
                                            })() :
                                            `<button class="btn btn-sm btn-success" onclick="gererGroupeTravail(${assignment.iddevoir})">
                                                <i class="fas fa-credit-card me-2"></i>Payer le groupe
                                            </button>`
                                        ) :
                                        `<button class="btn btn-sm btn-warning" onclick="gererGroupeTravail(${assignment.iddevoir})">
                                            <i class="fas fa-users me-2"></i>Constituer mon groupe pour payer
                                        </button>`
                                    ) :
                                    (assignment.est_payant && !assignment.access_granted ? 
                                        `<button class="btn btn-sm btn-warning" onclick="payerTravail(${assignment.iddevoir}, '${assignment.titre}', ${assignment.prix_par_etudiant || 0}, '${assignment.devise || 'USD'}')">
                                            <i class="fas fa-lock me-2"></i>Payer (${assignment.prix_par_etudiant || 0} ${assignment.devise || 'USD'})
                                        </button>` :
                                        `<a href="../uploads/travaux_cours/${assignment.fichier}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-download me-2"></i> Télécharger le devoir
                                        </a>`
                                    )
                                }
                                
                                ${!isGroupWork && (!assignment.est_payant || assignment.access_granted) ? 
                                    (isExpired ? 
                                        `<span class="badge bg-danger p-2">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            Délai dépassé
                                        </span>` : 
                                        `<button class="btn btn-sm btn-success" onclick="showSubmissionForm(${assignment.iddevoir})">
                                            <i class="fas fa-paper-plane me-2"></i> Soumettre
                                        </button>`
                                    ) : ''
                                }
                            </div>
                            
                            ${assignment.reponse ? 
                                `<div class="mt-3 p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-reply text-success me-2"></i>
                                            Votre soumission
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            ${formatDate(assignment.reponse.date_soumission)}
                                        </small>
                                    </div>
                                    <p class="small mb-2">${assignment.reponse.commentaire || 'Aucun commentaire'}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="../uploads/reponses/${assignment.reponse.fichier}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                            <i class="fas fa-file me-2"></i> Voir votre fichier
                                        </a>
                                        
                                        ${assignment.reponse.note ? 
                                            `<span class="badge bg-${assignment.reponse.note >= 10 ? 'success' : 'danger'} p-2">
                                                <i class="fas fa-${assignment.reponse.note >= 10 ? 'check-circle' : 'times-circle'} me-1"></i>
                                                Note: ${assignment.reponse.note}/20
                                            </span>` : 
                                            `<span class="badge bg-secondary p-2">
                                                <i class="fas fa-hourglass-half me-1"></i>
                                                En attente d'évaluation
                                            </span>`
                                        }
                                    </div>
                                    ${assignment.reponse.feedback_enseignant ? 
                                        `<div class="mt-3 p-2 bg-white rounded">
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="fas fa-comment-dots text-primary me-2"></i>
                                                <strong>Feedback de l'enseignant:</strong>
                                            </div>
                                            <p class="small mb-0">${assignment.reponse.feedback_enseignant}</p>
                                        </div>` : ''
                                    }
                                </div>` : ''
                            }
                        </div>
                    `;
                });
                html += `</div>`;
            } else {
                html += `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clipboard mb-3" style="font-size: 2rem;"></i>
                        <p class="mb-0">Aucun devoir disponible pour ce cours</p>
                    </div>
                `;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            courseContent.innerHTML = html;
        })
        .catch(error => {
            courseContent.innerHTML = `
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                    <div>
                        <strong>Erreur:</strong> ${error.message}
                    </div>
                </div>
            `;
        });
}

// Fonction pour obtenir l'icône en fonction du type de ressource
function getResourceIcon(type) {
    switch(type) {
        case 'PDF': return 'fas fa-file-pdf';
        case 'Vidéo': return 'fas fa-video';
        case 'Audio': return 'fas fa-headphones';
        case 'Présentation': return 'fas fa-file-powerpoint';
        case 'Lien': return 'fas fa-link';
        default: return 'fas fa-file';
    }
}

// Fonction pour formater une date
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('fr-FR', options);
}

// Fonction pour formater la description (convertit les sauts de ligne en balises HTML)
function formatDescription(description) {
    if (!description) return '';
    // Remplacer les sauts de ligne par des balises <br>
    let formatted = description.replace(/\n/g, '<br>');
    // Remplacer les *** par des gras et ** par des italiques (Markdown simple)
    formatted = formatted.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>');
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
    return formatted;
}

// Fonction pour afficher le formulaire de soumission
function showSubmissionForm(assignmentId) {
    document.getElementById('submission_iddevoir').value = assignmentId;
    document.getElementById('submission_commentaire').value = '';
    document.getElementById('submission_fichier').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('submitAssignmentModal'));
    modal.show();
}

// Fonction pour obtenir l'accès à une ressource payante
function accessPayment(id, type) {
    Swal.fire({
        title: 'Accès payant',
        html: `
            <div class="text-start">
                <p>Pour accéder à cette ressource, vous devez effectuer un paiement.</p>
                <p class="mb-0"><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)}</p>
                <p><strong>ID:</strong> ${id}</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-credit-card me-2"></i>Procéder au paiement',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Annuler',
        confirmButtonColor: '#004494',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `../controller/payment.php?type=${type}&id=${id}`;
        }
    });
}

// Fonction pour payer un travail pratique
function payerTravail(idDevoir, titre, montant, devise = 'USD') {
    document.getElementById('fp_affectation_id').value = idDevoir;
    document.getElementById('fp_frais_nom').textContent = titre;
    document.getElementById('fp_montant').value = montant;
    document.getElementById('fp_devise').value = devise;
    document.getElementById('fp_montant_display').textContent = new Intl.NumberFormat('fr-FR').format(montant) + ' ' + devise;
    
    // Configurer pour les paiements de travaux
    window.currentPaiementType = 'travail';
    window.currentDevoirId = idDevoir;
    
    const modal = new bootstrap.Modal(document.getElementById('flexPayModal'));
    modal.show();
}

// Fonction pour gérer les groupes de travail
function gererGroupeTravail(idDevoir) {
    // Charger les informations du groupe
    fetch(`../controller/travaux_cours_controller.php?action=get_groupe_info&devoir=${idDevoir}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showGroupeModal(idDevoir, data);
            } else {
                // Pas encore de groupe, proposer de créer
                showCreateGroupeModal(idDevoir);
            }
        })
        .catch(err => {
            showCreateGroupeModal(idDevoir);
        });
}

// Afficher le modal de gestion de groupe
function showGroupeModal(idDevoir, data) {
    const maxMembres = data.max_etudiants_groupe || 3;
    const typePrix = data.type_prix_groupe || 'forfaitaire';
    const prixUnitaire = data.prix_par_etudiant || 0;
    const prixForfaitaire = data.prix_forfaitaire || 0;
    const devise = data.devise || 'USD';
    const membresCount = data.membres ? data.membres.length : 0;
    const peutPayer = membresCount > 0 && !data.groupe.est_paye;
    
    let html = `
        <div class="text-start">
            <h5>Mon groupe</h5>
            <p class="mb-2">Numéro du groupe: <strong>${data.groupe.numero_groupe}</strong></p>
            <p class="mb-2">Statut: <span class="badge bg-${data.groupe.est_paye ? 'success' : (membresCount > 0 ? 'warning' : 'secondary')}">
                ${data.groupe.est_paye ? 'Payé' : (membresCount > 0 ? 'Groupe constitué' : 'En cours de constitution')}
            </span></p>
            
            <h6 class="mt-3">Membres du groupe (${membresCount}/${maxMembres}):</h6>
            <ul class="list-group">
    `;
    
    if (data.membres && data.membres.length > 0) {
        data.membres.forEach(membre => {
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                ${membre.noms} ${membre.est_createur ? '<span class="badge bg-primary">Chef</span>' : ''}
            </li>`;
        });
    } else {
        html += `<li class="list-group-item text-muted">Aucun membre ajouté</li>`;
    }
    
    html += `</ul>`;
    
    if (!data.groupe.est_paye) {
        // Afficher le prix estimé
        let montantAffiche = 0;
        if (typePrix === 'forfaitaire') {
            montantAffiche = prixForfaitaire;
        } else {
            montantAffiche = prixUnitaire * membresCount;
        }
        
        html += `
            <div class="mt-3 p-3 bg-light rounded">
                <p class="mb-1"><strong>Type de prix:</strong> ${typePrix === 'forfaitaire' ? 'Forfaitaire par groupe' : 'Par étudiant (' + prixUnitaire + ' ' + devise + ' × ' + membresCount + ')'}</p>
                <p class="mb-0"><strong>Montant à payer:</strong> <span class="text-success fs-5">${montantAffiche} ${devise}</span></p>
                ${typePrix === 'par_etudiant' ? '<small class="text-muted">Le montant sera ajusté selon le nombre de membres</small>' : ''}
            </div>
        `;
        
        // Bouton pour ajouter des membres si pas encore complet
        if (membresCount < maxMembres) {
            html += `
                <button class="btn btn-primary w-100 mt-2" onclick="showAddMemberModal(${idDevoir}, ${data.groupe.id_groupe})">
                    <i class="fas fa-user-plus me-2"></i>Ajouter un membre
                </button>
            `;
        }
        
        // Bouton payer - seulement si au moins 1 membre
        if (data.groupe.est_paye) {
            html += `
                <div class="alert alert-success mt-2">
                    <i class="fas fa-check-circle me-2"></i>Ce groupe a déjà payé. Tous les membres peuvent télécharger le fichier.
                </div>
            `;
        } else if (peutPayer) {
            html += `
                <button class="btn btn-success w-100 mt-2" onclick="payerGroupe(${idDevoir}, ${data.groupe.id_groupe}, '${devise}')">
                    <i class="fas fa-credit-card me-2"></i>Payer maintenant (${montantAffiche} ${devise})
                </button>
            `;
        } else if (membresCount === 0) {
            html += `
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-info-circle me-2"></i>Ajoutez au moins un membre pour pouvoir payer
                </div>
            `;
        }
    }
    
    html += `</div>`;
    
    Swal.fire({
        title: 'Gestion du groupe',
        html: html,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: membresCount < maxMembres ? 'Ajouter un membre' : 'Fermer',
        cancelButtonText: 'Fermer'
    }).then(result => {
        if (result.isConfirmed && membresCount < maxMembres) {
            showAddMemberModal(idDevoir, data.groupe.id_groupe);
        }
    });
}

// Afficher le modal pour créer un groupe (auto number)
function showCreateGroupeModal(idDevoir) {
    // Get next available group number automatically
    fetch(`../controller/travaux_cours_controller.php?action=get_next_groupe_number&devoir=${idDevoir}`)
        .then(response => response.json())
        .then(numData => {
            const nextNum = numData.success ? numData.next_number : 1;
            
            Swal.fire({
                title: 'Créer votre groupe',
                html: `
                    <div class="text-start">
                        <p>Vous n'avez pas encore de groupe pour ce travail.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Un numéro de groupe vous sera attribué automatiquement.
                        </div>
                        <p class="mb-0 text-muted">Cliquez sur "Créer" pour générer votre groupe.</p>
                    </div>
                `,
                confirmButtonText: 'Créer mon groupe',
                cancelButtonText: 'Annuler',
                showCancelButton: true
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('../controller/travaux_cours_controller.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=create_groupe&id_devoir=${idDevoir}&numero_groupe=${nextNum}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const groupeNum = data.data?.numero_groupe || nextNum;
                            Swal.fire({
                                title: 'Groupe créé!',
                                html: `<p>Votre numéro de groupe: <strong>${groupeNum}</strong></p>
                                       <p>Ajoutez maintenant vos collègues de groupe.</p>`,
                                icon: 'success'
                            }).then(() => {
                                gererGroupeTravail(idDevoir);
                            });
                        } else {
                            Swal.fire('Erreur', data.message || 'Erreur lors de la création du groupe', 'error');
                        }
                    });
                }
            });
        });
}

// Afficher le modal pour ajouter un membre
function showAddMemberModal(idDevoir, idGroupe) {
    // Fermer le modal Bootstrap et SweetAlert pour éviter le conflit de focus trap
    const bsModal = bootstrap.Modal.getInstance(document.getElementById('courseDetailsModal'));
    if (bsModal) bsModal.hide();
    Swal.close();

    setTimeout(() => { Swal.fire({
        title: 'Ajouter un membre',
        html: `
            <div class="text-start">
                <p>Entrez le matricule de l'étudiant à ajouter:</p>
                <div class="mb-3">
                    <label class="form-label">Matricule de l'étudiant</label>
                    <input type="text" class="form-control" id="matricule_membre" placeholder="Ex: ETU001234">
                </div>
            </div>
        `,
        confirmButtonText: 'Ajouter',
        cancelButtonText: 'Annuler',
        showCancelButton: true,
        didOpen: () => {
            const input = document.getElementById('matricule_membre');
            if (input) input.focus();
        },
        preConfirm: () => {
            const val = document.getElementById('matricule_membre').value.trim();
            if (!val) {
                Swal.showValidationMessage('Veuillez entrer un matricule');
                return false;
            }
            return { matricule: val };
        }
    }).then(result => {
        if (result.isConfirmed && result.value.matricule) {
            // Rechercher l'étudiant et l'ajouter
            fetch(`../controller/travaux_cours_controller.php?action=rechercher_etudiant&matricule=${result.value.matricule}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.etudiant) {
                        // Ajouter l'étudiant au groupe
                        fetch('../controller/travaux_cours_controller.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `action=add_membre_groupe&id_groupe=${idGroupe}&id_etudiant=${data.etudiant.idetudiant}`
                        })
                        .then(resp => resp.json())
                        .then(addResult => {
                            if (addResult.success) {
                                Swal.fire('Succès', 'Membre ajouté au groupe!', 'success').then(() => {
                                    gererGroupeTravail(idDevoir);
                                });
                            } else {
                                Swal.fire('Erreur', addResult.message || 'Erreur lors de l\'ajout du membre', 'error');
                            }
                        });
                    } else {
                        Swal.fire('Erreur', 'Étudiant non trouvé', 'error');
                    }
                });
        }
    }); }, 200);
}

// Payer pour le groupe
function payerGroupe(idDevoir, idGroupe, devise = 'USD') {
    // Fermer le modal Bootstrap et SweetAlert pour éviter le conflit
    const bsModal = bootstrap.Modal.getInstance(document.getElementById('courseDetailsModal'));
    if (bsModal) bsModal.hide();
    Swal.close();

    // Initiate payment for the group
    fetch(`../controller/travaux_cours_controller.php?action=get_montant_groupe&devoir=${idDevoir}&groupe=${idGroupe}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const dev = data.devise || devise;
                // Show payment modal
                document.getElementById('fp_affectation_id').value = idDevoir;
                document.getElementById('fp_frais_nom').textContent = 'Paiement groupe TP';
                document.getElementById('fp_montant').value = data.montant;
                document.getElementById('fp_devise').value = dev;
                document.getElementById('fp_montant_display').textContent = new Intl.NumberFormat('fr-FR').format(data.montant) + ' ' + dev;
                
                window.currentPaiementType = 'travail_groupe';
                window.currentDevoirId = idDevoir;
                window.currentGroupeId = idGroupe;
                
                const modal = new bootstrap.Modal(document.getElementById('flexPayModal'));
                modal.show();
            }
        });
}

// Add this at the end of your existing script section
document.addEventListener('DOMContentLoaded', function() {
    // Handle recours tab in navigation
    const recoursTab = document.getElementById('recours-tab');
    if (recoursTab) {
        // Add event listener for sidebar navigation
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            if (link.dataset.page === 'recours') {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    recoursTab.click();
                    
                    // Close sidebar on mobile
                    if (window.innerWidth < 768) {
                        document.getElementById('sidebar').classList.remove('show');
                    }
                });
            }
        });
        
        // Add to bottom navigation if needed
        const recoursNavItem = document.querySelector('.bottom-nav .nav-item[data-page="recours"]');
        if (recoursNavItem) {
            recoursNavItem.addEventListener('click', function(e) {
                e.preventDefault();
                recoursTab.click();
                
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
            });
        }
    }
    
    // Form validation for recours submission
    const recoursForm = document.querySelector('#newRecoursModal form');
    if (recoursForm) {
        recoursForm.addEventListener('submit', function(e) {
            const idEcue = document.getElementById('id_ecue').value;
            const idSession = document.getElementById('id_session').value;
            const motif = document.getElementById('motif').value;
            const description = document.getElementById('description').value;
            
            if (!idEcue || !idSession || !motif || !description) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Veuillez remplir tous les champs obligatoires.',
                    confirmButtonColor: '#004494'
                });
                return false;
            }
            
            // File validation
            const preuveInput = document.getElementById('preuve');
            if (preuveInput.files.length > 0) {
                const fileSize = preuveInput.files[0].size / 1024 / 1024; // Size in MB
                const fileExt = preuveInput.files[0].name.split('.').pop().toLowerCase();
                
                if (fileSize > 5) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le fichier ne doit pas dépasser 5 MB.',
                        confirmButtonColor: '#004494'
                    });
                    return false;
                }
                
                if (!['pdf', 'jpg', 'jpeg', 'png'].includes(fileExt)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Format de fichier non supporté. Utilisez PDF, JPG ou PNG.',
                        confirmButtonColor: '#004494'
                    });
                    return false;
                }
            }
        });
    }
    
    // Check URL params for tab activation
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'recours' && recoursTab) {
        recoursTab.click();
    }
    
    // Add custom styles for better UI
    const customStyles = document.createElement('style');
    customStyles.textContent = `
        /* Custom styles for better UI */
        .card {
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        
        .nav-pills .nav-link {
            border-radius: 50rem;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link:not(.active):hover {
            background-color: rgba(0, 68, 148, 0.1);
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            box-shadow: 0 4px 8px rgba(0, 68, 148, 0.2);
        }
        
        .btn-primary, .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .btn-outline-primary {
            border-color: var(--primary-color) !important;
            color: var(--primary-color) !important;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color) !important;
            color: white !important;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(0, 68, 148, 0.05);
            color: var(--primary-color);
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 68, 148, 0.25);
        }
        
        .bg-success-light {
            background-color: rgba(25, 135, 84, 0.1);
        }
        
        .bg-danger-light {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .bg-primary-light {
            background-color: rgba(0, 68, 148, 0.1);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-pane.show {
            animation: fadeIn 0.3s ease-out;
        }
        
        /* Mobile improvements handled in head_student.php */
    `;
    document.head.appendChild(customStyles);
});
    </script>

<!-- Ajouter juste avant la balise de fermeture de footer_student.php -->


<script>
    // Initialiser Select2 sur les champs spécifiés
    $(document).ready(function() {
        // Fonction d'initialisation Select2 dans un modal
        function initSelect2InModal($modal) {
            $modal.find('.select2').each(function() {
                // Détruire l'instance existante si elle existe
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $(this).find('option:first').text() || 'Sélectionnez une option',
                    allowClear: true,
                    dropdownParent: $modal
                });
            });
        }

        // Initialiser Select2 à chaque ouverture de modal
        $(document).on('shown.bs.modal', '.modal', function() {
            initSelect2InModal($(this));
        });

        // Nettoyer Select2 à la fermeture du modal
        $(document).on('hidden.bs.modal', '.modal', function() {
            $(this).find('.select2').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });
        });

        // S'assurer que les validations fonctionnent avec Select2
        $('#proposerSujetForm').on('submit', function() {
            if (!$('#idSpecialisation').val()) {
                $('#idSpecialisation').next().addClass('is-invalid');
                return false;
            }
            if (!$('#directeur').val()) {
                $('#directeur').next().addClass('is-invalid');
                return false;
            }
            return true;
        });
        
        // Gérer les conflits Directeur/Encadreur (proposerSujetModal)
        $(document).on('change', '[name="directeur_id"], [name="encadreur_id"]', function() {
            var $form = $(this).closest('form');
            var directeurVal = $form.find('[name="directeur_id"]').val();
            var encadreurVal = $form.find('[name="encadreur_id"]').val();
            
            if (directeurVal && encadreurVal && directeurVal === encadreurVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Le directeur et l\'encadreur ne peuvent pas être la même personne.'
                });
                $form.find('[name="encadreur_id"]').val(null).trigger('change');
            }
        });
    });
</script>



</body>
</html>
