<?php
session_start();

// Vérifier les droits d'accès
if (!isset($_SESSION['idRole']) || $_SESSION['idRole'] != 1) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Accès refusé';
    exit;
}

try {
    require_once '../vendor/autoload.php';
    require_once '../models/GrilleAncienne.php';
    require_once '../models/Universite.php';
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;

    $grilleAncienne = new GrilleAncienne();
    $universite = new Universite();
    
    // Récupérer l'import spécifique ou tous les imports
    $importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : null;
    
    if ($importId) {
        // Export pour un seul import
        $imports = [$grilleAncienne->getImportById($importId)];
        if (!$imports[0]) {
            throw new Exception('Import non trouvé.');
        }
    } else {
        // Export pour tous les imports
        $imports = $grilleAncienne->getAllImports();
        if (empty($imports)) {
            throw new Exception('Aucun import trouvé.');
        }
    }

    // Créer le document Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0); // Supprimer la feuille par défaut

    foreach ($imports as $index => $import) {
        createValidationSheet($spreadsheet, $grilleAncienne, $universite, $import, $index);
    }

    // Définir le nom du fichier
    if ($importId) {
        $filename = "Fiches_Validation_" . $imports[0]['promotion'] . "_" . $imports[0]['annee_academique'] . ".xlsx";
    } else {
        $filename = "Fiches_Validation_Toutes_Grilles_" . date('Y-m-d') . ".xlsx";
    }

    // Headers pour le téléchargement
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Écrire le fichier
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    error_log("Erreur export fiches validation: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Erreur: ' . $e->getMessage();
}

/**
 * Créer une feuille de validation pour un import
 */
function createValidationSheet($spreadsheet, $grilleAncienne, $universite, $import, $sheetIndex) {
    // Créer la feuille
    $sheetName = substr($import['promotion'] . " " . $import['semestre'], 0, 31);
    $worksheet = $spreadsheet->createSheet($sheetIndex);
    $worksheet->setTitle($sheetName);
    
    // Récupérer les données
    $ues = $grilleAncienne->getUEsByImport($import['id']);
    $etudiants = $grilleAncienne->getEtudiantsByImport($import['id']);
    
    $row = 1;
    
    // En-tête du document
    $worksheet->setCellValue('A' . $row, 'FICHE DE VALIDATION DES CRÉDITS');
    $worksheet->mergeCells('A' . $row . ':' . getColumnLetter(4 + count($ues)) . $row);
    $worksheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(16);
    $worksheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;
    
    // Informations de l'import
    $worksheet->setCellValue('A' . $row, 'Année Académique:');
    $worksheet->setCellValue('B' . $row, $import['annee_academique']);
    $worksheet->setCellValue('D' . $row, 'Session:');
    $worksheet->setCellValue('E' . $row, ucfirst($import['session']));
    $row++;
    
    $worksheet->setCellValue('A' . $row, 'Semestre:');
    $worksheet->setCellValue('B' . $row, $import['semestre']);
    $worksheet->setCellValue('D' . $row, 'Promotion:');
    $worksheet->setCellValue('E' . $row, $import['promotion']);
    $row += 2;
    
    // En-têtes du tableau
    $col = 1;
    $worksheet->setCellValueByColumnAndRow($col++, $row, 'N°');
    $worksheet->setCellValueByColumnAndRow($col++, $row, 'Matricule');
    $worksheet->setCellValueByColumnAndRow($col++, $row, 'Noms et Prénoms');
    
    // Colonnes pour chaque UE
    foreach ($ues as $ue) {
        $worksheet->setCellValueByColumnAndRow($col, $row, $ue['code_ue']);
        $worksheet->getCellByColumnAndRow($col, $row + 1)->setValue($ue['credits'] . ' cr');
        $col++;
    }
    
    $worksheet->setCellValueByColumnAndRow($col++, $row, 'Total Crédits');
    $worksheet->setCellValueByColumnAndRow($col++, $row, 'Moyenne');
    $worksheet->setCellValueByColumnAndRow($col, $row, 'Mention');
    
    // Style des en-têtes
    $headerRange = 'A' . $row . ':' . getColumnLetter($col) . ($row + 1);
    $worksheet->getStyle($headerRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('4472C4');
    $worksheet->getStyle($headerRange)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'))->setBold(true);
    $worksheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $row += 2;
    
    // Données des étudiants
    $num = 1;
    foreach ($etudiants as $etudiant) {
        $col = 1;
        
        // Informations de base
        $worksheet->setCellValueByColumnAndRow($col++, $row, $num++);
        $worksheet->setCellValueByColumnAndRow($col++, $row, $etudiant['matricule']);
        $worksheet->setCellValueByColumnAndRow($col++, $row, $etudiant['noms']);
        
        // Résultats par UE
        $totalCredits = 0;
        $creditsValides = 0;
        $totalPoints = 0;
        
        foreach ($ues as $ue) {
            $resultats = $grilleAncienne->getResultatsByEtudiant($etudiant['id'], 'ue');
            $resultatUE = null;
            
            foreach ($resultats as $res) {
                if ($res['ue_id'] == $ue['id']) {
                    $resultatUE = $res;
                    break;
                }
            }
            
            if ($resultatUE && $resultatUE['moyenne'] !== null) {
                $moyenne = round($resultatUE['moyenne'], 2);
                $valide = $resultatUE['est_valide'];
                
                $worksheet->setCellValueByColumnAndRow($col, $row, $moyenne);
                
                // Couleur selon validation
                if ($valide) {
                    $worksheet->getStyleByColumnAndRow($col, $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('C6EFCE');
                    $creditsValides += $ue['credits'];
                } else {
                    $worksheet->getStyleByColumnAndRow($col, $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFC7CE');
                }
                
                $totalPoints += $moyenne * $ue['credits'];
                $totalCredits += $ue['credits'];
            } else {
                $worksheet->setCellValueByColumnAndRow($col, $row, '-');
                $totalCredits += $ue['credits'];
            }
            
            $col++;
        }
        
        // Total des crédits validés
        $worksheet->setCellValueByColumnAndRow($col++, $row, $creditsValides . '/' . $totalCredits);
        
        // Moyenne générale
        $moyenneGenerale = $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
        $worksheet->setCellValueByColumnAndRow($col++, $row, round($moyenneGenerale, 2));
        
        // Mention
        $mention = getMention($moyenneGenerale);
        $worksheet->setCellValueByColumnAndRow($col, $row, $mention);
        
        // Style pour la moyenne générale
        if ($moyenneGenerale >= 10) {
            $worksheet->getStyleByColumnAndRow($col - 1, $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C6EFCE');
        } else {
            $worksheet->getStyleByColumnAndRow($col - 1, $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFC7CE');
        }
        
        $row++;
    }
    
    // Bordures pour tout le tableau
    $tableRange = 'A' . ($row - count($etudiants) - 1) . ':' . getColumnLetter($col) . ($row - 1);
    $worksheet->getStyle($tableRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    // Ajuster la largeur des colonnes
    for ($i = 1; $i <= $col; $i++) {
        $worksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }
    
    // Pied de page avec signatures
    $row += 3;
    $worksheet->setCellValue('A' . $row, 'Le Président du Jury');
    $worksheet->setCellValue('E' . $row, 'Le Secrétaire');
    
    $row += 4;
    $worksheet->setCellValue('A' . $row, 'Signature: ________________');
    $worksheet->setCellValue('E' . $row, 'Signature: ________________');
}

/**
 * Obtenir la lettre de colonne Excel
 */
function getColumnLetter($col) {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
}

/**
 * Déterminer la mention selon la moyenne
 */
function getMention($moyenne) {
    if ($moyenne >= 16) {
        return 'Très Bien';
    } elseif ($moyenne >= 14) {
        return 'Bien';
    } elseif ($moyenne >= 12) {
        return 'Assez Bien';
    } elseif ($moyenne >= 10) {
        return 'Satisfaction';
    } else {
        return 'Ajournement';
    }
}
?>
