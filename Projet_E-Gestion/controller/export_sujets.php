<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Drawing\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Vérification d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['annee_export'])) {
    header('Location: ../?view=recherche/affectation');
    exit;
}

// Initialiser la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$anneeId = intval($_POST['annee_export']);

// Récupérer les informations de l'année académique
$query = "SELECT * FROM annee_acad WHERE idannee_acad = :anneeId";
$stmt = $connexion->prepare($query);
$stmt->bindParam(':anneeId', $anneeId);
$stmt->execute();
$anneeInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anneeInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Année académique non trouvée.'
        }).then(() => {
            window.location.href = '../?view=recherche/affectation';
        });
    </script>";
    exit;
}

// Récupérer tous les filtres d'export
$statusExport = isset($_POST['statut_export']) && !empty($_POST['statut_export']) ? $_POST['statut_export'] : null;
$cycleExport = isset($_POST['cycle_export']) && !empty($_POST['cycle_export']) ? $_POST['cycle_export'] : null;
$specialisationExport = isset($_POST['specialisation_export']) && !empty($_POST['specialisation_export']) ? $_POST['specialisation_export'] : null;
$affectationExport = isset($_POST['affectation_export']) && !empty($_POST['affectation_export']) ? $_POST['affectation_export'] : null;

// Vérifier si l'utilisateur est un administrateur
$isAdmin = ($_SESSION['idRole'] == 1);

// Variable pour stocker les sections autorisées
$authorizedSections = [];
$sectionNames = []; // Pour le titre du document

// Si c'est un administrateur, il a accès à toutes les sections
if ($isAdmin) {
    if (isset($_POST['section_export']) && !empty($_POST['section_export'])) {
        // Si l'administrateur a sélectionné des sections spécifiques
        $selectedSections = is_array($_POST['section_export']) ? $_POST['section_export'] : [$_POST['section_export']];
        $authorizedSections = array_map('intval', array_filter($selectedSections));
        
        // Récupérer les noms des sections pour le titre
        if (!empty($authorizedSections)) {
            $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
            $query = "SELECT * FROM section WHERE idsection IN ($placeholders)";
            $stmt = $connexion->prepare($query);
            $stmt->execute($authorizedSections);
            $sectionsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sectionsData as $section) {
                $sectionNames[] = $section['designationSection'];
            }
        }
    } else {
        // Pour l'administrateur sans filtre de section, ne pas filtrer par section
        // Cela permettra de récupérer TOUS les sujets sans restriction
        $authorizedSections = []; // Tableau vide = pas de filtre de section
        $sectionNames = ["Toutes les sections"];
    }
} else {
    // Pour les autres utilisateurs, vérifier les sections dont ils sont responsables
    $userId = $_SESSION['id'];
    
    // Récupérer les sections dont l'utilisateur est responsable pour l'année sélectionnée
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE \"idUser\" = :userId AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':userId', $userId);
    $stmt->bindParam(':anneeId', $anneeId); // Utiliser l'année sélectionnée, pas forcément l'année courante
    $stmt->execute();
    $userSections = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    
    if (empty($userSections)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes responsable d\'aucune section pour cette année académique.'
            }).then(() => {
                window.location.href = '../?view=recherche/choix_etudiant';
            });
        </script>";
        exit;
    }
    
    // Si l'utilisateur a sélectionné des sections spécifiques parmi ses sections autorisées
    if (isset($_POST['section_export']) && !empty($_POST['section_export'])) {
        $selectedSections = is_array($_POST['section_export']) ? $_POST['section_export'] : [$_POST['section_export']];
        $selectedSections = array_map('intval', array_filter($selectedSections));
        
        // Vérifier que les sections sélectionnées sont autorisées
        $authorizedSections = array_intersect($selectedSections, $userSections);
        
        if (empty($authorizedSections)) {
            // Si aucune section autorisée sélectionnée, utiliser toutes les sections de l'utilisateur
            $authorizedSections = $userSections;
        }
    } else {
        // Utiliser toutes les sections autorisées pour cet utilisateur
        $authorizedSections = $userSections;
    }
    
    // Récupérer les noms des sections pour le titre
    if (!empty($authorizedSections)) {
        $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
        $query = "SELECT * FROM section WHERE idsection IN ($placeholders)";
        $stmt = $connexion->prepare($query);
        $stmt->execute($authorizedSections);
        $sectionsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sectionsData as $section) {
            $sectionNames[] = $section['designationSection'];
        }
    }
}

