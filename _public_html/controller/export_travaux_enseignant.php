<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// Vérifications d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Vérifier les paramètres requis
if (!isset($_GET['enseignant_id']) || !isset($_GET['annee_academique'])) {
    header('Location: ../?view=recherche/direction');
    exit;
}

// Initialiser la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$enseignantId = intval($_GET['enseignant_id']);
$anneeAcadId = intval($_GET['annee_academique']);

// Vérifier si l'utilisateur est administrateur
$isAdmin = ($_SESSION['idRole'] == 1);

// Récupérer les informations de l'année académique
$query = "SELECT * FROM annee_acad WHERE idannee_acad = :anneeId";
$stmt = $connexion->prepare($query);
$stmt->bindParam(':anneeId', $anneeAcadId);
$stmt->execute();
$anneeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anneeInfo) {
    header('Location: ../?view=recherche/direction');
    exit;
}

// Récupérer les informations de l'enseignant
$query = "SELECT a.*, g.designation as grade 
          FROM agent a 
          LEFT JOIN grade g ON a.grade_id = g.idgrade 
          WHERE a.idAgent = :enseignantId";
$stmt = $connexion->prepare($query);
$stmt->bindParam(':enseignantId', $enseignantId);
$stmt->execute();
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enseignant) {
    header('Location: ../?view=recherche/direction');
    exit;
}

// Variables pour stocker les sections autorisées
$authorizedSections = [];

// Vérifier les droits d'accès si l'utilisateur n'est pas admin
if (!$isAdmin) {
    $userId = $_SESSION['id'];
    
    // Récupérer les sections dont l'utilisateur est responsable pour l'année sélectionnée
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->bindParam(':anneeId', $anneeAcadId);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($userSections)) {
        // L'utilisateur n'est responsable d'aucune section
        header('Location: ../?view=recherche/direction');
        exit;
    }
    
    // Vérifier si l'enseignant appartient à une des sections autorisées
    $placeholders = implode(',', array_fill(0, count($userSections), '?'));
    $query = "SELECT COUNT(*) FROM agent_section 
              WHERE idAgent = ? AND idsection IN ($placeholders)";
    
    $params = array_merge([$enseignantId], $userSections);
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    $hasAccess = $stmt->fetchColumn() > 0;
    
    if (!$hasAccess) {
        // L'utilisateur n'a pas accès aux données de cet enseignant
        header('Location: ../?view=recherche/direction');
        exit;
    }
    
    // Stocker les sections autorisées pour filtrer les travaux
    $authorizedSections = $userSections;
}

// Récupérer les travaux pour l'enseignant et l'année sélectionnée
$query = "SELECT s.*, 
          spec.designation as specialisation, 
          e.noms as etudiant,
          e.matricule as matricule_etudiant,
          p.designationPromotion as promotion,
          sec.designationSection as section,
          o.designationOrientation as orientation
          FROM sujets s
          LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
          LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
          LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
          LEFT JOIN orientation o ON spec.idorientation = o.idorientation
          LEFT JOIN section sec ON o.section_idsection = sec.idsection
          WHERE (s.idDirecteur = :enseignantId OR s.idEncadreur = :enseignantId)
          AND s.annee_acad_idannee_acad = :anneeAcadId
          AND s.etudiant_idetudiant IS NOT NULL
          AND s.statut_validation = 'Validé'";

$params = [
    ':enseignantId' => $enseignantId,
    ':anneeAcadId' => $anneeAcadId
];

// Si l'utilisateur n'est pas admin et a des sections autorisées, filtrer par sections
if (!$isAdmin && !empty($authorizedSections)) {
    $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
    $query .= " AND o.section_idsection IN ($placeholders)";
    
    // Préparer la requête avec les paramètres nommés et positionnels
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':enseignantId', $enseignantId);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
    
    // Bind les sections avec des paramètres positionnels
    $paramIndex = 1;
    foreach ($authorizedSections as $section) {
        $stmt->bindValue($paramIndex + 2, $section, PDO::PARAM_INT);
        $paramIndex++;
    }
} else {
    // Admin ou pas de filtre de section
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':enseignantId', $enseignantId);
    $stmt->bindParam(':anneeAcadId', $anneeAcadId);
}

