<?php include "./views/include/header.php"; ?>

<?php
// Initialisation des variables pour éviter les erreurs
$resultsFound = false;
$etudiants = [];
$searchPerformed = false;
$searchCriteria = [];

// Traitement de la recherche si un formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchPerformed = true;
    
    // Connexion à la base de données
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupération des paramètres de recherche
    $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $promotion = isset($_POST['promotion']) ? $_POST['promotion'] : '';
    
    // Enregistrement des critères pour les afficher
    if (!empty($matricule)) $searchCriteria[] = "Matricule: " . htmlspecialchars($matricule);
    if (!empty($nom)) $searchCriteria[] = "Nom: " . htmlspecialchars($nom);
    
    // Construction de la requête SQL
    $sql = "SELECT 
                e.idetudiant, 
                e.matricule, 
                e.noms, 
                e.photo,
                p.\"designationPromotion\", 
                s.\"designationSection\",
                a.designation as annee_academique,
                p.idpromotion as promotion_id,
                COALESCE(sf.total_du, 0) as total_du,
                COALESCE(sf.total_paye, 0) as total_paye,
                COALESCE(sf.solde, 0) as solde
            FROM etudiant e
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
            LEFT JOIN situation_financiere_etudiant sf ON e.idetudiant = sf.etudiant_id AND sf.annee_acad_id = a.idannee_acad
            WHERE 1=1 AND e.est_actif=1";
    
    $params = [];
    
    // Ajout des conditions selon les critères de recherche
    if (!empty($matricule)) {
        $sql .= " AND e.matricule LIKE :matricule";
        $params[':matricule'] = "%$matricule%";
    }
    
    if (!empty($nom)) {
        $sql .= " AND e.noms LIKE :nom";
        $params[':nom'] = "%$nom%";
    }
    
    if (!empty($promotion)) {
        $sql .= " AND p.idpromotion = :promotion";
        $params[':promotion'] = $promotion;
        
        // Récupérer le nom de la promotion pour l'affichage
        $stmtPromo = $connexion->prepare("SELECT \"designationPromotion\" FROM promotion WHERE idpromotion = :id");
        $stmtPromo->bindParam(':id', $promotion);
        $stmtPromo->execute();
        $promoName = $stmtPromo->fetchColumn();
        if ($promoName) {
            $searchCriteria[] = "Promotion: " . htmlspecialchars($promoName);
        }
    }
    
    // Ordre de tri et limitation des résultats
    $sql .= " ORDER BY e.noms ASC LIMIT 100";
    
    try {
        $stmt = $connexion->prepare($sql);
        
        // Liaison des paramètres
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultsFound = count($etudiants) > 0;
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la recherche: " . $e->getMessage();
    }
}

// Récupération des promotions pour le formulaire de recherche avancée
$connexion = Connexion::getInstance()->getPDO();
$stmt = $connexion->prepare("
    SELECT 
        p.idpromotion, 
        p.\"designationPromotion\", 
        a.designation as annee_academique 
    FROM promotion p 
    JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad 
    ORDER BY a.designation DESC, p.\"designationPromotion\" ASC");
