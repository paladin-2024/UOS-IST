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


        // Récupérer l'information sur la session (première ou deuxième)
        $sessionInfo = [];
        if ($sessionId) {
            $sessionInfo = $universite->getSessionById($sessionId);
        }
        $isDeuxiemeSession = $sessionInfo && stripos($sessionInfo['designSession'], 'deuxième') !== false;




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
        $heuresParCredit = 25;

        if ($bureauId && $promotionId && $sessionId && $anneeId && ($semestreId || $afficherDeuxSemestres)) {
            // Récupérer les semestres à afficher
            $semestresToShow = $afficherDeuxSemestres ? $semestres : array_values(array_filter($semestres, function ($sem) use ($semestreId) {
                return $sem['idsemestre'] == $semestreId;
            }));

            if ($configDeliberation) {
                // Use the configured value if it exists, otherwise use 25 as the new default
                $heuresParCredit = isset($configDeliberation['heures_par_credit']) ? 
                    intval($configDeliberation['heures_par_credit']) : 25;
            } else {
                // No configuration found, use 25 as the new default
                $heuresParCredit = 25;
            }

            

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
                            if ($isDeuxiemeSession) {
                                $notePremiereSession = $deliberation->getNotesEtudiantECUEPremiereSession($matricule, $ecueId, $anneeId);
            
                                if ($notePremiereSession && $notePremiereSession['MF'] !== null && $notePremiereSession['MF'] >= 10) {
                                    $notes = $notePremiereSession;
                                } else {
                                    //On vérifie si l'UE a été validé malgré que la note est inférieure à 10
                                    if($ueValideeEnPremiereSession){
                                        $notes = $notePremiereSession;
                                    }else{
                                        $notes = $deliberation->getNotesEtudiantECUE($matricule, $ecueId, $sessionId, $anneeId);
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
                                $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heuresParCredit;

                                // Toujours ajouter le coefficient au total de l'UE
                                $totalCoeffUE += $coeffECUE;

                                // Ajouter les points pondérés seulement si la note est disponible
                                if ($notes['MF'] !== null) {
                                    $totalPointsUE += $notes['MF'] * $coeffECUE;
                                }
                            } else {
                                // Même si l'étudiant n'a pas de note, calculer le coefficient de l'ECUE
                                $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heuresParCredit;
                                $totalCoeffUE += $coeffECUE;
                            }
                        }

                        // Calculer la moyenne de l'UE en tenant compte de la configuration
                        if ($ecueCount > 0) {
                            // Si l'UE a été validée en première session, utiliser cette moyenne
                            if ($isDeuxiemeSession && $ueValideeEnPremiereSession) {
                                $moyenneUE = $moyenneUEPremiereSession;
                                $moyennesUE[$matricule][$ueId] = $moyenneUE;
                                $validationsUE[$matricule][$ueId] = true; // UE déjà validée

                                // Ajouter à la somme du semestre pour le calcul de la moyenne
                                $totalPointsSemestre += $totalPointsUE;
                                $ueAvecMoyenne++;

                                // Ajouter les crédits car l'UE est validée
                                $creditsValidesSemestre += $totalCoeffUE;
                            }
                            // Sinon, calculer la moyenne normalement
                            else if ($calculerAvecNotesVides || $ecueWithCompleteNotesCount == $ecueCount) {
                                if ($totalCoeffUE > 0) {
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
                                }
                            } else {
                                // Si on n'est pas configuré pour calculer avec des notes vides et qu'il manque des notes
                                $moyennesUE[$matricule][$ueId] = null;
                                $validationsUE[$matricule][$ueId] = false;
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
                                    <input type="hidden" name="view" value="deliberation/pv_deliberation">

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
                                            <!-- Ajouter ce bouton dans la section des boutons d'action, après le bouton "Bulletins" -->
                                            <button type="button" class="btn btn-danger" onclick="genererPVDeliberation()">
                                                <i class="bi bi-file-text me-1"></i> Générer le Procès verbal de délibération
                                            </button>


                                            <a href="?view=deliberation/pv_deliberation" class="btn btn-secondary">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <a href="?view=deliberation/pv_deliberation" class="btn btn-secondary">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </form>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </section>
                    <!-- Loading Modal -->
        <div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="loadingModalLabel">Traitement en cours...</h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-3">Veuillez patienter pendant le traitement de votre demande.</p>
                    </div>
                </div>
            </div>
        </div>
        </main>

        </main>






        <!-- À la fin du fichier, dans la section script -->
        <script>
           

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











        </script>


        <?php include "./views/include/footer.php"; ?>