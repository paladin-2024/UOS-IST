<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;

session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

// Fonction pour déterminer la mention (code lettre)
function mentions($point) {
    $mention = "";
    if ($point < 8)
        $mention = "G";
    else if ($point >= 8 && $point < 10)
        $mention = "F";
    else if ($point >= 10 && $point < 12)
        $mention = "E";
    else if ($point >= 12 && $point < 14)
        $mention = "D";
    else if ($point >= 14 && $point < 16)
        $mention = "C";
    else if ($point >= 16 && $point < 18)
        $mention = "B";
    else if ($point >= 18)
        $mention = "A";
    return $mention;
}

// Fonction pour déterminer la mention (texte complet)
function mentionsReleve($point) {
    $mention = "";
    if ($point < 8)
        $mention = "Insatisfaisant";
    else if ($point >= 8 && $point < 10)
        $mention = "Insuffisant";
    else if ($point >= 10 && $point < 12)
        $mention = "Passable";
    else if ($point >= 12 && $point < 14)
        $mention = "Assez bien";
    else if ($point >= 14 && $point < 16)
        $mention = "Bien";
    else if ($point >= 16 && $point < 18)
        $mention = "Très bien";
    else if ($point >= 18)
        $mention = "Excellent";
    return $mention;
}

// Récupérer les paramètres
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$semestreId = isset($_GET['semestre']) ? intval($_GET['semestre']) : 0;
$afficherDeuxSemestres = isset($_GET['deux_semestres']) && $_GET['deux_semestres'] == 1;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

$heureCredit=25;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$bureauId || !$promotionId || !$sessionId || !$anneeId || (!$semestreId && !$afficherDeuxSemestres)) {
    die('Paramètres incomplets');
}

// Initialiser les objets nécessaires
$universite = new Universite();
$deliberation = new Deliberation();
$ecue = new Ecue();
$agent = new Agent();

// Récupérer les informations de configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les informations du bureau, promotion, etc.
$bureau = $deliberation->getBureauJuryById($bureauId);
$promotion = $universite->getPromotionById($promotionId);
$session = $universite->getSessionById($sessionId);
$annee = $universite->getAnneeAcademiqueById($anneeId);

// Vérifier si c'est la deuxième session
$isDeuxiemeSession = $session && (stripos($session['designSession'], 'deuxième') !== false || 
                                  stripos($session['designSession'], 'deuxieme') !== false);

// Récupérer la configuration de délibération
$configDeliberation = $deliberation->getDeliberationConfig($bureauId, $sessionId, $anneeId);
$calculerAvecNotesVides = isset($configDeliberation['calculer_moyenne_avec_notes_vides']) ?
    (bool)$configDeliberation['calculer_moyenne_avec_notes_vides'] : false;

// Récupérer les semestres à afficher
$semestres = [];
if ($afficherDeuxSemestres) {
    // Récupérer les semestres de la promotion
    $semestres = $universite->getSemestresByPromotion($promotionId);
    if (count($semestres) >= 2) {
        $semestres = array_slice($semestres, 0, 2);
    }
} else {
    // Récupérer uniquement le semestre spécifié
    $semestre = $universite->getSemestreById($semestreId);
    if ($semestre) {
        $semestres[] = $semestre;
    }
}

// Récupérer les étudiants de la promotion
if ($isDeuxiemeSession) {
    // En deuxième session, ne récupérer que les étudiants éligibles
    $etudiants = $deliberation->getEtudiantsEligiblesDeuxiemeSession($promotionId, $anneeId, $semestres);
} else {
    // En première session, récupérer tous les étudiants
    $etudiants = $deliberation->getEtudiantsByPromotion($promotionId, $anneeId);
}

// Pour chaque semestre, récupérer les UE et ECUE
$uesBySemestre = [];
$ecuesByUE = [];
foreach ($semestres as $semestre) {
    $semId = $semestre['idsemestre'];
    $uesBySemestre[$semId] = $deliberation->getUEsBySemestre($semId);

    foreach ($uesBySemestre[$semId] as $ue) {
        $ueId = $ue['idUE'];
        $ecuesByUE[$ueId] = $ecue->getECUEsByUE2($ueId);
    }
}

