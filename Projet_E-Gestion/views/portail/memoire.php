<?php
require_once "head_student.php";

// Debug: Vérifier l'ID de l'étudiant
error_log("DEBUG memoire.php - Student ID from session: " . $studentId);
error_log("DEBUG memoire.php - Session data: " . print_r($_SESSION, true));



// Récupération des mémoires de l'étudiant (sujets avec statut)
$memoires = [];
$feesPaid = false;
$memoireFeesStatus = [];

try {
    // Récupérer les sujets (mémoires) de l'étudiant
    $etudiantModel = new Etudiant();
    $memoires = $etudiantModel->getStudentMemoires($studentId);
    
    // Récupérer la promotion de l'étudiant pour debug
    $connexion = Connexion::getInstance()->getPDO();
    $query = "SELECT promotion_idpromotion, designationPromotion FROM etudiant e 
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
              WHERE e.idetudiant = :id";
    $stmt = $connexion->prepare($query);
    $stmt->execute(['id' => $studentId]);
    $studentPromo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier le statut des frais
    $feesPaid = $etudiantModel->hasStudentPaidMemoireFees($studentId);
    $memoireFeesStatus = $etudiantModel->getMemoireFeesStatus($studentId);
    
    error_log("DEBUG memoire.php - Student promotion: " . ($studentPromo ? $studentPromo['designationPromotion'] . " (ID: {$studentPromo['promotion_idpromotion']})" : "NOT FOUND"));
    error_log("DEBUG memoire.php - Memoires found: " . count($memoires));
    error_log("DEBUG memoire.php - Fees paid: " . ($feesPaid ? 'yes' : 'no'));
    error_log("DEBUG memoire.php - Fees status: " . print_r($memoireFeesStatus, true));
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des mémoires: " . $e->getMessage());
    $memoires = [];
    $feesPaid = false;
}

