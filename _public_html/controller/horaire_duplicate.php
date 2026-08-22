<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

// Créer une instance de la classe Horaire
$horaire = new Horaire();
$universite = new Universite();

// Vérifier si l'utilisateur est administrateur
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];

// Code pour la duplication de semaine
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'duplicate_week') {
    // Définir le type de contenu comme JSON
    header('Content-Type: application/json');
    
    // Récupérer les paramètres
    $promotionId = isset($_POST['promotion_id']) ? intval($_POST['promotion_id']) : 0;
    $currentMonday = isset($_POST['current_monday']) ? $_POST['current_monday'] : '';
    $currentSunday = isset($_POST['current_sunday']) ? $_POST['current_sunday'] : '';
    $newMonday = isset($_POST['new_monday']) ? $_POST['new_monday'] : '';
    $anneeAcad = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
    $idUser = $_SESSION['id'];
    
    // Vérifier les paramètres
    if (empty($promotionId) || empty($currentMonday) || empty($currentSunday) || empty($newMonday) || empty($anneeAcad)) {
        echo json_encode(['success' => false, 'message' => 'Paramètres incomplets']);
        exit;
    }
    
    // Vérifier l'accès à la promotion
    if (!$isAdmin) {
        // Vérifier si l'utilisateur est responsable de la section de cette promotion
        $pdo = Connexion::getInstance()->getPDO();
        $query = "SELECT COUNT(*) 
                  FROM responsable_section rs
                  INNER JOIN section s ON s.idsection = rs.section_idsection
                  INNER JOIN orientation o ON o.section_idsection = s.idsection
                  INNER JOIN promotion p ON p.orientation_idorientation = o.idorientation
                  WHERE rs.\"idUser\" = :userId 
                  AND rs.annee_acad_idannee_acad = :anneeId
                  AND p.idpromotion = :promotionId";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->bindParam(':anneeId', $anneeAcad);
        $stmt->bindParam(':promotionId', $promotionId);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits pour dupliquer les horaires de cette promotion.']);
            exit;
        }
    }
    
    try {
        // NOUVELLE APPROCHE: créer un mappage jour-par-jour entre les deux semaines
        $currentWeekMap = [];
        $newWeekMap = [];
        
        // Créer des objets DateTime pour les dates de référence
        $currentMondayObj = new DateTime($currentMonday);
        $newMondayObj = new DateTime($newMonday);
        
        // Générer le mappage pour chaque jour de la semaine
        for ($i = 0; $i < 7; $i++) {
            $currentDayObj = clone $currentMondayObj;
            $currentDayObj->modify("+$i days");
            $currentDay = $currentDayObj->format('Y-m-d');
            
            $newDayObj = clone $newMondayObj;
            $newDayObj->modify("+$i days");
            $newDay = $newDayObj->format('Y-m-d');
            
            // Mapper les jours de la semaine
            $dayOfWeek = $currentDayObj->format('l'); // Lundi, Mardi, etc. en anglais
            $jourFr = [
                'Monday' => 'Lundi',
                'Tuesday' => 'Mardi',
                'Wednesday' => 'Mercredi',
                'Thursday' => 'Jeudi',
                'Friday' => 'Vendredi',
                'Saturday' => 'Samedi',
                'Sunday' => 'Dimanche'
            ][$dayOfWeek];
            
            $currentWeekMap[$currentDay] = $jourFr;
            $newWeekMap[$jourFr] = $newDay;
            
            // Journalisation pour le débogage
            error_log("Mappage: $currentDay ($jourFr) -> $newDay");
        }
        
        // Récupérer tous les horaires de la semaine courante
        $horaires = $horaire->getHorairesByPromotionAndDates(
            $promotionId, 
            $anneeAcad,
            $currentMonday,
            $currentSunday
        );
        
        $duplicatedCount = 0;
        $errorStats = [
            'total' => 0,
            'salle' => 0,
            'promotion' => 0,
            'enseignant' => 0,
            'autre' => 0
        ];
        $errorsDetails = [];
        
        // Dupliquer chaque horaire
        foreach ($horaires as $h) {
            // NOUVELLE LOGIQUE: déterminer la nouvelle date
            if (!empty($h['date_cours'])) {
                // Si l'horaire a une date spécifique
                $dateCours = $h['date_cours'];
                $jourSemaine = $currentWeekMap[$dateCours] ?? null;
                
                if ($jourSemaine && isset($newWeekMap[$jourSemaine])) {
                    // Utiliser le mappage pour obtenir la date correspondante dans la nouvelle semaine
                    $newDate = $newWeekMap[$jourSemaine];
                } else {
                    // Fallback si le mappage échoue
                    $jourObj = new DateTime($dateCours);
                    $dayOfWeek = $jourObj->format('N') - 1; // 0 (lundi) à 6 (dimanche)
                    
                    $newDateObj = clone $newMondayObj;
                    $newDateObj->modify("+$dayOfWeek days");
                    $newDate = $newDateObj->format('Y-m-d');
                    
                    error_log("Fallback pour date: $dateCours -> $newDate (jour de semaine $dayOfWeek)");
                }
            } else {
                // Si l'horaire n'a pas de date spécifique, utiliser le jour de la semaine
                $jourSemaine = $h['jour'];
                $newDate = $newWeekMap[$jourSemaine] ?? null;
                
                if (!$newDate) {
                    // Fallback si le mappage échoue
                    $jourMapping = [
                        'Lundi' => 0, 'Mardi' => 1, 'Mercredi' => 2, 
                        'Jeudi' => 3, 'Vendredi' => 4, 'Samedi' => 5, 'Dimanche' => 6
                    ];
                    
                    $dayOffset = $jourMapping[$jourSemaine] ?? 0;
                    $newDateObj = clone $newMondayObj;
                    $newDateObj->modify("+$dayOffset days");
                    $newDate = $newDateObj->format('Y-m-d');
                    
                    error_log("Fallback pour jour: $jourSemaine -> $newDate");
                }
            }
            
            // Journalisation pour le débogage
            error_log("Duplication: Horaire original: " . json_encode([
                'id' => $h['idhoraire'] ?? 'N/A',
                'jour' => $h['jour'], 
                'date_cours' => $h['date_cours'] ?? 'NULL',
                'nouvelle_date' => $newDate
            ]));
            
            // Insérer le nouvel horaire
            $result = $horaire->addHoraire(
                $h['jour'],
                $h['heure_debut'],
                $h['heure_fin'],
                $h['salle'],
                $h['idECUE'],
                $anneeAcad,
                $idUser,
                $h['type_cours'],
                $newDate
            );
            
            if ($result['success']) {
                $duplicatedCount++;
            } else {
                $errorStats['total']++;
                
                // Analyser le message d'erreur pour déterminer la catégorie
                $errorMessage = strtolower($result['message']);
                
                if (strpos($errorMessage, 'salle') !== false) {
                    $errorStats['salle']++;
                    $category = 'Conflit de salle';
                } elseif (strpos($errorMessage, 'promotion') !== false) {
                    $errorStats['promotion']++;
                    $category = 'Conflit de promotion';
                } elseif (strpos($errorMessage, 'enseignant') !== false) {
                    $errorStats['enseignant']++;
                    $category = 'Conflit d\'enseignant';
                } else {
                    $errorStats['autre']++;
                    $category = 'Autre erreur';
                }
                
                // Ajouter les détails de l'erreur
                $errorsDetails[] = [
                    'ecue' => $h['designationECUE'] ?? "ECUE ID: " . $h['idECUE'],
                    'jour' => $h['jour'],
                    'heures' => $h['heure_debut'] . ' - ' . $h['heure_fin'],
                    'salle' => $h['salle'],
                    'date' => $newDate ?? 'Non spécifiée',
                    'message' => $result['message'],
                    'category' => $category
                ];
            }
        }
        
        // Calculer l'offset de semaine pour la redirection
        $today = date('Y-m-d');
        $weekOffset = floor((strtotime($newMonday) - strtotime(date('Y-m-d', strtotime("monday this week", strtotime($today))))) / (7 * 24 * 60 * 60));
        
        echo json_encode([
            'success' => true, 
            'message' => $duplicatedCount . ' horaires ont été dupliqués avec succès.', 
            'weekOffset' => $weekOffset,
            'total_attempted' => count($horaires),
            'error_stats' => $errorStats,
            'errors_details' => $errorsDetails
        ]);
    } catch (Exception $e) {
        error_log("Exception dans horaire_duplicate.php: " . $e->getMessage() . " dans " . $e->getFile() . " ligne " . $e->getLine());
        
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    exit;
}
