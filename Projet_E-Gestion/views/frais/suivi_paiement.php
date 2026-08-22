<?php
include "./views/include/header.php";

$universite = new Universite();
$fraisModel = new Frais();

// Récupération des paramètres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedFrais = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'academique';
$estComplet = isset($_GET['estComplet']) ? intval($_GET['estComplet']) : null;
$anneeAcadId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

// Ajoutez ces deux lignes
$frais_rapport = isset($_GET['frais_rapport']) ? intval($_GET['frais_rapport']) : 0;
$promotion_rapport = isset($_GET['promotion_rapport']) ? intval($_GET['promotion_rapport']) : 0;

// Récupérer l'année académique actuelle si non spécifiée
$currentYear = $universite->getCurrentAcademicYear();
$anneeAcadId = $currentYear['idannee_acad'];

// Récupérer les sections accessibles à l'utilisateur
$sections = [];
if ($_SESSION['idRole'] == 1) { // Si administrateur
    $sections = $universite->getSections();
} else {
    // Pour les autres utilisateurs, vérifier les sections associées
    $userSections = $universite->getUserSections($_SESSION['id']);
    foreach ($userSections as $sectionId) {
        $sectionData = $universite->getSectionById($sectionId);
        if ($sectionData) {
            $sections[] = $sectionData;
        }
    }
}

// Préparer les filtres pour la requête
$filters = [];
if ($anneeAcadId > 0) {
    $filters['anneeAcadId'] = $anneeAcadId;
}
if ($promotionId > 0) {
    $filters['promotionId'] = $promotionId;
}
if ($estComplet !== null) {
    $filters['estComplet'] = (bool) $estComplet;
}

// Récupérer les paiements
$paiements = [];
if ($selectedType == 'academique') {
    if ($selectedFrais > 0) {
        $paiements = $fraisModel->getPaiementsByFrais($selectedFrais, $anneeAcadId);
        $fraisDetails = $fraisModel->getFraisById($selectedFrais);
    } else {
        $paiements = $fraisModel->getPaiements($search, $filters);
    }
} else {
    if ($selectedFrais > 0) {
        $paiements = $fraisModel->getPaiementsByFraisSoutenance($selectedFrais, $anneeAcadId);
        $fraisDetails = $fraisModel->getFraisSoutenanceById($selectedFrais);
    } else {
        $paiements = $fraisModel->getPaiementsSoutenance($search, $filters);
    }
}

// Récupérer les étudiants pour l'année académique sélectionnée
$etudiants = [];
if ($anneeAcadId > 0) {
    if ($promotionId > 0) {
        $etudiants = $universite->getEtudiantsByPromotion($promotionId, $anneeAcadId);
    } else {
        // Récupérer tous les étudiants (limité si besoin)
        $etudiants = $universite->getEtudiantsByAnneeAcad($anneeAcadId);
    }
}

// Récupérer les frais pour le formulaire d'ajout de paiement
$fraisList = [];
if ($selectedType == 'academique') {
    if ($promotionId > 0) {
        $fraisList = $fraisModel->getFraisByPromotion($promotionId, $anneeAcadId);
    } else {
        // Récupérer tous les frais (limité par section si nécessaire)
        $fraisList = $fraisModel->getAllFrais($anneeAcadId, '');
    }
} else {
    $fraisList = $fraisModel->getAllFraisSoutenance($anneeAcadId, '');
}

