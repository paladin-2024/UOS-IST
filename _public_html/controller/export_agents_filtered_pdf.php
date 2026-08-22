<?php
ob_start();
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Grade.php';
require_once dirname(__DIR__) . '/models/Service.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Récupérer les filtres du formulaire
$filters = [
    'type_agent' => $_POST['type_agent'] ?? '',
    'grade_id' => $_POST['grade_id'] ?? '',
    'sexe' => $_POST['sexe'] ?? '',
    'idStructure' => $_POST['idStructure'] ?? '',
    'idService' => $_POST['idService'] ?? '',
    'annee_engagement' => $_POST['annee_engagement'] ?? '',
    'prime_locale' => $_POST['prime_locale'] ?? '',
    'salaire_etat' => $_POST['salaire_etat'] ?? '',
    'prime_institutionnelle' => $_POST['prime_institutionnelle'] ?? '',
    'niveauEtude' => $_POST['niveauEtude'] ?? '',
    'etatCivil' => $_POST['etatCivil'] ?? '',
    'search' => $_POST['search'] ?? ''
];

// Type d'exportation
$exportType = $_POST['export_type'] ?? 'liste_simple';

// Colonnes à inclure
$columns = $_POST['columns'] ?? ['code', 'matricule', 'noms', 'sexe', 'grade', 'type'];

// Initialiser les modèles
$agentModel = new Agent();
$gradeModel = new Grade();
$serviceModel = new Service();
$structureModel = new Structure();
$universiteModel = new Universite();

// Récupérer les agents filtrés
$agents = $agentModel->getFilteredAgents2($filters);

// Récupérer les informations de l'université
$configUniversite = $universiteModel->getConfigurationUniversite();

// Fonction pour rogner une image pour ne garder que le visage
function cropFaceFromImage($imagePath) {
    // Vérifier si l'extension GD est disponible
    if (!extension_loaded('gd')) {
        return $imagePath; // Retourner l'image originale si GD n'est pas disponible
    }
    
    // Créer un nom de fichier temporaire pour l'image rognée
    $tempFile = sys_get_temp_dir() . '/' . uniqid('face_') . '.jpg';
    
    // Déterminer le type d'image
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return $imagePath;
    }
    
    // Créer une ressource d'image selon le type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        default:
            return $imagePath; // Type d'image non supporté
    }
    
    if (!$sourceImage) {
        return $imagePath;
    }
    
    // Dimensions de l'image source
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    
    // Définir la zone de rognage (ici, nous prenons le centre de l'image)
    $cropSize = min($width, $height);
    $x = ($width - $cropSize) / 2;
    $y = ($height - $cropSize) / 4; // Décaler vers le haut pour mieux cadrer le visage
    
    // Créer une nouvelle image carrée
    $croppedImage = imagecreatetruecolor($cropSize, $cropSize);
    
    // Préserver la transparence pour les PNG
    if ($imageInfo[2] == IMAGETYPE_PNG) {
        imagealphablending($croppedImage, false);
        imagesavealpha($croppedImage, true);
        $transparent = imagecolorallocatealpha($croppedImage, 255, 255, 255, 127);
        imagefilledrectangle($croppedImage, 0, 0, $cropSize, $cropSize, $transparent);
    }
    
    // Copier et rogner l'image
    imagecopy($croppedImage, $sourceImage, 0, 0, $x, $y, $cropSize, $cropSize);
    
    // Enregistrer l'image rognée
    imagejpeg($croppedImage, $tempFile, 90);
    
    // Libérer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($croppedImage);
    
    return $tempFile;
}

// Classe PDF personnalisée
class MYPDF extends TCPDF {
    public function Header() {
        // Cette méthode sera vide pour personnaliser l'en-tête manuellement
    }
    