// Récupérer la configuration de l'université
$query = "SELECT * FROM configuration_universite LIMIT 1";
$stmt = $connexion->prepare($query);
$stmt->execute();
$configUniversite = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les sujets pour l'année sélectionnée, filtré par sections autorisées et statut optionnel
$sujets = [];

// Construction de la requête de base
$query = "SELECT s.*, 
        s.resume as resume,
        a.designation as annee, 
        e.noms as etudiant,
        e.matricule as matricule_etudiant,
        d.noms as directeur,
        gr_d.designation as grade_directeur,
        enc.noms as encadreur,
        gr_e.designation as grade_encadreur,
        spec.designation as specialisation,
        o.\"designationOrientation\" as orientation,
        sec.\"designationSection\" as section
     FROM sujets s
     LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
     LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
     LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
     LEFT JOIN grade gr_d ON d.grade_id = gr_d.idgrade
     LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
     LEFT JOIN grade gr_e ON enc.grade_id = gr_e.idgrade
     LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
     LEFT JOIN orientation o ON spec.idorientation = o.idorientation
     LEFT JOIN section sec ON o.section_idsection = sec.idsection
     WHERE s.annee_acad_idannee_acad = ?";

$params = [$anneeId];

// Si des sections sont spécifiées (non-admin ou admin avec sélection), filtrer par sections
if (!empty($authorizedSections)) {
    $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
    $query .= " AND o.section_idsection IN ($placeholders)";
    $params = array_merge($params, $authorizedSections);
}

// Ajouter les filtres supplémentaires
if ($statusExport) {
    $query .= " AND s.statut_validation = ?";
    $params[] = $statusExport;
}

if ($cycleExport) {
    $query .= " AND s.cycle = ?";
    $params[] = $cycleExport;
}

if ($specialisationExport) {
    $query .= " AND s.idSpecialisation = ?";
    $params[] = $specialisationExport;
}

$query .= " ORDER BY spec.designation, s.intitule";

$stmt = $connexion->prepare($query);
$stmt->execute($params);
$sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Appliquer le filtre d'affectation après récupération des données (comme dans affectation.php)
if ($affectationExport && !empty($sujets)) {
    $filteredSujets = [];
    foreach ($sujets as $sujet) {
        $hasEtudiant = !empty($sujet['etudiant_idetudiant']);
        $hasDirecteur = !empty($sujet['idDirecteur']);
        $hasEncadreur = !empty($sujet['idEncadreur']);
        
        $includeSubject = false;
        
        switch ($affectationExport) {
            case 'avec_etudiant':
                $includeSubject = $hasEtudiant;
                break;
            case 'sans_etudiant':
                $includeSubject = !$hasEtudiant;
                break;
            case 'avec_directeur':
                $includeSubject = $hasDirecteur;
                break;
            case 'sans_directeur':
                $includeSubject = !$hasDirecteur;
                break;
            case 'avec_encadreur':
                $includeSubject = $hasEncadreur;
                break;
            case 'sans_encadreur':
                $includeSubject = !$hasEncadreur;
                break;
            case 'complet':
                $includeSubject = $hasEtudiant && $hasDirecteur && $hasEncadreur;
                break;
            case 'incomplet':
                $includeSubject = !($hasEtudiant && $hasDirecteur && $hasEncadreur);
                break;
            default:
                $includeSubject = true;
        }
        
        if ($includeSubject) {
            $filteredSujets[] = $sujet;
        }
    }
    $sujets = $filteredSujets;
}

