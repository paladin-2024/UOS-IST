<?php
session_start();
header('Content-Type: application/json');

// Vérifier les droits d'accès
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    if (!isset($_GET['import_id']) || !is_numeric($_GET['import_id'])) {
        throw new Exception('ID d\'import non fourni ou invalide.');
    }

    require_once '../models/GrilleAncienne.php';
    $grilleAncienne = new GrilleAncienne();
    
    $importId = intval($_GET['import_id']);
    
    // Récupérer les informations de l'import
    $import = $grilleAncienne->getImportById($importId);
    if (!$import) {
        throw new Exception('Import non trouvé.');
    }

    // Récupérer les données associées
    $ues = $grilleAncienne->getUEsByImport($importId);
    $etudiants = $grilleAncienne->getEtudiantsByImport($importId);

    // Générer le HTML pour l'affichage des détails
    $html = generateDetailsHTML($import, $ues, $etudiants, $grilleAncienne);

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Générer le HTML pour l'affichage des détails
 */
function generateDetailsHTML($import, $ues, $etudiants, $grilleAncienne) {
    ob_start();
    ?>
    
    <div class="row">
        <div class="col-md-6">
            <h6><i class="bi bi-info-circle me-2"></i>Informations générales</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <th>Année Académique</th>
                    <td><?= htmlspecialchars($import['annee_academique']) ?></td>
                </tr>
                <tr>
                    <th>Session</th>
                    <td>
                        <span class="badge bg-<?= $import['session'] === 'principale' ? 'primary' : 'warning' ?>">
                            <?= htmlspecialchars($import['session']) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Semestre</th>
                    <td><?= htmlspecialchars($import['semestre']) ?></td>
                </tr>
                <tr>
                    <th>Promotion</th>
                    <td><?= htmlspecialchars($import['promotion']) ?></td>
                </tr>
                <tr>
                    <th>Fichier origine</th>
                    <td><?= htmlspecialchars($import['fichier_origine']) ?></td>
                </tr>
                <tr>
                    <th>Date d'import</th>
                    <td><?= date('d/m/Y H:i', strtotime($import['date_import'])) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-md-6">
            <h6><i class="bi bi-bar-chart me-2"></i>Statistiques</h6>
            <div class="row text-center">
                <div class="col-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4><?= $import['nombre_etudiants'] ?></h4>
                            <small>Étudiants</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h4><?= $import['nombre_ues'] ?></h4>
                            <small>UEs</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h4><?= $import['nombre_ecues'] ?></h4>
                            <small>ECUEs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <h6><i class="bi bi-book me-2"></i>Unités d'Enseignement (<?= count($ues) ?>)</h6>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Code</th>
                            <th>Désignation</th>
                            <th>Crédits</th>
                            <th>ECUEs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ues as $ue): ?>
                            <?php 
                            $ecues = $grilleAncienne->getECUEsByUE($ue['id']);
                            ?>
                            <tr>
                                <td><code><?= htmlspecialchars($ue['code_ue']) ?></code></td>
                                <td><?= htmlspecialchars($ue['designation_ue']) ?></td>
                                <td><?= $ue['credits'] ?></td>
                                <td>
                                    <?php if (!empty($ecues)): ?>
                                        <small>
                                            <?php foreach ($ecues as $ecue): ?>
                                                <span class="badge bg-secondary me-1">
                                                    <?= htmlspecialchars($ecue['designation_ecue']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune ECUE</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <h6><i class="bi bi-people me-2"></i>Étudiants (<?= count($etudiants) ?>)</h6>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Matricule</th>
                            <th>Noms</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): ?>
                            <?php 
                            $notes = $grilleAncienne->getNotesByEtudiant($etudiant['id']);
                            $resultats = $grilleAncienne->getResultatsByEtudiant($etudiant['id'], 'annuel');
                            $moyenne = !empty($resultats) ? $resultats[0]['moyenne'] : null;
                            ?>
                            <tr>
                                <td><code><?= htmlspecialchars($etudiant['matricule']) ?></code></td>
                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                <td>
                                    <small class="text-muted"><?= count($notes) ?> notes</small>
                                </td>
                                <td>
                                    <?php if ($moyenne !== null): ?>
                                        <span class="badge bg-<?= $moyenne >= 10 ? 'success' : 'danger' ?>">
                                            <?= number_format($moyenne, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($ues) && !empty($etudiants)): ?>
    <hr>
    <div class="row">
        <div class="col-12">
            <h6><i class="bi bi-table me-2"></i>Aperçu de la grille de notes</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-primary">
                        <tr>
                            <th>Matricule</th>
                            <th>Noms</th>
                            <?php foreach (array_slice($ues, 0, 6) as $ue): ?>
                                <th class="text-center"><?= htmlspecialchars($ue['code_ue']) ?></th>
                            <?php endforeach; ?>
                            <?php if (count($ues) > 6): ?>
                                <th class="text-center">...</th>
                            <?php endif; ?>
                            <th class="text-center">Moyenne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($etudiants, 0, 10) as $etudiant): ?>
                            <?php 
                            $notes = $grilleAncienne->getNotesByEtudiant($etudiant['id']);
                            $notesByUE = [];
                            foreach ($notes as $note) {
                                $notesByUE[$note['code_ue']] = $note['note_finale'];
                            }
                            $resultats = $grilleAncienne->getResultatsByEtudiant($etudiant['id'], 'annuel');
                            $moyenne = !empty($resultats) ? $resultats[0]['moyenne'] : null;
                            ?>
                            <tr>
                                <td><small><?= htmlspecialchars($etudiant['matricule']) ?></small></td>
                                <td><small><?= htmlspecialchars($etudiant['noms']) ?></small></td>
                                <?php foreach (array_slice($ues, 0, 6) as $ue): ?>
                                    <td class="text-center">
                                        <?php if (isset($notesByUE[$ue['code_ue']])): ?>
                                            <small><?= number_format($notesByUE[$ue['code_ue']], 1) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if (count($ues) > 6): ?>
                                    <td class="text-center"><small>...</small></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <?php if ($moyenne !== null): ?>
                                        <small class="badge bg-<?= $moyenne >= 10 ? 'success' : 'danger' ?>">
                                            <?= number_format($moyenne, 2) ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($etudiants) > 10): ?>
                            <tr>
                                <td colspan="<?= 3 + min(6, count($ues)) + (count($ues) > 6 ? 1 : 0) ?>" class="text-center">
                                    <small class="text-muted">... et <?= count($etudiants) - 10 ?> autres étudiants</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
?>
