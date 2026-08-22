<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

$configQuery = $connexion->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$configResult = $configQuery->fetch(PDO::FETCH_ASSOC);
$creditHeure = $configResult && isset($configResult['credit_heure']) ? (float)$configResult['credit_heure'] : 25;

// Récupérer l'ID de l'étudiant depuis l'URL
$idEtudiant = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idEtudiant <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Identifiant d\'étudiant non spécifié ou invalide.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Récupérer les informations de base de l'étudiant
$stmt = $connexion->prepare("
    SELECT e.*, p.designationPromotion, p.idpromotion
    FROM etudiant e
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    WHERE e.idetudiant = ?
");
$stmt->execute([$idEtudiant]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Étudiant non trouvé dans la base de données.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Récupérer l'historique des promotions de l'étudiant
$stmt = $connexion->prepare("
    SELECT p.designationPromotion, p.idpromotion, aa.designation as annee_academique, 
           e.dateEnregistrement, e.annee_acad_idannee_acad
    FROM etudiant_historique eh
    JOIN promotion p ON eh.idpromotion = p.idpromotion
    JOIN etudiant e ON eh.idetudiant = e.idetudiant
    JOIN annee_acad aa ON eh.idannee_acad = aa.idannee_acad
    WHERE eh.idetudiant = ?
    ORDER BY aa.idannee_acad DESC
");
$stmt->execute([$idEtudiant]);
$historiquePromotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique actuelle
$stmt = $connexion->query("SELECT * FROM annee_acad WHERE dateCreation = (SELECT MAX(dateCreation) FROM annee_acad)");
$anneeActuelle = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer le matricule de l'étudiant
$matricule = $etudiant['matricule'];

// Récupérer les moyennes par année académique
$stmt = $connexion->prepare("
    SELECT cg.*, aa.designation as annee_academique, s.description as session_nom,
           e.designationECUE, ue.designationUE, sem.numeroSemestre
    FROM cotes_grille cg
    JOIN annee_acad aa ON cg.annee_acad_id = aa.idannee_acad
    JOIN session s ON cg.session_idsession = s.idsession
    JOIN ecue e ON cg.ECUE_idECUE = e.idECUE
    JOIN ue ON e.UE_idUE = ue.idUE
    JOIN semestre sem ON ue.semestre_idsemestre = sem.idsemestre
    WHERE cg.matricule = ?
    ORDER BY aa.idannee_acad DESC, sem.numeroSemestre ASC, ue.designationUE ASC
");
$stmt->execute([$matricule]);
$moyennes = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Récupérer les UE avec moyenne pondérée (même logique que grille_notes.php)
// 1) Prendre la meilleure note (MAX MF) par ECUE toutes sessions confondues
// 2) Comparer le nb d'ECUEs avec note au nb total d'ECUEs de l'UE
// 3) Si une ECUE manque de note → UE non validée
$stmt = $connexion->prepare("
    SELECT ue.idUE, ue.designationUE, sem.numeroSemestre, p.designationPromotion,
           bn.annee_acad_id,
           aa.designation as annee_academique,
           SUM(bn.best_mf * (ec.CMI + ec.TD + ec.TP)) / NULLIF(SUM(ec.CMI + ec.TD + ec.TP), 0) as moyenne_ue,
           SUM(ec.CMI + ec.TD + ec.TP) / ? as credits_ue,
           (SELECT COUNT(*) FROM ecue e2 WHERE e2.UE_idUE = ue.idUE) as nb_ecue_total,
           COUNT(ec.idECUE) as nb_ecue_inscrites,
           SUM(CASE WHEN bn.best_mf IS NOT NULL THEN 1 ELSE 0 END) as nb_ecue_avec_note,
           MAX(bn.session_id) as session_id,
           s.description as session_nom
    FROM ecue ec
    JOIN ue ON ec.UE_idUE = ue.idUE
    JOIN semestre sem ON ue.semestre_idsemestre = sem.idsemestre
    JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
    JOIN (
        SELECT cg.ECUE_idECUE, cg.annee_acad_id,
               MAX(cg.MF) as best_mf,
               MAX(cg.session_idsession) as session_id
        FROM cotes_grille cg
        WHERE cg.matricule = ?
        GROUP BY cg.ECUE_idECUE, cg.annee_acad_id
    ) bn ON bn.ECUE_idECUE = ec.idECUE
    LEFT JOIN annee_acad aa ON bn.annee_acad_id = aa.idannee_acad
    LEFT JOIN session s ON bn.session_id = s.idsession
    GROUP BY ue.idUE, bn.annee_acad_id
    ORDER BY bn.annee_acad_id DESC, sem.numeroSemestre ASC, ue.designationUE ASC
");
$stmt->execute([$creditHeure, $matricule]);
$ueStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ueStatus as &$ueRow) {
    // UE validée uniquement si TOUTES les ECUEs de l'UE ont une note ET moyenne >= 10
    $nbEcueTotal = max($ueRow['nb_ecue_total'], $ueRow['nb_ecue_inscrites']);
    $toutesNotesPresentes = ($ueRow['nb_ecue_avec_note'] == $nbEcueTotal);
    $ueRow['est_validee'] = ($toutesNotesPresentes && $ueRow['moyenne_ue'] !== null && floatval($ueRow['moyenne_ue']) >= 10) ? 1 : 0;
    $ueRow['note_finale'] = $toutesNotesPresentes ? $ueRow['moyenne_ue'] : null;
}
unset($ueRow);

// Récupérer les documents associés à l'étudiant
$stmt = $connexion->prepare("
    SELECT * FROM etudiant_documents 
    WHERE idetudiant = ?
    ORDER BY date_ajout DESC
");
$stmt->execute([$idEtudiant]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques globales
$totalCredits = 0;
$creditsObtenus = 0;
$moyenneGenerale = 0;
$totalUE = 0;
$ueValidees = 0;

foreach ($ueStatus as $ue) {
    $totalUE++;
    $creditsUE = isset($ue['credits_ue']) ? round(floatval($ue['credits_ue'])) : 0;
    $totalCredits += $creditsUE;
    if ($ue['est_validee']) {
        $ueValidees++;
        $creditsObtenus += $creditsUE;
    }
}

$tauxReussite = $totalUE > 0 ? round(($ueValidees / $totalUE) * 100, 2) : 0;


// Récupérer l'historique complet des inscriptions de l'étudiant
$stmt = $connexion->prepare("
    SELECT e.idetudiant, e.promotion_idpromotion, e.annee_acad_idannee_acad, 
           e.dateEnregistrement, e.est_actif,
           p.designationPromotion, p.cycle,
           o.designationOrientation, 
           s.designationSection,
           aa.designation as annee_academique
    FROM etudiant e
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
    WHERE e.matricule = ?
    ORDER BY e.dateEnregistrement DESC
");
$stmt->execute([$matricule]);
$historiqueInscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Fiche de Scolarité</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=etudiants/liste_etudiants">Étudiants</a></li>
                <li class="breadcrumb-item active">Fiche de Scolarité</li>
            </ol>
        </nav>
    </div>

    <section class="section profile">
        <div class="row">
            <!-- Carte d'information rapide -->
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <?php if (!empty($etudiant['photo'])): ?>
                                    <img src="<?= htmlspecialchars($etudiant['photo']) ?>" alt="Photo de l'étudiant" class="img-fluid rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                        <i class="bi bi-person" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h2 class="mb-1"><?= htmlspecialchars($etudiant['noms']) ?></h2>
                                <p class="mb-1"><strong>Matricule:</strong> <?= htmlspecialchars($etudiant['matricule']) ?></p>
                                <p class="mb-1"><strong>Promotion actuelle:</strong> <?= htmlspecialchars($etudiant['designationPromotion'] ?? 'Non définie') ?></p>
                                <p class="mb-0"><strong>Date d'inscription:</strong> <?= date('d/m/Y', strtotime($etudiant['dateEnregistrement'])) ?></p>
                            </div>
                            <div class="ms-auto text-end">
                                <div class="card border-primary mb-2" style="max-width: 18rem;">
                                    <div class="card-body text-primary">
                                        <h5 class="card-title">Taux de réussite</h5>
                                        <p class="card-text display-6"><?= $tauxReussite ?>%</p>
                                    </div>
                                </div>
                                <div class="card border-success" style="max-width: 18rem;">
                                    <div class="card-body text-success">
                                        <h5 class="card-title">Crédits obtenus</h5>
                                        <p class="card-text display-6"><?= $creditsObtenus ?>/<?= $totalCredits ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body pt-3">
                        <!-- Onglets -->
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-overview" type="button" role="tab" aria-controls="profile-overview" aria-selected="true">
                                    <i class="bi bi-person me-1"></i> Informations personnelles
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="moyennes-tab" data-bs-toggle="tab" data-bs-target="#moyennes-overview" type="button" role="tab" aria-controls="moyennes-overview" aria-selected="false">
                                    <i class="bi bi-bar-chart me-1"></i> Moyennes & Crédits
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ue-tab" data-bs-toggle="tab" data-bs-target="#ue-overview" type="button" role="tab" aria-controls="ue-overview" aria-selected="false">
                                    <i class="bi bi-list-check me-1"></i> État des UE
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-overview" type="button" role="tab" aria-controls="documents-overview" aria-selected="false">
                                    <i class="bi bi-file-earmark me-1"></i> Documents
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <!-- Onglet Informations personnelles -->
                            <div class="tab-pane fade show active profile-overview" id="profile-overview" role="tabpanel">
                                <h5 class="card-title">Détails du profil</h5>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Nom complet</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['noms']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Matricule</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['matricule']) ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Date de naissance</div>
                                    <div class="col-lg-9 col-md-8"><?= !empty($etudiant['date_naissance']) ? date('d/m/Y', strtotime($etudiant['date_naissance'])) : 'Non renseignée' ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Lieu de naissance</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['lieu_naissance'] ?? 'Non renseigné') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Sexe</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['sexe'] ?? 'Non renseigné') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Adresse</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['adresse'] ?? 'Non renseignée') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Téléphone</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['telephone'] ?? 'Non renseigné') ?></div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-3 col-md-4 label">Email</div>
                                    <div class="col-lg-9 col-md-8"><?= htmlspecialchars($etudiant['email'] ?? 'Non renseigné') ?></div>
                                </div>
                                
                                
                                <h5 class="card-title mt-4">Historique académique</h5>

                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr class="table-primary">
                                                <th>Année académique</th>
                                                <th>Section</th>
                                                <th>Orientation</th>
                                                <th>Promotion</th>
                                                <th>Cycle</th>
                                                <th>Date d'inscription</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($historiqueInscriptions)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Aucun historique disponible</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($historiqueInscriptions as $inscription): ?>
                                                    <?php 
                                                        $statut = $inscription['est_actif'] ? 'Inscription active' : 'Inscription archivée';
                                                        $statutClass = $inscription['est_actif'] ? 'bg-success text-white' : 'bg-secondary text-white';
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($inscription['annee_academique']) ?></td>
                                                        <td><?= htmlspecialchars($inscription['designationSection']) ?></td>
                                                        <td><?= htmlspecialchars($inscription['designationOrientation']) ?></td>
                                                        <td><?= htmlspecialchars($inscription['designationPromotion']) ?></td>
                                                        <td><?= htmlspecialchars($inscription['cycle']) ?></td>
                                                        <td><?= date('d/m/Y', strtotime($inscription['dateEnregistrement'])) ?></td>
                                                        <td><span class="badge <?= $statutClass ?>"><?= $statut ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                
                            </div>

                            <!-- Onglet Moyennes & Crédits -->
                            <div class="tab-pane fade" id="moyennes-overview" role="tabpanel">
                                <h5 class="card-title">Moyennes par année académique</h5>
                                
                                <?php
                                // Organiser les moyennes par année académique
                                $moyennesParAnnee = [];
                                foreach ($moyennes as $moyenne) {
                                    $annee = $moyenne['annee_academique'];
                                    if (!isset($moyennesParAnnee[$annee])) {
                                        $moyennesParAnnee[$annee] = [];
                                    }
                                    $moyennesParAnnee[$annee][] = $moyenne;
                                }
                                
                                if (empty($moyennesParAnnee)):
                                ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Aucune moyenne n'est disponible pour cet étudiant.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($moyennesParAnnee as $annee => $moyennesAnnee): ?>
                                        <div class="card mb-4">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0"><?= htmlspecialchars($annee) ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Semestre</th>
                                                                <th>UE</th>
                                                                <th>ECUE</th>
                                                                <th class="text-center">Moyenne CC</th>
                                                                <th class="text-center">Moyenne Examen</th>
                                                                <th class="text-center">Moyenne Finale</th>
                                                                <th class="text-center">Session</th>
                                                                <th class="text-center">Crédits</th>
                                                                <th class="text-center">Statut</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $totalPoints = 0;
                                                            $totalECUE = 0;
                                                            $ecueValides = 0;
                                                            $creditsPossibles = 0;
                                                            $creditsObtenus = 0;
                                                            
                                                            foreach ($moyennesAnnee as $m): 
                                                                // Supposons que chaque ECUE vaut 2 crédits (à adapter selon votre système)
                                                                $credits = 2;
                                                                $creditsPossibles += $credits;
                                                                $estValide = $m['MF'] >= 10;
                                                                if ($estValide) {
                                                                    $ecueValides++;
                                                                    $creditsObtenus += $credits;
                                                                }
                                                                $totalPoints += $m['MF'];
                                                                $totalECUE++;
                                                                
                                                                // Déterminer les classes CSS pour les moyennes
                                                                $cc = $m['CC'] !== null ? floatval($m['CC']) : null;
                                                                $ex = $m['EX'] !== null ? floatval($m['EX']) : null;
                                                                $mf = $m['MF'] !== null ? floatval($m['MF']) : null;
                                                                $ccClass = $cc !== null && $cc < 10 ? 'text-danger' : ($cc !== null && $cc >= 16 ? 'text-success' : '');
                                                                $exClass = $ex !== null && $ex < 10 ? 'text-danger' : ($ex !== null && $ex >= 16 ? 'text-success' : '');
                                                                $mfClass = $mf !== null && $mf < 10 ? 'text-danger' : ($mf !== null && $mf >= 16 ? 'text-success' : '');
                                                            ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($m['numeroSemestre']) ?></td>
                                                                    <td><?= htmlspecialchars($m['designationUE']) ?></td>
                                                                    <td><?= htmlspecialchars($m['designationECUE']) ?></td>
                                                                    <td class="text-center <?= $ccClass ?>"><?= $cc !== null ? number_format($cc, 2) : '-' ?></td>
                                                                    <td class="text-center <?= $exClass ?>"><?= $ex !== null ? number_format($ex, 2) : '-' ?></td>
                                                                    <td class="text-center <?= $mfClass ?> fw-bold"><?= $mf !== null ? number_format($mf, 2) : '-' ?></td>
                                                                    <td class="text-center"><?= htmlspecialchars($m['session_nom']) ?></td>
                                                                    <td class="text-center"><?= $credits ?></td>
                                                                    <td class="text-center">
                                                                        <?php if ($estValide): ?>
                                                                            <span class="badge bg-success">Validé</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger">Non validé</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr class="table-secondary">
                                                                <td colspan="5" class="text-end fw-bold">Moyenne générale:</td>
                                                                <td class="text-center fw-bold">
                                                                    <?php 
                                                                    $moyenneGenerale = $totalECUE > 0 ? $totalPoints / $totalECUE : 0;
                                                                    $moyenneClass = $moyenneGenerale < 10 ? 'text-danger' : ($moyenneGenerale >= 16 ? 'text-success' : '');
                                                                    echo '<span class="' . $moyenneClass . '">' . number_format($moyenneGenerale, 2) . '</span>';
                                                                    ?>
                                                                </td>
                                                                <td class="text-center fw-bold">Total</td>
                                                                <td class="text-center fw-bold"><?= $creditsObtenus ?>/<?= $creditsPossibles ?></td>
                                                                <td class="text-center fw-bold">
                                                                    <?php 
                                                                    $tauxReussite = $totalECUE > 0 ? ($ecueValides / $totalECUE) * 100 : 0;
                                                                    echo number_format($tauxReussite, 2) . '%';
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                                
                                                <!-- Graphique de performance -->
                                                <div class="mt-4">
                                                    <h6>Graphique de performance</h6>
                                                    <canvas id="performanceChart<?= str_replace(' ', '', $annee) ?>" style="max-height: 300px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Onglet État des UE -->
                            <div class="tab-pane fade" id="ue-overview" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title">État des Unités d'Enseignement</h5>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary me-2" id="btnShowAll">Tout afficher</button>
                                        <button class="btn btn-sm btn-outline-success me-2" id="btnShowValidated">Validées</button>
                                        <button class="btn btn-sm btn-outline-danger" id="btnShowNotValidated">Non validées</button>
                                    </div>
                                </div>
                                
                                <?php if (empty($ueStatus)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Aucune information sur les UE n'est disponible pour cet étudiant.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover" id="ueTable">
                                            <thead>
                                                <tr>
                                                    <th>Année académique</th>
                                                    <th>Semestre</th>
                                                    <th>Unité d'Enseignement</th>
                                                    <th class="text-center">Note finale</th>
                                                    <th class="text-center">Session</th>
                                                    <th class="text-center">Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ueStatus as $ue): ?>
                                                    <?php 
                                                    $estValidee = $ue['est_validee'] == 1;
                                                    $rowClass = $estValidee ? 'table-success ue-validated' : 'table-danger ue-not-validated';
                                                    $noteClass = $ue['note_finale'] < 10 ? 'text-danger' : ($ue['note_finale'] >= 16 ? 'text-success' : '');
                                                    ?>
                                                    <tr class="<?= $rowClass ?>">
                                                        <td><?= htmlspecialchars($ue['annee_academique'] ?? 'Non définie') ?></td>
                                                        <td><?= htmlspecialchars($ue['numeroSemestre']) ?></td>
                                                        <td><?= htmlspecialchars($ue['designationUE']) ?></td>
                                                        <td class="text-center <?= $noteClass ?> fw-bold">
                                                            <?= $ue['note_finale'] ? number_format($ue['note_finale'], 2) : '-' ?>
                                                        </td>
                                                        <td class="text-center"><?= htmlspecialchars($ue['session_nom'] ?? '-') ?></td>
                                                        <td class="text-center">
                                                            <?php if ($estValidee): ?>
                                                                <span class="badge bg-success">Validée</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Non validée</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Graphique de répartition des UE -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Répartition des UE</h5>
                                                    <canvas id="ueDistributionChart" style="max-height: 300px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">Progression par semestre</h5>
                                                    <canvas id="semestreProgressionChart" style="max-height: 300px;"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            

                            <!-- Remplacer la section des documents par ce code -->
                            <div class="tab-pane fade" id="documents-overview" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title">Documents associés</h5>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                                        <i class="bi bi-plus-circle me-1"></i> Ajouter un document
                                    </button>
                                </div>
                                
                                <!-- Résumé des documents obligatoires -->
                                <div class="card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">Documents obligatoires</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // Récupérer les documents obligatoires pour le cycle de l'étudiant
                                        $stmt = $connexion->prepare("
                                            SELECT p.cycle FROM promotion p WHERE p.idpromotion = ?
                                        ");
                                        $stmt->execute([$etudiant['idpromotion']]);
                                        $cycleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $cycle = $cycleInfo ? $cycleInfo['cycle'] : 'Premier';
                                        
                                        // Récupérer la liste des documents obligatoires pour ce cycle
                                        $stmt = $connexion->prepare("
                                            SELECT do.* 
                                            FROM documents_obligatoires do
                                            WHERE do.cycle = ? OR do.cycle = 'Tous'
                                            ORDER BY do.designation
                                        ");
                                        $stmt->execute([$cycle]);
                                        $documentsObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Récupérer les documents fournis par l'étudiant
                                        $stmt = $connexion->prepare("
                                            SELECT ed.*, do.designation as nom_document_obligatoire
                                            FROM etudiant_documents ed
                                            LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
                                            WHERE ed.matricule = ?
                                        ");
                                        $stmt->execute([$matricule]);
                                        $documentsFournis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Créer un tableau d'association pour les documents fournis
                                        $documentsParObligatoire = [];
                                        foreach ($documentsFournis as $doc) {
                                            if ($doc['document_obligatoire_id']) {
                                                $documentsParObligatoire[$doc['document_obligatoire_id']] = $doc;
                                            }
                                        }
                                        
                                        if (empty($documentsObligatoires)) {
                                            echo '<div class="alert alert-info">Aucun document obligatoire n\'est défini pour ce cycle.</div>';
                                        } else {
                                        ?>
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Document</th>
                                                            <th>Statut</th>
                                                            <th>Date fourni</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($documentsObligatoires as $docOblig): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($docOblig['designation']) ?></td>
                                                                <td>
                                                                    <?php if (isset($documentsParObligatoire[$docOblig['id']])): 
                                                                        $docFourni = $documentsParObligatoire[$docOblig['id']];
                                                                        $statutClass = 'bg-warning';
                                                                        $statutText = 'En attente';
                                                                        
                                                                        if ($docFourni['statut'] == 'Valide') {
                                                                            $statutClass = 'bg-success';
                                                                            $statutText = 'Validé';
                                                                        } elseif ($docFourni['statut'] == 'Rejeté') {
                                                                            $statutClass = 'bg-danger';
                                                                            $statutText = 'Rejeté';
                                                                        }
                                                                    ?>
                                                                        <span class="badge <?= $statutClass ?>"><?= $statutText ?></span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-danger">Non fourni</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (isset($documentsParObligatoire[$docOblig['id']])): ?>
                                                                        <?= date('d/m/Y', strtotime($documentsParObligatoire[$docOblig['id']]['date_ajout'])) ?>
                                                                    <?php else: ?>
                                                                        -
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (isset($documentsParObligatoire[$docOblig['id']])): 
                                                                        $docId = $documentsParObligatoire[$docOblig['id']]['id'];
                                                                        $docPath = $documentsParObligatoire[$docOblig['id']]['chemin_fichier'];
                                                                    ?>
                                                                        <div class="btn-group btn-group-sm" role="group">
                                                                            <a href="<?= htmlspecialchars($docPath) ?>" class="btn btn-primary" target="_blank">
                                                                                <i class="bi bi-eye"></i>
                                                                            </a>
                                                                            <a href="<?= htmlspecialchars($docPath) ?>" class="btn btn-success" download>
                                                                                <i class="bi bi-download"></i>
                                                                            </a>
                                                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#replaceDocumentModal" 
                                                                                data-document-id="<?= $docId ?>" data-obligatoire-id="<?= $docOblig['id'] ?>">
                                                                                <i class="bi bi-arrow-repeat"></i>
                                                                            </button>
                                                                            <?php if ($docFourni['statut'] != 'Valide'): ?>
                                                                            <button type="button" class="btn btn-info" onclick="validateDocument(<?= $docId ?>)">
                                                                                <i class="bi bi-check-circle"></i>
                                                                            </button>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentModal" 
                                                                            data-obligatoire-id="<?= $docOblig['id'] ?>" data-obligatoire-name="<?= htmlspecialchars($docOblig['designation']) ?>">
                                                                            <i class="bi bi-plus-circle"></i> Ajouter
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Liste de tous les documents -->
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">Tous les documents</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($documentsFournis)): ?>
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Aucun document n'est associé à cet étudiant.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Type</th>
                                                            <th>Titre</th>
                                                            <th>Description</th>
                                                            <th>Statut</th>
                                                            <th>Date d'ajout</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($documentsFournis as $doc): 
                                                        $statutClass = '';
                                                        switch ($doc['statut']) {
                                                            case 'Valide': 
                                                                $statutClass = 'bg-success'; 
                                                                break;
                                                            case 'Rejeté': 
                                                                $statutClass = 'bg-danger'; 
                                                                break;
                                                            default: 
                                                                $statutClass = 'bg-warning';
                                                        }
                                                    ?>
                                                            <tr>
                                                                <td>
                                                                    <?php if (!empty($doc['nom_document_obligatoire'])): ?>
                                                                        <span class="badge bg-primary"><?= htmlspecialchars($doc['nom_document_obligatoire']) ?></span>
                                                                    <?php else: ?>
                                                                        <?= htmlspecialchars($doc['type_document']) ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($doc['titre']) ?></td>
                                                                <td><?= htmlspecialchars($doc['description']) ?></td>
                                                                <td><span class="badge <?= $statutClass ?>"><?= htmlspecialchars($doc['statut']) ?></span></td>
                                                                <td><?= date('d/m/Y', strtotime($doc['date_ajout'])) ?></td>
                                                                <td>
                                                                    <div class="btn-group" role="group">
                                                                        <a href="<?= htmlspecialchars($doc['chemin_fichier']) ?>" class="btn btn-sm btn-primary" target="_blank">
                                                                            <i class="bi bi-eye"></i> Voir
                                                                        </a>
                                                                        <a href="<?= htmlspecialchars($doc['chemin_fichier']) ?>" class="btn btn-sm btn-success" download>
                                                                            <i class="bi bi-download"></i> Télécharger
                                                                        </a>
                                                                        <?php if ($doc['statut'] != 'Valide'): ?>
                                                                        <button type="button" class="btn btn-sm btn-info" onclick="validateDocument(<?= $doc['id'] ?>)">
                                                                            <i class="bi bi-check-circle"></i> Valider
                                                                        </button>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteDocument(<?= $doc['id'] ?>)">
                                                                            <i class="bi bi-trash"></i> Supprimer
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>






                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<!-- Modal pour ajouter un document -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/ajouter_document_etudiant.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="idetudiant" value="<?= $idEtudiant ?>">
                    <input type="hidden" name="matricule" value="<?= htmlspecialchars($matricule) ?>">
                    <input type="hidden" name="document_obligatoire_id" id="document_obligatoire_id" value="">
                    <input type="hidden" name="annee_acad_id" value="<?= $anneeActuelle['idannee_acad'] ?>">
                    
                    <div class="mb-3" id="document_type_container">
                        <label for="type_document" class="form-label">Type de document</label>
                        <select class="form-select" id="type_document" name="type_document" required>
                            <option value="">Sélectionnez un type...</option>
                            <option value="Document obligatoire">Document obligatoire</option>
                            <option value="Relevé de notes">Relevé de notes</option>
                            <option value="Attestation">Attestation</option>
                            <option value="Certificat">Certificat</option>
                            <option value="Diplôme">Diplôme</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="document_obligatoire_container" style="display:none;">
                        <label for="select_document_obligatoire" class="form-label">Document obligatoire</label>
                        <select class="form-select" id="select_document_obligatoire" name="select_document_obligatoire">
                            <option value="">Sélectionnez un document obligatoire...</option>
                            <?php foreach ($documentsObligatoires as $docOblig): 
                                // Ne pas afficher les documents déjà fournis
                                if (!isset($documentsParObligatoire[$docOblig['id']])):
                            ?>
                                <option value="<?= $docOblig['id'] ?>"><?= htmlspecialchars($docOblig['designation']) ?></option>
                            <?php 
                                endif;
                            endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="titre" name="titre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_file" class="form-label">Fichier</label>
                        <input type="file" class="form-control" id="document_file" name="document_file" required>
                        <div class="form-text">Formats acceptés: PDF, JPG, PNG. Taille max: 5 Mo</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour remplacer un document -->
<div class="modal fade" id="replaceDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remplacer un document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/remplacer_document_etudiant.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="document_id" id="replace_document_id" value="">
                    <input type="hidden" name="idetudiant" value="<?= $idEtudiant ?>">
                    <input type="hidden" name="matricule" value="<?= htmlspecialchars($matricule) ?>">
                    <input type="hidden" name="obligatoire_id" id="replace_obligatoire_id" value="">
                    <input type="hidden" name="annee_acad_id" value="<?= $anneeActuelle['idannee_acad'] ?>">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Vous êtes sur le point de remplacer un document existant. Le nouveau fichier remplacera l'ancien.
                    </div>
                    
                    <div class="mb-3">
                        <label for="replace_titre" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="replace_titre" name="titre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="replace_description" class="form-label">Description</label>
                        <textarea class="form-control" id="replace_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="replace_document_file" class="form-label">Nouveau fichier</label>
                        <input type="file" class="form-control" id="replace_document_file" name="document_file" required>
                        <div class="form-text">Formats acceptés: PDF, JPG, PNG. Taille max: 5 Mo</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="replace_raison" class="form-label">Raison du remplacement</label>
                        <textarea class="form-control" id="replace_raison" name="raison" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Remplacer</button>
                </div>
            </form>
        </div>
    </div>
</div>






<!-- Scripts pour les graphiques -->
<script>




document.addEventListener('DOMContentLoaded', function() {
    // Filtrage des UE
    document.getElementById('btnShowAll').addEventListener('click', function() {
        const rows = document.querySelectorAll('#ueTable tbody tr');
        rows.forEach(row => row.style.display = '');
    });
    
    document.getElementById('btnShowValidated').addEventListener('click', function() {
        const rows = document.querySelectorAll('#ueTable tbody tr');
        rows.forEach(row => {
            if (row.classList.contains('ue-validated')) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    document.getElementById('btnShowNotValidated').addEventListener('click', function() {
        const rows = document.querySelectorAll('#ueTable tbody tr');
        rows.forEach(row => {
            if (row.classList.contains('ue-not-validated')) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Graphique de distribution des UE
    const ueDistributionCtx = document.getElementById('ueDistributionChart');
    if (ueDistributionCtx) {
        new Chart(ueDistributionCtx, {
            type: 'pie',
            data: {
                labels: ['UE Validées', 'UE Non Validées'],
                datasets: [{
                    data: [<?= $ueValidees ?>, <?= $totalUE - $ueValidees ?>],
                    backgroundColor: ['#198754', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Graphique de progression par semestre
    const semestreProgressionCtx = document.getElementById('semestreProgressionChart');
    if (semestreProgressionCtx) {
        // Calculer les données par semestre
        const semestres = {};
        const ueRows = document.querySelectorAll('#ueTable tbody tr');
        
        ueRows.forEach(row => {
            const semestre = row.cells[1].textContent.trim();
            const estValidee = row.classList.contains('ue-validated');
            
            if (!semestres[semestre]) {
                semestres[semestre] = { total: 0, validees: 0 };
            }
            
            semestres[semestre].total++;
            if (estValidee) {
                semestres[semestre].validees++;
            }
        });
        
        const semestreLabels = Object.keys(semestres).sort();
        const semestreData = semestreLabels.map(sem => {
            const total = semestres[sem].total;
            const validees = semestres[sem].validees;
            return (validees / total) * 100;
        });
        
        new Chart(semestreProgressionCtx, {
            type: 'bar',
            data: {
                labels: semestreLabels.map(sem => `Semestre ${sem}`),
                datasets: [{
                    label: 'Taux de réussite (%)',
                    data: semestreData,
                    backgroundColor: '#0d6efd',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const sem = semestreLabels[context.dataIndex];
                                const validees = semestres[sem].validees;
                                const total = semestres[sem].total;
                                return `${validees}/${total} UE validées (${Math.round((validees/total)*100)}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Graphiques de performance par année
    <?php foreach ($moyennesParAnnee as $annee => $moyennesAnnee): ?>
        const performanceCtx<?= str_replace(' ', '', $annee) ?> = document.getElementById('performanceChart<?= str_replace(' ', '', $annee) ?>');
        if (performanceCtx<?= str_replace(' ', '', $annee) ?>) {
            // Extraire les données pour le graphique
            const ecues = <?= json_encode(array_map(function($m) { return $m['designationECUE']; }, $moyennesAnnee)) ?>;
            const moyennesCC = <?= json_encode(array_map(function($m) { return $m['CC']; }, $moyennesAnnee)) ?>;
            const moyennesEX = <?= json_encode(array_map(function($m) { return $m['EX']; }, $moyennesAnnee)) ?>;
            const moyennesMF = <?= json_encode(array_map(function($m) { return $m['MF']; }, $moyennesAnnee)) ?>;
            
            new Chart(performanceCtx<?= str_replace(' ', '', $annee) ?>, {
                type: 'bar',
                data: {
                    labels: ecues,
                    datasets: [
                        {
                            label: 'CC',
                            data: moyennesCC,
                            backgroundColor: 'rgba(255, 193, 7, 0.5)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Examen',
                            data: moyennesEX,
                            backgroundColor: 'rgba(13, 110, 253, 0.5)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Moyenne Finale',
                            data: moyennesMF,
                            backgroundColor: 'rgba(25, 135, 84, 0.5)',
                            borderColor: 'rgba(25, 135, 84, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 20,
                            title: {
                                display: true,
                                text: 'Notes sur 20'
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    <?php endforeach; ?>
});

// Fonction pour confirmer la suppression d'un document
function confirmDeleteDocument(documentId) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera définitivement ce document!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/supprimer_document_etudiant.php?id=${documentId}&idetudiant=<?= $idEtudiant ?>`;
        }
    });
}
</script>

<!-- Scripts pour la gestion des documents -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du type de document
    document.getElementById('type_document').addEventListener('change', function() {
        const docTypeContainer = document.getElementById('document_obligatoire_container');
        const docObligatoireId = document.getElementById('document_obligatoire_id');
        
        if (this.value === 'Document obligatoire') {
            docTypeContainer.style.display = 'block';
            // Réinitialiser la valeur si nécessaire
            docObligatoireId.value = '';
        } else {
            docTypeContainer.style.display = 'none';
            docObligatoireId.value = '';
        }
    });
    
    // Mise à jour de l'ID du document obligatoire lors de la sélection
    document.getElementById('select_document_obligatoire').addEventListener('change', function() {
        document.getElementById('document_obligatoire_id').value = this.value;
        // Auto-remplir le titre avec le nom du document obligatoire
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('titre').value = selectedOption.text;
        }
    });
    
    // Gestion de la pré-sélection lors de l'ouverture du modal d'ajout
    const addDocumentModal = document.getElementById('addDocumentModal');
    if (addDocumentModal) {
        addDocumentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const obligatoireId = button.getAttribute('data-obligatoire-id');
            const obligatoireName = button.getAttribute('data-obligatoire-name');
            
            if (obligatoireId) {
                // C'est un document obligatoire spécifique
                document.getElementById('type_document').value = 'Document obligatoire';
                document.getElementById('document_obligatoire_container').style.display = 'block';
                document.getElementById('select_document_obligatoire').value = obligatoireId;
                document.getElementById('document_obligatoire_id').value = obligatoireId;
                
                // Pré-remplir le titre
                if (obligatoireName) {
                    document.getElementById('titre').value = obligatoireName;
                }
            } else {
                // Réinitialiser
                document.getElementById('type_document').value = '';
                document.getElementById('document_obligatoire_container').style.display = 'none';
                document.getElementById('select_document_obligatoire').value = '';
                document.getElementById('document_obligatoire_id').value = '';
                document.getElementById('titre').value = '';
                document.getElementById('description').value = '';
            }
        });
    }
    
    // Gestion du modal de remplacement
    const replaceDocumentModal = document.getElementById('replaceDocumentModal');
    if (replaceDocumentModal) {
        replaceDocumentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const documentId = button.getAttribute('data-document-id');
            const obligatoireId = button.getAttribute('data-obligatoire-id');
            
            document.getElementById('replace_document_id').value = documentId;
            document.getElementById('replace_obligatoire_id').value = obligatoireId;
            
            // Pré-remplir avec les informations actuelles
            // (ici vous pourriez ajouter un appel AJAX pour récupérer les détails du document si nécessaire)
        });
    }
});

// Fonction pour confirmer la suppression d'un document
function confirmDeleteDocument(documentId) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera définitivement ce document!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/supprimer_document_etudiant.php?id=${documentId}&idetudiant=<?= $idEtudiant ?>&matricule=<?= urlencode($matricule) ?>`;
        }
    });
}

// Fonction pour valider un document
function validateDocument(documentId) {
    Swal.fire({
        title: 'Valider ce document?',
        text: "Confirmez-vous que ce document est valide et conforme?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, valider!',
        cancelButtonText: 'Annuler',
        input: 'text',
        inputPlaceholder: 'Commentaire (optionnel)',
        inputAttributes: {
            autocapitalize: 'off'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const commentaire = result.value || '';
            window.location.href = `controller/valider_document_etudiant.php?id=${documentId}&idetudiant=<?= $idEtudiant ?>&commentaire=${encodeURIComponent(commentaire)}`;
        }
    });
}


</script>

<?php include "./views/include/footer.php"; ?>