$query .= " ORDER BY p.designationPromotion ASC, s.intitule ASC";

// Exécuter la requête finale
if (!$isAdmin && !empty($authorizedSections)) {
    // Reconstruire la requête complète pour les sections
    $query = "SELECT s.*, 
              spec.designation as specialisation, 
              e.noms as etudiant,
              e.matricule as matricule_etudiant,
              p.designationPromotion as promotion,
              sec.designationSection as section,
              o.designationOrientation as orientation
              FROM sujets s
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE (s.idDirecteur = ? OR s.idEncadreur = ?)
              AND s.annee_acad_idannee_acad = ?
              AND s.etudiant_idetudiant IS NOT NULL
              AND s.statut_validation = 'Validé'";
    
    $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
    $query .= " AND o.section_idsection IN ($placeholders)";
    $query .= " ORDER BY p.designationPromotion ASC, s.intitule ASC";
    
    $params = array_merge(
        [$enseignantId, $enseignantId, $anneeAcadId],
        $authorizedSections
    );
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
} else {
    // Admin : pas de filtre de section
    $query = "SELECT s.*, 
              spec.designation as specialisation, 
              e.noms as etudiant,
              e.matricule as matricule_etudiant,
              p.designationPromotion as promotion,
              sec.designationSection as section,
              o.designationOrientation as orientation
              FROM sujets s
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE (s.idDirecteur = ? OR s.idEncadreur = ?)
              AND s.annee_acad_idannee_acad = ?
              AND s.etudiant_idetudiant IS NOT NULL
              ORDER BY p.designationPromotion ASC, s.intitule ASC";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute([$enseignantId, $enseignantId, $anneeAcadId]);
}

$travaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la configuration de l'université
$query = "SELECT * FROM configuration_universite LIMIT 1";
$stmt = $connexion->prepare($query);
$stmt->execute();
$configUniversite = $stmt->fetch(PDO::FETCH_ASSOC);

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Travaux de Recherche');

// Définir une largeur fixe pour la colonne B
$sheet->getColumnDimension('B')->setWidth(50);

// Activer le retour à la ligne automatique pour la colonne B
$sheet->getStyle('B')->getAlignment()->setWrapText(true);

// Configurer la page en mode paysage et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// Ajouter le logo si disponible
if ($configUniversite && !empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/uploads/logos/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de l\'université');
        $drawing->setPath($logoPath);
        $drawing->setHeight(80);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);
    }
}

// Styles pour les différents niveaux
$styleTitle = [
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSubtitle = [
    'font' => ['bold' => true, 'size' => 12],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleHeader = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];

// Nom de l'université
if ($configUniversite) {
    $sheet->setCellValue('C1', strtoupper($configUniversite['nom']));
    $sheet->mergeCells('C1:G1');
    $sheet->getStyle('C1:G1')->applyFromArray($styleTitle);
}

// Titre du document
$sheet->setCellValue('A3', 'LISTE DES TRAVAUX DE RECHERCHE');
$sheet->mergeCells('A3:G3');
$sheet->getStyle('A3:G3')->applyFromArray($styleTitle);
$sheet->getRowDimension(3)->setRowHeight(30);

// Informations sur l'enseignant et l'année académique
$nomComplet = $enseignant['noms'];
if (!empty($enseignant['grade'])) {
    $nomComplet = $enseignant['grade'] . ' ' . $nomComplet;
}
$sheet->setCellValue('A5', 'Enseignant: ' . $nomComplet);
$sheet->mergeCells('A5:G5');
$sheet->getStyle('A5:G5')->applyFromArray($styleSubtitle);

$sheet->setCellValue('A6', 'Année Académique: ' . $anneeInfo['designation']);
$sheet->mergeCells('A6:G6');
$sheet->getStyle('A6:G6')->applyFromArray($styleSubtitle);

// Statistiques
$totalTravaux = count($travaux);
$roleDirecteur = 0;
$roleEncadreur = 0;
$roleBoth = 0;

foreach ($travaux as $travail) {
    if ($travail['idDirecteur'] == $enseignantId && $travail['idEncadreur'] == $enseignantId) {
        $roleBoth++;
    } elseif ($travail['idDirecteur'] == $enseignantId) {
        $roleDirecteur++;
    } elseif ($travail['idEncadreur'] == $enseignantId) {
        $roleEncadreur++;
    }
}

$sheet->setCellValue('A8', 'STATISTIQUES:');
$sheet->getStyle('A8')->applyFromArray(['font' => ['bold' => true]]);

$sheet->setCellValue('A9', 'Total des travaux: ' . $totalTravaux);
$sheet->setCellValue('A10', 'En tant que Directeur uniquement: ' . $roleDirecteur);
$sheet->setCellValue('A11', 'En tant que Co-encadreur uniquement: ' . $roleEncadreur);
$sheet->setCellValue('A12', 'En tant que Directeur et Co-encadreur: ' . $roleBoth);

// En-têtes des colonnes
$row = 14;
$sheet->setCellValue('A' . $row, 'N°');
$sheet->setCellValue('B' . $row, 'Intitulé du sujet');
$sheet->setCellValue('C' . $row, 'Étudiant');
$sheet->setCellValue('D' . $row, 'Promotion');
$sheet->setCellValue('E' . $row, 'Section');
$sheet->setCellValue('F' . $row, 'Rôle');
$sheet->setCellValue('G' . $row, 'État');
$sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleHeader);