// Récupérer les statistiques des sujets
$statistiques = [
    'total' => 0,
    'commission_valides' => 0,
    'commission_en_attente' => 0,
    'commission_rejetes' => 0,
    'commission_modifies' => 0
];

// Construction des requêtes de statistiques avec tous les filtres appliqués
$baseQuery = "SELECT COUNT(*) as total FROM sujets s
              JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              JOIN orientation o ON spec.idorientation = o.idorientation
              WHERE s.annee_acad_idannee_acad = ?";

$baseParams = [$anneeId];

// Appliquer les mêmes filtres que pour la requête principale
if (!empty($authorizedSections)) {
    $placeholders = implode(',', array_fill(0, count($authorizedSections), '?'));
    $baseQuery .= " AND o.section_idsection IN ($placeholders)";
    $baseParams = array_merge($baseParams, $authorizedSections);
}

if ($cycleExport) {
    $baseQuery .= " AND s.cycle = ?";
    $baseParams[] = $cycleExport;
}

if ($specialisationExport) {
    $baseQuery .= " AND s.idSpecialisation = ?";
    $baseParams[] = $specialisationExport;
}

// Pour les statistiques, nous utilisons les données filtrées directement
// car le filtre d'affectation ne peut pas être appliqué en SQL
$statistiques['total'] = count($sujets);

// Calculer les statistiques par statut à partir des données filtrées
$statistiques['commission_valides'] = 0;
$statistiques['commission_en_attente'] = 0;
$statistiques['commission_rejetes'] = 0;
$statistiques['commission_modifies'] = 0;

foreach ($sujets as $sujet) {
    switch ($sujet['statut_validation']) {
        case 'Validé':
            $statistiques['commission_valides']++;
            break;
        case 'En attente':
            $statistiques['commission_en_attente']++;
            break;
        case 'Rejeté':
        case 'A reformulé':
            $statistiques['commission_rejetes']++;
            break;
        case 'Modifié':
            $statistiques['commission_modifies']++;
            break;
    }
}

// Grouper les sujets par spécialisation
$sujetsBySpecialisation = [];
foreach ($sujets as $sujet) {
    $specialisation = $sujet['specialisation'];
    if (!isset($sujetsBySpecialisation[$specialisation])) {
        $sujetsBySpecialisation[$specialisation] = [];
    }
    $sujetsBySpecialisation[$specialisation][] = $sujet;
}

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sujets de Recherche');

// Configurer la page en mode paysage et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);

// Définir les styles de base
$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$dataStyleCenter = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];

$subtitleStyle = [
    'font' => [
        'bold' => true,
        'size' => 14,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D9E1F2'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

$statistiqueStyle = [
    'font' => [
        'bold' => true,
        'size' => 11,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F2F2F2'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];

// Ajouter le logo si disponible
if ($configUniversite && !empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/uploads/logos/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('A1');
        $drawing->setWidth(100);
        $drawing->setHeight(100);
        $drawing->setWorksheet($sheet);
    }
}

// Titre de l'université
$sheet->setCellValue('C1', $configUniversite ? strtoupper($configUniversite['nom']) : 'UNIVERSITÉ');
$sheet->mergeCells('C1:H1');
$sheet->getStyle('C1:H1')->applyFromArray($titleStyle);

// Sous-titre avec la section et l'année académique
$sectionTitle = count($sectionNames) > 0 ? implode(', ', $sectionNames) : 'TOUTES LES SECTIONS';
$sheet->setCellValue('C2', $sectionTitle);
$sheet->mergeCells('C2:H2');
$sheet->getStyle('C2:H2')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
]);

// Année académique
$sheet->setCellValue('C3', 'ANNÉE ACADÉMIQUE: ' . $anneeInfo['designation']);
$sheet->mergeCells('C3:H3');
$sheet->getStyle('C3:H3')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 12,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
]);

