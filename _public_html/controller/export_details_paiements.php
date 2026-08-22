<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require dirname(__DIR__) . '/vendor/autoload.php'; // Pour PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

// Récupérer les paramètres
$frais_id = isset($_GET['fraisId']) ? intval($_GET['fraisId']) : 0;
$promotion_id = isset($_GET['promotionId']) ? intval($_GET['promotionId']) : 0;

if (!$frais_id || !$promotion_id) {
    echo "<script>
        alert('Paramètres manquants');
        window.location.href = '../?view=finance/frais.promotion';
    </script>";
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les détails du frais et de la promotion
    $sql = "SELECT 
                f.id, 
                f.designation, 
                f.montant, 
                f.devise, 
                f.est_obligatoire,
                cf.designation as categorie,
                p.\"designationPromotion\" as promotion,
                s.\"designationSection\" as section,
                a.designation as annee_academique
            FROM frais f
            JOIN categories_frais cf ON f.categorie_id = cf.id
            JOIN affectation_frais af ON f.id = af.frais_id
            JOIN promotion p ON af.promotion_id = p.idpromotion
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
            WHERE f.id = :frais_id AND p.idpromotion = :promotion_id
            LIMIT 1";
    
    $stmt = $connexion->prepare($sql);
    $stmt->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmt->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $frais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$frais) {
        echo "<script>
            alert('Frais ou promotion non trouvé');
            window.location.href = '../?view=finance/frais.promotion';
        </script>";
        exit();
    }
    
    // Récupérer le nombre d'étudiants dans la promotion
    $sqlEtudiants = "SELECT COUNT(*) as nb_etudiants 
                     FROM etudiant 
                     WHERE promotion_idpromotion = :promotion_id 
                     AND est_actif = 1";
    
    $stmtEtudiants = $connexion->prepare($sqlEtudiants);
    $stmtEtudiants->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtEtudiants->execute();
    $resultEtudiants = $stmtEtudiants->fetch(PDO::FETCH_ASSOC);
    $nb_etudiants = $resultEtudiants['nb_etudiants'];
    
    // Récupérer les paiements pour ce frais dans cette promotion
    $sqlPaiements = "SELECT 
                        pf.id,
                        pf.matricule_etudiant as matricule,
                        e.noms as nom_etudiant,
                        pf.montant,
                        pf.date_valeur as date_paiement,
                        pf.reference_externe as reference,
                        pf.est_confirme
                    FROM paiements_frais pf
                    JOIN etudiant e ON pf.etudiant_id = e.idetudiant
                    JOIN affectation_frais af ON pf.affectation_id = af.id
                    WHERE af.frais_id = :frais_id 
                    AND e.promotion_idpromotion = :promotion_id
                    ORDER BY e.noms ASC";
    
    $stmtPaiements = $connexion->prepare($sqlPaiements);
    $stmtPaiements->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtPaiements->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtPaiements->execute();
    
    $paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer le montant total perçu
    $sqlMontantPercu = "SELECT COALESCE(SUM(pf.montant), 0) as montant_percu
                        FROM paiements_frais pf
                        JOIN etudiant e ON pf.etudiant_id = e.idetudiant
                        JOIN affectation_frais af ON pf.affectation_id = af.id
                        WHERE af.frais_id = :frais_id 
                        AND e.promotion_idpromotion = :promotion_id
                        AND pf.est_confirme = 1";
    
    $stmtMontantPercu = $connexion->prepare($sqlMontantPercu);
    $stmtMontantPercu->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtMontantPercu->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtMontantPercu->execute();
    $resultMontantPercu = $stmtMontantPercu->fetch(PDO::FETCH_ASSOC);
    $montant_percu = $resultMontantPercu['montant_percu'];
    
    // Récupérer les étudiants qui n'ont pas effectué de paiement
    $sqlEtudiantsSansPaiement = "SELECT 
                                    e.matricule,
                                    e.noms as nom_etudiant
                                FROM etudiant e
                                WHERE e.promotion_idpromotion = :promotion_id
                                AND e.est_actif = 1
                                AND e.idetudiant NOT IN (
                                    SELECT DISTINCT pf.etudiant_id
                                    FROM paiements_frais pf
                                    JOIN affectation_frais af ON pf.affectation_id = af.id
                                    WHERE af.frais_id = :frais_id
                                )
                                ORDER BY e.noms";
    
    $stmtEtudiantsSansPaiement = $connexion->prepare($sqlEtudiantsSansPaiement);
    $stmtEtudiantsSansPaiement->bindParam(':frais_id', $frais_id, PDO::PARAM_INT);
    $stmtEtudiantsSansPaiement->bindParam(':promotion_id', $promotion_id, PDO::PARAM_INT);
    $stmtEtudiantsSansPaiement->execute();
    
    $etudiants_sans_paiement = $stmtEtudiantsSansPaiement->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $montant_attendu = $frais['montant'] * $nb_etudiants;
    $taux_paiement = $montant_attendu > 0 ? ($montant_percu / $montant_attendu) * 100 : 0;
    
    // Créer un nouveau document Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Détails Paiements');
    
    // Configurer les en-têtes
    $sheet->setCellValue('A1', 'DÉTAILS DES PAIEMENTS');
    $sheet->mergeCells('A1:G1');
    
    $sheet->setCellValue('A2', 'Frais: ' . $frais['designation'] . ' (' . number_format($frais['montant'], 2, ',', ' ') . ' ' . $frais['devise'] . ')');
    $sheet->mergeCells('A2:G2');
    
    $sheet->setCellValue('A3', 'Catégorie: ' . $frais['categorie']);
    $sheet->mergeCells('A3:G3');
    
    $sheet->setCellValue('A4', 'Promotion: ' . $frais['section'] . ' - ' . $frais['promotion']);
    $sheet->mergeCells('A4:G4');
    
    $sheet->setCellValue('A5', 'Année académique: ' . $frais['annee_academique']);
    $sheet->mergeCells('A5:G5');
    
    $sheet->setCellValue('A6', 'Date d\'extraction: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A6:G6');
    
    // Style des en-têtes
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 14
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'CCCCFF',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:G6')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    // Statistiques
    $sheet->setCellValue('A8', 'STATISTIQUES DE PAIEMENT');
    $sheet->mergeCells('A8:G8');
    
    $sheet->setCellValue('A9', 'Nombre d\'étudiants:');
    $sheet->setCellValue('B9', $nb_etudiants);
    
    $sheet->setCellValue('A10', 'Montant attendu:');
    $sheet->setCellValue('B10', number_format($montant_attendu, 2, ',', ' ') . ' ' . $frais['devise']);
    
    $sheet->setCellValue('A11', 'Montant perçu:');
    $sheet->setCellValue('B11', number_format($montant_percu, 2, ',', ' ') . ' ' . $frais['devise']);
    
    $sheet->setCellValue('A12', 'Taux de paiement:');
    $sheet->setCellValue('B12', number_format($taux_paiement, 2, ',', ' ') . '%');
    
    // Style pour les statistiques
    $statsHeaderStyle = [
        'font' => [
            'bold' => true,
            'size' => 12
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E0E0FF',
            ],
        ],
    ];
    
    $statsStyle = [
        'font' => [
            'bold' => true,
        ],
    ];
    
    $sheet->getStyle('A8:G8')->applyFromArray($statsHeaderStyle);
    $sheet->getStyle('A9:A12')->applyFromArray($statsStyle);
    
    // En-têtes des paiements
    $sheet->setCellValue('A14', 'LISTE DES PAIEMENTS');
    $sheet->mergeCells('A14:G14');
    $sheet->getStyle('A14:G14')->applyFromArray($statsHeaderStyle);
    
    $sheet->setCellValue('A15', 'N°');
    $sheet->setCellValue('B15', 'Matricule');
    $sheet->setCellValue('C15', 'Nom de l\'étudiant');
    $sheet->setCellValue('D15', 'Montant payé');
    $sheet->setCellValue('E15', 'Date de paiement');
    $sheet->setCellValue('F15', 'Référence');
    $sheet->setCellValue('G15', 'Statut');
    
    // Style des en-têtes de colonnes
    $columnHeaderStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E0E0E0',
            ],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $sheet->getStyle('A15:G15')->applyFromArray($columnHeaderStyle);
    
    // Remplir les données des paiements
    $row = 16;
    $counter = 1;
    
    foreach ($paiements as $paiement) {
        $montantPaye = $paiement['montant'] ?? 0;
        $montantTotal = $frais['montant'];
        $pourcentage = $montantTotal > 0 ? ($montantPaye / $montantTotal) * 100 : 0;
        
        $statut = $pourcentage >= 100 ? 'Payé' : ($pourcentage > 0 ? 'Partiel' : 'Non payé');
        $statutColor = $pourcentage >= 100 ? 'CCFFCC' : ($pourcentage > 0 ? 'FFFFCC' : 'FFCCCC');
        
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $paiement['matricule'] ?? '-');
        $sheet->setCellValue('C' . $row, $paiement['nom_etudiant'] ?? '-');
        $sheet->setCellValue('D' . $row, number_format($montantPaye, 2, ',', ' ') . ' ' . $frais['devise']);
        $sheet->setCellValue('E' . $row, $paiement['date_paiement'] ? date('d/m/Y', strtotime($paiement['date_paiement'])) : '-');
        $sheet->setCellValue('F' . $row, $paiement['reference'] ?? '-');
        $sheet->setCellValue('G' . $row, $statut);
        
        // Colorer la cellule de statut
        $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statutColor);
        
        $row++;
        $counter++;
    }
    
    // Style des cellules de données
    $dataCellStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    if (count($paiements) > 0) {
        $sheet->getStyle('A16:G' . ($row - 1))->applyFromArray($dataCellStyle);
    } else {
        $sheet->setCellValue('A16', 'Aucun paiement trouvé');
        $sheet->mergeCells('A16:G16');
        $sheet->getStyle('A16:G16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A16:G16')->applyFromArray($dataCellStyle);
    }
    
    // En-têtes des étudiants sans paiement
    $row += 2;
    $sheet->setCellValue('A' . $row, 'ÉTUDIANTS SANS PAIEMENT');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($statsHeaderStyle);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'N°');
    $sheet->setCellValue('B' . $row, 'Matricule');
    $sheet->setCellValue('C' . $row, 'Nom de l\'étudiant');
    $sheet->setCellValue('D' . $row, 'Montant dû');
    
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($columnHeaderStyle);
    
    // Remplir les données des étudiants sans paiement
    $row++;
    $counter = 1;
    
    foreach ($etudiants_sans_paiement as $etudiant) {
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $etudiant['matricule'] ?? '-');
        $sheet->setCellValue('C' . $row, $etudiant['nom_etudiant'] ?? '-');
        $sheet->setCellValue('D' . $row, number_format($frais['montant'], 2, ',', ' ') . ' ' . $frais['devise']);
        
        $row++;
        $counter++;
    }
    
    // Style des cellules de données
    if (count($etudiants_sans_paiement) > 0) {
        $sheet->getStyle('A' . ($row - count($etudiants_sans_paiement)) . ':D' . ($row - 1))->applyFromArray($dataCellStyle);
    } else {
        $sheet->setCellValue('A' . $row, 'Tous les étudiants ont effectué au moins un paiement');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($dataCellStyle);
    }
    
    // Légende
    $row += 2;
    $sheet->setCellValue('A' . $row, 'Légende:');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Payé');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCFFCC');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Partiel');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'Non payé');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCCCC');
    
    // Ajuster automatiquement la largeur des colonnes
    foreach (range('A', 'G') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Générer le fichier Excel
    $writer = new Xlsx($spreadsheet);
    $fileName = 'Details_Paiements_' . $frais['designation'] . '_' . $frais['promotion'] . '_' . date('Ymd_His') . '.xlsx';
    // Remplacer les caractères spéciaux dans le nom du fichier
    $fileName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
    
} catch (PDOException $e) {
    echo "<script>
        alert('Erreur lors de l\'exportation: " . addslashes($e->getMessage()) . "');
        window.location.href = '../?view=finance/frais.promotion';
    </script>";
    exit();
} catch (Exception $e) {
    echo "<script>
        alert('Erreur: " . addslashes($e->getMessage()) . "');
        window.location.href = '../?view=finance/frais.promotion';
    </script>";
    exit();
}
