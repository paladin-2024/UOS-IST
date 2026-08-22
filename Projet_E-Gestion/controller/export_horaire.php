<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Récupérer les paramètres
$idPromotion = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;
$idAnneeAcad = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
$dateDebut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
$dateFin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';
$format = isset($_POST['format']) ? trim($_POST['format']) : 'pdf';
$titre = isset($_POST['titre']) ? trim($_POST['titre']) : 'Emploi du temps';

// Validation des données
if ($idPromotion <= 0 || $idAnneeAcad <= 0 || empty($dateDebut) || empty($dateFin)) {
    header("Location: ../index.php?view=enseignement/horaires&error=parametres_invalides");
    exit;
}

// Créer des instances des modèles
$horaire = new Horaire();
$universite = new Universite();

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les horaires
$horaires = $horaire->getHorairesByPromotionAndDates($idPromotion, $idAnneeAcad, $dateDebut, $dateFin);

// Récupérer les informations de la promotion
$promotion = $universite->getPromotionById($idPromotion);
$anneeAcad = $universite->getAcademicYearById($idAnneeAcad);

// Si format PDF
if ($format === 'pdf') {
    // Organiser les horaires par jour
    $horairesByDay = [];
    $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    
    foreach ($horaires as $h) {
        // Déterminer le jour
        if (!empty($h['date_cours'])) {
            $jourMapping = [
                'Monday' => 'Lundi',
                'Tuesday' => 'Mardi',
                'Wednesday' => 'Mercredi',
                'Thursday' => 'Jeudi',
                'Friday' => 'Vendredi',
                'Saturday' => 'Samedi',
                'Sunday' => 'Dimanche'
            ];
            $jourSemaine = date('l', strtotime($h['date_cours']));
            $jour = $jourMapping[$jourSemaine];
        } else {
            $jour = $h['jour'];
        }
        
        if (!isset($horairesByDay[$jour])) {
            $horairesByDay[$jour] = [];
        }
        
        $horairesByDay[$jour][] = $h;
    }
    
    // Trier les horaires par jour et heure de début
    foreach ($horairesByDay as &$dayHoraires) {
        usort($dayHoraires, function($a, $b) {
            return strcmp($a['heure_debut'], $b['heure_debut']);
        });
    }
    
    // Début du HTML
    $htmlOutput = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Emploi du temps</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 9pt; 
                color: #333; 
                margin: 0;
                padding: 0;
            }
            h1 { 
                font-size: 14pt; 
                color: #000; 
                text-align: center; 
                margin: 5px 0; 
            }
            h2 { 
                font-size: 12pt; 
                color: #000; 
                margin: 0 0 8px 0; 
                text-align: center; 
                padding-bottom: 3px; 
                border-bottom: 1px solid #ccc; 
            }
            h3 {
                font-size: 11pt;
                color: #444;
                margin: 10px 0 5px 0;
                background-color: #f5f5f5;
                padding: 3px;
                text-align: center;
                border-radius: 3px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 10px;
            }
            table, th, td { 
                border: 1px solid #ccc; 
            }
            th { 
                background-color: #f0f0f0; 
                font-weight: bold; 
                padding: 3px; 
                text-align: center; 
                font-size: 9pt;
            }
            td { 
                padding: 4px; 
                vertical-align: middle; 
                font-size: 8pt; 
            }
            .header {
                text-align: center;
                margin-bottom: 10px;
            }
            .institution-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
            }
            .institution-logo {
                width: 20%;
                text-align: left;
            }
            .institution-logo img {
                max-height: 50px;
            }
            .institution-details {
                width: 60%;
                text-align: center;
                font-weight: bold;
            }
            .institution-contact {
                width: 20%;
                text-align: right;
                font-size: 8pt;
            }
            .header-separator {
                border-bottom: 1px solid #000;
                margin: 5px 0 10px 0;
            }
            .jour-cell {
                font-weight: bold;
                width: 12%;
            }
            .cours-cell {
                width: 45%;
            }
            .horaire-cell {
                width: 23%;
            }
            .enseignant-cell {
                width: 20%;
            }
            .cm { background-color: #cfe2ff; }
            .td { background-color: #d1e7dd; }
            .tp { background-color: #fff3cd; }
            .eval { background-color: #f8d7da; }
            .semaine-info {
                text-align: center;
                margin: 5px 0;
                font-size: 10pt;
                font-weight: bold;
            }
            .footer {
                text-align: right;
                font-size: 8pt;
                margin-top: 5px;
                font-style: italic;
            }
            .legende {
                display: flex;
                justify-content: center;
                margin: 10px 0;
                font-size: 8pt;
            }
            .legende-item {
                margin: 0 5px;
                padding: 2px 5px;
                border-radius: 2px;
            }
        </style>
    </head>
    <body>';

    // Ajouter l'en-tête avec les informations de l'institution
    $htmlOutput .= '<div class="institution-info">
        <div class="institution-logo">';

    // Ajouter le logo s'il existe
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoMime = mime_content_type($logoPath);
            $htmlOutput .= '<img src="data:' . $logoMime . ';base64,' . $logoData . '" alt="Logo Institution" />';
        }
    }

    $htmlOutput .= '</div>
    <div class="institution-details">
        <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['nom'] ?? 'Université') . '</div>
        
    </div>
    <div class="institution-contact">
        <div>' . htmlspecialchars($configUniversite['adresse'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['ville'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['telephone'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['email'] ?? '') . '</div>
    </div>
</div>
<div class="header-separator"></div>

<div class="header">
    <h1>' . htmlspecialchars($titre) . '</h1>
    <h1>Semaine du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin)) . '</h1>
    
