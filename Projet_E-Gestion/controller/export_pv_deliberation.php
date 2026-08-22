<?php
require_once '../config/config.php';
require_once '../config/Connexion.php';
require_once '../vendor/autoload.php';
require_once '../models/Universite.php';
require_once '../models/Deliberation.php';
require_once '../models/Ecue.php';
require_once '../models/Agent.php';
require_once '../assets/html2pdf/vendor/autoload.php';

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

// Récupérer le crédit horaire depuis la configuration de l'université
$db = Connexion::getInstance()->getPDO();
$configQuery = $db->query("SELECT credit_heure FROM configuration_universite LIMIT 1");
$config = $configQuery->fetch(PDO::FETCH_ASSOC);
$heureCredit = $config && isset($config['credit_heure']) ? $config['credit_heure'] : 25;

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

// Initialiser les tableaux pour stocker les résultats
$moyennesUE = [];
$validationsUE = [];
$moyennesSemestre = [];
$validationsSemestre = [];
$moyennesAnnuelles = [];
$validationsAnnuelles = [];

// ============================================================================
// RÉCUPÉRER LES MOYENNES DIRECTEMENT DEPUIS LES TABLES SAUVEGARDÉES
// Cela garantit la cohérence avec la grille de notes (grille_notes.php)
// ============================================================================