// Set page title for mobile header
$pageTitle = 'Mes Mémoires';
$currentPage = 'memoire';
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid p-4">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">
                <i class="fas fa-book me-2"></i>Mes Mémoires
            </h4>
        </div>

        <?php if (empty($memoires)): ?>
            <div class="alert alert-info d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fs-4"></i>
                <div>
                    <h5>Aucun mémoire assigné pour le moment</h5>
                    <p class="mb-0">Votre sujet de mémoire sera affiché ici une fois qu'il vous sera assigné par l'administration.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($memoires as $memoire): 
                    $hasMemoire = !empty($memoire['memoire_path']);
                    $estProgrammee = $memoire['statut'] === 'Programmée';
                ?>
                <div class="col-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-alt me-2"></i>Mon Mémoire
                                </h5>
                                <?php if ($hasMemoire): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Mémoire déposé
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-clock me-1"></i>En cours
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Informations du sujet</h6>
                                    <p><i class="fas fa-heading text-primary me-2"></i><strong>Titre:</strong> <?php echo htmlspecialchars($memoire['intitule'] ?? 'Non défini'); ?></p>
                                    <p><i class="fas fa-graduation-cap text-primary me-2"></i><strong>Spécialisation:</strong> <?php echo htmlspecialchars($memoire['specialisation_nom'] ?? 'Non définie'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Encadrement</h6>
                                    <p><i class="fas fa-user-tie text-primary me-2"></i><strong>Directeur:</strong> <?php echo htmlspecialchars($memoire['directeur_nom'] ?? 'Non assigné'); ?></p>
                                    <?php if ($estProgrammee && !empty($memoire['date_soutenance'])): ?>
                                    <p><i class="fas fa-calendar-alt text-primary me-2"></i><strong>Date Soutenance:</strong> <?php echo date('d/m/Y H:i', strtotime($memoire['date_soutenance'])); ?></p>
                                    <p><i class="fas fa-map-marker-alt text-primary me-2"></i><strong>Lieu:</strong> <?php echo htmlspecialchars($memoire['lieu_soutenance'] ?? 'Non défini'); ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($memoire['note_finale']) && $memoire['note_finale'] !== null): 
                                        $noteFinale = floatval($memoire['note_finale']);
                                        $pourcentage = ($noteFinale / 20) * 100;
                                        if ($pourcentage < 50) {
                                            $mention = 'Ajourné';
                                            $mentionClass = 'danger';
                                        } elseif ($pourcentage <= 69) {
                                            $mention = 'Satisfaction';
                                            $mentionClass = 'warning';
                                        } elseif ($pourcentage <= 79) {
                                            $mention = 'Distinction';
                                            $mentionClass = 'success';
                                        } elseif ($pourcentage <= 89) {
                                            $mention = 'Grande Distinction';
                                            $mentionClass = 'info';
                                        } else {
                                            $mention = 'Plus Grande Distinction';
                                            $mentionClass = 'primary';
                                        }
                                    ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <h6 class="text-<?= $mentionClass ?> mb-2"><i class="fas fa-trophy me-2"></i>Résultat de Soutenance</h6>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fs-2 fw-bold text-<?= $mentionClass ?>"><?= number_format($noteFinale, 2) ?><small class="fs-6 text-muted">/20</small></span>
                                            <span class="badge bg-<?= $mentionClass ?> fs-6 px-3 py-2"><?= $mention ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Actions</h6>
                                </div>
                                <?php if ($hasMemoire): ?>
                                <div class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>Mémoire envoyé
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <?php if (!$hasMemoire): ?>
                                    <?php if ($feesPaid): ?>
                                    <button class="btn btn-primary" onclick="uploadMemoire(<?php echo $memoire['idsujets']; ?>)">
                                        <i class="fas fa-upload me-2"></i>Envoyer mon mémoire (PDF)
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-secondary" disabled title="Vous devez d'abord payer les frais de mémoire">
                                        <i class="fas fa-lock me-2"></i>Paiement requis
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!$estProgrammee): ?>
                                    <button class="btn btn-success" onclick="uploadMemoire(<?php echo $memoire['idsujets']; ?>)">
                                        <i class="fas fa-sync-alt me-2"></i>Remplacer le mémoire
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-success" disabled title="Impossible de remplacer - la soutenance est déjà programmée">
                                        <i class="fas fa-sync-alt me-2"></i>Remplacer le mémoire
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($memoire['memoire_path']); ?>" class="btn btn-info" target="_blank">
                                        <i class="fas fa-file-pdf me-2"></i>Voir mon mémoire
                                    </a>
                                    <?php if ($estProgrammee): ?>
                                    <button class="btn btn-warning" onclick="viewDefenseDetails(<?php echo $memoire['idsoutenance'] ?? 0; ?>)">
                                        <i class="fas fa-calendar-check me-2"></i>Détails soutenance
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$hasMemoire): ?>
                                <?php if (!empty($memoireFeesStatus)): ?>
                                    <?php 
                                        // Vérifier si tous les frais sont payés
                                        $allFeesPaid = true;
                                        $unpaidFees = [];
                                        foreach ($memoireFeesStatus as $fee) {
                                            if ($fee['statut_paiement'] !== 'paye') {
                                                $allFeesPaid = false;
                                                $unpaidFees[] = $fee;
                                            }
                                        }
                                    ?>
                                    <?php if (!$allFeesPaid): ?>
                                    <div class="alert alert-danger mt-3 mb-0">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        <strong>Paiement requis:</strong> Vous ne pouvez pas envoyer votre mémoire tant que tous les frais ne sont pas payés.
                                        <div class="mt-2">
                                            <strong>Frais à régler:</strong>
                                            <ul class="mb-0 mt-2">
                                                <?php foreach ($memoireFeesStatus as $fee): ?>
                                                <li>
                                                    <?php echo htmlspecialchars($fee['designation']); ?>
                                                    (<?php echo number_format($fee['montant'], 2); ?> <?php echo htmlspecialchars($fee['devise']); ?>)
                                                    <?php if ($fee['statut_paiement'] === 'paye'): ?>
                                                        <span class="badge bg-success ms-2">✓ Payé</span>
                                                    <?php elseif ($fee['statut_paiement'] === 'partiel'): ?>
                                                        <span class="badge bg-warning ms-2">Paiement partiel (<?php echo number_format($fee['montantPaye'], 2); ?> / <?php echo number_format($fee['montant'], 2); ?>)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger ms-2">À payer</span>
                                                    <?php endif; ?>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <small class="d-block mt-2">Accédez à la section "Frais Académiques" pour effectuer vos paiements.</small>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-success mt-3 mb-0">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Tous les frais requis sont payés:</strong> Vous pouvez maintenant soumettre votre mémoire.
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Aucun frais requis:</strong> Vous pouvez soumettre votre mémoire dès maintenant.
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/bottom_nav.php"; ?>