// Initialiser les tableaux pour stocker les résultats calculés
$notesByEtudiantEcue = [];
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
    foreach ($semestres as $semestre) {
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
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heureCredit;

                    // Toujours ajouter le coefficient au total de l'UE
                    $totalCoeffUE += $coeffECUE;

                    // Ajouter les points pondérés seulement si la note est disponible
                    if ($notes['MF'] !== null) {
                        $totalPointsUE += $notes['MF'] * $coeffECUE;
                    }
                } else {
                    // Même si l'étudiant n'a pas de note, calculer le coefficient de l'ECUE
                    $coeffECUE = ($ecueItem['CMI'] + $ecueItem['TD'] + $ecueItem['TP']) / $heureCredit;
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
                    'est_valide' => $moyenneSemestre >= 10 && ($creditsValidesSemestre==$totalCreditsSemestre) // Considérer comme validé si moyenne >= 10
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
    if ($afficherDeuxSemestres && count($semestres) >= 2) {
        $totalPointsAnnee = 0;
        $totalCreditsAnnee = 0;
        $creditsValidesAnnee = 0;
        $semestreAvecMoyenne = 0;

        foreach ($semestres as $semestre) {
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
            if ($calculerAvecNotesVides || $semestreAvecMoyenne == count($semestres)) {
                $moyenneAnnuelle = $totalPointsAnnee / $totalCreditsAnnee;
                $moyennesAnnuelles[$matricule] = $moyenneAnnuelle;

                // Calculer le pourcentage basé sur la moyenne (sur 20)
                $pourcentageValidationAnnee = ($moyenneAnnuelle / 20) * 100;

                $validationsAnnuelles[$matricule] = [
                    'credits_valides' => $creditsValidesAnnee,
                    'credits_total' => $totalCreditsAnnee,
                    'pourcentage' => $pourcentageValidationAnnee,
                    'est_valide' => $moyenneAnnuelle >= 10 && ($creditsValidesAnnee==$totalCreditsAnnee) // Critère de validation annuelle basé sur la moyenne
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

// Préparer les données des étudiants pour le palmarès
$etudiantsPalmares = [];

foreach ($etudiants as $etudiant) {
    $matricule = $etudiant['matricule'];
    
    // Obtenir la moyenne (annuelle ou du semestre selon le mode)
    $moyenne = null;
    $credits = null;
    $estValide = false;
    
    if ($afficherDeuxSemestres) {
        // Mode annuel - utiliser la moyenne annuelle
        if (isset($moyennesAnnuelles[$matricule])) {
            $moyenne = $moyennesAnnuelles[$matricule];
            if (isset($validationsAnnuelles[$matricule])) {
                $credits = $validationsAnnuelles[$matricule]['credits_valides'] . '/' . 
                           $validationsAnnuelles[$matricule]['credits_total'];
                $estValide = $validationsAnnuelles[$matricule]['est_valide'];
            }
        }
    } else {
        // Mode semestriel - utiliser la moyenne du semestre
        $semId = $semestres[0]['idsemestre'];
        if (isset($moyennesSemestre[$matricule][$semId])) {
            $moyenne = $moyennesSemestre[$matricule][$semId];
            if (isset($validationsSemestre[$matricule][$semId])) {
                $credits = $validationsSemestre[$matricule][$semId]['credits_valides'] . '/' . 
                           $validationsSemestre[$matricule][$semId]['credits_total'];
                $estValide = $validationsSemestre[$matricule][$semId]['est_valide'];
            }
        }
    }
    
    // Ajouter l'étudiant au tableau s'il a une moyenne
    $etudiantsPalmares[] = [
        'matricule' => $matricule,
        'nom' => $etudiant['noms'],
        'moyenne' => $moyenne,  // Peut être null
        'credits' => $credits,
        'est_valide' => $estValide,
        'mention' => $moyenne !== null ? mentionsReleve($moyenne) : '-'
    ];
}

// Trier les étudiants par moyenne décroissante
usort($etudiantsPalmares, function($a, $b) {
    if ($a['moyenne'] == $b['moyenne']) return 0;
    return ($a['moyenne'] > $b['moyenne']) ? -1 : 1;
});

// Attribuer un rang à chaque étudiant
$rang = 1;
$moyennePrecedente = null;
$rangPrecedent = 1;

foreach ($etudiantsPalmares as &$etudiant) {
    // Si la moyenne est identique à la précédente, garder le même rang
    if ($moyennePrecedente !== null && $etudiant['moyenne'] == $moyennePrecedente) {
        $etudiant['rang'] = $rangPrecedent;
    } else {
        $etudiant['rang'] = $rang;
        $rangPrecedent = $rang;
    }
    
    $moyennePrecedente = $etudiant['moyenne'];
    $rang++;
}

// Récupérer le chemin du logo
$logoPath = isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
    '../' . $configUniversite['logo'] : '';

// Convertir le logo en base64 s'il existe
$logoBase64 = '';
if (!empty($logoPath) && file_exists($logoPath)) {
    $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
}

// Générer le contenu HTML du Palmarès
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Palmarès Académique - ' . htmlspecialchars($promotion['designationPromotion']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
        }
        
        .institution-header {
            margin-bottom: 1px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .logo {
            float: left;
            max-width: 70px;
            max-height: 70px;
            margin-right: 15px;
        }
        
        .institution-info {
            margin-left: 10px;
            margin-top: -15px;
        }
        
        h1 {
            font-size: 16pt;
            margin: 5px 0;
            text-align: center;
        }
        
        h2 {
            font-size: 14pt;
            margin: 5px 0;
            text-align: center;
        }
        
        h3 {
            font-size: 12pt;
            margin: 10px 0;
        }
        
        p {
            margin: 5px 0;
            text-align: justify;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
        
        th {
            background-color: #f0f0f0;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-left {
            text-align: left;
        }
        
        .signature {
            margin-top: 30px;
            text-align: center;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 9pt;
        }
        
        .top-rank {
            background-color: #fffce6;
            font-weight: bold;
        }
        
        .medal-1 {
            background-color: #ffd700; /* Or */
        }
        
        .medal-2 {
            background-color: #c0c0c0; /* Argent */
        }
        
        .medal-3 {
            background-color: #cd7f32; /* Bronze */
        }
    </style>
</head>
<body>
    <div class="institution-header">
        ' . (isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
        '<img src="' . $logoBase64 . '" class="logo" alt="Logo">' : '') . '
        <div class="institution-info">
            <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPERIEUR') . '</div>
            <div><strong>' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</strong></div>
            <div>Tél: ' . htmlspecialchars($configUniversite['telephone'] ?? '') . ' | Email: ' . htmlspecialchars($configUniversite['email'] ?? '') . '</div>
            ' . (isset($configUniversite['site_web']) && !empty($configUniversite['site_web']) ? 
                '<div>Site web: ' . htmlspecialchars($configUniversite['site_web']) . '</div>' : '') . '
        </div>
    </div>
    <p>______________________________________________________________________________________________</p>

    <h1>PALMARÈS ACADÉMIQUE</h1>
    <h2>' . htmlspecialchars($promotion['designationPromotion']) . ' - ' . htmlspecialchars($session['description']) . '</h2>
    <h2>Année Académique: ' . htmlspecialchars($annee['designation']) . '</h2>
    
    <p>
        Le Jury de délibération, après examen des résultats, a établi le classement suivant pour
        ' . ($afficherDeuxSemestres ? 'l\'année académique' : 'le semestre ' . $semestres[0]['numeroSemestre']) . '
        ' . htmlspecialchars($annee['designation']) . '.
    </p>';

// Afficher les statistiques globales
$html .= '
    <h3>I. STATISTIQUES GLOBALES</h3>
    <table>
        <tr>
            <th>Total étudiants</th>
            <th>Etudiants admis</th>
            <th>Nombre d\'étudiants ajournés</th>
            <th>Taux de réussite</th>
            <th>Moyenne générale</th>
        </tr>
        <tr>
            <td>' . count($etudiants) . '</td>
            <td>' . count(array_filter($etudiantsPalmares, function($e) { return $e['est_valide']; })) . '</td>
            <td>' . count(array_filter($etudiantsPalmares, function($e) { return !$e['est_valide']; })) . '</td>
            <td>' . number_format((count(array_filter($etudiantsPalmares, function($e) { return $e['est_valide']; })) / count($etudiantsPalmares) * 100), 2) . '%</td>
            <td>' . number_format(array_sum(array_column($etudiantsPalmares, 'moyenne')) / count($etudiantsPalmares), 2) . '</td>
        </tr>
    </table>';

// Afficher le classement des étudiants
$html .= '
    <h3>II. CLASSEMENT PAR ORDRE DE MÉRITE</h3>
    
    <table>
        <tr>
            <th>Rang</th>
            <th>Matricule</th>
            <th>Nom et Prénom</th>
            <th>Moyenne</th>
            <th>Mention</th>
            <th>Crédits validés</th>
                        <th>Décision</th>
        </tr>';

// Afficher les étudiants classés
$statsAdmis = 0;
$statsAjournes = 0;
$statsAdmisRachat = 0; // Uniquement pour la deuxième session en mode annuel

// Dans la boucle qui traite chaque étudiant pour le palmarès (remplacez la détermination actuelle de $decision)
foreach ($etudiantsPalmares as $index => $etudiant) {
    $matricule = $etudiant['matricule'];
    $rang = isset($etudiant['rang']) ? $etudiant['rang'] : '-';
    $rowClass = '';
    
    // Attribuer les classes pour le style visuel
    if ($etudiant['moyenne'] !== null && isset($etudiant['rang'])) {
        if ($rang == 1) {
            $rowClass = 'medal-1';
        } else if ($rang == 2) {
            $rowClass = 'medal-2';
        } else if ($rang == 3) {
            $rowClass = 'medal-3';
        } else if ($rang <= 10) {
            $rowClass = 'top-rank';
        }
    }
    
    // Déterminer la décision selon le mode (semestre ou année) et la session
    $decision = '';
    $matricule = $etudiant['matricule'];
    
    if ($afficherDeuxSemestres) {
        // Mode annuel
        if ($isDeuxiemeSession) {
            // Deuxième session - Année
                // Si tous les crédits sont validés
                $creditsValides = $validationsAnnuelles[$matricule]['credits_valides'] ?? 0;
                $creditsTotal = $validationsAnnuelles[$matricule]['credits_total'] ?? 1; // éviter division par zéro
                $pourcentage = ($creditsValides / $creditsTotal) * 100;

                
                
                if ($creditsValides == $creditsTotal) {
                    $decision = "ADMIS";
                    $statsAdmis++;
                } else if ($pourcentage >= 75) {
                    $decision = "ADMIS RACHAT";
                    $statsAdmisRachat++;
                } else {
                    $decision = "AJOURNÉ";
                    $statsAjournes++;
                }
            
        } else {
            // Première session - Année
            // Pour être ADMIS, il faut avoir validé toutes les UE
            $creditsValides = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule]['credits_valides'] : 0;
            $creditsTotal = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule]['credits_total'] : 1;
            
            if ($creditsValides == $creditsTotal && $creditsTotal > 0) {
                $decision = "ADMIS";
                $statsAdmis++;
            } else {
                $decision = "AJOURNÉ";
                $statsAjournes++;
            }
        }
    } else {
        // Mode semestriel
        $semId = $semestres[0]['idsemestre'];
        
        // Compter les UE validées
        $ueValidees = 0;
        $totalUE = 0;
        
        foreach ($uesBySemestre[$semId] as $ue) {
            $ueId = $ue['idUE'];
            $totalUE++;
            
            if (isset($validationsUE[$matricule][$ueId]) && $validationsUE[$matricule][$ueId]) {
                $ueValidees++;
            }
        }
        
        // Déterminer la décision pour le semestre
        if ($ueValidees == $totalUE && $totalUE > 0) {
            $decision = "VT"; // Validé Totalement
            $statsAdmis++; // VT
        } else if ($ueValidees > 0) {
            $decision = "VP"; // Validé Partiellement 
            $statsAjournes++; // VP ou NV
        } else {
            $decision = "NV"; // Non Validé
            $statsAjournes++; // VP ou NV
        }
    }
    
    $html .= '
        <tr class="' . $rowClass . '">
            <td>' . (isset($etudiant['rang']) ? $rang . (($rang <= 3 && is_numeric($rang)) ? ' 🏆' : '') : '-') . '</td>
            <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
            <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
            <td>' . ($etudiant['moyenne'] !== null ? number_format($etudiant['moyenne'], 2) : '-') . '</td>
            <td>' . htmlspecialchars($etudiant['mention']) . '</td>
            <td>' . htmlspecialchars($etudiant['credits'] ?? '-') . '</td>
            <td>' . $decision . '</td>
        </tr>';
}


$html .= '
    </table>';

// Après la table de classement, si on est en mode semestriel
if (!$afficherDeuxSemestres) {
    $html .= '
    <div class="mt-3">
        <p><strong>Légende des décisions :</strong></p>
        <ul>
            <li><strong>VT</strong> : Validé Totalement (toutes les UE validées)</li>
            <li><strong>VP</strong> : Validé Partiellement (au moins une UE validée)</li>
            <li><strong>NV</strong> : Non Validé (aucune UE validée)</li>
        </ul>
    </div>';
}


// Conclusion et signatures
$html .= '
    <h3>III. CERTIFICATION</h3>
    <p>
        Le présent palmarès est certifié exact et conforme aux procès-verbaux de délibération.
        Il a été établi sous la responsabilité du Jury présidé par ' . htmlspecialchars($bureau['president_nom'] ?? 'M./Mme le Président') . '.
    </p>
    
    <div class="signature">
        <p>Fait à ________________, le ' . date('d/m/Y') . '</p>
        <p style="margin-top: 20px;">Le Président du Jury</p>
        <p style="margin-top: 40px;"><strong>' . htmlspecialchars($bureau['president_nom'] ?? 'Le Président') . '</strong></p>
    </div>
    
    <div class="footer">
        <p>Palmarès Académique - ' . htmlspecialchars($promotion['designationPromotion']) . ' - ' . htmlspecialchars($session['description']) . ' - ' . htmlspecialchars($annee['designation']) . '</p>
        <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
    </div>
</body>
</html>';

// Générer le PDF
try {
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    
    // D'abord écrire le HTML
    $html2pdf->writeHTML($html);
    
    // Accéder à l'objet TCPDF sous-jacent
    $pdf = $html2pdf->pdf;
    
    // Nombre de pages dans le document
    $numPages = $pdf->getNumPages();
    
    // Pour chaque page, ajouter le filigrane
    for ($i = 1; $i <= $numPages; $i++) {
        $pdf->setPage($i);
        
        // Dimensions de la page
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        
        // Réduire la taille de l'image
        $imageWidth = $pageWidth * 0.3;
        $imageHeight = 0; // Proportionnel
        
        // Estimer la hauteur de l'image si elle est proportionnelle
        $estimatedHeight = $imageWidth * 0.90;
        
        // Position centrale, mais décalée vers le haut
        $x = ($pageWidth - $imageWidth) / 2;
        $y = ($pageHeight - $estimatedHeight) / 2 - 30;
        
        // Ajouter le filigrane avec une faible opacité
        if (!empty($logoPath) && file_exists($logoPath)) {
            $pdf->setAlpha(0.05);
            try {
                $pdf->Image($logoPath, 
                        $x, $y, $imageWidth, $imageHeight, '', '', '', false, 300, '', false, false, 0);
            } catch (Exception $e) {
                // Silencieusement ignorer les erreurs d'image de filigrane
            }
        }
        
        // Ajouter le filigrane texte "DOCUMENT OFFICIEL"
        $pdf->StartTransform();
        $pdf->SetFont('helvetica', 'B', 40);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Rotate(45, $pageWidth/2, $pageHeight/2);
        $textWidth = $pdf->GetStringWidth("DOCUMENT OFFICIEL");
        $pdf->setAlpha(0.1);
        $pdf->Text($pageWidth/2 - $textWidth/2, $pageHeight/2, "DOCUMENT OFFICIEL");
        $pdf->StopTransform();
        $pdf->setAlpha(1);
    }

    // Générer le PDF
    $filename = 'Palmares_Academique_' . str_replace(' ', '_', $promotion['designationPromotion']) . '_' . date('Y-m-d') . '.pdf';
    $html2pdf->output($filename, 'I');
} catch (Html2PdfException $e) {
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>