// Récupérer les promotions pour les filtres
$promotions = [];
foreach ($sections as $section) {
    $sectionPromotions = $universite->getPromotionsBySection($section['idsection'], $anneeAcadId);
    $promotions = array_merge($promotions, $sectionPromotions);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUIVI DES PAIEMENTS<?php echo ($selectedFrais > 0 && isset($fraisDetails)) ? ' - ' . $fraisDetails['designation'] : ''; ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=frais/configuration_frais&type_frais=<?= $selectedType ?>">Frais</a></li>
                <li class="breadcrumb-item active">Paiements</li>
            </ol>
        </nav>
    </div>

    

    <!-- État des paiements par frais -->
<div class="col-12 mt-4">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">État des paiements par frais</h5>
            
            <form method="GET" action="">
                <input type="hidden" name="view" value="frais/suivi_paiement">
                <input type="hidden" name="type" value="<?= $selectedType ?>">
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="promotion_rapport" class="form-label">Promotion</label>
                        <select name="promotion_rapport" id="promotion_rapport" class="form-select" onchange="loadFraisByPromotion(this.value)">
                            <option value="0">Toutes les promotions</option>
                            <?php foreach ($promotions as $promotion): ?>
                                <option value="<?= $promotion['idpromotion'] ?>" <?= $promotion_rapport == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="frais_rapport" class="form-label">Frais</label>
                        <select name="frais_rapport" id="frais_rapport" class="form-select">
                            <option value="0">Tous les frais</option>
                            <?php 
                            // Si une promotion est sélectionnée, n'affichez que les frais de cette promotion
                            $displayFrais = [];
                            if ($promotion_rapport > 0 && $selectedType == 'academique') {
                                $displayFrais = $fraisModel->getFraisByPromotion($promotion_rapport, $anneeAcadId);
                            } else {
                                $displayFrais = $fraisList;
                            }
                            
                            foreach ($displayFrais as $frais): 
                            ?>
                                <option value="<?= $selectedType == 'academique' ? $frais['idfrais'] : $frais['idfrais_soutenance'] ?>" 
                                        <?= $frais_rapport == ($selectedType == 'academique' ? $frais['idfrais'] : $frais['idfrais_soutenance']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($frais['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Afficher</button>
                        <button type="button" class="btn btn-success me-2" onclick="exportPaymentStatus()">
                            <i class="bi bi-file-excel"></i> Exporter
                        </button>
                    </div>
                </div>
            </form>

            
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-striped" id="tablePaymentStatus">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Matricule</th>
                            <th>Nom de l'étudiant</th>
                            <th>Frais</th>
                            <th>Montant total</th>
                            <th>Montant payé</th>
                            <th>Montant restant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Récupérer les données pour le rapport selon les filtres
                        $frais_rapport = isset($_GET['frais_rapport']) ? intval($_GET['frais_rapport']) : 0;
                        $promotion_rapport = isset($_GET['promotion_rapport']) ? intval($_GET['promotion_rapport']) : 0;
                        
                        $etudiants_paiements = [];
                        
                        // Chargez les données d'état de paiement ici
                        if ($selectedType == 'academique') {
                            if ($frais_rapport > 0) {
                                // Pour un frais spécifique
                                $etudiants = $promotion_rapport > 0 
                                    ? $universite->getEtudiantsByPromotion($promotion_rapport, $anneeAcadId) 
                                    : $universite->getEtudiantsByAnneeAcad($anneeAcadId);
                                
                                $frais = $fraisModel->getFraisById($frais_rapport);
                                
                                foreach ($etudiants as $i => $etudiant) {
                                    // Vérifier si l'étudiant a payé ce frais
                                    $montantPaye = 0;
                                    $paiements = $fraisModel->getPaiementsByEtudiant($etudiant['idetudiant'], $anneeAcadId);
                                    foreach ($paiements as $paiement) {
                                        if ($paiement['frais_idfrais'] == $frais_rapport) {
                                            $montantPaye += $paiement['montantPaye'];
                                        }
                                    }
                                    
                                    $montantTotal = $frais['montant'];
                                    $montantRestant = max(0, $montantTotal - $montantPaye);
                                    $statut = ($montantPaye >= $montantTotal) ? 'Complet' : 'Partiel';
                                    $pourcentage = $montantTotal > 0 ? ($montantPaye / $montantTotal * 100) : 0;
                                    
                                    $etudiants_paiements[] = [
                                        'index' => $i + 1,
                                        'matricule' => $etudiant['matricule'],
                                        'nom' => $etudiant['noms'],
                                        'designation_frais' => $frais['designation'],
                                        'montant_total' => $montantTotal,
                                        'montant_paye' => $montantPaye,
                                        'montant_restant' => $montantRestant,
                                        'pourcentage' => $pourcentage,
                                        'statut' => $statut,
                                        'devise' => $frais['devise'],
                                        'etudiant_id' => $etudiant['idetudiant'],
                                        'frais_id' => $frais_rapport
                                    ];
                                }
                            } else {
                                // Pour tous les frais
                                $etudiants = $promotion_rapport > 0 
                                    ? $universite->getEtudiantsByPromotion($promotion_rapport, $anneeAcadId) 
                                    : $universite->getEtudiantsByAnneeAcad($anneeAcadId);
                                
                                $index = 1;
                                foreach ($etudiants as $etudiant) {
                                    $fraisPromotion = $fraisModel->getFraisByPromotion($etudiant['promotion_idpromotion'], $anneeAcadId);
                                    $paiements = $fraisModel->getPaiementsByEtudiant($etudiant['idetudiant'], $anneeAcadId);
                                    
                                    foreach ($fraisPromotion as $frais) {
                                        $montantPaye = 0;
                                        foreach ($paiements as $paiement) {
                                            if ($paiement['frais_idfrais'] == $frais['idfrais']) {
                                                $montantPaye += $paiement['montantPaye'];
                                            }
                                        }
                                        
                                        $montantTotal = $frais['montant'];
                                        $montantRestant = max(0, $montantTotal - $montantPaye);
                                        $statut = ($montantPaye >= $montantTotal) ? 'Complet' : 'Partiel';
                                        $pourcentage = $montantTotal > 0 ? ($montantPaye / $montantTotal * 100) : 0;
                                        
                                        $etudiants_paiements[] = [
                                            'index' => $index++,
                                            'matricule' => $etudiant['matricule'],
                                            'nom' => $etudiant['noms'],
                                            'designation_frais' => $frais['designation'],
                                            'montant_total' => $montantTotal,
                                            'montant_paye' => $montantPaye,
                                            'montant_restant' => $montantRestant,
                                            'pourcentage' => $pourcentage,
                                            'statut' => $statut,
                                            'devise' => $frais['devise'],
                                            'etudiant_id' => $etudiant['idetudiant'],
                                            'frais_id' => $frais['idfrais']
                                        ];
                                    }
                                }
                            }
                        } else {
                            // Même logique pour les frais de soutenance
                        }
                        
                        // Afficher les résultats
                        foreach ($etudiants_paiements as $ep):
                        ?>
                        <tr class="<?= $ep['statut'] == 'Complet' ? 'table-success' : '' ?>">
                            <td><?= $ep['index'] ?></td>
                            <td><?= htmlspecialchars($ep['matricule']) ?></td>
                            <td><?= htmlspecialchars($ep['nom']) ?></td>
                            <td><?= htmlspecialchars($ep['designation_frais']) ?></td>
                            <td><?= number_format($ep['montant_total'], 2) ?> <?= $ep['devise'] ?></td>
                            <td><?= number_format($ep['montant_paye'], 2) ?> <?= $ep['devise'] ?></td>
                            <td><?= number_format($ep['montant_restant'], 2) ?> <?= $ep['devise'] ?></td>
                            <td>
                                <?php if ($ep['statut'] == 'Complet'): ?>
                                    <span class="badge bg-success">Complet</span>
                                <?php else: ?>
                                    <div class="progress">
                                        <div class="progress-bar <?= $ep['pourcentage'] > 50 ? 'bg-warning' : 'bg-danger' ?>" role="progressbar" style="width: <?= $ep['pourcentage'] ?>%;" aria-valuenow="<?= $ep['pourcentage'] ?>" aria-valuemin="0" aria-valuemax="100"><?= round($ep['pourcentage']) ?>%</div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="printStudentStatement(<?= $ep['etudiant_id'] ?>, <?= $ep['frais_id'] ?>)">
                                    <i class="bi bi-printer"></i> Relevé
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($etudiants_paiements)): ?>
                        <tr>
                            <td colspan="9" class="text-center">Aucune donnée disponible</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>




</main>



<script>
// Fonction pour charger les frais en fonction de la promotion sélectionnée
function loadFraisByPromotion(promotionId) {
    const fraisSelect = document.getElementById('frais_rapport');
    fraisSelect.innerHTML = '<option value="0">Chargement...</option>';
    
    // Si aucune promotion n'est sélectionnée, afficher l'option "Tous les frais"
    if (!promotionId || promotionId == "0") {
        fraisSelect.innerHTML = '<option value="0">Tous les frais</option>';
        
        // Charger tous les frais disponibles
        <?php foreach ($fraisList as $frais): ?>
        fraisSelect.innerHTML += `<option value="<?= $selectedType == 'academique' ? $frais['idfrais'] : $frais['idfrais_soutenance'] ?>">
            <?= htmlspecialchars(addslashes($frais['designation'])) ?>
        </option>`;
        <?php endforeach; ?>
        
        return;
    }
    
    // Requête AJAX pour récupérer les frais associés à la promotion
    fetch(`controller/get_frais_by_promotion.php?promotionId=${promotionId}&type=${encodeURIComponent('<?= $selectedType ?>')}&anneeAcadId=<?= $anneeAcadId ?>`)
        .then(response => response.json())
        .then(data => {
            fraisSelect.innerHTML = '<option value="0">Tous les frais</option>';
            
            if (data.length === 0) {
                fraisSelect.innerHTML += '<option value="" disabled>Aucun frais disponible pour cette promotion</option>';
                return;
            }
            
            // Ajouter les options de frais
            data.forEach(frais => {
                fraisSelect.innerHTML += `<option value="${frais.id}">
                    ${frais.designation}
                </option>`;
            });
            
            // Si un frais était préalablement sélectionné et qu'il est disponible pour cette promotion, le sélectionner
            const previouslySelectedFrais = "<?= $frais_rapport ?>";
            if (previouslySelectedFrais) {
                const option = fraisSelect.querySelector(`option[value="${previouslySelectedFrais}"]`);
                if (option) {
                    option.selected = true;
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des frais:', error);
            fraisSelect.innerHTML = '<option value="0">Erreur de chargement</option>';
        });
}

/**
 * Exporte l'état des paiements en Excel
 */
function exportPaymentStatus() {
    const frais = document.getElementById('frais_rapport').value;
    const promotion = document.getElementById('promotion_rapport').value;
    const type = '<?= $selectedType ?>';
    
    window.location.href = `controller/export_payment_status.php?frais=${frais}&promotion=${promotion}&type=${type}`;
}

function printStudentStatement(etudiantId, fraisId = 0) {
    // Paramètres
    const anneeAcad = <?= $anneeAcadId ?>;
    const type = '<?= $selectedType ?>';
    
    // Redirection vers le contrôleur d'exportation
    window.location.href = `controller/export_student_statement.php?etudiantId=${etudiantId}&fraisId=${fraisId}&anneeAcad=${anneeAcad}&type=${type}`;
}


// Charger les frais au chargement de la page si une promotion est sélectionnée
document.addEventListener('DOMContentLoaded', function() {
    const promotionSelect = document.getElementById('promotion_rapport');
    if (promotionSelect.value !== "0") {
        loadFraisByPromotion(promotionSelect.value);
    } else {
        // Sélectionner le frais précédemment choisi si nécessaire
        const previouslySelectedFrais = "<?= $frais_rapport ?>";
        if (previouslySelectedFrais) {
            const fraisSelect = document.getElementById('frais_rapport');
            const option = fraisSelect.querySelector(`option[value="${previouslySelectedFrais}"]`);
            if (option) {
                option.selected = true;
            }
        }
    }
});
</script>


<?php include "./views/include/footer.php"; ?>