<!-- Modal for uploading memoire -->
<div class="modal fade" id="uploadMemoireModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>Envoyer mon mémoire
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../controller/upload_memoire.php" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="sujet_id" id="memoireSujetId">
                    <input type="hidden" name="uploadMemoireBtn" value="1">
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instructions</h6>
                        <ul class="mb-0">
                            <li>Le mémoire doit être au format PDF uniquement</li>
                            <li>Taille maximale : 50 MB</li>
                            <li>Assurez-vous que le document est complet avant l'envoi</li>
                            <li>Vous pourrez remplacer le mémoire après l'envoi si nécessaire</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="memoireFile" class="form-label fw-bold">
                            <i class="fas fa-file-pdf text-danger me-2"></i>Sélectionner votre mémoire (PDF)
                        </label>
                        <input type="file" class="form-control form-control-lg" name="memoire_file" id="memoireFile" accept=".pdf" required onchange="validateFile(this)">
                        <div class="form-text">Format accepté : PDF uniquement (max 50MB)</div>
                        <div id="fileError" class="text-danger mt-2" style="display:none;"></div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="uploadMemoireBtn" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Envoyer mon mémoire
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for defense details -->
<div class="modal fade" id="defenseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-check me-2"></i>Détails de votre Soutenance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="defenseDetailsContent">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>



<?php include __DIR__ . "/includes/main_scripts.php"; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
function uploadMemoire(sujetId) {
    document.getElementById('memoireSujetId').value = sujetId;
    document.getElementById('uploadForm').reset();
    document.getElementById('fileError').style.display = 'none';
    new bootstrap.Modal(document.getElementById('uploadMemoireModal')).show();
}

function validateFile(input) {
    const file = input.files[0];
    const fileError = document.getElementById('fileError');
    const submitBtn = document.getElementById('submitBtn');
    
    if (file) {
        // Vérifier le type
        const fileType = file.type;
        if (fileType !== 'application/pdf') {
            fileError.textContent = 'Le fichier doit être au format PDF.';
            fileError.style.display = 'block';
            submitBtn.disabled = true;
            input.value = '';
            return false;
        }
        
        // Vérifier la taille (50MB max)
        const maxSize = 50 * 1024 * 1024; // 50MB en bytes
        if (file.size > maxSize) {
            fileError.textContent = 'Le fichier ne doit pas dépasser 50MB.';
            fileError.style.display = 'block';
            submitBtn.disabled = true;
            input.value = '';
            return false;
        }
        
        // Tout est OK
        fileError.style.display = 'none';
        submitBtn.disabled = false;
        
        // Afficher le nom du fichier
        fileError.textContent = 'Fichier sélectionné: ' + file.name;
        fileError.className = 'text-success mt-2';
        fileError.style.display = 'block';
    }
}

function viewDefenseDetails(soutenanceId) {
    if (!soutenanceId) {
        Swal.fire({
            icon: 'warning',
            title: 'Non disponible',
            text: 'Les détails de soutenance ne sont pas encore disponibles.',
            confirmButtonColor: '#ff9800'
        });
        return;
    }

    const modalContent = document.getElementById('defenseDetailsContent');
    modalContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('defenseDetailsModal')).show();

    fetch('../controller/get_soutenance_details.php?id=' + soutenanceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const soutenance = data.soutenance;
                const dateSoutenance = new Date(soutenance.date_soutenance);
                modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <strong>Date et Lieu:</strong> ${dateSoutenance.toLocaleDateString('fr-FR', {year: 'numeric', month: 'long', day: 'numeric'})} à ${dateSoutenance.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}
                                <br>
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <strong>${soutenance.lieu || 'Lieu à préciser'}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                         <div class="col-12">
                             <h6 class="text-primary mb-3"><i class="fas fa-users me-2"></i>Jury de Soutenance</h6>
                             <div class="list-group">
                                 ${soutenance.lecteurs && soutenance.lecteurs.length > 0 
                                     ? soutenance.lecteurs.map((lecteur, index) => `
                                         <div class="list-group-item">
                                             <div class="d-flex justify-content-between align-items-center">
                                                 <div>
                                                     <h6 class="mb-1">${lecteur.noms}</h6>
                                                     <small class="text-muted">${lecteur.est_premier_lecteur ? '🔴 Premier Lecteur' : '🟢 Deuxième Lecteur'}</small>
                                                 </div>
                                                 <span class="badge bg-info">${lecteur.grade || ''}</span>
                                             </div>
                                         </div>
                                     `).join('')
                                     : '<p class="text-muted">Jury à confirmer</p>'
                                 }
                             </div>
                         </div>
                     </div>
                     <div class="row mt-4">
                         <div class="col-12">
                             <div class="btn-group w-100" role="group">
                                 <button class="btn btn-success" onclick="generateDefensePoster(${soutenanceId})">
                                     <i class="fas fa-file-pdf me-2"></i>Télécharger PDF
                                 </button>
                                 <button class="btn btn-primary" onclick="downloadPosterImage(${soutenanceId})">
                                     <i class="fas fa-image me-2"></i>Télécharger Image
                                 </button>
                             </div>
                         </div>
                     </div>
                    `;
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Erreur lors du chargement des détails'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur de connexion au serveur
                </div>
            `;
        });
}

