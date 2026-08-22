<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Vérifier que l'utilisateur est connecté et est un enseignant
$userId = $_SESSION['id'] ?? 0;

// Vérifier si l'utilisateur est un enseignant
$stmt = $connexion->prepare("
    SELECT a.idAgent 
    FROM agent a 
    JOIN t_users u ON a.idAgent = u.idAgent 
    WHERE u.idUser = ? AND a.type_agent = 'Enseignant'
");
$stmt->execute([$userId]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userId || !$enseignant) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous devez être connecté en tant qu\'enseignant pour accéder à cette page.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Récupérer l'ID de l'agent (enseignant)
$idEnseignant = $enseignant['idAgent'];

// Récupérer l'ID de l'ECUE depuis l'URL
$idEcue = isset($_GET['ecue']) ? intval($_GET['ecue']) : 0;
if ($idEcue <= 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ECUE non spécifié ou invalide.'
        }).then(() => {
            window.location.href = '?view=recherche/mes_cours';
        });
    </script>";
    exit;
}

// Récupérer l'année académique actuelle
$stmt = $connexion->query("SELECT * FROM annee_acad WHERE dateCreation = (SELECT MAX(dateCreation) FROM annee_acad)");
$currentYear = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de déterminer l\'année académique courante.'
        }).then(() => {
            window.location.href = '?view=dashboard';
        });
    </script>";
    exit;
}

// Vérifier si l'enseignant est autorisé à accéder à cet ECUE
$stmt = $connexion->prepare("
    SELECT COUNT(*) as count 
    FROM enseignant_ecue 
    WHERE idAgent = ? AND idECUE = ? AND anneeAcad = ?
");
$stmt->execute([$idEnseignant, $idEcue, $currentYear['idannee_acad']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] == 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'êtes pas autorisé à accéder aux évaluations de ce cours.'
        }).then(() => {
            window.location.href = '?view=recherche/mes_cours';
        });
    </script>";
    exit;
}

// Récupérer les détails de l'ECUE
$stmt = $connexion->prepare("
    SELECT e.idECUE, e.designationECUE, e.CMI, e.TD, e.TP, 
           u.designationUE, s.numeroSemestre, p.designationPromotion
    FROM ecue e
    JOIN ue u ON e.UE_idUE = u.idUE
    JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
    JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
    WHERE e.idECUE = ?
");
$stmt->execute([$idEcue]);
$ecueDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ecueDetails) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ECUE non trouvé.'
        }).then(() => {
            window.location.href = '?view=recherche/mes_cours';
        });
    </script>";
    exit;
}

// Récupérer les sessions (première, deuxième)
$stmt = $connexion->query("SELECT * FROM session ORDER BY idsession");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la première session
$stmt = $connexion->prepare("SELECT * FROM session WHERE designSession LIKE ? LIMIT 1");
$stmt->execute(['%Première%']);
$s1 = $stmt->fetch(PDO::FETCH_ASSOC);
$premiereSession = $s1['idsession'];

