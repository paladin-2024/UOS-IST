<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier si l'ID de l'exercice est fourni
if (!isset($_GET['exercice_id']) || empty($_GET['exercice_id'])) {
    header('Location: ../?view=finance/config_budget');
    exit;
}

$exercice_id = intval($_GET['exercice_id']);
$connexion = Connexion::getInstance()->getPDO();

// Récupérer les informations de l'exercice budgétaire
$stmt = $connexion->prepare("
    SELECT * FROM exercices_budgetaires 
    WHERE id = :exercice_id
");
$stmt->bindParam(':exercice_id', $exercice_id);
$stmt->execute();
$exercice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exercice) {
    header('Location: ../?view=finance/config_budget');
    exit;
}

// Récupérer toutes les catégories budgétaires
$stmt = $connexion->query("
    SELECT c.*, p.designation as parent_designation
    FROM categories_budget c
    LEFT JOIN categories_budget p ON c.parent_id = p.id
    WHERE c.est_actif = 1
    ORDER BY c.type, c.code, c.niveau
");
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Identifier les catégories qui ont des sous-catégories
$has_children = [];
foreach ($all_categories as $cat) {
    if (!empty($cat['parent_id'])) {
        $has_children[$cat['parent_id']] = true;
    }
}

// Récupérer les données du budget pour l'exercice sélectionné
$budget_data = [];
$stmt = $connexion->prepare("
    SELECT b.* 
    FROM budget b
    WHERE b.exercice_id = :exercice_id
");
$stmt->bindParam(':exercice_id', $exercice_id);
$stmt->execute();
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indexer les budgets par catégorie_id pour un accès facile
foreach ($budgets as $budget) {
    $budget_data[$budget['categorie_id']] = $budget;
}

// Fonction améliorée pour calculer les totaux de budget - similaire à celle de config_budget.php
function calculateCategoryTotals($categories, $category_id, $budget_data) {
    $totals = [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ];
    
    // Trouver toutes les sous-catégories directes
    $children = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $category_id) {
            $children[] = $cat;
        }
    }
    
    if (empty($children)) {
        // Si c'est une catégorie terminale, retourner ses propres valeurs
        if (isset($budget_data[$category_id])) {
            $budget = $budget_data[$category_id];
            $totals['prevu'] = $budget['montant_prevu'];
            $totals['revise'] = $budget['montant_revise'] ?? $budget['montant_prevu'];
            $totals['engage'] = $budget['montant_engage'];
            $totals['realise'] = $budget['montant_realise'];
            $totals['disponible'] = $budget['disponible'];
        }
        return $totals;
    }
    
    // Sinon, additionner les totaux de toutes les sous-catégories
    foreach ($children as $child) {
        $child_totals = calculateCategoryTotals($categories, $child['id'], $budget_data);
        $totals['prevu'] += $child_totals['prevu'];
        $totals['revise'] += $child_totals['revise'];
        $totals['engage'] += $child_totals['engage'];
        $totals['realise'] += $child_totals['realise'];
        $totals['disponible'] += $child_totals['disponible'];
    }
    
    return $totals;
}

// Préparer les totaux généraux
$totaux = [
    'recettes' => [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ],
    'depenses' => [
        'prevu' => 0,
        'revise' => 0,
        'engage' => 0,
        'realise' => 0,
        'disponible' => 0
    ]
];

// Calculer les totaux pour les catégories racines
foreach ($all_categories as $categorie) {
    if (empty($categorie['parent_id'])) { // Seulement les catégories racines
        $type = $categorie['type'] === 'Recette' ? 'recettes' : 'depenses';
        $calculated_totals = calculateCategoryTotals($all_categories, $categorie['id'], $budget_data);
        
        $totaux[$type]['prevu'] += $calculated_totals['prevu'];
        $totaux[$type]['revise'] += $calculated_totals['revise'];
        $totaux[$type]['engage'] += $calculated_totals['engage'];
        $totaux[$type]['realise'] += $calculated_totals['realise'];
        $totaux[$type]['disponible'] += $calculated_totals['disponible'];
    }
}

