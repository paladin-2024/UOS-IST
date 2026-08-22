<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();
$deliberation = new Deliberation(); // Instancier notre nouveau modèle

// Vérifier si l'utilisateur est administrateur ou membre d'un jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryMember = false;
$isJuryPresident = false;

// Récupérer le crédit horaire et les pondérations depuis la configuration
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure, ponderation_cc_defaut, ponderation_ex_defaut FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$creditHeure = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;
$ponderationCCDefaut = $config && isset($config['ponderation_cc_defaut']) ? (float)$config['ponderation_cc_defaut'] : 0.4;
$ponderationEXDefaut = $config && isset($config['ponderation_ex_defaut']) ? (float)$config['ponderation_ex_defaut'] : 0.6;


// Récupérer les bureaux de jury où l'agent est membre
$juryBureaux = [];
if ($agentId) {
    $juryBureaux = $deliberation->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
    $isJuryPresident = $deliberation->isJuryPresident($agentId);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
    echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Accès refusé',
                    text: 'Vous n\'avez pas les droits pour accéder à cette page.'
                }).then(() => {
                    window.location.href = 'index';
                });
            </script>";
    exit();
}

// Récupérer les paramètres de sélection
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$avecRecours = isset($_GET['avec_recours']) && $_GET['avec_recours'] == 1;


// Récupérer l'information sur la session (première ou deuxième)
$sessionInfo = [];
if ($sessionId) {
    $sessionInfo = $universite->getSessionById($sessionId);
}
$isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

// Récupérer l'ID de la première session pour les comparaisons en deuxième session
$premiereSessionId = null;
if ($isDeuxiemeSession) {
    $premiereSessions = $universite->getSessions("Première session");
    if (!empty($premiereSessions)) {
        $premiereSessionId = $premiereSessions[0]['idsession'];
    }
}


$configDeliberation = null;
$calculerAvecNotesVides = false;

if ($bureauId && $sessionId && $anneeId) {
    $configDeliberation = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
    $calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ?
        (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;
}

// Validation des accès
if ($bureauId && !$isAdmin) {
    // Vérifier si l'agent est membre de ce bureau
    $hasAccess = false;
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId) {
            $hasAccess = true;
            break;
        }
    }

    if (!$hasAccess) {
        echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Accès refusé',
                        text: 'Vous n\'êtes pas autorisé à accéder à ce bureau de jury.'
                    }).then(() => {
                        window.location.href = 'deliberation/grille_notes';
                    });
                </script>";
        exit();
    }
}

// Récupérer les données pour les sélecteurs
if ($isAdmin) {
    $bureaux = $deliberation->getJurys('', true); // Tous les jurys actifs
} else {
    $bureaux = $juryBureaux; // Seulement les jurys où l'agent est membre
}

// Récupérer les promotions associées au bureau sélectionné
$promotions = [];
if ($bureauId) {
    $promotions = $deliberation->getPromotionsByJury($bureauId);
}

// Récupérer les semestres associés à la promotion sélectionnée
$semestres = [];
if ($promotionId) {
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
}

// Récupérer les sessions et années académiques
$sessions = $deliberation->getAllSessions();
$annees = $deliberation->getAcademicYears();

// Récupérer les données de la grille si tous les paramètres sont sélectionnés
$grilleData = null;
$etudiants = [];
$uesBySemestre = [];
$ecuesByUE = [];
$notesByEtudiantEcue = [];
$moyennesUE = [];
$validationsUE = [];
$moyennesSemestre = [];
$validationsSemestre = [];
$moyennesAnnuelles = [];
$validationsAnnuelles = [];