    public function Footer() {
        // Position à 15 mm du bas
        $this->SetY(-15);
        // Police Arial italique 8
        $this->SetFont('dejavusans', 'I', 8);
        // Numéro de page
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Créer une instance de TCPDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8');

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Liste des Agents');
$pdf->SetSubject('Rapport du personnel');
$pdf->SetKeywords('Agents, Personnel, Liste');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

// Définir les marges
$pdf->SetMargins(10, 15, 10);

// Définir la police par défaut
$pdf->SetFont('dejavusans', '', 10);

// Ajouter une page
$pdf->AddPage();

// En-tête avec les informations de l'université
if ($configUniversite) {
    // Logo de l'université (si disponible)
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 20, 0, '', '', '', false, 300, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 8, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
    
    if (!empty($configUniversite['sigle'])) {
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
    }
    
    $pdf->SetFont('dejavusans', '', 9);
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
    $pdf->Ln(2);
    $pdf->Cell(0, 0, '', 'T', 1);
    $pdf->Ln(3);
}

// Titre du document
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'LISTE DES AGENTS', 0, 1, 'C');
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 5, 'Date d\'édition: ' . date('d/m/Y'), 0, 1, 'C');

// Afficher les critères de filtrage utilisés
$pdf->Ln(5);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 7, 'Critères de filtrage:', 0, 1);
$pdf->SetFont('dejavusans', '', 9);

$filterTexts = [];
if (!empty($filters['type_agent'])) $filterTexts[] = 'Type d\'agent: ' . $filters['type_agent'];
if (!empty($filters['grade_id'])) {
    $grade = $gradeModel->getGradeById($filters['grade_id']);
    $filterTexts[] = 'Grade: ' . ($grade['designation'] ?? 'Inconnu');
}
if (!empty($filters['sexe'])) $filterTexts[] = 'Sexe: ' . ($filters['sexe'] == 'M' ? 'Masculin' : 'Féminin');
if (!empty($filters['idStructure'])) {
    $structure = $structureModel->getStructureById($filters['idStructure']);
    $filterTexts[] = 'Structure: ' . ($structure['designation'] ?? 'Inconnue');
}
if (!empty($filters['idService'])) {
    $service = $serviceModel->getServiceById($filters['idService']);
    $filterTexts[] = 'Service: ' . ($service['designationService'] ?? 'Inconnu');
}
if (!empty($filters['annee_engagement'])) $filterTexts[] = 'Année d\'engagement: ' . $filters['annee_engagement'];
if ($filters['prime_locale'] !== '') $filterTexts[] = 'Prime locale: ' . ($filters['prime_locale'] == '1' ? 'Oui' : 'Non');
if ($filters['salaire_etat'] !== '') $filterTexts[] = 'Salaire de l\'état: ' . ($filters['salaire_etat'] == '1' ? 'Oui' : 'Non');
if ($filters['prime_institutionnelle'] !== '') $filterTexts[] = 'Prime institutionnelle: ' . ($filters['prime_institutionnelle'] == '1' ? 'Oui' : 'Non');
if (!empty($filters['niveauEtude'])) $filterTexts[] = 'Niveau d\'étude: ' . $filters['niveauEtude'];
if (!empty($filters['etatCivil'])) $filterTexts[] = 'État civil: ' . $filters['etatCivil'];
if (!empty($filters['search'])) $filterTexts[] = 'Recherche: ' . $filters['search'];

if (empty($filterTexts)) {
    $pdf->Cell(0, 7, 'Aucun filtre appliqué - Liste complète', 0, 1);
} else {
    // Afficher les filtres sur plusieurs lignes si nécessaire
    $filterLine = '';
    foreach ($filterTexts as $index => $text) {
        if ($index > 0 && strlen($filterLine . ' | ' . $text) > 120) {
            $pdf->Cell(0, 7, $filterLine, 0, 1);
            $filterLine = $text;
        } else {
            $filterLine = $index === 0 ? $text : $filterLine . ' | ' . $text;
        }
    }
    if (!empty($filterLine)) {
        $pdf->Cell(0, 7, $filterLine, 0, 1);
    }
}

// Nombre total d'agents
$pdf->Ln(3);
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 7, 'Nombre total d\'agents: ' . count($agents), 0, 1);

