<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier que l'utilisateur est connecté et est membre d'un jury
if (!isset($_SESSION['id']) || ($_SESSION['role'] != 'Jury' && $_SESSION['role'] != 'Administrateur')) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits nécessaires pour effectuer cette action.'
        }).then(() => {
            window.location.href = '../index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$id_annee = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$id_session = isset($_GET['session']) ? intval($_GET['session']) : 0;
$id_promotion = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$type_export = isset($_GET['type']) ? $_GET['type'] : 'pv'; // 'pv' ou 'rapport'

// Vérifier que les paramètres nécessaires sont fournis
if ($id_annee == 0) {
    die("Année académique non spécifiée.");
}

// Instancier la connexion
$conn = Connexion::getInstance()->getPDO();

// Récupérer le nom de l'année académique
$query_annee = "SELECT designation FROM annee_acad WHERE idannee_acad = :id_annee";
$stmt_annee = $conn->prepare($query_annee);
$stmt_annee->bindParam(':id_annee', $id_annee);
$stmt_annee->execute();
$annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);

if (!$annee) {
    die("Année académique non trouvée.");
}

// Récupérer les informations sur la session si spécifiée
$session_info = null;
if ($id_session > 0) {
    $query_session = "SELECT \"designSession\",description FROM session WHERE idsession = :id_session";
    $stmt_session = $conn->prepare($query_session);
    $stmt_session->bindParam(':id_session', $id_session);
    $stmt_session->execute();
    $session_info = $stmt_session->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les informations sur la promotion si spécifiée
$promotion_info = null;
if ($id_promotion > 0) {
    $query_promotion = "SELECT p.\"designationPromotion\", o.\"designationOrientation\", s.\"designationSection\"
                        FROM promotion p
                        JOIN orientation o ON p.orientation_idorientation = o.idorientation
                        JOIN section s ON o.section_idsection = s.idsection
                        WHERE p.idpromotion = :id_promotion";
    $stmt_promotion = $conn->prepare($query_promotion);
    $stmt_promotion->bindParam(':id_promotion', $id_promotion);
    $stmt_promotion->execute();
    $promotion_info = $stmt_promotion->fetch(PDO::FETCH_ASSOC);
}

$query_recours = "
    SELECT r.id_recours, r.matricule, e.noms as nom_etudiant, p.\"designationPromotion\",
           ec.\"designationECUE\", u.\"designationUE\", r.motif, r.date_creation, r.statut,
           s.\"designSession\", s.description, a.designation as annee_acad,
           rr.id_reponse, rr.nouvelle_note_cc, rr.nouvelle_note_ex, rr.commentaire,
           rr.date_reponse, rr.valide_jury, rr.date_validation,
           ag.noms as nom_enseignant, vl.\"nomUser\" as validateur,
           o.\"designationOrientation\", sec.\"designationSection\",
           hc.cc_avant, hc.ex_avant, hc.mf_avant,
           hc.cc_apres, hc.ex_apres, hc.mf_apres
    FROM recours r
    JOIN etudiant e ON r.matricule = e.matricule
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN ecue ec ON r.id_ecue = ec.\"idECUE\"
    JOIN ue u ON ec.\"UE_idUE\" = u.\"idUE\"
    JOIN session s ON r.id_session = s.idsession
    JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
    JOIN recours_reponse rr ON r.id_recours = rr.id_recours
    LEFT JOIN agent ag ON rr.id_enseignant = ag.\"idAgent\"
    LEFT JOIN t_users vl ON rr.id_validateur = vl.\"idUser\"
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section sec ON o.section_idsection = sec.idsection
    LEFT JOIN historique_cotes hc ON r.id_ecue = hc.\"ECUE_idECUE\" 
       AND r.id_session = hc.session_idsession 
       AND r.matricule = hc.matricule 
       AND r.id_annee_acad = hc.annee_acad_id
       AND hc.idhistorique = (
           SELECT MAX(h2.idhistorique) 
           FROM historique_cotes h2 
           WHERE h2.\"ECUE_idECUE\" = r.id_ecue
             AND h2.session_idsession = r.id_session
             AND h2.matricule = r.matricule
             AND h2.annee_acad_id = r.id_annee_acad
       )
    WHERE r.id_annee_acad = :id_annee
    AND rr.valide_jury = 1";

// Ajouter les filtres optionnels
if ($id_session > 0) {
    $query_recours .= " AND r.id_session = :id_session";
}

if ($id_promotion > 0) {
    $query_recours .= " AND e.promotion_idpromotion = :id_promotion";
}


// Ajouter les filtres optionnels
$params = [':id_annee' => $id_annee];

if ($id_session > 0) {
    $query_recours .= " AND r.id_session = :id_session";
    $params[':id_session'] = $id_session;
}

if ($id_promotion > 0) {
    $query_recours .= " AND e.promotion_idpromotion = :id_promotion";
    $params[':id_promotion'] = $id_promotion;
}

// Trier par section, promotion, nom de l'étudiant
$query_recours .= " ORDER BY sec.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\", e.noms";

$stmt_recours = $conn->prepare($query_recours);
foreach ($params as $key => $value) {
    $stmt_recours->bindValue($key, $value);
}
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);

// Si aucun recours trouvé
if (count($recours) == 0) {
    die("Aucun recours validé trouvé pour les critères sélectionnés.");
}

// Récupérer les informations de l'établissement
$query_etablissement = "SELECT * FROM configuration_universite LIMIT 1";
$stmt_etablissement = $conn->prepare($query_etablissement);
$stmt_etablissement->execute();
$etablissement = $stmt_etablissement->fetch(PDO::FETCH_ASSOC);

// Récupérer les membres du jury pour les promotions concernées
$query_bureau = "SELECT DISTINCT bjd.idbureau, bjd.designation, bjd.numero_decision, 
                p.noms as president, s.noms as secretaire
                FROM bureau_jury_deliberation bjd
                JOIN agent p ON bjd.president_id = p.\"idAgent\"
                JOIN agent s ON bjd.secretaire_id = s.\"idAgent\"
                JOIN bureau_jury_promotion bjp ON bjd.idbureau = bjp.idbureau
                WHERE bjd.annee_acad_idannee_acad = :id_annee
                AND bjd.est_actif = 1";

if ($id_promotion > 0) {
    $query_bureau .= " AND bjp.idpromotion = :id_promotion";
}

$stmt_bureau = $conn->prepare($query_bureau);
$stmt_bureau->bindParam(':id_annee', $id_annee);
if ($id_promotion > 0) {
    $stmt_bureau->bindParam(':id_promotion', $id_promotion);
}
$stmt_bureau->execute();
$bureaux = $stmt_bureau->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les autres membres pour chaque bureau
$bureaux_membres = [];
foreach ($bureaux as $bureau) {
    $query_membres = "SELECT mbj.idmembre, a.noms, mbj.fonction
                     FROM membre_bureau_jury mbj
                     JOIN agent a ON mbj.\"idAgent\" = a.\"idAgent\"
                     WHERE mbj.idbureau = :idbureau";
    $stmt_membres = $conn->prepare($query_membres);
    $stmt_membres->bindParam(':idbureau', $bureau['idbureau']);
    $stmt_membres->execute();
    $membres = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);
    
    $bureaux_membres[$bureau['idbureau']] = [
        'bureau' => $bureau,
        'membres' => $membres
    ];
}

