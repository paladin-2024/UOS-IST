<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer toutes les années académiques
$stmt = $connexion->query("SELECT idannee_acad, designation, est_active FROM annee_acad ORDER BY designation DESC");
$annees_academiques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique en cours (active, sinon la plus récente)
$annee_encours = null;
foreach ($annees_academiques as $annee) {
    if ($annee['est_active'] == 1) {
        $annee_encours = $annee;
        break;
    }
}
if (!$annee_encours && count($annees_academiques) > 0) {
    $annee_encours = $annees_academiques[0];
}

// Récupérer les années sélectionnées (source et destination)
$selectedAnneeSource = isset($_GET['annee_source']) ? intval($_GET['annee_source']) : 0;
$selectedAnneeCible = isset($_GET['annee_cible']) ? intval($_GET['annee_cible']) : ($annee_encours ? $annee_encours['idannee_acad'] : 0);

// Si aucune année source sélectionnée, prendre l'avant-dernière année
if ($selectedAnneeSource == 0 && count($annees_academiques) > 1) {
    $selectedAnneeSource = $annees_academiques[1]['idannee_acad'];
}

// Récupérer les promotions sources (selon l'année source sélectionnée)
$promotions_sources = [];
if ($selectedAnneeSource > 0) {
    $stmt = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle,
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE aa.idannee_acad = ?
        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"
    ");
    $stmt->execute([$selectedAnneeSource]);
    $promotions_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les promotions cibles (selon l'année cible sélectionnée)
$promotions_cibles = [];
if ($selectedAnneeCible > 0) {
    $stmt = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle,
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE aa.idannee_acad = ?
        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"
    ");
    $stmt->execute([$selectedAnneeCible]);
    $promotions_cibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer la promotion source sélectionnée
$selectedPromoSource = isset($_GET['promotion_source']) ? intval($_GET['promotion_source']) : 0;

// Récupérer la promotion cible sélectionnée
$selectedPromoCible = isset($_GET['promotion_cible']) ? intval($_GET['promotion_cible']) : 0;

// Récupérer les étudiants de la promotion source
$etudiants = [];
if ($selectedPromoSource > 0) {
    $stmt = $connexion->prepare("
        SELECT e.idetudiant, e.matricule, e.noms, e.sexe, e.telephone, e.adressemail
        FROM etudiant e
        WHERE e.promotion_idpromotion = ? AND e.est_actif=1
        ORDER BY e.noms
    ");
    $stmt->execute([$selectedPromoSource]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les informations sur la promotion source
$promoSourceInfo = null;
if ($selectedPromoSource > 0) {
    $stmt = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle, 
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE p.idpromotion = ?
    ");
    $stmt->execute([$selectedPromoSource]);
    $promoSourceInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les informations sur la promotion cible
$promoCibleInfo = null;
if ($selectedPromoCible > 0) {
    $stmt = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle, 
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE p.idpromotion = ?
    ");
    $stmt->execute([$selectedPromoCible]);
    $promoCibleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les moyennes et crédits d'un étudiant
function getHistoriqueEtudiant($connexion, $matricule) {
    // Récupérer les moyennes annuelles
    $stmt = $connexion->prepare("
        SELECT ma.idpromotion, p.\"designationPromotion\", aa.designation as annee_academique,
               ma.moyenne_deliberee, ma.est_admis, ma.credits_obtenus, ma.credits_total,
               ma.mention
        FROM moyenne_annuelle ma
        JOIN promotion p ON ma.idpromotion = p.idpromotion
        JOIN annee_acad aa ON ma.annee_acad_idannee_acad = aa.idannee_acad
        WHERE ma.matricule = ?
        ORDER BY aa.designation DESC
    ");
    $stmt->execute([$matricule]);
    $moyennes_annuelles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les moyennes par semestre
    $stmt = $connexion->prepare("
        SELECT ms.idsemestre, s.\"numeroSemestre\", p.\"designationPromotion\",
               aa.designation as annee_academique, ms.moyenne_deliberee,
               ms.est_valide, ms.credits_obtenus, ms.credits_total
        FROM moyenne_semestre ms
        JOIN semestre s ON ms.idsemestre = s.idsemestre
        JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
        JOIN annee_acad aa ON ms.annee_acad_idannee_acad = aa.idannee_acad
        WHERE ms.matricule = ?
        ORDER BY aa.designation DESC, s.\"numeroSemestre\"
    ");
    $stmt->execute([$matricule]);
    $moyennes_semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'moyennes_annuelles' => $moyennes_annuelles,
        'moyennes_semestres' => $moyennes_semestres
    ];
}

// Fonction pour récupérer le total des crédits validés d'un étudiant (toutes années confondues)
function getCreditsValidesPromotion($connexion, $matricule, $idPromotion, $anneeAcadId, $promotionNom, $anneeDesignation) {
    $totalCredits = 0;
    $found = false;
    
    // Récupérer le crédit horaire depuis la configuration
    $creditHeure = 25;
    try {
        $configQuery = $connexion->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
        $configResult = $configQuery->fetch(PDO::FETCH_ASSOC);
        if ($configResult && isset($configResult['credit_heure'])) {
            $creditHeure = (float)$configResult['credit_heure'];
        }
    } catch (Exception $e) {}
    
    // Source 1: cotes_grille — même logique que grille_notes.php
    // Moyenne pondérée par UE = SUM(MF * coeff) / SUM(coeff), UE validée si >= 10
    // Crédits UE = SUM(CMI + TD + TP) / creditHeure
    try {
        $stmt = $connexion->prepare("
            SELECT sub.\"idUE\", sub.moyenne_ue, sub.credits_ue
            FROM (
                SELECT ue.\"idUE\",
                       SUM(bn.best_mf * (ec.\"CMI\" + ec.\"TD\" + ec.\"TP\")) / NULLIF(SUM(ec.\"CMI\" + ec.\"TD\" + ec.\"TP\"), 0) as moyenne_ue,
                       SUM(ec.\"CMI\" + ec.\"TD\" + ec.\"TP\") / ? as credits_ue,
                       (SELECT COUNT(*) FROM ecue e2 WHERE e2.\"UE_idUE\" = ue.\"idUE\") as nb_ecue_total,
                       COUNT(ec.\"idECUE\") as nb_ecue_inscrites,
                       SUM(CASE WHEN bn.best_mf IS NOT NULL THEN 1 ELSE 0 END) as nb_ecue_avec_note
                FROM ecue ec
                JOIN ue ON ec.\"UE_idUE\" = ue.\"idUE\"
                JOIN (
                    SELECT cg.\"ECUE_idECUE\", MAX(cg.\"MF\") as best_mf
                    FROM cotes_grille cg
                    WHERE cg.matricule = ?
                    GROUP BY cg.\"ECUE_idECUE\"
                ) bn ON bn.\"ECUE_idECUE\" = ec.\"idECUE\"
                GROUP BY ue.\"idUE\"
            ) as sub
            WHERE sub.nb_ecue_avec_note = GREATEST(sub.nb_ecue_total, sub.nb_ecue_inscrites)
            AND sub.moyenne_ue >= 10
        ");
        $stmt->execute([$creditHeure, $matricule]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($results)) {
            foreach ($results as $row) {
                $totalCredits += round(floatval($row['credits_ue']));
            }
            $found = true;
        }
    } catch (Exception $e) {
        error_log("Erreur vérification crédits cotes_grille: " . $e->getMessage());
    }
    
    // Source 2: grilles anciennes (résultats importés historiques)
    try {
        $tableCheck = $connexion->query("SHOW TABLES LIKE 'grilles_anciennes_notes'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $connexion->prepare("
                SELECT SUM(sub.credits) as credits_obtenus
                FROM (
                    SELECT ue.id, ue.credits, AVG(n.note_finale) as moyenne_ue
                    FROM grilles_anciennes_etudiants e
                    JOIN grilles_anciennes_notes n ON e.id = n.etudiant_id
                    JOIN grilles_anciennes_ecue ec ON n.ecue_id = ec.id
                    JOIN grilles_anciennes_ue ue ON ec.ue_id = ue.id
                    WHERE e.matricule = ?
                    AND n.note_finale IS NOT NULL
                    GROUP BY ue.id, ue.credits
                    HAVING moyenne_ue >= 10
                ) as sub
            ");
            $stmt->execute([$matricule]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['credits_obtenus'] !== null) {
                $totalCredits += intval($result['credits_obtenus']);
                $found = true;
            }
        }
    } catch (Exception $e) {
        error_log("Erreur vérification crédits grilles anciennes: " . $e->getMessage());
    }
    
    return $found ? $totalCredits : null;
}

// Calculer les crédits validés pour chaque étudiant de la promotion source
if (!empty($etudiants) && $promoSourceInfo) {
    foreach ($etudiants as &$etudiant) {
        $etudiant['credits_valides'] = getCreditsValidesPromotion(
            $connexion, $etudiant['matricule'], 
            $selectedPromoSource, $selectedAnneeSource,
            $promoSourceInfo['designationPromotion'],
            $promoSourceInfo['annee_academique']
        );
    }
    unset($etudiant);
}

// Traitement de la réinscription
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reinscription'])) {
    $etudiantsAReinscrire = isset($_POST['etudiant']) ? $_POST['etudiant'] : [];
    
    if (empty($etudiantsAReinscrire)) {
        $message = "Aucun étudiant sélectionné pour la réinscription.";
        $messageType = "warning";
    } else {
        try {
            $connexion->beginTransaction();
            
            // 1. Désactiver les anciens enregistrements
            $stmtDesactiver = $connexion->prepare("
                UPDATE etudiant 
                SET est_actif = 0 
                WHERE idetudiant = ?
            ");
            
            // 2. Requête pour récupérer les données complètes d'un étudiant
            $stmtGetEtudiant = $connexion->prepare("
                SELECT * FROM etudiant WHERE idetudiant = ?
            ");
            
            // 3. Requête pour insérer un nouvel enregistrement étudiant
            $stmtInsertEtudiant = $connexion->prepare("
                INSERT INTO etudiant (
                    matricule, noms, lieuNaissance, dateNaissance, adressemail, 
                    telephone, adresse, personne_contact, telephone_contact, photo, 
                    pwd, sexe, nationalite, dateEnregistrement, 
                    annee_acad_idannee_acad, promotion_idpromotion, idUser, est_actif
                ) VALUES (
                    :matricule, :noms, :lieuNaissance, :dateNaissance, :adressemail, 
                    :telephone, :adresse, :personne_contact, :telephone_contact, :photo, 
                    :pwd, :sexe, :nationalite, NOW(), 
                    :annee_acad_idannee_acad, :promotion_idpromotion, :idUser, 1
                )
            ");
            
            // 4. Ajouter une entrée dans le journal des activités
            $stmtJournal = $connexion->prepare("
                INSERT INTO journal_activites (
                    user_type, user_id, type_activite, id_element, description, date_activite
                ) VALUES (
                    'admin', :user_id, 'reinscription', :id_etudiant, 
                    :description, NOW()
                )
            ");
            
            $compteur = 0;
            $etudiantsRejetes = [];
            foreach ($etudiantsAReinscrire as $idEtudiant) {
                // Récupérer les données complètes de l'étudiant
                $stmtGetEtudiant->execute([$idEtudiant]);
                $etudiant = $stmtGetEtudiant->fetch(PDO::FETCH_ASSOC);

                if ($etudiant) {
                    /* // TEMPORAIREMENT DÉSACTIVÉ: Vérification des crédits validés (minimum 45 requis)
                    if ($promoSourceInfo) {
                        $creditsValides = getCreditsValidesPromotion(
                            $connexion, $etudiant['matricule'], 
                            $selectedPromoSource, $selectedAnneeSource,
                            $promoSourceInfo['designationPromotion'],
                            $promoSourceInfo['annee_academique']
                        );
                        $creditsValides = $creditsValides !== null ? intval($creditsValides) : 0;
                        if ($creditsValides < 45) {
                            $etudiantsRejetes[] = $etudiant['noms'] . ' (' . $creditsValides . ' crédits)';
                            continue;
                        }
                    }
                    */ // FIN TEMPORAIREMENT DÉSACTIVÉ

                    // Vérifier si l'étudiant est déjà actif dans la promotion cible
                    $stmtCheck = $connexion->prepare("
                        SELECT COUNT(*) as count FROM etudiant
                        WHERE matricule = ? AND promotion_idpromotion = ? AND annee_acad_idannee_acad = ? AND est_actif = 1
                    ");
                    $stmtCheck->execute([$etudiant['matricule'], $selectedPromoCible, $selectedAnneeCible]);
                    $exists = $stmtCheck->fetch(PDO::FETCH_ASSOC)['count'] > 0;

                    if ($exists) {
                        // Étudiant déjà inscrit dans la promotion cible, sauter
                        continue;
                    }

                    // Désactiver l'ancien enregistrement
                    $stmtDesactiver->execute([$idEtudiant]);

                    // Insérer un nouvel enregistrement avec la nouvelle promotion et année académique
                    $stmtInsertEtudiant->execute([
                        'matricule' => $etudiant['matricule'],
                        'noms' => $etudiant['noms'],
                        'lieuNaissance' => $etudiant['lieuNaissance'],
                        'dateNaissance' => $etudiant['dateNaissance'],
                        'adressemail' => $etudiant['adressemail'],
                        'telephone' => $etudiant['telephone'],
                        'adresse' => $etudiant['adresse'],
                        'personne_contact' => $etudiant['personne_contact'],
                        'telephone_contact' => $etudiant['telephone_contact'],
                        'photo' => $etudiant['photo'],
                        'pwd' => $etudiant['pwd'],
                        'sexe' => $etudiant['sexe'],
                        'nationalite' => $etudiant['nationalite'],
                        'annee_acad_idannee_acad' => $selectedAnneeCible,
                        'promotion_idpromotion' => $selectedPromoCible,
                        'idUser' => $_SESSION['id']
                    ]);
                    
                    // Récupérer l'ID du nouvel enregistrement
                    $nouvelIdEtudiant = $connexion->lastInsertId();
                    
                    // Ajouter une entrée dans le journal
                    $description = "Réinscription de l'étudiant {$etudiant['noms']} (matricule: {$etudiant['matricule']}) " .
                                  "de la promotion {$promoSourceInfo['designationPromotion']} vers {$promoCibleInfo['designationPromotion']}";
                    
                    $stmtJournal->execute([
                        'user_id' => $_SESSION['id'],
                        'id_etudiant' => $nouvelIdEtudiant,
                        'description' => $description
                    ]);
                    
                    $compteur++;
                }
            }
            
            $connexion->commit();
            
            $message = "$compteur étudiant(s) réinscrit(s) avec succès.";
            $messageType = "success";
            
            if (!empty($etudiantsRejetes)) {
                // TEMPORAIREMENT DÉSACTIVÉ: message d'erreur crédits insuffisants
                // $message .= "<br><i class='bi bi-exclamation-triangle'></i> <strong>" . count($etudiantsRejetes) . " étudiant(s) rejeté(s)</strong> (crédits validés insuffisants, minimum 45 requis) : " . implode(', ', $etudiantsRejetes);
                // $messageType = $compteur > 0 ? "warning" : "danger";
            }
        } catch (Exception $e) {
            $connexion->rollBack();
            $message = "Erreur lors de la réinscription : " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Réinscription des Étudiant(e)s</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Réinscription</li>
            </ol>
        </nav>
    </div>

    <section class="section py-2">
        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-2" style="position: relative; z-index: 10;">
                    <div class="card-body py-3">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show py-2 mb-2" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Formulaire compact sur une seule ligne -->
                        <form method="GET" action="" id="formReinscription">
                            <input type="hidden" name="view" value="etudiants/reinscription_etudiants">
                            
                            <div class="row g-2 align-items-end">
                                <!-- SOURCE -->
                                <div class="col-12 col-xl-5">
                                    <div class="bg-light rounded p-2 border-start border-primary border-3">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-box-arrow-right text-primary me-1"></i>
                                            <small class="fw-bold text-primary">SOURCE</small>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <select class="form-select form-select-sm" name="annee_source" id="annee_source" required>
                                                    <option value="">Année...</option>
                                                    <?php foreach ($annees_academiques as $annee): ?>
                                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $selectedAnneeSource == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($annee['designation']) ?><?= $annee['est_active'] == 1 ? ' ★' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <div class="position-relative">
                                                    <select class="form-select form-select-sm" name="promotion_source" id="promotion_source" required <?= empty($promotions_sources) ? 'disabled' : '' ?>>
                                                        <option value="">Promotion...</option>
                                                        <?php foreach ($promotions_sources as $promo): ?>
                                                            <option value="<?= $promo['idpromotion'] ?>" <?= $selectedPromoSource == $promo['idpromotion'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($promo['designationSection'] . ' / ' . $promo['designationOrientation'] . ' / ' . $promo['designationPromotion']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="spinner_promo_source" class="position-absolute top-50 end-0 translate-middle-y me-4" style="display: none;">
                                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Flèche -->
                                <div class="col-12 col-xl-1 text-center py-1">
                                    <i class="bi bi-arrow-right-circle-fill text-success d-none d-xl-inline" style="font-size: 1.8rem;"></i>
                                    <i class="bi bi-arrow-down-circle-fill text-success d-inline d-xl-none" style="font-size: 1.5rem;"></i>
                                </div>
                                
                                <!-- DESTINATION -->
                                <div class="col-12 col-xl-5">
                                    <div class="bg-light rounded p-2 border-start border-success border-3">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                                            <small class="fw-bold text-success">DESTINATION</small>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <select class="form-select form-select-sm border-success" name="annee_cible" id="annee_cible" required>
                                                    <option value="">Année...</option>
                                                    <?php foreach ($annees_academiques as $annee): ?>
                                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $selectedAnneeCible == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($annee['designation']) ?><?= $annee['est_active'] == 1 ? ' ★' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <div class="position-relative">
                                                    <select class="form-select form-select-sm border-success" name="promotion_cible" id="promotion_cible" required <?= empty($promotions_cibles) ? 'disabled' : '' ?>>
                                                        <option value="">Promotion...</option>
                                                        <?php foreach ($promotions_cibles as $promo): ?>
                                                            <option value="<?= $promo['idpromotion'] ?>" <?= $selectedPromoCible == $promo['idpromotion'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($promo['designationSection'] . ' / ' . $promo['designationOrientation'] . ' / ' . $promo['designationPromotion']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div id="spinner_promo_cible" class="position-absolute top-50 end-0 translate-middle-y me-4" style="display: none;">
                                                        <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Bouton -->
                                <div class="col-12 col-xl-1">
                                    <button type="submit" class="btn btn-primary w-100" id="btnCharger">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                        
                <?php if ($selectedPromoSource > 0 && $selectedPromoCible > 0): ?>
                <!-- Carte étudiants -->
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-white">
                        <div class="d-flex align-items-center gap-3">
                            <!-- Résumé compact du transfert -->
                            <span class="badge bg-primary"><?= htmlspecialchars($promoSourceInfo['designationPromotion']) ?> <small>(<?= $promoSourceInfo['annee_academique'] ?>)</small></span>
                            <i class="bi bi-arrow-right text-success"></i>
                            <span class="badge bg-success"><?= htmlspecialchars($promoCibleInfo['designationPromotion']) ?> <small>(<?= $promoCibleInfo['annee_academique'] ?>)</small></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if (!empty($etudiants)): ?>
                            <!-- Recherche instantanée -->
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0" id="searchEtudiant" placeholder="Rechercher..." autocomplete="off">
                            </div>
                            <?php endif; ?>
                            <span id="selectionCount" class="badge bg-secondary"><?= count($etudiants) ?> étudiants</span>
                            <?php if (!empty($etudiants)): ?>
                            <div class="form-check m-0">
                                <input class="form-check-input" type="checkbox" id="selectAll" title="Tout sélectionner">
                                <label class="form-check-label small" for="selectAll">Tous</label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($etudiants)): ?>
                            <div class="alert alert-warning m-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucun étudiant actif trouvé dans la promotion source.
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" id="formEtudiants">
                                <!-- TEMPORAIREMENT DESACTIVE: Message regle des credits -->
                                <!-- <div class="alert alert-info py-2 mx-3 mt-2 mb-0 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Règle :</strong> Minimum <strong>45 crédits validés</strong> requis dans la promotion source pour accepter la réinscription.
                                    Les étudiants ne remplissant pas cette condition sont grisés.
                                </div> -->
                                <!-- Tableau avec hauteur fixe et scroll -->
                                <div style="max-height: calc(100vh - 280px); overflow-y: auto; position: relative; z-index: 1;">
                                    <table class="table table-sm table-hover table-striped mb-0" id="tableEtudiants">
                                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                            <tr>
                                                <th style="width: 40px;" class="text-center"></th>
                                                <th style="width: 120px;">Matricule</th>
                                                <th>Nom complet</th>
                                                <th style="width: 120px;">Téléphone</th>
                                                <th style="width: 100px;" class="text-center">Crédits</th>
                                                <th style="width: 60px;" class="text-center">Hist.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants as $etudiant): 
                                                $cv = isset($etudiant['credits_valides']) && $etudiant['credits_valides'] !== null ? intval($etudiant['credits_valides']) : 0;
                                                // TEMPORAIREMENT DESACTIVE: $creditsSuffisants = ($cv >= 45);
                                                $creditsSuffisants = true; // Temporairement toujours eligible
                                            ?>
                                                <tr class="<?= !$creditsSuffisants ? 'table-danger opacity-75' : '' ?>">
                                                    <td class="text-center">
                                                        <input class="form-check-input etudiant-checkbox" type="checkbox" name="etudiant[]" value="<?= $etudiant['idetudiant'] ?>"
                                                            <?= !$creditsSuffisants ? 'disabled title="Crédits insuffisants (minimum 45 requis)"' : '' ?>>
                                                    </td>
                                                    <td><code class="small"><?= htmlspecialchars($etudiant['matricule']) ?></code></td>
                                                    <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                    <td class="small text-muted"><?= htmlspecialchars($etudiant['telephone'] ?: '-') ?></td>
                                                    <td class="text-center">
                                                        <?php if ($cv >= 45): ?>
                                                            <span class="badge bg-success" title="Crédits suffisants"><?= $cv ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger" title="Crédits insuffisants (minimum 45)"><?= $cv ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-link p-0 text-info"
                                                                onclick="voirHistorique('<?= $etudiant['matricule'] ?>', '<?= htmlspecialchars(addslashes($etudiant['noms'])) ?>')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Barre de confirmation fixe en bas -->
                                <div class="card-footer bg-warning-subtle border-top d-flex justify-content-between align-items-center py-2">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        L'ancien enregistrement sera désactivé, un nouveau sera créé dans la destination.
                                    </small>
                                    <button type="submit" name="reinscription" class="btn btn-success">
                                        <i class="bi bi-check-circle-fill me-1"></i> Confirmer la réinscription
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- Message d'attente compact -->
                <div class="card">
                    <div class="card-body text-center py-4 text-muted">
                        <i class="bi bi-arrow-up-circle" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">Sélectionnez les années et promotions ci-dessus</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour afficher l'historique de l'étudiant -->
<div class="modal fade" id="historiqueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historique académique de l'étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historiqueLoader" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des données...</p>
                </div>
                
                <div id="historiqueContent" style="display: none;">
                    <h6 id="etudiantNom" class="mb-3"></h6>
                    
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">Moyennes annuelles</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr class="table-light">
                                        <th>Promotion</th>
                                        <th>Année académique</th>
                                        <th>Moyenne</th>
                                        <th>Crédits obtenus</th>
                                        <th>Crédits total</th>
                                        <th>Mention</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="moyennesAnnuellesBody">
                                    <!-- Les données seront chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div>
                        <h6 class="border-bottom pb-2">Moyennes par semestre</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr class="table-light">
                                        <th>Semestre</th>
                                        <th>Promotion</th>
                                        <th>Année académique</th>
                                        <th>Moyenne</th>
                                        <th>Crédits obtenus</th>
                                        <th>Crédits total</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="moyennesSemestresBody">
                                    <!-- Les données seront chargées dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="recommandationText">Analyse en cours...</span>
                    </div>
                </div>
                
                <div id="historiqueError" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Une erreur s'est produite lors du chargement des données.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestionnaire pour la case à cocher "Tout sélectionner"
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.etudiant-checkbox:not(:disabled)');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectionCount();
        });
    }
    
    // Mettre à jour la case "Tout sélectionner" si toutes les cases sont cochées manuellement
    const etudiantCheckboxes = document.querySelectorAll('.etudiant-checkbox');
    etudiantCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const enabledCbs = document.querySelectorAll('.etudiant-checkbox:not(:disabled)');
            const allChecked = enabledCbs.length > 0 && Array.from(enabledCbs).every(cb => cb.checked);
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
            updateSelectionCount();
        });
    });
    
    // Fonction pour afficher le spinner et désactiver le select
    function showSpinnerAndDisable(selectId, spinnerId) {
        const select = document.getElementById(selectId);
        const spinner = document.getElementById(spinnerId);
        if (select && spinner) {
            select.disabled = true;
            select.classList.add('opacity-50');
            spinner.style.display = 'block';
        }
    }
    
    // Fonction pour soumettre le formulaire en rechargeant les promotions
    function rechargerPromotions(resetSource = false) {
        const form = document.getElementById('formReinscription');
        const promoSource = document.getElementById('promotion_source');
        const promoCible = document.getElementById('promotion_cible');
        
        // Afficher les spinners
        if (resetSource) {
            showSpinnerAndDisable('promotion_source', 'spinner_promo_source');
        }
        showSpinnerAndDisable('promotion_cible', 'spinner_promo_cible');
        
        // Désactiver la validation required pour permettre la soumission
        if (promoSource) {
            promoSource.removeAttribute('required');
            if (resetSource) promoSource.value = '';
        }
        if (promoCible) {
            promoCible.removeAttribute('required');
            promoCible.value = '';
        }
        
        // Soumettre le formulaire
        setTimeout(() => {
            form.submit();
        }, 200);
    }
    
    // Support natif + jQuery/Select2
    const anneeSourceEl = document.getElementById('annee_source');
    const anneeCibleEl = document.getElementById('annee_cible');
    
    // Écouteurs natifs (pour les select standard)
    if (anneeSourceEl) {
        anneeSourceEl.addEventListener('change', function() {
            rechargerPromotions(true);
        });
    }
    if (anneeCibleEl) {
        anneeCibleEl.addEventListener('change', function() {
            rechargerPromotions(false);
        });
    }
    
    // Écouteurs jQuery/Select2 (si jQuery est disponible)
    if (typeof jQuery !== 'undefined') {
        // Attendre un peu que Select2 soit initialisé
        setTimeout(function() {
            jQuery('#annee_source').off('change select2:select').on('change select2:select', function() {
                rechargerPromotions(true);
            });
            jQuery('#annee_cible').off('change select2:select').on('change select2:select', function() {
                rechargerPromotions(false);
            });
        }, 500);
    }
    
    // Compteur de sélection
    function updateSelectionCount() {
        const checked = document.querySelectorAll('.etudiant-checkbox:checked').length;
        const eligible = document.querySelectorAll('.etudiant-checkbox:not(:disabled)').length;
        const ineligible = document.querySelectorAll('.etudiant-checkbox:disabled').length;
        const countDisplay = document.getElementById('selectionCount');
        if (countDisplay) {
            let text = checked > 0 ? checked + ' / ' + eligible + ' sélectionné(s)' : eligible + ' éligible(s)';
            if (ineligible > 0) {
                text += ' | ' + ineligible + ' inéligible(s)';
            }
            countDisplay.textContent = text;
            countDisplay.className = checked > 0 ? 'badge bg-success' : 'badge bg-secondary';
        }
    }
    
    // Initialiser le compteur
    updateSelectionCount();
    
    // Recherche instantanée dans le tableau
    const searchInput = document.getElementById('searchEtudiant');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const table = document.getElementById('tableEtudiants');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const matricule = row.cells[1]?.textContent.toLowerCase() || '';
                const nom = row.cells[2]?.textContent.toLowerCase() || '';
                const telephone = row.cells[3]?.textContent.toLowerCase() || '';
                
                const match = matricule.includes(searchTerm) || 
                              nom.includes(searchTerm) || 
                              telephone.includes(searchTerm);
                
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });
            
            // Mettre à jour le compteur avec les résultats filtrés
            const countDisplay = document.getElementById('selectionCount');
            const checked = document.querySelectorAll('.etudiant-checkbox:checked').length;
            if (countDisplay) {
                if (searchTerm) {
                    countDisplay.textContent = visibleCount + ' trouvé(s)';
                    countDisplay.className = 'badge bg-info';
                } else {
                    updateSelectionCount();
                }
            }
        });
        
        // Effacer la recherche avec Escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        });
    }
});

// Fonction pour afficher l'historique d'un étudiant
function voirHistorique(matricule, nom) {
    // Réinitialiser le modal
    document.getElementById('historiqueLoader').style.display = 'block';
    document.getElementById('historiqueContent').style.display = 'none';
    document.getElementById('historiqueError').style.display = 'none';
    document.getElementById('etudiantNom').textContent = 'Étudiant: ' + nom + ' (' + matricule + ')';
    
    // Afficher le modal
    const historiqueModal = new bootstrap.Modal(document.getElementById('historiqueModal'));
    historiqueModal.show();
    
    // Charger les données
    fetch('controller/get_historique_etudiant.php?matricule=' + encodeURIComponent(matricule))
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Remplir le tableau des moyennes annuelles
            const moyennesAnnuellesBody = document.getElementById('moyennesAnnuellesBody');
            moyennesAnnuellesBody.innerHTML = '';
            
            if (data.moyennes_annuelles.length === 0) {
                moyennesAnnuellesBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune donnée disponible</td></tr>';
            } else {
                data.moyennes_annuelles.forEach(moyenne => {
                    const row = document.createElement('tr');
                    const estAdmisClass = moyenne.est_admis == 1 ? 'bg-success text-white' : 'bg-danger text-white';
                    const estAdmisText = moyenne.est_admis == 1 ? 'Admis' : 'Ajourné';
                    
                    row.innerHTML = `
                        <td>${moyenne.designationPromotion}</td>
                        <td>${moyenne.annee_academique}</td>
                        <td>${parseFloat(moyenne.moyenne_deliberee).toFixed(2)}</td>
                        <td>${moyenne.credits_obtenus}</td>
                        <td>${moyenne.credits_total}</td>
                        <td>${moyenne.mention || '-'}</td>
                        <td class="${estAdmisClass}">${estAdmisText}</td>
                    `;
                    moyennesAnnuellesBody.appendChild(row);
                });
            }
            
            // Remplir le tableau des moyennes par semestre
            const moyennesSemestresBody = document.getElementById('moyennesSemestresBody');
            moyennesSemestresBody.innerHTML = '';
            
            if (data.moyennes_semestres.length === 0) {
                moyennesSemestresBody.innerHTML = '<tr><td colspan="7" class="text-center">Aucune donnée disponible</td></tr>';
            } else {
                data.moyennes_semestres.forEach(semestre => {
                    const row = document.createElement('tr');
                    const estValideClass = semestre.est_valide == 1 ? 'bg-success text-white' : 'bg-danger text-white';
                    const estValideText = semestre.est_valide == 1 ? 'Validé' : 'Non validé';
                    
                    row.innerHTML = `
                        <td>${semestre.numeroSemestre}</td>
                        <td>${semestre.designationPromotion}</td>
                        <td>${semestre.annee_academique}</td>
                        <td>${parseFloat(semestre.moyenne_deliberee).toFixed(2)}</td>
                        <td>${semestre.credits_obtenus}</td>
                        <td>${semestre.credits_total}</td>
                        <td class="${estValideClass}">${estValideText}</td>
                    `;
                    moyennesSemestresBody.appendChild(row);
                });
            }
            
            // Générer une recommandation simple
            const recommandationText = document.getElementById('recommandationText');
            const derniereAnnee = data.moyennes_annuelles.length > 0 ? data.moyennes_annuelles[0] : null;
            
            if (derniereAnnee) {
                if (derniereAnnee.est_admis == 1) {
                    recommandationText.innerHTML = '<strong class="text-success">Recommandation:</strong> L\'étudiant a validé sa dernière année avec une moyenne de ' + 
                        parseFloat(derniereAnnee.moyenne_deliberee).toFixed(2) + ' et ' + derniereAnnee.credits_obtenus + '/' + derniereAnnee.credits_total + 
                        ' crédits. <strong>Réinscription recommandée</strong>.';
                } else {
                    const creditsSuffisants = derniereAnnee.credits_obtenus >= (derniereAnnee.credits_total * 0.8);
                    
                    if (creditsSuffisants) {
                        recommandationText.innerHTML = '<strong class="text-warning">Recommandation:</strong> L\'étudiant n\'a pas validé sa dernière année mais a obtenu ' + 
                            derniereAnnee.credits_obtenus + '/' + derniereAnnee.credits_total + 
                            ' crédits, ce qui peut être suffisant pour une réinscription conditionnelle. <strong>À évaluer au cas par cas</strong>.';
                    } else {
                        recommandationText.innerHTML = '<strong class="text-danger">Recommandation:</strong> L\'étudiant n\'a pas validé sa dernière année et n\'a obtenu que ' + 
                            derniereAnnee.credits_obtenus + '/' + derniereAnnee.credits_total + 
                            ' crédits. <strong>Réinscription non recommandée</strong> sans mesures d\'accompagnement spécifiques.';
                    }
                }
            } else {
                recommandationText.innerHTML = 'Aucune donnée de performance académique disponible pour formuler une recommandation.';
            }
            
            // Afficher le contenu
            document.getElementById('historiqueLoader').style.display = 'none';
            document.getElementById('historiqueContent').style.display = 'block';
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('historiqueLoader').style.display = 'none';
            document.getElementById('historiqueError').style.display = 'block';
            document.getElementById('historiqueError').textContent = 'Erreur: ' + error.message;
        });
}
</script>

<?php include "./views/include/footer.php"; ?>
