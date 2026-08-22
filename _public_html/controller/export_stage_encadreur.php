<?php
session_start();

require_once '../config/Connexion.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php?view=login');
    exit();
}

$pdo = Connexion::getInstance()->getPDO();
$userId = $_SESSION['id'];
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Récupérer l'ID de l'agent (enseignant)
$query = "SELECT a.idAgent, a.noms FROM agent a 
          INNER JOIN t_users u ON a.idAgent = u.idAgent 
          WHERE u.idUser = ? AND a.type_agent = 'Enseignant'";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enseignant) {
    die('Accès refusé');
}

$idEnseignant = $enseignant['idAgent'];

// Récupérer l'année académique
$queryYear = "SELECT designation FROM annee_acad WHERE idannee_acad = ?";
$stmtYear = $pdo->prepare($queryYear);
$stmtYear->execute([$anneeId]);
$annee = $stmtYear->fetch(PDO::FETCH_ASSOC);

if (!$annee) {
    die('Année académique non trouvée');
}

// Récupérer les étudiants encadrés
$queryEncadreur = "SELECT 
                    sa.idstage,
                    sa.idetudiant,
                    sa.lieu_stage,
                    sa.date_debut,
                    sa.date_fin,
                    sa.cote_entreprise,
                    sa.cote_lecteur,
                    e.noms as nom_etudiant,
                    e.matricule,
                    p.designationPromotion as promotion,
                    lect.noms as lecteur_nom
                   FROM stage_assignments sa
                   INNER JOIN etudiant e ON sa.idetudiant = e.idetudiant
                   INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   LEFT JOIN agent lect ON sa.idlecteur = lect.idAgent
                   WHERE sa.idencadreur = ?
                   AND e.annee_acad_idannee_acad = ?
                   ORDER BY e.noms";
$stmt = $pdo->prepare($queryEncadreur);
$stmt->execute([$idEnseignant, $anneeId]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer le fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Titre
$sheet->setCellValue('A1', 'LISTE DES ÉTUDIANTS ENCADRÉS EN STAGE');
$sheet->setCellValue('A2', 'Encadreur: ' . $enseignant['noms']);
$sheet->setCellValue('A3', 'Année Académique: ' . $annee['designation']);

// Style du titre
$sheet->getStyle('A1:A3')->getFont()->setBold(true);
$sheet->mergeCells('A1:J1');
$sheet->mergeCells('A2:J2');
$sheet->mergeCells('A3:J3');
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// En-têtes du tableau
$row = 5;
$headers = ['#', 'Matricule', 'Nom Étudiant', 'Promotion', 'Lieu de Stage', 'Lecteur', 'Note Encadreur', 'Note Lecteur', 'Moyenne', 'Statut'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . $row, $header);
    $col++;
}

// Style des en-têtes
$sheet->getStyle('A5:J5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
$sheet->getStyle('A5:J5')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setBold(true);
$sheet->getStyle('A5:J5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Données
$row = 6;
$i = 1;
foreach ($etudiants as $etudiant) {
    $moyenne = '';
    $statut = '-';
    
    if ($etudiant['cote_entreprise'] !== null && $etudiant['cote_lecteur'] !== null) {
        $moyenne = round(($etudiant['cote_entreprise'] + $etudiant['cote_lecteur']) / 2, 2);
        $statut = $moyenne >= 10 ? 'Réussi' : 'Échoué';
    }
    
    $sheet->setCellValue('A' . $row, $i);
    $sheet->setCellValue('B' . $row, $etudiant['matricule']);
    $sheet->setCellValue('C' . $row, $etudiant['nom_etudiant']);
    $sheet->setCellValue('D' . $row, $etudiant['promotion']);
    $sheet->setCellValue('E' . $row, $etudiant['lieu_stage'] ?? 'Non spécifié');
    $sheet->setCellValue('F' . $row, $etudiant['lecteur_nom'] ?? 'Non assigné');
    $sheet->setCellValue('G' . $row, $etudiant['cote_entreprise'] !== null ? $etudiant['cote_entreprise'] : 'Non noté');
    $sheet->setCellValue('H' . $row, $etudiant['cote_lecteur'] !== null ? $etudiant['cote_lecteur'] : 'Non noté');
    $sheet->setCellValue('I' . $row, $moyenne !== '' ? $moyenne : '-');
    $sheet->setCellValue('J' . $row, $statut);
    
    // Colorer la ligne en fonction du statut
    if ($statut === 'Réussi') {
        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC8E6C9');
    } elseif ($statut === 'Échoué') {
        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFCDD2');
    }
    
    $row++;
    $i++;
}

// Bordures pour tout le tableau
$lastRow = $row - 1;
$sheet->getStyle('A5:J' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Ajuster la largeur des colonnes
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(25);
$sheet->getColumnDimension('F')->setWidth(25);
$sheet->getColumnDimension('G')->setWidth(15);
$sheet->getColumnDimension('H')->setWidth(15);
$sheet->getColumnDimension('I')->setWidth(12);
$sheet->getColumnDimension('J')->setWidth(12);

// Centrer les données numériques
$sheet->getStyle('A6:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G6:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Générer le fichier
$filename = 'Etudiants_Encadres_' . str_replace(' ', '_', $enseignant['noms']) . '_' . str_replace('/', '-', $annee['designation']) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