// Récupérer les informations de l'université
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Créer une instance de TCPDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Suivi Budgétaire - ' . $exercice['designation']);
$pdf->SetSubject('Rapport budgétaire');
$pdf->SetKeywords('Budget, Finance, Suivi, Rapport');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 15);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen
$successColor = array(40, 167, 69); // Vert
$dangerColor = array(220, 53, 69); // Rouge
$warningColor = array(255, 193, 7); // Jaune
$parentCategoryColor = array(100, 100, 180); // Couleur pour catégories parentes
$level1Color = array(245, 245, 245); // Gris très clair pour niveau 1
$level2Color = array(250, 250, 250); // Blanc cassé pour niveau 2
$level3Color = array(255, 255, 255); // Blanc pour niveau 3+

// Ajouter une page
$pdf->AddPage();

// Ajouter le logo en filigrane
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        // Sauvegarder l'état actuel
        $pdf->setAlpha(0.1);
        
        // Position au centre
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        
        $logoWidth = 100;
        $logoHeight = 100;
        
        $x = ($pageWidth - $logoWidth) / 2;
        $y = ($pageHeight - $logoHeight) / 2;
        
        // Ajouter l'image en filigrane
        $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        
        // Restaurer l'état
        $pdf->setAlpha(1);
    }
}

// En-tête avec les informations de l'université
if ($configUniversite) {
    // Logo de l'université (visible)
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 25, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetY(10);
    $pdf->Cell(0, 6, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
    
    if (!empty($configUniversite['sigle'])) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
    }
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    if (!empty($configUniversite['adresse'])) {
        $pdf->Cell(0, 4, $configUniversite['adresse'], 0, 1, 'C');
    }
    
    $contactInfo = '';
    if (!empty($configUniversite['telephone'])) {
        $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
    }
    if (!empty($configUniversite['email'])) {
        $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
    }
    if (!empty($configUniversite['site_web'])) {
        $contactInfo .= 'Web: ' . $configUniversite['site_web'];
    }
    
    if (!empty($contactInfo)) {
        $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
    }
    
    // Ligne de séparation élégante
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(10, 42, $pdf->getPageWidth() - 10, 42);
}

// Titre du document avec fond coloré et dégradé
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Ln(5);
$pdf->Cell(0, 10, 'RAPPORT DE SUIVI BUDGÉTAIRE', 0, 1, 'C', true);

// Informations sur l'exercice budgétaire avec un design amélioré
$pdf->SetFillColor(240, 240, 245);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Ln(5);

// Boîte encadrée pour les infos de l'exercice
$pdf->RoundedRect(60, $pdf->GetY(), 170, 25, 3.50, '1111', 'DF', array(), array(240, 240, 245));
$pdf->SetY($pdf->GetY() + 5);
$pdf->Cell(0, 8, 'Exercice budgétaire: ' . $exercice['designation'], 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Période: ' . date('d/m/Y', strtotime($exercice['date_debut'])) . ' au ' . date('d/m/Y', strtotime($exercice['date_fin'])), 0, 1, 'C');
$pdf->Cell(0, 6, 'Statut: ' . ($exercice['est_actif'] ? 'Actif' : 'Inactif') . ($exercice['est_cloture'] ? ' - Clôturé' : ''), 0, 1, 'C');

// Résumé du budget avec design amélioré
$pdf->Ln(10);
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'RÉSUMÉ DU BUDGET', 0, 1, 'C', true);

// Tableau de résumé avec design amélioré
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 9);

// En-têtes du tableau
$pdf->SetFillColor(220, 220, 230);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(50, 7, 'Type', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Prévu', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Révisé', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Engagé', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Réalisé', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Disponible', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Taux Réal.', 1, 1, 'C', true);

