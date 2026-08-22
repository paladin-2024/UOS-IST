<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    die("Accès non autorisé");
}

$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : '';
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

if (empty($matricule)) {
    die("Matricule manquant");
}

$db = Connexion::getInstance()->getPDO();

// Récupérer les informations de l'étudiant
$sql = "SELECT e.*, p.\"designationPromotion\" 
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        WHERE e.matricule = :matricule";
$stmt = $db->prepare($sql);
$stmt->execute([':matricule' => $matricule]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die("Étudiant non trouvé");
}

// Récupérer toutes les dettes de l'étudiant
$sql = "SELECT 
            d.*,
            ec.\"designationECUE\",
            d.credits_ecue as credits,  -- Utilisation directe du champ credits_ecue
            s.\"numeroSemestre\",
            aa.designation as annee_academique,
            sess.\"designSession\"
        FROM dette_etudiant d
        JOIN ecue ec ON d.\"ECUE_idECUE\" = ec.\"idECUE\"
        JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
        JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
        JOIN session sess ON d.session_idsession = sess.idsession
        WHERE d.matricule = :matricule
        ORDER BY aa.designation DESC, s.\"numeroSemestre\" ASC";

$stmt = $db->prepare($sql);
$stmt->execute([':matricule' => $matricule]);
$dettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les évaluations de rachat
$evaluations = [];
foreach ($dettes as $dette) {
    $sql = "SELECT * FROM dette_evaluation 
            WHERE id_dette = :dette
            ORDER BY date_evaluation DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':dette' => $dette['id_dette']]);
    $evaluations[$dette['id_dette']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="student-info mb-4">
    <h6>Informations de l'étudiant</h6>
    <table class="table table-sm">
        <tr>
            <td><strong>Matricule:</strong></td>
            <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
            <td><strong>Nom:</strong></td>
            <td><?= htmlspecialchars($etudiant['noms']) ?></td>
        </tr>
        <tr>
            <td><strong>Promotion:</strong></td>
            <td><?= htmlspecialchars($etudiant['designationPromotion']) ?></td>
            <td><strong>Email:</strong></td>
            <td><?= htmlspecialchars($etudiant['adressemail']) ?></td>
        </tr>
    </table>
</div>

<div class="dettes-list">
    <h6>Liste des dettes</h6>
    <?php if (empty($dettes)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Aucune dette enregistrée pour cet étudiant.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Année</th>
                        <th>Semestre</th>
                        <th>ECUE</th>
                        <th>Crédits</th>
                        <th>Note obtenue</th>
                        <th>Session</th>
                        <th>Note rachat</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dettes as $dette): ?>
                        <tr>
                            <td><?= htmlspecialchars($dette['annee_academique']) ?></td>
                            <td>S<?= $dette['numeroSemestre'] ?></td>
                            <td><?= htmlspecialchars($dette['designationECUE']) ?></td>
                            <td><?= $dette['credits'] ?></td>
                            <td><?= number_format($dette['note_obtenue'], 2) ?></td>
                            <td><?= htmlspecialchars($dette['designSession']) ?></td>
                            <td>
                                <?php if ($dette['note_rachat']): ?>
                                    <?= number_format($dette['note_rachat'], 2) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dette['statut'] == 'En cours'): ?>
                                    <span class="badge bg-warning">En cours</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Validée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if (!empty($evaluations[$dette['id_dette']])): ?>
                            <tr>
                                <td colspan="8" class="bg-light">
                                    <small>
                                        <strong>Évaluations de rachat:</strong>
                                        <?php foreach ($evaluations[$dette['id_dette']] as $eval): ?>
                                            <?= $eval['type_evaluation'] ?>: <?= number_format($eval['note'], 2) ?> 
                                            (<?= date('d/m/Y', strtotime($eval['date_evaluation'])) ?>)
                                        <?php endforeach; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <h6>Résumé</h6>
            <?php
            $totalDettes = count($dettes);
            $dettesEnCours = array_filter($dettes, function($d) { return $d['statut'] == 'En cours'; });
            $dettesValidees = array_filter($dettes, function($d) { return $d['statut'] == 'Validée'; });
            $totalCredits = array_sum(array_column($dettes, 'credits'));
            ?>
            <ul>
                <li>Total des dettes: <strong><?= $totalDettes ?></strong></li>
                <li>Dettes en cours: <strong><?= count($dettesEnCours) ?></strong></li>
                <li>Dettes validées: <strong><?= count($dettesValidees) ?></strong></li>
                <li>Total des crédits en dette: <strong><?= $totalCredits ?></strong></li>
            </ul>
        </div>
    <?php endif; ?>
</div>