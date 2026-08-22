<?php
session_start();
require_once dirname(__DIR__).'/config/config.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__). '/models/Universite.php';
require_once dirname(__DIR__)."/vendor/autoload.php"; // Assume PhpSpreadsheet and TCPDF are installed

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['id'])) {
    header("Location: ../login");
    exit;
}

// Get the export format from the request
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Function to get student profile completion data
function getStudentProfileCompletionData($universite) {
    $students = $universite->getStudents();
    $completionData = [];
    
    foreach ($students as $student) {
        // Calculate completion percentage based on filled fields
        $totalFields = 9;
        $completedFields = 0;
        
        // Count completed fields
        if (!empty($student['noms'])) $completedFields++;
        if (!empty($student['lieuNaissance'])) $completedFields++;
        if (!empty($student['dateNaissance'])) $completedFields++;
        if (!empty($student['adressemail'])) $completedFields++;
        if (!empty($student['telephone'])) $completedFields++;
        if (!empty($student['adresse'])) $completedFields++;
        if (!empty($student['personne_contact'])) $completedFields++;
        if (!empty($student['telephone_contact'])) $completedFields++;
        if (!empty($student['photo'])) $completedFields++;
        
        $completionPercentage = ($completedFields / $totalFields) * 100;
        
        // Add completion status
        $status = '';
        if ($completionPercentage == 0) {
            $status = 'Non commencé';
        } elseif ($completionPercentage < 50) {
            $status = 'En cours';
        } elseif ($completionPercentage < 100) {
            $status = 'Presque complet';
        } else {
            $status = 'Complet';
        }
        
        $student['completedFields'] = $completedFields;
        $student['totalFields'] = $totalFields;
        $student['completionPercentage'] = $completionPercentage;
        $student['completionStatus'] = $status;
        
        $completionData[] = $student;
    }
    
    return $completionData;
}

$universite = new Universite();
$studentCompletionData = getStudentProfileCompletionData($universite);

// Export based on the requested format
if ($format === 'excel') {
    // Export to Excel using PhpSpreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', '#');
    $sheet->setCellValue('B1', 'Matricule');
    $sheet->setCellValue('C1', 'Nom');
    $sheet->setCellValue('D1', 'Promotion');
    $sheet->setCellValue('E1', 'Année');
    $sheet->setCellValue('F1', 'Champs remplis');
    $sheet->setCellValue('G1', 'Pourcentage');
    $sheet->setCellValue('H1', 'Statut');
    
    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'E0E0E0',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // Populate data
    $row = 2;
    foreach ($studentCompletionData as $i => $student) {
        $sheet->setCellValue('A' . $row, $i + 1);
        $sheet->setCellValue('B' . $row, $student['matricule']);
        $sheet->setCellValue('C' . $row, $student['noms']);
        $sheet->setCellValue('D' . $row, $student['designationPromotion'] ?? 'N/A');
        $sheet->setCellValue('E' . $row, $student['annee'] ?? 'N/A');
        $sheet->setCellValue('F' . $row, $student['completedFields'] . '/' . $student['totalFields']);
        $sheet->setCellValue('G' . $row, round($student['completionPercentage']) . '%');
        $sheet->setCellValue('H' . $row, $student['completionStatus']);
        
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set the filename
    $fileName = 'Completion_Profils_Etudiants_' . date('Y-m-d') . '.xlsx';
    
    // Redirect output to a client's web browser (Excel2007)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
} else if ($format === 'pdf') {
    // Export to PDF using TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('E-GESTION');
    $pdf->SetTitle('Rapport de complétion des profils étudiants');
    $pdf->SetSubject('Profils Etudiants');
    
    // Set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Rapport de complétion des profils étudiants', date('d/m/Y'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
    $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Add a page
    $pdf->AddPage();
    
    // Create the table content
    $html = '<h1>Rapport de complétion des profils étudiants</h1>';
    $html .= '<table border="1" cellpadding="5">
        <thead>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <th>#</th>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Promotion</th>
                <th>Champs remplis</th>
                <th>Pourcentage</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($studentCompletionData as $i => $student) {
        // Set row color based on completion status
        $rowColor = '';
        if ($student['completionStatus'] === 'Complet') {
            $rowColor = 'background-color: #d4edda;';
        } elseif ($student['completionStatus'] === 'Presque complet') {
            $rowColor = 'background-color: #d1ecf1;';
        } elseif ($student['completionStatus'] === 'En cours') {
            $rowColor = 'background-color: #fff3cd;';
        } else {
            $rowColor = 'background-color: #f8d7da;';
        }
        
        $html .= '<tr style="' . $rowColor . '">
            <td>' . ($i + 1) . '</td>
            <td>' . $student['matricule'] . '</td>
            <td>' . $student['noms'] . '</td>
            <td>' . ($student['designationPromotion'] ?? 'N/A') . '</td>
            <td>' . $student['completedFields'] . '/' . $student['totalFields'] . '</td>
            <td>' . round($student['completionPercentage']) . '%</td>
            <td>' . $student['completionStatus'] . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Add summary statistics
    $totalStudents = count($studentCompletionData);
    $completeCount = count(array_filter($studentCompletionData, fn($s) => $s['completionStatus'] === 'Complet'));
    $partialCount = count(array_filter($studentCompletionData, fn($s) => $s['completionStatus'] === 'Presque complet' || $s['completionStatus'] === 'En cours'));
    $notStartedCount = count(array_filter($studentCompletionData, fn($s) => $s['completionStatus'] === 'Non commencé'));
    
    $html .= '<h2>Résumé</h2>';
    $html .= '<table border="1" cellpadding="5">
        <tr>
            <th style="background-color: #f0f0f0;">Total étudiants</th>
            <th style="background-color: #f0f0f0;">Profils complets</th>
            <th style="background-color: #f0f0f0;">Profils partiels</th>
            <th style="background-color: #f0f0f0;">Non commencés</th>
        </tr>
        <tr>
            <td>' . $totalStudents . '</td>
            <td>' . $completeCount . ' (' . ($totalStudents > 0 ? round(($completeCount / $totalStudents) * 100) : 0) . '%)</td>
            <td>' . $partialCount . ' (' . ($totalStudents > 0 ? round(($partialCount / $totalStudents) * 100) : 0) . '%)</td>
            <td>' . $notStartedCount . ' (' . ($totalStudents > 0 ? round(($notStartedCount / $totalStudents) * 100) : 0) . '%)</td>
        </tr>
    </table>';
    
    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('Completion_Profils_Etudiants_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>