</div>

    <div class="legende">
        <span class="legende-item cm">CM</span>
        <span class="legende-item td">TD</span>
        <span class="legende-item tp">TP</span>
        <span class="legende-item eval">Évaluation</span>
    </div>';

    // Titre de la promotion
    $htmlOutput .= '<h3>Promotion: ' . htmlspecialchars($promotion['designationPromotion']) . '</h3>';
    
    // Générer le tableau avec 4 colonnes
    $htmlOutput .= '<table cellspacing="0" cellpadding="3">
        <thead>
            <tr>
                <th class="jour-cell">Jour</th>
                <th class="cours-cell">Cours</th>
                <th class="horaire-cell">Horaire</th>
                <th class="enseignant-cell">Enseignant</th>
            </tr>
        </thead>
        <tbody>';
    
    // Pour chaque jour de la semaine
    foreach ($weekDays as $jour) {
        if (isset($horairesByDay[$jour]) && !empty($horairesByDay[$jour])) {
            $rowspan = count($horairesByDay[$jour]);
            $firstRow = true;
            
            foreach ($horairesByDay[$jour] as $index => $h) {
                $htmlOutput .= '<tr>';
                
                // Colonne Jour (seulement sur la première ligne de chaque jour)
                if ($firstRow) {
                    $htmlOutput .= '<td class="jour-cell" rowspan="' . $rowspan . '">' . $jour . '</td>';
                    $firstRow = false;
                }
                
                                // Déterminer le type de cours pour la classe CSS
                                $typeClass = 'cm'; // CM par défaut
                                if (isset($h['type_cours'])) {
                                    if (strpos(strtolower($h['type_cours']), 'td') !== false) {
                                        $typeClass = 'td';
                                    } elseif (strpos(strtolower($h['type_cours']), 'tp') !== false) {
                                        $typeClass = 'tp';
                                    } elseif (strpos(strtolower($h['type_cours']), 'eval') !== false) {
                                        $typeClass = 'eval';
                                    }
                                }
                                
                                // Colonne Cours
                                $htmlOutput .= '<td class="cours-cell ' . $typeClass . '">' . 
                                    htmlspecialchars($h['designationECUE']) . 
                                    '<br><small>Salle: ' . htmlspecialchars($h['salle']) . '</small></td>';
                                
                                // Colonne Horaire
                                $htmlOutput .= '<td class="horaire-cell">' . 
                                    substr($h['heure_debut'], 0, 5) . ' - ' . substr($h['heure_fin'], 0, 5) . 
                                    '<br>Type: ' . htmlspecialchars($h['type_cours'] ?? 'CM') . '</td>';
                                
                                // Colonne Enseignant
                                $htmlOutput .= '<td class="enseignant-cell">' . htmlspecialchars($h['enseignant_nom']) . '</td>';
                                
                                $htmlOutput .= '</tr>';
                            }
                        } else {
                            // Si aucun cours ce jour-là
                            $htmlOutput .= '<tr>
                                <td class="jour-cell">' . $jour . '</td>
                                <td class="cours-cell" colspan="3">Aucun cours programmé</td>
                            </tr>';
                        }
                    }
                    
                    $htmlOutput .= '</tbody></table>';
                
                    // Pied de page
                    $htmlOutput .= '<div class="footer">
                        Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ' le ' . date('d/m/Y à H:i') . '
                    </div>';
                
                    // Fermer le HTML
                    $htmlOutput .= '</body></html>';
                
                    // Générer le PDF avec Html2Pdf
                    try {
                        // Utiliser des marges plus petites pour éviter les débordements
                        $html2pdf = new Html2Pdf('L', 'A4', 'fr', true, 'UTF-8', [5, 5, 5, 5]);
                        $html2pdf->setDefaultFont('Arial');
                        
                        // Ajuster la taille du document si nécessaire pour éviter les débordements
                        $html2pdf->pdf->SetDisplayMode('fullpage');
                        
                        $html2pdf->writeHTML($htmlOutput);
                        $html2pdf->output('emploi_du_temps.pdf', 'I');
                        exit;
                    } catch (Html2PdfException $e) {
                        $html2pdf->clean();
                        $formatter = new ExceptionFormatter($e);
                        echo $formatter->getHtmlMessage();
                        exit;
                    }
                } 
                // Si format Excel
                else if ($format === 'excel') {
                    // Utiliser PhpSpreadsheet pour générer le fichier Excel
                    require_once dirname(__DIR__) . '/vendor/autoload.php';
                    
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    
                    // Définir le titre
                    $sheet->setCellValue('A1', $titre);
                    $sheet->mergeCells('A1:D1');
                    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    
                    // Ajouter la promotion et la période
                    $sheet->setCellValue('A2', 'Promotion: ' . $promotion['designationPromotion']);
                    $sheet->mergeCells('A2:D2');
                    $sheet->getStyle('A2')->getFont()->setBold(true);
                    
                    $sheet->setCellValue('A3', 'Semaine du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin)));
                    $sheet->mergeCells('A3:D3');
                    
                    // Définir les en-têtes de colonnes
                    $sheet->setCellValue('A5', 'Jour');
                    $sheet->setCellValue('B5', 'Cours');
                    $sheet->setCellValue('C5', 'Horaire');
                    $sheet->setCellValue('D5', 'Enseignant');
                    
                    // Mettre en forme les en-têtes
                    $sheet->getStyle('A5:D5')->getFont()->setBold(true);
                    $sheet->getStyle('A5:D5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
                    
                    // Organiser les horaires par jour
                    $horairesByDay = [];
                    $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                    
                    foreach ($horaires as $h) {
                        // Déterminer le jour
                        if (!empty($h['date_cours'])) {
                            $jourMapping = [
                                'Monday' => 'Lundi',
                                'Tuesday' => 'Mardi',
                                'Wednesday' => 'Mercredi',
                                'Thursday' => 'Jeudi',
                                'Friday' => 'Vendredi',
                                'Saturday' => 'Samedi',
                                'Sunday' => 'Dimanche'
                            ];
                            $jourSemaine = date('l', strtotime($h['date_cours']));
                            $jour = $jourMapping[$jourSemaine];
                        } else {
                            $jour = $h['jour'];
                        }
                        
                        if (!isset($horairesByDay[$jour])) {
                            $horairesByDay[$jour] = [];
                        }
                        
                        $horairesByDay[$jour][] = $h;
                    }
                    
                    // Trier les horaires par jour et heure de début
                    foreach ($horairesByDay as &$dayHoraires) {
                        usort($dayHoraires, function($a, $b) {
                            return strcmp($a['heure_debut'], $b['heure_debut']);
                        });
                    }
                    
                    // Remplir le tableau d'horaires
                    $rowIndex = 6;
                    
                    foreach ($weekDays as $jour) {
                        if (isset($horairesByDay[$jour]) && !empty($horairesByDay[$jour])) {
                            $startRow = $rowIndex;
                            
                            foreach ($horairesByDay[$jour] as $h) {
                                // Déterminer le type de cours
                                $typeCours = $h['type_cours'] ?? 'CM';
                                
                                // Colonne Jour (seulement sur la première ligne de chaque jour)
                                if ($rowIndex == $startRow) {
                                    $sheet->setCellValue('A' . $rowIndex, $jour);
                                }
                                
                                // Colonne Cours
                                $sheet->setCellValue('B' . $rowIndex, $h['designationECUE'] . "\nSalle: " . $h['salle']);
                                
                                // Colonne Horaire
                                $sheet->setCellValue('C' . $rowIndex, substr($h['heure_debut'], 0, 5) . ' - ' . substr($h['heure_fin'], 0, 5) . "\nType: " . $typeCours);
                                
                                // Colonne Enseignant
                                $sheet->setCellValue('D' . $rowIndex, $h['enseignant_nom']);
                                
                                // Activer le retour à la ligne
                                $sheet->getStyle('B' . $rowIndex . ':D' . $rowIndex)->getAlignment()->setWrapText(true);
                                
                                // Appliquer la couleur selon le type de cours
                                $fillColor = 'FFFFFFFF'; // Blanc par défaut
                                if (strpos(strtolower($typeCours), 'cm') !== false) {
                                    $fillColor = 'FFCFE2FF'; // Bleu clair pour CM
                                } elseif (strpos(strtolower($typeCours), 'td') !== false) {
                                    $fillColor = 'FFD1E7DD'; // Vert clair pour TD
                                } elseif (strpos(strtolower($typeCours), 'tp') !== false) {
                                    $fillColor = 'FFFFF3CD'; // Jaune clair pour TP
                                } elseif (strpos(strtolower($typeCours), 'eval') !== false) {
                                    $fillColor = 'FFF8D7DA'; // Rouge clair pour Évaluation
                                }
                                
                                $sheet->getStyle('B' . $rowIndex)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);
                                
                                $rowIndex++;
                            }
                            
                            // Fusionner les cellules pour le jour
                            if ($startRow < $rowIndex - 1) {
                                $sheet->mergeCells('A' . $startRow . ':A' . ($rowIndex - 1));
                                $sheet->getStyle('A' . $startRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                            }
                        } else {
                            // Si aucun cours ce jour-là
                            $sheet->setCellValue('A' . $rowIndex, $jour);
                            $sheet->setCellValue('B' . $rowIndex, 'Aucun cours programmé');
                            $sheet->mergeCells('B' . $rowIndex . ':D' . $rowIndex);
                            
                            $rowIndex++;
                        }
                    }
                    
                    // Ajuster automatiquement la largeur des colonnes
                    $sheet->getColumnDimension('A')->setWidth(15);
                    $sheet->getColumnDimension('B')->setWidth(40);
                    $sheet->getColumnDimension('C')->setWidth(20);
                    $sheet->getColumnDimension('D')->setWidth(25);
                    
                    // Définir une hauteur minimale pour les lignes
                    for ($i = 6; $i < $rowIndex; $i++) {
                        $sheet->getRowDimension($i)->setRowHeight(30);
                    }
                    
                    // Bordures pour toutes les cellules du tableau
                    $styleArray = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                    ];
                    $sheet->getStyle('A5:D' . ($rowIndex - 1))->applyFromArray($styleArray);
                    
                    // Définir les headers pour télécharger le fichier
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment;filename="emploi_du_temps.xlsx"');
                    header('Cache-Control: max-age=0');
                    
                    // Envoyer le fichier au navigateur
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save('php://output');
                    exit;
                } else {
                    // Format non pris en charge
                    header("Location: ../index.php?view=enseignement/horaires&error=format_non_supporte");
                    exit;
                }
                