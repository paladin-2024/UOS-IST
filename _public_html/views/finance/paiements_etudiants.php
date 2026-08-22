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

// Vérifier si l'utilisateur a des responsabilités dans des sections
$stmt_sections = $connexion->prepare("
    SELECT DISTINCT section_idsection 
    FROM responsable_section 
    WHERE \"idUser\" = :idUser
");
$stmt_sections->bindParam(':idUser', $idUser);
$stmt_sections->execute();
$user_sections = $stmt_sections->fetchAll(PDO::FETCH_COLUMN);
$has_section_responsibility = !empty($user_sections);

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);

// Récupérer les caisses actives
$stmt = $connexion->prepare("SELECT id, designation, devise FROM caisses WHERE est_actif = 1 ORDER BY designation");
$stmt->execute();
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les comptes bancaires actifs
$stmt = $connexion->prepare("SELECT id, nom_banque, intitule_compte, numero_compte, devise FROM comptes_bancaires WHERE est_actif = 1 ORDER BY nom_banque, intitule_compte");
$stmt->execute();
$comptes_bancaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier le type de recherche - Par défaut: recherche par nom
// Si un matricule est passé directement (après enregistrement), on bascule automatiquement en mode matricule
$type_recherche = isset($_GET['type_recherche']) ? $_GET['type_recherche'] : 'nom';
if (!isset($_GET['type_recherche']) && isset($_GET['matricule']) && !empty($_GET['matricule'])) {
    $type_recherche = 'matricule';
}
$matricule = '';
$nom_recherche = '';
$etudiant = null;
$etudiants = [];
$frais_individuels = [];
$frais_promotion = [];
$annee_selectionnee = isset($_GET['annee_academique']) ? $_GET['annee_academique'] : 'courante';
$arrieres_autres_annees = [];
$total_arrieres = 0;

// Filtre lieu de paiement - Par défaut: Faculté
$lieu_paiement_filtre = isset($_GET['lieu_paiement']) ? $_GET['lieu_paiement'] : 'Faculté';

// Récupérer toutes les années académiques disponibles
$stmt = $connexion->prepare("SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC");
$stmt->execute();
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($type_recherche === 'matricule') {
    $matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : '';
    if (!empty($matricule)) {
        // Récupérer les informations de l'étudiant par matricule
        $sql = "
         SELECT e.*, 
         p.\"designationPromotion\" AS promotion_nom,
         CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte_nom,
         aa.designation AS annee_academique
         FROM etudiant e
         LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
         LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
         LEFT JOIN section s ON o.section_idsection = s.idsection
         LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
         WHERE e.matricule = :matricule AND e.est_actif=1";

        // Si l'utilisateur a des responsabilités de section, filtrer par ces sections
        if ($has_section_responsibility) {
            $sql .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
        }

        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->execute();
        $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} else {
    $nom_recherche = isset($_GET['nom_recherche']) ? trim($_GET['nom_recherche']) : '';
    if (!empty($nom_recherche)) {
        // Rechercher les étudiants par nom
        $recherche = "%$nom_recherche%";
        $sql = "
        SELECT e.*, 
               p.\"designationPromotion\" AS promotion_nom,
               CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte_nom,
               aa.designation AS annee_academique
        FROM etudiant e
        LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
        LEFT JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE e.noms LIKE :recherche AND e.est_actif=1";

        // Si l'utilisateur a des responsabilités de section, filtrer par ces sections
        if ($has_section_responsibility) {
            $sql .= " AND s.idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
        }

        $sql .= " LIMIT 50";

        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':recherche', $recherche);
        $stmt->execute();
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si un seul étudiant est trouvé, le sélectionner automatiquement
        if (count($etudiants) === 1) {
            $etudiant = $etudiants[0];
            $matricule = $etudiant['matricule'];
        }
    }
}

