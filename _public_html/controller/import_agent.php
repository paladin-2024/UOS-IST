<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Universite.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$agent = new Agent();
$structure = new Structure();
$universite = new Universite();
$idUser = $_SESSION['id'] ?? null; // Récupérer l'ID de l'utilisateur depuis la session

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['importFile']['tmp_name'];
    $idStructure = $_POST['importIdStructure'];
    $startRow = $_POST['startRow'];
    $nomsColumn = $_POST['nomsColumn'];
    $matriculeColumn = !empty($_POST['matriculeColumn']) ? $_POST['matriculeColumn'] : null;
    $typeAgentColumn = !empty($_POST['typeAgentColumn']) ? $_POST['typeAgentColumn'] : null;
    $gradeColumn = !empty($_POST['gradeColumn']) ? $_POST['gradeColumn'] : null;
    $lieuNaissanceColumn = !empty($_POST['lieuNaissanceColumn']) ? $_POST['lieuNaissanceColumn'] : null;
    $dateNaissanceColumn = !empty($_POST['dateNaissanceColumn']) ? $_POST['dateNaissanceColumn'] : null;
    $sexeColumn = !empty($_POST['sexeColumn']) ? $_POST['sexeColumn'] : null;
    $etatCivilColumn = !empty($_POST['etatCivilColumn']) ? $_POST['etatCivilColumn'] : null;
    $niveauEtudeColumn = !empty($_POST['niveauEtudeColumn']) ? $_POST['niveauEtudeColumn'] : null;
    $telephoneColumn = !empty($_POST['telephoneColumn']) ? $_POST['telephoneColumn'] : null;
    $emailColumn = !empty($_POST['emailColumn']) ? $_POST['emailColumn'] : null;
    $codeAgentColumn = !empty($_POST['codeAgentColumn']) ? $_POST['codeAgentColumn'] : null;
    $defaultType = !empty($_POST['defaultType']) ? $_POST['defaultType'] : null;
    $defaultGrade = !empty($_POST['defaultGrade']) ? $_POST['defaultGrade'] : null;
    $defaultSection = !empty($_POST['defaultSection']) ? $_POST['defaultSection'] : null;

    try {
        // Vérifier si la structure existe
        if (!$structure->checkStructureExists($idStructure)) {
            throw new Exception('La structure sélectionnée est invalide.');
        }

        // Vérifier si une section principale est sélectionnée pour les enseignants
        if ($defaultType === 'Enseignant' && empty($defaultSection)) {
            throw new Exception('Veuillez sélectionner une section principale pour les enseignants.');
        }

        // Charger le fichier Excel
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();

        // Statistiques d'importation
        $totalRows = 0;
        $successCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;

        // Parcourir les lignes du fichier
        for ($row = $startRow; $row <= $worksheet->getHighestRow(); $row++) {
            $totalRows++;
            
            // Récupérer les données de la ligne
            $noms = $worksheet->getCell(Coordinate::stringFromColumnIndex($nomsColumn) . $row)->getValue();
            
            // Vérifier si le nom est vide (ligne vide ou fin du fichier)
            if (empty($noms)) {
                continue;
            }
            
            $matricule = $matriculeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($matriculeColumn) . $row)->getValue() : null;
            $typeAgent = $typeAgentColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($typeAgentColumn) . $row)->getValue() : $defaultType;
            $gradeValue = $gradeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($gradeColumn) . $row)->getValue() : null;
            $lieuNaissance = $lieuNaissanceColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($lieuNaissanceColumn) . $row)->getValue() : null;
            $dateNaissance = $dateNaissanceColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($dateNaissanceColumn) . $row)->getValue() : null;
            $sexe = $sexeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($sexeColumn) . $row)->getValue() : null;
            $etatCivil = $etatCivilColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($etatCivilColumn) . $row)->getValue() : null;
            $niveauEtude = $niveauEtudeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($niveauEtudeColumn) . $row)->getValue() : null;
            $telephone = $telephoneColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($telephoneColumn) . $row)->getValue() : null;
            $email = $emailColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($emailColumn) . $row)->getValue() : null;
            $codeAgent = $codeAgentColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($codeAgentColumn) . $row)->getValue() : null;

            // Convertir la date de naissance au format Y-m-d
            if ($dateNaissance) {
                $dateNaissance = convertDateToYMD($dateNaissance);
            }

            // Normaliser le sexe (M/F)
            if ($sexe) {
                $sexe = normalizeGender($sexe);
            }

            // Normaliser l'état civil
            if ($etatCivil) {
                $etatCivil = normalizeMaritalStatus($etatCivil);
            }

            // Normaliser le type d'agent
            if ($typeAgent) {
                $typeAgent = normalizeAgentType($typeAgent);
            }

            // Déterminer l'ID du grade
            $gradeId = null;
            if ($gradeValue) {
                // Essayer de trouver le grade par sa désignation
                $gradeId = $agent->getGradeIdByDesignation($gradeValue);
            }
            
            // Si aucun grade trouvé dans le fichier, utiliser le grade par défaut
            if (!$gradeId && $defaultGrade) {
                $gradeId = $defaultGrade;
            }

            // Vérifier les doublons pour l'agent
            if ($agent->checkDuplicateAgent($noms, $dateNaissance, $idStructure)) {
                $duplicateCount++;
                continue;
            }

            // Générer un matricule si non fourni
            if (!$matricule) {
                $matricule = generateNextMatricule($typeAgent);
            }

            // Ajouter l'agent dans la base de données
            $idAgent = $agent->addAgent_returnID($noms, $lieuNaissance, $dateNaissance, $sexe, $etatCivil, $niveauEtude, $telephone, $email, $codeAgent, $matricule, $typeAgent, $gradeId, $idStructure, null);

            if ($idAgent) {
                $successCount++;
                
                // Si c'est un enseignant, ajouter la section principale
                if ($typeAgent === 'Enseignant' && $defaultSection) {
                    $universite->addAgentSection($idAgent, $defaultSection, 1); // 1 = section principale
                }
            } else {
                $errorCount++;
            }

        }

        // Afficher un message de succès avec les statistiques
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Importation terminée',
                    html: 'Résultats de l\'importation:<br>' +
                          'Total de lignes traitées: {$totalRows}<br>' +
                          'Agents ajoutés avec succès: {$successCount}<br>' +
                          'Doublons ignorés: {$duplicateCount}<br>' +
                          'Erreurs: {$errorCount}'
                }).then(() => {
                    window.location.href = '../grh/agent.add';
                });
              </script>";
    } catch (Exception $e) {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "'
                }).then(() => {
                    window.location.href = '../grh/agent.add';
                });
              </script>";
    }
}