// Calculer les statistiques pour le QR code
$total_recours = count($recours);
$recours_avec_modification = 0;

foreach ($recours as $r) {
    if ($r['nouvelle_note_cc'] !== null || $r['nouvelle_note_ex'] !== null) {
        $recours_avec_modification++;
    }
}

// Classe TCPDF personnalisée pour le design amélioré
class MYPDF extends TCPDF {
    // Pied de page personnalisé
    public function Footer() {
        // Position à 15mm du bas
        $this->SetY(-15);
        
        // Ligne de séparation fine
        $this->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        
        // Police et couleur
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Date et signature électronique (centré sur sa propre ligne)
        $this->SetX(15);
        $this->Cell(($this->getPageWidth() - 30), 5, 'Document généré le ' . date('d/m/Y') . ' • Signature électronique sécurisée', 0, 1, 'C');

        // Nom de l'université et site web (centré sur sa propre ligne)
        $configUniversite = $GLOBALS['etablissement'] ?? array('nom' => 'eGestion', 'site_web' => '');
        $this->Cell(($this->getPageWidth() - 30), 5, ($configUniversite['nom'] ?? 'eGestion') . ' • Document officiel. ' . ($configUniversite['site_web'] ?? ''), 0, 1, 'C');
    }
}

// Créer une instance de la classe personnalisée
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Rendre la variable etablissement accessible globalement pour le pied de page
$GLOBALS['etablissement'] = $etablissement;

// Configurer le document
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($etablissement['nom'] ?? 'eGestion');
$titre = ($type_export == 'pv') ? 
    "Procès-Verbal de Validation des Recours - " . $annee['designation'] :
    "Rapport Détaillé des Recours Validés - " . $annee['designation'];
$pdf->SetTitle($titre);
$pdf->SetSubject("Recours académiques");
$pdf->SetKeywords('Recours, Délibération, PV');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

// Définir les marges
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen

// Ajouter une page
$pdf->AddPage('L');