// 1. Récupérer les moyennes UE sauvegardées
$stmtMoyenneUE = $db->prepare("
    SELECT matricule, \"idUE\", moyenne_deliberee, est_validee, credits_obtenus
    FROM moyenne_ue
    WHERE session_idsession = :sessionId
    AND annee_acad_idannee_acad = :anneeId
");
$stmtMoyenneUE->execute([':sessionId' => $sessionId, ':anneeId' => $anneeId]);
while ($row = $stmtMoyenneUE->fetch(PDO::FETCH_ASSOC)) {
    $moyennesUE[$row['matricule']][$row['idUE']] = $row['moyenne_deliberee'];
    $validationsUE[$row['matricule']][$row['idUE']] = (bool)$row['est_validee'];
}

// 2. Récupérer les moyennes semestre sauvegardées
$stmtMoyenneSem = $db->prepare("
    SELECT matricule, idsemestre, moyenne_deliberee, est_valide, credits_obtenus, credits_total
    FROM moyenne_semestre
    WHERE session_idsession = :sessionId
    AND annee_acad_idannee_acad = :anneeId
");
$stmtMoyenneSem->execute([':sessionId' => $sessionId, ':anneeId' => $anneeId]);
while ($row = $stmtMoyenneSem->fetch(PDO::FETCH_ASSOC)) {
    $moyennesSemestre[$row['matricule']][$row['idsemestre']] = $row['moyenne_deliberee'];
    $creditsTotal = $row['credits_total'] > 0 ? $row['credits_total'] : 1;
    $validationsSemestre[$row['matricule']][$row['idsemestre']] = [
        'credits_valides' => $row['credits_obtenus'],
        'credits_total' => $row['credits_total'],
        'pourcentage' => ($row['moyenne_deliberee'] / 20) * 100,
        'est_valide' => (bool)$row['est_valide']
    ];
}

// 3. Récupérer les moyennes annuelles sauvegardées (si mode deux semestres)
if ($afficherDeuxSemestres) {
    $stmtMoyenneAn = $db->prepare("
        SELECT matricule, moyenne_deliberee, est_admis, credits_obtenus, credits_total
        FROM moyenne_annuelle
        WHERE idpromotion = :promotionId
        AND session_idsession = :sessionId
        AND annee_acad_idannee_acad = :anneeId
    ");
    $stmtMoyenneAn->execute([':promotionId' => $promotionId, ':sessionId' => $sessionId, ':anneeId' => $anneeId]);
    while ($row = $stmtMoyenneAn->fetch(PDO::FETCH_ASSOC)) {
        $moyennesAnnuelles[$row['matricule']] = $row['moyenne_deliberee'];
        $creditsTotal = $row['credits_total'] > 0 ? $row['credits_total'] : 1;
        $validationsAnnuelles[$row['matricule']] = [
            'credits_valides' => $row['credits_obtenus'],
            'credits_total' => $row['credits_total'],
            'pourcentage' => ($row['moyenne_deliberee'] / 20) * 100,
            'est_valide' => (bool)$row['est_admis']
        ];
    }
}
            
            // Préparer les listes d'étudiants par catégorie
            $etudiantsValideTotalement = [];
            $etudiantsValidePartiellement = [];
            $etudiantsNonValide = [];
            $etudiantsAdmis = [];
            $etudiantsAdmisAvecRachat = [];
            $etudiantsAjournes = [];
            
            // Pour chaque étudiant, déterminer sa catégorie
            foreach ($etudiants as $etudiant) {
                $matricule = $etudiant['matricule'];
                
                if ($afficherDeuxSemestres) {
                    // Logique pour les résultats annuels
                    $moyenneAnnuelle = isset($moyennesAnnuelles[$matricule]) ? $moyennesAnnuelles[$matricule] : null;
                    
                    if ($moyenneAnnuelle === null) {
                        // Si pas de moyenne annuelle, considérer comme ajourné
                        $etudiantsAjournes[] = [
                            'nom' => $etudiant['noms'],
                            'matricule' => $matricule,
                            'moyenne' => '-',
                            'credits' => isset($validationsAnnuelles[$matricule]) ? 
                                $validationsAnnuelles[$matricule]['credits_valides'] . '/' . $validationsAnnuelles[$matricule]['credits_total'] : '-'
                        ];
                        continue;
                    }
                    
                    $creditsValides = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule]['credits_valides'] : 0;
                    $creditsTotal = isset($validationsAnnuelles[$matricule]) ? $validationsAnnuelles[$matricule]['credits_total'] : 0;
                    $pourcentageCredits = ($creditsTotal > 0) ? (($creditsValides / $creditsTotal) * 100) : 0;
                    
                    // Déterminer la catégorie de l'étudiant
                    if ($isDeuxiemeSession) {
                        // En deuxième session
                        if ($creditsValides == $creditsTotal && $creditsTotal > 0) {
                            $etudiantsAdmis[] = [
                                'nom' => $etudiant['noms'],
                                'matricule' => $matricule,
                                'moyenne' => $moyenneAnnuelle,
                                'credits' => "$creditsValides/$creditsTotal"
                            ];
                        } else if ($pourcentageCredits >= 75 && $moyenneAnnuelle >= 10) {
                            $etudiantsAdmisAvecRachat[] = [
                                'nom' => $etudiant['noms'],
                                'matricule' => $matricule,
                                'moyenne' => $moyenneAnnuelle,
                                'credits' => "$creditsValides/$creditsTotal"
                            ];
                        } else {
                            $etudiantsAjournes[] = [
                                'nom' => $etudiant['noms'],
                                'matricule' => $matricule,
                                'moyenne' => $moyenneAnnuelle,
                                'credits' => "$creditsValides/$creditsTotal"
                            ];
                        }
                    } else {
                        // En première session
                        if ($creditsValides == $creditsTotal && $creditsTotal > 0) {
                            $etudiantsAdmis[] = [
                                'nom' => $etudiant['noms'],
                                'matricule' => $matricule,
                                'moyenne' => $moyenneAnnuelle,
                                'credits' => "$creditsValides/$creditsTotal"
                            ];
                        } else {
                            $etudiantsAjournes[] = [
                                'nom' => $etudiant['noms'],
                                'matricule' => $matricule,
                                'moyenne' => $moyenneAnnuelle,
                                'credits' => "$creditsValides/$creditsTotal"
                            ];
                        }
                    }
                } else {
                    // Logique pour un seul semestre
                    $semId = $semestres[0]['idsemestre'];
                    
                    // Compter les UE validées pour ce semestre
                    $ueValidees = 0;
                    $totalUE = 0;
                    
                    foreach ($uesBySemestre[$semId] as $ue) {
                        $ueId = $ue['idUE'];
                        $totalUE++;
                        
                        if (isset($validationsUE[$matricule][$ueId]) && $validationsUE[$matricule][$ueId]) {
                            $ueValidees++;
                        }
                    }
                    
                    if ($ueValidees == $totalUE && $totalUE > 0) {
                        $etudiantsValideTotalement[] = [
                            'nom' => $etudiant['noms'],
                            'matricule' => $matricule,
                            'ue_validees' => "$ueValidees/$totalUE",
                            'moyenne' => isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : '-'
                        ];
                    } else if ($ueValidees > 0) {
                        $etudiantsValidePartiellement[] = [
                            'nom' => $etudiant['noms'],
                            'matricule' => $matricule,
                            'ue_validees' => "$ueValidees/$totalUE",
                            'moyenne' => isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : '-'
                        ];
                    } else {
                        $etudiantsNonValide[] = [
                            'nom' => $etudiant['noms'],
                            'matricule' => $matricule,
                            'ue_validees' => "$ueValidees/$totalUE",
                            'moyenne' => isset($moyennesSemestre[$matricule][$semId]) ? $moyennesSemestre[$matricule][$semId] : '-'
                        ];
                    }
                }
            }
            
            // Calculer les statistiques globales
            $statsGlobales = [
                'total_etudiants' => count($etudiants),
                'etudiants_admis' => $afficherDeuxSemestres ? count($etudiantsAdmis) + count($etudiantsAdmisAvecRachat) : count($etudiantsValideTotalement),
                'etudiants_ajournes' => $afficherDeuxSemestres ? count($etudiantsAjournes) : count($etudiantsValidePartiellement) + count($etudiantsNonValide)
            ];
            
            $statsGlobales['taux_reussite'] = ($statsGlobales['total_etudiants'] > 0) ? 
                ($statsGlobales['etudiants_admis'] / $statsGlobales['total_etudiants']) * 100 : 0;
            
            // Calculer les statistiques par UE
            $ueStats = [];
            foreach ($semestres as $semestre) {
                $semId = $semestre['idsemestre'];
                
                foreach ($uesBySemestre[$semId] as $ue) {
                    $ueId = $ue['idUE'];
                    $ueLabel = $ue['codeUE'] . ' - ' . $ue['designationUE'];
                    
                    $etudiantsValidesUE = 0;
                    $etudiantsTotalUE = 0;
                    
                    foreach ($etudiants as $etudiant) {
                        $matricule = $etudiant['matricule'];
                        $etudiantsTotalUE++;
                        
                        if (isset($validationsUE[$matricule][$ueId]) && $validationsUE[$matricule][$ueId]) {
                            $etudiantsValidesUE++;
                        }
                    }
                    
                    $tauxReussiteUE = ($etudiantsTotalUE > 0) ? ($etudiantsValidesUE / $etudiantsTotalUE) * 100 : 0;
                    
                    $ueStats[] = [
                        'label' => $ueLabel,
                        'taux' => $tauxReussiteUE
                    ];
                }
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
            
            // Générer le contenu HTML du PV
            $html = '
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <title>PV de Délibération - ' . htmlspecialchars($promotion['designationPromotion']) . '</title>
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
            
                <h1>PROCÈS-VERBAL DE DÉLIBÉRATION</h1>
                <h2>' . htmlspecialchars($promotion['designationPromotion']) . ' - ' . htmlspecialchars($session['description']) . '</h2>
                <h2>Année Académique: ' . htmlspecialchars($annee['designation']) . '</h2>
                
                <p>
                            L\'an deux mille ..........................................., le ................................. jour du mois de ................, s\'est réuni le jury de délibération de la ' . 
        htmlspecialchars($promotion['designationPromotion']) . ', sous la présidence de ' . 
        htmlspecialchars($bureau['president_nom'] ?? 'M./Mme le Président') . 
        ', pour délibérer sur les résultats de la ' . htmlspecialchars($session['description']) . ' de l\'année académique ' . 
        htmlspecialchars($annee['designation']) . '.
    </p>
    
    <p>
        Après délibération à huit clos, le jury a pris les décisions suivantes:
    </p>';

// Afficher les statistiques globales
$html .= '
    <h3>I. STATISTIQUES GLOBALES</h3>
    <table>
        <tr>
            <th>Nombre total d\'étudiants</th>
            <th>Nombre d\'étudiants admis</th>
            <th>Nombre d\'étudiants ajournés</th>
            <th>Taux de réussite</th>
        </tr>
        <tr>
            <td>' . $statsGlobales['total_etudiants'] . '</td>
            <td>' . $statsGlobales['etudiants_admis'] . '</td>
            <td>' . $statsGlobales['etudiants_ajournes'] . '</td>
            <td>' . number_format($statsGlobales['taux_reussite'], 2) . '%</td>
        </tr>
    </table>';

// Afficher les statistiques par UE
$html .= '
    <h3>II. STATISTIQUES PAR UNITÉ D\'ENSEIGNEMENT</h3>
    <table>
        <tr>
            <th>Unité d\'Enseignement</th>
            <th>Taux de réussite</th>
        </tr>';

foreach ($ueStats as $stat) {
    $html .= '
        <tr>
            <td class="text-left">' . htmlspecialchars($stat['label']) . '</td>
            <td>' . number_format($stat['taux'], 2) . '%</td>
        </tr>';
}

$html .= '
    </table>';

// Afficher les listes d'étudiants selon le mode (semestre ou année)
if ($afficherDeuxSemestres) {
    // Mode annuel
    $html .= '
        <h3>III. RÉSULTATS DES DÉLIBÉRATIONS</h3>';
    
    // Étudiants admis sans rachat
    $html .= '
        <h4>A. Sont admis sans rachat, les étudiants dont les noms suivent :</h4>';
    
    if (count($etudiantsAdmis) > 0) {
        $html .= '
            <table>
                <tr>
                    <th>#</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>Crédits validés</th>
                </tr>';
        
        foreach ($etudiantsAdmis as $index => $etudiant) {
            $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
            $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
            
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                    <td>' . $mention . '</td>
                    <td>' . htmlspecialchars($etudiant['credits']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
    } else {
        $html .= '
            <p>Aucun étudiant n\'a été admis sans rachat.</p>';
    }
    
    // Étudiants admis avec rachat (seulement en deuxième session)
    if ($isDeuxiemeSession) {
        $html .= '
            <h4>B. Sont admis avec rachat, les étudiants dont les noms suivent :</h4>';
        
        if (count($etudiantsAdmisAvecRachat) > 0) {
            $html .= '
                <table>
                    <tr>
                        <th>#</th>
                        <th>Matricule</th>
                        <th>Nom et Prénom</th>
                        <th>Moyenne</th>
                        <th>Mention</th>
                        <th>Crédits validés</th>
                    </tr>';
            
            foreach ($etudiantsAdmisAvecRachat as $index => $etudiant) {
                $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
                $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
                
                $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                        <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                        <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                        <td>' . $mention . '</td>
                        <td>' . htmlspecialchars($etudiant['credits']) . '</td>
                    </tr>';
            }
            
            $html .= '
                </table>';
        } else {
            $html .= '
                <p>Aucun étudiant n\'a été admis avec rachat.</p>';
        }
    }
    
    // Étudiants ajournés
    $html .= '
        <h4>' . ($isDeuxiemeSession ? 'C' : 'B') . '. Sont ajournés '. ($isDeuxiemeSession ? ':' : ' et recommandés au Rattrapage :').'</h4>';
    
    if (count($etudiantsAjournes) > 0) {
        $html .= '
            <table>
                <tr>
                    <th>#</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>Crédits validés</th>
                </tr>';
        
        foreach ($etudiantsAjournes as $index => $etudiant) {
            $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
            $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                    <td>' . $mention . '</td>
                    <td>' . htmlspecialchars($etudiant['credits']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
    } else {
        $html .= '
            <p>Aucun étudiant n\'a été ajourné.</p>';
    }
} else {
    // Mode semestriel
    $html .= '
        <h3>III. RÉSULTATS DES DÉLIBÉRATIONS - SEMESTRE ' . htmlspecialchars($semestres[0]['numeroSemestre']) . '</h3>';
    
    // Étudiants ayant validé totalement
    $html .= '
        <h4>A. Ont validés totalement les UE du semestre, les étudiants dont les noms suivent :</h4>';
    
    if (count($etudiantsValideTotalement) > 0) {
        $html .= '
            <table>
                <tr>
                    <th>#</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>UE validées</th>
                </tr>';
        
        foreach ($etudiantsValideTotalement as $index => $etudiant) {
            $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
            $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                    <td>' . $mention . '</td>
                    <td>' . htmlspecialchars($etudiant['ue_validees']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
    } else {
        $html .= '
            <p>Aucun étudiant n\'a validé toutes les UE.</p>';
    }
    
    // Étudiants ayant validé partiellement
    $html .= '
        <h4>B. Ont validés partiellement les UE du semestre, les étudiants dont les noms suivent :</h4>';
    
    if (count($etudiantsValidePartiellement) > 0) {
        $html .= '
            <table>
                <tr>
                    <th>#</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>UE validées</th>
                </tr>';
        
        foreach ($etudiantsValidePartiellement as $index => $etudiant) {
            $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
            $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
            
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                    <td>' . $mention . '</td>
                    <td>' . htmlspecialchars($etudiant['ue_validees']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
    } else {
        $html .= '
            <p>Aucun étudiant n\'a validé partiellement les UE.</p>';
    }
    
    // Étudiants n'ayant validé aucune UE
    $html .= '
        <h4>C. N\'ont validés aucune UE du semestre, les étudiants dont les noms suivent :</h4>';
    
    if (count($etudiantsNonValide) > 0) {
        $html .= '
            <table>
                <tr>
                    <th>#</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>UE validées</th>
                </tr>';
        
        foreach ($etudiantsNonValide as $index => $etudiant) {
            $moyenne = is_numeric($etudiant['moyenne']) ? $etudiant['moyenne'] : 0;
            $mention = is_numeric($etudiant['moyenne']) ? mentionsReleve($moyenne) : '-';
            
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="text-left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td>' . (is_numeric($etudiant['moyenne']) ? number_format($etudiant['moyenne'], 2) : $etudiant['moyenne']) . '</td>
                    <td>' . $mention . '</td>
                    <td>' . htmlspecialchars($etudiant['ue_validees']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
    } else {
        $html .= '
            <p>Tous les étudiants ont validé au moins une UE.</p>';
    }
}

// Conclusion et signatures
$html .= '
    <h3>IV. CONCLUSION</h3>
    <p>
        Le jury, après avoir délibéré, a arrêté les résultats ci-dessus conformément aux règlements en vigueur.
        Le présent procès-verbal a été établi et signé par les membres du jury présents.
    </p>
    
    <div class="signature">
        <p>Fait à ________________, le ' . date('d/m/Y') . '</p>
        <p style="margin-top: 20px;">Le Président du Jury</p>
        <p style="margin-top: 40px;"><strong>' . htmlspecialchars($bureau['president_nom'] ?? 'Le Président') . '</strong></p>
    </div>
    
    <div class="footer">
        <p>PV de délibération - ' . htmlspecialchars($promotion['designationPromotion']) . ' - ' . htmlspecialchars($session['description']) . ' - ' . htmlspecialchars($annee['designation']) . '</p>
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
            $pdf->Image($logoPath, 
                    $x, $y, $imageWidth, $imageHeight, '', '', '', false, 300, '', false, false, 0);
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
            $filename = 'PV_Deliberation_' . $promotion['designationPromotion'] . '_' . date('Y-m-d') . '.pdf';
            $html2pdf->output($filename, 'I');
        } catch (Html2PdfException $e) {
            die('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
        ?>
        