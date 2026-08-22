<?php
require_once "head_student.php";

// Debug: Vérifier l'ID de l'étudiant
error_log("DEBUG stage.php - Student ID from session: " . $studentId);
error_log("DEBUG stage.php - Session data: " . print_r($_SESSION, true));

// Récupération des stages de l'étudiant
$stages = [];
try {
    // Appeler directement la méthode sans le paramètre année car un stage n'est pas lié à une année
    $stages = $etudiantModel->getStudentStages($studentId, null);
    error_log("DEBUG stage.php - Stages found: " . count($stages));
    error_log("DEBUG stage.php - Stages data: " . print_r($stages, true));
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des stages: " . $e->getMessage());
    $stages = [];
}

// Set page title for mobile header
$pageTitle = 'Mes Stages';
$currentPage = 'stage';
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary">
                <i class="fas fa-building me-2"></i>Mes Stages
            </h4>
        </div>

        <?php if (empty($stages)): ?>
            <div class="alert alert-info d-flex align-items-center">
                <i class="fas fa-info-circle me-3 fs-4"></i>
                <div>
                    <h5>Aucun stage assigné pour le moment</h5>
                    <p class="mb-0">Votre stage sera affiché ici une fois qu'il vous sera assigné par l'administration.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($stages as $stage): 
                    $hasReport = !empty($stage['rapport_path']);
                    $dateDebut = $stage['date_debut'] ? date('d/m/Y', strtotime($stage['date_debut'])) : 'Non définie';
                    $dateFin = $stage['date_fin'] ? date('d/m/Y', strtotime($stage['date_fin'])) : 'Non définie';
                ?>
                <div class="col-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-building me-2"></i>Mon Stage
                                </h5>
                                <?php if ($hasReport): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>Rapport déposé
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
                                    <h6 class="text-muted mb-3">Informations du stage</h6>
                                    <p><i class="fas fa-map-marker-alt text-primary me-2"></i><strong>Lieu:</strong> <?php echo htmlspecialchars($stage['lieu_stage'] ?? 'Non défini'); ?></p>
                                    <p><i class="fas fa-calendar-alt text-primary me-2"></i><strong>Date de début:</strong> <?php echo $dateDebut; ?></p>
                                    <p><i class="fas fa-calendar-check text-primary me-2"></i><strong>Date de fin:</strong> <?php echo $dateFin; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">Encadrement</h6>
                                    <p><i class="fas fa-user-tie text-primary me-2"></i><strong>Encadreur:</strong> <?php echo htmlspecialchars($stage['encadreur_nom'] ?? 'Non assigné'); ?></p>
                                    <?php if (!empty($stage['lecteur_nom'])): ?>
                                    <p><i class="fas fa-user-check text-primary me-2"></i><strong>Lecteur:</strong> <?php echo htmlspecialchars($stage['lecteur_nom']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($stage['cote_lecteur'])): ?>
                                    <p><i class="fas fa-star text-primary me-2"></i><strong>Cote Lecteur:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($stage['cote_lecteur']); ?>/20</span></p>
                                    <?php endif; ?>
                                    <?php if ($estPromotionTerminale && !empty($stage['cote_entreprise'])): ?>
                                    <p><i class="fas fa-building text-primary me-2"></i><strong>Cote Entreprise:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($stage['cote_entreprise']); ?>/20</span></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Actions</h6>
                                </div>
                                <?php if ($hasReport): ?>
                                <div class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>Rapport envoyé
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <?php if (!$hasReport): ?>
                                    <?php
                                    // Pour l'instant, on permet l'upload sans vérifier les frais
                                    // Vous pouvez réactiver la vérification des frais si nécessaire
                                    $canUpload = true;
                                    ?>
                                    
                                    <?php if ($canUpload): ?>
                                    <button class="btn btn-primary" onclick="uploadReport(<?php echo $stage['idstage']; ?>)">
                                        <i class="fas fa-upload me-2"></i>Envoyer mon rapport (PDF)
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-secondary" disabled title="Vous devez d'abord payer les frais de stage">
                                        <i class="fas fa-lock me-2"></i>Paiement requis pour envoyer le rapport
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-success" onclick="uploadReport(<?php echo $stage['idstage']; ?>)">
                                        <i class="fas fa-sync-alt me-2"></i>Remplacer le rapport
                                    </button>
                                    <a href="<?php echo htmlspecialchars($stage['rapport_path']); ?>" class="btn btn-info" target="_blank">
                                        <i class="fas fa-file-pdf me-2"></i>Voir mon rapport
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$hasReport): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Vous devez soumettre votre rapport de stage avant la date limite. 
                                Le rapport doit être au format PDF (max 10MB).
                            </div>
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

<!-- Modal for uploading report -->
<div class="modal fade" id="uploadReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>Envoyer mon rapport de stage
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../controller/upload_stage_report.php" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="stage_id" id="reportStageId">
                    <input type="hidden" name="uploadReportBtn" value="1">
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instructions</h6>
                        <ul class="mb-0">
                            <li>Le rapport doit être au format PDF uniquement</li>
                            <li>Taille maximale : 10 MB</li>
                            <li>Assurez-vous que le document est complet avant l'envoi</li>
                            <li>Vous pourrez remplacer le rapport après l'envoi si nécessaire</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reportFile" class="form-label fw-bold">
                            <i class="fas fa-file-pdf text-danger me-2"></i>Sélectionner votre rapport (PDF)
                        </label>
                        <input type="file" class="form-control form-control-lg" name="report_file" id="reportFile" accept=".pdf" required onchange="validateFile(this)">
                        <div class="form-text">Format accepté : PDF uniquement (max 10MB)</div>
                        <div id="fileError" class="text-danger mt-2" style="display:none;"></div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="uploadReportBtn" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Envoyer mon rapport
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

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Succès!',
        text: '<?php echo $_SESSION['success_message']; ?>',
        confirmButtonColor: '#28a745'
    });
</script>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Erreur!',
        text: '<?php echo $_SESSION['error_message']; ?>',
        confirmButtonColor: '#dc3545'
    });
</script>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<script>
function uploadReport(stageId) {
    document.getElementById('reportStageId').value = stageId;
    document.getElementById('uploadForm').reset();
    document.getElementById('fileError').style.display = 'none';
    new bootstrap.Modal(document.getElementById('uploadReportModal')).show();
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
        
        // Vérifier la taille (10MB max)
        const maxSize = 10 * 1024 * 1024; // 10MB en bytes
        if (file.size > maxSize) {
            fileError.textContent = 'Le fichier ne doit pas dépasser 10MB.';
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

// Confirmation avant envoi - Version simplifiée
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    
    Swal.fire({
        title: 'Confirmer l\'envoi',
        text: 'Voulez-vous vraiment envoyer ce rapport? Vous pourrez le remplacer plus tard si nécessaire.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, envoyer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Soumettre le formulaire normalement
            form.submit();
        }
    });
});
</script>

<?php
require_once "footer_student.php";
?>