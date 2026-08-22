<?php
include_once "./views/include/header.php";

// Vérifier que l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'Enseignant' && $_SESSION['role'] != 'Administrateur') {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

$conn = Connexion::getInstance()->getPDO();

// Récupérer l'idAgent de l'enseignant connecté
$query_agent = "SELECT idAgent FROM t_users WHERE idUser = :id_user";
$stmt_agent = $conn->prepare($query_agent);
$stmt_agent->bindParam(':id_user', $_SESSION['id']);
$stmt_agent->execute();
$agent = $stmt_agent->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucun profil enseignant associé à votre compte.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

$id_agent = $agent['idAgent'];

// Récupérer les filtres
$id_annee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$id_session = isset($_GET['session']) ? intval($_GET['session']) : 0;
$statut = isset($_GET['statut']) ? $_GET['statut'] : '';

// Récupérer les années académiques pour le filtre
$query_annees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC";
$stmt_annees = $conn->prepare($query_annees);
$stmt_annees->execute();
$annees = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la dernière année académique si aucune n'est sélectionnée
if ($id_annee == 0 && !empty($annees)) {
    $id_annee = $annees[0]['idannee_acad'];
}

// Récupérer les sessions pour le filtre
$query_sessions = "SELECT idsession, designSession FROM session ORDER BY idsession";
$stmt_sessions = $conn->prepare($query_sessions);
$stmt_sessions->execute();
$sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

// Construire la requête pour récupérer les recours
$query_recours = "
    SELECT r.id_recours, r.matricule, e.noms as nom_etudiant,
           ec.designationECUE, r.motif, r.date_creation, r.statut,
           s.designSession, a.designation as annee_acad,
           CASE WHEN rr.id_reponse IS NOT NULL THEN 1 ELSE 0 END as a_reponse
    FROM recours r
    LEFT JOIN etudiant e ON r.matricule = e.matricule
    LEFT JOIN ecue ec ON r.id_ecue = ec.idECUE
    LEFT JOIN session s ON r.id_session = s.idsession
    LEFT JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
    LEFT JOIN recours_reponse rr ON r.id_recours = rr.id_recours
    WHERE ec.idECUE IN (
        SELECT ee.idECUE 
        FROM enseignant_ecue ee 
        WHERE ee.idAgent = :id_agent
    )
    AND r.id_annee_acad = :id_annee";

// Ajouter les filtres optionnels
$params = [':id_agent' => $id_agent, ':id_annee' => $id_annee];

if ($id_session > 0) {
    $query_recours .= " AND r.id_session = :id_session";
    $params[':id_session'] = $id_session;
}

if (!empty($statut)) {
    $query_recours .= " AND r.statut = :statut";
    $params[':statut'] = $statut;
}

// Trier par date de création (plus récent en premier) et par statut
$query_recours .= " ORDER BY r.date_creation DESC";

$stmt_recours = $conn->prepare($query_recours);
foreach ($params as $key => $value) {
    $stmt_recours->bindValue($key, $value);
}
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Mes Recours à Traiter</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignant</li>
                <li class="breadcrumb-item active">Mes Recours</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filtres -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        
                        <form action="" method="GET" class="row g-3">
                            <!-- Année académique -->
                            <div class="col-md-4">
                                <label for="annee" class="form-label">Année académique</label>
                                <select class="form-select" id="annee" name="annee">
                                    <?php foreach($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" 
                                                <?= $annee['idannee_acad'] == $id_annee ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Session -->
                            <div class="col-md-4">
                                <label for="session" class="form-label">Session</label>
                                <select class="form-select" id="session" name="session">
                                    <option value="0">Toutes les sessions</option>
                                    <?php foreach($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" 
                                                <?= $session['idsession'] == $id_session ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['designSession']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Statut -->
                            <div class="col-md-4">
                                <label for="statut" class="form-label">Statut</label>
                                <select class="form-select" id="statut" name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="En attente" <?= $statut == 'En attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="En traitement" <?= $statut == 'En traitement' ? 'selected' : '' ?>>En traitement</option>
                                    <option value="Approuvé" <?= $statut == 'Approuvé' ? 'selected' : '' ?>>Approuvé</option>
                                    <option value="Rejeté" <?= $statut == 'Rejeté' ? 'selected' : '' ?>>Rejeté</option>
                                </select>
                            </div>
                            
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des recours -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recours à traiter</h5>
                        
                        <?php if (count($recours) == 0): ?>
                        <div class="alert alert-info">
                            Aucun recours trouvé pour les critères sélectionnés.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Étudiant</th>
                                        <th>ECUE</th>
                                        <th>Session</th>
                                        <th>Motif</th>
                                        <th>Date de dépôt</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recours as $r): 
                                        // Définir une classe CSS pour le statut
                                        $statut_class = '';
                                        switch($r['statut']) {
                                            case 'En attente': $statut_class = 'badge bg-warning'; break;
                                            case 'En traitement': $statut_class = 'badge bg-info'; break;
                                            case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                            case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                            default: $statut_class = 'badge bg-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['matricule']) ?></td>
                                        <td><?= htmlspecialchars($r['nom_etudiant']) ?></td>
                                        <td><?= htmlspecialchars($r['designationECUE']) ?></td>
                                        <td><?= htmlspecialchars($r['designSession']) ?></td>
                                        <td><?= htmlspecialchars($r['motif']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($r['date_creation'])) ?></td>
                                        <td><span class="<?= $statut_class ?>"><?= htmlspecialchars($r['statut']) ?></span></td>
                                        <td>
                                            <a href="deliberation/recours.details?id=<?= $r['id_recours'] ?>" class="btn btn-sm btn-primary">
                                                <?php if ($r['a_reponse']): ?>
                                                <i class="bi bi-eye"></i> Voir
                                                <?php else: ?>
                                                <i class="bi bi-reply"></i> Répondre
                                                <?php endif; ?>
                                            </a>
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
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit du formulaire lors du changement des filtres
    document.getElementById('annee').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('session').addEventListener('change', function() {
        this.form.submit();
    });
    
    document.getElementById('statut').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php include_once "./views/include/footer.php"; ?>
