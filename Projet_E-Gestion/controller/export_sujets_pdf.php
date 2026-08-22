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

// Vérification des responsabilités de l'utilisateur connecté
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $universiteModel = new Universite();
    
    // Récupération de l'année académique
    $anneeAcad = isset($_POST['annee_export']) ? intval($_POST['annee_export']) : getCurrentAcademicYear($db);
    if (!$anneeAcad) {
        throw new Exception('Année académique non spécifiée');
    }
    
    // Vérification des droits d'accès
    $userSections = [];
    if (!$hasFullAccess) {
        $userSections = getUserSections($db, $currentUserId, $anneeAcad);
        if (empty($userSections)) {
            throw new Exception('Accès non autorisé');
        }
    }
    
    // Construction de la requête selon les filtres
    $params = [':anneeAcad' => $anneeAcad];
    $whereConditions = ['s.annee_acad_idannee_acad = :anneeAcad'];
    
    // Filtres de base
    if (!empty($_POST['cycle_export'])) {
        $whereConditions[] = 's.cycle = :cycle';
        $params[':cycle'] = $_POST['cycle_export'];
    }
    
    if (!empty($_POST['specialisation_export'])) {
        $whereConditions[] = 's.idSpecialisation = :specialisation';
        $params[':specialisation'] = $_POST['specialisation_export'];
    }
    
    if (!empty($_POST['statut_export'])) {
        $whereConditions[] = 's.statut_validation = :statut';
        $params[':statut'] = $_POST['statut_export'];
    }
    
    // Filtres d'affectation
    if (!empty($_POST['affectation_export'])) {
        switch ($_POST['affectation_export']) {
            case 'avec_etudiant':
                $whereConditions[] = 's.etudiant_idetudiant IS NOT NULL';
                break;
            case 'sans_etudiant':
                $whereConditions[] = 's.etudiant_idetudiant IS NULL';
                break;
            case 'avec_directeur':
                $whereConditions[] = 's.idDirecteur IS NOT NULL';
                break;
            case 'sans_directeur':
                $whereConditions[] = 's.idDirecteur IS NULL';
                break;
            case 'avec_encadreur':
                $whereConditions[] = 's.idEncadreur IS NOT NULL';
                break;
            case 'sans_encadreur':
                $whereConditions[] = 's.idEncadreur IS NULL';
                break;
            case 'complet':
                $whereConditions[] = 's.etudiant_idetudiant IS NOT NULL AND s.idDirecteur IS NOT NULL AND s.idEncadreur IS NOT NULL';
                break;
            case 'incomplet':
                $whereConditions[] = '(s.etudiant_idetudiant IS NULL OR s.idDirecteur IS NULL OR s.idEncadreur IS NULL)';
                break;
        }
    }
    
    // Restriction par section pour les responsables
    if (!$hasFullAccess) {
        $sectionsPlaceholders = [];
        foreach ($userSections as $index => $sectionId) {
            $placeholderName = ':userSection' . $index;
            $sectionsPlaceholders[] = $placeholderName;
            $params[$placeholderName] = $sectionId;
        }
        $whereConditions[] = "sec.idsection IN (" . implode(',', $sectionsPlaceholders) . ")";
    } elseif (!empty($_POST['section_export'])) {
        $whereConditions[] = 'sec.idsection = :section';
        $params[':section'] = $_POST['section_export'];
    }
    
    // Requête principale - construire étape par étape pour éviter les erreurs
    $query = "SELECT s.*, 
                 a.designation as annee, 
                 spec.designation as specialisation,
                 ur.designation_UR as unite_recherche,
                 sec.designationSection as section,
                 o.designationOrientation as orientation,
                 e.noms as etudiant_noms,
                 e.matricule as etudiant_matricule,
                 dir.noms as directeur_noms,
                 enc.noms as encadreur_noms
              FROM sujets s
              LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN unite_recherche ur ON spec.idUnite_recherche = ur.idunite_recherche
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent dir ON s.idDirecteur = dir.idAgent
              LEFT JOIN agent enc ON s.idEncadreur = enc.idAgent";
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(' AND ', $whereConditions);
    }
    
    $query .= " ORDER BY sec.designationSection, spec.designation, s.intitule";
    
    // Debug des paramètres pour identifier le problème
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sujets)) {
        throw new Exception('Aucun sujet trouvé avec les critères spécifiés');
    }
    
    // Récupération des informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Créer une instance de TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Export Sujets de Recherche - ' . $sujets[0]['annee']);
    $pdf->SetSubject('Liste des sujets de recherche');
    $pdf->SetKeywords('Sujets, Recherche, Export, PDF');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Couleurs pour le design
    $primaryColor = array(0, 87, 146); // Bleu foncé
    $secondaryColor = array(70, 130, 180); // Bleu acier
    $accentColor = array(0, 121, 194); // Bleu moyen
    
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
    
    // Informations sur les filtres appliqués
    $filtresInfo = [];
    if (!empty($_POST['cycle_export'])) {
        $filtresInfo[] = 'Cycle: ' . $_POST['cycle_export'];
    }
    if (!empty($_POST['specialisation_export'])) {
        // Récupérer le nom de la spécialisation
        $querySpec = "SELECT designation FROM specialisation WHERE idSpecialisation = :id";
        $stmtSpec = $db->prepare($querySpec);
        $stmtSpec->execute(['id' => $_POST['specialisation_export']]);
        $specResult = $stmtSpec->fetch(PDO::FETCH_ASSOC);
        if ($specResult) {
            $filtresInfo[] = 'Spécialisation: ' . $specResult['designation'];
        }
    }
    if (!empty($_POST['statut_export'])) {
        $filtresInfo[] = 'Statut: ' . $_POST['statut_export'];
    }
    if (!empty($_POST['section_export'])) {
        // Récupérer le nom de la section
        $querySection = "SELECT designationSection FROM section WHERE idsection = :id";
        $stmtSection = $db->prepare($querySection);
        $stmtSection->execute(['id' => $_POST['section_export']]);
        $sectionResult = $stmtSection->fetch(PDO::FETCH_ASSOC);
        if ($sectionResult) {
            $filtresInfo[] = 'Section: ' . $sectionResult['designationSection'];
        }
    }
    if (!empty($_POST['affectation_export'])) {
        $affectationLabels = [
            'avec_etudiant' => 'Avec étudiant',
            'sans_etudiant' => 'Sans étudiant',
            'avec_directeur' => 'Avec directeur',
            'sans_directeur' => 'Sans directeur',
            'avec_encadreur' => 'Avec encadreur',
            'sans_encadreur' => 'Sans encadreur',
            'complet' => 'Complètement affecté',
            'incomplet' => 'Affectation incomplète'
        ];
        $filtresInfo[] = 'Affectation: ' . ($affectationLabels[$_POST['affectation_export']] ?? $_POST['affectation_export']);
    }
    if (!$hasFullAccess && !empty($userSections)) {
        // Afficher les sections de l'utilisateur
        $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
        $queryUserSections = "SELECT designationSection FROM section WHERE idsection IN ($sectionsParams)";
        $stmtUserSections = $db->prepare($queryUserSections);
        $stmtUserSections->execute($userSections);
        $userSectionsNames = $stmtUserSections->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($userSectionsNames)) {
            $filtresInfo[] = 'Sections autorisées: ' . implode(', ', $userSectionsNames);
        }
    }
    
    if (!empty($filtresInfo)) {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 6, 'Filtres appliqués : ' . implode(' | ', $filtresInfo), 0, 1, 'C');
    }
    
    $pdf->Ln(5);
    
    // Groupement des données si demandé
    $groupement = $_POST['groupement_export'] ?? 'aucun';
    $groupedSujets = [];
    
    if ($groupement === 'aucun') {
        $groupedSujets['Tous les sujets'] = $sujets;
    } else {
        foreach ($sujets as $sujet) {
            $key = '';
            switch ($groupement) {
                case 'specialisation':
                    $key = $sujet['specialisation'] ?? 'Non définie';
                    break;
                case 'cycle':
                    $key = $sujet['cycle'] ?? 'Non défini';
                    break;
                case 'statut':
                    $key = $sujet['statut_validation'] ?? 'Non défini';
                    break;
                case 'section':
                    $key = $sujet['section'] ?? 'Non définie';
                    break;
                default:
                    $key = 'Autres';
            }
            
            if (!isset($groupedSujets[$key])) {
                $groupedSujets[$key] = [];
            }
            $groupedSujets[$key][] = $sujet;
        }
    }
    
    // Colonnes sélectionnées
    $colonnes = $_POST['colonnes'] ?? ['intitule', 'cycle', 'specialisation', 'statut', 'etudiant', 'directeur'];
    
    // Génération du contenu pour chaque groupe
    foreach ($groupedSujets as $groupName => $groupSujets) {
        // Titre du groupe (sauf si "Tous les sujets")
        if ($groupName !== 'Tous les sujets') {
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, strtoupper($groupName) . ' (' . count($groupSujets) . ' sujets)', 0, 1, 'L');
            
            $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(15, $pdf->GetY(), $pdf->getPageWidth() - 15, $pdf->GetY());
            $pdf->Ln(3);
        }
        
        // Tableau des sujets
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 8);
        
        // En-têtes du tableau
        $currentY = $pdf->GetY();
        if ($currentY > 250) { // Si proche du bas de page, nouvelle page
            $pdf->AddPage();
        }
        
        $tableWidth = $pdf->getPageWidth() - 30;
        $numCols = count($colonnes);
        $colWidth = $tableWidth / $numCols;
        
        // Ajuster la largeur des colonnes selon le contenu
        $colWidths = [];
        foreach ($colonnes as $col) {
            switch ($col) {
                case 'intitule':
                    $colWidths[$col] = $tableWidth * 0.3; // 30% pour l'intitulé
                    break;
                case 'cycle':
                    $colWidths[$col] = $tableWidth * 0.1; // 10% pour le cycle
                    break;
                case 'statut':
                    $colWidths[$col] = $tableWidth * 0.12; // 12% pour le statut
                    break;
                default:
                    $colWidths[$col] = $tableWidth * 0.16; // 16% pour les autres
                    break;
            }
        }
        
        // En-têtes
        foreach ($colonnes as $col) {
            $header = '';
            switch ($col) {
                case 'intitule': $header = 'Intitulé du sujet'; break;
                case 'cycle': $header = 'Cycle'; break;
                case 'specialisation': $header = 'Spécialisation'; break;
                case 'statut': $header = 'Statut'; break;
                case 'etudiant': $header = 'Étudiant'; break;
                case 'directeur': $header = 'Directeur'; break;
                case 'encadreur': $header = 'Encadreur'; break;
                case 'section': $header = 'Section'; break;
                case 'unite_recherche': $header = 'Unité recherche'; break;
                case 'annee': $header = 'Année'; break;
            }
            $pdf->Cell($colWidths[$col], 8, $header, 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Données
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(40, 40, 40);
        
        foreach ($groupSujets as $index => $sujet) {
            // Vérifier si on a besoin d'une nouvelle page
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                
                // Répéter les en-têtes
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(60, 60, 60);
                foreach ($colonnes as $col) {
                    $header = '';
                    switch ($col) {
                        case 'intitule': $header = 'Intitulé du sujet'; break;
                        case 'cycle': $header = 'Cycle'; break;
                        case 'specialisation': $header = 'Spécialisation'; break;
                        case 'statut': $header = 'Statut'; break;
                        case 'etudiant': $header = 'Étudiant'; break;
                        case 'directeur': $header = 'Directeur'; break;
                        case 'encadreur': $header = 'Encadreur'; break;
                        case 'section': $header = 'Section'; break;
                        case 'unite_recherche': $header = 'Unité recherche'; break;
                        case 'annee': $header = 'Année'; break;
                    }
                    $pdf->Cell($colWidths[$col], 8, $header, 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(40, 40, 40);
            }
            
            // Couleur de fond alternée
            $fill = ($index % 2 == 0) ? 0 : 1;
            if ($fill) {
                $pdf->SetFillColor(250, 250, 250);
            }
            
            foreach ($colonnes as $col) {
                $value = '';
                switch ($col) {
                    case 'intitule':
                        $value = $sujet['intitule'] ?? '';
                        break;
                    case 'cycle':
                        $value = $sujet['cycle'] ?? '';
                        break;
                    case 'specialisation':
                        $value = $sujet['specialisation'] ?? 'Non définie';
                        break;
                    case 'statut':
                        $value = $sujet['statut_validation'] ?? 'En attente';
                        break;
                    case 'etudiant':
                        $value = !empty($sujet['etudiant_noms']) ? 
                                 $sujet['etudiant_noms'] . (!empty($sujet['etudiant_matricule']) ? ' (' . $sujet['etudiant_matricule'] . ')' : '') : 
                                 'Non assigné';
                        break;
                    case 'directeur':
                        $value = $sujet['directeur_noms'] ?? 'Non assigné';
                        break;
                    case 'encadreur':
                        $value = $sujet['encadreur_noms'] ?? 'Non assigné';
                        break;
                    case 'section':
                        $value = $sujet['section'] ?? 'Non définie';
                        break;
                    case 'unite_recherche':
                        $value = $sujet['unite_recherche'] ?? 'Non définie';
                        break;
                    case 'annee':
                        $value = $sujet['annee'] ?? '';
                        break;
                }
                
                // Limiter la longueur du texte selon la colonne
                $maxLength = ($col === 'intitule') ? 50 : 25;
                if (strlen($value) > $maxLength) {
                    $value = substr($value, 0, $maxLength) . '...';
                }
                
                $pdf->Cell($colWidths[$col], 6, $value, 1, 0, 'L', $fill);
            }
            $pdf->Ln();
        }
        
        $pdf->Ln(5);
    }
    
    // Statistiques si demandées
    if (isset($_POST['inclure_statistiques']) && $_POST['inclure_statistiques'] == '1') {
        $pdf->AddPage();
        
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'STATISTIQUES', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Statistiques par statut
        $stats = [];
        foreach ($sujets as $sujet) {
            $statut = $sujet['statut_validation'] ?? 'En attente';
            if (!isset($stats[$statut])) {
                $stats[$statut] = 0;
            }
            $stats[$statut]++;
        }
        
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Répartition par statut de validation', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        
        foreach ($stats as $statut => $count) {
            $pourcentage = round(($count / count($sujets)) * 100, 1);
            $pdf->Cell(50, 6, $statut . ':', 0, 0, 'L');
            $pdf->Cell(30, 6, $count . ' (' . $pourcentage . '%)', 0, 1, 'L');
        }
    }
    
    // Pied de page avec informations
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 4, 'Document généré automatiquement le ' . date('d/m/Y à H:i'), 0, 1, 'L');
    $pdf->Cell(0, 4, 'Référence: SRS-' . date('YmdHis'), 0, 1, 'L');
    
    // Nettoyer la sortie et envoyer le PDF
    ob_clean();
    
    // Nom du fichier
    $fileName = 'Sujets_Recherche_' . $sujets[0]['annee'] . '_' . date('Y-m-d') . '.pdf';
    
    // Sortie du PDF
    $pdf->Output($fileName, 'I'); // 'I' pour affichage dans le navigateur, 'D' pour téléchargement

} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur génération PDF sujets: " . $e->getMessage());
    
    echo "<script>
        alert('Erreur lors de la génération du PDF: " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}
?>