// Récupérer les types d'évaluation (contrôle continu, examen, etc.)
$stmt = $connexion->query("SELECT * FROM typeevaluation ORDER BY idType");
$typesEvaluation = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les évaluations existantes pour cet ECUE
$stmt = $connexion->prepare("
    SELECT e.*, t.designationT, t.categorie, s.description 
    FROM evaluations e
    JOIN typeevaluation t ON e.idType = t.idType
    JOIN session s ON e.session_idsession = s.idsession
    WHERE e.idECUE = ? AND e.annee_acad_id = ?
    ORDER BY e.date_evaluation DESC
");
$stmt->execute([$idEcue, $currentYear['idannee_acad']]);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des étudiants inscrits à ce cours
$stmt = $connexion->prepare("
    SELECT DISTINCT e.idetudiant, e.matricule, e.noms
    FROM etudiant e
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN semestre s ON s.promotion_idpromotion = p.idpromotion
    JOIN ue u ON u.semestre_idsemestre = s.idsemestre
    JOIN ecue ec ON ec.UE_idUE = u.idUE
    WHERE ec.idECUE = ? AND e.annee_acad_idannee_acad = ?
    ORDER BY e.noms
");
$stmt->execute([$idEcue, $currentYear['idannee_acad']]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la configuration des moyennes pour cet ECUE
$stmt = $connexion->prepare("
    SELECT * FROM configuration_moyenne 
    WHERE idECUE = ? AND annee_acad_id = ? AND session_idsession = ?
");
$stmt->execute([$idEcue, $currentYear['idannee_acad'], $premiereSession]);
$configMoyenne = $stmt->fetch(PDO::FETCH_ASSOC);

// Si aucune configuration n'existe, utiliser les valeurs par défaut
if (!$configMoyenne) {
    $configMoyenne = [
        'ponderation_cc' => 0.40,
        'ponderation_ex' => 0.60,
        'formule_cc' => 'MOYENNE',
        'formule_ex' => 'VALEUR'
    ];
}

// Récupérer l'état de verrouillage des sessions
$stmt = $connexion->prepare("
    SELECT * FROM ecue_notes_verrouillage 
    WHERE idECUE = ? AND idannee_acad = ?
");
$stmt->execute([$idEcue, $currentYear['idannee_acad']]);
$sessionsVerrouillees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir en tableau indexé par idsession pour faciliter la vérification
$verrouillageParSession = [];
foreach ($sessionsVerrouillees as $verrouillage) {
    $verrouillageParSession[$verrouillage['idsession']] = $verrouillage;
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>ÉVALUATIONS - <?= htmlspecialchars($ecueDetails['designationECUE']) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=recherche/mes_cours">Mes Cours</a></li>
                <li class="breadcrumb-item active">Évaluations</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Informations du cours -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations générales</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Cours:</strong> <?= htmlspecialchars($ecueDetails['designationECUE']) ?></p>
                                <p><strong>UE:</strong> <?= htmlspecialchars($ecueDetails['designationUE']) ?></p>
                                <p><strong>Semestre:</strong> <?= htmlspecialchars($ecueDetails['numeroSemestre']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Promotion:</strong> <?= htmlspecialchars($ecueDetails['designationPromotion']) ?></p>
                                <p><strong>Année académique:</strong> <?= htmlspecialchars($currentYear['designation']) ?></p>
                                <p><strong>Volume horaire:</strong> CMI: <?= $ecueDetails['CMI'] ?>h | TD: <?= $ecueDetails['TD'] ?>h | TP: <?= $ecueDetails['TP'] ?>h</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglets de navigation -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="evaluations-tab" data-bs-toggle="tab" data-bs-target="#evaluations-content" type="button" role="tab" aria-selected="true">
                                    <i class="bi bi-list-check me-1"></i> Liste des évaluations
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview-content" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-eye me-1"></i> Prévisualisation
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes-content" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Grille de notes
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config-content" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-gear me-1"></i> Configuration
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <!-- Onglet Liste des évaluations -->
                            <div class="tab-pane fade show active" id="evaluations-content" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Liste des évaluations</h5>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEvaluationModal">
                                        <i class="bi bi-plus-circle"></i> Ajouter une évaluation
                                    </button>
                                </div>

                                <?php if (empty($evaluations)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-1"></i> Aucune évaluation n'a encore été créée pour ce cours.
                                    </div>
                                <?php else: ?>
                                    <!-- Remplacer la table des évaluations par celle-ci -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Titre</th>
                                                    <th>Type</th>
                                                    <th>Catégorie</th>
                                                    <th>Note maximale</th>
                                                    <th>Date</th>
                                                    <th>Session</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($evaluations as $eval): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($eval['titre']) ?></td>
                                                        <td><?= htmlspecialchars($eval['designationT']) ?></td>
                                                        <td>
                                                            <?php
                                                            $categorie = htmlspecialchars($eval['categorie'] ?? '');
                                                            $categorieClass = '';

                                                            if (strtoupper($categorie) === 'CC') {
                                                                $categorieClass = 'badge bg-primary';
                                                                $categorie = 'Contrôle continu';
                                                            } else if (strtoupper($categorie) === 'EX') {
                                                                $categorieClass = 'badge bg-warning text-dark';
                                                                $categorie = 'Examen';
                                                            }
                                                            ?>
                                                            <span class="<?= $categorieClass ?>"><?= $categorie ?></span>
                                                        </td>
                                                        <td class="text-center"><?= $eval['note_max'] ?? '20' ?></td>
                                                        <td><?= date('d/m/Y', strtotime($eval['date_evaluation'])) ?></td>
                                                        <td><?= htmlspecialchars($eval['description']) ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary" onclick="openEnterGradesModal(<?= $eval['idevaluation'] ?>, '<?= addslashes($eval['titre']) ?>')">
                                                                <i class="bi bi-pencil-square"></i> Saisir les notes
                                                            </button>
                                                            <button class="btn btn-sm btn-warning" onclick="openEditEvaluationModal(<?= $eval['idevaluation'] ?>)">
                                                                <i class="bi bi-pencil"></i> Modifier
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="confirmDeleteEvaluation(<?= $eval['idevaluation'] ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>


                                <?php endif; ?>
                            </div>

                            <!-- Onglet Prévisualisation -->
                            <div class="tab-pane fade" id="preview-content" role="tabpanel">
                                <div class="card mb-4">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Prévisualisation des notes</h6>
                                        <div class="d-flex">
                                            <button class="btn btn-primary me-2" onclick="loadPreviewData()">
                                                <i class="bi bi-arrow-clockwise"></i> Actualiser
                                            </button>
                                            <button class="btn btn-warning me-2" onclick="openAddPointsModal()">
                                                <i class="bi bi-plus-circle"></i> Ajouter des points
                                            </button>
                                            <button class="btn btn-primary me-2" onclick="compileAllNotes()">
                                                <i class="bi bi-calculator"></i> Envoyer à la grille
                                            </button>
                                            <button class="btn btn-success" id="exportPreviewBtn" style="display: none;">
                                                <i class="bi bi-file-pdf"></i> Exporter
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Sélection de session pour la prévisualisation -->
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="preview-session" class="form-label">Session</label>
                                                <select class="form-select" id="preview-session">
                                                    <option value="all">Toutes les sessions</option>
                                                    <?php foreach ($sessions as $session): ?>
                                                        <option value="<?= $session['idsession'] ?>"><?= htmlspecialchars($session['description']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-8 d-flex align-items-end">
                                                <button class="btn btn-outline-primary" onclick="loadPreviewData()">
                                                    <i class="bi bi-search"></i> Afficher
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Conteneur pour les données de prévisualisation -->
                                        <div id="preview-container">
                                            <div class="text-center py-5">
                                                <p class="text-muted">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    Sélectionnez une session et cliquez sur "Afficher" pour prévisualiser les notes.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Grille de notes -->
                            <div class="tab-pane fade" id="notes-content" role="tabpanel">
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Synthèse des moyennes</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-end mb-3">
                                            <button class="btn btn-success" onclick="exportAllNotes()">
                                                <i class="bi bi-file-pdf"></i> Exporter PDF
                                            </button>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>N°</th>
                                                        <th>Matricule</th>
                                                        <th>Nom de l'étudiant</th>
                                                        <th class="text-center">CC (Première session)</th>
                                                        <th class="text-center">Examen (Première session)</th>
                                                        <th class="text-center bg-light">Moyenne (Première session)</th>
                                                        <th class="text-center">Examen (Deuxième session)</th>
                                                        <th class="text-center bg-light">Moyenne (Deuxième session)</th>
                                                    </tr>
                                                </thead>

                                                <!-- Remplacer cette partie du code dans l'onglet "Grille de notes" -->
                                                <tbody>
                                                    <?php
                                                    $i = 1;
                                                    // Récupérer tous les étudiants d'abord
                                                    $stmtEtudiants = $connexion->prepare("
        SELECT e.idetudiant, e.matricule, e.noms
        FROM etudiant e
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        JOIN semestre s ON s.promotion_idpromotion = p.idpromotion
        JOIN ue u ON u.semestre_idsemestre = s.idsemestre
        JOIN ecue ec ON ec.UE_idUE = u.idUE
        WHERE ec.idECUE = ? AND e.annee_acad_idannee_acad = ?
        ORDER BY e.noms
    ");
                                                    $stmtEtudiants->execute([$idEcue, $currentYear['idannee_acad']]);
                                                    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

                                                    // Récupérer les cotes compilées
                                                    $stmt = $connexion->prepare("
        SELECT cg.*, s.designSession 
        FROM cotes_grille cg
        JOIN session s ON cg.session_idsession = s.idsession
        WHERE cg.ECUE_idECUE = ? AND cg.annee_acad_id = ?
    ");
                                                    $stmt->execute([$idEcue, $currentYear['idannee_acad']]);
                                                    $cotesGrille = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                    // Organiser les cotes par matricule et session
                                                    $cotesParEtudiant = [];
                                                    foreach ($cotesGrille as $cote) {
                                                        if (!isset($cotesParEtudiant[$cote['matricule']])) {
                                                            $cotesParEtudiant[$cote['matricule']] = [];
                                                        }
                                                        $cotesParEtudiant[$cote['matricule']][$cote['session_idsession']] = $cote;
                                                    }

                                                    // Récupérer les IDs des sessions (première, deuxième)
                                                    $stmtSessions = $connexion->query("SELECT idsession, description FROM session ORDER BY idsession");
                                                    $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

                                                    // Trouver les IDs de la première et deuxième session
                                                    $premiereSessionId = null;
                                                    $deuxiemeSessionId = null;
                                                    foreach ($sessions as $session) {
                                                        if (stripos($session['description'], 'première') !== false || stripos($session['description'], 'premiere') !== false) {
                                                            $premiereSessionId = $session['idsession'];
                                                        } else if (stripos($session['description'], 'deuxième') !== false || stripos($session['description'], 'deuxieme') !== false) {
                                                            $deuxiemeSessionId = $session['idsession'];
                                                        }
                                                    }

                                                    // Si pas trouvé, prendre les deux premières sessions
                                                    if (!$premiereSessionId && count($sessions) > 0) {
                                                        $premiereSessionId = $sessions[0]['idsession'];
                                                    }

                                                    if (!$deuxiemeSessionId && count($sessions) > 1) {
                                                        $deuxiemeSessionId = $sessions[1]['idsession'];
                                                    }

                                                    // Afficher les données pour chaque étudiant
                                                    foreach ($etudiants as $etudiant):
                                                        $matricule = $etudiant['matricule'];

                                                        // Initialiser les variables avec des valeurs par défaut
                                                        $ccPremiere = '-';
                                                        $examenPremiere = '-';
                                                        $moyennePremiere = '-';
                                                        $examenDeuxieme = '-';
                                                        $moyenneDeuxieme = '-';

                                                        $ccPremiereClass = '';
                                                        $examenPremiereClass = '';
                                                        $moyennePremiereClass = '';
                                                        $examenDeuxiemeClass = '';
                                                        $moyenneDeuxiemeClass = '';

                                                        // Récupérer les cotes pour la première session
                                                        if ($premiereSessionId && isset($cotesParEtudiant[$matricule][$premiereSessionId])) {
                                                            $cotePremiere = $cotesParEtudiant[$matricule][$premiereSessionId];

                                                            if (isset($cotePremiere['CC']) && $cotePremiere['CC'] !== null) {
                                                                $ccPremiere = number_format((float)$cotePremiere['CC'], 2);
                                                                $ccPremiereClass = (float)$cotePremiere['CC'] < 10 ? 'text-danger' : ((float)$cotePremiere['CC'] >= 16 ? 'text-success' : '');
                                                            }

                                                            if (isset($cotePremiere['EX']) && $cotePremiere['EX'] !== null) {
                                                                $examenPremiere = number_format((float)$cotePremiere['EX'], 2);
                                                                $examenPremiereClass = (float)$cotePremiere['EX'] < 10 ? 'text-danger' : ((float)$cotePremiere['EX'] >= 16 ? 'text-success' : '');
                                                            }

                                                            if (isset($cotePremiere['MF']) && $cotePremiere['MF'] !== null) {
                                                                $moyennePremiere = number_format((float)$cotePremiere['MF'], 2);
                                                                $moyennePremiereClass = (float)$cotePremiere['MF'] < 10 ? 'text-danger fw-bold' : ((float)$cotePremiere['MF'] >= 16 ? 'text-success fw-bold' : 'fw-bold');
                                                            }
                                                        }

                                                        // Récupérer les cotes pour la deuxième session
                                                        if ($deuxiemeSessionId && isset($cotesParEtudiant[$matricule][$deuxiemeSessionId])) {
                                                            $coteDeuxieme = $cotesParEtudiant[$matricule][$deuxiemeSessionId];

                                                            if (isset($coteDeuxieme['EX']) && $coteDeuxieme['EX'] !== null) {
                                                                $examenDeuxieme = number_format((float)$coteDeuxieme['EX'], 2);
                                                                $examenDeuxiemeClass = (float)$coteDeuxieme['EX'] < 10 ? 'text-danger' : ((float)$coteDeuxieme['EX'] >= 16 ? 'text-success' : '');
                                                            }

                                                            if (isset($coteDeuxieme['MF']) && $coteDeuxieme['MF'] !== null) {
                                                                $moyenneDeuxieme = number_format((float)$coteDeuxieme['MF'], 2);
                                                                $moyenneDeuxiemeClass = (float)$coteDeuxieme['MF'] < 10 ? 'text-danger fw-bold' : ((float)$coteDeuxieme['MF'] >= 16 ? 'text-success fw-bold' : 'fw-bold');
                                                            }
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td><?= $i++ ?></td>
                                                            <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                            <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                            <td class="text-center <?= $ccPremiereClass ?>"><?= $ccPremiere ?></td>
                                                            <td class="text-center <?= $examenPremiereClass ?>"><?= $examenPremiere ?></td>
                                                            <td class="text-center bg-light <?= $moyennePremiereClass ?>"><?= $moyennePremiere ?></td>
                                                            <td class="text-center <?= $examenDeuxiemeClass ?>"><?= $examenDeuxieme ?></td>
                                                            <td class="text-center bg-light <?= $moyenneDeuxiemeClass ?>"><?= $moyenneDeuxieme ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>

                                                    <?php if (count($etudiants) === 0): ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center">
                                                                <div class="alert alert-info mb-0">
                                                                    <i class="bi bi-info-circle me-2"></i>
                                                                    Aucun étudiant trouvé pour cet ECUE.
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php elseif (count($cotesGrille) === 0): ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center">
                                                                <div class="alert alert-info mb-0">
                                                                    <i class="bi bi-info-circle me-2"></i>
                                                                    Aucune note compilée n'est disponible pour cet ECUE. Veuillez d'abord compiler les notes dans l'onglet "Prévisualisation".
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>







                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Onglet Configuration -->
                            <div class="tab-pane fade" id="config-content" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Configuration des pondérations</h5>
                                        <form id="configForm" action="controller/evaluation_controller.php" method="POST">
                                            <input type="hidden" name="action" value="save_config">
                                            <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                                            <input type="hidden" name="annee_acad_id" value="<?= $currentYear['idannee_acad'] ?>">

                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-1"></i>
                                                <strong>Note:</strong> Cette configuration ne s'applique qu'à la première session. Pour la deuxième session, l'examen vaut automatiquement 100% de la note finale.
                                            </div>

                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0">Première session</h5>
                                                </div>
                                                <div class="card-body">
                                                    <input type="hidden" name="session_ids[]" value="<?= $premiereSession ?>">

                                                    <div class="row align-items-center mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Pondération Contrôle Continu (%)</label>
                                                            <input type="range" class="form-range" id="ponderation_cc_range" min="0" max="100" step="5"
                                                                value="<?= $configMoyenne ? $configMoyenne['ponderation_cc'] * 100 : 40 ?>">
                                                            <input type="hidden" name="ponderation_cc[<?= $premiereSession ?>]" id="ponderation_cc_value">
                                                            <input type="hidden" name="ponderation_ex[<?= $premiereSession ?>]" id="ponderation_ex_value">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="d-flex justify-content-between">
                                                                <div id="cc_percent" class="h4"></div>
                                                                <div class="h4">+</div>
                                                                <div id="ex_percent" class="h4"></div>
                                                                <div class="h4">=</div>
                                                                <div class="h4">100%</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="alert alert-secondary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <p><strong>Signification:</strong> Pour la première session, la note finale sera calculée comme suit:</p>
                                                                <ul>
                                                                    <li>La moyenne des Contrôles Continus comptera pour <span id="cc_weight"></span>% de la note finale</li>
                                                                    <li>La note d'Examen comptera pour <span id="ex_weight"></span>% de la note finale</li>
                                                                </ul>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Exemple:</strong> Avec cette configuration, si un étudiant a:</p>
                                                                <ul>
                                                                    <li>Une moyenne de 12/20 aux Contrôles Continus</li>
                                                                    <li>Une note de 14/20 à l'Examen</li>
                                                                    <li>Sa note finale sera: <span id="example_result"></span>/20</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save"></i> Enregistrer la configuration
                                                </button>
                                            </div>
                                        </form>
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

<!-- Modal pour ajouter une évaluation -->
<div class="modal fade" id="addEvaluationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une évaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Remplacer la partie correspondante dans le modal pour ajouter une évaluation -->
            <div class="modal-body">
                <form id="addEvaluationForm" action="controller/evaluation_controller.php" method="POST">
                    <input type="hidden" name="action" value="add_evaluation">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    <input type="hidden" name="idUser" value="<?= $userId ?>">
                    <input type="hidden" name="annee_acad_id" value="<?= $currentYear['idannee_acad'] ?>">

                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre de l'évaluation</label>
                        <input type="text" class="form-control" id="titre" name="titre" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (optionnelle)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_evaluation" class="form-label">Date de l'évaluation</label>
                            <input type="date" class="form-control" id="date_evaluation" name="date_evaluation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="idType" class="form-label">Type d'évaluation</label>
                            <select class="form-select" id="idType" name="idType" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($typesEvaluation as $type): ?>
                                    <option value="<?= $type['idType'] ?>"><?= htmlspecialchars($type['designationT']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="session_idsession" class="form-label">Session</label>
                            <select class="form-select" id="session_idsession" name="session_idsession" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= $session['idsession'] ?>"><?= htmlspecialchars($session['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="note_max" class="form-label">Note maximale</label>
                            <input type="number" class="form-control" id="note_max" name="note_max" value="20" min="1" max="100" step="1" required>
                            <div class="form-text">Note maximale pour cette évaluation (par défaut: 20)</div>
                        </div>
                        <div class="col-md-4" id="ponderationContainer">
                            <label for="ponderation" class="form-label">Pondération</label>
                            <input type="number" class="form-control" id="ponderation" name="ponderation" value="1" min="0.1" max="10" step="0.1" required>
                            <div class="form-text">Poids relatif dans la catégorie</div>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="est_visible" name="est_visible" value="1">
                        <label class="form-check-label" for="est_visible">Rendre visible pour les étudiants</label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- Modal pour modifier une évaluation -->
<div class="modal fade" id="editEvaluationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une évaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEvaluationForm" action="controller/evaluation_controller.php" method="POST">
                    <input type="hidden" name="action" value="update_evaluation">
                    <input type="hidden" name="idevaluation" id="edit_idevaluation">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">

                    <div class="mb-3">
                        <label for="edit_titre" class="form-label">Titre de l'évaluation</label>
                        <input type="text" class="form-control" id="edit_titre" name="titre" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description (optionnelle)</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_date_evaluation" class="form-label">Date de l'évaluation</label>
                            <input type="date" class="form-control" id="edit_date_evaluation" name="date_evaluation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_idType" class="form-label">Type d'évaluation</label>
                            <select class="form-select" id="edit_idType" name="idType" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($typesEvaluation as $type): ?>
                                    <option value="<?= $type['idType'] ?>" data-categorie="<?= $type['categorie'] ?>"><?= htmlspecialchars($type['designationT']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_session_idsession" class="form-label">Session</label>
                            <select class="form-select" id="edit_session_idsession" name="session_idsession" required>
                                <option value="">Sélectionnez...</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= $session['idsession'] ?>"><?= htmlspecialchars($session['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_note_max" class="form-label">Note maximale</label>
                            <input type="number" class="form-control" id="edit_note_max" name="note_max" min="1" max="100" step="1" required>
                            <small class="form-text text-muted">La note sera ramenée sur 20 lors de la compilation.</small>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_est_visible" name="est_visible" value="1">
                        <label class="form-check-label" for="edit_est_visible">Rendre visible pour les étudiants</label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour saisir les notes -->
<div class="modal fade" id="enterGradesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Saisie des notes - <span id="evaluation_title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="gradesForm" action="controller/evaluation_controller.php" method="POST">
                    <input type="hidden" name="action" value="save_grades">
                    <input type="hidden" name="idevaluation" id="grades_idevaluation">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    <input type="hidden" name="session_idsession" id="grades_session_id">
                    <input type="hidden" name="note_max" id="grades_note_max">

                    <!-- Options d'exportation et d'importation -->
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-info me-2" onclick="exportGradesTemplate()">
                            <i class="bi bi-file-earmark-excel"></i> Exporter modèle Excel
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="openImportGradesModal()">
                            <i class="bi bi-file-earmark-arrow-up"></i> Importer depuis Excel
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Matricule</th>
                                    <th>Nom de l'étudiant</th>
                                    <th>Note (<span id="note_max_display">20</span>)</th>
                                </tr>
                            </thead>
                            <tbody id="grades_table_body">
                                <!-- Les lignes seront générées dynamiquement par JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour importer des notes depuis Excel -->
<div class="modal fade" id="importGradesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des notes depuis Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importGradesForm" action="controller/import_grades.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="idevaluation" id="import_idevaluation" value="<?= $evaluation['idevaluation'] ?>">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Veuillez d'abord exporter le modèle Excel, remplir les notes, puis le réimporter ici.
                        <br>
                        <strong>Important :</strong> Ne modifiez pas la structure du fichier Excel.
                    </div>

                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel (*.xlsx)</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx" required>
                        <div class="form-text">Seuls les fichiers Excel (XLSX) sont acceptés.</div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmImport" name="confirmImport" required>
                        <label class="form-check-label" for="confirmImport">
                            Je confirme que les données importées sont correctes et correspondent à l'évaluation sélectionnée.
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Importer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter des points à la moyenne -->
<div class="modal fade" id="addPointsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter des points à la moyenne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPointsForm">
                    <div class="mb-3">
                        <label for="session_points" class="form-label">Session</label>
                        <select class="form-select" id="session_points" required>
                            <option value="">Sélectionnez une session...</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['idsession'] ?>"><?= htmlspecialchars($session['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="points_to_add" class="form-label">Points à ajouter</label>
                        <input type="number" class="form-control" id="points_to_add" min="0.1" max="5" step="0.1" value="0.5" required>
                        <div class="form-text">Valeur entre 0.1 et 5 points.</div>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Motif (optionnel)</label>
                        <textarea class="form-control" id="reason" rows="2" placeholder="Raison de l'ajout de points"></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_add_points" required>
                        <label class="form-check-label" for="confirm_add_points">
                            Je confirme vouloir ajouter ces points à tous les étudiants pour cette session.
                        </label>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Les points seront ajoutés à la moyenne finale de chaque étudiant, sans dépasser 20/20.
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary" onclick="previewAddPoints()">Prévisualiser</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Formulaires cachés pour les exports -->
<form id="exportTemplateForm" action="controller/export_notes_template.php" method="POST" style="display: none;">
    <input type="hidden" name="idevaluation" id="export_template_idevaluation">
    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
</form>

<form id="exportForm" action="controller/export_notes_evaluation.php" method="POST" style="display: none;">
    <input type="hidden" name="idevaluation" id="export_idevaluation">
    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
</form>

<form id="exportAllForm" action="controller/export_notes_ecue.php" method="POST" style="display: none;">
    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
</form>

<script>
    // Fonction pour ouvrir le modal de modification d'une évaluation
    function openEditEvaluationModal(evaluationId) {
        // Récupérer les données de l'évaluation via AJAX
        fetch(`controller/get_evaluation.php?id=${evaluationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire('Erreur', data.error, 'error');
                    return;
                }

                // Remplir le formulaire avec les données
                document.getElementById('edit_idevaluation').value = data.idevaluation;
                document.getElementById('edit_titre').value = data.titre;
                document.getElementById('edit_description').value = data.description || '';
                document.getElementById('edit_date_evaluation').value = data.date_evaluation;
                document.getElementById('edit_idType').value = data.idType;
                document.getElementById('edit_session_idsession').value = data.session_idsession;
                document.getElementById('edit_note_max').value = data.note_max || 20;
                document.getElementById('edit_est_visible').checked = data.est_visible == 1;

                // Afficher le modal
                new bootstrap.Modal(document.getElementById('editEvaluationModal')).show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire('Erreur', 'Une erreur est survenue lors de la récupération des données.', 'error');
            });
    }

    // Fonction pour exporter le modèle Excel pour une évaluation
    function exportGradesTemplate() {
        const evaluationId = document.getElementById('grades_idevaluation').value;
        document.getElementById('export_template_idevaluation').value = evaluationId;
        document.getElementById('exportTemplateForm').submit();
    }

    /**
     * Ouvre le modal de saisie des notes pour une évaluation donnée
     * Gère automatiquement les spécificités de la première ou deuxième session
     * 
     * @param {number} evaluationId - ID de l'évaluation
     * @param {string} evaluationTitle - Titre de l'évaluation
     */
    function openEnterGradesModal(evaluationId, evaluationTitle) {
        // Mettre à jour les informations dans le modal
        document.getElementById('evaluation_title').textContent = evaluationTitle;
        document.getElementById('grades_idevaluation').value = evaluationId;
        document.getElementById('import_idevaluation').value = evaluationId;

        // Afficher un indicateur de chargement
        const tableBody = document.getElementById('grades_table_body');
        tableBody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des données...</p>
            </td>
        </tr>
    `;

        // Ouvrir le modal pendant le chargement
        const modal = new bootstrap.Modal(document.getElementById('enterGradesModal'));
        modal.show();

        // Récupérer les notes existantes et les étudiants via AJAX
        fetch(`controller/get_grades.php?evaluation=${evaluationId}&ecue=<?= $idEcue ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP! Statut: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    // Si c'est une erreur concernant l'absence d'étudiants éligibles en 2e session
                    if (data.error.includes('Aucun étudiant n\'est éligible')) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Information',
                            text: data.error,
                            confirmButtonText: 'Compris'
                        });
                        // Fermer le modal
                        bootstrap.Modal.getInstance(document.getElementById('enterGradesModal')).hide();
                    } else {
                        // Autres erreurs
                        Swal.fire('Erreur', data.error, 'error');
                    }
                    return;
                }

                // Stocker l'ID de session pour le formulaire
                document.getElementById('grades_session_id').value = data.session_id;
                document.getElementById('grades_note_max').value = data.note_max || 20;
                document.getElementById('note_max_display').textContent = data.note_max || 20;

                // Vérifier si c'est un examen et si c'est une deuxième session
                const isExam = data.evaluation_category === 'EX';
                const isDeuxiemeSession = data.is_deuxieme_session;
                const maxNote = data.note_max || 20;

                // Supprimer les alertes précédentes s'il y en a
                const existingAlerts = document.querySelectorAll('#enterGradesModal .alert');
                existingAlerts.forEach(alert => alert.remove());

                // Ajouter un bandeau informatif selon le contexte
                let infoHtml = '';

                if (isDeuxiemeSession) {
                    // Message spécifique pour la deuxième session
                    infoHtml = `
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Mode deuxième session</strong> - Seuls les étudiants n'ayant pas validé l'UE en première session (moyenne < 10) sont affichés.
                    </div>`;

                    if (isExam) {
                        infoHtml += `
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            En deuxième session, l'examen compte pour 100% de la note finale.
                        </div>`;
                    }
                } else if (isExam) {
                    // Message pour un examen en première session
                    infoHtml = `
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Cet examen compte pour ${data.ponderation_examen}% de la note finale.
                    </div>`;
                } else {
                    // Message pour un contrôle continu
                    infoHtml = `
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Les contrôles continus comptent pour ${data.ponderation_cc}% de la note finale.
                    </div>`;
                }

                // Insérer le message d'info avant le tableau
                const modalBody = document.querySelector('#enterGradesModal .modal-body');
                if (modalBody) {
                    // Retirer les alertes précédentes
                    const existingMessages = modalBody.querySelectorAll('.alert-info, .alert-warning');
                    existingMessages.forEach(el => el.remove());

                    // Insérer le nouveau message au début
                    const firstChild = modalBody.firstChild;
                    if (firstChild) {
                        modalBody.insertBefore(document.createRange().createContextualFragment(infoHtml), firstChild);
                    } else {
                        modalBody.innerHTML = infoHtml + modalBody.innerHTML;
                    }
                }

                // Générer les lignes du tableau avec les étudiants et leurs notes
                tableBody.innerHTML = '';

                if (!data.students || data.students.length === 0) {
                    tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun étudiant trouvé pour cette évaluation.
                                ${isDeuxiemeSession ? 'Tous les étudiants ont validé l\'UE en première session.' : ''}
                            </div>
                        </td>
                    </tr>`;
                    return;
                }

                // Afficher les étudiants et leurs notes
                data.students.forEach((student, index) => {
                    const row = document.createElement('tr');

                    row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${student.matricule}</td>
                    <td>${student.noms}</td>
                    <td>
                        <input type="number" class="form-control" name="notes[${student.idetudiant}]" 
                               value="${student.note !== null ? student.note : ''}" 
                               min="0" max="${maxNote}" step="0.25">
                    </td>`;

                    tableBody.appendChild(row);
                });
            })
            .catch(error => {
                console.error('Erreur:', error);
                tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            Une erreur est survenue lors de la récupération des données.
                        </div>
                    </td>
                </tr>`;
            });
    }

    // Fonction pour confirmer la suppression d'une évaluation
    function confirmDeleteEvaluation(evaluationId) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir supprimer cette évaluation ? Cette action est irréversible et supprimera toutes les notes associées.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/evaluation_controller.php?action=delete_evaluation&id=${evaluationId}&ecue=<?= $idEcue ?>`;
            }
        });
    }

    // Fonction pour exporter les notes d'une évaluation
    function exportNotesEvaluation(evaluationId) {
        document.getElementById('export_idevaluation').value = evaluationId;
        document.getElementById('exportForm').submit();
    }

    // Fonction pour exporter toutes les notes
    function exportAllNotes() {
        document.getElementById('exportAllForm').submit();
    }

    // Fonction pour compiler les notes
    function compileAllNotes() {
        // Récupérer les sessions disponibles
        const sessions = <?= json_encode($sessions) ?>;

        // Créer les options pour le select
        const sessionOptions = sessions.map(session =>
            `<option value="${session.idsession}">${session.description}</option>`
        ).join('');

        // Afficher un dialogue avec sélection de session
        Swal.fire({
            title: 'Compiler les notes',
            html: `
            <p>Veuillez sélectionner la session pour laquelle vous souhaitez compiler les notes :</p>
            <select id="session-select" class="form-select mb-3">
                <option value="all">Toutes les sessions</option>
                ${sessionOptions}
            </select>
        `,
            showCancelButton: true,
            confirmButtonText: 'Prévisualiser',
            cancelButtonText: 'Annuler',
            preConfirm: () => {
                return document.getElementById('session-select').value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const sessionId = result.value;

                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Prévisualisation en cours...',
                    html: 'Génération de la prévisualisation des notes. Veuillez patienter.',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false
                });

                // Faire la requête AJAX pour prévisualiser les notes
                fetch('controller/preview_compilation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'idECUE': <?= $idEcue ?>,
                            'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                            'session_id': sessionId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Générer le tableau de prévisualisation
                            let previewHTML = `
                        <div class="preview-container" style="max-height: 70vh; overflow-y: auto;">
                            <h5 class="mb-3">Prévisualisation des notes - ${data.ecue.designationECUE}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Matricule</th>
                                            <th>Nom de l'étudiant</th>
                                            <th class="text-center">CC (1ère session)</th>
                                            <th class="text-center">Examen (1ère session)</th>
                                            <th class="text-center bg-light">Moyenne (1ère session)</th>
                                            <th class="text-center">Examen (2ème session)</th>
                                            <th class="text-center bg-light">Moyenne (2ème session)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;

                            // Dans la fonction loadPreviewData(), modifiez la partie qui génère chaque ligne du tableau :
                            data.students.forEach((student, index) => {
                                // Définir les classes CSS pour les notes (ne change pas)
                                const ccClass = student.premiere_session.cc < 10 ? 'text-danger' : (student.premiere_session.cc >= 16 ? 'text-success' : '');
                                const exClass = student.premiere_session.ex < 10 ? 'text-danger' : (student.premiere_session.ex >= 16 ? 'text-success' : '');
                                const mfClass = student.premiere_session.mf < 10 ? 'text-danger fw-bold' : (student.premiere_session.mf >= 16 ? 'text-success fw-bold' : 'fw-bold');
                                const ex2Class = student.deuxieme_session.ex < 10 ? 'text-danger' : (student.deuxieme_session.ex >= 16 ? 'text-success' : '');
                                const mf2Class = student.deuxieme_session.mf < 10 ? 'text-danger fw-bold' : (student.deuxieme_session.mf >= 16 ? 'text-success fw-bold' : 'fw-bold');

                                // Générer la ligne avec des contrôles stricts pour les valeurs nulles
                                previewHTML += `
        <tr>
            <td>${index + 1}</td>
            <td>${student.matricule}</td>
            <td>
                ${student.noms}
                <button class="btn btn-sm btn-link p-0 ms-2" onclick='showStudentDetails("${student.matricule}", ${JSON.stringify(student).replace(/'/g, "\\'")})'><i class="bi bi-info-circle"></i></button>
            </td>
            <td class="text-center ${ccClass}">${student.premiere_session.cc !== null ? student.premiere_session.cc.toFixed(2) : '-'}</td>
            <td class="text-center ${exClass}">${student.premiere_session.ex !== null ? student.premiere_session.ex.toFixed(2) : '-'}</td>
            <td class="text-center bg-light ${mfClass}">${student.premiere_session.mf !== null ? student.premiere_session.mf.toFixed(2) : '-'}</td>
            <td class="text-center ${ex2Class}">${student.deuxieme_session.ex !== null ? student.deuxieme_session.ex.toFixed(2) : '-'}</td>
            <td class="text-center bg-light ${mf2Class}">${student.deuxieme_session.mf !== null ? student.deuxieme_session.mf.toFixed(2) : '-'}</td>
        </tr>
    `;
                            });


                            previewHTML += `
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 d-flex justify-content-between">
                                    <div>
                                        <p><strong>Configuration:</strong> CC: ${data.config ? (data.config.ponderation_cc * 100).toFixed(0) : 40}%, Examen: ${data.config ? (data.config.ponderation_ex * 100).toFixed(0) : 60}% (1ère session)</p>
                                        <p><strong>Note:</strong> En 2ème session, l'examen compte pour 100% de la note finale.</p>
                                    </div>
                                    <button class="btn btn-success" onclick="exportPreviewToExcel()">
                                        <i class="bi bi-file-excel me-1"></i> Exporter en Excel
                                    </button>
                                </div>
                            </div>
                    `;

                            // Afficher la prévisualisation et demander confirmation
                            Swal.fire({
                                title: 'Prévisualisation des notes',
                                html: previewHTML,
                                width: '80%',
                                showCancelButton: true,
                                confirmButtonText: 'Confirmer la compilation',
                                cancelButtonText: 'Annuler',
                                customClass: {
                                    container: 'swal-wide',
                                    content: 'swal-tall-content'
                                }
                            }).then((confirmResult) => {
                                if (confirmResult.isConfirmed) {
                                    // Si l'utilisateur confirme, procéder à la compilation réelle
                                    Swal.fire({
                                        title: 'Compilation en cours...',
                                        html: 'Compilation des notes en cours. Veuillez patienter.',
                                        didOpen: () => {
                                            Swal.showLoading();
                                        },
                                        allowOutsideClick: false,
                                        allowEscapeKey: false,
                                        allowEnterKey: false
                                    });

                                    // Faire la requête AJAX pour compiler les notes
                                    fetch('controller/compile_notes.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: new URLSearchParams({
                                                'idECUE': <?= $idEcue ?>,
                                                'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                                                'session_id': sessionId
                                            })
                                        })
                                        .then(response => response.json())
                                        .then(compileData => {
                                            if (compileData.success) {
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Succès',
                                                    text: compileData.message || 'Les notes ont été compilées et envoyées à la grille avec succès.'
                                                }).then(() => {
                                                    // Recharger la page pour afficher les notes mises à jour
                                                    window.location.reload();
                                                });
                                            } else {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Erreur',
                                                    text: compileData.message || 'Une erreur est survenue lors de la compilation des notes.'
                                                });
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Erreur:', error);
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Erreur',
                                                text: 'Une erreur est survenue lors de la communication avec le serveur.'
                                            });
                                        });
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de la prévisualisation des notes.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la communication avec le serveur.'
                        });
                    });
            }
        });
    }


    // Fonction pour ouvrir le modal d'import depuis le modal de saisie des notes
    function openImportGradesModal() {
        // Récupérer l'ID d'évaluation depuis le modal de saisie
        const evaluationId = document.getElementById('grades_idevaluation').value;

        // Vérifier que nous avons bien un ID d'évaluation
        if (!evaluationId) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'ID d\'évaluation non défini. Veuillez réessayer.'
            });
            return;
        }

        // Définir l'ID d'évaluation dans le formulaire d'importation
        document.getElementById('import_idevaluation').value = evaluationId;

        // Fermer le modal de saisie si ouvert
        const enterGradesModal = bootstrap.Modal.getInstance(document.getElementById('enterGradesModal'));
        if (enterGradesModal) {
            enterGradesModal.hide();
        }

        // Ouvrir le modal d'importation
        const importModal = new bootstrap.Modal(document.getElementById('importGradesModal'));
        importModal.show();
    }



    function loadPreviewData() {
    const sessionId = document.getElementById('preview-session').value;
    const previewContainer = document.getElementById('preview-container');
    
    // Afficher un indicateur de chargement
    previewContainer.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des données...</p>
        </div>
    `;
    
    // Masquer le bouton d'export pendant le chargement
    document.getElementById('exportPreviewBtn').style.display = 'none';
    
    // Faire la requête AJAX pour prévisualiser les notes
    fetch('controller/preview_compilation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'idECUE': <?= $idEcue ?>,
            'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
            'session_id': sessionId
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log("Données reçues:", data); // Log pour débogage
        
        if (data.success) {
            // Calculer les statistiques
            const stats = calculateStatistics(data.students);
            
            // Générer le tableau de prévisualisation
            let previewHTML = `
                <div class="preview-container">
                    <h5 class="mb-3">Prévisualisation des notes - ${data.ecue.designationECUE}</h5>
                    
                    <!-- Carte des statistiques -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Statistiques</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-success">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-success">Taux de réussite</h6>
                                                    <h2 class="mb-0">${stats.successRate}%</h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-danger">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-danger">Taux d'échec</h6>
                                                    <h2 class="mb-0">${stats.failureRate}%</h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-primary">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-primary">Moyenne générale</h6>
                                                    <h2 class="mb-0">${stats.averageGrade.toFixed(2)}</h2>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-warning">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-warning">Cotes manquantes</h6>
                                                    <h2 class="mb-0">${stats.missingGradesRate}%</h2>
                                                    <small>${stats.missingGradesCount} étudiant(s)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Répartition des notes -->
                            <div class="mt-3">
                                <h6>Répartition des notes</h6>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: ${stats.gradeDistribution.failed}%;" 
                                        title="Échec (<10): ${stats.gradeDistribution.failed}%">
                                        ${stats.gradeDistribution.failed}%
                                    </div>
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: ${stats.gradeDistribution.passable}%;" 
                                        title="Passable (10-12): ${stats.gradeDistribution.passable}%">
                                        ${stats.gradeDistribution.passable}%
                                    </div>
                                    <div class="progress-bar bg-info" role="progressbar" style="width: ${stats.gradeDistribution.assezBien}%;" 
                                        title="Assez bien (12-14): ${stats.gradeDistribution.assezBien}%">
                                        ${stats.gradeDistribution.assezBien}%
                                    </div>
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: ${stats.gradeDistribution.bien}%;" 
                                        title="Bien (14-16): ${stats.gradeDistribution.bien}%">
                                        ${stats.gradeDistribution.bien}%
                                    </div>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: ${stats.gradeDistribution.tresBien}%;" 
                                        title="Très bien (16-20): ${stats.gradeDistribution.tresBien}%">
                                        ${stats.gradeDistribution.tresBien}%
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-1 small">
                                    <span>Échec (<10)</span>
                                    <span>Passable (10-12)</span>
                                    <span>Assez bien (12-14)</span>
                                    <span>Bien (14-16)</span>
                                    <span>Très bien (16-20)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tableau des notes -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Matricule</th>
                                    <th>Nom de l'étudiant</th>
                                    <th class="text-center">CC (1ère session)</th>
                                    <th class="text-center">Examen (1ère session)</th>
                                    <th class="text-center bg-light">Moyenne (1ère session)</th>
                                    <th class="text-center">Examen (2ème session)</th>
                                    <th class="text-center bg-light">Moyenne (2ème session)</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            data.students.forEach((student, index) => {
                // Accéder directement aux données provenant de l'API
                const ccValue = student.premiere_session.cc;
                const exValue = student.premiere_session.ex;
                const mfValue = student.premiere_session.mf;
                const ex2Value = student.deuxieme_session.ex;
                const mf2Value = student.deuxieme_session.mf;
                
                // Définir les classes CSS pour les notes
                const ccClass = ccValue !== null ? (ccValue < 10 ? 'text-danger' : (ccValue >= 16 ? 'text-success' : '')) : '';
                const exClass = exValue !== null ? (exValue < 10 ? 'text-danger' : (exValue >= 16 ? 'text-success' : '')) : '';
                const mfClass = mfValue !== null ? (mfValue < 10 ? 'text-danger fw-bold' : (mfValue >= 16 ? 'text-success fw-bold' : 'fw-bold')) : '';
                const ex2Class = ex2Value !== null ? (ex2Value < 10 ? 'text-danger' : (ex2Value >= 16 ? 'text-success' : '')) : '';
                const mf2Class = mf2Value !== null ? (mf2Value < 10 ? 'text-danger fw-bold' : (mf2Value >= 16 ? 'text-success fw-bold' : 'fw-bold')) : '';
                
                // Ajouter une icône d'avertissement si des notes sont manquantes
                const warningIcon = (!student.meta.cc_premiere_complete || !student.meta.ex_premiere_complete || !student.meta.ex_deuxieme_complete) 
                    ? '<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Notes incomplètes"></i>' : '';
                
                    previewHTML += `
        <tr>
            <td>${index + 1}</td>
            <td>${student.matricule}</td>
            <td>
                ${student.noms} ${warningIcon}
                <button class="btn btn-sm btn-link p-0 ms-2" onclick="showStudentDetailsById('${student.matricule}')"><i class="bi bi-info-circle"></i></button>
            </td>
            <td class="text-center ${ccClass}">${ccValue !== null && ccValue !== undefined ? Number(ccValue).toFixed(2) : '-'}</td>
            <td class="text-center ${exClass}">${exValue !== null && exValue !== undefined ? Number(exValue).toFixed(2) : '-'}</td>
            <td class="text-center bg-light ${mfClass}">${mfValue !== null && mfValue !== undefined ? Number(mfValue).toFixed(2) : '-'}</td>
            <td class="text-center ${ex2Class}">${ex2Value !== null && ex2Value !== undefined ? Number(ex2Value).toFixed(2) : '-'}</td>
            <td class="text-center bg-light ${mf2Class}">${mf2Value !== null && mf2Value !== undefined ? Number(mf2Value).toFixed(2) : '-'}</td>
        </tr>
    `;
            });
            
            previewHTML += `
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex justify-content-between">
                        <div>
                            <p><strong>Configuration:</strong> CC: ${data.config ? (data.config.ponderation_cc * 100).toFixed(0) : 40}%, Examen: ${data.config ? (data.config.ponderation_ex * 100).toFixed(0) : 60}% (1ère session)</p>
                            <p><strong>Note:</strong> En 2ème session, l'examen compte pour 100% de la note finale.</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="compileNotesFromPreview()">
                                <i class="bi bi-calculator me-1"></i> Compiler les notes
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Afficher les données
            previewContainer.innerHTML = previewHTML;
            
            // Afficher le bouton d'export
            document.getElementById('exportPreviewBtn').style.display = 'inline-block';
            document.getElementById('exportPreviewBtn').onclick = function() {
                exportPreviewToExcel();
            };
            
            // Stocker les données pour une utilisation ultérieure
            window.allStudentsData = {};
            data.students.forEach(student => {
                window.allStudentsData[student.matricule] = student;
            });
        } else {
            previewContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    ${data.message || 'Une erreur est survenue lors de la prévisualisation des notes.'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        previewContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i>
                Une erreur est survenue lors de la communication avec le serveur.
            </div>
        `;
    });
}