// Titre du document
$titre = 'LISTE DES SUJETS DE RECHERCHE';
if ($statusExport) {
    $titre .= ' - ' . strtoupper($statusExport);
}
$sheet->setCellValue('C4', $titre);
$sheet->mergeCells('C4:H4');
$sheet->getStyle('C4:H4')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 14,
        'color' => ['rgb' => '000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
]);

// Ajouter les filtres appliqués
$row = 5;
$filtresAppliques = [];
if (!empty($anneeInfo['designation'])) {
    $filtresAppliques[] = "Année académique: " . $anneeInfo['designation'];
}
if (!empty($sectionNames)) {
    $filtresAppliques[] = "Section: " . implode(', ', $sectionNames);
}
if ($cycleExport) {
    $cycleLabel = $cycleExport == 'Premier' ? 'Licence' : ($cycleExport == 'Deuxieme' ? 'Master' : 'Doctorat');
    $filtresAppliques[] = "Cycle: $cycleLabel";
}
if ($specialisationExport) {
    // Récupérer le nom de la spécialisation
    $querySpec = "SELECT designation FROM specialisation WHERE \"idSpecialisation\" = ?";
    $stmtSpec = $connexion->prepare($querySpec);
    $stmtSpec->execute([$specialisationExport]);
    $specName = $stmtSpec->fetch(PDO::FETCH_ASSOC);
    if ($specName) {
        $filtresAppliques[] = "Spécialisation: " . $specName['designation'];
    }
}
if ($statusExport) {
    $filtresAppliques[] = "Statut: $statusExport";
}
if ($affectationExport) {
    $affectationLabels = [
        'avec_etudiant' => 'Avec étudiant assigné',
        'sans_etudiant' => 'Sans étudiant assigné',
        'avec_directeur' => 'Avec directeur assigné',
        'sans_directeur' => 'Sans directeur assigné',
        'avec_encadreur' => 'Avec encadreur assigné',
        'sans_encadreur' => 'Sans encadreur assigné',
        'complet' => 'Complètement affecté',
        'incomplet' => 'Affectation incomplète'
    ];
    $filtresAppliques[] = "Affectation: " . $affectationLabels[$affectationExport];
}

if (!empty($filtresAppliques)) {
    $sheet->setCellValue('C' . $row, 'FILTRES APPLIQUÉS: ' . implode(' • ', $filtresAppliques));
    $sheet->mergeCells('C' . $row . ':H' . $row);
    $sheet->getStyle('C' . $row . ':H' . $row)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 10,
            'italic' => true,
            'color' => ['rgb' => '666666'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
    ]);
    $row++;
}

// Statistiques (filtrées par sections autorisées)
$row = !empty($filtresAppliques) ? 7 : 6;
$statsTitle = 'STATISTIQUES';
if (!$isAdmin && !empty($userSections)) {
    $statsTitle .= ' (Sections autorisées uniquement)';
} elseif ($isAdmin && !empty($authorizedSections)) {
    // Pour l'admin, comparer avec toutes les sections de la base
    $queryAllSections = "SELECT COUNT(*) as total FROM section";
    $stmtAllSections = $connexion->prepare($queryAllSections);
    $stmtAllSections->execute();
    $totalSections = $stmtAllSections->fetch(PDO::FETCH_ASSOC)['total'];
    
    if (count($authorizedSections) < $totalSections) {
        $statsTitle .= ' (Sections sélectionnées)';
    }
}
$sheet->setCellValue('B' . $row, $statsTitle . ':');
$sheet->mergeCells('B' . $row . ':D' . $row);
$sheet->getStyle('B' . $row . ':D' . $row)->applyFromArray($statistiqueStyle);

$row++;
$sheet->setCellValue('B' . $row, 'Total des sujets:');
$sheet->setCellValue('C' . $row, $statistiques['total']);
$sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($statistiqueStyle);

$row++;
$sheet->setCellValue('B' . $row, 'Sujets validés:');
$sheet->setCellValue('C' . $row, $statistiques['commission_valides']);
$sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($statistiqueStyle);