// Générer le contenu selon le type d'exportation
if ($exportType === 'fiches_individuelles') {
    // Générer une fiche individuelle pour chaque agent
    foreach ($agents as $index => $agent) {
        if ($index > 0) {
            $pdf->AddPage();
        }
        
        // Titre de la fiche
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'FICHE INDIVIDUELLE', 0, 1, 'C');
        
        // Photo de l'agent (si disponible)
        if (!empty($agent['photo']) && file_exists(dirname(__DIR__) . '/uploads/agents/' . $agent['photo'])) {
            $photoPath = dirname(__DIR__) . '/uploads/agents/' . $agent['photo'];
            $croppedPhotoPath = cropFaceFromImage($photoPath);
            
            // Positionner la photo en haut à droite
            $pdf->Image($croppedPhotoPath, $pdf->getPageWidth() - 50, $pdf->getY() - 5, 30, 30, '', '', '', false, 300, '', false, false, 1);
            
            // Supprimer le fichier temporaire si créé
            if ($croppedPhotoPath != $photoPath) {
                @unlink($croppedPhotoPath);
            }
        }
        
        // Informations de base
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMATIONS PERSONNELLES', 1, 1, 'L', 1);
        $pdf->SetFont('dejavusans', '', 10);
        
        // Définir les largeurs des colonnes
        $col1Width = 60;
        $col2Width = $pdf->getPageWidth() - $col1Width - 20; // 20 = marges gauche et droite
        
        $pdf->Cell($col1Width, 7, 'Code agent:', 1);
        $pdf->Cell($col2Width, 7, $agent['codeAgent'], 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Matricule:', 1);
        $pdf->Cell($col2Width, 7, $agent['matricule'], 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Noms:', 1);
        $pdf->Cell($col2Width, 7, $agent['noms'], 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Sexe:', 1);
        $pdf->Cell($col2Width, 7, $agent['sexe'] == 'M' ? 'Masculin' : 'Féminin', 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Date de naissance:', 1);
        $pdf->Cell($col2Width, 7, date('d/m/Y', strtotime($agent['dateNaissance'])), 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Lieu de naissance:', 1);
        $pdf->Cell($col2Width, 7, $agent['lieuNaissance'], 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'État civil:', 1);
        $pdf->Cell($col2Width, 7, $agent['etatCivil'], 1);
        $pdf->Ln();
        
        if (!empty($agent['conjoint'])) {
            $pdf->Cell($col1Width, 7, 'Conjoint(e):', 1);
            $pdf->Cell($col2Width, 7, $agent['conjoint'], 1);
            $pdf->Ln();
        }
        
        $pdf->Cell($col1Width, 7, 'Adresse:', 1);
        $adresse = '';
        if (!empty($agent['adresse_avenue'])) $adresse .= 'Av. ' . $agent['adresse_avenue'];
        if (!empty($agent['adresse_quartier'])) $adresse .= (!empty($adresse) ? ', ' : '') . 'Q. ' . $agent['adresse_quartier'];
        if (!empty($agent['adresse_commune'])) $adresse .= (!empty($adresse) ? ', ' : '') . 'C. ' . $agent['adresse_commune'];
        $pdf->Cell($col2Width, 7, $adresse ?: 'Non renseignée', 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Téléphone:', 1);
        $pdf->Cell($col2Width, 7, $agent['telephone'], 1);
        $pdf->Ln();
        
        if (!empty($agent['email'])) {
            $pdf->Cell($col1Width, 7, 'Email:', 1);
            $pdf->Cell($col2Width, 7, $agent['email'], 1);
            $pdf->Ln();
        }
        
        // Informations professionnelles
        $pdf->Ln(5);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMATIONS PROFESSIONNELLES', 1, 1, 'L', 1);
        $pdf->SetFont('dejavusans', '', 10);
        
        $pdf->Cell($col1Width, 7, 'Type d\'agent:', 1);
        $pdf->Cell($col2Width, 7, $agent['type_agent'], 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Niveau d\'étude:', 1);
        $pdf->Cell($col2Width, 7, $agent['niveauEtude'], 1);
        $pdf->Ln();
        
        // Récupérer le grade
        $grade = null;
        if (!empty($agent['grade_id'])) {
            $grade = $gradeModel->getGradeById($agent['grade_id']);
        }
        
        $pdf->Cell($col1Width, 7, 'Grade actuel:', 1);
        $pdf->Cell($col2Width, 7, $grade ? $grade['designation'] : 'Non défini', 1);
        $pdf->Ln();
        
        // Récupérer la structure
        $structure = null;
        if (!empty($agent['idStructure'])) {
            $structure = $structureModel->getStructureById($agent['idStructure']);
        }
        
        $pdf->Cell($col1Width, 7, 'Structure:', 1);
        $pdf->Cell($col2Width, 7, $structure ? $structure['designation'] : 'Non définie', 1);
        $pdf->Ln();
        
        // Récupérer le service
        $service = null;
        if (!empty($agent['idService'])) {
            $service = $serviceModel->getServiceById($agent['idService']);
        }
        
        $pdf->Cell($col1Width, 7, 'Service:', 1);
        $pdf->Cell($col2Width, 7, $service ? $service['designationService'] : 'Non défini', 1);
        $pdf->Ln();
        
        if (!empty($agent['annee_engagement'])) {
            $pdf->Cell($col1Width, 7, 'Année d\'engagement:', 1);
            $pdf->Cell($col2Width, 7, $agent['annee_engagement'], 1);
            $pdf->Ln();
        }
        
        if (!empty($agent['reference_acte_engagement'])) {
            $pdf->Cell($col1Width, 7, 'Référence acte d\'engagement:', 1);
            $pdf->Cell($col2Width, 7, $agent['reference_acte_engagement'], 1);
            $pdf->Ln();
        }
        
        // Options de paiement
        $pdf->Ln(5);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, 'OPTIONS DE PAIEMENT', 1, 1, 'L', 1);
        $pdf->SetFont('dejavusans', '', 10);
        
        $pdf->Cell($col1Width, 7, 'Prime locale:', 1);
        $pdf->Cell($col2Width, 7, ($agent['prime_locale'] == 1 ? 'Oui' : 'Non'), 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Salaire de base de l\'état:', 1);
        $pdf->Cell($col2Width, 7, ($agent['salaire_etat'] == 1 ? 'Oui' : 'Non'), 1);
        $pdf->Ln();
        
        $pdf->Cell($col1Width, 7, 'Prime institutionnelle:', 1);
        $pdf->Cell($col2Width, 7, ($agent['prime_institutionnelle'] == 1 ? 'Oui' : 'Non'), 1);
        $pdf->Ln();
        
        // Contact d'urgence
        if (!empty($agent['contact_urgence']) || !empty($agent['telephone_urgence'])) {
            $pdf->Ln(5);
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 8, 'CONTACT D\'URGENCE', 1, 1, 'L', 1);
            $pdf->SetFont('dejavusans', '', 10);
            
            if (!empty($agent['contact_urgence'])) {
                $pdf->Cell($col1Width, 7, 'Personne à contacter:', 1);
                $pdf->Cell($col2Width, 7, $agent['contact_urgence'], 1);
                $pdf->Ln();
            }
            
            if (!empty($agent['degre_parente_urgence'])) {
                $pdf->Cell($col1Width, 7, 'Degré de parenté:', 1);
                $pdf->Cell($col2Width, 7, $agent['degre_parente_urgence'], 1);
                $pdf->Ln();
            }
            
            if (!empty($agent['telephone_urgence'])) {
                $pdf->Cell($col1Width, 7, 'Téléphone d\'urgence:', 1);
                $pdf->Cell($col2Width, 7, $agent['telephone_urgence'], 1);
                $pdf->Ln();
            }
        }
    }
} else {
    // Liste simple ou détaillée
    $pdf->Ln(5);
    
    // Définir les colonnes à afficher
    $tableColumns = [];
    $tableWidths = [];
    
    // Ajouter les colonnes sélectionnées
    if (in_array('code', $columns)) {
        $tableColumns[] = 'Code';
        $tableWidths[] = 25;
    }
    if (in_array('matricule', $columns)) {
        $tableColumns[] = 'Matricule';
        $tableWidths[] = 25;
    }
    if (in_array('noms', $columns)) {
        $tableColumns[] = 'Noms';
        $tableWidths[] = 60;
    }
    if (in_array('sexe', $columns)) {
        $tableColumns[] = 'Sexe';
        $tableWidths[] = 15;
    }
    if (in_array('grade', $columns)) {
        $tableColumns[] = 'Grade';
        $tableWidths[] = 30;
    }
    if (in_array('type', $columns)) {
        $tableColumns[] = 'Type';
        $tableWidths[] = 25;
    }
    if (in_array('structure', $columns)) {
        $tableColumns[] = 'Structure';
        $tableWidths[] = 40;
    }
    if (in_array('service', $columns)) {
        $tableColumns[] = 'Service';
        $tableWidths[] = 40;
    }
    if (in_array('telephone', $columns)) {
        $tableColumns[] = 'Téléphone';
        $tableWidths[] = 30;
    }
    if (in_array('email', $columns)) {
        $tableColumns[] = 'Email';
        $tableWidths[] = 40;
    }
    if (in_array('niveau', $columns)) {
        $tableColumns[] = 'Niveau d\'étude';
        $tableWidths[] = 30;
    }
    if (in_array('engagement', $columns)) {
        $tableColumns[] = 'Année d\'engagement';
        $tableWidths[] = 25;
    }
    
    // Ajuster les largeurs des colonnes si nécessaire
    $totalWidth = array_sum($tableWidths);
    $pageWidth = $pdf->getPageWidth() - 20; // 20 = marges gauche et droite
    
    if ($totalWidth > $pageWidth) {
        $ratio = $pageWidth / $totalWidth;
        foreach ($tableWidths as &$width) {
            $width = $width * $ratio;
        }
    }
    
    // Créer l'en-tête du tableau
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    
    foreach ($tableColumns as $index => $column) {
        $pdf->Cell($tableWidths[$index], 7, $column, 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Contenu du tableau
    $pdf->SetFont('dejavusans', '', 8);
    $fill = false;
    
    foreach ($agents as $agent) {
        // Vérifier si on a besoin d'un saut de page
        if ($pdf->GetY() > $pdf->getPageHeight() - 15) {
            $pdf->AddPage();
            
                        // Répéter l'en-tête du tableau
                        $pdf->SetFont('dejavusans', 'B', 9);
                        $pdf->SetFillColor(220, 220, 220);
                        
                        foreach ($tableColumns as $index => $column) {
                            $pdf->Cell($tableWidths[$index], 7, $column, 1, 0, 'C', 1);
                        }
                        $pdf->Ln();
                        $pdf->SetFont('dejavusans', '', 8);
                    }
                    
                    $colIndex = 0;
                    
                    // Remplir les données selon les colonnes sélectionnées
                    if (in_array('code', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['codeAgent'], 1, 0, 'L', $fill);
                    }
                    if (in_array('matricule', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['matricule'], 1, 0, 'L', $fill);
                    }
                    if (in_array('noms', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['noms'], 1, 0, 'L', $fill);
                    }
                    if (in_array('sexe', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['sexe'], 1, 0, 'C', $fill);
                    }
                    if (in_array('grade', $columns)) {
                        $grade = '';
                        if (!empty($agent['grade_id'])) {
                            $gradeInfo = $gradeModel->getGradeById($agent['grade_id']);
                            $grade = $gradeInfo ? $gradeInfo['designation'] : '';
                        }
                        $pdf->Cell($tableWidths[$colIndex++], 6, $grade, 1, 0, 'L', $fill);
                    }
                    if (in_array('type', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['type_agent'], 1, 0, 'L', $fill);
                    }
                    if (in_array('structure', $columns)) {
                        $structure = '';
                        if (!empty($agent['idStructure'])) {
                            $structureInfo = $structureModel->getStructureById($agent['idStructure']);
                            $structure = $structureInfo ? $structureInfo['designation'] : '';
                        }
                        $pdf->Cell($tableWidths[$colIndex++], 6, $structure, 1, 0, 'L', $fill);
                    }
                    if (in_array('service', $columns)) {
                        $service = '';
                        if (!empty($agent['idService'])) {
                            $serviceInfo = $serviceModel->getServiceById($agent['idService']);
                            $service = $serviceInfo ? $serviceInfo['designationService'] : '';
                        }
                        $pdf->Cell($tableWidths[$colIndex++], 6, $service, 1, 0, 'L', $fill);
                    }
                    if (in_array('telephone', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['telephone'], 1, 0, 'L', $fill);
                    }
                    if (in_array('email', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['email'], 1, 0, 'L', $fill);
                    }
                    if (in_array('niveau', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['niveauEtude'], 1, 0, 'L', $fill);
                    }
                    if (in_array('engagement', $columns)) {
                        $pdf->Cell($tableWidths[$colIndex++], 6, $agent['annee_engagement'] ?? '', 1, 0, 'C', $fill);
                    }
                    
                    $pdf->Ln();
                    $fill = !$fill; // Alterner les couleurs de fond
                }
                
                // Si c'est une liste détaillée, ajouter des informations supplémentaires
                if ($exportType === 'liste_detaillee') {
                    $pdf->AddPage();
                    
                    $pdf->SetFont('dejavusans', 'B', 14);
                    $pdf->Cell(0, 10, 'DÉTAILS SUPPLÉMENTAIRES', 0, 1, 'C');
                    
                    // Statistiques
                    $pdf->SetFont('dejavusans', 'B', 12);
                    $pdf->Cell(0, 8, 'STATISTIQUES', 1, 1, 'L', 1);
                    $pdf->SetFont('dejavusans', '', 10);
                    
                    // Compter par type d'agent
                    $typeStats = [];
                    foreach ($agents as $agent) {
                        $type = $agent['type_agent'];
                        if (!isset($typeStats[$type])) {
                            $typeStats[$type] = 0;
                        }
                        $typeStats[$type]++;
                    }
                    
                    $pdf->Cell(60, 7, 'Répartition par type d\'agent:', 1);
                    $statsText = '';
                    foreach ($typeStats as $type => $count) {
                        $statsText .= $type . ': ' . $count . ' | ';
                    }
                    $statsText = rtrim($statsText, ' | ');
                    $pdf->Cell(0, 7, $statsText, 1, 1);
                    
                    // Compter par sexe
                    $sexeStats = ['M' => 0, 'F' => 0];
                    foreach ($agents as $agent) {
                        $sexe = $agent['sexe'] ?? 'M';
                        $sexeStats[$sexe]++;
                    }
                    
                    $pdf->Cell(60, 7, 'Répartition par sexe:', 1);
                    $pdf->Cell(0, 7, 'Masculin: ' . $sexeStats['M'] . ' | Féminin: ' . $sexeStats['F'], 1, 1);
                    
                    // Compter par niveau d'étude
                    $niveauStats = [];
                    foreach ($agents as $agent) {
                        $niveau = $agent['niveauEtude'] ?? 'Non défini';
                        if (!isset($niveauStats[$niveau])) {
                            $niveauStats[$niveau] = 0;
                        }
                        $niveauStats[$niveau]++;
                    }
                    
                    $pdf->Cell(60, 7, 'Répartition par niveau d\'étude:', 1);
                    $statsText = '';
                    foreach ($niveauStats as $niveau => $count) {
                        $statsText .= $niveau . ': ' . $count . ' | ';
                    }
                    $statsText = rtrim($statsText, ' | ');
                    $pdf->Cell(0, 7, $statsText, 1, 1);
                    
                    // Compter par structure
                    $structureStats = [];
                    foreach ($agents as $agent) {
                        if (!empty($agent['idStructure'])) {
                            $structureInfo = $structureModel->getStructureById($agent['idStructure']);
                            $structureName = $structureInfo ? $structureInfo['designation'] : 'Non définie';
                            
                            if (!isset($structureStats[$structureName])) {
                                $structureStats[$structureName] = 0;
                            }
                            $structureStats[$structureName]++;
                        }
                    }
                    
                    $pdf->Cell(60, 7, 'Répartition par structure:', 1);
                    $pdf->Ln();
                    
                    foreach ($structureStats as $structure => $count) {
                        $pdf->Cell(100, 7, $structure, 1);
                        $pdf->Cell(30, 7, $count . ' agent(s)', 1);
                        $pdf->Ln();
                    }
                    
                    
                }
            }
            
            // Pied de page avec signature
            $pdf->Ln(20);
            $pdf->SetFont('dejavusans', 'I', 10);
            $pdf->Cell(0, 7, 'Document généré automatiquement par le système de gestion universitaire.', 0, 1, 'C');
            $pdf->Cell(0, 7, 'Fait à __________________, le ' . date('d/m/Y'), 0, 1, 'C');
            
            // Espace pour signature
            $pdf->Ln(10);
            $pdf->Cell(0, 7, 'Signature du responsable', 0, 1, 'C');
            
            // Sortie du PDF (nettoyage des buffers pour éviter l'erreur TCPDF)
            while (ob_get_level() > 0) { @ob_end_clean(); }
            if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
            @ini_set('zlib.output_compression', 'Off');
            $pdf->Output('Liste_Agents_' . date('Y-m-d') . '.pdf', 'I');
            