// Ligne des recettes
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(230, 250, 230); // Vert clair pour recettes
$pdf->SetTextColor(0, 0, 0);
$taux_realisation_recettes = ($totaux['recettes']['prevu'] > 0) ? ($totaux['recettes']['realise'] / $totaux['recettes']['prevu'] * 100) : 0;

$pdf->Cell(50, 7, 'Recettes', 1, 0, 'L', true);
$pdf->Cell(40, 7, number_format($totaux['recettes']['prevu'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['recettes']['revise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['recettes']['engage'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['recettes']['realise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['recettes']['disponible'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($taux_realisation_recettes, 2) . '%', 1, 1, 'R', true);

// Ligne des dépenses
$pdf->SetFillColor(250, 230, 230); // Rouge clair pour dépenses
$taux_realisation_depenses = ($totaux['depenses']['prevu'] > 0) ? ($totaux['depenses']['realise'] / $totaux['depenses']['prevu'] * 100) : 0;

$pdf->Cell(50, 7, 'Dépenses', 1, 0, 'L', true);
$pdf->Cell(40, 7, number_format($totaux['depenses']['prevu'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['depenses']['revise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['depenses']['engage'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['depenses']['realise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($totaux['depenses']['disponible'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($taux_realisation_depenses, 2) . '%', 1, 1, 'R', true);

// Ligne de la balance
$pdf->SetFillColor(230, 230, 250); // Violet clair pour la balance
$pdf->SetFont('helvetica', 'B', 10);
$balance_prevu = $totaux['recettes']['prevu'] - $totaux['depenses']['prevu'];
$balance_revise = $totaux['recettes']['revise'] - $totaux['depenses']['revise'];
$balance_engage = $totaux['recettes']['engage'] - $totaux['depenses']['engage'];
$balance_realise = $totaux['recettes']['realise'] - $totaux['depenses']['realise'];
$balance_disponible = $totaux['recettes']['disponible'] - $totaux['depenses']['disponible'];
$taux_balance = ($totaux['recettes']['prevu'] > 0) ? ($balance_realise / $totaux['recettes']['prevu'] * 100) : 0;

$pdf->Cell(50, 7, 'Balance', 1, 0, 'L', true);
$pdf->Cell(40, 7, number_format($balance_prevu, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($balance_revise, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($balance_engage, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($balance_realise, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(40, 7, number_format($balance_disponible, 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($taux_balance, 2) . '%', 1, 1, 'R', true);

// Fonction améliorée pour afficher les catégories et sous-catégories avec leurs montants
function renderCategoryRows($pdf, $categories, $category_id, $level = 0, $budget_data, $has_children) {
    // Récupérer la catégorie actuelle
    $category = null;
    foreach ($categories as $cat) {
        if ($cat['id'] == $category_id) {
            $category = $cat;
            break;
        }
    }
    
    if (!$category) return;
    
    $isParent = isset($has_children[$category['id']]) && $has_children[$category['id']];
    
    // Calculer les totaux budgétaires pour cette catégorie et ses enfants
    $totals = calculateCategoryTotals($categories, $category['id'], $budget_data);
    
    // Déterminer les couleurs de fond et de texte selon le niveau
    if ($category['type'] === 'Recette') {
        if ($level == 0) {
            $pdf->SetFillColor(220, 240, 220); // Vert clair pour les catégories principales
        } elseif ($level == 1) {
            $pdf->SetFillColor(230, 245, 230); // Vert un peu plus clair pour niveau 1
        } else {
            $pdf->SetFillColor(240, 250, 240); // Vert très clair pour niveau 2+
        }
    } else {
        if ($level == 0) {
            $pdf->SetFillColor(240, 220, 220); // Rouge clair pour les catégories principales
        } elseif ($level == 1) {
            $pdf->SetFillColor(245, 230, 230); // Rouge un peu plus clair pour niveau 1
        } else {
            $pdf->SetFillColor(250, 240, 240); // Rouge très clair pour niveau 2+
        }
    }
    
    // Définir le style du texte selon le niveau
    if ($level == 0) {
        $pdf->SetFont('helvetica', 'B', 9);
    } else if ($isParent) {
        $pdf->SetFont('helvetica', 'B', 8);
    } else {
        $pdf->SetFont('helvetica', '', 8);
    }
    
    // Créer l'indentation visuelle pour montrer la hiérarchie
    $prefix = '';
    if ($level > 0) {
        $prefix = str_repeat('   ', $level) . '';
    }
    
    $designation = $prefix . $category['code'] . ' - ' . $category['designation'];
    
    // Calculer les pourcentages
    $taux_engagement = ($totals['prevu'] > 0) ? ($totals['engage'] / $totals['prevu'] * 100) : 0;
    $taux_realisation = ($totals['prevu'] > 0) ? ($totals['realise'] / $totals['prevu'] * 100) : 0;
    
    // Style de bordure adapté au niveau hiérarchique
    if ($level == 0) {
        $pdf->SetLineWidth(0.2);
        $borderStyle = 'B';
    } else {
        $pdf->SetLineWidth(0.1);
        $borderStyle = 'B';
    }
    
    // Hauteur de la cellule selon le niveau
    $cellHeight = 6;
    
    // Afficher la ligne de la catégorie
    $pdf->Cell(132, $cellHeight, $designation, $borderStyle, 0, 'L', true);
    $pdf->Cell(25, $cellHeight, number_format($totals['prevu'], 2, ',', ' '), $borderStyle, 0, 'R', true);
    $pdf->Cell(25, $cellHeight, number_format($totals['revise'], 2, ',', ' '), $borderStyle, 0, 'R', true);
    $pdf->Cell(25, $cellHeight, number_format($totals['engage'], 2, ',', ' '), $borderStyle, 0, 'R', true);
    $pdf->Cell(25, $cellHeight, number_format($totals['realise'], 2, ',', ' '), $borderStyle, 0, 'R', true);
    $pdf->Cell(25, $cellHeight, number_format($totals['disponible'], 2, ',', ' '), $borderStyle, 0, 'R', true);
    $pdf->Cell(20, $cellHeight, number_format($taux_realisation, 2) . '%', $borderStyle, 1, 'R', true);
    
    // Afficher récursivement les enfants, s'il y en a
    if ($isParent) {
        // Trouver toutes les sous-catégories directes
        foreach ($categories as $child) {
            if ($child['parent_id'] == $category_id) {
                renderCategoryRows($pdf, $categories, $child['id'], $level + 1, $budget_data, $has_children);
            }
        }
    }
}

// Détail des recettes
$pdf->AddPage();
$pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'DÉTAIL DES RECETTES', 0, 1, 'C', true);
$pdf->Ln(5);

// En-têtes du tableau des recettes
$pdf->SetFillColor(220, 240, 220);
$pdf->SetTextColor(0, 100, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(132, 7, 'Catégorie', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Prévu', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Révisé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Engagé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Réalisé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Disponible', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Taux Réal.', 1, 1, 'C', true);

// Afficher les catégories de recettes
$pdf->SetTextColor(0, 0, 0);
foreach ($all_categories as $categorie) {
    if ($categorie['type'] === 'Recette' && empty($categorie['parent_id'])) {
        renderCategoryRows($pdf, $all_categories, $categorie['id'], 0, $budget_data, $has_children);
    }
}

// Afficher le total des recettes
$pdf->SetFillColor(100, 180, 100);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(132, 7, 'TOTAL RECETTES', 1, 0, 'L', true);
$pdf->Cell(25, 7, number_format($totaux['recettes']['prevu'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['recettes']['revise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['recettes']['engage'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['recettes']['realise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['recettes']['disponible'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(20, 7, number_format($taux_realisation_recettes, 2) . '%', 1, 1, 'R', true);

// Détail des dépenses
$pdf->AddPage();
$pdf->SetFillColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'DÉTAIL DES DÉPENSES', 0, 1, 'C', true);
$pdf->Ln(5);

// En-têtes du tableau des dépenses
$pdf->SetFillColor(240, 220, 220);
$pdf->SetTextColor(100, 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(132, 7, 'Catégorie', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Prévu', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Révisé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Engagé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Réalisé', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Disponible', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Taux Réal.', 1, 1, 'C', true);

// Afficher les catégories de dépenses
$pdf->SetTextColor(0, 0, 0);
foreach ($all_categories as $categorie) {
    if ($categorie['type'] === 'Dépense' && empty($categorie['parent_id'])) {
        renderCategoryRows($pdf, $all_categories, $categorie['id'], 0, $budget_data, $has_children);
    }
}

// Afficher le total des dépenses
$pdf->SetFillColor(180, 100, 100);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(132, 7, 'TOTAL DÉPENSES', 1, 0, 'L', true);
$pdf->Cell(25, 7, number_format($totaux['depenses']['prevu'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['depenses']['revise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['depenses']['engage'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['depenses']['realise'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(25, 7, number_format($totaux['depenses']['disponible'], 2, ',', ' '), 1, 0, 'R', true);
$pdf->Cell(20, 7, number_format($taux_realisation_depenses, 2) . '%', 1, 1, 'R', true);



// Pied de page avec informations sur le document
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 10, 'REMARQUES ET CONCLUSIONS', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(0, 6, 'Ce rapport présente l\'état d\'exécution du budget ' . $exercice['designation'] . 
                      ' à la date du ' . date('d/m/Y') . '.', 0, 'L');
$pdf->Ln(2);
$balance_text = 'La balance budgétaire est ';
if ($balance_realise > 0) {
    $balance_text .= 'positive de ' . number_format($balance_realise, 2, ',', ' ') . ' ' . ($config['devise_principale'] ?? 'USD') . '.';
} elseif ($balance_realise < 0) {
    $balance_text .= 'négative de ' . number_format(abs($balance_realise), 2, ',', ' ') . ' ' . ($config['devise_principale'] ?? 'USD') . '.';
} else {
    $balance_text .= 'équilibrée.';
}
$pdf->MultiCell(0, 6, $balance_text, 0, 'L');
$pdf->Ln(2);

// Taux d'exécution globaux
$taux_execution_global = ($totaux['recettes']['prevu'] + $totaux['depenses']['prevu'] > 0) ? 
                        (($totaux['recettes']['realise'] + $totaux['depenses']['realise']) / 
                        ($totaux['recettes']['prevu'] + $totaux['depenses']['prevu']) * 100) : 0;

$pdf->MultiCell(0, 6, 'Le taux d\'exécution global du budget est de ' . number_format($taux_execution_global, 2) . '%.', 0, 'L');
$pdf->Ln(5);

// Signatures
$pdf->SetDrawColor(180, 180, 180);
$pdf->Ln(20);
$pdf->Cell(90, 0, '', 'T', 0, 'C');
$pdf->Cell(15, 0, '', 0, 0);
$pdf->Cell(90, 0, '', 'T', 1, 'C');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(90, 5, 'Responsable financier', 0, 0, 'C');
$pdf->Cell(15, 5, '', 0, 0);
$pdf->Cell(90, 5, 'Ordonnateur', 0, 1, 'C');
$pdf->Ln(30);

// Informations sur la génération du rapport
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système', 0, 1, 'C');
$pdf->Cell(0, 5, 'Ce document est confidentiel et destiné à usage interne uniquement.', 0, 1, 'C');

// Générer le PDF
$filename = 'Rapport_Budget_' . $exercice['designation'] . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'I');