// Remplir les données
$row++;
$count = 1;

// Grouper les travaux par promotion
$travauxByPromotion = [];
foreach ($travaux as $travail) {
    $promotion = $travail['promotion'] ?? 'Non définie';
    if (!isset($travauxByPromotion[$promotion])) {
        $travauxByPromotion[$promotion] = [];
    }
    $travauxByPromotion[$promotion][] = $travail;
}

// Parcourir les travaux groupés par promotion
foreach ($travauxByPromotion as $promotion => $travauxPromotion) {
    // Ajouter un séparateur de promotion
    $sheet->setCellValue('A' . $row, $promotion);
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $row++;
    
    foreach ($travauxPromotion as $travail) {
        $role = [];
        if ($travail['idDirecteur'] == $enseignantId) {
            $role[] = 'Directeur';
        }
        if ($travail['idEncadreur'] == $enseignantId) {
            $role[] = 'Co-encadreur';
        }
        $roleText = implode(' & ', $role);
        
        $etudiantInfo = $travail['etudiant'];
        if (!empty($travail['matricule_etudiant'])) {
            $etudiantInfo .= ' (' . $travail['matricule_etudiant'] . ')';
        }

        $sheet->setCellValue('A' . $row, $count);
        $sheet->setCellValue('B' . $row, $travail['intitule']);
        $sheet->setCellValue('C' . $row, $etudiantInfo);
        $sheet->setCellValue('D' . $row, $travail['promotion']);
        $sheet->setCellValue('E' . $row, $travail['section'] ?? 'Non définie');
        $sheet->setCellValue('F' . $row, $roleText);
        $sheet->setCellValue('G' . $row, $travail['etatSujet'] ?? 'En cours');

        // Appliquer le style aux données
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($styleData);
        
        // Centrer le numéro et le rôle
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        $count++;
    }
}

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(15);

// Ajouter la date de génération
$row += 2;
$sheet->setCellValue('A' . $row, 'Document généré le ' . date('d/m/Y à H:i'));
$sheet->mergeCells('A' . $row . ':C' . $row);
$sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
    'font' => ['italic' => true, 'size' => 10],
]);

// Note sur les restrictions si applicable
if (!$isAdmin && !empty($authorizedSections)) {
    $row++;
    $sheet->setCellValue('A' . $row, 'Note: Ce document contient uniquement les travaux des sections dont vous êtes responsable.');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '666666']],
    ]);
}

// Créer le nom du fichier
$fileName = 'Travaux_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $enseignant['noms']) . '_' . 
            str_replace(' ', '_', $anneeInfo['designation']) . '_' . 
            date('Y-m-d_H-i-s') . '.xlsx';

// Définir les en-têtes HTTP pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Créer le writer et envoyer le fichier au navigateur
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>