$row++;
$sheet->setCellValue('B' . $row, 'Sujets en attente:');
$sheet->setCellValue('C' . $row, $statistiques['commission_en_attente']);
$sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($statistiqueStyle);

$row++;
$sheet->setCellValue('B' . $row, 'Sujets rejetés:');
$sheet->setCellValue('C' . $row, $statistiques['commission_rejetes']);
$sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($statistiqueStyle);

$row++;
$sheet->setCellValue('B' . $row, 'Sujets modifiés:');
$sheet->setCellValue('C' . $row, $statistiques['commission_modifies']);
$sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray($statistiqueStyle);

$row += 2;

// Colonnes à exporter (si non fournies, utiliser les colonnes par défaut)
$selectedColumns = isset($_POST['colonnes']) && is_array($_POST['colonnes']) ? $_POST['colonnes'] : ['intitule', 'cycle', 'statut', 'etudiant', 'directeur', 'encadreur'];
$selectedColumns = array_values(array_unique(array_filter($selectedColumns)));
if (empty($selectedColumns)) {
    $selectedColumns = ['intitule', 'cycle', 'statut', 'etudiant', 'directeur', 'encadreur'];
}

$columnMap = [
    'intitule' => ['header' => 'Intitulé du sujet', 'width' => 50],
    'cycle' => ['header' => 'Cycle', 'width' => 15],
    'statut' => ['header' => 'État', 'width' => 15],
    'etudiant' => ['header' => 'Étudiant', 'width' => 30],
    'directeur' => ['header' => 'Directeur', 'width' => 30],
    'encadreur' => ['header' => 'Encadreur', 'width' => 30],
    'annee' => ['header' => 'Année académique', 'width' => 22],
    'specialisation' => ['header' => 'Spécialisation', 'width' => 35],
    'section' => ['header' => 'Section', 'width' => 25],
    'unite_recherche' => ['header' => 'Unité de recherche', 'width' => 30],
    'resume' => ['header' => 'Introduction / Problématique', 'width' => 60],
];

$exportColumns = array_values(array_filter($selectedColumns, function ($key) use ($columnMap) {
    return isset($columnMap[$key]);
}));

if (empty($exportColumns)) {
    $exportColumns = ['intitule', 'cycle', 'statut', 'etudiant', 'directeur', 'encadreur'];
}

$headers = ['N°'];
$colWidths = [8];
foreach ($exportColumns as $columnKey) {
    $headers[] = $columnMap[$columnKey]['header'];
    $colWidths[] = $columnMap[$columnKey]['width'];
}

// Définir les largeurs des colonnes
foreach ($colWidths as $i => $width) {
    $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
}

$globalCounter = 1;

// Si aucun sujet trouvé
$lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