// Si un étudiant est trouvé, récupérer ses frais
if ($etudiant) {
    // Récupérer l'ID de la promotion et l'année académique de l'étudiant
    $promotion_id = $etudiant['promotion_idpromotion'];
    $annee_academique_etudiant = $etudiant['annee_academique'] ?? null;

    // Déterminer l'année académique à utiliser pour le filtrage
    $annee_acad_id_filtre = null;
    if ($annee_selectionnee === 'toutes') {
        // Pas de filtre par année
        $annee_acad_id_filtre = null;
    } elseif ($annee_selectionnee === 'courante' || empty($annee_selectionnee)) {
        // Utiliser l'année de la promotion actuelle
        $stmt = $connexion->prepare("SELECT annee_acad_idannee_acad FROM promotion WHERE idpromotion = :promotion_id");
        $stmt->bindParam(':promotion_id', $promotion_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $annee_acad_id_filtre = $result['annee_acad_idannee_acad'] ?? null;
    } else {
        // Utiliser l'année spécifiquement sélectionnée
        $annee_acad_id_filtre = $annee_selectionnee;
    }

    // 1. Récupérer les frais individuels de l'étudiant
    $sql_individuels = "
        SELECT 
            af.id, 
            af.frais_id,
            af.promotion_id,
            af.matricule_etudiant,
            af.montant_specifique,
            af.date_affectation,
            af.devise,
            af.statut_paiement,
            af.est_exempte,
            f.designation AS frais_designation, 
            f.est_echelonnable,
            f.montant AS montant_frais,
            f.devise AS devise_frais,
            f.annee_acad_id,
            f.lieu_paiement,
            cf.designation AS categorie_nom,
            aa.designation AS annee_academique,
            aa.idannee_acad AS annee_id,
            p.\"designationPromotion\" AS promotion_nom,
            (SELECT COALESCE(SUM(pf.montant), 0) 
             FROM paiements_frais pf 
             WHERE pf.affectation_id = af.id 
             AND pf.matricule_etudiant = :matricule
             AND pf.est_confirme = 1) AS montant_paye
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
        WHERE af.matricule_etudiant = :matricule
        AND af.est_exempte = 0
        AND f.lieu_paiement = :lieu_paiement";

    if ($annee_acad_id_filtre !== null) {
        $sql_individuels .= " AND f.annee_acad_id = :annee_acad_id";
    }

    $sql_individuels .= " ORDER BY aa.designation DESC, f.designation";

    $stmt_individuels = $connexion->prepare($sql_individuels);
    $stmt_individuels->bindParam(':matricule', $matricule);
    $stmt_individuels->bindParam(':lieu_paiement', $lieu_paiement_filtre);
    if ($annee_acad_id_filtre !== null) {
        $stmt_individuels->bindParam(':annee_acad_id', $annee_acad_id_filtre);
    }
    $stmt_individuels->execute();
    $frais_individuels = $stmt_individuels->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les frais de promotion (sans les frais déjà affectés individuellement, filtrés par année académique)
    $sql_promotion = "
        SELECT 
            af.id,
            af.frais_id,
            af.promotion_id,
            af.matricule_etudiant,
            af.montant_specifique,
            af.date_affectation,
            af.devise,
            af.statut_paiement,
            af.est_exempte, 
            f.designation AS frais_designation, 
            f.est_echelonnable,
            f.montant AS montant_frais,
            f.devise AS devise_frais,
            f.annee_acad_id,
            f.lieu_paiement,
            cf.designation AS categorie_nom,
            aa.designation AS annee_academique,
            aa.idannee_acad AS annee_id,
            p.\"designationPromotion\" AS promotion_nom,
            (SELECT COALESCE(SUM(pf.montant), 0) 
             FROM paiements_frais pf 
             WHERE pf.affectation_id = af.id 
             AND pf.matricule_etudiant = :matricule
             AND pf.est_confirme = 1) AS montant_paye
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
        WHERE af.promotion_id = :promotion_id
        AND af.matricule_etudiant IS NULL
        AND af.est_exempte = 0
        AND f.lieu_paiement = :lieu_paiement
        AND NOT EXISTS (
            SELECT 1 FROM affectation_frais af2 
            WHERE af2.frais_id = af.frais_id 
            AND af2.matricule_etudiant = :matricule2
        )";

    if ($annee_acad_id_filtre !== null) {
        $sql_promotion .= " AND f.annee_acad_id = :annee_acad_id";
    }

    $sql_promotion .= " ORDER BY aa.designation DESC, f.designation";

    $stmt_promotion = $connexion->prepare($sql_promotion);
    $stmt_promotion->bindParam(':matricule', $matricule);
    $stmt_promotion->bindParam(':matricule2', $matricule);
    $stmt_promotion->bindParam(':promotion_id', $promotion_id);
    $stmt_promotion->bindParam(':lieu_paiement', $lieu_paiement_filtre);
    if ($annee_acad_id_filtre !== null) {
        $stmt_promotion->bindParam(':annee_acad_id', $annee_acad_id_filtre);
    }
    $stmt_promotion->execute();
    $frais_promotion = $stmt_promotion->fetchAll(PDO::FETCH_ASSOC);

    // Fonction pour calculer les montants restants et statuts pour un tableau de frais
    function processFrais(&$frais)
    {
        foreach ($frais as &$affectation) {
            // Déterminer le montant total du frais
            $montant_total = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
            $affectation['montant_total'] = $montant_total;
            $affectation['montant_restant'] = $montant_total - $affectation['montant_paye'];

            // Mise à jour du statut de paiement basé sur les paiements réels de l'étudiant
            if ($affectation['montant_paye'] >= $montant_total) {
                $affectation['statut_paiement_etudiant'] = 'Complet';
            } elseif ($affectation['montant_paye'] > 0) {
                $affectation['statut_paiement_etudiant'] = 'Partiel';
            } else {
                $affectation['statut_paiement_etudiant'] = 'Non payé';
            }

            // Initialiser un tableau vide pour les tranches
            $affectation['tranches'] = [];
        }
    }

    // Traitement des frais individuels et des frais de promotion
    processFrais($frais_individuels);
    processFrais($frais_promotion);

    // Récupérer les tranches pour les frais échelonnables
    function loadTranches(&$frais, $connexion, $matricule)
    {
        foreach ($frais as &$affectation) {
            // Ne traiter que les frais échelonnables
            if ($affectation['est_echelonnable'] == 1) {
                $stmt = $connexion->prepare("
                     SELECT ep.*, 
                            tpc.montant_fixe,
                            (SELECT COALESCE(SUM(pt.montant), 0) 
                             FROM paiements_tranches pt
                             JOIN paiements_frais pf ON pt.paiement_id = pf.id
                             WHERE pt.echelonnement_id = ep.id 
                             AND pf.matricule_etudiant = :matricule
                             AND pf.est_confirme = 1) AS montant_paye
                     FROM echelonnement_paiement ep
                     LEFT JOIN tranches_paiement_config tpc ON tpc.frais_id = :frais_id 
                        AND tpc.numero_tranche = ep.numero_tranche
                     WHERE ep.affectation_id = :affectation_id
                     ORDER BY ep.numero_tranche
                 ");
                $stmt->bindParam(':affectation_id', $affectation['id']);
                $stmt->bindParam(':frais_id', $affectation['frais_id']);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $affectation['tranches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculer le montant restant pour chaque tranche
                foreach ($affectation['tranches'] as &$tranche) {
                    // Utiliser le montant_fixe configuré s'il existe, sinon le montant calculé
                    $montant_affiche = !empty($tranche['montant_fixe'])
                        ? floatval($tranche['montant_fixe'])
                        : floatval($tranche['montant']);

                    $tranche['montant_affiche'] = $montant_affiche;
                    $tranche['montant_restant'] = $montant_affiche - $tranche['montant_paye'];

                    // Mettre à jour le statut de la tranche
                    if ($tranche['montant_paye'] >= $montant_affiche) {
                        $tranche['statut_paiement'] = 'Complet';
                    } elseif ($tranche['montant_paye'] > 0) {
                        $tranche['statut_paiement'] = 'Partiel';
                    } else {
                        $tranche['statut_paiement'] = 'Non payé';
                    }
                }
            }
        }
    }

    // Chargement des tranches
    loadTranches($frais_individuels, $connexion, $matricule);
    loadTranches($frais_promotion, $connexion, $matricule);
}

// Récupérer l'historique des paiements si un étudiant est sélectionné
$historique_paiements = [];
if ($etudiant) {
    $sql_historique = "
        SELECT pf.*, 
               af.id AS affectation_id,
               f.designation AS frais_designation,
               f.annee_acad_id,
               aa.designation AS annee_academique,
               t.reference AS transaction_reference,
               t.date_transaction,
               t.source,
               t.source_id,
               u.\"nomUser\" AS agent_nom,
               CASE 
                   WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
                   WHEN t.source = 'Banque' THEN (SELECT CONCAT(nom_banque, ' - ', intitule_compte) FROM comptes_bancaires WHERE id = t.source_id)
                   ELSE 'Non spécifié'
               END AS source_nom
        FROM paiements_frais pf
        INNER JOIN affectation_frais af ON pf.affectation_id = af.id
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN transactions t ON pf.transaction_id = t.id
        LEFT JOIN t_users u ON t.\"idUser\" = u.\"idUser\"
        WHERE pf.matricule_etudiant = :matricule
        AND pf.est_confirme = 1";

    // Filtrer par année académique si nécessaire
    if ($annee_acad_id_filtre !== null) {
        $sql_historique .= " AND f.annee_acad_id = :annee_acad_id";
    }

    $sql_historique .= " ORDER BY t.date_transaction DESC";

    $stmt = $connexion->prepare($sql_historique);
    $stmt->bindParam(':matricule', $matricule);
    if ($annee_acad_id_filtre !== null) {
        $stmt->bindParam(':annee_acad_id', $annee_acad_id_filtre);
    }
    $stmt->execute();
    $historique_paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calcul du solde total de l'étudiant par devise
$totaux_par_devise = [];

if ($etudiant) {
    // Calculer les totaux à partir des frais individuels
    foreach ($frais_individuels as $frais) {
        // Déterminer la devise avec une logique plus robuste
        $devise = !empty($frais['devise']) ? $frais['devise'] : (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');

        // Nettoyer la devise (enlever les espaces)
        $devise = trim($devise);
        if (empty($devise)) {
            $devise = 'USD'; // Devise par défaut
        }

        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }

        $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
        $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
    }

    // Calculer les totaux à partir des frais de promotion
    foreach ($frais_promotion as $frais) {
        // Déterminer la devise avec une logique plus robuste
        $devise = !empty($frais['devise']) ? $frais['devise'] : (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');

        // Nettoyer la devise (enlever les espaces)
        $devise = trim($devise);
        if (empty($devise)) {
            $devise = 'USD'; // Devise par défaut
        }

        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }

        $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
        $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
    }

    // Calculer le solde restant pour chaque devise
    foreach ($totaux_par_devise as $devise => &$totaux) {
        $totaux['solde_restant'] = $totaux['total_du'] - $totaux['total_paye'];
    }
}

// Maintenir la compatibilité avec l'ancien code (pour USD par défaut)
$total_du = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_du'] : 0;
$total_paye = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_paye'] : 0;
$solde_total = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['solde_restant'] : 0;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Paiements des Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Paiements des Étudiants</li>
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

        <?php if ($has_section_responsibility):
            // Récupérer les noms des sections
            $sections_names_sql = "SELECT \"designationSection\" FROM section WHERE idsection IN (" . implode(',', array_map('intval', $user_sections)) . ")";
            $stmt_names = $connexion->prepare($sections_names_sql);
            $stmt_names->execute();
            $sections_names = $stmt_names->fetchAll(PDO::FETCH_COLUMN);
        ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Filtrage actif:</strong> Vous visualisez uniquement les étudiants appartenant aux sections suivantes :
                <strong><?= implode(', ', $sections_names) ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Recherche d'étudiant -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rechercher un étudiant</h5>
                        <form action="" method="GET" class="row g-3">
                            <input type="hidden" name="view" value="finance/paiements_etudiants">

                            <div class="col-md-3">
                                <label for="type_recherche" class="form-label">Type de recherche</label>
                                <select class="form-select" id="type_recherche" name="type_recherche" onchange="toggleSearchFields()">
                                    <option value="matricule" <?= $type_recherche === 'matricule' ? 'selected' : '' ?>>Par matricule</option>
                                    <option value="nom" <?= $type_recherche === 'nom' ? 'selected' : '' ?>>Par nom</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="matricule_container" <?= $type_recherche === 'nom' ? 'style="display:none;"' : '' ?>>
                                <label for="matricule" class="form-label">Matricule de l'étudiant</label>
                                <input type="text" class="form-control" id="matricule" name="matricule"
                                    value="<?= htmlspecialchars($matricule) ?>" placeholder="Entrez le matricule">
                            </div>

                            <div class="col-md-6" id="nom_container" <?= $type_recherche === 'matricule' ? 'style="display:none;"' : '' ?>>
                                <label for="nom_recherche" class="form-label">Nom de l'étudiant</label>
                                <input type="text" class="form-control" id="nom_recherche" name="nom_recherche"
                                    value="<?= htmlspecialchars($nom_recherche) ?>" placeholder="Entrez le nom ou prénom">
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($type_recherche === 'nom' && !empty($etudiants) && count($etudiants) > 1): ?>
            <!-- Résultats de recherche par nom -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Résultats de la recherche</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Nom</th>
                                            <th>Promotion</th>
                                            <th>Faculté</th>
                                            <th>Année</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $etud): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($etud['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etud['noms']) ?></td>
                                                <td><?= htmlspecialchars($etud['promotion_nom'] ?? 'Non spécifiée') ?></td>
                                                <td><?= htmlspecialchars($etud['faculte_nom'] ?? 'Non spécifiée') ?></td>
                                                <td><?= htmlspecialchars($etud['annee_academique'] ?? 'Non spécifiée') ?></td>
                                                <td>
                                                    <a href="?view=finance/paiements_etudiants&type_recherche=matricule&matricule=<?= $etud['matricule'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-check-circle"></i> Sélectionner
                                                    </a>
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

        <?php if ($etudiant): ?>
            <!-- Sélecteur lieu de paiement et année académique -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body py-3">
                            <form action="" method="GET" class="row g-3 align-items-center">
                                <input type="hidden" name="view" value="finance/paiements_etudiants">
                                <input type="hidden" name="type_recherche" value="matricule">
                                <input type="hidden" name="matricule" value="<?= htmlspecialchars($matricule) ?>">

                                <div class="col-auto">
                                    <label class="fw-bold">Lieu de paiement :</label>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="lieu_paiement" id="lieu_faculte" value="Faculté"
                                            <?= $lieu_paiement_filtre === 'Faculté' ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <label class="form-check-label" for="lieu_faculte">
                                            <i class="bi bi-building"></i> Faculté
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="lieu_paiement" id="lieu_caisse" value="Caisse centrale"
                                            <?= $lieu_paiement_filtre === 'Caisse centrale' ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <label class="form-check-label" for="lieu_caisse">
                                            <i class="bi bi-safe"></i> Caisse centrale
                                        </label>
                                    </div>
                                </div>

                                <div class="col-auto">
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-funnel"></i> Mode: <?= htmlspecialchars($lieu_paiement_filtre) ?>
                                    </span>
                                </div>

                                <div class="col-auto ms-auto">
                                    <label class="fw-bold">Filtrer par année académique :</label>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" name="annee_academique" onchange="this.form.submit()">
                                        <option value="courante" <?= $annee_selectionnee === 'courante' ? 'selected' : '' ?>>
                                            Année courante (<?= htmlspecialchars($etudiant['annee_academique'] ?? 'Non spécifiée') ?>)
                                        </option>
                                        <option value="toutes" <?= $annee_selectionnee === 'toutes' ? 'selected' : '' ?>>
                                            Toutes les années
                                        </option>
                                        <?php foreach ($annees_academiques as $annee): ?>
                                            <option value="<?= $annee['idannee_acad'] ?>"
                                                <?= $annee_selectionnee == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($annee['designation']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <?php
                                    // Déterminer le texte à afficher selon la sélection
                                    $texte_filtre = '';
                                    if ($annee_selectionnee === 'toutes') {
                                        $texte_filtre = 'Affichage de toutes les années académiques';
                                    } elseif ($annee_selectionnee === 'courante' || empty($annee_selectionnee)) {
                                        $texte_filtre = 'Affichage de l\'année de la promotion actuelle';
                                    } else {
                                        // Trouver le nom de l'année sélectionnée
                                        foreach ($annees_academiques as $annee) {
                                            if ($annee['idannee_acad'] == $annee_selectionnee) {
                                                $texte_filtre = 'Affichage de l\'année : ' . $annee['designation'];
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <span class="badge bg-info text-white">
                                        <i class="bi bi-info-circle"></i> <?= htmlspecialchars($texte_filtre) ?>
                                    </span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations de l'étudiant -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Informations de l'étudiant</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Matricule:</strong> <?= htmlspecialchars($etudiant['matricule']) ?></p>
                                    <p><strong>Nom:</strong> <?= htmlspecialchars($etudiant['noms'] . ' ' . ($etudiant['postnom'] ?? '') . ' ' . ($etudiant['prenom'] ?? '')) ?></p>
                                    <p><strong>Sexe:</strong> <?= htmlspecialchars($etudiant['sexe'] ?? 'Non spécifié') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Promotion actuelle:</strong> <?= htmlspecialchars($etudiant['promotion_nom'] ?? 'Non spécifiée') ?></p>
                                    <p><strong>Faculté/Section:</strong> <?= htmlspecialchars($etudiant['faculte_nom'] ?? 'Non spécifiée') ?></p>
                                    <p><strong>Année académique de la promotion:</strong> <?= htmlspecialchars($etudiant['annee_academique'] ?? 'Non spécifiée') ?></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <?php if (!empty($totaux_par_devise)): ?>
                                        <h6 class="mb-3">Situation financière par devise</h6>

                                        <?php foreach ($totaux_par_devise as $devise_courante => $totaux_devise): ?>
                                            <div class="alert alert-info mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="fw-bold text-primary"><?= htmlspecialchars($devise_courante) ?></div>
                                                    <div class="d-flex gap-4">
                                                        <div><strong>Total dû:</strong> <?= number_format($totaux_devise['total_du'], 2) ?> <?= htmlspecialchars($devise_courante) ?></div>
                                                        <div><strong>Total payé:</strong> <?= number_format($totaux_devise['total_paye'], 2) ?> <?= htmlspecialchars($devise_courante) ?></div>
                                                        <div><strong>Solde restant:</strong>
                                                            <span class="<?= $totaux_devise['solde_restant'] > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
                                                                <?= number_format($totaux_devise['solde_restant'], 2) ?> <?= htmlspecialchars($devise_courante) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <div class="text-center">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Aucun frais assigné à cet étudiant.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Frais individuels -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Frais individuels</h5>

                            <?php if (empty($frais_individuels)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun frais individuel assigné à cet étudiant.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Frais</th>
                                                <th>Catégorie</th>
                                                <th>Année académique</th>
                                                <th>Montant total</th>
                                                <th>Montant payé</th>
                                                <th>Reste à payer</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($frais_individuels as $affectation):
                                                $devise = $affectation['devise'] ?: $affectation['devise_frais'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($affectation['frais_designation']) ?></td>
                                                    <td><?= htmlspecialchars($affectation['categorie_nom'] ?? 'Non spécifiée') ?></td>
                                                    <td><?= htmlspecialchars($affectation['annee_academique'] ?? 'Non spécifiée') ?></td>
                                                    <td>
                                                        <?= number_format($affectation['montant_total'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <?= number_format($affectation['montant_paye'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <?= number_format($affectation['montant_restant'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $affectation['statut_paiement_etudiant'] === 'Complet' ? 'success' : ($affectation['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                            <?= htmlspecialchars($affectation['statut_paiement_etudiant']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($affectation['est_echelonnable'] == 1): ?>
                                                            <!-- Boutons pour frais échelonnables -->
                                                            <div class="btn-group btn-group-sm mb-1" role="group">
                                                                <!-- Bouton pour afficher les tranches -->
                                                                <button type="button" class="btn btn-info" data-bs-toggle="collapse" data-bs-target="#tranches_ind_<?= $affectation['id'] ?>">
                                                                    <i class="bi bi-list-check"></i> Tranches
                                                                    <?php if (empty($affectation['tranches'])): ?>
                                                                        <span class="badge bg-warning">0</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-dark"><?= count($affectation['tranches']) ?></span>
                                                                    <?php endif; ?>
                                                                </button>

                                                                <?php if ($affectation['montant_restant'] > 0): ?>
                                                                    <!-- Bouton pour payer le montant total -->
                                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paiementCompletModal"
                                                                        data-affectation-id="<?= $affectation['id'] ?>"
                                                                        data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?>"
                                                                        data-montant-total="<?= $affectation['montant_total'] ?>"
                                                                        data-montant-restant="<?= $affectation['montant_restant'] ?>"
                                                                        data-devise="<?= htmlspecialchars($devise) ?>">
                                                                        <i class="bi bi-cash-coin"></i> Total
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Bouton de paiement direct pour frais non échelonnables -->
                                                            <button type="button" class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#paiementModal"
                                                                data-affectation-id="<?= $affectation['id'] ?>"
                                                                data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?>"
                                                                data-montant-restant="<?= $affectation['montant_restant'] ?>"
                                                                data-devise="<?= htmlspecialchars($devise) ?>">
                                                                <i class="bi bi-cash"></i> Payer
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>

                                                <?php if ($affectation['est_echelonnable'] == 1 && !empty($affectation['tranches'])): ?>
                                                    <!-- Ligne pour afficher les tranches (cachée par défaut) -->
                                                    <tr class="collapse" id="tranches_ind_<?= $affectation['id'] ?>">
                                                        <td colspan="8" class="p-0">
                                                            <div class="p-3 border-top border-bottom bg-light">
                                                                <h6 class="mb-3">Tranches de paiement pour <?= htmlspecialchars($affectation['frais_designation']) ?></h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered">
                                                                        <thead>
                                                                            <tr class="table-secondary">
                                                                                <th>N°</th>
                                                                                <th>Désignation</th>
                                                                                <th>Échéance</th>
                                                                                <th>Montant</th>
                                                                                <th>Payé</th>
                                                                                <th>Reste</th>
                                                                                <th>Statut</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($affectation['tranches'] as $tranche):
                                                                                $montant_affiche = !empty($tranche['montant_fixe']) ? floatval($tranche['montant_fixe']) : floatval($tranche['montant']);
                                                                                $tranche_restant = $montant_affiche - $tranche['montant_paye'];
                                                                            ?>
                                                                                <tr>
                                                                                    <td><?= $tranche['numero_tranche'] ?></td>
                                                                                    <td><?= htmlspecialchars($tranche['designation']) ?></td>
                                                                                    <td><?= isset($tranche['date_echeance']) ? date('d/m/Y', strtotime($tranche['date_echeance'])) : 'N/A' ?></td>
                                                                                    <td><?= number_format($montant_affiche, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td><?= number_format($tranche['montant_paye'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td><?= number_format($tranche_restant, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td>
                                                                                        <span class="badge bg-<?= $tranche['statut_paiement'] === 'Complet' ? 'success' : ($tranche['statut_paiement'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                                            <?= htmlspecialchars($tranche['statut_paiement']) ?>
                                                                                        </span>
                                                                                    </td>
                                                                                    <td>
                                                                                        <?php if ($tranche_restant > 0): ?>
                                                                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paiementTrancheModal"
                                                                                                data-affectation-id="<?= $affectation['id'] ?>"
                                                                                                data-echelonnement-id="<?= $tranche['id'] ?>"
                                                                                                data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?> (Tranche <?= $tranche['numero_tranche'] ?>)"
                                                                                                data-montant-restant="<?= $tranche_restant ?>"
                                                                                                data-devise="<?= htmlspecialchars($devise) ?>">
                                                                                                <i class="bi bi-cash"></i> Payer
                                                                                            </button>
                                                                                        <?php else: ?>
                                                                                            <button type="button" class="btn btn-sm btn-success" disabled>
                                                                                                <i class="bi bi-check-circle"></i> Payé
                                                                                            </button>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Frais de promotion -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Frais de promotion</h5>

                            <?php if (empty($frais_promotion)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun frais de promotion applicable à cet étudiant.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Frais</th>
                                                <th>Catégorie</th>
                                                <th>Année académique</th>
                                                <th>Promotion</th>
                                                <th>Montant total</th>
                                                <th>Montant payé</th>
                                                <th>Reste à payer</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($frais_promotion as $affectation):
                                                $devise = $affectation['devise'] ?: $affectation['devise_frais'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($affectation['frais_designation']) ?></td>
                                                    <td><?= htmlspecialchars($affectation['categorie_nom'] ?? 'Non spécifiée') ?></td>
                                                    <td><?= htmlspecialchars($affectation['annee_academique'] ?? 'Non spécifiée') ?></td>
                                                    <td><?= htmlspecialchars($affectation['promotion_nom'] ?? 'Non spécifiée') ?></td>
                                                    <td>
                                                        <?= number_format($affectation['montant_total'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <?= number_format($affectation['montant_paye'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <?= number_format($affectation['montant_restant'], 2) ?>
                                                        <?= htmlspecialchars($devise) ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $affectation['statut_paiement_etudiant'] === 'Complet' ? 'success' : ($affectation['statut_paiement_etudiant'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                            <?= htmlspecialchars($affectation['statut_paiement_etudiant']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($affectation['est_echelonnable'] == 1): ?>
                                                            <!-- Boutons pour frais échelonnables -->
                                                            <div class="btn-group btn-group-sm mb-1" role="group">
                                                                <!-- Bouton pour afficher les tranches -->
                                                                <button type="button" class="btn btn-info" data-bs-toggle="collapse" data-bs-target="#tranches_prom_<?= $affectation['id'] ?>">
                                                                    <i class="bi bi-list-check"></i> Tranches
                                                                    <?php if (empty($affectation['tranches'])): ?>
                                                                        <span class="badge bg-warning">0</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-light text-dark"><?= count($affectation['tranches']) ?></span>
                                                                    <?php endif; ?>
                                                                </button>

                                                                <?php if ($affectation['montant_restant'] > 0): ?>
                                                                    <!-- Bouton pour payer le montant total -->
                                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paiementCompletModal"
                                                                        data-affectation-id="<?= $affectation['id'] ?>"
                                                                        data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?>"
                                                                        data-montant-total="<?= $affectation['montant_total'] ?>"
                                                                        data-montant-restant="<?= $affectation['montant_restant'] ?>"
                                                                        data-devise="<?= htmlspecialchars($devise) ?>">
                                                                        <i class="bi bi-cash-coin"></i> Total
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Bouton de paiement direct pour frais non échelonnables -->
                                                            <button type="button" class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#paiementModal"
                                                                data-affectation-id="<?= $affectation['id'] ?>"
                                                                data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?>"
                                                                data-montant-restant="<?= $affectation['montant_restant'] ?>"
                                                                data-devise="<?= htmlspecialchars($devise) ?>">
                                                                <i class="bi bi-cash"></i> Payer
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>

                                                <?php if ($affectation['est_echelonnable'] == 1 && !empty($affectation['tranches'])): ?>
                                                    <!-- Ligne pour afficher les tranches (cachée par défaut) -->
                                                    <tr class="collapse" id="tranches_prom_<?= $affectation['id'] ?>">
                                                        <td colspan="9" class="p-0">
                                                            <div class="p-3 border-top border-bottom bg-light">
                                                                <h6 class="mb-3">Tranches de paiement pour <?= htmlspecialchars($affectation['frais_designation']) ?></h6>
                                                                <div class="table-responsive">
                                                                    <table class="table table-sm table-bordered">
                                                                        <thead>
                                                                            <tr class="table-secondary">
                                                                                <th>N°</th>
                                                                                <th>Désignation</th>
                                                                                <th>Échéance</th>
                                                                                <th>Montant</th>
                                                                                <th>Payé</th>
                                                                                <th>Reste</th>
                                                                                <th>Statut</th>
                                                                                <th>Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php foreach ($affectation['tranches'] as $tranche):
                                                                                $montant_affiche = !empty($tranche['montant_fixe']) ? floatval($tranche['montant_fixe']) : floatval($tranche['montant']);
                                                                                $tranche_restant = $montant_affiche - $tranche['montant_paye'];
                                                                            ?>
                                                                                <tr>
                                                                                    <td><?= $tranche['numero_tranche'] ?></td>
                                                                                    <td><?= htmlspecialchars($tranche['designation']) ?></td>
                                                                                    <td><?= isset($tranche['date_echeance']) ? date('d/m/Y', strtotime($tranche['date_echeance'])) : 'N/A' ?></td>
                                                                                    <td><?= number_format($montant_affiche, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td><?= number_format($tranche['montant_paye'], 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td><?= number_format($tranche_restant, 2) ?> <?= htmlspecialchars($devise) ?></td>
                                                                                    <td>
                                                                                        <span class="badge bg-<?= $tranche['statut_paiement'] === 'Complet' ? 'success' : ($tranche['statut_paiement'] === 'Partiel' ? 'warning' : 'danger') ?>">
                                                                                            <?= htmlspecialchars($tranche['statut_paiement']) ?>
                                                                                        </span>
                                                                                    </td>
                                                                                    <td>
                                                                                        <?php if ($tranche_restant > 0): ?>
                                                                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paiementTrancheModal"
                                                                                                data-affectation-id="<?= $affectation['id'] ?>"
                                                                                                data-echelonnement-id="<?= $tranche['id'] ?>"
                                                                                                data-frais-designation="<?= htmlspecialchars($affectation['frais_designation']) ?> (Tranche <?= $tranche['numero_tranche'] ?>)"
                                                                                                data-montant-restant="<?= $tranche_restant ?>"
                                                                                                data-devise="<?= htmlspecialchars($devise) ?>">
                                                                                                <i class="bi bi-cash"></i> Payer
                                                                                            </button>
                                                                                        <?php else: ?>
                                                                                            <button type="button" class="btn btn-sm btn-success" disabled>
                                                                                                <i class="bi bi-check-circle"></i> Payé
                                                                                            </button>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des paiements -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Historique des paiements</h5>

                            <?php if (empty($historique_paiements)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun paiement enregistré pour cet étudiant.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped datatable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Frais</th>
                                                <th>Montant</th>
                                                <th>Mode</th>
                                                <th>Source</th>
                                                <th>Référence</th>
                                                <th>Agent</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($historique_paiements as $paiement): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($paiement['date_transaction'])) ?></td>
                                                    <td><?= htmlspecialchars($paiement['frais_designation']) ?></td>
                                                    <td>
                                                        <?= number_format($paiement['montant'], 2) ?>
                                                        <?= htmlspecialchars($paiement['devise']) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($paiement['mode_paiement']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $paiement['source'] === 'Caisse' ? 'success' : 'primary' ?>">
                                                            <?= htmlspecialchars($paiement['source']) ?>
                                                        </span>
                                                        <?= htmlspecialchars($paiement['source_nom']) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($paiement['reference_externe'] ?: $paiement['transaction_reference']) ?></td>
                                                    <td><?= htmlspecialchars($paiement['agent_nom'] ?? 'Non spécifié') ?></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="bi bi-printer"></i> Imprimer
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item" href="controller/generer_recu_format.php?id=<?= $paiement['id'] ?>&format=A4" target="_blank">
                                                                        <i class="bi bi-file-earmark-text"></i> Format A4
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="controller/generer_recu_format.php?id=<?= $paiement['id'] ?>&format=A5" target="_blank">
                                                                        <i class="bi bi-file-earmark"></i> Format A5
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="controller/generer_recu_format.php?id=<?= $paiement['id'] ?>&format=POS" target="_blank">
                                                                        <i class="bi bi-receipt"></i> Format POS (Ticket)
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <hr class="dropdown-divider">
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="controller/generer_recu_format.php?id=<?= $paiement['id'] ?>&format=A4-double" target="_blank">
                                                                        <i class="bi bi-file-earmark-ruled"></i> A4 Double (2 reçus)
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                            <!-- Bouton Annuler -->
                                                            <button type="button" class="btn btn-sm btn-danger ms-1" 
                                                                onclick="confirmerAnnulation(<?= $paiement['id'] ?>, '<?= htmlspecialchars($paiement['frais_designation'], ENT_QUOTES) ?>', <?= $paiement['montant'] ?>, '<?= htmlspecialchars($paiement['devise']) ?>')"
                                                                title="Annuler ce paiement">
                                                                <i class="bi bi-x-circle"></i>
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
        <?php elseif (!empty($matricule) || !empty($nom_recherche)): ?>
            <!-- Aucun étudiant trouvé -->
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Aucun étudiant trouvé avec les critères de recherche spécifiés.
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour paiement normal -->
<div class="modal fade" id="paiementModal" tabindex="-1" aria-labelledby="paiementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-height: 90vh;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <form action="controller/save_paiement.php" method="POST" style="display: flex; flex-direction: column; height: 100%;">
                <input type="hidden" name="action" value="paiement_normal">
                <input type="hidden" name="affectation_id" id="affectation_id">
                <input type="hidden" name="matricule_etudiant" value="<?= htmlspecialchars($matricule) ?>">

                <div class="modal-header py-2">
                    <h5 class="modal-title" id="paiementModalLabel">Enregistrer un paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 1rem;">
                    <!-- Informations du frais -->
                    <div class="alert alert-info mb-2 py-2 px-3" style="font-size: 0.9rem;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <strong>Frais:</strong> <span id="frais_designation"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Restant:</strong> <span id="montant_restant"></span> <span id="devise"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Montant et Date -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="montant" class="form-label mb-1" style="font-size: 0.9rem;">Montant <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="montant" name="montant" required>
                                <span class="input-group-text" id="devise_input"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="date_valeur" class="form-label mb-1" style="font-size: 0.9rem;">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="date_valeur" name="date_valeur" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Mode et Référence -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Mode <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="reference_externe" class="form-label mb-1" style="font-size: 0.9rem;">Référence</label>
                            <input type="text" class="form-control form-control-sm" id="reference_externe" name="reference_externe" placeholder="N° chèque...">
                        </div>
                    </div>

                    <!-- Source et Caisse/Banque -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="source_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Source <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="source_paiement" name="source_paiement" required onchange="toggleSourceOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Caisse">Caisse</option>
                                <option value="Banque">Banque</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="caisse_container" style="display: none;">
                            <label for="caisse_id" class="form-label mb-1" style="font-size: 0.9rem;">Caisse <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="caisse_id" name="caisse_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($caisses as $caisse): ?>
                                    <option value="<?= $caisse['id'] ?>" data-devise="<?= $caisse['devise'] ?>">
                                        <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="banque_container" style="display: none;">
                            <label for="compte_bancaire_id" class="form-label mb-1" style="font-size: 0.9rem;">Compte <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="compte_bancaire_id" name="compte_bancaire_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($comptes_bancaires as $compte): ?>
                                    <option value="<?= $compte['id'] ?>" data-devise="<?= $compte['devise'] ?>">
                                        <?= htmlspecialchars($compte['nom_banque'] . ' - ' . $compte['intitule_compte']) ?> (<?= $compte['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-2">
                        <label for="commentaire" class="form-label mb-1" style="font-size: 0.9rem;">Commentaire</label>
                        <textarea class="form-control form-control-sm" id="commentaire" name="commentaire" rows="1" style="resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour paiement de tranche -->
<div class="modal fade" id="paiementTrancheModal" tabindex="-1" aria-labelledby="paiementTrancheModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-height: 90vh;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <form action="controller/save_paiement.php" method="POST" style="display: flex; flex-direction: column; height: 100%;">
                <input type="hidden" name="action" value="paiement_tranche">
                <input type="hidden" name="affectation_id" id="tranche_affectation_id">
                <input type="hidden" name="echelonnement_id" id="echelonnement_id">
                <input type="hidden" name="matricule_etudiant" value="<?= htmlspecialchars($matricule) ?>">

                <div class="modal-header py-2">
                    <h5 class="modal-title" id="paiementTrancheModalLabel">Paiement de tranche</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 1rem;">
                    <!-- Informations du frais -->
                    <div class="alert alert-info mb-2 py-2 px-3" style="font-size: 0.9rem;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <strong>Frais:</strong> <span id="tranche_frais_designation"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Restant:</strong> <span id="tranche_montant_restant"></span> <span id="tranche_devise"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Montant et Date -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="tranche_montant" class="form-label mb-1" style="font-size: 0.9rem;">Montant <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="tranche_montant" name="montant" required>
                                <span class="input-group-text" id="tranche_devise_input"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="tranche_date_valeur" class="form-label mb-1" style="font-size: 0.9rem;">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="tranche_date_valeur" name="date_valeur" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Mode et Référence -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="tranche_mode_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Mode <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tranche_mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tranche_reference_externe" class="form-label mb-1" style="font-size: 0.9rem;">Référence</label>
                            <input type="text" class="form-control form-control-sm" id="tranche_reference_externe" name="reference_externe" placeholder="N° chèque...">
                        </div>
                    </div>

                    <!-- Source et Caisse/Banque -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="tranche_source_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Source <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tranche_source_paiement" name="source_paiement" required onchange="toggleTrancheSourceOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Caisse">Caisse</option>
                                <option value="Banque">Banque</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="tranche_caisse_container" style="display: none;">
                            <label for="tranche_caisse_id" class="form-label mb-1" style="font-size: 0.9rem;">Caisse <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tranche_caisse_id" name="caisse_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($caisses as $caisse): ?>
                                    <option value="<?= $caisse['id'] ?>" data-devise="<?= $caisse['devise'] ?>">
                                        <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="tranche_banque_container" style="display: none;">
                            <label for="tranche_compte_bancaire_id" class="form-label mb-1" style="font-size: 0.9rem;">Compte <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="tranche_compte_bancaire_id" name="compte_bancaire_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($comptes_bancaires as $compte): ?>
                                    <option value="<?= $compte['id'] ?>" data-devise="<?= $compte['devise'] ?>">
                                        <?= htmlspecialchars($compte['nom_banque'] . ' - ' . $compte['intitule_compte']) ?> (<?= $compte['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-2">
                        <label for="tranche_commentaire" class="form-label mb-1" style="font-size: 0.9rem;">Commentaire</label>
                        <textarea class="form-control form-control-sm" id="tranche_commentaire" name="commentaire" rows="1" style="resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour paiement du montant total (frais échelonnables) -->
<div class="modal fade" id="paiementCompletModal" tabindex="-1" aria-labelledby="paiementCompletModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-height: 90vh;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <form action="controller/save_paiement.php" method="POST" style="display: flex; flex-direction: column; height: 100%;">
                <input type="hidden" name="action" value="paiement_complet">
                <input type="hidden" name="affectation_id" id="complet_affectation_id">
                <input type="hidden" name="matricule_etudiant" value="<?= htmlspecialchars($matricule) ?>">

                <div class="modal-header py-2">
                    <h5 class="modal-title" id="paiementCompletModalLabel">Paiement du montant total</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 1rem;">
                    <!-- Informations du frais -->
                    <div class="alert alert-success mb-3 py-2 px-3" style="font-size: 0.9rem;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <strong>Frais:</strong> <span id="complet_frais_designation"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Montant total:</strong> <span id="complet_montant_total"></span> <span id="complet_devise"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Avertissement -->
                    <div class="alert alert-info mb-3 py-2 px-3" style="font-size: 0.9rem;">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Le paiement du montant total couvrira l'intégralité du frais et toutes ses tranches associées.
                    </div>

                    <!-- Montant et Date -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="complet_montant" class="form-label mb-1" style="font-size: 0.9rem;">Montant à payer <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="complet_montant" name="montant" required readonly>
                                <span class="input-group-text" id="complet_devise_input"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="complet_date_valeur" class="form-label mb-1" style="font-size: 0.9rem;">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="complet_date_valeur" name="date_valeur" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Mode et Référence -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="complet_mode_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Mode <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="complet_mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Virement">Virement</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Carte bancaire">Carte</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="complet_reference_externe" class="form-label mb-1" style="font-size: 0.9rem;">Référence</label>
                            <input type="text" class="form-control form-control-sm" id="complet_reference_externe" name="reference_externe" placeholder="N° chèque...">
                        </div>
                    </div>

                    <!-- Source et Caisse/Banque -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="complet_source_paiement" class="form-label mb-1" style="font-size: 0.9rem;">Source <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="complet_source_paiement" name="source_paiement" required onchange="toggleCompletSourceOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Caisse">Caisse</option>
                                <option value="Banque">Banque</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="complet_caisse_container" style="display: none;">
                            <label for="complet_caisse_id" class="form-label mb-1" style="font-size: 0.9rem;">Caisse <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="complet_caisse_id" name="caisse_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($caisses as $caisse): ?>
                                    <option value="<?= $caisse['id'] ?>" data-devise="<?= $caisse['devise'] ?>">
                                        <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="complet_banque_container" style="display: none;">
                            <label for="complet_compte_bancaire_id" class="form-label mb-1" style="font-size: 0.9rem;">Compte <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="complet_compte_bancaire_id" name="compte_bancaire_id">
                                <option value="">Sélectionner</option>
                                <?php foreach ($comptes_bancaires as $compte): ?>
                                    <option value="<?= $compte['id'] ?>" data-devise="<?= $compte['devise'] ?>">
                                        <?= htmlspecialchars($compte['nom_banque'] . ' - ' . $compte['intitule_compte']) ?> (<?= $compte['devise'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Commentaire -->
                    <div class="mb-2">
                        <label for="complet_commentaire" class="form-label mb-1" style="font-size: 0.9rem;">Commentaire</label>
                        <textarea class="form-control form-control-sm" id="complet_commentaire" name="commentaire" rows="1" style="resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-sm btn-success">Enregistrer le paiement complet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Fonction pour basculer entre les champs de recherche
    function toggleSearchFields() {
        const typeRecherche = document.getElementById('type_recherche').value;
        const matriculeContainer = document.getElementById('matricule_container');
        const nomContainer = document.getElementById('nom_container');

        if (typeRecherche === 'matricule') {
            matriculeContainer.style.display = 'block';
            nomContainer.style.display = 'none';
            document.getElementById('matricule').setAttribute('required', 'required');
            document.getElementById('nom_recherche').removeAttribute('required');
        } else {
            matriculeContainer.style.display = 'none';
            nomContainer.style.display = 'block';
            document.getElementById('matricule').removeAttribute('required');
            document.getElementById('nom_recherche').setAttribute('required', 'required');
        }
    }

    // Fonction pour basculer entre les options de source de paiement
    function toggleSourceOptions() {
        const sourcePaiement = document.getElementById('source_paiement').value;
        const caisseContainer = document.getElementById('caisse_container');
        const banqueContainer = document.getElementById('banque_container');

        if (sourcePaiement === 'Caisse') {
            caisseContainer.style.display = 'block';
            banqueContainer.style.display = 'none';
            document.getElementById('caisse_id').setAttribute('required', 'required');
            document.getElementById('compte_bancaire_id').removeAttribute('required');
        } else if (sourcePaiement === 'Banque') {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'block';
            document.getElementById('caisse_id').removeAttribute('required');
            document.getElementById('compte_bancaire_id').setAttribute('required', 'required');
        } else {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'none';
            document.getElementById('caisse_id').removeAttribute('required');
            document.getElementById('compte_bancaire_id').removeAttribute('required');
        }
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

    // Fonction pour basculer entre les options de source de paiement pour le montant total
    function toggleCompletSourceOptions() {
        const sourcePaiement = document.getElementById('complet_source_paiement').value;
        const caisseContainer = document.getElementById('complet_caisse_container');
        const banqueContainer = document.getElementById('complet_banque_container');

        if (sourcePaiement === 'Caisse') {
            caisseContainer.style.display = 'block';
            banqueContainer.style.display = 'none';
            document.getElementById('complet_caisse_id').setAttribute('required', 'required');
            document.getElementById('complet_compte_bancaire_id').removeAttribute('required');
        } else if (sourcePaiement === 'Banque') {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'block';
            document.getElementById('complet_caisse_id').removeAttribute('required');
            document.getElementById('complet_compte_bancaire_id').setAttribute('required', 'required');
        } else {
            caisseContainer.style.display = 'none';
            banqueContainer.style.display = 'none';
            document.getElementById('complet_caisse_id').removeAttribute('required');
            document.getElementById('complet_compte_bancaire_id').removeAttribute('required');
        }
    }

    // Initialisation des modals de paiement
    document.addEventListener('DOMContentLoaded', function() {
        // Modal de paiement normal
        const paiementModal = document.getElementById('paiementModal');
        if (paiementModal) {
            paiementModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const affectationId = button.getAttribute('data-affectation-id');
                const fraisDesignation = button.getAttribute('data-frais-designation');
                const montantRestant = parseFloat(button.getAttribute('data-montant-restant'));
                const devise = button.getAttribute('data-devise');

                document.getElementById('affectation_id').value = affectationId;
                document.getElementById('frais_designation').textContent = fraisDesignation;
                document.getElementById('montant_restant').textContent = montantRestant.toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('devise').textContent = devise;
                document.getElementById('devise_input').textContent = devise;

                // Définir le montant maximum
                document.getElementById('montant').setAttribute('max', montantRestant);
                document.getElementById('montant').value = montantRestant.toFixed(2);
            });
        }

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

                document.getElementById('tranche_affectation_id').value = affectationId;
                document.getElementById('echelonnement_id').value = echelonnementId;
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

        // Validation des devises pour les paiements en caisse
        const caisseSelect = document.getElementById('caisse_id');
        if (caisseSelect) {
            caisseSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const caisseDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('devise').textContent;

                    if (caisseDevise !== fraisDevise) {
                        alert(`Attention: La devise de la caisse (${caisseDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir une caisse avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }

        // Validation des devises pour les paiements bancaires
        const compteSelect = document.getElementById('compte_bancaire_id');
        if (compteSelect) {
            compteSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const compteDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('devise').textContent;

                    if (compteDevise !== fraisDevise) {
                        alert(`Attention: La devise du compte bancaire (${compteDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir un compte avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }

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

        // Validation du mode de paiement
        const modePaiement = document.getElementById('mode_paiement');
        const referenceExterne = document.getElementById('reference_externe');

        if (modePaiement && referenceExterne) {
            modePaiement.addEventListener('change', function() {
                const mode = this.value;
                if (mode === 'Chèque' || mode === 'Virement' || mode === 'Mobile Money') {
                    referenceExterne.setAttribute('required', 'required');
                    referenceExterne.parentElement.querySelector('small').classList.add('text-danger');
                } else {
                    referenceExterne.removeAttribute('required');
                    referenceExterne.parentElement.querySelector('small').classList.remove('text-danger');
                }
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

        // Modal de paiement du montant total (frais échelonnables)
        const paiementCompletModal = document.getElementById('paiementCompletModal');
        if (paiementCompletModal) {
            paiementCompletModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const affectationId = button.getAttribute('data-affectation-id');
                const fraisDesignation = button.getAttribute('data-frais-designation');
                const montantTotal = parseFloat(button.getAttribute('data-montant-total'));
                const devise = button.getAttribute('data-devise');

                document.getElementById('complet_affectation_id').value = affectationId;
                document.getElementById('complet_frais_designation').textContent = fraisDesignation;
                document.getElementById('complet_montant_total').textContent = montantTotal.toLocaleString('fr-FR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('complet_devise').textContent = devise;
                document.getElementById('complet_devise_input').textContent = devise;

                // Montant à payer égal au montant total
                document.getElementById('complet_montant').value = montantTotal.toFixed(2);
            });
        }

        // Validation des devises pour le paiement complet en caisse
        const completCaisseSelect = document.getElementById('complet_caisse_id');
        if (completCaisseSelect) {
            completCaisseSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const caisseDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('complet_devise').textContent;

                    if (caisseDevise !== fraisDevise) {
                        alert(`Attention: La devise de la caisse (${caisseDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir une caisse avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }

        // Validation des devises pour le paiement complet bancaire
        const completCompteSelect = document.getElementById('complet_compte_bancaire_id');
        if (completCompteSelect) {
            completCompteSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const compteDevise = selectedOption.getAttribute('data-devise');
                    const fraisDevise = document.getElementById('complet_devise').textContent;

                    if (compteDevise !== fraisDevise) {
                        alert(`Attention: La devise du compte bancaire (${compteDevise}) est différente de celle du frais (${fraisDevise}). Veuillez choisir un compte avec la même devise.`);
                        this.value = '';
                    }
                }
            });
        }

        // Validation du mode de paiement pour le montant total
        const completModePaiement = document.getElementById('complet_mode_paiement');
        const completReferenceExterne = document.getElementById('complet_reference_externe');

        if (completModePaiement && completReferenceExterne) {
            completModePaiement.addEventListener('change', function() {
                const mode = this.value;
                if (mode === 'Chèque' || mode === 'Virement' || mode === 'Mobile Money') {
                    completReferenceExterne.setAttribute('required', 'required');
                } else {
                    completReferenceExterne.removeAttribute('required');
                }
            });
        }
    });
</script>

<?php
// Vérifier si c'est un succès de paiement
$success = isset($_GET['success']) && $_GET['success'] == 1;
$paiement_success = isset($_SESSION['paiement_success']) ? $_SESSION['paiement_success'] : null;

if ($success && $paiement_success) {
    unset($_SESSION['paiement_success']);
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Paiement enregistré avec succès!',
                html: `
                <div style="text-align: left; margin: 20px 0;">
                    <p><strong>Numéro de reçu:</strong> <?= htmlspecialchars($paiement_success['numero_recu']) ?></p>
                    <p><strong>Montant:</strong> <?= number_format($paiement_success['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($paiement_success['devise']) ?></p>
                    <p><strong>Frais:</strong> <?= htmlspecialchars($paiement_success['frais']) ?></p>
                </div>
                <div style="margin-top: 20px;">
                    <p style="text-align: center; color: #666; font-size: 14px;">Choisissez le format d'impression :</p>
                </div>
            `,
                icon: 'success',
                showCloseButton: true,
                focusConfirm: false,
                footer: '<button type="button" class="btn btn-secondary btn-sm" onclick="Swal.close()">Fermer</button>',
                html: `
                <div style="text-align: left; margin: 20px 0;">
                    <p><strong>Numéro de reçu:</strong> <?= htmlspecialchars($paiement_success['numero_recu']) ?></p>
                    <p><strong>Montant:</strong> <?= number_format($paiement_success['montant'], 2, ',', ' ') ?> <?= htmlspecialchars($paiement_success['devise']) ?></p>
                    <p><strong>Frais:</strong> <?= htmlspecialchars($paiement_success['frais']) ?></p>
                </div>
                <div style="margin-top: 25px;">
                    <p style="text-align: center; color: #666; font-size: 14px; margin-bottom: 15px;">Choisissez le format d'impression :</p>
                    <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary" onclick="window.open('controller/generer_recu_format.php?id=<?= $paiement_success['id'] ?>&format=A4', '_blank'); Swal.close();">
                            <i class="bi bi-file-earmark-text"></i> A4
                        </button>
                        <button type="button" class="btn btn-info" onclick="window.open('controller/generer_recu_format.php?id=<?= $paiement_success['id'] ?>&format=A5', '_blank'); Swal.close();">
                            <i class="bi bi-file-earmark"></i> A5
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.open('controller/generer_recu_format.php?id=<?= $paiement_success['id'] ?>&format=POS', '_blank'); Swal.close();">
                            <i class="bi bi-receipt"></i> POS
                        </button>
                        <button type="button" class="btn btn-success" onclick="window.open('controller/generer_recu_format.php?id=<?= $paiement_success['id'] ?>&format=A4-double', '_blank'); Swal.close();">
                            <i class="bi bi-file-earmark-ruled"></i> A4 Double
                        </button>
                    </div>
                </div>
            `,
                showConfirmButton: false,
                width: 600
            });
        });
    </script>
<?php
}
?>

<!-- Modal pour annulation de paiement -->
<div class="modal fade" id="annulationPaiementModal" tabindex="-1" aria-labelledby="annulationPaiementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="annulationPaiementModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Annuler un paiement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Attention :</strong> Cette action est irréversible. L'annulation du paiement entraînera :
                    <ul class="mb-0 mt-2">
                        <li>L'extourne de la transaction dans la caisse/banque</li>
                        <li>La mise à jour du statut de paiement du frais</li>
                        <li>La recalculation de la situation financière de l'étudiant</li>
                    </ul>
                </div>
                
                <div id="infoPaiementAnnulation" class="mb-3 p-3 bg-light rounded">
                    <!-- Informations du paiement injectées par JavaScript -->
                </div>
                
                <div class="mb-3">
                    <label for="motifAnnulation" class="form-label">Motif de l'annulation <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motifAnnulation" rows="3" required 
                        placeholder="Ex: Erreur de saisie, paiement en double, demande de l'étudiant..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="btnConfirmerAnnulation">
                    <i class="bi bi-x-circle me-1"></i>Confirmer l'annulation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variable globale pour stocker l'ID du paiement à annuler
let paiementIdAnnulation = null;

// Fonction pour ouvrir le modal de confirmation d'annulation
function confirmerAnnulation(paiementId, fraisDesignation, montant, devise) {
    paiementIdAnnulation = paiementId;
    
    // Remplir les informations du paiement
    document.getElementById('infoPaiementAnnulation').innerHTML = `
        <p class="mb-1"><strong>Frais :</strong> ${fraisDesignation}</p>
        <p class="mb-0"><strong>Montant :</strong> ${parseFloat(montant).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${devise}</p>
    `;
    
    // Vider le champ motif
    document.getElementById('motifAnnulation').value = '';
    
    // Ouvrir le modal
    const modal = new bootstrap.Modal(document.getElementById('annulationPaiementModal'));
    modal.show();
}

// Gestionnaire du bouton de confirmation d'annulation
document.getElementById('btnConfirmerAnnulation').addEventListener('click', function() {
    const motif = document.getElementById('motifAnnulation').value.trim();
    
    if (!motif) {
        Swal.fire({
            icon: 'error',
            title: 'Motif requis',
            text: 'Veuillez saisir un motif pour l\'annulation.'
        });
        return;
    }
    
    if (!paiementIdAnnulation) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ID du paiement non trouvé.'
        });
        return;
    }
    
    // Fermer le modal
    bootstrap.Modal.getInstance(document.getElementById('annulationPaiementModal')).hide();
    
    // Afficher le loader
    Swal.fire({
        title: 'Annulation en cours...',
        text: 'Veuillez patienter',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Envoyer la requête d'annulation
    const formData = new FormData();
    formData.append('paiement_id', paiementIdAnnulation);
    formData.append('motif_annulation', motif);
    
    fetch('controller/annuler_paiement.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Paiement annulé',
                text: data.message,
                confirmButtonText: 'OK'
            }).then(() => {
                // Recharger la page pour afficher les modifications
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'annulation.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Erreur de communication avec le serveur.'
        });
    });
});
</script>

<?php
include "./views/include/footer.php";
?>