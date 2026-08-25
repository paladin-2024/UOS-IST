<?php
include "./views/include/header.php";

// Initialisation de la connexion
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer l'idAgent de l'utilisateur connecté
$stmt = $connexion->prepare("SELECT \"idAgent\" FROM t_users WHERE \"idUser\" = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_agent = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_agent['idAgent'] ?? null;

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

// Paramètres de filtrage
$type_rapport = isset($_GET['type_rapport']) ? $_GET['type_rapport'] : 'echeanciers_periode';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d', strtotime('+30 days'));
$promotion_id = isset($_GET['promotion_id']) ? $_GET['promotion_id'] : '';
$categorie_id = isset($_GET['categorie_id']) ? $_GET['categorie_id'] : '';
$annee_acad_id = isset($_GET['annee_acad_id']) ? $_GET['annee_acad_id'] : '';
$statut_paiement = isset($_GET['statut_paiement']) ? $_GET['statut_paiement'] : '';

// Récupérer les années académiques
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les promotions filtrées par année académique si nécessaire
$promotionQuery = "
    SELECT p.idpromotion, p.\"designationPromotion\", 
           CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte,
           aa.designation AS annee_academique,
           aa.idannee_acad
    FROM promotion p
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section s ON o.section_idsection = s.idsection
    JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
";

// Ajouter le filtre d'année académique si sélectionné
if (!empty($annee_acad_id)) {
    $promotionQuery .= " WHERE aa.idannee_acad = :annee_acad_id";
}

$promotionQuery .= " ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"";

$stmt = $connexion->prepare($promotionQuery);
if (!empty($annee_acad_id)) {
    $stmt->bindParam(':annee_acad_id', $annee_acad_id);
}
$stmt->execute();
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories de frais
$stmt = $connexion->prepare("SELECT id, designation FROM categories_frais ORDER BY designation");
$stmt->execute();
$categories_frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les données selon le type de rapport
$echeanciers = [];

if (!empty($_GET)) {
    $params = [];
    $where_clauses = [];
    
    // Conditions de base pour tous les rapports
    if (!empty($promotion_id)) {
        $where_clauses[] = "p.idpromotion = :promotion_id";
        $params[':promotion_id'] = $promotion_id;
    }
    
    if (!empty($categorie_id)) {
        $where_clauses[] = "cf.id = :categorie_id";
        $params[':categorie_id'] = $categorie_id;
    }
    
    if (!empty($annee_acad_id)) {
        $where_clauses[] = "aa.idannee_acad = :annee_acad_id";
        $params[':annee_acad_id'] = $annee_acad_id;
    }
    
    // Conditions spécifiques selon le type de rapport
    switch ($type_rapport) {
        case 'echeanciers_periode':
            $where_clauses[] = "ep.date_echeance BETWEEN :date_debut AND :date_fin";
            $params[':date_debut'] = $date_debut;
            $params[':date_fin'] = $date_fin;
            break;
            
        case 'echeanciers_retard':
            $where_clauses[] = "ep.date_echeance < CURRENT_DATE";
            $where_clauses[] = "ep.statut_paiement != 'Complet'";
            break;
            
        case 'echeanciers_venir':
            $where_clauses[] = "ep.date_echeance > CURRENT_DATE";
            break;
    }
    
    // Filtrer par statut de paiement si spécifié
    if (!empty($statut_paiement)) {
        $where_clauses[] = "ep.statut_paiement = :statut_paiement";
        $params[':statut_paiement'] = $statut_paiement;
    }
    
    // Construire la clause WHERE
    $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Requête pour récupérer les échéanciers
    $query = "
        SELECT 
            ep.id, 
            ep.numero_tranche, 
            ep.designation AS tranche_designation, 
            ep.montant, 
            ep.date_echeance, 
            ep.statut_paiement,
            ep.montant_paye,
            ep.montant_restant,
            af.id AS affectation_id,
            f.id AS frais_id,
            f.designation AS frais_designation,
            cf.designation AS categorie_nom,
            e.matricule,
            e.noms,
            CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte,
            p.\"designationPromotion\",
            aa.designation AS annee_academique,
            (ep.montant - COALESCE(ep.montant_paye, 0)) AS solde,
            CASE 
                WHEN ep.date_echeance < CURRENT_DATE AND ep.statut_paiement != 'Complet' THEN (CURRENT_DATE - ep.date_echeance)
                ELSE 0
            END AS jours_retard
        FROM echelonnement_paiement ep
        JOIN affectation_frais af ON ep.affectation_id = af.id
        JOIN frais f ON af.frais_id = f.id
        JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN etudiant e ON af.matricule_etudiant = e.matricule
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
        LEFT JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        $where_clause
        ORDER BY ep.date_echeance, s.\"designationSection\", p.\"designationPromotion\", e.noms
    ";
    
    $stmt = $connexion->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $echeanciers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Rapports des Échéanciers</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Rapports des Échéanciers</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulaire de filtrage -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres de rapport</h5>
                        <form action="" method="GET" class="row g-3" id="filterForm">
                            <input type="hidden" name="view" value="finance/echeanciers">

                            <div class="col-md-4">
                                <label for="type_rapport" class="form-label">Type de rapport</label>
                                <select class="form-select" id="type_rapport" name="type_rapport" onchange="toggleDateFields()">
                                    <option value="echeanciers_periode" <?= $type_rapport === 'echeanciers_periode' ? 'selected' : '' ?>>Échéanciers sur une période</option>
                                    <option value="echeanciers_retard" <?= $type_rapport === 'echeanciers_retard' ? 'selected' : '' ?>>Échéanciers en retard</option>
                                    <option value="echeanciers_venir" <?= $type_rapport === 'echeanciers_venir' ? 'selected' : '' ?>>Échéanciers à venir</option>
                                </select>
                            </div>

                            <div class="col-md-4 date-field" id="date_debut_container">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= $date_debut ?>">
                            </div>

                            <div class="col-md-4 date-field" id="date_fin_container">
                                <label for="date_fin" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= $date_fin ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="annee_acad_id" class="form-label">Année académique</label>
                                <select class="form-select" id="annee_acad_id" name="annee_acad_id" onchange="submitForm()">
                                    <option value="">Toutes les années</option>
                                    <?php foreach($annees_academiques as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $annee_acad_id == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="promotion_id" class="form-label">Promotion</label>
                                <select class="form-select" id="promotion_id" name="promotion_id">
                                    <option value="">Toutes les promotions</option>
                                    <?php foreach($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" <?= $promotion_id == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promotion['faculte'] . ' - ' . $promotion['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="categorie_id" class="form-label">Catégorie de frais</label>
                                <select class="form-select" id="categorie_id" name="categorie_id">
                                    <option value="">Toutes les catégories</option>
                                    <?php foreach($categories_frais as $categorie): ?>
                                        <option value="<?= $categorie['id'] ?>" <?= $categorie_id == $categorie['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($categorie['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="statut_paiement" class="form-label">Statut de paiement</label>
                                <select class="form-select" id="statut_paiement" name="statut_paiement">
                                    <option value="">Tous les statuts</option>
                                    <option value="Non payé" <?= $statut_paiement === 'Non payé' ? 'selected' : '' ?>>Non payé</option>
                                    <option value="Partiel" <?= $statut_paiement === 'Partiel' ? 'selected' : '' ?>>Partiel</option>
                                    <option value="Complet" <?= $statut_paiement === 'Complet' ? 'selected' : '' ?>>Complet</option>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search me-1"></i> Générer le rapport
                                </button>
                                <?php if (!empty($echeanciers)): ?>
                                    <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                        <i class="bi bi-file-excel me-1"></i> Exporter en Excel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Résultats du rapport -->
        <?php if (!empty($_GET)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php
                                switch ($type_rapport) {
                                    case 'echeanciers_periode':
                                        echo "Échéanciers du " . date('d/m/Y', strtotime($date_debut)) . " au " . date('d/m/Y', strtotime($date_fin));
                                        break;
                                    case 'echeanciers_retard':
                                        echo "Échéanciers en retard de paiement";
                                        break;
                                    case 'echeanciers_venir':
                                        echo "Prochains échéanciers à venir";
                                        break;
                                }
                                ?>
                            </h5>

                            <?php if (empty($echeanciers)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun échéancier ne correspond aux critères de recherche.
                                </div>
                            <?php else: ?>
                                <!-- Statistiques sommaires -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body py-3">
                                                <h6 class="card-title mb-0">Total des échéances</h6>
                                                <h3 class="mt-2 mb-0"><?= count($echeanciers) ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body py-3">
                                                <h6 class="card-title mb-0">Montant total</h6>
                                                <h3 class="mt-2 mb-0">
                                                    <?= number_format(array_sum(array_column($echeanciers, 'montant')), 2) ?> USD
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-white">
                                            <div class="card-body py-3">
                                                <h6 class="card-title mb-0">Montant restant</h6>
                                                <h3 class="mt-2 mb-0">
                                                    <?= number_format(array_sum(array_column($echeanciers, 'solde')), 2) ?> USD
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-danger text-white">
                                            <div class="card-body py-3">
                                                <h6 class="card-title mb-0">Échéances en retard</h6>
                                                <h3 class="mt-2 mb-0">
                                                    <?= count(array_filter($echeanciers, function($e) { return $e['jours_retard'] > 0; })) ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tableau des échéanciers -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped datatable" id="echeanciers-table">
                                        <thead>
                                            <tr>
                                                <th>Matricule</th>
                                                <th>Nom</th>
                                                <th>Promotion</th>
                                                <th>Frais</th>
                                                <th>Tranche</th>
                                                <th>Montant</th>
                                                <th>Payé</th>
                                                <th>Solde</th>
                                                <th>Échéance</th>
                                                <th>Statut</th>
                                                <th>Retard (jours)</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($echeanciers as $echeancier): ?>
                                                <tr class="<?= $echeancier['jours_retard'] > 0 ? 'table-danger' : ($echeancier['statut_paiement'] === 'Complet' ? 'table-success' : '') ?>">
                                                    <td><?= htmlspecialchars($echeancier['matricule']) ?></td>
                                                    <td><?= htmlspecialchars($echeancier['noms']) ?></td>
                                                    <td><?= htmlspecialchars($echeancier['designationPromotion']) ?></td>
                                                    <td><?= htmlspecialchars($echeancier['frais_designation']) ?></td>
                                                    <td><?= htmlspecialchars($echeancier['tranche_designation']) ?></td>
                                                    <td><?= number_format($echeancier['montant'], 2) ?> USD</td>
                                                    <td><?= number_format($echeancier['montant_paye'], 2) ?> USD</td>
                                                    <td><?= number_format($echeancier['solde'], 2) ?> USD</td>
                                                    <td><?= date('d/m/Y', strtotime($echeancier['date_echeance'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $echeancier['statut_paiement'] === 'Complet' ? 'success' : ($echeancier['statut_paiement'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                            <?= htmlspecialchars($echeancier['statut_paiement']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($echeancier['jours_retard'] > 0): ?>
                                                            <strong class="text-danger"><?= $echeancier['jours_retard'] ?> jours</strong>
                                                        <?php else: ?>
                                                            0
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="?view=finance/paiements_etudiants&type_recherche=matricule&matricule=<?= $echeancier['matricule'] ?>" class="btn btn-sm btn-primary" title="Voir détails">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($echeancier['statut_paiement'] !== 'Complet'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#paiementTrancheModal" 
                                                                    data-affectation-id="<?= $echeancier['affectation_id'] ?>"
                                                                    data-echelonnement-id="<?= $echeancier['id'] ?>"
                                                                    data-frais-designation="<?= htmlspecialchars($echeancier['frais_designation'] . ' - ' . $echeancier['tranche_designation']) ?>"
                                                                    data-montant-restant="<?= $echeancier['solde'] ?>"
                                                                    data-devise="USD"
                                                                    data-matricule="<?= $echeancier['matricule'] ?>"
                                                                    title="Enregistrer un paiement">
                                                                <i class="bi bi-cash-coin"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-info" onclick="sendRappel('<?= $echeancier['matricule'] ?>', '<?= $echeancier['id'] ?>')" title="Envoyer un rappel">
                                                            <i class="bi bi-bell"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-dark">
                                                <th colspan="5">Total</th>
                                                <th><?= number_format(array_sum(array_column($echeanciers, 'montant')), 2) ?> USD</th>
                                                <th><?= number_format(array_sum(array_column($echeanciers, 'montant_paye')), 2) ?> USD</th>
                                                <th><?= number_format(array_sum(array_column($echeanciers, 'solde')), 2) ?> USD</th>
                                                <th colspan="4"></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour paiement de tranche -->
<div class="modal fade" id="paiementTrancheModal" tabindex="-1" aria-labelledby="paiementTrancheModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/save_paiement.php" method="POST">
                <input type="hidden" name="action" value="paiement_tranche">
                <input type="hidden" name="affectation_id" id="tranche_affectation_id">
                <input type="hidden" name="echelonnement_id" id="echelonnement_id">
                <input type="hidden" name="matricule_etudiant" id="matricule_etudiant">
                <input type="hidden" name="redirect" value="finance/echeanciers">

                <div class="modal-header">
                    <h5 class="modal-title" id="paiementTrancheModalLabel">Enregistrer un paiement de tranche</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong>Frais:</strong> <span id="tranche_frais_designation"></span><br>
                                <strong>Montant restant à payer:</strong> <span id="tranche_montant_restant"></span> <span id="tranche_devise"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tranche_montant" class="form-label">Montant à payer <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="tranche_montant" name="montant" required>
                                <span class="input-group-text" id="tranche_devise_input"></span>
                            </div>
                            <small class="form-text text-muted">Le montant doit être inférieur ou égal au montant restant à payer.</small>
                        </div>

                        <div class="col-md-6">
                            <label for="tranche_date_valeur" class="form-label">Date de valeur <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="tranche_date_valeur" name="date_valeur" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tranche_mode_paiement" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="tranche_mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner un mode</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement bancaire</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte bancaire</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="tranche_reference_externe" class="form-label">Référence externe</label>
                            <input type="text" class="form-control" id="tranche_reference_externe" name="reference_externe" placeholder="N° chèque, référence virement...">
                            <small class="form-text text-muted">Obligatoire pour les paiements par chèque, virement ou mobile money.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tranche_source_paiement" class="form-label">Source du paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="tranche_source_paiement" name="source_paiement" required onchange="toggleTrancheSourceOptions()">
                                <option value="">Sélectionner une source</option>
                                <option value="Caisse">Caisse</option>
                                <option value="Banque">Banque</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="tranche_caisse_container" style="display: none;">
                            <label for="tranche_caisse_id" class="form-label">Caisse <span class="text-danger">*</span></label>
                            <select class="form-select" id="tranche_caisse_id" name="caisse_id">
                                <option value="">Sélectionner une caisse</option>
                                <?php 
                                // Récupérer les caisses actives
                                $stmt = $connexion->prepare("SELECT id, designation, devise FROM caisses WHERE est_actif = 1 ORDER BY designation");
                                $stmt->execute();
                                $caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($caisses as $caisse): 
                                ?>
                                    <option value="<?= $caisse['id'] ?>" data-devise="<?= $caisse['devise'] ?>">
                                        <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6" id="tranche_banque_container" style="display: none;">
                            <label for="tranche_compte_bancaire_id" class="form-label">Compte bancaire <span class="text-danger">*</span></label>
                            <select class="form-select" id="tranche_compte_bancaire_id" name="compte_bancaire_id">
                                <option value="">Sélectionner un compte</option>
                                <?php 
                                // Récupérer les comptes bancaires actifs
                                $stmt = $connexion->prepare("SELECT id, nom_banque, intitule_compte, numero_compte, devise FROM comptes_bancaires WHERE est_actif = 1 ORDER BY nom_banque, intitule_compte");
                                $stmt->execute();
                                $comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($comptes_bancaires as $compte): 
                                ?>
                                    <option value="<?= $compte['id'] ?>" data-devise="<?= $compte['devise'] ?>">
                                        <?= htmlspecialchars($compte['nom_banque'] . ' - ' . $compte['intitule_compte']) ?> (<?= $compte['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tranche_commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="tranche_commentaire" name="commentaire" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer le paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'envoi de rappel -->
<div class="modal fade" id="rappelModal" tabindex="-1" aria-labelledby="rappelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rappelModalLabel">Envoyer un rappel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rappelForm">
                    <input type="hidden" id="rappel_matricule" name="matricule">
                    <input type="hidden" id="rappel_echeancier_id" name="echeancier_id">
                    
                    <div class="mb-3">
                        <label for="mode_rappel" class="form-label">Mode de rappel</label>
                        <select class="form-select" id="mode_rappel" name="mode_rappel" required>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="both">Email et SMS</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_rappel" class="form-label">Message personnalisé (optionnel)</label>
                        <textarea class="form-control" id="message_rappel" name="message_rappel" rows="4"></textarea>
                        <small class="form-text text-muted">Ce message sera ajouté au rappel standard indiquant les détails de l'échéance.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="sendRappelBtn">Envoyer le rappel</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour activer/désactiver les champs de date selon le type de rapport
    function toggleDateFields() {
        const typeRapport = document.getElementById('type_rapport').value;
        const dateDebutContainer = document.getElementById('date_debut_container');
        const dateFinContainer = document.getElementById('date_fin_container');
        
        if (typeRapport === 'echeanciers_periode') {
            dateDebutContainer.style.display = 'block';
            dateFinContainer.style.display = 'block';
        } else {
            dateDebutContainer.style.display = 'none';
            dateFinContainer.style.display = 'none';
        }
    }
    
    // Fonction pour soumettre le formulaire lorsque l'année académique change
    function submitForm() {
        document.getElementById('filterForm').submit();
    }
    
    // Fonction pour basculer entre les options de source de paiement pour les tranches
    function toggleTrancheSourceOptions() {
        const sourcePaiement = document.getElementById('tranche_source_paiement').value;
        const caisseContainer = document.getElementById('tranche_caisse_container');
        const banqueContainer = document.getElementById('tranche_banque_container');

        if (sourcePaiement === 'Caisse') {
            caisseContainer.style.display = 'block';
            banqueContainer.style.display = 'none';
            document.getElementById('tranche_caisse_id').setAttribute('required', 'required');
            document.getElementById('tranche_compte_bancaire_id').removeAttribute('required');
        } else if (sourcePaiement === 'Banque') {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'block';
            document.getElementById('tranche_caisse_id').removeAttribute('required');
            document.getElementById('tranche_compte_bancaire_id').setAttribute('required', 'required');
        } else {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'none';
            document.getElementById('tranche_caisse_id').removeAttribute('required');
            document.getElementById('tranche_compte_bancaire_id').removeAttribute('required');
        }
    }
    
    // Fonction pour envoyer un rappel
    function sendRappel(matricule, echeancier_id) {
        document.getElementById('rappel_matricule').value = matricule;
        document.getElementById('rappel_echeancier_id').value = echeancier_id;
        
        // Ouvrir le modal de rappel
        new bootstrap.Modal(document.getElementById('rappelModal')).show();
    }
    
    // Fonction pour exporter les données vers Excel
    function exportToExcel() {
        // Créer une copie de la table sans la colonne d'actions
        const table = document.getElementById('echeanciers-table');
        const tableClone = table.cloneNode(true);
        
        // Supprimer la dernière colonne (Actions)
        const rows = tableClone.querySelectorAll('tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('th, td');
            if (cells.length > 0) {
                cells[cells.length - 1].remove();
            }
        });
        
        // Convertir la table en workbook Excel
        const wb = XLSX.utils.table_to_book(tableClone, {sheet: "Echeanciers"});
        
        // Générer le fichier Excel
        const fileName = 'Rapport_Echeanciers_<?= date('Y-m-d') ?>.xlsx';
        XLSX.writeFile(wb, fileName);
    }
    
    // Initialisation quand le document est chargé
    document.addEventListener('DOMContentLoaded', function() {
        // Appliquer l'état initial des champs de date
        toggleDateFields();
        
        // Modal de paiement de tranche
        const paiementTrancheModal = document.getElementById('paiementTrancheModal');
        if (paiementTrancheModal) {
            paiementTrancheModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const affectationId = button.getAttribute('data-affectation-id');
                const echelonnementId = button.getAttribute('data-echelonnement-id');
                const fraisDesignation = button.getAttribute('data-frais-designation');
                const montantRestant = parseFloat(button.getAttribute('data-montant-restant'));
                const devise = button.getAttribute('data-devise');
                const matricule = button.getAttribute('data-matricule');

                document.getElementById('tranche_affectation_id').value = affectationId;
                document.getElementById('echelonnement_id').value = echelonnementId;
                document.getElementById('matricule_etudiant').value = matricule;
                document.getElementById('tranche_frais_designation').textContent = fraisDesignation;
                document.getElementById('tranche_montant_restant').textContent = montantRestant.toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('tranche_devise').textContent = devise;
                document.getElementById('tranche_devise_input').textContent = devise;

                // Définir le montant maximum
                document.getElementById('tranche_montant').setAttribute('max', montantRestant);
                document.getElementById('tranche_montant').value = montantRestant.toFixed(2);
            });
        }
        
        // Validation du mode de paiement pour les tranches
        const trancheModePaiement = document.getElementById('tranche_mode_paiement');
        const trancheReferenceExterne = document.getElementById('tranche_reference_externe');

        if (trancheModePaiement && trancheReferenceExterne) {
            trancheModePaiement.addEventListener('change', function() {
                const mode = this.value;
                if (mode === 'Chèque' || mode === 'Virement' || mode === 'Mobile Money') {
                    trancheReferenceExterne.setAttribute('required', 'required');
                    trancheReferenceExterne.parentElement.querySelector('small').classList.add('text-danger');
                } else {
                    trancheReferenceExterne.removeAttribute('required');
                    trancheReferenceExterne.parentElement.querySelector('small').classList.remove('text-danger');
                }
            });
        }
        
        // Gestion du bouton d'envoi de rappel
        document.getElementById('sendRappelBtn').addEventListener('click', function() {
            const form = document.getElementById('rappelForm');
            const formData = new FormData(form);
            
            // Afficher un indicateur de chargement
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi en cours...';
            this.disabled = true;
            
            fetch('controller/send_rappel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Fermer le modal
                bootstrap.Modal.getInstance(document.getElementById('rappelModal')).hide();
                
                // Réinitialiser le bouton
                this.innerHTML = 'Envoyer le rappel';
                this.disabled = false;
                
                // Afficher le message de résultat
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Rappel envoyé',
                        text: data.message
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de l\'envoi du rappel.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                
                // Réinitialiser le bouton
                this.innerHTML = 'Envoyer le rappel';
                this.disabled = false;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de l\'envoi du rappel.'
                });
            });
        });
        
        // Validation des devises pour les paiements de tranches en caisse
        const trancheCaisseSelect = document.getElementById('tranche_caisse_id');
        if (trancheCaisseSelect) {
            trancheCaisseSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const caisseDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('tranche_devise').textContent;

                    if (caisseDevise !== fraisDevise) {
                        alert(`Attention: La devise de la caisse (${caisseDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir une caisse avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }

        // Validation des devises pour les paiements de tranches bancaires
        const trancheCompteSelect = document.getElementById('tranche_compte_bancaire_id');
        if (trancheCompteSelect) {
            trancheCompteSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const compteDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('tranche_devise').textContent;

                    if (compteDevise !== fraisDevise) {
                        alert(`Attention: La devise du compte bancaire (${compteDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir un compte avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>
