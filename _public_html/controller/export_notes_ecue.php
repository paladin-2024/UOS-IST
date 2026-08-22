<?php
session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Ecue.php';
require_once dirname(__DIR__) . '/models/Enseignant.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Vérifications d'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['idECUE'])) {
    header('Location: ../?view=enseignement/cours');
    exit;
}

$universite = new Universite();
$ecue = new Ecue();
$enseignant = new Enseignant();

$idEcue = intval($_POST['idECUE']);

// Récupérer l'ID de l'utilisateur et vérifier s'il est un enseignant
$userId = $_SESSION['id'];
$idEnseignant = $enseignant->getAgentIdByUserId($userId);

if (!$idEnseignant || !$enseignant->isUserEnseignant($userId)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'êtes pas autorisé à accéder à cette ressource.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Vérifier que l'enseignant est autorisé à accéder à cet ECUE
if (!$enseignant->isEnseignantAssignedToEcue($idEnseignant, $idEcue, $currentYear['idannee_acad'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'êtes pas autorisé à accéder aux évaluations de ce cours.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer les détails de l'ECUE
$ecueDetails = $ecue->getEcueById($idEcue);
if (!$ecueDetails) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'ECUE non trouvé.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer toutes les évaluations pour cet ECUE
$evaluations = $ecue->getEvaluationsByEcue($idEcue, $currentYear['idannee_acad']);
if (empty($evaluations)) {
    echo "<script>
        Swal.fire({
            icon: 'info',
            title: 'Information',
            text: 'Aucune évaluation n\'a été trouvée pour ce cours.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer la liste des étudiants inscrits à ce cours
$etudiants = $ecue->getStudentsByEcue($idEcue, $currentYear['idannee_acad']);
if (empty($etudiants)) {
    echo "<script>
        Swal.fire({
            icon: 'info',
            title: 'Information',
            text: 'Aucun étudiant n\'est inscrit à ce cours.'
        }).then(() => {
            window.history.back();
        });
    </script>";
    exit;
}

// Récupérer la configuration des moyennes
$configMoyenne = $ecue->getConfigurationMoyenne($idEcue, $currentYear['idannee_acad']);

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Créer un nouveau document Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notes ' . substr($ecueDetails['designationECUE'], 0, 20));

// Configurer la page en mode paysage et ajuster à 1 page de largeur
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0); // 0 = autant de pages que nécessaire en hauteur

// Styles pour les différents niveaux
$styleTitle = [
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleUniversite = [
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleSubtitle = [
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
];

$styleHeader = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleSessionHeader = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B4C6E7']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
];

$styleData = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
];

// Ajouter le logo si disponible
$row = 1;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        // Insérer le logo dans le document Excel
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de l\'université');
        $drawing->setPath($logoPath);
        
        // Centrer le logo
        $drawing->setCoordinates('D1'); // Colonne centrale (ajuster selon le nombre de colonnes)
        $drawing->setOffsetX(30); // Ajuster pour centrer parfaitement
        $drawing->setHeight(80); // Ajustez la hauteur selon vos besoins
        $drawing->setWorksheet($sheet);
        
        // Ajuster la hauteur de la ligne pour le logo
        $sheet->getRowDimension(1)->setRowHeight(60);
        
        // Décaler les autres informations
        $row = 4;
    }
}

// Ajouter les informations d'en-tête
$sheet->setCellValue('A' . $row, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'));
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->applyFromArray($styleUniversite);
$row++;

$sheet->setCellValue('A' . $row, 'FICHE DE NOTES - ' . strtoupper($ecueDetails['designationECUE']));
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->applyFromArray($styleTitle);
$row++;

$sheet->setCellValue('A' . $row, 'Année académique: ' . ($currentYear['designation'] ?? ''));
$sheet->mergeCells('A' . $row . ':I' . $row);
$sheet->getStyle('A' . $row)->applyFromArray([
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$row++;

// Informations sur l'ECUE
$row++;
$sheet->setCellValue('A' . $row, 'UE: ' . ($ecueDetails['designationUE'] ?? ''));
$sheet->setCellValue('D' . $row, 'Promotion: ' . ($ecueDetails['designationPromotion'] ?? ''));
$sheet->setCellValue('G' . $row, 'Semestre: ' . ($ecueDetails['numeroSemestre'] ?? ''));
$row++;

// Récupérer les cotes compilées à la place des notes individuelles
$cotesGrille = $ecue->getCotesGrille($idEcue, $currentYear['idannee_acad']);

// Récupérer les sessions
$sessions = $ecue->getSessionsByEcue($idEcue, $currentYear['idannee_acad']);

// En-têtes du tableau
$sheet->setCellValue('A7', 'N°');
$sheet->setCellValue('B7', 'Matricule');
$sheet->setCellValue('C7', 'Noms et prénoms');

// En-tête principale pour la première session
$sheet->setCellValue('D6', 'PREMIÈRE SESSION');
$sheet->mergeCells('D6:F6');
$sheet->getStyle('D6:F6')->applyFromArray($styleSessionHeader);

// Sous-en-têtes pour la première session
$sheet->setCellValue('D7', 'CC S1');
$sheet->setCellValue('E7', 'EX S1');
$sheet->setCellValue('F7', 'MOY S1');

// En-tête principale pour la deuxième session
$sheet->setCellValue('G6', 'DEUXIÈME SESSION');
$sheet->mergeCells('G6:I6');
$sheet->getStyle('G6:I6')->applyFromArray($styleSessionHeader);

// Sous-en-têtes pour la deuxième session
$sheet->setCellValue('G7', 'EX S2');
$sheet->setCellValue('H7', 'MOY S2');

// Appliquer le style aux en-têtes
$sheet->getStyle('A7:H7')->applyFromArray($styleHeader);

// Trouver les IDs des sessions
$premiereSessionId = null;
$deuxiemeSessionId = null;

foreach ($sessions as $session) {
    if (stripos($session['designSession'], 'première') !== false || 
        stripos($session['designSession'], 'premiere') !== false) {
        $premiereSessionId = $session['idsession'];
    } elseif (stripos($session['designSession'], 'deuxième') !== false || 
              stripos($session['designSession'], 'deuxieme') !== false) {
        $deuxiemeSessionId = $session['idsession'];
    }
}

// Données des étudiants
$rowIndex = 8;
foreach ($etudiants as $index => $etudiant) {
    // Numéro, matricule et nom
    $sheet->setCellValue('A' . $rowIndex, $index + 1);
    $sheet->setCellValue('B' . $rowIndex, $etudiant['matricule']);
    $sheet->setCellValue('C' . $rowIndex, $etudiant['noms']);
    
    // Filtrer les cotes pour cet étudiant
    $cotesEtudiant = array_filter($cotesGrille, function($c) use ($etudiant) {
        return $c['matricule'] === $etudiant['matricule'];
    });
    
    // Notes de première session
    $cotePremiereSession = null;
    foreach ($cotesEtudiant as $cote) {
        if (isset($cote['session_idsession']) && $cote['session_idsession'] == $premiereSessionId) {
            $cotePremiereSession = $cote;
            break;
        }
    }
    
    if ($cotePremiereSession) {
        $sheet->setCellValue('D' . $rowIndex, is_numeric($cotePremiereSession['CC']) ? number_format($cotePremiereSession['CC'], 2) : '');
        $sheet->setCellValue('E' . $rowIndex, is_numeric($cotePremiereSession['EX']) ? number_format($cotePremiereSession['EX'], 2) : '');
        $sheet->setCellValue('F' . $rowIndex, is_numeric($cotePremiereSession['MF']) ? number_format($cotePremiereSession['MF'], 2) : '');
    }
    
    // Notes de deuxième session
    $coteDeuxiemeSession = null;
    foreach ($cotesEtudiant as $cote) {
        if (isset($cote['session_idsession']) && $cote['session_idsession'] == $deuxiemeSessionId) {
            $coteDeuxiemeSession = $cote;
            break;
        }
    }
    
    if ($coteDeuxiemeSession) {
        $sheet->setCellValue('G' . $rowIndex, is_numeric($coteDeuxiemeSession['EX']) ? number_format($coteDeuxiemeSession['EX'], 2) : '');
        $sheet->setCellValue('H' . $rowIndex, is_numeric($coteDeuxiemeSession['MF']) ? number_format($coteDeuxiemeSession['MF'], 2) : '');
        
        // Note finale (MF de deuxième session ou MF de première session si supérieure)
        $notePremiere = $cotePremiereSession && is_numeric($cotePremiereSession['MF']) ? $cotePremiereSession['MF'] : 0;
        $noteDeuxieme = is_numeric($coteDeuxiemeSession['MF']) ? $coteDeuxiemeSession['MF'] : 0;
        $noteFinale = max($notePremiere, $noteDeuxieme);
        
        
    } else if ($cotePremiereSession && is_numeric($cotePremiereSession['MF'])) {
        // Si pas de deuxième session, la note finale est celle de la première session
        //$sheet->setCellValue('I' . $rowIndex, number_format($cotePremiereSession['MF'], 2));
    }
    
    // Appliquer le style aux données
    $sheet->getStyle('A' . $rowIndex . ':I' . $rowIndex)->applyFromArray($styleData);
    
    $rowIndex++;
}

// Ajuster automatiquement la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(5);     // N°
$sheet->getColumnDimension('B')->setWidth(15);    // Matricule
$sheet->getColumnDimension('C')->setWidth(30);    // Nom de l'étudiant
$sheet->getColumnDimension('D')->setWidth(10);    // CC 1ère session
$sheet->getColumnDimension('E')->setWidth(10);    // EX 1ère session
$sheet->getColumnDimension('F')->setWidth(10);    // MOY 1ère session
$sheet->getColumnDimension('G')->setWidth(10);    // EX 2ème session
$sheet->getColumnDimension('H')->setWidth(10);    // MOY 2ème session

// Ajouter des statistiques
$rowIndex += 2;
$sheet->setCellValue('A' . $rowIndex, 'STATISTIQUES:');
$sheet->mergeCells('A' . $rowIndex . ':I' . $rowIndex);
$sheet->getStyle('A' . $rowIndex)->applyFromArray([
    'font' => ['bold' => true, 'size' => 12],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
]);
$rowIndex++;

// Calculer les statistiques pour la première session
$totalEtudiants = count($etudiants);
$reussisPremiere = 0;
$reussisDeuxieme = 0;
$reussisTotal = 0;

foreach ($etudiants as $etudiant) {
    $cotesEtudiant = array_filter($cotesGrille, function($c) use ($etudiant) {
        return $c['matricule'] === $etudiant['matricule'];
    });
    
    $moyennePremiere = 0;
    $moyenneDeuxieme = 0;
    
    foreach ($cotesEtudiant as $cote) {
        if (isset($cote['session_idsession']) && $cote['session_idsession'] == $premiereSessionId && is_numeric($cote['MF'])) {
            $moyennePremiere = $cote['MF'];
            if ($moyennePremiere >= 10) {
                $reussisPremiere++;
            }
        } elseif (isset($cote['session_idsession']) && $cote['session_idsession'] == $deuxiemeSessionId && is_numeric($cote['MF'])) {
            $moyenneDeuxieme = $cote['MF'];
            if ($moyenneDeuxieme >= 10) {
                $reussisDeuxieme++;
            }
        }
    }
    
    // Compter les réussites totales (première session ou deuxième session)
    if ($moyennePremiere >= 10 || $moyenneDeuxieme >= 10) {
        $reussisTotal++;
    }
}

// Afficher les statistiques
$sheet->setCellValue('A' . $rowIndex, 'Total étudiants:');
$sheet->setCellValue('C' . $rowIndex, $totalEtudiants);
$rowIndex++;

$sheet->setCellValue('A' . $rowIndex, 'Réussite en première session:');
$sheet->setCellValue('C' . $rowIndex, $reussisPremiere);
$sheet->setCellValue('D' . $rowIndex, number_format(($totalEtudiants > 0 ? ($reussisPremiere / $totalEtudiants) * 100 : 0), 2) . '%');
$rowIndex++;

$sheet->setCellValue('A' . $rowIndex, 'Réussite en deuxième session:');
$sheet->setCellValue('C' . $rowIndex, $reussisDeuxieme);
$sheet->setCellValue('D' . $rowIndex, number_format(($totalEtudiants > 0 ? ($reussisDeuxieme / $totalEtudiants) * 100 : 0), 2) . '%');
$rowIndex++;

$sheet->setCellValue('A' . $rowIndex, 'Réussite totale:');
$sheet->setCellValue('C' . $rowIndex, $reussisTotal);
$sheet->setCellValue('D' . $rowIndex, number_format(($totalEtudiants > 0 ? ($reussisTotal / $totalEtudiants) * 100 : 0), 2) . '%');
$rowIndex++;

// Ajouter signature d'enseignant 
$rowIndex += 2;
$sheet->setCellValue('A' . $rowIndex, 'Date:');
$sheet->setCellValue('C' . $rowIndex, date('d/m/Y'));
$rowIndex += 3;

$sheet->setCellValue('A' . $rowIndex, 'Signature de l\'enseignant:');
$sheet->mergeCells('A' . $rowIndex . ':C' . $rowIndex);
$sheet->setCellValue('G' . $rowIndex, 'Signature du chef de département:');
$sheet->mergeCells('G' . $rowIndex . ':H' . $rowIndex);

// Définir le titre du document
$spreadsheet->getProperties()
    ->setCreator($configUniversite['nom'] ?? 'E-GESTION')
    ->setLastModifiedBy('Enseignant')
    ->setTitle('Notes ' . $ecueDetails['designationECUE'])
    ->setSubject('Fiche de notes')
    ->setDescription('Fiche de notes pour ' . $ecueDetails['designationECUE'])
    ->setKeywords('notes, ' . $ecueDetails['designationECUE'])
    ->setCategory('Notes académiques');

// Enregistrer le document Excel
$fileName = 'Notes_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ecueDetails['designationECUE']) . '_' . date('Ymd_His') . '.xlsx';
$writer = new Xlsx($spreadsheet);

// Envoyer le document au navigateur
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Assurez-vous que les buffers sont vides avant d'envoyer le fichier
ob_end_clean();  // Nécessaire pour éviter les erreurs de corruption de fichier
$writer->save('php://output');
exit;