if (empty($sujetsBySpecialisation)) {
    $sheet->setCellValue('B' . $row, 'Aucun sujet trouvé pour les critères sélectionnés.');
    $sheet->mergeCells('B' . $row . ':' . $lastColumnLetter . $row);
    $sheet->getStyle('B' . $row . ':' . $lastColumnLetter . $row)->applyFromArray([
        'font' => ['bold' => true, 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $row++;
} else {
    // Parcourir chaque spécialisation et ajouter ses sujets
    foreach ($sujetsBySpecialisation as $specialisation => $specialisationSujets) {
        // Titre de la spécialisation
        $sheet->setCellValue('B' . $row, $specialisation);
        $sheet->mergeCells('B' . $row . ':' . $lastColumnLetter . $row);
        $sheet->getStyle('B' . $row . ':' . $lastColumnLetter . $row)->applyFromArray($subtitleStyle);
        $row++;

        // En-têtes des colonnes
        $lastColumnIndex = count($headers);
        $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColumnIndex);
        for ($i = 0; $i < count($headers); $i++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($columnLetter . $row, $headers[$i]);
        }
        $sheet->getStyle('A' . $row . ':' . $lastColumnLetter . $row)->applyFromArray($headerStyle);
        $row++;

        // Données des sujets
        $counter = 1;
        foreach ($specialisationSujets as $sujet) {
            // Formater les informations sur l'étudiant
            $etudiantInfo = !empty($sujet['etudiant']) ? 
                $sujet['etudiant'] . (!empty($sujet['matricule_etudiant']) ? ' (' . $sujet['matricule_etudiant'] . ')' : '') : 
                'Non assigné';

            // Formater les informations sur le directeur
            $directeurInfo = !empty($sujet['directeur']) ? 
                (!empty($sujet['grade_directeur']) ? $sujet['grade_directeur'] . ' ' : '') . $sujet['directeur'] : 
                'Non assigné';

            // Formater les informations sur l'encadreur
            $encadreurInfo = !empty($sujet['encadreur']) ? 
                (!empty($sujet['grade_encadreur']) ? $sujet['grade_encadreur'] . ' ' : '') . $sujet['encadreur'] : 
                'Non assigné';

            // Déterminer les cycles
            $cycle = '';
            switch ($sujet['cycle']) {
                case 'Premier':
                    $cycle = 'Licence';
                    break;
                case 'Deuxieme':
                    $cycle = 'Master';
                    break;
                case 'Troisieme':
                    $cycle = 'Doctorat';
                    break;
                default:
                    $cycle = $sujet['cycle'];
            }

            $rowValues = [
                'intitule' => $sujet['intitule'],
                'cycle' => $cycle,
                'statut' => $sujet['statut_validation'],
                'etudiant' => $etudiantInfo,
                'directeur' => $directeurInfo,
                'encadreur' => $encadreurInfo,
                'annee' => $sujet['annee'] ?? '',
                'specialisation' => $sujet['specialisation'] ?? '',
                'section' => $sujet['section'] ?? '',
                'unite_recherche' => $sujet['orientation'] ?? '',
                'resume' => $sujet['resume'] ?? '',
            ];

            // Données des cellules
            $sheet->setCellValue('A' . $row, $counter);
            $sheet->getStyle('A' . $row)->applyFromArray($dataStyleCenter);

            $statusColumnLetter = null;
            $currentColumnIndex = 2;
            foreach ($exportColumns as $columnKey) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColumnIndex);
                $sheet->setCellValue($columnLetter . $row, $rowValues[$columnKey] ?? '');
                $sheet->getStyle($columnLetter . $row)->applyFromArray($dataStyle);

                if ($columnKey === 'statut') {
                    $statusColumnLetter = $columnLetter;
                }

                $currentColumnIndex++;
            }

            // Colorier la cellule d'état selon le statut
            if ($statusColumnLetter !== null) {
                switch ($sujet['statut_validation']) {
                    case 'Validé':
                        $sheet->getStyle($statusColumnLetter . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'C6EFCE'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                        break;
                    case 'En attente':
                        $sheet->getStyle($statusColumnLetter . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFEB9C'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                        break;
                    case 'Rejeté':
                        $sheet->getStyle($statusColumnLetter . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFC7CE'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                        break;
                    case 'Modifié':
                        $sheet->getStyle($statusColumnLetter . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D9E1F2'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                        break;
                }
            }

            $row++;
            $counter++;
            $globalCounter++;
        }

        // Ajouter une ligne vide entre les spécialisations
        $row++;
    }
}
// Ajouter la date d'export
$row += 2;
$sheet->setCellValue('B' . $row, 'Document généré le ' . date('d/m/Y à H:i'));
$sheet->mergeCells('B' . $row . ':D' . $row);
$sheet->getStyle('B' . $row . ':D' . $row)->applyFromArray([
    'font' => ['italic' => true],
]);

// Nom du fichier
$filename = 'Sujets_Recherche_' . str_replace(' ', '_', $anneeInfo['designation']);
if ($statusExport) {
    $filename .= '_' . str_replace(' ', '_', $statusExport);
}
$filename .= '_' . date('Y-m-d_H-i-s') . '.xlsx';

// Définir les headers pour le téléchargement
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Créer l'objet Writer pour écrire le document Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