// Ajouter le logo en filigrane
if (!empty($etablissement['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $etablissement['logo'];
    if (file_exists($logoPath)) {
        // Sauvegarder l'état actuel
        $pdf->setAlpha(0.1);
        
        // Position au centre
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        
        // Définir une largeur plus petite mais conserver la même hauteur
        $logoWidth = 70;
        $logoHeight = 100;
        
        $x = ($pageWidth - $logoWidth) / 2;
        $y = ($pageHeight - $logoHeight) / 2;
        
        // Ajouter l'image en filigrane avec largeur réduite
        $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        
        // Restaurer l'état
        $pdf->setAlpha(1);
    }
}

// En-tête avec les informations de l'université
if ($etablissement) {
    // Logo de l'université (visible)
    if (!empty($etablissement['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $etablissement['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 20, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY(45, 12);
    $pdf->Cell(0, 6, strtoupper($etablissement['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetXY(45, 18);
    $pdf->Cell(0, 6, strtoupper($etablissement['nom'] ?? ''), 0, 1, 'C');
    
    if (!empty($etablissement['sigle'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(45, 24);
        $pdf->Cell(0, 5, $etablissement['sigle'], 0, 1, 'C');
    }
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(80, 80, 80);
    if (!empty($etablissement['adresse'])) {
        $pdf->SetXY(45, 29);
        $pdf->Cell(0, 4, $etablissement['adresse'], 0, 1, 'C');
    }
    
    $contactInfo = '';
    if (!empty($etablissement['telephone'])) {
        $contactInfo .= 'Tél: ' . $etablissement['telephone'] . ' ';
    }
    if (!empty($etablissement['email'])) {
        $contactInfo .= 'Email: ' . $etablissement['email'] . ' ';
    }
    if (!empty($etablissement['site_web'])) {
        $contactInfo .= 'Web: ' . $etablissement['site_web'];
    }
    
    if (!empty($contactInfo)) {
        $pdf->SetXY(45, 33);
        $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
    }
    
    // Ligne de séparation
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(15, 40, $pdf->getPageWidth() - 15, 40);
    
    
    
}

// Ajouter "Secrétariat Général Académique" en police calligraphique à gauche
$pdf->SetFont('times', 'I', 12); // Police Times en italique qui donne un aspect calligraphique
$pdf->SetTextColor(100, 100, 100); // Gris foncé pour un aspect officiel mais discret
$pdf->SetXY(15, 42);
$pdf->Cell(100, 6, 'Secrétariat Général Académique', 0, 1, 'L');

// Réinitialiser la couleur du texte pour la suite
$pdf->SetTextColor(80, 80, 80);



// Générer un QR code avec les informations du document
$qrCodeData = ($type_export == 'pv') ? "PROCÈS-VERBAL DE VALIDATION DES RECOURS\n" : "RAPPORT DÉTAILLÉ DES RECOURS\n";
$qrCodeData .= "Année académique: " . $annee['designation'] . "\n";
if ($session_info) {
    $qrCodeData .= "Session: " . $session_info['description'] . "\n";
}
if ($promotion_info) {
    $qrCodeData .= "Promotion: " . $promotion_info['designationPromotion'] . "\n";
}
$qrCodeData .= "Total recours: " . $total_recours . "\n";
$qrCodeData .= "Recours avec modification: " . $recours_avec_modification . "\n";
$qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
$qrCodeData .= isset($etablissement['site_web']) ? $etablissement['site_web'] : '';

// Ajouter le QR code en haut à droite
$style = array(
    'border' => false,
    'padding' => 2,
    'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]),
    'bgcolor' => array(255, 255, 255),
    'module_width' => 1,
    'module_height' => 1
);

// Positionner le QR code à droite dans l'en-tête
$pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $pdf->getPageWidth() - 45, 10, 25, 25, $style, 'N');

// Titre du document avec fond coloré
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Ln(15);
$pdf->Cell(0, 8, ($type_export == 'pv') ? 'PROCÈS-VERBAL DE VALIDATION DES RECOURS' : 'RAPPORT DÉTAILLÉ DES RECOURS VALIDÉS', 0, 1, 'C', 1);

// Sous-titre: année académique
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Année académique: ' . $annee['designation'], 0, 1, 'C');

// Si c'est un PV, ajouter la formule introductive et réduire les informations du jury
if ($type_export == 'pv' && !empty($bureaux)) {
    $bureau = reset($bureaux_membres)['bureau'];
    $membres_txt = '';
    
    foreach ($bureaux_membres as $bureau_info) {
        foreach ($bureau_info['membres'] as $membre) {
            $membres_txt .= $membre['noms'] . ' (' . $membre['fonction'] . '), ';
        }
    }
    
    $membres_txt = rtrim($membres_txt, ', ');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Ln(2);
    $pdf->MultiCell(0, 5, 'Nous, soussignés, ' . $bureau['president'] . ' (Président du Jury), ' . 
                     $bureau['secretaire'] . ' (Secrétaire) et ' . $membres_txt . 
                     ', membres du Bureau de Délibération, désignés par décision N°' . 
                     $bureau['numero_decision'] . ', certifions avoir examiné et validé les recours présentés ci-après pour ' . 
                     ($session_info ? 'la ' . $session_info['description'] : 'toutes les sessions') . 
                     ($promotion_info ? ' et pour la promotion ' . $promotion_info['designationPromotion'] : '') . '.', 0, 'L');
} else {
    // Informations sur les filtres appliqués
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Ln(2);
    $pdf->Cell(0, 6, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 60, $pdf->GetY());
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 9);
    
    if ($session_info) {
        $pdf->Cell(40, 5, 'Session:', 0, 0, 'L');
        $pdf->Cell(0, 5, $session_info['description'], 0, 1, 'L');
    }
    
    if ($promotion_info) {
        $pdf->Cell(40, 5, 'Promotion:', 0, 0, 'L');
        $pdf->Cell(0, 5, $promotion_info['designationPromotion'], 0, 1, 'L');
    }
    
    $pdf->Cell(40, 5, 'Document généré le:', 0, 0, 'L');
    $pdf->Cell(0, 5, date('d/m/Y H:i'), 0, 1, 'L');
    
    // Informations sur le jury si disponible
    if (!empty($bureaux)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Ln(2);
        $pdf->Cell(0, 6, 'COMPOSITION DU JURY', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(15, $pdf->GetY(), 60, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 9);
        
        foreach ($bureaux_membres as $bureau_info) {
            $bureau = $bureau_info['bureau'];
            $membres = $bureau_info['membres'];
            
            $pdf->Cell(40, 5, 'Bureau:', 0, 0, 'L');
            $pdf->Cell(0, 5, $bureau['designation'], 0, 1, 'L');
            
            $pdf->Cell(40, 5, 'Décision n°:', 0, 0, 'L');
            $pdf->Cell(0, 5, $bureau['numero_decision'], 0, 1, 'L');
            
            $pdf->Cell(40, 5, 'Président:', 0, 0, 'L');
            $pdf->Cell(0, 5, $bureau['president'], 0, 1, 'L');
            
            $pdf->Cell(40, 5, 'Secrétaire:', 0, 0, 'L');
            $pdf->Cell(0, 5, $bureau['secretaire'], 0, 1, 'L');
            
            if (!empty($membres)) {
                $pdf->Cell(40, 5, 'Membres:', 0, 0, 'L');
                $first_membre = array_shift($membres);
                $pdf->Cell(0, 5, $first_membre['noms'] . ' (' . $first_membre['fonction'] . ')', 0, 1, 'L');
                
                foreach ($membres as $membre) {
                    $pdf->Cell(40, 5, '', 0, 0, 'L');
                    $pdf->Cell(0, 5, $membre['noms'] . ' (' . $membre['fonction'] . ')', 0, 1, 'L');
                }
            }
            
            $pdf->Ln(2);
        }
    }
}

$pdf->Ln(5);

// Regrouper par promotion
$recours_by_promotion = [];
foreach ($recours as $r) {
    $promotion_key = $r['designationPromotion'];
    if (!isset($recours_by_promotion[$promotion_key])) {
        $recours_by_promotion[$promotion_key] = [];
    }
    $recours_by_promotion[$promotion_key][] = $r;
}

// Contenu principal selon le type de document
if ($type_export == 'pv') {
    // Procès-verbal simplifié
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'LISTE DES RECOURS VALIDÉS', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 60, $pdf->GetY());
    $pdf->Ln(2);
    
    // En-têtes du tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 6, 'N°', 1, 0, 'C', 1);
    $pdf->Cell(25, 6, 'Matricule', 1, 0, 'C', 1);
    $pdf->Cell(55, 6, 'Étudiant', 1, 0, 'C', 1);
    $pdf->Cell(95, 6, 'ECUE', 1, 0, 'C', 1);
    $pdf->Cell(20, 6, 'Av. CC', 1, 0, 'C', 1);
    $pdf->Cell(20, 6, 'Apr. CC', 1, 0, 'C', 1);
    $pdf->Cell(20, 6, 'Av. EX', 1, 0, 'C', 1);
    $pdf->Cell(17, 6, 'Apr. EX', 1, 0, 'C', 1);
    $pdf->Ln();
    
    // Données du tableau
    $pdf->SetFont('helvetica', '', 8);
    $i = 1;
    $lastPromotion = '';
    
    foreach ($recours as $r) {
        // Afficher un séparateur de promotion si on change de promotion
        if ($lastPromotion != $r['designationPromotion']) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(0, 5, 'Promotion: ' . $r['designationPromotion'], 1, 1, 'L', 1);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(245, 245, 245);
            $lastPromotion = $r['designationPromotion'];
        }
        
        // Calculer les valeurs avant et après modification
        $cc_avant = $r['cc_avant'] !== null ? number_format($r['cc_avant'], 2) : 'N/A';
        $ex_avant = $r['ex_avant'] !== null ? number_format($r['ex_avant'], 2) : 'N/A';
        $cc_apres = $r['nouvelle_note_cc'] !== null ? number_format($r['nouvelle_note_cc'], 2) : $cc_avant;
        $ex_apres = $r['nouvelle_note_ex'] !== null ? number_format($r['nouvelle_note_ex'], 2) : $ex_avant;
        
        // Afficher la ligne du tableau avec alternance de couleur
        $fill = ($i % 2 == 0) ? true : false;
        $pdf->Cell(15, 5, $i, 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, $r['matricule'], 1, 0, 'C', $fill);
        $pdf->Cell(55, 5, $r['nom_etudiant'], 1, 0, 'L', $fill);
        $pdf->Cell(95, 5, $r['designationECUE'], 1, 0, 'L', $fill);
        $pdf->Cell(20, 5, $cc_avant, 1, 0, 'C', $fill);
        $pdf->Cell(20, 5, $cc_apres, 1, 0, 'C', $fill);
        $pdf->Cell(20, 5, $ex_avant, 1, 0, 'C', $fill);
        $pdf->Cell(17, 5, $ex_apres, 1, 1, 'C', $fill);
        
        $i++;
        
        // Vérifier si on a besoin d'une nouvelle page
        if ($pdf->GetY() > 180) {
            $pdf->AddPage('L');
            
            // Répéter l'en-tête du tableau
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(15, 6, 'N°', 1, 0, 'C', 1);
            $pdf->Cell(25, 6, 'Matricule', 1, 0, 'C', 1);
            $pdf->Cell(55, 6, 'Étudiant', 1, 0, 'C', 1);
            $pdf->Cell(85, 6, 'ECUE', 1, 0, 'C', 1);
            $pdf->Cell(20, 6, 'Av. CC', 1, 0, 'C', 1);
            $pdf->Cell(20, 6, 'Apr. CC', 1, 0, 'C', 1);
            $pdf->Cell(20, 6, 'Av. EX', 1, 0, 'C', 1);
            $pdf->Cell(20, 6, 'Apr. EX', 1, 0, 'C', 1);
            $pdf->Ln();
            $pdf->SetFont('helvetica', '', 8);
        }
    }
    
    // Conclusion et attestation
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Ln(5);
    $pdf->MultiCell(0, 5, 'Les membres du jury, après avoir examiné avec soin les recours ci-dessus, confirment que les modifications apportées aux notes sont conformes aux règlements académiques et attestent de leur validité.', 0, 'L');
    
    // Espace pour les signatures
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(120, 5, 'Le Secrétaire du Jury', 0, 0, 'C');
    $pdf->Cell(120, 5, 'Le Président du Jury', 0, 1, 'C');
    $pdf->Ln(5); // Espace pour signature manuscrite
    
    if (!empty($bureaux)) {
        $bureau = reset($bureaux_membres)['bureau'];
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(120, 5, $bureau['secretaire'], 0, 0, 'C');
        $pdf->Cell(120, 5, $bureau['president'], 0, 1, 'C');
    }
    
} else {
    // Rapport détaillé
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'DÉTAILS DES RECOURS VALIDÉS', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 70, $pdf->GetY());
    $pdf->Ln(2);
    
    // Regrouper par promotion
    $recours_by_promotion = [];
    foreach ($recours as $r) {
        $promotion_key = $r['designationPromotion'];
        if (!isset($recours_by_promotion[$promotion_key])) {
            $recours_by_promotion[$promotion_key] = [];
        }
        $recours_by_promotion[$promotion_key][] = $r;
    }
    
    // Afficher chaque promotion
    foreach ($recours_by_promotion as $promotion => $promo_recours) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(0, 6, 'Promotion: ' . $promotion, 1, 1, 'L', 1);
        $pdf->Ln(1);
        
        // Afficher chaque recours de la promotion
        $i = 1;
        foreach ($promo_recours as $r) {
            // Calculer la nouvelle moyenne finale (MF) si disponible
            $mf_apres = null;
            
            // Si les deux composantes (CC et EX) sont modifiées, recalculer la MF
            if ($r['nouvelle_note_cc'] !== null && $r['nouvelle_note_ex'] !== null) {
                // Récupérer les pondérations depuis la configuration
                require_once '../models/Universite.php';
                $universite = new Universite();
                $ponderations = $universite->getPonderationsDefaut();
                $ponderation_cc = $ponderations['ponderation_cc'];
                $ponderation_ex = $ponderations['ponderation_ex'];
                $mf_apres = ($r['nouvelle_note_cc'] * $ponderation_cc) + ($r['nouvelle_note_ex'] * $ponderation_ex);
                $mf_apres = number_format($mf_apres, 2);
            } 
            // Si seulement une composante est modifiée, utiliser l'autre telle quelle
            elseif ($r['nouvelle_note_cc'] !== null) {
                // Récupérer les pondérations depuis la configuration
                require_once '../models/Universite.php';
                $universite = new Universite();
                $ponderations = $universite->getPonderationsDefaut();
                $ponderation_cc = $ponderations['ponderation_cc'];
                $ponderation_ex = $ponderations['ponderation_ex'];
                $ex = $r['ex_avant'] ?? 0;
                $mf_apres = ($r['nouvelle_note_cc'] * $ponderation_cc) + ($ex * $ponderation_ex);
                $mf_apres = number_format($mf_apres, 2);
            }
            elseif ($r['nouvelle_note_ex'] !== null) {
                // Récupérer les pondérations depuis la configuration
                require_once '../models/Universite.php';
                $universite = new Universite();
                $ponderations = $universite->getPonderationsDefaut();
                $ponderation_cc = $ponderations['ponderation_cc'];
                $ponderation_ex = $ponderations['ponderation_ex'];
                $cc = $r['cc_avant'] ?? 0;
                $mf_apres = ($cc * $ponderation_cc) + ($r['nouvelle_note_ex'] * $ponderation_ex);
                $mf_apres = number_format($mf_apres, 2);
            }
            
            // Cadre pour chaque recours avec design amélioré
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 6, 2, '1001', 'DF', array(), array(250, 250, 250));
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, 'Recours #' . $i . ' - ' . $r['matricule'] . ' - ' . $r['nom_etudiant'], 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            
            // Table layout avec colonnes équilibrées
            $pdf->Cell(35, 5, 'ECUE:', 1, 0, 'L', 1);
            $pdf->Cell(225, 5, $r['designationECUE'] . ' (' . $r['designationUE'] . ')', 1, 1, 'L', 1);
            
            $pdf->Cell(35, 5, 'Motif du recours:', 1, 0, 'L', 1);
            
            // Gérer les motifs longs
            $motif = $r['motif'];
            if (strlen($motif) > 130) {
                $motif = substr($motif, 0, 127) . '...';
            }
            $pdf->Cell(225, 5, $motif, 1, 1, 'L', 1);
            
            // Présentation des notes sur une ligne
            $pdf->Cell(35, 5, 'Avant modification:', 1, 0, 'L', 1);
            $notes_avant = 'CC: ' . ($r['cc_avant'] ? number_format($r['cc_avant'], 2) : 'N/A');
            $notes_avant .= ' | EX: ' . ($r['ex_avant'] ? number_format($r['ex_avant'], 2) : 'N/A');
            $notes_avant .= ' | MF: ' . ($r['mf_avant'] ? number_format($r['mf_avant'], 2) : 'N/A');
            $pdf->Cell(225, 5, $notes_avant, 1, 1, 'L', 1);
            
            $pdf->Cell(35, 5, 'Après modification:', 1, 0, 'L', 1);
            $notes_apres = 'CC: ' . ($r['nouvelle_note_cc'] ? number_format($r['nouvelle_note_cc'], 2) : ($r['cc_avant'] ? number_format($r['cc_avant'], 2) : 'N/A'));
            $notes_apres .= ' | EX: ' . ($r['nouvelle_note_ex'] ? number_format($r['nouvelle_note_ex'], 2) : ($r['ex_avant'] ? number_format($r['ex_avant'], 2) : 'N/A'));
            $notes_apres .= ' | MF: ' . ($mf_apres ?? ($r['mf_avant'] ? number_format($r['mf_avant'], 2) : 'N/A'));
            $pdf->Cell(225, 5, $notes_apres, 1, 1, 'L', 1);
            
            // Informations de validation
            $pdf->Cell(35, 5, 'Validé par:', 1, 0, 'L', 1);
            $validation_info = $r['validateur'] . ' (le ' . date('d/m/Y', strtotime($r['date_validation'])) . ')';
            if (!empty($r['nom_enseignant'])) {
                $validation_info .= ' — Enseignant: ' . $r['nom_enseignant'];
            }
            $pdf->Cell(225, 5, $validation_info, 1, 1, 'L', 1);
            
            $pdf->Ln(2);
            $i++;
            
            // Vérifier si on a besoin d'une nouvelle page
            if ($pdf->GetY() > 180) {
                $pdf->AddPage('L');
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 6, 'Promotion: ' . $promotion . ' (suite)', 0, 1, 'L');
                $pdf->Ln(1);
            }
        }
        
        $pdf->Ln(2);
    }
    
    // Conclusion
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Ln(3);
    $pdf->MultiCell(0, 5, 'Les membres du jury, après avoir examiné avec soin les recours ci-dessus, confirment que les modifications apportées aux notes sont conformes aux règlements académiques et attestent de leur validité.', 0, 'L');
    
    // Espace pour les signatures
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(120, 5, 'Le Secrétaire du Jury', 0, 0, 'C');
    $pdf->Cell(120, 5, 'Le Président du Jury', 0, 1, 'C');
    $pdf->Ln(5); // Espace pour signature manuscrite
    
    if (!empty($bureaux)) {
        $bureau = reset($bureaux_membres)['bureau'];
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(120, 5, $bureau['secretaire'], 0, 0, 'C');
        $pdf->Cell(120, 5, $bureau['president'], 0, 1, 'C');
    }
}

// Statistiques à la fin du document
$pdf->AddPage('L');

// Titre avec design amélioré
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'STATISTIQUES DES RECOURS', 0, 1, 'C', 1);
$pdf->SetTextColor(80, 80, 80);
$pdf->Ln(3);

// Calculer les statistiques
$total_recours = count($recours);
$recours_avec_modification = 0;
$recours_cc = 0;
$recours_ex = 0;
$recours_cc_ex = 0;

foreach ($recours as $r) {
    if ($r['nouvelle_note_cc'] !== null || $r['nouvelle_note_ex'] !== null) {
        $recours_avec_modification++;
        
        if ($r['nouvelle_note_cc'] !== null && $r['nouvelle_note_ex'] !== null) {
            $recours_cc_ex++;
        } elseif ($r['nouvelle_note_cc'] !== null) {
            $recours_cc++;
        } elseif ($r['nouvelle_note_ex'] !== null) {
            $recours_ex++;
        }
    }
}

// Afficher les statistiques générales avec style amélioré
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->Cell(0, 6, 'Statistiques générales', 0, 1, 'L');

// Ligne décorative sous le titre de section
$pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
$pdf->Line(15, $pdf->GetY(), 70, $pdf->GetY());
$pdf->Ln(2);

// Tableau de statistiques avec style amélioré
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(120, 6, 'Indicateur', 1, 0, 'L', 1);
$pdf->Cell(40, 6, 'Nombre', 1, 0, 'C', 1);
$pdf->Cell(40, 6, 'Pourcentage', 1, 1, 'C', 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(120, 6, 'Nombre total de recours validés', 1, 0, 'L');
$pdf->Cell(40, 6, $total_recours, 1, 0, 'C');
$pdf->Cell(40, 6, '100%', 1, 1, 'C');

$pdf->Cell(120, 6, 'Recours ayant entraîné une modification de notes', 1, 0, 'L');
$pdf->Cell(40, 6, $recours_avec_modification, 1, 0, 'C');
$pdf->Cell(40, 6, ($total_recours > 0 ? round(($recours_avec_modification / $total_recours) * 100) : 0) . '%', 1, 1, 'C');

$pdf->Cell(120, 6, 'Recours avec modification du CC uniquement', 1, 0, 'L');
$pdf->Cell(40, 6, $recours_cc, 1, 0, 'C');
$pdf->Cell(40, 6, ($total_recours > 0 ? round(($recours_cc / $total_recours) * 100) : 0) . '%', 1, 1, 'C');

$pdf->Cell(120, 6, 'Recours avec modification de l\'EX uniquement', 1, 0, 'L');
$pdf->Cell(40, 6, $recours_ex, 1, 0, 'C');
$pdf->Cell(40, 6, ($total_recours > 0 ? round(($recours_ex / $total_recours) * 100) : 0) . '%', 1, 1, 'C');

$pdf->Cell(120, 6, 'Recours avec modification du CC et de l\'EX', 1, 0, 'L');
$pdf->Cell(40, 6, $recours_cc_ex, 1, 0, 'C');
$pdf->Cell(40, 6, ($total_recours > 0 ? round(($recours_cc_ex / $total_recours) * 100) : 0) . '%', 1, 1, 'C');

$pdf->Ln(5);

// Statistiques par promotion si plusieurs promotions
if (count($recours_by_promotion) > 1) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 6, 'Statistiques par promotion', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 70, $pdf->GetY());
    $pdf->Ln(2);
    
    // En-têtes du tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(80, 6, 'Promotion', 1, 0, 'C', 1);
    $pdf->Cell(30, 6, 'Total recours', 1, 0, 'C', 1);
    $pdf->Cell(30, 6, 'Avec modif.', 1, 0, 'C', 1);
    $pdf->Cell(30, 6, 'CC seul', 1, 0, 'C', 1);
    $pdf->Cell(30, 6, 'EX seul', 1, 0, 'C', 1);
    $pdf->Cell(30, 6, 'CC et EX', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $i = 0;
    
    foreach ($recours_by_promotion as $promotion => $promo_recours) {
        $total_promo = count($promo_recours);
        $modif_promo = 0;
        $cc_promo = 0;
        $ex_promo = 0;
        $cc_ex_promo = 0;
        
        foreach ($promo_recours as $r) {
            if ($r['nouvelle_note_cc'] !== null || $r['nouvelle_note_ex'] !== null) {
                $modif_promo++;
                
                if ($r['nouvelle_note_cc'] !== null && $r['nouvelle_note_ex'] !== null) {
                    $cc_ex_promo++;
                } elseif ($r['nouvelle_note_cc'] !== null) {
                    $cc_promo++;
                } elseif ($r['nouvelle_note_ex'] !== null) {
                    $ex_promo++;
                }
            }
        }
        
        // Alternance de couleur pour les lignes
        $fill = ($i % 2 == 0) ? true : false;
        $pdf->Cell(80, 6, $promotion, 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, $total_promo, 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $modif_promo, 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $cc_promo, 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $ex_promo, 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $cc_ex_promo, 1, 1, 'C', $fill);
        $i++;
    }
}

// Graphique visuel - Exemple d'un petit graphique à barres horizontal basique
if ($total_recours > 0) {
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 6, 'Représentation visuelle des modifications', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(60, 60, 60);
    
    // Calcul des pourcentages
    $pct_modif = ($total_recours > 0) ? ($recours_avec_modification / $total_recours) * 100 : 0;
    $pct_cc = ($total_recours > 0) ? ($recours_cc / $total_recours) * 100 : 0;
    $pct_ex = ($total_recours > 0) ? ($recours_ex / $total_recours) * 100 : 0;
    $pct_cc_ex = ($total_recours > 0) ? ($recours_cc_ex / $total_recours) * 100 : 0;
    
    // Barre max width en mm
    $max_width = 150;
    
    // Barre 1: Avec modification
    $pdf->Cell(50, 6, 'Avec modification:', 0, 0, 'R');
    $bar_width = ($pct_modif / 100) * $max_width;
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell($bar_width, 6, '', 0, 0, 'L', 1);
    $pdf->Cell(15, 6, round($pct_modif) . '%', 0, 1, 'L');
    
    // Barre 2: CC uniquement
    $pdf->Cell(50, 6, 'CC uniquement:', 0, 0, 'R');
    $bar_width = ($pct_cc / 100) * $max_width;
    $pdf->SetFillColor(70, 130, 180); // Bleu acier
    $pdf->Cell($bar_width, 6, '', 0, 0, 'L', 1);
    $pdf->Cell(15, 6, round($pct_cc) . '%', 0, 1, 'L');
    
    // Barre 3: EX uniquement
    $pdf->Cell(50, 6, 'EX uniquement:', 0, 0, 'R');
    $bar_width = ($pct_ex / 100) * $max_width;
    $pdf->SetFillColor(0, 121, 194); // Bleu moyen
    $pdf->Cell($bar_width, 6, '', 0, 0, 'L', 1);
    $pdf->Cell(15, 6, round($pct_ex) . '%', 0, 1, 'L');
    
    // Barre 4: CC et EX
    $pdf->Cell(50, 6, 'CC et EX:', 0, 0, 'R');
    $bar_width = ($pct_cc_ex / 100) * $max_width;
    $pdf->SetFillColor(30, 144, 255); // Bleu doux
    $pdf->Cell($bar_width, 6, '', 0, 0, 'L', 1);
    $pdf->Cell(15, 6, round($pct_cc_ex) . '%', 0, 1, 'L');
}

// Pied de document
$pdf->SetY($pdf->GetPageHeight() - 25);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Document généré automatiquement par le système de gestion académique.', 0, 1, 'C');

// Générer le PDF
$filename = ($type_export == 'pv') ? 'PV_Recours_' : 'Rapport_Recours_';
$filename .= $annee['designation'] . '_' . date('Ymd_His') . '.pdf';

// Sortie du PDF
$pdf->Output($filename, 'I'); // 'I' pour afficher dans le navigateur
exit;
?>