// Générer l'affiche de soutenance (PDF)
function generateDefensePoster(soutenanceId) {
    if (!soutenanceId) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de générer l\'affiche: données manquantes',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    // Ouvrir le PDF dans un nouvel onglet
    window.open('../controller/generate_defense_poster.php?id=' + soutenanceId, '_blank');
}

// Télécharger l'affiche en image (via html2canvas)
function downloadPosterImage(soutenanceId) {
    if (!soutenanceId) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de générer l\'image: données manquantes',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    Swal.fire({
        title: 'Génération en cours...',
        text: 'Veuillez patienter pendant la création de l\'image',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('../controller/get_defense_poster_html.php?id=' + soutenanceId)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                throw new Error(result.message || 'Erreur lors de la récupération des données');
            }
            
            const data = result.data;
            
            // Créer le conteneur de l'affiche
            const posterContainer = document.createElement('div');
            posterContainer.id = 'posterForCapture';
            posterContainer.style.cssText = 'position:fixed;left:-9999px;top:0;width:794px;height:1123px;background:#fff;font-family:Arial,sans-serif;';
            
            posterContainer.innerHTML = `
                <div style="width:100%;height:100%;position:relative;background:linear-gradient(180deg, #192a56 0%, #192a56 240px, #f5f5fa 240px);">
                    <!-- Header -->
                    <div style="text-align:center;padding-top:25px;color:#fff;">
                        ${data.universite.logo ? `<img src="../${data.universite.logo}" style="width:70px;height:70px;border-radius:50%;background:#fff;padding:5px;object-fit:contain;">` : ''}
                        <div style="font-size:11px;margin-top:10px;text-transform:uppercase;letter-spacing:1px;">${data.universite.ministere}</div>
                        <div style="font-size:18px;font-weight:bold;margin-top:6px;text-transform:uppercase;">${data.universite.nom}</div>
                        ${data.section ? `<div style="font-size:14px;margin-top:6px;">Section : ${data.section}</div>` : ''}
                    </div>
                    
                    <!-- Badge Soutenance -->
                    <div style="background:#d4af37;color:#192a56;padding:10px 35px;border-radius:6px;display:inline-block;position:absolute;left:50%;transform:translateX(-50%);top:195px;font-weight:bold;font-size:14px;">
                        SOUTENANCE DE MÉMOIRE
                    </div>
                    
                    <!-- Photo Card -->
                    <div style="position:absolute;left:50%;transform:translateX(-50%);top:250px;background:#fff;border-radius:10px;padding:12px;box-shadow:0 4px 15px rgba(0,0,0,0.15);text-align:center;width:180px;">
                        ${data.etudiant.photo ? `<img src="../${data.etudiant.photo}" style="width:140px;height:160px;object-fit:cover;border-radius:5px;">` : '<div style="width:140px;height:160px;background:#e0e0e0;border-radius:5px;display:flex;align-items:center;justify-content:center;color:#999;">Photo</div>'}
                        <div style="font-weight:bold;color:#192a56;margin-top:8px;font-size:12px;text-transform:uppercase;">${data.etudiant.noms}</div>
                        <div style="color:#666;font-size:11px;margin-top:4px;">Mat: ${data.etudiant.matricule}</div>
                    </div>
                    
                    <!-- Titre du mémoire -->
                    <div style="position:absolute;top:490px;left:35px;right:35px;text-align:center;">
                        <span style="color:#d4af37;font-size:24px;font-weight:bold;">"</span>
                        <span style="color:#333;font-size:14px;font-style:italic;font-weight:bold;">${data.memoire.titre}</span>
                        <span style="color:#d4af37;font-size:24px;font-weight:bold;">"</span>
                        <div style="color:#40739e;font-size:12px;margin-top:8px;">Spécialisation : ${data.memoire.specialisation}</div>
                    </div>
                    
                    <!-- Date et Lieu -->
                    <div style="position:absolute;top:590px;left:50%;transform:translateX(-50%);display:flex;gap:15px;">
                        <div style="background:#192a56;color:#fff;padding:12px 25px;border-radius:6px;text-align:center;min-width:180px;">
                            <div style="color:#d4af37;font-size:9px;font-weight:bold;margin-bottom:4px;">DATE & HEURE</div>
                            <div style="font-size:12px;font-weight:bold;">${data.soutenance.jour_semaine} ${data.soutenance.date_formatee}</div>
                            <div style="font-size:11px;margin-top:4px;">à ${data.soutenance.heure}</div>
                        </div>
                        <div style="background:#40739e;color:#fff;padding:12px 25px;border-radius:6px;text-align:center;min-width:180px;">
                            <div style="color:#d4af37;font-size:9px;font-weight:bold;margin-bottom:4px;">LIEU</div>
                            <div style="font-size:12px;font-weight:bold;">${data.soutenance.lieu}</div>
                        </div>
                    </div>
                    
                    <!-- Ligne décorative -->
                    <div style="position:absolute;top:690px;left:80px;right:80px;height:2px;background:#d4af37;"></div>
                    
                    <!-- Encadrement et Jury -->
                    <div style="position:absolute;top:710px;left:40px;right:40px;display:flex;justify-content:space-between;">
                        <div style="width:45%;">
                            <div style="color:#192a56;font-weight:bold;font-size:13px;margin-bottom:8px;">ENCADREMENT</div>
                            <div style="color:#333;font-size:12px;">Directeur : ${data.encadrement.directeur_grade ? data.encadrement.directeur_grade + ' ' : ''}${data.encadrement.directeur}</div>
                        </div>
                        <div style="width:45%;">
                            <div style="color:#192a56;font-weight:bold;font-size:13px;margin-bottom:8px;">MEMBRES DU JURY</div>
                            ${data.jury.president ? `<div style="color:#333;font-size:12px;margin-bottom:4px;">Président : ${data.jury.president_grade ? data.jury.president_grade + ' ' : ''}${data.jury.president}</div>` : ''}
                            ${data.jury.secretaire ? `<div style="color:#333;font-size:12px;margin-bottom:4px;">Secrétaire : ${data.jury.secretaire_grade ? data.jury.secretaire_grade + ' ' : ''}${data.jury.secretaire}</div>` : ''}
                            ${data.lecteurs.map(l => `<div style="color:#333;font-size:12px;margin-bottom:4px;">${l.est_premier_lecteur ? '1er' : '2ème'} Lecteur : ${l.grade ? l.grade + ' ' : ''}${l.lecteur_noms}</div>`).join('')}
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style="position:absolute;bottom:0;left:0;right:0;background:#192a56;color:#fff;padding:12px;text-align:center;border-top:3px solid #d4af37;">
                        <div style="font-size:11px;">Vous êtes cordialement invité(e) à assister à cette soutenance</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(posterContainer);
            
            // Attendre le chargement des images
            const images = posterContainer.querySelectorAll('img');
            const imagePromises = Array.from(images).map(img => {
                return new Promise((resolve) => {
                    if (img.complete) resolve();
                    else {
                        img.onload = resolve;
                        img.onerror = resolve;
                    }
                });
            });
            
            return Promise.all(imagePromises).then(() => {
                return html2canvas(posterContainer, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff'
                });
            });
        })
        .then(canvas => {
            // Télécharger l'image
            const link = document.createElement('a');
            link.download = 'affiche_soutenance_' + soutenanceId + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            // Nettoyer
            const poster = document.getElementById('posterForCapture');
            if (poster) poster.remove();
            
            Swal.fire({
                icon: 'success',
                title: 'Image générée',
                text: 'L\'affiche a été téléchargée avec succès',
                timer: 2000,
                showConfirmButton: false
            });
        })
        .catch(error => {
            console.error('Erreur:', error);
            const poster = document.getElementById('posterForCapture');
            if (poster) poster.remove();
            
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.message || 'Impossible de générer l\'image',
                confirmButtonColor: '#dc3545'
            });
        });
}

// Confirmation avant envoi - attendre que Swal soit chargé
function setupUploadFormListener() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm && typeof Swal !== 'undefined') {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            
            Swal.fire({
                title: "Confirmer l'envoi",
                text: "Voulez-vous vraiment envoyer ce mémoire? Vous pourrez le remplacer plus tard si nécessaire.",
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Oui, envoyer",
                cancelButtonText: "Annuler"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Soumettre le formulaire normalement
                    form.submit();
                }
            });
        });
    } else if (!uploadForm) {
        // uploadForm n'existe pas encore, réessayer plus tard
        setTimeout(setupUploadFormListener, 100);
    } else if (typeof Swal === 'undefined') {
        // Swal n'est pas encore chargé, réessayer plus tard
        setTimeout(setupUploadFormListener, 100);
    }
}

// Démarrer après le chargement du DOM
document.addEventListener('DOMContentLoaded', setupUploadFormListener);
</script>

<?php
require_once "footer_student.php";
?>
