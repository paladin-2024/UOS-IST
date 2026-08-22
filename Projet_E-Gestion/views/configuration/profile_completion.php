<?php
include "./views/include/header.php";
$universite = new Universite();

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$promotionId = isset($_GET['promotionId']) ? $_GET['promotionId'] : '';
$anneeId = isset($_GET['anneeId']) ? $_GET['anneeId'] : '';
$completionStatus = isset($_GET['completionStatus']) ? $_GET['completionStatus'] : '';

// Function to get student profile completion data
function getStudentProfileCompletionData($universite, $search = '', $promotionId = '', $anneeId = '') {
    // Get students with filters
    $students = $universite->getEtudiantsByPromotionAndNom($promotionId, $anneeId,$search);
    
    $completionData = [];
    
    foreach ($students as $student) {
        // Calculate completion percentage based on filled fields
        $totalFields = 9; // Total number of profile fields we're tracking
        $completedFields = 0;
        
        // Count completed fields
        if (!empty($student['noms'])) $completedFields++;
        if (!empty($student['lieuNaissance'])) $completedFields++;
        if (!empty($student['dateNaissance'])) $completedFields++;
        if (!empty($student['adressemail'])) $completedFields++;
        if (!empty($student['telephone'])) $completedFields++;
        if (!empty($student['adresse'])) $completedFields++;
        if (!empty($student['personne_contact'])) $completedFields++;
        if (!empty($student['telephone_contact'])) $completedFields++;
        if (!empty($student['photo'])) $completedFields++;
        
        $completionPercentage = ($completedFields / $totalFields) * 100;
        
        // Add completion status
        $status = '';
        if ($completionPercentage == 0) {
            $status = 'Non commencé';
        } elseif ($completionPercentage < 50) {
            $status = 'En cours';
        } elseif ($completionPercentage < 100) {
            $status = 'Presque complet';
        } else {
            $status = 'Complet';
        }
        
        $student['completedFields'] = $completedFields;
        $student['totalFields'] = $totalFields;
        $student['completionPercentage'] = $completionPercentage;
        $student['completionStatus'] = $status;
        
        $completionData[] = $student;
    }
    
    // Filter by completion status if requested
    if (!empty($completionStatus)) {
        $completionData = array_filter($completionData, function($student) use ($completionStatus) {
            return $student['completionStatus'] === $completionStatus;
        });
    }
    
    return $completionData;
}

// Get all available promotions for filter
$promotions = $universite->getPromotions();

// Get all academic years for filter
$academicYears = $universite->getAcademicYears();

// Get student profile completion data
$studentCompletionData = getStudentProfileCompletionData($universite, $search, $promotionId, $anneeId);

// Calculate summary statistics
$totalStudents = count($studentCompletionData);
$completeCount = 0;
$partialCount = 0;
$notStartedCount = 0;

foreach ($studentCompletionData as $student) {
    if ($student['completionStatus'] === 'Complet') {
        $completeCount++;
    } elseif ($student['completionStatus'] === 'Non commencé') {
        $notStartedCount++;
    } else {
        $partialCount++;
    }
}

$completePercentage = $totalStudents > 0 ? round(($completeCount / $totalStudents) * 100) : 0;
$partialPercentage = $totalStudents > 0 ? round(($partialCount / $totalStudents) * 100) : 0;
$notStartedPercentage = $totalStudents > 0 ? round(($notStartedCount / $totalStudents) * 100) : 0;
?>

