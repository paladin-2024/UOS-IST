<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérification de la session
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $universiteModel = new Universite();
    
    // Récupération de l'année académique (obligatoire)
    $anneeAcad = isset($_POST['annee_export']) ? intval($_POST['annee_export']) : getCurrentAcademicYear($db);
    if (!$anneeAcad) {
        throw new Exception('Année académique non spécifiée');
    }
    
    // Vérification des droits d'accès
    $currentUserId = $_SESSION['id']; 
    $hasFullAccess = $_SESSION['idRole'] == 1;
    
    // Récupération des sections autorisées
    $userSections = [];
    $sectionsToInclude = [];
    if (!$hasFullAccess) {
        $userSections = getUserSections($db, $currentUserId, $anneeAcad);
        if (empty($userSections)) {
            throw new Exception('Accès non autorisé - aucune section assignée');
        }
        $sectionsToInclude = $userSections;
    } else {
        // Si une section spécifique est demandée par l'admin
        if (!empty($_POST['section_export'])) {
            $sectionsToInclude = [intval($_POST['section_export'])];
        }
        // Sinon, toutes les sections (pas de filtre)
    }
    
    // Construction de la requête avec tous les filtres
    $query = "SELECT s.idsujets, s.intitule, s.cycle, s.statut_validation,
                 a.designation as annee,
                 spec.designation as specialisation,
                 sec.designationSection as section,
                 e.noms as etudiant_noms,
                 e.matricule as etudiant_matricule,
                 dir.noms as directeur_noms,
                 enc.noms as encadreur_noms,
                 ur.designation_UR as unite_recherche
              FROM sujets s
              LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN unite_recherche ur ON spec.idUnite_recherche = ur.idunite_recherche
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent
              WHERE s.annee_acad_idannee_acad = :anneeAcad";
    
    $params = [':anneeAcad' => $anneeAcad];
    
    // Filtrage par cycle
    if (!empty($_POST['cycle_export'])) {
        $query .= " AND s.cycle = :cycle";
        $params[':cycle'] = $_POST['cycle_export'];
    }
    
    // Filtrage par spécialisation
    if (!empty($_POST['specialisation_export'])) {
        $query .= " AND s.idSpecialisation = :specialisation";
        $params[':specialisation'] = intval($_POST['specialisation_export']);
    }
    
    // Filtrage par statut de validation
    if (!empty($_POST['statut_export'])) {
        $query .= " AND s.statut_validation = :statut";
        $params[':statut'] = $_POST['statut_export'];
    }
    
    // Filtrage par état d'affectation
    if (!empty($_POST['affectation_export'])) {
        switch ($_POST['affectation_export']) {
            case 'avec_etudiant':
                $query .= " AND s.etudiant_idetudiant IS NOT NULL";
                break;
            case 'sans_etudiant':
                $query .= " AND s.etudiant_idetudiant IS NULL";
                break;
            case 'avec_directeur':
                $query .= " AND s.idDirecteur IS NOT NULL";
                break;
            case 'sans_directeur':
                $query .= " AND s.idDirecteur IS NULL";
                break;
            case 'avec_encadreur':
                $query .= " AND s.idEncadreur IS NOT NULL";
                break;
            case 'sans_encadreur':
                $query .= " AND s.idEncadreur IS NULL";
                break;
            case 'complet':
                $query .= " AND s.etudiant_idetudiant IS NOT NULL AND s.idDirecteur IS NOT NULL AND s.idEncadreur IS NOT NULL";
                break;
            case 'incomplet':
                $query .= " AND (s.etudiant_idetudiant IS NULL OR s.idDirecteur IS NULL OR s.idEncadreur IS NULL)";
                break;
        }
    }
    
    // Ajouter le filtrage par section si nécessaire
    if (!empty($sectionsToInclude)) {
        $sectionsPlaceholders = [];
        foreach ($sectionsToInclude as $index => $sectionId) {
            $placeholderName = ':section' . $index;
            $sectionsPlaceholders[] = $placeholderName;
            $params[$placeholderName] = $sectionId;
        }
        $query .= " AND sec.idsection IN (" . implode(',', $sectionsPlaceholders) . ")";
    }
    
    // Ordre de tri selon le groupement demandé
    $groupement = $_POST['groupement_export'] ?? 'aucun';
    switch ($groupement) {
        case 'specialisation':
            $query .= " ORDER BY spec.designation, s.intitule";
            break;
        case 'cycle':
            $query .= " ORDER BY s.cycle, spec.designation, s.intitule";
            break;
        case 'statut':
            $query .= " ORDER BY s.statut_validation, spec.designation, s.intitule";
            break;
        case 'section':
            $query .= " ORDER BY sec.designationSection, spec.designation, s.intitule";
            break;
        default:
            $query .= " ORDER BY sec.designationSection, spec.designation, s.intitule";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sujets)) {
        throw new Exception('Aucun sujet trouvé pour cette année académique');
    }
    
    // Récupération des informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Créer une instance de TCPDF en mode paysage
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Export Sujets de Recherche - ' . $sujets[0]['annee']);
    $pdf->SetSubject('Liste des sujets de recherche');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Couleurs pour le design
    $primaryColor = array(0, 87, 146);
    $secondaryColor = array(70, 130, 180);
    $accentColor = array(0, 121, 194);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Ajouter le logo en filigrane
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->setAlpha(0.1);
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            $logoWidth = 70;
            $logoHeight = 100;
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
            $pdf->setAlpha(1);
        }
    }

    // En-tête avec les informations de l'université
    if ($configUniversite) {
        // Logo de l'université (visible)
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 15, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        // Titre et informations de l'université
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetY(15);
        $pdf->Cell(0, 8, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
        
        if (!empty($configUniversite['sigle'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($configUniversite['adresse'])) {
            $pdf->Cell(0, 4, $configUniversite['adresse'], 0, 1, 'C');
        }
        
        $contactInfo = '';
        if (!empty($configUniversite['telephone'])) {
            $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
        }
        if (!empty($configUniversite['email'])) {
            $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
        }
        if (!empty($configUniversite['site_web'])) {
            $contactInfo .= 'Web: ' . $configUniversite['site_web'];
        }
        
        if (!empty($contactInfo)) {
            $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
        }
        
        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 48, $pdf->getPageWidth() - 15, 48);
    }

    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'LISTE DES SUJETS DE RECHERCHE', 0, 1, 'C', 1);

    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Année académique : ' . $sujets[0]['annee'] . ' | Total : ' . count($sujets) . ' sujets', 0, 1, 'C');
    
    // Afficher les filtres appliqués
    $filtresAppliques = [];
    
    if (!empty($_POST['cycle_export'])) {
        $cycleLabels = [
            'Premier' => 'Licence',
            'Deuxieme' => 'Master', 
            'Troisieme' => 'Doctorat'
        ];
        $filtresAppliques[] = 'Cycle: ' . ($cycleLabels[$_POST['cycle_export']] ?? $_POST['cycle_export']);
    }
    
    if (!empty($_POST['specialisation_export'])) {
        // Récupérer le nom de la spécialisation
        $querySpec = "SELECT designation FROM specialisation WHERE idSpecialisation = :id";
        $stmtSpec = $db->prepare($querySpec);
        $stmtSpec->execute([':id' => $_POST['specialisation_export']]);
        $specResult = $stmtSpec->fetch(PDO::FETCH_ASSOC);
        if ($specResult) {
            $filtresAppliques[] = 'Spécialisation: ' . $specResult['designation'];
        }
    }
    
    if (!empty($_POST['statut_export'])) {
        $filtresAppliques[] = 'Statut: ' . $_POST['statut_export'];
    }
    
    if (!empty($_POST['affectation_export'])) {
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
        $filtresAppliques[] = 'Affectation: ' . ($affectationLabels[$_POST['affectation_export']] ?? $_POST['affectation_export']);
    }
    
    // Afficher les sections incluses
    if (!empty($sectionsToInclude)) {
        $sectionsNames = [];
        foreach ($sujets as $sujet) {
            if (!empty($sujet['section']) && !in_array($sujet['section'], $sectionsNames)) {
                $sectionsNames[] = $sujet['section'];
            }
        }
        if (!empty($sectionsNames)) {
            $filtresAppliques[] = 'Sections: ' . implode(', ', $sectionsNames);
        }
    }
    
    // Afficher les filtres appliqués
    if (!empty($filtresAppliques)) {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 6, 'Filtres appliqués: ' . implode(' | ', $filtresAppliques), 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    
    // Gestion des colonnes sélectionnées
    $colonnesSelectionnees = isset($_POST['colonnes']) ? $_POST['colonnes'] : [
        'intitule', 'cycle', 'specialisation', 'statut', 'annee', 'etudiant', 'directeur'
    ];
    
    // Définition des colonnes disponibles avec leurs largeurs par défaut
    $colonnesDisponibles = [
        'intitule' => ['titre' => 'Intitulé du sujet', 'largeur' => 85],
        'cycle' => ['titre' => 'Cycle', 'largeur' => 20],
        'specialisation' => ['titre' => 'Spécialisation', 'largeur' => 45],
        'section' => ['titre' => 'Section', 'largeur' => 25],
        'statut' => ['titre' => 'Statut', 'largeur' => 22],
        'annee' => ['titre' => 'Année', 'largeur' => 20],
        'etudiant' => ['titre' => 'Étudiant', 'largeur' => 38],
        'directeur' => ['titre' => 'Directeur', 'largeur' => 35],
        'encadreur' => ['titre' => 'Encadreur', 'largeur' => 35],
        'unite_recherche' => ['titre' => 'Unité de recherche', 'largeur' => 40]
    ];
    
    // Construire les colonnes à afficher
    $colonnesAAfficher = ['num' => ['titre' => 'N°', 'largeur' => 12]]; // Toujours inclure le numéro
    foreach ($colonnesSelectionnees as $colonne) {
        if (isset($colonnesDisponibles[$colonne])) {
            $colonnesAAfficher[$colonne] = $colonnesDisponibles[$colonne];
        }
    }
    
    // Calculer les largeurs proportionnelles
    $largeurDisponible = 267; // 297mm - 30mm marges
    $largeurTotale = array_sum(array_column($colonnesAAfficher, 'largeur'));
    
    if ($largeurTotale > $largeurDisponible) {
        $ratio = $largeurDisponible / $largeurTotale;
        foreach ($colonnesAAfficher as $key => $colonne) {
            $colonnesAAfficher[$key]['largeur'] = $colonne['largeur'] * $ratio;
        }
    }
    
    // Tableau avec largeurs ajustées pour le format paysage
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 8);
    
    // Créer les en-têtes du tableau
    foreach ($colonnesAAfficher as $key => $colonne) {
        $pdf->Cell($colonne['largeur'], 8, $colonne['titre'], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Données avec gestion des textes longs
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(40, 40, 40);
    
    foreach ($sujets as $index => $sujet) {
        // Vérifier si on a besoin d'une nouvelle page
        if ($pdf->GetY() > 190) { // Ajusté pour le format paysage
            $pdf->AddPage();
            
            // Répéter les en-têtes
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 8);
            
            foreach ($colonnesAAfficher as $key => $colonne) {
                $pdf->Cell($colonne['largeur'], 8, $colonne['titre'], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(40, 40, 40);
        }
        
        // Couleur de fond alternée
        $fill = ($index % 2 == 0) ? 0 : 1;
        if ($fill) {
            $pdf->SetFillColor(250, 250, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Préparer toutes les données possibles
        $donneesLigne = [
            'num' => $index + 1,
            'intitule' => $sujet['intitule'] ?? '',
            'cycle' => $sujet['cycle'] ?? '',
            'specialisation' => $sujet['specialisation'] ?? 'Non définie',
            'section' => $sujet['section'] ?? 'Non définie',
            'statut' => $sujet['statut_validation'] ?? 'En attente',
            'annee' => $sujet['annee'] ?? '',
            'etudiant' => !empty($sujet['etudiant_noms']) ? $sujet['etudiant_noms'] : 'Non assigné',
            'directeur' => !empty($sujet['directeur_noms']) ? $sujet['directeur_noms'] : 'Non assigné',
            'encadreur' => !empty($sujet['encadreur_noms']) ? $sujet['encadreur_noms'] : 'Non assigné',
            'unite_recherche' => $sujet['unite_recherche'] ?? 'Non définie'
        ];
        
        // Ajouter le matricule à l'étudiant si disponible
        if (!empty($sujet['etudiant_matricule'])) {
            $donneesLigne['etudiant'] .= ' (' . $sujet['etudiant_matricule'] . ')';
        }
        
        // Limiter la longueur des textes selon la largeur de colonne
        foreach ($donneesLigne as $key => $valeur) {
            if (isset($colonnesAAfficher[$key])) {
                $largeurColonne = $colonnesAAfficher[$key]['largeur'];
                $maxChars = intval($largeurColonne * 1.2); // Approximation
                
                if (strlen($valeur) > $maxChars) {
                    $donneesLigne[$key] = substr($valeur, 0, $maxChars - 3) . '...';
                }
            }
        }
        
        // Calculer la hauteur nécessaire pour chaque cellule visible
        $hauteurs = [];
        foreach ($colonnesAAfficher as $key => $colonne) {
            if (isset($donneesLigne[$key])) {
                $hauteurs[$key] = $pdf->getNumLines($donneesLigne[$key], $colonne['largeur']) * 3.5;
            }
        }
        
        // Prendre la hauteur maximale, avec un minimum de 6
        $hauteurLigne = max(6, !empty($hauteurs) ? max($hauteurs) : 6);
        
        // Sauvegarder la position Y actuelle
        $startY = $pdf->GetY();
        $currentX = $pdf->GetX();
        $offsetX = 0;
        
        // Créer les cellules pour chaque colonne sélectionnée
        foreach ($colonnesAAfficher as $key => $colonne) {
            $valeur = $donneesLigne[$key] ?? '';
            $largeur = $colonne['largeur'];
            
            $pdf->SetXY($currentX + $offsetX, $startY);
            
            // Gestion spéciale pour le statut (couleur)
            if ($key === 'statut') {
                $statutColor = array(60, 60, 60);
                switch ($valeur) {
                    case 'Validé':
                        $statutColor = array(40, 167, 69);
                        break;
                    case 'Rejeté':
                        $statutColor = array(220, 53, 69);
                        break;
                    case 'En attente':
                        $statutColor = array(255, 193, 7);
                        break;
                }
                $pdf->SetTextColor($statutColor[0], $statutColor[1], $statutColor[2]);
                $pdf->Cell($largeur, $hauteurLigne, $valeur, 1, 0, 'C', $fill);
                $pdf->SetTextColor(40, 40, 40); // Remettre la couleur par défaut
            } else if ($key === 'num') {
                // Numéro centré
                $pdf->Cell($largeur, $hauteurLigne, $valeur, 1, 0, 'C', $fill);
            } else if (in_array($key, ['cycle', 'section', 'annee'])) {
                // Colonnes courtes centrées
                $pdf->Cell($largeur, $hauteurLigne, $valeur, 1, 0, 'C', $fill);
            } else {
                // Autres colonnes avec MultiCell pour texte long
                $pdf->MultiCell($largeur, $hauteurLigne, $valeur, 1, 'L', $fill, 0);
            }
            
            $offsetX += $largeur;
        }
        
        // Positionner le curseur à la fin de la ligne
        $pdf->SetY($startY + $hauteurLigne);
    }
    
    // Pied de page
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 4, 'Document généré le ' . date('d/m/Y à H:i'), 0, 1, 'L');
    
    // Nettoyer la sortie et envoyer le PDF
    ob_clean();
    
    // Nom du fichier
    $fileName = 'Sujets_Recherche_Simple_' . date('Y-m-d') . '.pdf';
    
    // Sortie du PDF
    $pdf->Output($fileName, 'I');

} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur génération PDF sujets: " . $e->getMessage());
    
    echo "<script>
        alert('Erreur lors de la génération du PDF: " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}
?>
