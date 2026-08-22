<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

$stmt = $connexion->prepare("SELECT \"idAgent\", \"nomUser\" FROM t_users WHERE \"idUser\" = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$idAgent = $user_data['idAgent'] ?? null;
$nomAgent = $user_data['nomUser'] ?? 'Inconnu';

$est_admin = (isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1);

$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$filtre_agent = isset($_GET['filtre_agent']) ? $_GET['filtre_agent'] : $idAgent;
if (!$est_admin) {
    $filtre_agent = $idAgent;
}
$filtre_source = isset($_GET['filtre_source']) ? $_GET['filtre_source'] : '';
$filtre_devise = isset($_GET['filtre_devise']) ? $_GET['filtre_devise'] : '';

$sql = "
    SELECT 
        pf.id,
        pf.recu_numero,
        pf.montant,
        pf.devise,
        pf.mode_paiement,
        pf.reference_externe,
        pf.date_valeur,
        pf.commentaire,
        e.matricule,
        e.noms AS etudiant_nom,
        f.designation AS frais_designation,
        cf.designation AS categorie_frais,
        aa.designation AS annee_academique,
        p.\"designationPromotion\" AS promotion_nom,
        CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte_nom,
        t.reference AS transaction_reference,
        t.date_transaction,
        t.source,
        t.source_id,
        u.\"nomUser\" AS agent_nom,
        CASE 
            WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
            WHEN t.source = 'Banque' THEN (SELECT CONCAT(nom_banque, ' - ', intitule_compte) FROM comptes_bancaires WHERE id = t.source_id)
            ELSE 'Non spécifié'
        END AS source_nom
    FROM paiements_frais pf
    INNER JOIN etudiant e ON pf.matricule_etudiant = e.matricule AND e.est_actif = 1
    LEFT JOIN affectation_frais af ON pf.affectation_id = af.id
    LEFT JOIN frais f ON af.frais_id = f.id
    LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
    LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN t_users u ON t.\"idUser\" = u.\"idUser\"
    WHERE pf.date_valeur BETWEEN :date_debut AND :date_fin
    AND pf.est_confirme = 1
";

$params = [
    ':date_debut' => $date_debut,
    ':date_fin' => $date_fin
];

if (!empty($filtre_agent)) {
    $sql .= " AND t.\"idAgent\" = :filtre_agent";
    $params[':filtre_agent'] = $filtre_agent;
}

if (!empty($filtre_source)) {
    $sql .= " AND t.source = :filtre_source";
    $params[':filtre_source'] = $filtre_source;
}

if (!empty($filtre_devise)) {
    $sql .= " AND pf.devise = :filtre_devise";
    $params[':filtre_devise'] = $filtre_devise;
}

$sql .= " ORDER BY pf.date_valeur ASC, pf.id ASC";

$stmt = $connexion->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totaux_par_devise = [];
foreach ($paiements as $paiement) {
    $devise = $paiement['devise'] ?? 'USD';
    if (!isset($totaux_par_devise[$devise])) {
        $totaux_par_devise[$devise] = [
            'total' => 0,
            'nombre' => 0
        ];
    }
    $totaux_par_devise[$devise]['total'] += $paiement['montant'];
    $totaux_par_devise[$devise]['nombre']++;
}

$queryConfig = "SELECT * FROM configuration_universite LIMIT 1";
$stmtConfig = $connexion->query($queryConfig);
$universite = $stmtConfig->fetch(PDO::FETCH_ASSOC);

if (!$universite) {
    $universite = [
        'nom' => 'Université',
        'adresse' => 'Adresse non configurée',
        'telephone' => 'N/A',
        'email' => 'N/A'
    ];
}

$logoPath = '';
if (!empty($universite['logo'])) {
    $logoFullPath = dirname(__DIR__) . '/' . $universite['logo'];
    if (file_exists($logoFullPath)) {
        $logoPath = $logoFullPath;
    }
}
if (empty($logoPath)) {
    $logoDefaultPath = dirname(__DIR__) . '/assets/img/logo.png';
    if (file_exists($logoDefaultPath)) {
        $logoPath = $logoDefaultPath;
    }
}

class PDF extends TCPDF
{
    private $universite_data;
    private $logo_path;

    public function setUniversiteData($data)
    {
        $this->universite_data = $data;
    }

    public function setLogoPath($path)
    {
        $this->logo_path = $path;
    }

    public function Header()
    {
        if (!empty($this->logo_path) && file_exists($this->logo_path)) {
            $this->Image($this->logo_path, 15, 10, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        if ($this->universite_data) {
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 5, $this->universite_data['nom'], 0, 1, 'C');
            $this->SetFont('helvetica', '', 9);
            $this->Cell(0, 4, $this->universite_data['adresse'], 0, 1, 'C');
            $this->Cell(0, 4, 'Tél: ' . $this->universite_data['telephone'] . ' | Email: ' . $this->universite_data['email'], 0, 1, 'C');
            $this->Ln(3);
        }
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setUniversiteData($universite);
$pdf->setLogoPath($logoPath);
$pdf->SetCreator('E-Gestion');
$pdf->SetAuthor($nomAgent);
$pdf->SetTitle('Rapport des Paiements');
$pdf->SetSubject('Rapport financier');

$pdf->SetMargins(15, 35, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);

$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'RAPPORT DES PAIEMENTS', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Période du ' . date('d/m/Y', strtotime($date_debut)) . ' au ' . date('d/m/Y', strtotime($date_fin)), 0, 1, 'C');
$pdf->Ln(5);

if (!empty($totaux_par_devise)) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'RÉSUMÉ', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(70, 6, 'Devise', 1, 0, 'C', true);
    $pdf->Cell(70, 6, 'Montant Total', 1, 0, 'C', true);
    $pdf->Cell(70, 6, 'Nombre de Paiements', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 9);
    foreach ($totaux_par_devise as $devise => $data) {
        $pdf->Cell(70, 6, $devise, 1, 0, 'C');
        $pdf->Cell(70, 6, number_format($data['total'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
        $pdf->Cell(70, 6, $data['nombre'], 1, 1, 'C');
    }
    $pdf->Ln(5);
}

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'DÉTAILS DES PAIEMENTS', 0, 1, 'L');
$pdf->Ln(2);

if (empty($paiements)) {
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 6, 'Aucun paiement trouvé pour la période sélectionnée.', 0, 1, 'C');
} else {
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('helvetica', 'B', 8);
    
    $pdf->Cell(30, 6, 'N° Reçu', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Date', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'Étudiant', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Matricule', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'Frais', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Montant', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Mode', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Agent', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 7);
    
    foreach ($paiements as $paiement) {
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            $pdf->SetFillColor(220, 220, 220);
            $pdf->SetFont('helvetica', 'B', 8);
            
            $pdf->Cell(30, 6, 'N° Reçu', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Date', 1, 0, 'C', true);
            $pdf->Cell(50, 6, 'Étudiant', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Matricule', 1, 0, 'C', true);
            $pdf->Cell(50, 6, 'Frais', 1, 0, 'C', true);
            $pdf->Cell(30, 6, 'Montant', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Mode', 1, 0, 'C', true);
            $pdf->Cell(40, 6, 'Agent', 1, 1, 'C', true);
            
            $pdf->SetFont('helvetica', '', 7);
        }
        
        $pdf->Cell(30, 5, substr($paiement['recu_numero'], 0, 20), 1, 0, 'L');
        $pdf->Cell(20, 5, date('d/m/Y', strtotime($paiement['date_valeur'])), 1, 0, 'C');
        $pdf->Cell(50, 5, substr($paiement['etudiant_nom'], 0, 30), 1, 0, 'L');
        $pdf->Cell(25, 5, $paiement['matricule'], 1, 0, 'C');
        $pdf->Cell(50, 5, substr($paiement['frais_designation'], 0, 30), 1, 0, 'L');
        $pdf->Cell(30, 5, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 1, 0, 'R');
        $pdf->Cell(25, 5, substr($paiement['mode_paiement'], 0, 15), 1, 0, 'C');
        $pdf->Cell(40, 5, substr($paiement['agent_nom'], 0, 25), 1, 1, 'L');
    }
}

$pdf->Ln(10);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(130, 6, '', 0, 0);
$pdf->Cell(0, 6, 'Signature et Cachet', 0, 1, 'C');
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(130, 6, '', 0, 0);
$pdf->Cell(0, 6, '____________________________', 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par ' . $nomAgent, 0, 1, 'L');

$filename = 'Rapport_Paiements_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