// Fonction pour afficher les détails d'un étudiant par son matricule
function showStudentDetailsById(matricule) {
    if (!window.allStudentsData || !window.allStudentsData[matricule]) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Données de l\'étudiant non trouvées.'
        });
        return;
    }
    
    const studentData = window.allStudentsData[matricule];
    
    try {
        showStudentDetails(matricule, studentData);
    } catch (error) {
        console.error("Erreur lors de l'affichage des détails:", error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur technique',
            text: 'Une erreur s\'est produite lors de l\'affichage des détails: ' + error.message
        });
    }
}



    

    // Fonction pour calculer les statistiques
    function calculateStatistics(students) {
        // Initialiser les compteurs
        let totalStudents = students.length;
        let totalPassedFirstSession = 0;
        let totalPassedSecondSession = 0;
        let totalWithDistinction = 0;
        let sumGrades = 0;
        let validGradesCount = 0;
        let missingGradesCount = 0;

        // Compteurs pour la distribution des notes
        let failed = 0; // < 10
        let passable = 0; // 10-12
        let assezBien = 0; // 12-14
        let bien = 0; // 14-16
        let tresBien = 0; // 16-20

        // Parcourir les étudiants
        students.forEach(student => {
            // Ne compter que les étudiants avec des notes valides
            // Déterminer la note finale (priorité à la 2ème session si disponible)
            let finalGrade = null;
            if (student.deuxieme_session.mf !== null) {
                finalGrade = student.deuxieme_session.mf;
                if (finalGrade >= 10) {
                    totalPassedSecondSession++;
                }
            } else if (student.premiere_session.mf !== null) {
                finalGrade = student.premiere_session.mf;
                if (finalGrade >= 10) {
                    totalPassedFirstSession++;
                }
            }

            // Si une note finale est disponible
            if (finalGrade !== null) {
                // Ajouter à la somme pour calculer la moyenne
                sumGrades += finalGrade;
                validGradesCount++;

                // Compter pour la distribution des notes
                if (finalGrade < 10) {
                    failed++;
                } else if (finalGrade < 12) {
                    passable++;
                } else if (finalGrade < 14) {
                    assezBien++;
                } else if (finalGrade < 16) {
                    bien++;
                } else {
                    tresBien++;
                    totalWithDistinction++;
                }
            } else {
                // Compter les étudiants sans note finale
                missingGradesCount++;
            }
        });

        // Calculer les taux
        const totalPassed = totalPassedFirstSession + totalPassedSecondSession;
        const successRate = totalStudents > 0 ? Math.round((totalPassed / totalStudents) * 100) : 0;
        const failureRate = 100 - successRate;
        const averageGrade = validGradesCount > 0 ? sumGrades / validGradesCount : 0;
        const missingGradesRate = totalStudents > 0 ? Math.round((missingGradesCount / totalStudents) * 100) : 0;

        // Calculer les pourcentages pour la distribution des notes
        const gradeDistribution = {
            failed: totalStudents > 0 ? Math.round((failed / totalStudents) * 100) : 0,
            passable: totalStudents > 0 ? Math.round((passable / totalStudents) * 100) : 0,
            assezBien: totalStudents > 0 ? Math.round((assezBien / totalStudents) * 100) : 0,
            bien: totalStudents > 0 ? Math.round((bien / totalStudents) * 100) : 0,
            tresBien: totalStudents > 0 ? Math.round((tresBien / totalStudents) * 100) : 0
        };

        return {
            totalStudents,
            totalPassed,
            totalPassedFirstSession,
            totalPassedSecondSession,
            successRate,
            failureRate,
            distinctionRate: totalStudents > 0 ? Math.round((totalWithDistinction / totalStudents) * 100) : 0,
            averageGrade,
            gradeDistribution,
            missingGradesCount,
            missingGradesRate
        };
    }


    // Fonction pour compiler les notes à partir de la prévisualisation
    function compileNotesFromPreview() {
        const sessionId = document.getElementById('preview-session').value;

        Swal.fire({
            title: 'Confirmation',
            text: 'Êtes-vous sûr de vouloir compiler ces notes ? Cette action mettra à jour les moyennes dans la grille de notes.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Oui, compiler',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un indicateur de chargement
                Swal.fire({
                    title: 'Compilation en cours...',
                    html: 'Compilation des notes en cours. Veuillez patienter.',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false
                });

                // Faire la requête AJAX pour compiler les notes
                fetch('controller/compile_notes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'idECUE': <?= $idEcue ?>,
                            'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                            'session_id': sessionId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succès',
                                text: data.message || 'Les notes ont été compilées et envoyées à la grille avec succès.'
                            }).then(() => {
                                // Recharger la prévisualisation
                                loadPreviewData();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de la compilation des notes.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la communication avec le serveur.'
                        });
                    });
            }
        });
    }

    // Fonction pour exporter la prévisualisation vers Excel
    
    function exportPreviewToExcel() {
    // Créer un élément temporaire pour contenir le tableau
    const tempTable = document.createElement('table');
    tempTable.innerHTML = document.querySelector('.preview-container table').innerHTML;

    // Créer un livre Excel
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(tempTable);

    // Ajouter la feuille au livre
    XLSX.utils.book_append_sheet(wb, ws, "Prévisualisation Notes");

    // Générer le fichier Excel et le télécharger
    const ecueTitle = document.querySelector('.preview-container h5').textContent.replace('Prévisualisation des notes - ', '');
    const fileName = `Prévisualisation_Notes_${ecueTitle.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0,10)}.xlsx`;
    XLSX.writeFile(wb, fileName);
}

    
    // Fonction pour afficher les détails de notes d'un étudiant
    function showStudentDetails(matricule, studentData) {
    console.log("Données étudiant:", studentData); // Pour débogage
    
    // Générer le HTML pour les détails des notes
    let detailsHTML = `
        <div class="student-details">
            <h5 class="mb-3">Détails des notes - ${studentData.noms} (${matricule})</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th>Session</th>
                            <th class="text-center">Note</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    // Vérifier si les données de détails existent
    if (studentData.notes_detail && studentData.notes_detail.length > 0) {
        // Créer un tableau pour suivre les évaluations uniques par leur ID
        const displayedEvaluations = new Set();
        
        // Trier les notes par session puis par type
        const sortedNotes = [...studentData.notes_detail].sort((a, b) => {
            if (a.session !== b.session) {
                return a.session.includes('Première') ? -1 : 1;
            }
            return a.categorie === 'CC' ? -1 : 1;
        });
        
        sortedNotes.forEach(note => {
            // Vérifier si cette évaluation a déjà été affichée
            const evalKey = note.evaluation_id.toString();
            if (displayedEvaluations.has(evalKey)) {
                return; // Ignorer cette évaluation si déjà affichée
            }
            
            // Marquer cette évaluation comme affichée
            displayedEvaluations.add(evalKey);
            
            const noteValue = note.note;
            const noteClass = noteValue !== null ? 
                (noteValue < 10 ? 'text-danger' : (noteValue >= 16 ? 'text-success' : '')) : '';
            
            const noteDisplay = noteValue !== null ? 
                noteValue.toFixed(2) : 
                '<span class="text-warning">Non évalué</span>';
            
            detailsHTML += `
                <tr>
                    <td>${note.titre}</td>
                    <td>${note.type}</td>
                    <td>${note.session}</td>
                    <td class="text-center ${noteClass}">${noteDisplay}</td>
                </tr>
            `;
        });
    } else {
        detailsHTML += `
            <tr>
                <td colspan="4" class="text-center">Aucune note disponible</td>
            </tr>
        `;
    }

    detailsHTML += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <h6>Résumé:</h6>
                <ul>
                    <li><strong>Première session:</strong> 
                        CC: ${studentData.premiere_session.cc !== null ? studentData.premiere_session.cc.toFixed(2) : '-'}, 
                        Examen: ${studentData.premiere_session.ex !== null ? studentData.premiere_session.ex.toFixed(2) : '-'}, 
                        Moyenne: ${studentData.premiere_session.mf !== null ? studentData.premiere_session.mf.toFixed(2) : '-'}
                    </li>
                    <li><strong>Deuxième session:</strong> 
                        Examen: ${studentData.deuxieme_session.ex !== null ? studentData.deuxieme_session.ex.toFixed(2) : '-'}, 
                        Moyenne: ${studentData.deuxieme_session.mf !== null ? studentData.deuxieme_session.mf.toFixed(2) : '-'}
                    </li>
                </ul>
            </div>
        </div>
    `;

    // Ajouter un avertissement si des évaluations sont manquantes
    if (studentData.meta) {
        const warnings = [];
        
        if (!studentData.meta.cc_premiere_complete && studentData.meta.total_evals_cc_premiere > 0) {
            warnings.push(`Contrôles continus incomplets (${studentData.meta.has_cc_premiere}/${studentData.meta.total_evals_cc_premiere})`);
        }
        
        if (!studentData.meta.ex_premiere_complete && studentData.meta.total_evals_ex_premiere > 0) {
            warnings.push(`Examen première session incomplet (${studentData.meta.has_ex_premiere}/${studentData.meta.total_evals_ex_premiere})`);
        }
        
        if (!studentData.meta.ex_deuxieme_complete && studentData.meta.total_evals_ex_deuxieme > 0) {
            warnings.push(`Examen deuxième session incomplet (${studentData.meta.has_ex_deuxieme}/${studentData.meta.total_evals_ex_deuxieme})`);
        }
        
        if (warnings.length > 0) {
            detailsHTML += `
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Attention:</strong> Certaines notes sont manquantes, ce qui empêche le calcul complet des moyennes.
                    <ul class="mb-0 mt-2">
                        ${warnings.map(w => `<li>${w}</li>`).join('')}
                    </ul>
                </div>
            `;
        }
    }

    Swal.fire({
        title: 'Détails des notes',
        html: detailsHTML,
        width: '800px',
        confirmButtonText: 'Fermer'
    });
}







    // Fonction pour ouvrir le modal d'ajout de points
    function openAddPointsModal() {
        const modal = new bootstrap.Modal(document.getElementById('addPointsModal'));
        modal.show();
    }

    // Fonction pour prévisualiser l'ajout de points
    function previewAddPoints() {
        const sessionId = document.getElementById('session_points').value;
        const pointsToAdd = parseFloat(document.getElementById('points_to_add').value);
        const reason = document.getElementById('reason').value;
        const confirmed = document.getElementById('confirm_add_points').checked;

        if (!sessionId) {
            Swal.fire('Erreur', 'Veuillez sélectionner une session.', 'error');
            return;
        }

        if (isNaN(pointsToAdd) || pointsToAdd < 0.1 || pointsToAdd > 5) {
            Swal.fire('Erreur', 'Les points à ajouter doivent être entre 0.1 et 5.', 'error');
            return;
        }

        if (!confirmed) {
            Swal.fire('Erreur', 'Veuillez confirmer l\'ajout de points.', 'error');
            return;
        }

        // Afficher un indicateur de chargement
        Swal.fire({
            title: 'Prévisualisation en cours...',
            html: 'Génération de la prévisualisation. Veuillez patienter.',
            didOpen: () => {
                Swal.showLoading();
            },
            allowOutsideClick: false
        });

        // Faire la requête AJAX pour prévisualiser l'ajout de points
        fetch('controller/preview_add_points.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'idECUE': <?= $idEcue ?>,
                    'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                    'session_id': sessionId,
                    'points_to_add': pointsToAdd
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fermer le modal d'ajout de points
                    bootstrap.Modal.getInstance(document.getElementById('addPointsModal')).hide();

                    // Générer le tableau de prévisualisation
                    let previewHTML = `
                <div>
                    <h5 class="mb-3">Prévisualisation de l'ajout de points - ${data.session_name}</h5>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Une nouvelle évaluation de type "Points bonus" sera créée avec une note maximale de ${pointsToAdd} points.
                        Tous les étudiants recevront cette note comme un bonus, ce qui impactera leur moyenne générale.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Matricule</th>
                                    <th>Nom de l'étudiant</th>
                                    <th class="text-center">Note actuelle</th>
                                    <th class="text-center">Estimation nouvelle note</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    data.students.forEach((student, index) => {
                        const currentClass = student.current_note < 10 ? 'text-danger' : (student.current_note >= 16 ? 'text-success' : '');
                        const newClass = student.new_note < 10 ? 'text-danger' : (student.new_note >= 16 ? 'text-success' : '');

                        previewHTML += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${student.matricule}</td>
                        <td>${student.noms}</td>
                        <td class="text-center ${currentClass}">${student.current_note.toFixed(2)}</td>
                        <td class="text-center ${newClass} fw-bold">${student.new_note.toFixed(2)}</td>
                    </tr>`;
                    });

                    previewHTML += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <p><strong>Résumé:</strong> ${data.summary.total_students} étudiants au total</p>
                    <p><strong>Détails:</strong></p>
                    <ul>
                        <li>${data.summary.students_with_notes} étudiants avec des notes complètes</li>
                        <li>${data.summary.students_without_notes} étudiants sans notes complètes (exclus)</li>
                        <li>${data.summary.students_affected} étudiants recevront des points</li>
                    </ul>
                </div>`;

                    // Afficher la prévisualisation et demander confirmation
                    Swal.fire({
                        title: 'Prévisualisation de l\'ajout de points',
                        html: previewHTML,
                        width: '800px',
                        showCancelButton: true,
                        confirmButtonText: 'Créer l\'évaluation bonus',
                        cancelButtonText: 'Annuler',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Créer l'évaluation bonus avec les points
                            Swal.fire({
                                title: 'Ajout de points en cours...',
                                html: 'Création de l\'évaluation bonus en cours. Veuillez patienter.',
                                didOpen: () => {
                                    Swal.showLoading();
                                },
                                allowOutsideClick: false
                            });

                            fetch('controller/add_points.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams({
                                        'idECUE': <?= $idEcue ?>,
                                        'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                                        'session_id': sessionId,
                                        'points_to_add': pointsToAdd,
                                        'reason': reason,
                                        'recompile': 1
                                    })
                                })
                                .then(response => response.json())
                                .then(result => {
                                    if (result.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Succès',
                                            text: result.message,
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            // Recharger la page pour afficher la nouvelle évaluation
                                            window.location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Erreur',
                                            text: result.message
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Erreur:', error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Erreur',
                                        text: 'Une erreur est survenue lors de la communication avec le serveur.'
                                    });
                                });
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la prévisualisation.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
            });
    }


    



    // Fonction pour calculer les statistiques
    function calculateStatistics(students) {
    // Initialiser les compteurs
    let totalStudents = students.length;
    let totalPassedFirstSession = 0;
    let totalPassedSecondSession = 0;
    let totalWithDistinction = 0;
    let sumGrades = 0;
    let validGradesCount = 0;
    let missingGradesCount = 0;
    
    // Compteurs pour la distribution des notes
    let failed = 0;        // < 10
    let passable = 0;      // 10-12
    let assezBien = 0;     // 12-14
    let bien = 0;          // 14-16
    let tresBien = 0;      // 16-20
    
    // Parcourir les étudiants
    students.forEach(student => {
        // Déterminer la note finale (priorité à la 2ème session si disponible)
        let finalGrade = null;
        
        if (student.deuxieme_session.mf !== null) {
            finalGrade = student.deuxieme_session.mf;
            if (finalGrade >= 10) {
                totalPassedSecondSession++;
            }
        } else if (student.premiere_session.mf !== null) {
            finalGrade = student.premiere_session.mf;
            if (finalGrade >= 10) {
                totalPassedFirstSession++;
            }
        }
        
        // Si une note finale est disponible
        if (finalGrade !== null) {
            // Ajouter à la somme pour calculer la moyenne
            sumGrades += finalGrade;
            validGradesCount++;
            
            // Compter pour la distribution des notes
            if (finalGrade < 10) {
                failed++;
            } else if (finalGrade < 12) {
                passable++;
            } else if (finalGrade < 14) {
                assezBien++;
            } else if (finalGrade < 16) {
                bien++;
            } else {
                tresBien++;
                totalWithDistinction++;
            }
        } else {
            // Compter les étudiants sans note finale
            missingGradesCount++;
        }
    });
    
    // Calculer les taux
    const totalPassed = totalPassedFirstSession + totalPassedSecondSession;
    const successRate = totalStudents > 0 ? Math.round((totalPassed / totalStudents) * 100) : 0;
    const failureRate = 100 - successRate;
    const distinctionRate = totalStudents > 0 ? Math.round((totalWithDistinction / totalStudents) * 100) : 0;
    const averageGrade = validGradesCount > 0 ? sumGrades / validGradesCount : 0;
    const missingGradesRate = totalStudents > 0 ? Math.round((missingGradesCount / totalStudents) * 100) : 0;
    
    // Calculer les pourcentages pour la distribution des notes
    const gradeDistribution = {
        failed: totalStudents > 0 ? Math.round((failed / totalStudents) * 100) : 0,
        passable: totalStudents > 0 ? Math.round((passable / totalStudents) * 100) : 0,
        assezBien: totalStudents > 0 ? Math.round((assezBien / totalStudents) * 100) : 0,
        bien: totalStudents > 0 ? Math.round((bien / totalStudents) * 100) : 0,
        tresBien: totalStudents > 0 ? Math.round((tresBien / totalStudents) * 100) : 0
    };
    
    return {
        totalStudents,
        totalPassed,
        totalPassedFirstSession,
        totalPassedSecondSession,
        successRate,
        failureRate,
        distinctionRate,
        averageGrade,
        gradeDistribution,
        missingGradesCount,
        missingGradesRate
    };
}



    function showEvaluationDetails(categorie, evaluations) {
        if (!evaluations || evaluations.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Information',
                text: 'Aucune évaluation disponible pour cette catégorie.'
            });
            return;
        }

        let detailsHTML = `
        <div class="evaluation-details">
            <h5 class="mb-3">Détails des évaluations - ${categorie}</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th class="text-center">Note/20</th>
                            <th class="text-center">Pondération</th>
                            <th class="text-center">Contribution</th>
                        </tr>
                    </thead>
                    <tbody>`;

        let totalPonderation = 0;
        evaluations.forEach(eval => {
            totalPonderation += eval.ponderation;
        });

        evaluations.forEach(eval => {
            const noteClass = eval.note < 10 ? 'text-danger' : (eval.note >= 16 ? 'text-success' : '');
            const contribution = (eval.note * eval.ponderation / totalPonderation).toFixed(2);

            detailsHTML += `
            <tr>
                <td>${eval.titre || 'Non spécifié'}</td>
                <td>${eval.type || categorie}</td>
                <td class="text-center ${noteClass}">${eval.note.toFixed(2)}</td>
                <td class="text-center">${eval.ponderation || '1.00'}</td>
                <td class="text-center">${contribution}</td>
            </tr>`;
        });

        // Calculer et ajouter la ligne de moyenne
        const moyenneEvaluations = evaluations.reduce((sum, eval) => sum + (eval.note * eval.ponderation), 0) /
            evaluations.reduce((sum, eval) => sum + eval.ponderation, 0);

        const moyenneClass = moyenneEvaluations < 10 ? 'text-danger' : (moyenneEvaluations >= 16 ? 'text-success' : '');

        detailsHTML += `
                <tr class="table-secondary">
                    <td colspan="2" class="fw-bold text-end">Moyenne:</td>
                    <td class="text-center fw-bold ${moyenneClass}">${moyenneEvaluations.toFixed(2)}</td>
                    <td class="text-center fw-bold">${totalPonderation.toFixed(2)}</td>
                    <td class="text-center fw-bold">${moyenneEvaluations.toFixed(2)}</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Note:</strong> La contribution est calculée en tenant compte de la pondération relative de chaque évaluation.
    </div>