$stmt->execute();
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Situation Financière des Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item"><a href="finance/rapport">Rapports</a></li>
                <li class="breadcrumb-item active">Recherche Situation Étudiant</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Alerte d'erreur éventuelle -->
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-search text-primary me-2"></i>
                            Rechercher un étudiant
                        </h5>
                        
                        <!-- Formulaires de recherche -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm border-primary">
                                    <div class="card-header bg-primary text-white py-3">
                                        <h5 class="card-title mb-0" style="color: white !important;">
                                            <i class="bi bi-person-badge me-2"></i> Recherche par matricule
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="matricule" class="form-label">Matricule de l'étudiant</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                                    <input type="text" class="form-control" id="matricule" name="matricule" 
                                                           placeholder="Ex: 2023001" value="<?= isset($_POST['matricule']) ? htmlspecialchars($_POST['matricule']) : '' ?>">
                                                </div>
                                                <small class="text-muted">Entrez le matricule exact ou une partie du matricule</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-search me-2"></i> Rechercher
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm border-success">
                                    <div class="card-header bg-success text-white py-3">
                                        <h5 class="card-title mb-0" style="color: white !important;">
                                            <i class="bi bi-filter-circle me-2"></i> Recherche avancée
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="post" action="">
                                            <div class="mb-3">
                                                <label for="nom" class="form-label">Nom de l'étudiant</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                    <input type="text" class="form-control" id="nom" name="nom" 
                                                           placeholder="Nom ou prénom" value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="promotion" class="form-label">Promotion</label>
                                                <select class="form-select" id="promotion" name="promotion">
                                                    <option value="">Toutes les promotions</option>
                                                    <?php 
                                                    $currentYear = null;
                                                    foreach ($promotions as $promo): 
                                                        // Afficher un séparateur d'optgroup pour chaque nouvelle année académique
                                                        if ($currentYear !== $promo['annee_academique']):
                                                            if ($currentYear !== null): 
                                                                echo '</optgroup>';
                                                            endif;
                                                            $currentYear = $promo['annee_academique'];
                                                            echo '<optgroup label="' . htmlspecialchars($currentYear) . '">';
                                                        endif;
                                                    ?>
                                                        <option value="<?= $promo['idpromotion'] ?>" <?= (isset($_POST['promotion']) && $_POST['promotion'] == $promo['idpromotion']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($promo['designationPromotion']) ?>
                                                        </option>
                                                    <?php 
                                                    endforeach; 
                                                    // Fermer le dernier optgroup si existant
                                                    if ($currentYear !== null): 
                                                        echo '</optgroup>';
                                                    endif;
                                                    ?>
                                                </select>
                                            </div>

                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-filter me-2"></i> Filtrer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Résultats de la recherche -->
                        <?php if ($searchPerformed): ?>
                            <!-- Affichage des critères de recherche -->
                            <div class="alert alert-info mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                                    <div>
                                        <strong>Recherche effectuée avec les critères :</strong>
                                        <ul class="mb-0 mt-1">
                                            <?php foreach ($searchCriteria as $criteria): ?>
                                                <li><?= $criteria ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($resultsFound): ?>
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light py-3">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-list-ul text-primary me-2"></i>
                                            Résultats de la recherche (<?= count($etudiants) ?> étudiant<?= count($etudiants) > 1 ? 's' : '' ?>)
                                        </h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th scope="col" class="ps-4">Matricule</th>
                                                        <th scope="col">Photo</th>
                                                        <th scope="col">Nom</th>
                                                        <th scope="col">Promotion</th>
                                                        <th scope="col">Section</th>
                                                        <th scope="col">Situation</th>
                                                        <th scope="col" class="text-end pe-4">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($etudiants as $etudiant): ?>
                                                        <tr>
                                                            <td class="ps-4"><strong><?= htmlspecialchars($etudiant['matricule']) ?></strong></td>
                                                            <td>
                                                                <?php if (!empty($etudiant['photo'])): ?>
                                                                    <img src="<?= htmlspecialchars($etudiant['photo']) ?>" alt="Photo" class="rounded-circle shadow-sm" width="45" height="45">
                                                                <?php else: ?>
                                                                    <img src="uploads/user.png" alt="Photo" class="rounded-circle shadow-sm" width="45" height="45">
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><span class="fw-medium"><?= htmlspecialchars($etudiant['noms']) ?></span></td>
                                                            <td><?= htmlspecialchars($etudiant['designationPromotion']) ?></td>
                                                            <td><?= htmlspecialchars($etudiant['designationSection']) ?></td>
                                                            <td>
                                                                <?php 
                                                                $solde = $etudiant['solde'] ?? 0;
                                                                $badge_class = $solde > 0 ? 'danger' : 'success';
                                                                $badge_text = $solde > 0 ? 'Doit ' . number_format($solde, 2, ',', ' ') . ' USD' : 'À jour';
                                                                ?>
                                                                <span class="badge bg-<?= $badge_class ?> rounded-pill px-3 py-2">
                                                                    <i class="bi bi-<?= $solde > 0 ? 'exclamation-triangle' : 'check-circle' ?> me-1"></i>
                                                                    <?= $badge_text ?>
                                                                </span>
                                                                </td>
                                                            <td class="text-end pe-4">
                                                                <a href="finance/rapport/etudiants.situation?id=<?= $etudiant['idetudiant'] ?>" class="btn btn-primary btn-sm">
                                                                    <i class="bi bi-eye me-1"></i> Consulter
                                                                </a>
                                                                <a href="controller/situation_etudiant.php?id=<?= $etudiant['idetudiant'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm ms-1">
                                                                    <i class="bi bi-printer me-1"></i> Imprimer
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center mb-4">
                                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Aucun résultat trouvé</h5>
                                        <p class="mb-0">Aucun étudiant ne correspond aux critères de recherche spécifiés. Veuillez modifier vos critères et réessayer.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Conseils pour la recherche si aucune recherche n'a été effectuée -->
                        <?php if (!$searchPerformed): ?>
                        <div class="card bg-light border-0 mt-4">
                            <div class="card-body p-4">
                                <h5 class="text-primary mb-3">
                                    <i class="bi bi-lightbulb-fill me-2"></i>
                                    Conseils pour la recherche
                                </h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-group list-group-flush bg-transparent">
                                            <li class="list-group-item bg-transparent ps-0">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                Utilisez le <strong>matricule</strong> pour une recherche précise
                                            </li>
                                            <li class="list-group-item bg-transparent ps-0">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                Recherchez par <strong>nom</strong> si vous ne connaissez pas le matricule exact
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-group list-group-flush bg-transparent">
                                            <li class="list-group-item bg-transparent ps-0">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                Filtrez par <strong>promotion</strong> pour affiner les résultats
                                            </li>
                                            <li class="list-group-item bg-transparent ps-0">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                Consultez la <strong>situation complète</strong> pour voir tous les détails financiers
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* Améliorations de style pour la page */
.card {
    border-radius: 0.75rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    overflow: hidden;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.table img {
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.input-group-text {
    background-color: #f8f9fa;
}

.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

.bg-primary, .btn-primary {
    background-color: #4154f1;
    border-color: #4154f1;
}

.bg-success, .btn-success {
    background-color: #2eca6a;
    border-color: #2eca6a;
}

.border-primary {
    border-color: #4154f1 !important;
}

.border-success {
    border-color: #2eca6a !important;
}

.alert-info {
    background-color: #f0f6ff;
    border-color: #d6e4ff;
    color: #0a47a9;
}

.table th {
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-size: 0.75rem;
    background-color: #f8f9fa;
}

.table-hover tbody tr:hover {
    background-color: rgba(65, 84, 241, 0.05);
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-weight: 500;
}

.card-title {
    font-weight: 600;
    color: #333;
}

/* Animation pour les cartes */
.card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}
</style>

<?php include "./views/include/footer.php"; ?>

