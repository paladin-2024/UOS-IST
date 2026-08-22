<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../../connexion');
    exit();
}

require_once dirname(dirname(__DIR__)) . '/models/GrilleAncienne.php';

$grilleAncienne = new GrilleAncienne();

// Récupérer l'ID de l'import
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

if (!$importId) {
    // Si pas d'ID spécifique, afficher tous les imports
    $imports = $grilleAncienne->getImports();
} else {
    // Récupérer les détails de l'import et les étudiants
    $importDetails = $grilleAncienne->getImportDetails($importId);
    $etudiants = $grilleAncienne->getEtudiantsByImport($importId);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étudiants - Grilles Anciennes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .import-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s;
        }
        
        .import-card:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .student-actions {
            display: flex;
            gap: 5px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
        }
        
        .stats-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include dirname(dirname(__DIR__)) . '/includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <?php if (!$importId): ?>
        <!-- Liste des imports disponibles -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-folder-open"></i> Sélectionner un import</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($imports as $import): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card import-card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($import['promotion']); ?>
                                        </h5>
                                        <p class="card-text">
                                            <strong>Année:</strong> <?php echo htmlspecialchars($import['annee_academique']); ?><br>
                                            <strong>Session:</strong> <?php echo htmlspecialchars($import['session']); ?><br>
                                            <strong>Semestre:</strong> <?php echo $import['semestre'] ?: 'Année complète'; ?><br>
                                            <strong>Date:</strong> <?php echo date('d/m/Y', strtotime($import['date_import'])); ?>
                                        </p>
                                        <a href="?import_id=<?php echo $import['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-users"></i> Voir les étudiants
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($imports)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Aucun import disponible.
                                    <a href="import.php" class="alert-link">Importer une grille</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Détails d'un import spécifique -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4>
                                <i class="fas fa-graduation-cap"></i> 
                                <?php echo htmlspecialchars($importDetails['promotion']); ?>
                            </h4>
                            <a href="liste_etudiants.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3><?php echo count($etudiants); ?></h3>
                                    <p>Étudiants</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Année académique:</strong><br>
                                <?php echo htmlspecialchars($importDetails['annee_academique']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Session:</strong><br>
                                <?php echo htmlspecialchars($importDetails['session']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Semestre:</strong><br>
                                <?php echo $importDetails['semestre'] ?: 'Année complète'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions globales -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="btn-group" role="group">
                    <button class="btn btn-success" onclick="generateAllBulletins()">
                        <i class="fas fa-file-pdf"></i> Générer toutes les fiches
                    </button>
                    <button class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Exporter en Excel
                    </button>
                    <button class="btn btn-info" onclick="printPreview()">
                        <i class="fas fa-print"></i> Aperçu impression
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Liste des étudiants -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Liste des étudiants</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>#</th>
                                        <th>Matricule</th>
                                        <th>Nom complet</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($etudiants as $index => $etudiant): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input student-check" 
                                                   value="<?php echo $etudiant['matricule']; ?>">
                                        </td>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($etudiant['matricule']); ?></td>
                                        <td><?php echo htmlspecialchars($etudiant['noms']); ?></td>
                                        <td>
                                            <div class="student-actions">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="viewStudent('<?php echo $etudiant['matricule']; ?>')"
                                                        title="Voir les détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="fiche_validation_ancienne.php?import_id=<?php echo $importId; ?>&matricule=<?php echo $etudiant['matricule']; ?>" 
                                                   class="btn btn-sm btn-success"
                                                   target="_blank"
                                                   title="Générer la fiche">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <button class="btn btn-sm btn-primary"
                                                        onclick="editStudent('<?php echo $etudiant['matricule']; ?>')"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal pour voir les détails d'un étudiant -->
    <div class="modal fade" id="studentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'étudiant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetails">
                    <!-- Contenu chargé dynamiquement -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="btnGenerateFiche">
                        <i class="fas fa-file-pdf"></i> Générer la fiche
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialiser DataTable
            $('#studentsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                },
                pageLength: 25,
                order: [[2, 'asc']]
            });
            
            // Sélectionner/désélectionner tout
            $('#selectAll').on('change', function() {
                $('.student-check').prop('checked', this.checked);
            });
        });
        
        function viewStudent(matricule) {
            // Charger les détails de l'étudiant
            $('#studentModal').modal('show');
            $('#studentDetails').html('<div class="text-center"><div class="spinner-border"></div></div>');
            
            // Ici, vous pouvez faire un appel AJAX pour récupérer les détails
            // Pour l'instant, on affiche juste un message
            setTimeout(() => {
                $('#studentDetails').html(`
                    <div class="alert alert-info">
                        <strong>Matricule:</strong> ${matricule}<br>
                        <p>Les détails complets seront chargés ici...</p>
                    </div>
                `);
                
                $('#btnGenerateFiche').attr('onclick', `generateFiche('${matricule}')`);
            }, 500);
        }
        
        function editStudent(matricule) {
            alert('Fonction d\'édition à implémenter pour: ' + matricule);
        }
        
        function generateFiche(matricule) {
            const importId = <?php echo $importId ?? 0; ?>;
            window.open(`fiche_validation_ancienne.php?import_id=${importId}&matricule=${matricule}`, '_blank');
        }
        
        function generateAllBulletins() {
            const selected = $('.student-check:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selected.length === 0) {
                alert('Veuillez sélectionner au moins un étudiant');
                return;
            }
            
            if (confirm(`Générer les fiches pour ${selected.length} étudiant(s) ?`)) {
                // Générer les fiches une par une
                selected.forEach(matricule => {
                    generateFiche(matricule);
                });
            }
        }
        
        function exportToExcel() {
            const importId = <?php echo $importId ?? 0; ?>;
            window.location.href = `export_grille_ancienne.php?import_id=${importId}`;
        }
        
        function printPreview() {
            window.print();
        }
    </script>
</body>
</html>