if ($bureauId && $promotionId && $sessionId && $anneeId && ($semestreId || $afficherDeuxSemestres)) {
    // Récupérer les semestres à afficher
    $semestresToShow = $afficherDeuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
        return $sem['idsemestre'] == $semestreId;
    }));


    // Vérifier si c'est la deuxième session
    $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;

    // Récupérer les étudiants de la promotion
    if ($isDeuxiemeSession) {
        // En deuxième session, ne récupérer que les étudiants éligibles
        $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestresToShow);
    } else {
        // En première session, récupérer tous les étudiants
        $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
    }

    // Filtrer les étudiants qui ont un recours si le checkbox est activé
    if ($avecRecours) {
        $query_recours = "SELECT DISTINCT matricule FROM recours 
                          WHERE id_annee_acad = :annee AND id_session = :session";
        $stmt_recours = $db->prepare($query_recours);
        $stmt_recours->bindParam(':annee', $anneeId);
        $stmt_recours->bindParam(':session', $sessionId);
        $stmt_recours->execute();
        $matriculesAvecRecours = $stmt_recours->fetchAll(PDO::FETCH_COLUMN);
        
        $etudiants = array_values(array_filter($etudiants, function($etudiant) use ($matriculesAvecRecours) {
            return in_array($etudiant['matricule'], $matriculesAvecRecours);
        }));
    }

    // Pour chaque semestre, récupérer les UE et ECUE
    foreach ($semestresToShow as $semestre) {
        $semId = $semestre['idsemestre'];
        $uesBySemestre[$semId] = $deliberation->getUEsBySemestre($semId);

        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $ecuesByUE[$ueId] = $ecue->getECUEsByUE2($ueId);
        }
    }

    // Initialiser les tableaux pour stocker les résultats calculés
    $moyennesUE = [];
    $validationsUE = [];
    $moyennesSemestre = [];
    $validationsSemestre = [];
    $moyennesAnnuelles = [];
    $validationsAnnuelles = [];

    // Récupérer les notes pour tous les étudiants et ECUE
    foreach ($etudiants as $etudiant) {
        $matricule = $etudiant['matricule'];

        // Pour chaque semestre à afficher
        foreach ($semestresToShow as $semestre) {
            $semId = $semestre['idsemestre'];
            $totalPointsSemestre = 0;
            $totalCreditsSemestre = 0;
            $creditsValidesSemestre = 0;
            $ecueAvecNotesSemestre = 0;
            $totalEcueSemestre = 0;
            $ueAvecMoyenne = 0;
            $totalUE = 0;

            // Pour chaque UE du semestre
            foreach ($uesBySemestre[$semId] as $ue) {
                $ueId = $ue['idUE'];
                $totalUE++;
                $totalPointsUE = 0;
                $totalCoeffUE = 0;
                $ecueCount = 0;
                $ecueWithNotesCount = 0;
                $ecueWithCompleteNotesCount = 0;

                // Vérifier si l'UE a été validée en première session
                $ueValideeEnPremiereSession = false;
                if ($isDeuxiemeSession) {
                    $moyenneUEPremiereSession = $deliberation->getMoyenneUEPremiereSession($matricule, $ueId, $anneeId);
                    $ueValideeEnPremiereSession = ($moyenneUEPremiereSession !== null && $moyenneUEPremiereSession >= 10);
                }

                // Pour chaque ECUE de l'UE
                foreach ($ecuesByUE[$ueId] as $ecueItem) {
                    $ecueId = $ecueItem['idECUE'];
                    $ecueCount++;
                    $totalEcueSemestre++;

                    // Récupérer la note de l'étudiant pour cet ECUE
                    if ($isDeuxiemeSession && $premiereSessionId) {
                        // Récupérer les notes des deux sessions
                        $notePremiereSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $premiereSessionId, $anneeId);
                        $noteDeuxiemeSession = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);

                        // Vérifier si l'ECUE a une note valide en première session
                        $hasValidS1Note = $notePremiereSession && $notePremiereSession['MF'] !== null;
                        $s1NoteGe10 = $hasValidS1Note && floatval($notePremiereSession['MF']) >= 10;

                        // Si l'ECUE avait une note >= 10 en première session, utiliser cette note
                        if ($s1NoteGe10) {
                            $notes = $notePremiereSession;
                        } 
                        // Si l'UE a été validée en S1 ET l'ECUE avait une note en S1 (même < 10), garder cette note
                        else if ($ueValideeEnPremiereSession && $hasValidS1Note) {
                            $notes = $notePremiereSession;
                        } 
                        // Sinon, utiliser la note de deuxième session (ou première session si pas de note S2)
                        else {
                            // Préférer la note de S2 si disponible, sinon S1
                            if ($noteDeuxiemeSession && $noteDeuxiemeSession['MF'] !== null) {
                                $notes = $noteDeuxiemeSession;
                            } else if ($hasValidS1Note) {
                                $notes = $notePremiereSession;
                            } else {
                                $notes = $noteDeuxiemeSession ?: $notePremiereSession;
                            }
                        }
                    } else {
                        $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
                    }

                    if ($notes) {
                        $notesByEtudiantEcue[$matricule][$ecueId] = $notes;

                        // Vérifier si les notes sont complètes selon la configuration
                        $notesCompletes = false;

                        if ($isDeuxiemeSession) {
                            // En deuxième session, seule la note d'examen est nécessaire
                            $notesCompletes = $notes['EX'] !== null;
                        } else {
                            // En première session, les deux notes sont nécessaires
                            $notesCompletes = $notes['CC'] !== null && $notes['EX'] !== null;
                        }

                        // Compter les ECUE avec des notes (complètes ou non)
                        if ($notes['MF'] !== null) {
                            $ecueWithNotesCount++;
                            $ecueAvecNotesSemestre++;
                        }

                        // Compter les ECUE avec des notes complètes
                        if ($notesCompletes) {
                            $ecueWithCompleteNotesCount++;
                        }

                        // Calculer le coefficient (crédits) de l'ECUE
                        $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $creditHeure;

                        // Toujours ajouter le coefficient au total de l'UE
                        $totalCoeffUE += $coeffECUE;

                        // Ajouter les points pondérés seulement si la note est disponible
                        if ($notes['MF'] !== null) {
                            $totalPointsUE += $notes['MF'] * $coeffECUE;
                        }
                    } else {
                        // Même si l'étudiant n'a pas de note, calculer le coefficient de l'ECUE
                        $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $creditHeure;
                        $totalCoeffUE += $coeffECUE;
                    }
                }

                // Calculer la moyenne de l'UE en tenant compte de la configuration
                if ($ecueCount > 0) {
                    // Vérifier si TOUTES les ECUEs ont une note (MF non null)
                    $toutesEcuesOntNote = ($ecueWithNotesCount == $ecueCount);
                    
                    // Si l'UE a été validée en première session
                    if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                        // En 2ème session, ne calculer la moyenne que si toutes les ECUEs ont une note
                        if ($toutesEcuesOntNote && $totalCoeffUE > 0) {
                            // Recalculer la moyenne UE avec les notes actuelles
                            $moyenneUE = $totalPointsUE / $totalCoeffUE;
                            $moyennesUE[$matricule][$ueId] = $moyenneUE;
                            // Validation basée sur la moyenne recalculée (>= 10)
                            $validationsUE[$matricule][$ueId] = $moyenneUE >= 10;

                            // Ajouter à la somme du semestre pour le calcul de la moyenne
                            $totalPointsSemestre += $totalPointsUE;
                            $ueAvecMoyenne++;

                            // Ajouter les crédits si l'UE est validée
                            if ($moyenneUE >= 10) {
                                $creditsValidesSemestre += $totalCoeffUE;
                            }
                        } else {
                            // Notes manquantes - pas de moyenne calculée
                            $moyennesUE[$matricule][$ueId] = null;
                            $validationsUE[$matricule][$ueId] = false;
                        }
                    }
                    // Sinon, calculer la moyenne normalement
                    else {
                        // En 2ème session: exiger que TOUTES les ECUEs aient une note (MF non null)
                        // En 1ère session: utiliser la configuration existante
                        $peutCalculerMoyenne = false;
                        
                        if ($isDeuxiemeSession) {
                            // En 2ème session, toutes les ECUEs doivent avoir une note
                            $peutCalculerMoyenne = $toutesEcuesOntNote;
                        } else {
                            // En 1ère session, utiliser la logique existante
                            $peutCalculerMoyenne = $calculerAvecNotesVides || $ecueWithCompleteNotesCount == $ecueCount;
                        }
                        
                        if ($peutCalculerMoyenne && $totalCoeffUE > 0) {
                            $moyenneUE = $totalPointsUE / $totalCoeffUE;
                            $moyennesUE[$matricule][$ueId] = $moyenneUE;
                            $validationsUE[$matricule][$ueId] = $moyenneUE >= 10;

                            // Ajouter à la somme du semestre pour le calcul de la moyenne
                            $totalPointsSemestre += $totalPointsUE;
                            $ueAvecMoyenne++;

                            // Ajouter les crédits si l'UE est validée
                            if ($moyenneUE >= 10) {
                                $creditsValidesSemestre += $totalCoeffUE;
                            }
                        } else {
                            // Notes manquantes - pas de moyenne calculée, UE non validée
                            $moyennesUE[$matricule][$ueId] = null;
                            $validationsUE[$matricule][$ueId] = false;
                        }
                    }

                    // Dans tous les cas, ajouter les crédits de l'UE au total du semestre
                    $totalCreditsSemestre += $totalCoeffUE;
                }
            }

            // Calculer la moyenne du semestre en tenant compte de la configuration
            if ($totalCreditsSemestre > 0) {
                // Si on est configuré pour calculer avec des notes vides ou si toutes les UE ont des moyennes
                if ($calculerAvecNotesVides || $ueAvecMoyenne == $totalUE) {
                    $moyenneSemestre = $totalPointsSemestre / $totalCreditsSemestre;
                    $moyennesSemestre[$matricule][$semId] = $moyenneSemestre;

                    // Calculer le pourcentage basé sur la moyenne (sur 20)
                    $pourcentageValidation = ($moyenneSemestre / 20) * 100;

                    $validationsSemestre[$matricule][$semId] = [
                        'credits_valides' => $creditsValidesSemestre,
                        'credits_total' => $totalCreditsSemestre,
                        'pourcentage' => $pourcentageValidation,
                        'est_valide' => $moyenneSemestre >= 10 // Considérer comme validé si moyenne >= 10
                    ];
                } else {
                    // Si on n'est pas configuré pour calculer avec des notes vides et qu'il manque des moyennes d'UE
                    $moyennesSemestre[$matricule][$semId] = null;
                    $validationsSemestre[$matricule][$semId] = [
                        'credits_valides' => $creditsValidesSemestre, // Garder les crédits validés
                        'credits_total' => $totalCreditsSemestre,     // Garder le total des crédits
                        'pourcentage' => 0,
                        'est_valide' => false
                    ];
                }
            }
        }



        // Si on affiche deux semestres, calculer la moyenne annuelle
        // Si on affiche deux semestres, calculer la moyenne annuelle
        if ($afficherDeuxSemestres && count($semestresToShow) >= 2) {
            $totalPointsAnnee = 0;
            $totalCreditsAnnee = 0;
            $creditsValidesAnnee = 0;
            $semestreAvecMoyenne = 0;

            foreach ($semestresToShow as $semestre) {
                $semId = $semestre['idsemestre'];

                // Ajouter les crédits validés et totaux, même si le semestre n'a pas de moyenne
                if (isset($validationsSemestre[$matricule][$semId])) {
                    // Ajouter les crédits validés (capitalisés)
                    $creditsValidesAnnee += $validationsSemestre[$matricule][$semId]['credits_valides'];

                    // Ajouter les crédits totaux
                    $totalCreditsAnnee += $validationsSemestre[$matricule][$semId]['credits_total'];

                    // Ajouter les points pour la moyenne seulement si le semestre a une moyenne
                    if (isset($moyennesSemestre[$matricule][$semId]) && $moyennesSemestre[$matricule][$semId] !== null) {
                        $semestreAvecMoyenne++;
                        $totalPointsAnnee += $moyennesSemestre[$matricule][$semId] * $validationsSemestre[$matricule][$semId]['credits_total'];
                    }
                }
            }

            if ($totalCreditsAnnee > 0) {
                // Si on est configuré pour calculer avec des notes vides ou si tous les semestres ont des moyennes
                if ($calculerAvecNotesVides || $semestreAvecMoyenne == count($semestresToShow)) {
                    $moyenneAnnuelle = $totalPointsAnnee / $totalCreditsAnnee;
                    $moyennesAnnuelles[$matricule] = $moyenneAnnuelle;

                    // Calculer le pourcentage basé sur la moyenne (sur 20)
                    $pourcentageValidationAnnee = ($moyenneAnnuelle / 20) * 100;

                    $validationsAnnuelles[$matricule] = [
                        'credits_valides' => $creditsValidesAnnee,
                        'credits_total' => $totalCreditsAnnee,
                        'pourcentage' => $pourcentageValidationAnnee,
                        'est_valide' => $moyenneAnnuelle >= 10 // Critère de validation annuelle basé sur la moyenne
                    ];
                } else {
                    // Si on n'est pas configuré pour calculer avec des notes vides et qu'il manque des moyennes de semestre
                    $moyennesAnnuelles[$matricule] = null;
                    $validationsAnnuelles[$matricule] = [
                        'credits_valides' => $creditsValidesAnnee,  // Garder les crédits validés (capitalisés)
                        'credits_total' => $totalCreditsAnnee,      // Garder le total des crédits
                        'pourcentage' => 0,
                        'est_valide' => false
                    ];
                }
            }
        }
    }

    $nomSemestre = $universite->getSemestreById($semId)['numeroSemestre'];
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Grille de Notes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Grille de notes</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- 1. SÉLECTION DES PARAMÈTRES -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-2"></i>
                            Sélection des paramètres de la grille
                        </h5>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/grille_notes">

                            <div class="col-md-3">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($bureaux as $bureau): ?>
                                        <option value="<?= $bureau['idbureau'] ?>" <?= ($bureauId == $bureau['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bureau['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($bureauId): ?>
                                <div class="col-md-3">
                                    <label for="promotion" class="form-label">Promotion</label>
                                    <select name="promotion" id="promotion" class="form-select" required onchange="this.form.submit()">
                                        <option value="">Sélectionner une promotion</option>
                                        <?php foreach ($promotions as $promotion): ?>
                                            <option value="<?= $promotion['idpromotion'] ?>" <?= ($promotionId == $promotion['idpromotion']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if ($promotionId): ?>
                                    <div class="col-md-3">
                                        <label for="semestre" class="form-label">Semestre</label>
                                        <select name="semestre" id="semestre" class="form-select" <?= $afficherDeuxSemestres ? 'disabled' : 'required' ?> onchange="toggleDeuxSemestres(false); this.form.submit();">
                                            <option value="">Sélectionner un semestre</option>
                                            <?php foreach ($semestres as $semestre): ?>
                                                <option value="<?= $semestre['idsemestre'] ?>" <?= ($semestreId == $semestre['idsemestre']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="deux_semestres" name="deux_semestres" value="1" <?= $afficherDeuxSemestres ? 'checked' : '' ?> onchange="toggleDeuxSemestres(this.checked); this.form.submit();">
                                            <label class="form-check-label" for="deux_semestres">
                                                Afficher les deux semestres
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="avec_recours" name="avec_recours" value="1" <?= $avecRecours ? 'checked' : '' ?> onchange="this.form.submit();">
                                            <label class="form-check-label" for="avec_recours">
                                                Afficher seulement les étudiants avec recours
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="session" class="form-label">Session</label>
                                        <select name="session" id="session" class="form-select" required onchange="this.form.submit()">
                                            <option value="">Sélectionner une session</option>
                                            <?php foreach ($sessions as $session): ?>
                                                <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($session['description']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="annee" class="form-label">Année Académique</label>
                                        <select name="annee" id="annee" class="form-select" required onchange="this.form.submit()">
                                            <option value="">Sélectionner une année</option>
                                            <?php foreach ($annees as $annee): ?>
                                                <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($annee['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId): ?>
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i> Afficher
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="exporterGrille()">
                                        <i class="bi bi-file-excel me-1"></i> Excel-Print
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="exporterGrilleModifiable()">
                                        <i class="bi bi-pencil-square me-1"></i> Excel-Edit
                                    </button>
                                    <!-- Ajouter ce code dans la section des boutons d'action, juste après le bouton d'exportation -->
                                    <button type="button" class="btn btn-warning" onclick="importerGrille()">
                                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Publier
                                    </button>
                                    <!-- Ajouter ce bouton dans la section des boutons d'action, après le bouton "Bulletins" -->
                                    <button type="button" class="btn btn-danger" onclick="genererPVDeliberation()">
                                        <i class="bi bi-file-text me-1"></i> PV
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="sauvegarderMoyennes()">
                                        <i class="bi bi-database-check me-1"></i> Save Moyennes
                                    </button>

                                    <button type="button" class="btn btn-secondary" onclick="afficherModalCompensations()">
                                        <i class="bi bi-shuffle me-1"></i> Comp Inter-ECUE
                                    </button>

                                    

                                    <a href="?view=deliberation/grille_notes" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Refresh
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="col-12">
                                    <a href="?view=deliberation/grille_notes" class="btn btn-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Refresh
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>

                    </div>
                </div>
            </div>





            <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId && !empty($etudiants)): ?>

                <!-- 2. GRILLE DE NOTES -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-table me-2"></i>
                                Grille de notes - <?= $afficherDeuxSemestres ? 'Année complète' : 'Semestre ' . $nomSemestre ?><?= $avecRecours ? ' - Recours' : '' ?>
                            </h5>

                            <!-- Options de filtrage et tri -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="searchEtudiants" placeholder="Rechercher un étudiant...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="filterResults">
                                        <option value="all">Tous les étudiants</option>
                                        <option value="success">Réussite (≥ 10)</option>
                                        <option value="failure">Échec (< 10)</option>
                                        <option value="missing">Notes manquantes</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser filtres
                                    </button>
                                </div>
                            </div>

                            <!-- Tableau des résultats -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="tableGrilleNotes">

                                    <thead class="table-primary">
                                        <tr>
                                            <th rowspan="3" style="vertical-align: middle;">#</th>
                                            <th rowspan="3" style="vertical-align: middle;">Nom de l'étudiant</th>

                                            <?php
                                            // Afficher les en-têtes pour chaque semestre
                                            $semestresToShow = $afficherDeuxSemestres ? $semestres : array_filter($semestres, function ($sem) use ($semestreId) {
                                                return $sem['idsemestre'] == $semestreId;
                                            });

                                            foreach ($semestresToShow as $semestre):
                                                $semId = $semestre['idsemestre'];
                                                $totalColspanSemestre = 0;

                                                // Calculer le nombre total de colonnes pour ce semestre
                                                foreach ($uesBySemestre[$semId] as $ue) {
                                                    $ueId = $ue['idUE'];
                                                    $ecueCount = count($ecuesByUE[$ueId] ?? []);
                                                    $totalColspanSemestre += $ecueCount + 2; // +2 pour la moyenne UE et la validation
                                                }

                                                // Ajouter 4 colonnes pour les résultats du semestre (moyenne, crédits, pourcentage, décision)
                                                // Seulement si c'est une grille semestrielle
                                                $totalColspanSemestre += $afficherDeuxSemestres ? 3 : 4;
                                            ?>
                                                <!-- Ajout d'une ligne pour l'intitulé du semestre -->
                                                <th colspan="<?= $totalColspanSemestre ?>" class="text-center bg-primary text-white"> SEMESTRE
                                                    <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                                </th>
                                            <?php endforeach; ?>

                                            <?php if ($afficherDeuxSemestres): ?>
                                                <!-- Colonnes pour les résultats annuels -->
                                                <th colspan="4" class="text-center bg-success text-white">Résultats Annuels</th>
                                            <?php endif; ?>
                                            <!-- Add this new header for the actions column -->
                                            <th rowspan="3" style="vertical-align: middle;">Actions</th>
                                        </tr>
                                        <!-- Reste de l'en-tête inchangé -->

                                        <tr>
                                            <?php foreach ($semestresToShow as $semestre):
                                                $semId = $semestre['idsemestre'];

                                                // Pour chaque UE du semestre, créer un groupe de colonnes
                                                foreach ($uesBySemestre[$semId] as $ue):
                                                    $ueId = $ue['idUE'];
                                                    $ecueList = $ecuesByUE[$ueId] ?? [];
                                                    $ecueCount = count($ecueList);

                                                    // Ajouter 2 colonnes pour la moyenne UE et la validation
                                                    $totalColspan = $ecueCount + 2;
                                            ?>
                                                    <th colspan="<?= $totalColspan ?>" class="text-center course-group">
                                                        <?= $ue['codeUE'] ?? 'UE' . $ueId ?> - <?= htmlspecialchars($ue['designationUE']) ?>
                                                    </th>
                                                <?php endforeach; ?>

                                                <!-- Colonnes pour les résultats du semestre -->
                                                <!-- En-têtes pour les résultats du semestre -->
                                                <th colspan="<?= $afficherDeuxSemestres ? 3 : 4 ?>" class="text-center bg-info text-white">
                                                    Résultats S<?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                                </th>

                                            <?php endforeach; ?>

                                            <?php if ($afficherDeuxSemestres): ?>
                                                <!-- En-têtes pour les résultats annuels restent inchangés dans cette ligne -->
                                                <th colspan="4" class="text-center bg-success text-white">Résultats Annuels</th>
                                            <?php endif; ?>
                                        </tr>
                                        <tr>
                                            <?php foreach ($semestresToShow as $semestre):
                                                $semId = $semestre['idsemestre'];

                                                // En-têtes pour chaque UE et ses ECUE
                                                foreach ($uesBySemestre[$semId] as $ue):
                                                    $ueId = $ue['idUE'];
                                                    $ecueList = $ecuesByUE[$ueId] ?? [];

                                                    // En-têtes pour chaque ECUE avec texte vertical
                                                    foreach ($ecueList as $ecueItem):
                                            ?>
                                                        <th class="subject-header" title="<?= htmlspecialchars($ecueItem['designationECUE']) ?>">
                                                            <?= htmlspecialchars(substr($ecueItem['designationECUE'], 0, 20)) ?>
                                                        </th>
                                                    <?php endforeach; ?>

                                                    <!-- En-têtes pour la moyenne et validation de l'UE -->
                                                    <th class="subject-header bg-light">Moy. UE</th>
                                                    <th class="subject-header bg-light">Valid.</th>
                                                <?php endforeach; ?>

                                                <!-- En-têtes pour les résultats du semestre -->
                                                <th class="subject-header bg-info text-white">Moy. Sem.</th>
                                                <th class="subject-header bg-info text-white">Crédits</th>
                                                <th class="subject-header bg-info text-white">%</th>
                                                <?php if (!$afficherDeuxSemestres): ?>
                                                    <th class="subject-header bg-info text-white">Décision</th>
                                                <?php endif; ?>

                                            <?php endforeach; ?>

                                            <?php if ($afficherDeuxSemestres): ?>
                                                <!-- En-têtes pour les résultats annuels -->
                                                <th class="subject-header bg-success text-white">Moy. Ann.</th>
                                                <th class="subject-header bg-success text-white">Crédits</th>
                                                <th class="subject-header bg-success text-white">%</th>
                                                <th class="subject-header bg-success text-white">Décision</th>
                                            <?php endif; ?>
                                        </tr>
                                        <!-- Dans la section qui génère l'en-tête du tableau avec les crédits -->
                                        <tr>
                                            <th colspan="2" class="text-center bg-light">Crédits</th>

                                            <?php foreach ($semestresToShow as $semestre):
                                                $semId = $semestre['idsemestre'];

                                                // Ligne pour les crédits de chaque ECUE
                                                foreach ($uesBySemestre[$semId] as $ue):
                                                    $ueId = $ue['idUE'];
                                                    $ecueList = $ecuesByUE[$ueId] ?? [];

                                                    foreach ($ecueList as $ecueItem):
                                                        $credits = number_format(($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $creditHeure, 1);
                                            ?>
                                                        <th class="text-center small bg-light"><?= $credits ?></th>
                                                    <?php endforeach; ?>

                                                    <!-- Cellules pour la moyenne UE et validation -->
                                                    <?php
                                                    // Calculer la somme des crédits des ECUE pour cette UE
                                                    $totalCreditsUE = 0;
                                                    foreach ($ecueList as $ecueItem) {
                                                        $totalCreditsUE += ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $creditHeure;
                                                    }
                                                    $totalCreditsUE = number_format($totalCreditsUE, 1);
                                                    ?>
                                                    <th class="text-center small bg-light"><?= $totalCreditsUE ?></th>
                                                    <th class="text-center small bg-light">-</th>

                                                <?php endforeach; ?>

                                                <!-- Cellules pour les résultats du semestre -->
                                                <th class="text-center small bg-info text-white">-</th>
                                                <th class="text-center small bg-info text-white">-</th>
                                                <th class="text-center small bg-info text-white">-</th>
                                                <?php if (!$afficherDeuxSemestres): ?>
                                                    <th class="text-center small bg-info text-white">-</th>
                                                <?php endif; ?>
                                            <?php endforeach; ?>

                                            <?php if ($afficherDeuxSemestres): ?>
                                                <!-- Cellules pour les résultats annuels -->
                                                <th class="text-center small bg-success text-white">-</th>
                                                <th class="text-center small bg-success text-white">-</th>
                                                <th class="text-center small bg-success text-white">-</th>
                                                <th class="text-center small bg-success text-white">-</th>
                                            <?php endif; ?>
                                            <th class="text-center small bg-light">-</th>
                                        </tr>

                                    </thead>

                                    <tbody>
                                        <?php foreach ($etudiants as $index => $etudiant):
                                            $matricule = $etudiant['matricule'];
                                        ?>

                                            <!-- Dans la boucle qui génère les lignes du tableau -->
                                            <tr data-matricule="<?= $matricule ?>">
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>

                                                <?php foreach ($semestresToShow as $semestre):
                                                    $semId = $semestre['idsemestre'];

                                                    // Compter les UE validées pour déterminer la décision semestrielle
                                                    $ueValidees = 0;
                                                    $totalUE = 0;

                                                    // Afficher les notes pour chaque UE et ses ECUE
                                                    foreach ($uesBySemestre[$semId] as $ue):
                                                        $ueId = $ue['idUE'];
                                                        $ecueList = $ecuesByUE[$ueId] ?? [];
                                                        $totalUE++;

                                                        // Afficher les notes pour chaque ECUE
                                                        foreach ($ecueList as $ecueItem):
                                                            $ecueId = $ecueItem['idECUE'];
                                                            $note = isset($notesByEtudiantEcue[$matricule][$ecueId]) ? $notesByEtudiantEcue[$matricule][$ecueId]['MF'] : null;
                                                            $noteClass = ($note !== null && $note < 10) ? 'text-danger' : '';
                                                ?>
                                                            <td class="text-center <?= $noteClass ?>"><?= $note !== null ? number_format($note, 2) : '-' ?></td>
                                                        <?php endforeach; ?>

                                                        <!-- Afficher la moyenne et validation de l'UE -->
                                                        <?php
                                                        $moyenneUE = isset($moyennesUE[$matricule][$ueId]) ? $moyennesUE[$matricule][$ueId] : null;
                                                        $estValidee = isset($validationsUE[$matricule][$ueId]) ? $validationsUE[$matricule][$ueId] : false;
                                                        if ($estValidee) $ueValidees++;
                                                        $moyenneClass = ($moyenneUE !== null && $moyenneUE < 10) ? 'text-danger' : '';
                                                        $validationClass = $estValidee ? 'text-success' : 'text-danger';
                                                        ?>
                                                        <td class="text-center bg-light <?= $moyenneClass ?>"><?= $moyenneUE !== null ? number_format($moyenneUE, 2) : '-' ?></td>
                                                        <td class="text-center bg-light <?= $validationClass ?>"><?= $estValidee ? 'V' : 'NV' ?></td>
                                                    <?php endforeach; ?>

                                                    <!-- Afficher les résultats du semestre -->
                                                    <?php
                                                    $moyenneSemestre = isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : null;
                                                    $validationSemestre = isset($validationsSemestre[$matricule][$semId]) ? $validationsSemestre[$matricule][$semId] : null;
                                                    $moyenneClass = ($moyenneSemestre !== null && $moyenneSemestre < 10) ? 'text-danger' : '';
                                                    $validationClass = ($validationSemestre && $validationSemestre['est_valide']) ? 'text-success' : 'text-danger';

                                                    // Déterminer la décision semestrielle
                                                    $decisionSemestre = 'NV';
                                                    if ($moyenneSemestre === null) {
                                                        $decisionSemestre = '-';
                                                        $decisionClass = '';
                                                    } else if ($ueValidees == $totalUE && $totalUE > 0) {
                                                        $decisionSemestre = 'V';
                                                        $decisionClass = 'text-success';
                                                    } else if ($ueValidees > 0) {
                                                        $decisionSemestre = 'VP';
                                                        $decisionClass = 'text-warning';
                                                    } else {
                                                        $decisionClass = 'text-danger';
                                                    }
                                                    ?>
                                                    <td class="text-center bg-info text-white <?= $moyenneClass ?>"><?= $moyenneSemestre !== null ? number_format($moyenneSemestre, 2) : '-' ?></td>
                                                    <td class="text-center bg-info text-white">
                                                        <?= ($validationSemestre) ? $validationSemestre['credits_valides'] . '/' . $validationSemestre['credits_total'] : '-' ?>
                                                    </td>
                                                    <td class="text-center bg-info text-white <?= $validationClass ?>">
                                                        <?= ($validationSemestre && $moyenneSemestre !== null) ? number_format($validationSemestre['pourcentage'], 1) . '%' : '-' ?>
                                                    </td>
                                                    <?php if (!$afficherDeuxSemestres): ?>
                                                        <td class="text-center bg-info text-white <?= $decisionClass ?>">
                                                            <strong><?= $decisionSemestre ?></strong>
                                                        </td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>

                                                <?php if ($afficherDeuxSemestres): ?>
                                                    <!-- Afficher les résultats annuels -->
                                                    <?php
                                                    $moyenneAnnuelle = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
                                                    $validationAnnuelle = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule] : null;
                                                    $moyenneClass = ($moyenneAnnuelle !== null && $moyenneAnnuelle < 10) ? 'text-danger' : '';
                                                    $validationClass = ($validationAnnuelle && $validationAnnuelle['est_valide']) ? 'text-success' : 'text-danger';
                                                    $decision = '-';
                                                    $decisionClass = '';

                                                    // Vérifier si des notes sont manquantes
                                                    $hasIncompleteNotes = false;
                                                    foreach ($semestresToShow as $sem) {
                                                        $semId = $sem['idsemestre'];
                                                        if (
                                                            isset($validationsSemestre[$matricule][$semId]) &&
                                                            isset($validationsSemestre[$matricule][$semId]['notes_manquantes']) &&
                                                            $validationsSemestre[$matricule][$semId]['notes_manquantes']
                                                        ) {
                                                            $hasIncompleteNotes = true;
                                                            break;
                                                        }
                                                    }

                                                    if ($hasIncompleteNotes) {
                                                        $decision = 'INCOMPLET';
                                                        $decisionClass = 'text-danger';
                                                    } else if ($moyenneAnnuelle !== null) {
                                                        // Calculer le pourcentage de crédits validés
                                                        $creditsValidesPercent = 0;
                                                        if ($validationAnnuelle && $validationAnnuelle['credits_total'] > 0) {
                                                            $creditsValidesPercent = ($validationAnnuelle['credits_valides'] / $validationAnnuelle['credits_total']) * 100;
                                                        }

                                                        // Logique différente selon la session
                                                        if ($isDeuxiemeSession) {
                                                            // En deuxième session
                                                            if ($validationAnnuelle && $validationAnnuelle['credits_valides'] == $validationAnnuelle['credits_total']) {
                                                                $decision = 'ADMIS SANS RACHAT';
                                                                $decisionClass = 'text-success';
                                                            } else {
                                                                // Vérifier si l'étudiant est en classe terminale
                                                                $estClasseTerminale = false;
                                                                foreach ($promotions as $promo) {
                                                                    if ($promo['idpromotion'] == $promotionId && $promo['est_terminale'] == 1) {
                                                                        $estClasseTerminale = true;
                                                                        break;
                                                                    }
                                                                }

                                                                // Vérifier les dettes si c'est une classe terminale
                                                                $aDesDettesPassees = false;
                                                                if ($estClasseTerminale) {
                                                                    $aDesDettesPassees = $deliberation->etudiantADesDettes($matricule, $anneeId, $promotionId);
                                                                }

                                                                if ($estClasseTerminale && ($aDesDettesPassees || $creditsValidesPercent < 100)) {
                                                                    // En classe terminale, aucun rachat possible et toutes les dettes doivent être validées
                                                                    $decision = 'AJOURNÉ';
                                                                    $decisionClass = 'text-danger';
                                                                } else if (!$estClasseTerminale && $creditsValidesPercent >= 75 && $moyenneAnnuelle >= 10) {
                                                                    // Pas en classe terminale, rachat possible avec 75% des crédits
                                                                    $decision = 'ADMIS AVEC RACHAT';
                                                                    $decisionClass = 'text-success';
                                                                } else {
                                                                    $decision = 'AJOURNÉ';
                                                                    $decisionClass = 'text-danger';
                                                                }
                                                            }
                                                        } else {
                                                            // En première session
                                                            if ($validationAnnuelle && $validationAnnuelle['credits_valides'] == $validationAnnuelle['credits_total']) {
                                                                $decision = 'ADMIS SANS RACHAT';
                                                                $decisionClass = 'text-success';
                                                            } else {
                                                                $decision = 'ADMIS AU RATTRAPAGE';
                                                                $decisionClass = 'text-warning';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <td class="text-center bg-success text-white <?= $moyenneClass ?>"><?= $moyenneAnnuelle !== null ? number_format($moyenneAnnuelle, 2) : '-' ?></td>
                                                    <td class="text-center bg-success text-white">
                                                        <?= ($validationAnnuelle) ? $validationAnnuelle['credits_valides'] . '/' . $validationAnnuelle['credits_total'] : '-' ?>
                                                    </td>
                                                    <td class="text-center bg-success text-white <?= $validationClass ?>">
                                                        <?= ($validationAnnuelle && $moyenneAnnuelle !== null) ? number_format($validationAnnuelle['pourcentage'], 1) . '%' : '-' ?>
                                                    </td>
                                                    <td class="text-center bg-success text-white <?= $decisionClass ?>">
                                                        <strong><?= $decision ?></strong>
                                                    </td>
                                                <?php endif; ?>


                                                <!-- Ajouter le bouton d'export individuel -->
                                                <td class="text-center">
                                                    <a href="controller/export_bulletin_individuel.php?matricule=<?= $matricule ?>&bureau=<?= $bureauId ?>&promotion=<?= $promotionId ?>&semestre=<?= $semestreId ?>&deux_semestres=<?= $afficherDeuxSemestres ? '1' : '0' ?>&session=<?= $sessionId ?>&annee=<?= $anneeId ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Exporter le bulletin individuel"
                                                        target="_blank">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                </td>


                                            </tr>



                                        <?php endforeach; ?>
                                    </tbody>



                                </table>
                            </div>

                            <!-- Légende -->
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Légende</h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <span class="badge bg-success">V</span> UE Validée (moyenne ≥ 10)
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-danger">NV</span> UE Non Validée (moyenne < 10)
                                                        </div>
                                                        <div class="col-md-3">
                                                            <span class="text-danger">Note en rouge</span> Note < 10
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <span class="badge bg-info">%</span> Pourcentage de la note maximale
                                                                </div>
                                                        </div>
                                                        <?php if (!$afficherDeuxSemestres): ?>
                                                            <div class="row mt-2">
                                                                <div class="col-md-4">
                                                                    <span class="badge bg-success">V</span> Semestre Validé (toutes les UE validées)
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <span class="badge bg-warning text-dark">VP</span> Semestre Validé Partiellement (au moins une UE validée)
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <span class="badge bg-danger">NV</span> Semestre Non Validé (aucune UE validée)
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Ajouter une explication sur le calcul des moyennes -->
                                                        <div class="row mt-2">
                                                            <div class="col-md-12">
                                                                <div class="alert alert-info">
                                                                    <i class="bi bi-info-circle me-2"></i>
                                                                    <strong>Calcul des moyennes:</strong>
                                                                    <?php if ($calculerAvecNotesVides): ?>
                                                                        Les moyennes sont calculées même si certaines notes sont manquantes.
                                                                    <?php else: ?>
                                                                        Les moyennes ne sont calculées que si toutes les notes requises sont présentes
                                                                        (CC et EX en première session, EX en deuxième session).
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>

                        <!-- 3. STATISTIQUES -->
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-bar-chart-line me-2"></i>
                                        Statistiques
                                    </h5>

                                    <div class="row">
                                        <!-- Statistiques par UE -->
                                        <div class="col-md-6">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-primary text-white">
                                                    <h6 class="card-title mb-0">Taux de réussite par UE</h6>
                                                </div>
                                                <div class="card-body">
                                                    <canvas id="chartReussiteUE" style="height: 250px;"></canvas>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Statistiques globales -->
                                        <div class="col-md-6">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-success text-white">
                                                    <h6 class="card-title mb-0">Statistiques globales</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <?php
                                                        // Récupérer les statistiques globales via le modèle Deliberation
                                                        $statsGlobales = $afficherDeuxSemestres
                                                            ? $deliberation->getStatistiquesGlobales($promotionId, $sessionId, $anneeId)
                                                            : $deliberation->getStatistiquesGlobales($promotionId, $sessionId, $anneeId, $semestreId);

                                                        $totalEtudiants = $statsGlobales['total_etudiants'];
                                                        $etudiantsAdmis = $statsGlobales['etudiants_admis'];
                                                        $etudiantsAjournes = $statsGlobales['etudiants_ajournes'];
                                                        $pourcentageReussite = $statsGlobales['taux_reussite'];
                                                        ?>

                                                        <div class="col-md-6 mb-3">
                                                            <div class="card bg-light">
                                                                <div class="card-body text-center">
                                                                    <h2 class="card-title"><?= $totalEtudiants ?></h2>
                                                                    <p class="card-text">Total des étudiants</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6 mb-3">
                                                            <div class="card bg-success text-white">
                                                                <div class="card-body text-center">
                                                                    <h2 class="card-title"><?= $etudiantsAdmis ?></h2>
                                                                    <p class="card-text">Étudiants admis</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6 mb-3">
                                                            <div class="card bg-danger text-white">
                                                                <div class="card-body text-center">
                                                                    <h2 class="card-title"><?= $etudiantsAjournes ?></h2>
                                                                    <p class="card-text">Étudiants ajournés</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6 mb-3">
                                                            <div class="card bg-info text-white">
                                                                <div class="card-body text-center">
                                                                    <h2 class="card-title"><?= number_format($pourcentageReussite, 1) ?>%</h2>
                                                                    <p class="card-text">Taux de réussite</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>

                        <!-- Message si aucune donnée n'est disponible -->
                        <?php if ($bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId): ?>
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucune donnée disponible pour les paramètres sélectionnés. Veuillez vérifier que des notes ont été enregistrées pour cette promotion, ce semestre, cette session et cette année académique.
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                    </div>
    </section>
</main>


<div class="modal fade" id="importGrilleModal" tabindex="-1" aria-labelledby="importGrilleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importGrilleModalLabel">Importer une grille modifiée</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/import_grille_modifiable.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Importez uniquement des fichiers Excel générés par la fonction "Exporter grille modifiable". Ne modifiez pas la structure du fichier.
                    </div>
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Fichier Excel</label>
                        <input type="file" class="form-control" id="excelFile" name="excelFile" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <h5 id="loadingModalLabel">Génération du fichier Excel en cours...</h5>
                <p class="text-muted">Veuillez patienter pendant la préparation de votre fichier.</p>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour la génération des bulletins -->
<div class="modal fade" id="bulletinsModal" tabindex="-1" aria-labelledby="bulletinsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulletinsModalLabel">Générer les bulletins individuels</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulletinsForm" action="controller/export_bulletins.php" method="POST">
                <div class="modal-body">
                    <!-- Paramètres cachés pour transmettre les informations de la grille -->
                    <input type="hidden" name="bureau" value="<?= $bureauId ?>">
                    <input type="hidden" name="promotion" value="<?= $promotionId ?>">
                    <input type="hidden" name="semestre" value="<?= $semestreId ?>">
                    <input type="hidden" name="deux_semestres" value="<?= $afficherDeuxSemestres ? '1' : '0' ?>">
                    <input type="hidden" name="session" value="<?= $sessionId ?>">
                    <input type="hidden" name="annee" value="<?= $anneeId ?>">

                    <div class="mb-3">
                        <label class="form-label">Format de sortie</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="formatExcel" value="excel" checked>
                            <label class="form-check-label" for="formatExcel">
                                <i class="bi bi-file-excel me-1"></i> Excel (un fichier par étudiant)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="formatPDF" value="pdf">
                            <label class="form-check-label" for="formatPDF">
                                <i class="bi bi-file-pdf me-1"></i> PDF (un fichier par étudiant)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="formatZip" value="zip">
                            <label class="form-check-label" for="formatZip">
                                <i class="bi bi-file-zip me-1"></i> Archive ZIP (tous les bulletins)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="inclure_logo" id="incluireLogo" value="1" checked>
                            <label class="form-check-label" for="incluireLogo">
                                Inclure le logo de l'établissement
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="inclure_signature" id="incluireSignature" value="1" checked>
                            <label class="form-check-label" for="incluireSignature">
                                Inclure la signature du président du jury
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="inclure_statistiques" id="incluireStats" value="1" checked>
                            <label class="form-check-label" for="incluireStats">
                                Inclure les statistiques de la promotion
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="etudiants" class="form-label">Étudiants</label>
                        <select class="form-select" id="etudiants" name="etudiants[]" multiple size="5">
                            <option value="tous" selected>Tous les étudiants</option>
                            <?php foreach ($etudiants as $etudiant): ?>
                                <option value="<?= $etudiant['matricule'] ?>">
                                    <?= htmlspecialchars($etudiant['noms']) ?> (<?= $etudiant['matricule'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Maintenez la touche Ctrl pour sélectionner plusieurs étudiants.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Générer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Données JSON pour les compensations -->
<script>
    const notesData = <?= json_encode([
                            'notesByEtudiantEcue' => $notesByEtudiantEcue,
                            'moyennesUE' => $moyennesUE,
                            'validationsUE' => $validationsUE,
                            'ecuesByUE' => $ecuesByUE,
                            'etudiants' => $etudiants,
                            'creditHeure' => $creditHeure,
                            'semestresToShow' => array_map(function ($sem) {
                                return ['idsemestre' => $sem['idsemestre'], 'numeroSemestre' => $sem['numeroSemestre']];
                            }, (isset($semestresToShow) ? $semestresToShow : []))
                        ]) ?>;

    const uesByUE = <?= json_encode(array_map(function ($ue) {
                        return ['idUE' => $ue['idUE'], 'codeUE' => $ue['codeUE'], 'designationUE' => $ue['designationUE']];
                    }, call_user_func(function () {
                        global $uesBySemestre;
                        $allUEs = [];
                        foreach ($uesBySemestre as $semUEs) {
                            foreach ($semUEs as $ue) {
                                $allUEs[$ue['idUE']] = $ue;
                            }
                        }
                        return array_values($allUEs);
                    }))) ?>;
</script>

<!-- À la fin du fichier, dans la section script -->
<script>
    function importerGrille() {
        // Afficher le modal d'importation
        var importModal = new bootstrap.Modal(document.getElementById('importGrilleModal'));
        importModal.show();
    }


    // Fonction pour basculer entre l'affichage d'un semestre ou des deux semestres
    function toggleDeuxSemestres(checked) {
        const semestreSelect = document.getElementById('semestre');
        if (checked) {
            semestreSelect.disabled = true;
            semestreSelect.value = '';
        } else {
            semestreSelect.disabled = false;
        }
    }

    // Attendre que le DOM soit complètement chargé
    document.addEventListener('DOMContentLoaded', function() {

        /*
        // Vérifier si la grille contient des données à sauvegarder
        if (<?= $bureauId && $promotionId && ($semestreId || $afficherDeuxSemestres) && $sessionId && $anneeId && !empty($etudiants) ? 'true' : 'false' ?>) {
            // Sauvegarder automatiquement les moyennes au chargement de la page
            setTimeout(() => {
                sauvegarderMoyennes();
            }, 8000); // Délai de 2 secondes pour s'assurer que la page est entièrement chargée
        }

        */


        // Fonction pour rechercher des étudiants dans la grille
        const searchInput = document.getElementById('searchEtudiants');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchText = this.value.toLowerCase();
                const rows = document.querySelectorAll('#tableGrilleNotes tbody tr');

                rows.forEach(row => {
                    const matricule = row.getAttribute('data-matricule');
                    const nom = row.cells[1].textContent.toLowerCase();

                    if (matricule.includes(searchText) || nom.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Fonction pour filtrer les résultats
        const filterSelect = document.getElementById('filterResults');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                console.log('Filtre changé:', this.value); // Pour le débogage
                const filterValue = this.value;
                const rows = document.querySelectorAll('#tableGrilleNotes tbody tr');

                rows.forEach(row => {
                    // Récupérer la moyenne (semestre ou annuelle selon le mode d'affichage)
                    let moyenne = null;
                    let isAfficherDeuxSemestres = <?= $afficherDeuxSemestres ? 'true' : 'false' ?>;
                    let hasMissingNotes = false;

                    if (isAfficherDeuxSemestres) {
                        // Récupérer la moyenne annuelle
                        const moyenneCell = row.cells[row.cells.length - 4];
                        if (moyenneCell && moyenneCell.textContent.trim() !== '-') {
                            moyenne = parseFloat(moyenneCell.textContent);
                        }
                    } else {
                        // Récupérer la moyenne du semestre
                        const moyenneCell = row.cells[row.cells.length - 4];
                        if (moyenneCell && moyenneCell.textContent.trim() !== '-') {
                            moyenne = parseFloat(moyenneCell.textContent);
                        }
                    }

                    // Vérifier s'il y a des notes manquantes
                    for (let i = 2; i < row.cells.length - 4; i++) {
                        if (row.cells[i].textContent.trim() === '-') {
                            hasMissingNotes = true;
                            break;
                        }
                    }

                    switch (filterValue) {
                        case 'success':
                            row.style.display = (!isNaN(moyenne) && moyenne >= 10) ? '' : 'none';
                            break;
                        case 'failure':
                            row.style.display = (!isNaN(moyenne) && moyenne < 10) ? '' : 'none';
                            break;
                        case 'missing':
                            row.style.display = hasMissingNotes ? '' : 'none';
                            break;
                        default: // 'all'
                            row.style.display = '';
                            break;
                    }
                });
            });
        }

        // Fonction pour réinitialiser les filtres
        const resetButton = document.getElementById('resetFilters');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                // Réinitialiser la recherche
                const searchInput = document.getElementById('searchEtudiants');
                if (searchInput) {
                    searchInput.value = '';
                }

                // Réinitialiser le filtre de résultats
                const filterSelect = document.getElementById('filterResults');
                if (filterSelect) {
                    filterSelect.value = 'all';
                }

                // Réafficher tous les étudiants
                const rows = document.querySelectorAll('#tableGrilleNotes tbody tr');
                rows.forEach(row => {
                    row.style.display = '';
                });
            });
        }

        // Initialisation des graphiques si des données sont disponibles
        const chartReussiteUE = document.getElementById('chartReussiteUE');

        if (chartReussiteUE) {
            // Préparer les données pour le graphique de réussite par UE
            const ueLabels = [];
            const ueData = [];

            <?php
            // Récupérer les statistiques par UE via le modèle Deliberation
            $ueStats = $deliberation->getStatistiquesParUE($promotionId, $sessionId, $anneeId, $semestresToShow);
            ?>

            <?php foreach ($ueStats as $stat): ?>
                ueLabels.push('<?= addslashes($stat['label']) ?>');
                ueData.push(<?= $stat['taux'] ?>);
            <?php endforeach; ?>

            // Créer le graphique
            new Chart(chartReussiteUE, {
                type: 'bar',
                data: {
                    labels: ueLabels,
                    datasets: [{
                        label: 'Taux de réussite (%)',
                        data: ueData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
                                    return context.raw.toFixed(1) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    // Remplacer les fonctions existantes par ces versions mises à jour
    function exporterGrille() {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        // Déclencher le téléchargement
        window.open('controller/export_grille_notes.php?' + new URLSearchParams(window.location.search).toString());

        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    function exporterGrilleModifiable() {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();

        // Déclencher le téléchargement
        window.open('controller/export_grille_modifiable.php?' + new URLSearchParams(window.location.search).toString());

        // Recharger la page après un court délai
        setTimeout(() => {
            location.reload();
        }, 1000);
    }





    function genererBulletins() {
        // Afficher le modal existant pour la génération des bulletins
        const bulletinsModal = new bootstrap.Modal(document.getElementById('bulletinsModal'));
        bulletinsModal.show();

        // Modifier le comportement du formulaire pour gérer les deux cas (individuel et groupe)
        const bulletinsForm = document.getElementById('bulletinsForm');
        if (bulletinsForm) {
            bulletinsForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Récupérer les valeurs du formulaire
                const formData = new FormData(this);
                const format = document.querySelector('input[name="format"]:checked').value;
                const etudiantsSelect = document.getElementById('etudiants');
                const selectedOptions = Array.from(etudiantsSelect.selectedOptions);

                // Vérifier si "Tous les étudiants" est sélectionné
                const allStudentsSelected = selectedOptions.some(option => option.value === 'tous');

                // Si tous les étudiants sont sélectionnés et format PDF, utiliser export_bulletins_groupe.php
                if (allStudentsSelected && format === 'pdf') {
                    // Fermer le modal
                    bulletinsModal.hide();

                    // Afficher le modal de chargement
                    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                    document.getElementById('loadingModalLabel').textContent = 'Génération des bulletins en cours...';
                    loadingModal.show();

                    // Paramètres de l'URL
                    const params = new URLSearchParams();
                    params.append('bureau', <?= $bureauId ?>);
                    params.append('promotion', <?= $promotionId ?>);
                    params.append('semestre', <?= $semestreId ?>);
                    params.append('deux_semestres', <?= $afficherDeuxSemestres ? '1' : '0' ?>);
                    params.append('session', <?= $sessionId ?>);
                    params.append('annee', <?= $anneeId ?>);

                    // Ouvrir le script d'exportation dans un nouvel onglet
                    window.open('controller/export_bulletins_groupe.php?' + params.toString(), '_blank');

                    // Fermer le modal après un délai et recharger la page
                    setTimeout(() => {
                        loadingModal.hide();
                        // Recharger la page après un court délai
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    }, 3000);
                }
                // Si un seul étudiant est sélectionné et format PDF, utiliser export_bulletin_individuel.php
                else if (!allStudentsSelected && selectedOptions.length === 1 && format === 'pdf') {
                    // Fermer le modal
                    bulletinsModal.hide();

                    // Récupérer le matricule de l'étudiant sélectionné
                    const matricule = selectedOptions[0].value;

                    // Ouvrir le bulletin dans un nouvel onglet
                    const params = new URLSearchParams();
                    params.append('matricule', matricule);
                    params.append('bureau', <?= $bureauId ?>);
                    params.append('promotion', <?= $promotionId ?>);
                    params.append('semestre', <?= $semestreId ?>);
                    params.append('deux_semestres', <?= $afficherDeuxSemestres ? '1' : '0' ?>);
                    params.append('session', <?= $sessionId ?>);
                    params.append('annee', <?= $anneeId ?>);

                    window.open('controller/export_bulletin_individuel.php?' + params.toString(), '_blank');

                    // Recharger la page après un court délai
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
                // Pour les autres cas (Excel, ZIP, etc.), utiliser le comportement existant
                else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Format non disponible pour le moment. Veuillez choisir un format valide.'
                    });
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            }, {
                once: true
            }); // Utiliser { once: true } pour éviter les soumissions multiples
        }
    }


    function genererPVDeliberation() {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.getElementById('loadingModalLabel').textContent = 'Génération du PV de délibération en cours...';
        loadingModal.show();

        // Paramètres de l'URL
        const params = new URLSearchParams();
        params.append('bureau', <?= $bureauId ?>);
        params.append('promotion', <?= $promotionId ?>);
        params.append('semestre', <?= $semestreId ?>);
        params.append('deux_semestres', <?= $afficherDeuxSemestres ? '1' : '0' ?>);
        params.append('session', <?= $sessionId ?>);
        params.append('annee', <?= $anneeId ?>);

        // Ouvrir le script d'exportation dans un nouvel onglet
        window.open('controller/export_pv_deliberation.php?' + params.toString(), '_blank');

        // Fermer le modal après un délai
        setTimeout(() => {
            loadingModal.hide();
        }, 2000);
    }














    // Fonction pour calculer les compensations pour un étudiant
    function calculerCompensations(matricule) {
        const compensations = [];

        for (const [ueId, ecues] of Object.entries(notesData.ecuesByUE)) {
            const ueKey = parseInt(ueId);
            const moyenneUE = notesData.moyennesUE[matricule]?.[ueKey];
            const estValideUE = notesData.validationsUE[matricule]?.[ueKey];

            if (moyenneUE !== undefined && moyenneUE !== null && moyenneUE >= 10 && estValideUE) {
                let ecuesEnEchec = [];
                let totalCoeff = 0;
                let totalPoints = 0;

                ecues.forEach(ecue => {
                    const noteObj = notesData.notesByEtudiantEcue[matricule]?.[ecue.idECUE];
                    const note = noteObj?.MF;
                    const coeff = (ecue.CMI + ecue.TD + ecue.TP) / notesData.creditHeure;

                    if (note !== undefined && note !== null) {
                        totalCoeff += coeff;
                        totalPoints += note * coeff;

                        if (note < 10) {
                            ecuesEnEchec.push({
                                ecueId: ecue.idECUE,
                                designationECUE: ecue.designationECUE,
                                note: parseFloat(note),
                                coeff: coeff
                            });
                        }
                    }
                });

                ecuesEnEchec.forEach(ecue => {
                    const noteRequise = ((moyenneUE * totalCoeff) - totalPoints + ecue.note * ecue.coeff) / ecue.coeff;
                    const noteCalculee = Math.min(20, noteRequise);
                    const noteMin = Math.max(10, Math.ceil(noteCalculee * 100) / 100);

                    compensations.push({
                        matricule: matricule,
                        ueId: ueKey,
                        ecueId: ecue.ecueId,
                        ecueDesignation: ecue.designationECUE,
                        noteActuelle: ecue.note,
                        noteRequise: noteMin,
                        ueDesignation: uesByUE.find(u => u.idUE == ueKey)?.designationUE || `UE ${ueKey}`,
                        estApplicable: noteMin <= 20
                    });
                });
            }
        }

        return compensations;
    }

    // Fonction pour afficher le modal de compensations
    function afficherModalCompensations() {
        // Calculer les compensations pour TOUS les étudiants
        let tousLesCompensations = [];
        notesData.etudiants.forEach(etudiant => {
            const compensations = calculerCompensations(etudiant.matricule);
            tousLesCompensations = tousLesCompensations.concat(compensations);
        });

        if (tousLesCompensations.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Aucune compensation',
                text: 'Aucune compensation inter-ECUE n\'est disponible pour cette grille. Les compensations sont possibles seulement si un étudiant a une UE validée (moyenne ≥ 10) avec au moins un ECUE en échec (note < 10).'
            });
            return;
        }

        // Créer le tableau HTML
        let html = `<div class="table-responsive">
            <table class="table table-sm table-bordered" id="tableCompensations">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">
                            <input type="checkbox" id="selectAllCompensations" title="Sélectionner tout"> 
                        </th>
                        <th>Étudiant</th>
                        <th>UE</th>
                        <th>ECUE en échec</th>
                        <th>Note actuelle</th>
                        <th>Note requise</th>
                    </tr>
                </thead>
                <tbody>`;

        tousLesCompensations.forEach((comp, idx) => {
            const etudiant = notesData.etudiants.find(e => e.matricule === comp.matricule);
            const statusIcon = comp.estApplicable ? '✓' : '✗';
            const rowClass = comp.estApplicable ? '' : 'table-danger';

            html += `<tr class="${rowClass}">
                <td>
                    <input type="checkbox" class="compensation-check" value="${idx}" 
                        ${comp.estApplicable ? '' : 'disabled'}>
                </td>
                <td><strong>${etudiant?.noms || comp.matricule}</strong></td>
                <td>${comp.ueDesignation}</td>
                <td>${comp.ecueDesignation}</td>
                <td class="text-danger"><strong>${comp.noteActuelle.toFixed(2)}</strong></td>
                <td class="text-success"><strong>${comp.noteRequise.toFixed(2)}</strong></td>
            </tr>`;
        });

        html += `</tbody></table></div>`;
        html += `<div class="alert alert-info mt-3">
            <small>
                <strong>Mode d'emploi:</strong> Cochez les lignes pour lesquelles vous souhaitez appliquer la compensation. 
                Les notes requises (colonne verte) seront automatiquement appliquées à la grille.
            </small>
        </div>`;

        Swal.fire({
            title: 'Compensations Inter-ECUE',
            html: html,
            icon: 'info',
            width: '1200px',
            confirmButtonText: 'Appliquer les compensations',
            cancelButtonText: 'Annuler',
            showCancelButton: true,
            didOpen: (modal) => {
                modal.querySelector('.swal2-html-container').style.textAlign = 'left';

                // Gestion du checkbox "Sélectionner tout"
                const selectAllCheckbox = document.getElementById('selectAllCompensations');
                const checkboxes = document.querySelectorAll('.compensation-check');

                selectAllCheckbox.addEventListener('change', () => {
                    checkboxes.forEach(checkbox => {
                        if (!checkbox.disabled) {
                            checkbox.checked = selectAllCheckbox.checked;
                        }
                    });
                });

                // Sauvegarder les compensations sélectionnées
                modal.querySelector('.swal2-confirm').addEventListener('click', () => {
                    const selected = Array.from(document.querySelectorAll('.compensation-check:checked'))
                        .map(cb => parseInt(cb.value))
                        .map(idx => tousLesCompensations[idx]);

                    if (selected.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Aucune sélection',
                            text: 'Veuillez sélectionner au moins une compensation à appliquer.'
                        });
                        return false;
                    }

                    appliquerCompensations(selected);
                });
            }
        });
    }

    // Fonction pour appliquer les compensations
    function appliquerCompensations(compensations) {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.getElementById('loadingModalLabel').textContent = 'Application des compensations en cours...';
        loadingModal.show();

        // Envoyer au serveur
        fetch('controller/appliquer_compensations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bureau: <?= $bureauId ?>,
                    promotion: <?= $promotionId ?>,
                    semestre: <?= $semestreId ?>,
                    deux_semestres: <?= $afficherDeuxSemestres ? '1' : '0' ?>,
                    session: <?= $sessionId ?>,
                    annee: <?= $anneeId ?>,
                    compensations: compensations
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingModal.hide();
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Compensations appliquées',
                        text: data.message || 'Les compensations ont été appliquées avec succès. La page va se recharger.'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de l\'application des compensations.'
                    });
                }
            })
            .catch(error => {
                loadingModal.hide();
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');

                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
                console.error('Erreur:', error);
            });
    }

    // Fonction pour imprimer la grille
    function imprimerGrille() {
        window.print();
    }

    // Fonction pour sauvegarder les moyennes dans la base de données
    // Fonction pour sauvegarder les moyennes dans la base de données
    function sauvegarderMoyennes() {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.getElementById('loadingModalLabel').textContent = 'Sauvegarde des moyennes en cours...';
        loadingModal.show();

        // Récupérer les paramètres de la grille
        // Avant l'envoi, assurez-vous que toutes les moyennes, même null, sont incluses
        const params = {
            bureau: <?= $bureauId ?>,
            promotion: <?= $promotionId ?>,
            semestre: <?= $semestreId ?>,
            deux_semestres: <?= $afficherDeuxSemestres ? '1' : '0' ?>,
            session: <?= $sessionId ?>,
            annee: <?= $anneeId ?>,
            // Convertir les objets PHP en objets JavaScript avec préservation des null
            moyennesUE: JSON.parse('<?= json_encode($moyennesUE, JSON_FORCE_OBJECT) ?>'),
            moyennesSemestre: JSON.parse('<?= json_encode($moyennesSemestre, JSON_FORCE_OBJECT) ?>'),
            moyennesAnnuelles: JSON.parse('<?= json_encode($moyennesAnnuelles, JSON_FORCE_OBJECT) ?>'),
            // Ajouter les données de validation
            validationsUE: JSON.parse('<?= json_encode($validationsUE, JSON_FORCE_OBJECT) ?>'),
            validationsSemestre: JSON.parse('<?= json_encode($validationsSemestre, JSON_FORCE_OBJECT) ?>'),
            validationsAnnuelles: JSON.parse('<?= json_encode($validationsAnnuelles, JSON_FORCE_OBJECT) ?>')
        };


        // Envoyer les données au serveur
        fetch('controller/update_moyennes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(params)
            })
            .then(response => response.json())
            .then(data => {
                // Fermer explicitement le modal de chargement
                loadingModal.hide();
                // Supprimer le backdrop et nettoyer les classes du body
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';

                // Afficher un message de succès ou d'erreur
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: 'Les moyennes ont été sauvegardées avec succès dans la base de données.'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de la sauvegarde des moyennes.'
                    });
                }
            })
            .catch(error => {
                // Fermer explicitement le modal de chargement
                loadingModal.hide();
                // Supprimer le backdrop et nettoyer les classes du body
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';

                // Afficher un message d'erreur
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
                console.error('Erreur:', error);
            });

    }

    // Fonction pour afficher le modal de compensation entre UE
    function afficherModalCompensationUE() {
        // Récupérer la première ligne du tableau pour avoir un accès aux données
        const firstRow = document.querySelector('#tableGrilleNotes tbody tr');
        if (!firstRow) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Aucune donnée de grille disponible.'
            });
            return;
        }

        // Créer un modal interactif
        let html = '<div class="row">' +
            '<div class="col-12">' +
            '<div class="form-group mb-3">' +
            '<label for="modeCompensationUE" class="form-label"><strong>Mode de compensation:</strong></label>' +
            '<select class="form-select" id="modeCompensationUE">' +
            '<option value="same_weight">Compenser UE avec même pondération uniquement</option>' +
            '<option value="any">Compenser UE indépendamment de la pondération</option>' +
            '</select>' +
            '<small class="form-text text-muted mt-2">' +
            '<strong>Mode 1:</strong> L\'UE déficitaire sera compensée uniquement avec une autre UE ayant le même nombre de crédits/poids.<br>' +
            '<strong>Mode 2:</strong> L\'UE déficitaire peut être compensée avec n\'importe quelle autre UE réussie.' +
            '</small>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="alert alert-warning mt-3">' +
            '<strong>Important:</strong> La compensation entre UE ajustera intelligemment les notes des ECUE de manière à:' +
            '<ul>' +
            '<li>Relever l\'UE déficitaire (< 10) à 10</li>' +
            '<li>Maintenir exactement la même moyenne finale de l\'étudiant</li>' +
            '<li>Réduire proportionnellement les notes des ECUE de l\'UE compensatrice</li>' +
            '</ul>' +
            '</div>';

        Swal.fire({
            title: 'Compenser entre UE',
            html: html,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Continuer',
            cancelButtonText: 'Annuler',
            didOpen: (modal) => {
                modal.querySelector('.swal2-html-container').style.textAlign = 'left';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const mode = document.getElementById('modeCompensationUE').value;
                calculerEtAfficherCompensationUE(mode);
            }
        });
    }

    // Fonction pour calculer les compensations entre UE
    function calculerEtAfficherCompensationUE(mode) {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.getElementById('loadingModalLabel').textContent = 'Calcul des compensations inter-UE en cours...';
        loadingModal.show();

        // Envoyer au serveur
        const compensationData = {
            bureau: <?= $bureauId ?>,
            promotion: <?= $promotionId ?>,
            semestre: <?= $semestreId ?>,
            deux_semestres: <?= $afficherDeuxSemestres ? '1' : '0' ?>,
            session: <?= $sessionId ?>,
            annee: <?= $anneeId ?>,
            mode: mode,
            moyennesUE: JSON.parse('<?= addslashes(json_encode($moyennesUE, JSON_FORCE_OBJECT)) ?>'),
            validationsUE: JSON.parse('<?= addslashes(json_encode($validationsUE, JSON_FORCE_OBJECT)) ?>'),
            notesByEtudiantEcue: JSON.parse('<?= addslashes(json_encode($notesByEtudiantEcue, JSON_FORCE_OBJECT)) ?>'),
            ecuesByUE: JSON.parse('<?= addslashes(json_encode($ecuesByUE, JSON_FORCE_OBJECT)) ?>')
        };

        fetch('controller/compenser_entre_ue.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(compensationData)
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.hide();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');

            if (data.success) {
                // Afficher le tableau des compensations possibles
                afficherTableauCompensationUE(data.compensations, mode);
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Aucune compensation disponible',
                    text: data.message || 'Aucune compensation inter-UE n\'est possible avec les critères sélectionnés.'
                });
            }
        })
        .catch(error => {
            loadingModal.hide();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');

            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors du calcul des compensations.'
            });
            console.error('Erreur:', error);
        });
    }

    // Fonction pour afficher le tableau des compensations entre UE
    function afficherTableauCompensationUE(compensations, mode) {
        if (!compensations || compensations.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Aucune compensation',
                text: 'Aucune compensation inter-UE n\'est disponible avec le mode sélectionné.'
            });
            return;
        }

        // Créer le tableau HTML
        let html = '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered" id="tableCompensationUE">' +
            '<thead class="table-light">' +
            '<tr>' +
            '<th style="width: 80px;"><input type="checkbox" id="selectAllUECompensations" title="Sélectionner tout"> </th>' +
            '<th>Étudiant</th>' +
            '<th>UE Déficitaire</th>' +
            '<th>Moyenne actuelle</th>' +
            '<th>UE Compensatrice</th>' +
            '<th>Crédits UE</th>' +
            '<th>Réduction estimée</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';

        compensations.forEach((comp, idx) => {
            const rowClass = comp.estApplicable ? '' : 'table-danger';
            const statusIcon = comp.estApplicable ? '✓' : '✗';
            const disabled = comp.estApplicable ? '' : 'disabled';
            const delta = (10 - comp.moyenneDeficitaire).toFixed(2);
            const reduction = comp.reductionEstimee.toFixed(2);

            html += '<tr class="' + rowClass + '">' +
                '<td><input type="checkbox" class="ue-compensation-check" value="' + idx + '" ' + disabled + '></td>' +
                '<td><strong>' + (comp.etudiantNoms || comp.matricule) + '</strong></td>' +
                '<td><span class="badge bg-danger">' + comp.ueDeficitaireDesignation + '</span></td>' +
                '<td class="text-danger"><strong>' + comp.moyenneDeficitaire.toFixed(2) + '</strong> (Δ +' + delta + ')</td>' +
                '<td><span class="badge bg-success">' + comp.ueCompensatriceDesignation + '</span></td>' +
                '<td>' + comp.creditsUE.toFixed(2) + '</td>' +
                '<td class="text-warning"><strong>-' + reduction + '</strong></td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
        
        const modeText = mode === 'same_weight' ? 'Compensation avec même pondération uniquement' : 'Compensation libre sans restriction';
        html += '<div class="alert alert-info mt-3">' +
            '<small>' +
            '<strong>Résumé:</strong> Cochez les lignes pour lesquelles vous souhaitez appliquer la compensation. ' +
            'La réduction estimée montre de combien la moyenne de l\'UE compensatrice diminuera.' +
            '<br><strong>Mode:</strong> ' + modeText +
            '</small>' +
            '</div>';

        Swal.fire({
            title: 'Compensations entre UE - Sélection',
            html: html,
            icon: 'info',
            width: '1400px',
            confirmButtonText: 'Appliquer les compensations',
            cancelButtonText: 'Annuler',
            showCancelButton: true,
            didOpen: (modal) => {
                modal.querySelector('.swal2-html-container').style.textAlign = 'left';

                // Gestion du checkbox "Sélectionner tout"
                const selectAllCheckbox = document.getElementById('selectAllUECompensations');
                const checkboxes = document.querySelectorAll('.ue-compensation-check');

                selectAllCheckbox.addEventListener('change', () => {
                    checkboxes.forEach(checkbox => {
                        if (!checkbox.disabled) {
                            checkbox.checked = selectAllCheckbox.checked;
                        }
                    });
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const selected = Array.from(document.querySelectorAll('.ue-compensation-check:checked'))
                    .map(cb => parseInt(cb.value))
                    .map(idx => compensations[idx]);

                if (selected.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aucune sélection',
                        text: 'Veuillez sélectionner au moins une compensation à appliquer.'
                    });
                    return;
                }

                appliquerCompensationUE(selected);
            }
        });
    }

    // Fonction pour appliquer les compensations entre UE
    function appliquerCompensationUE(compensations) {
        // Afficher le modal de chargement
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        document.getElementById('loadingModalLabel').textContent = 'Application des compensations inter-UE en cours...';
        loadingModal.show();

        // Envoyer au serveur
        fetch('controller/appliquer_compensation_ue.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                bureau: <?= $bureauId ?>,
                promotion: <?= $promotionId ?>,
                semestre: <?= $semestreId ?>,
                deux_semestres: <?= $afficherDeuxSemestres ? '1' : '0' ?>,
                session: <?= $sessionId ?>,
                annee: <?= $anneeId ?>,
                compensations: compensations
            })
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.hide();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Compensations appliquées',
                    text: data.message || 'Les compensations inter-UE ont été appliquées avec succès. La page va se recharger.'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue lors de l\'application des compensations.'
                });
            }
        })
        .catch(error => {
            loadingModal.hide();
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');

            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la communication avec le serveur.'
            });
            console.error('Erreur:', error);
        });
    }
</script>


<?php include "./views/include/footer.php"; ?>