/**
 * Convertit une date au format Y-m-d
 */
function convertDateToYMD($date) {
    // Si la date est déjà au format Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    // Si c'est un timestamp Excel (nombre de jours depuis le 1er janvier 1900)
    if (is_numeric($date)) {
        $excelBaseDate = new DateTime('1899-12-30');
        $excelBaseDate->modify('+' . intval($date) . ' days');
        return $excelBaseDate->format('Y-m-d');
    }

    // Essayer différents formats de date
    $date = preg_replace('/[^0-9\/\-\.]/', '', $date); // Supprimer les caractères non numériques et non séparateurs

    $formats = [
        'd/m/Y', 'd-m-Y', 'd.m.Y',
        'm/d/Y', 'm-d-Y', 'm.d.Y',
        'Y/m/d', 'Y-m-d', 'Y.m.d'
    ];

    foreach ($formats as $format) {
        $dateTime = DateTime::createFromFormat($format, $date);
        if ($dateTime !== false) {
            return $dateTime->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Normalise le genre (M/F)
 */
function normalizeGender($gender) {
    $gender = strtoupper(trim($gender));

    if (in_array($gender, ['M', 'MASCULIN', 'HOMME', 'H', 'MALE'])) {
        return 'M';
    } elseif (in_array($gender, ['F', 'FEMININ', 'FEMME', 'FEMALE'])) {
        return 'F';
    }

    return $gender;
}

/**
 * Normalise l'état civil
 */
function normalizeMaritalStatus($status) {
    $status = ucfirst(strtolower(trim($status)));

    $mapping = [
        'C' => 'Célibataire',
        'Celibataire' => 'Célibataire',
        'S' => 'Célibataire', // Single
        'M' => 'Marié',
        'Marie' => 'Marié',
        'Mariee' => 'Marié',
        'D' => 'Divorcé',
        'Divorce' => 'Divorcé',
        'Divorcee' => 'Divorcé',
        'V' => 'Veuf',
        'Veuve' => 'Veuf'
    ];

    return $mapping[$status] ?? $status;
}

/**
 * Normalise le type d'agent
 */
function normalizeAgentType($type) {
    $type = ucfirst(strtolower(trim($type)));

    $mapping = [
        'E' => 'Enseignant',
        'Ens' => 'Enseignant',
        'Prof' => 'Enseignant',
        'Professeur' => 'Enseignant',
        'A' => 'Administratif',
        'Admin' => 'Administratif',
        'R' => 'Recherche',
        'Rech' => 'Recherche',
        'Chercheur' => 'Recherche'
    ];

    return $mapping[$type] ?? $type;
}

/**
 * Génère un matricule unique pour un agent
 */
function generateNextMatricule($typeAgent) {
    $prefix = 'AG';

    // Préfixe selon le type d'agent
    if ($typeAgent == 'Enseignant') {
        $prefix = 'ENS';
    } elseif ($typeAgent == 'Administratif') {
        $prefix = 'ADM';
    } elseif ($typeAgent == 'Recherche') {
        $prefix = 'RCH';
    }

    // Année courante
    $year = date('Y');

    // Numéro aléatoire à 4 chiffres
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

    return $prefix . '-' . $year . '-' . $random;
}
?>