<!-- Main Content -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD - COMPLÉTION DES PROFILS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Configuration</li>
                <li class="breadcrumb-item active">Complétion des profils</li>
            </ol>
        </nav>
    </div>

    <!-- Dashboard Section -->
    <section class="section dashboard">
        <div class="row">
            <!-- Left side columns -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Statistics Cards -->
                    <div class="col-xxl-3 col-md-3">
                        <div class="card info-card sales-card">
                            <div class="card-body">
                                <h5 class="card-title">Total des étudiants</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $totalStudents ?></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-3">
                        <div class="card info-card revenue-card">
                            <div class="card-body">
                                <h5 class="card-title">Profils complets</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $completeCount ?></h6>
                                        <span class="text-success small pt-1 fw-bold"><?= $completePercentage ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-3">
                        <div class="card info-card customers-card">
                            <div class="card-body">
                                <h5 class="card-title">Profils partiels</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $partialCount ?></h6>
                                        <span class="text-warning small pt-1 fw-bold"><?= $partialPercentage ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-3 col-md-3">
                        <div class="card info-card revenue-card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Non commencés</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= $notStartedCount ?></h6>
                                        <span class="text-danger small pt-1 fw-bold"><?= $notStartedPercentage ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Overview Bar -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Vue d'ensemble des profils</h5>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completePercentage ?>%" aria-valuenow="<?= $completePercentage ?>" aria-valuemin="0" aria-valuemax="100"><?= $completePercentage ?>%</div>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $partialPercentage ?>%" aria-valuenow="<?= $partialPercentage ?>" aria-valuemin="0" aria-valuemax="100"><?= $partialPercentage ?>%</div>
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $notStartedPercentage ?>%" aria-valuenow="<?= $notStartedPercentage ?>" aria-valuemin="0" aria-valuemax="100"><?= $notStartedPercentage ?>%</div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small><i class="bi bi-square-fill text-success"></i> Complet</small>
                                    <small><i class="bi bi-square-fill text-warning"></i> Partiel</small>
                                    <small><i class="bi bi-square-fill text-danger"></i> Non commencé</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Table -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Suivi de la complétion des profils étudiants</h5>
                                
                                <!-- Filters -->
                                <form method="GET" action="" class="row g-3 mb-4">
                                    <input type="hidden" name="view" value="configuration/profile_completion">
                                    
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un étudiant...">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <select name="promotionId" class="form-select">
                                            <option value="">Toutes les promotions</option>
                                            <?php foreach ($promotions as $promotion): ?>
                                                <option value="<?= $promotion['idpromotion'] ?>" <?= $promotionId == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="anneeId" class="form-select">
                                            <option value="">Toutes les années</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId == $year['idannee_acad'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($year['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <select name="completionStatus" class="form-select">
                                            <option value="">Tous les statuts</option>
                                            <option value="Complet" <?= $completionStatus === 'Complet' ? 'selected' : '' ?>>Complet</option>
                                            <option value="Presque complet" <?= $completionStatus === 'Presque complet' ? 'selected' : '' ?>>Presque complet</option>
                                            <option value="En cours" <?= $completionStatus === 'En cours' ? 'selected' : '' ?>>En cours</option>
                                            <option value="Non commencé" <?= $completionStatus === 'Non commencé' ? 'selected' : '' ?>>Non commencé</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                                    </div>
                                </form>
                                
                                <!-- Table of students -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Matricule</th>
                                                <th scope="col">Nom</th>
                                                <th scope="col">Promotion</th>
                                                <th scope="col">Année</th>
                                                <th scope="col">Champs remplis</th>
                                                <th scope="col">Pourcentage</th>
                                                <th scope="col">Statut</th>
                                                <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (empty($studentCompletionData)): 
                                            ?>
                                                <tr>
                                                <td colspan="9" class="text-center">Aucun étudiant trouvé avec les critères de recherche actuels.</td>
                                                </tr>
                                            <?php 
                                            else:
                                                $i = 1;
                                                foreach ($studentCompletionData as $student):
                                                    // Define progress bar class based on completion percentage
                                                    if ($student['completionPercentage'] == 100) {
                                                        $progressClass = 'bg-success';
                                                    } elseif ($student['completionPercentage'] >= 50) {
                                                        $progressClass = 'bg-info';
                                                    } elseif ($student['completionPercentage'] > 0) {
                                                        $progressClass = 'bg-warning';
                                                    } else {
                                                        $progressClass = 'bg-danger';
                                                    }
                                            ?>
                                                <tr>
                                                    <td><?= $i ?></td>
                                                    <td><?= htmlspecialchars($student['matricule']) ?></td>
                                                    <td><?= htmlspecialchars($student['noms']) ?></td>
                                                    <td><?= htmlspecialchars($student['designationPromotion'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($student['annee'] ?? 'N/A') ?></td>
                                                    <td><?= $student['completedFields'] ?>/<?= $student['totalFields'] ?></td>
                                                    <td>
                                                        <div class="progress">
                                                            <div class="progress-bar <?= $progressClass ?>" role="progressbar" 
                                                                style="width: <?= $student['completionPercentage'] ?>%" 
                                                                aria-valuenow="<?= $student['completionPercentage'] ?>" 
                                                                aria-valuemin="0" aria-valuemax="100">
                                                                <?= round($student['completionPercentage']) ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($student['completionStatus'] === 'Complet'): ?>
                                                            <span class="badge bg-success"><?= $student['completionStatus'] ?></span>
                                                        <?php elseif ($student['completionStatus'] === 'Presque complet'): ?>
                                                            <span class="badge bg-info"><?= $student['completionStatus'] ?></span>
                                                        <?php elseif ($student['completionStatus'] === 'En cours'): ?>
                                                            <span class="badge bg-warning"><?= $student['completionStatus'] ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><?= $student['completionStatus'] ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" onclick="viewProfileDetails(<?= $student['idetudiant'] ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning" onclick="sendReminder(<?= $student['idetudiant'] ?>)">
                                                            <i class="bi bi-bell"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php
                                                $i++;
                                                endforeach;
                                            endif;
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Export buttons -->
                                <div class="mt-3">
                                    <button class="btn btn-success" onclick="exportToExcel()">
                                        <i class="bi bi-file-earmark-excel"></i> Exporter vers Excel
                                    </button>
                                    <button class="btn btn-danger" onclick="exportToPDF()">
                                        <i class="bi bi-file-earmark-pdf"></i> Exporter vers PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Missing Fields Analysis -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Analyse des champs manquants</h5>
                                <canvas id="missingFieldsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Completion Trend -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Statuts de complétion par promotion</h5>
                                <canvas id="completionByPromotionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Profile Details Modal -->
<div class="modal fade" id="profileDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du profil étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="profileDetailsContent">
                <!-- Content will be loaded dynamically -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for interactive features -->
<script>
    // Function to view profile details
    function viewProfileDetails(studentId) {
        const modal = new bootstrap.Modal(document.getElementById('profileDetailsModal'));
        const contentDiv = document.getElementById('profileDetailsContent');
        
        // Show loading spinner
        contentDiv.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;
        
        modal.show();
        
        // In a real implementation, you would fetch the student's profile details via AJAX
        // For now, we'll simulate this with a timeout
        setTimeout(() => {
            // Replace this with actual AJAX call in production
            fetch(`controller/get_student_profile.php?id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        contentDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Format the profile details
                    let html = `
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                ${data.photo ? 
                                    `<img src="${data.photo}" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">` : 
                                    `<div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; margin: 0 auto;">
                                        <i class="bi bi-person-fill" style="font-size: 4rem;"></i>
                                    </div>`
                                }
                                <h5 class="mt-2">${data.noms}</h5>
                                <p class="text-muted">${data.matricule}</p>
                            </div>
                            <div class="col-md-8">
                                <h6>Informations personnelles</h6>
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>Lieu de naissance</th>
                                            <td>${data.lieuNaissance || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Date de naissance</th>
                                            <td>${data.dateNaissance || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td>${data.adressemail || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Téléphone</th>
                                            <td>${data.telephone || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Adresse</th>
                                            <td>${data.adresse || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Personne à contacter</th>
                                            <td>${data.personne_contact || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Téléphone de contact</th>
                                            <td>${data.telephone_contact || '<span class="text-danger">Non renseigné</span>'}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    contentDiv.innerHTML = html;
                })
                .catch(error => {
                    contentDiv.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement des données: ${error.message}</div>`;
                });
        }, 1000);
    }
    
   // Function to send reminder
function sendReminder(studentId) {
    Swal.fire({
        title: 'Envoyer un rappel',
        text: "Souhaitez-vous envoyer un rappel à cet étudiant pour compléter son profil?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, envoyer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Send the reminder via AJAX
            fetch('controller/send_profile_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + studentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire(
                        'Erreur!',
                        data.error,
                        'error'
                    );
                } else {
                    Swal.fire(
                        'Rappel envoyé!',
                        data.message || 'L\'étudiant a été notifié avec succès.',
                        'success'
                    );
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire(
                    'Erreur!',
                    'Une erreur est survenue lors de l\'envoi du rappel.',
                    'error'
                );
            });
        }
    });
}

    
    // Function to export to Excel
    function exportToExcel() {
        window.location.href = 'controller/export_profile_completion.php?format=excel';
    }
    
    // Function to export to PDF
    function exportToPDF() {
        window.location.href = 'controller/export_profile_completion.php?format=pdf';
    }

    // Charts initialization (using Chart.js)
    document.addEventListener('DOMContentLoaded', function() {
    // Chart for missing fields analysis
    const missingFieldsCtx = document.getElementById('missingFieldsChart').getContext('2d');
    let missingFieldsChart;

    // Chart for completion by promotion
    const completionByPromotionCtx = document.getElementById('completionByPromotionChart').getContext('2d');
    let completionByPromotionChart;
    
    // Load missing fields data
    fetch('controller/get_profile_completion_stats.php?action=missing_fields')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error loading missing fields data:', data.error);
                return;
            }
            
            missingFieldsChart = new Chart(missingFieldsCtx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Nombre d\'étudiants avec champs manquants',
                        data: data.data,
                        backgroundColor: 'rgba(255, 99, 132, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error fetching missing fields data:', error);
        });
    
    // Load completion status data
    fetch('controller/get_profile_completion_stats.php?action=completion_status')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error loading completion status data:', data.error);
                return;
            }
            
            completionByPromotionChart = new Chart(completionByPromotionCtx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Statut de complétion',
                        data: data.data,
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',  // vert - complet
                            'rgba(23, 162, 184, 0.8)', // bleu - presque complet
                            'rgba(255, 193, 7, 0.8)',  // jaune - en cours
                            'rgba(220, 53, 69, 0.8)'   // rouge - non commencé
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error fetching completion status data:', error);
        });
});
</script>

<?php include "./views/include/footer.php"; ?>