</div>`;

        Swal.fire({
            title: `Détails des évaluations - ${categorie}`,
            html: detailsHTML,
            width: '800px',
            confirmButtonText: 'Fermer'
        });
    }

    function compileNotesFromPreview() {
    const sessionId = document.getElementById('preview-session').value;
    
    Swal.fire({
        title: 'Confirmation',
        text: 'Êtes-vous sûr de vouloir compiler ces notes ? Cette action mettra à jour les moyennes dans la grille de notes.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, compiler',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher un indicateur de chargement
            Swal.fire({
                title: 'Compilation en cours...',
                html: 'Compilation des notes en cours. Veuillez patienter.',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false
            });
            
            // Faire la requête AJAX pour compiler les notes
            fetch('controller/compile_notes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'idECUE': <?= $idEcue ?>,
                    'annee_acad_id': <?= $currentYear['idannee_acad'] ?>,
                    'session_id': sessionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: data.message || 'Les notes ont été compilées et envoyées à la grille avec succès.'
                    }).then(() => {
                        // Recharger la prévisualisation
                        loadPreviewData();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la compilation des notes.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
            });
        }
    });
}



    // Fonction pour compiler les notes à partir de la prévisualisation
   

    document.addEventListener('DOMContentLoaded', function() {
        // Configuration de la pondération
        const ccRange = document.getElementById('ponderation_cc_range');
        const ccValue = document.getElementById('ponderation_cc_value');
        const ccPercent = document.getElementById('cc_percent');
        const exPercent = document.getElementById('ex_percent');
        const ccWeight = document.getElementById('cc_weight');
        const exWeight = document.getElementById('ex_weight');
        const exampleResult = document.getElementById('example_result');

        document.getElementById('importGradesModal').addEventListener('show.bs.modal', function(event) {
            const evaluationId = document.getElementById('grades_idevaluation').value;
            document.getElementById('import_idevaluation').value = evaluationId;
        });



        function updateValues() {
            if (!ccRange) return;

            const ccVal = parseInt(ccRange.value);
            const exVal = 100 - ccVal;
            const sessionId = <?= $premiereSession ?>; // Obtenir l'ID de session du PHP

            // Mettre à jour les champs cachés avec les bonnes clés de tableau
            document.getElementById('ponderation_cc_value').value = ccVal / 100;
            document.getElementById('ponderation_ex_value').value = exVal / 100;

            // Ajouter ces lignes pour le débogage
            console.log('Pondération CC:', ccVal / 100, 'Session ID:', sessionId);
            console.log('Pondération EX:', exVal / 100, 'Session ID:', sessionId);

            ccPercent.textContent = `CC: ${ccVal}%`;
            exPercent.textContent = `Examen: ${exVal}%`;
            ccWeight.textContent = ccVal;
            exWeight.textContent = exVal;

            // Calcul de l'exemple
            const exampleCC = 12;
            const exampleEX = 14;
            const result = ((exampleCC * ccVal / 100) + (exampleEX * exVal / 100)).toFixed(2);
            exampleResult.textContent = result;
        }


        if (ccRange) {
            ccRange.addEventListener('input', updateValues);
            // Initialisation
            updateValues();
        }

        // Initialisation des tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Validation des formulaires d'évaluation
        function validateEvaluationForm(form) {
            const sessionSelect = form.querySelector('[name="session_idsession"]');
            const typeSelect = form.querySelector('[name="idType"]');

            if (!sessionSelect || !typeSelect) return true;

            // Récupérer les valeurs sélectionnées
            const sessionId = sessionSelect.value;
            const typeId = typeSelect.value;
            const sessionText = sessionSelect.options[sessionSelect.selectedIndex].text.toLowerCase();

            // Récupérer la catégorie du type d'évaluation
            const typeOption = typeSelect.options[typeSelect.selectedIndex];
            const typeCategorie = typeOption ? typeOption.getAttribute('data-categorie') : '';
            const typeText = typeOption ? typeOption.text.toLowerCase() : '';

            // Vérifier si c'est la deuxième session
            const isDeuxiemeSession = sessionText.includes('deuxième') || sessionText.includes('deuxieme');

            // 1. Contrôle continu non autorisé en deuxième session
            if (isDeuxiemeSession && (typeCategorie === 'CC' || typeText.includes('contrôle') || typeText.includes('controle') || typeText.includes('cc'))) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation échouée',
                    text: 'Les contrôles continus ne sont pas autorisés en deuxième session.'
                });
                return false;
            }

            // 2. Vérifier s'il existe déjà un examen pour cette session
            if (typeCategorie === 'EX' || typeText.includes('examen')) {
                // Récupérer toutes les évaluations existantes
                const evaluations = <?= json_encode($evaluations) ?>;
                const currentEvalId = form.querySelector('[name="idevaluation"]')?.value;

                // Filtrer pour trouver un examen existant pour cette session
                const existingExam = evaluations.find(eval => {
                    // Exclure l'évaluation actuelle en cas de modification
                    if (currentEvalId && eval.idevaluation == currentEvalId) return false;

                    return eval.session_idsession == sessionId &&
                        (eval.categorie === 'EX' || eval.designationT.toLowerCase().includes('examen'));
                });

                if (existingExam) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation échouée',
                        text: 'Un examen existe déjà pour cette session dans ce cours.'
                    });
                    return false;
                }
            }

            return true;
        }

        // Appliquer la validation aux formulaires
        const addForm = document.getElementById('addEvaluationForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                if (!validateEvaluationForm(this)) {
                    e.preventDefault();
                }
            });
        }

        const editForm = document.getElementById('editEvaluationForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                if (!validateEvaluationForm(this)) {
                    e.preventDefault();
                }
            });
        }

        // Initialiser le bouton de compilation
        const compileBtn = document.getElementById('compileNotesBtn');
        if (compileBtn) {
            compileBtn.addEventListener('click', compileAllNotes);
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>