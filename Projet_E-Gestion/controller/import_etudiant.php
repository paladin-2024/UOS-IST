<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$universite = new Universite();
$idUser = $_SESSION['id'] ?? null; // Retrieve idUser from session

function standardizeSexe($sexe) {
    if (empty($sexe)) return null;
    
    // Convert to lowercase and remove any extra spaces/special characters
    $sexe = trim(strtolower($sexe));
    $sexe = preg_replace('/\s+/', '', $sexe); // Remove all whitespace
    
    // Broader set of possible values for male
    $masculinValues = ['m', 'h', 'male', 'homme', 'masculin', 'masculine', 'mr', 'monsieur', 
                      'garçon', 'garcon', '1', 'hommes', 'males', 'boy', 'boys', 'mens', 'men'];
    
    // Broader set of possible values for female
    $femininValues = ['f', 'femme', 'female', 'feminin', 'féminin', 'feminine', 'mme', 'madame', 
                     'fille', '2', 'femmes', 'females', 'girl', 'girls', 'womens', 'women'];
    
    // Check for partial matches too
    foreach ($masculinValues as $value) {
        if (strpos($sexe, $value) !== false) {
            return "Masculin";
        }
    }
    
    foreach ($femininValues as $value) {
        if (strpos($sexe, $value) !== false) {
            return "Feminin";
        }
    }
    
    // If first letter is 'm', consider it masculine
    if (substr($sexe, 0, 1) === 'm') {
        return "Masculin";
    }
    
    // If first letter is 'f', consider it feminine
    if (substr($sexe, 0, 1) === 'f') {
        return "Feminin";
    }
    
    // Default to Masculin if we can't determine (you can adjust this default as needed)
    return "Masculin";
}

function convertDateToYMD($date) {
    if (empty($date)) return null;
    
    // Si c'est un objet DateTime (parfois retourné par PhpSpreadsheet)
    if ($date instanceof DateTime) {
        return $date->format('Y-m-d');
    }
    
    // Si la valeur est numérique, pourrait être une date Excel
    if (is_numeric($date)) {
        try {
            // Convertir directement la valeur numérique Excel en date
            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date);
            return $excelDate->format('Y-m-d');
        } catch (Exception $e) {
            // Si échec, continuer avec les autres méthodes
        }
    }
    
    // Convertir en chaîne et nettoyer
    $dateStr = (string)$date;
    
    // Méthode 1: Essayer avec DateTime::createFromFormat pour des formats communs
    $formats = ['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'Y-m-d', 'Y/m/d'];
    foreach ($formats as $format) {
        $dateObj = DateTime::createFromFormat($format, $dateStr);
        if ($dateObj !== false) {
            // Vérifier si la date est dans une plage raisonnable
            $year = (int)$dateObj->format('Y');
            if ($year >= 1900 && $year <= 2100) {
                return $dateObj->format('Y-m-d');
            }
        }
    }
    
    // Méthode 2: Pour les formats dd/mm/yy, convertir manuellement
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2})$/', $dateStr, $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];
        
        // Ajuster l'année à 4 chiffres (supposer 20xx pour les années < 50, 19xx sinon)
        $year = ($year < 50) ? 2000 + $year : 1900 + $year;
        
        // Vérifier si la date est valide
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }
    
    // Méthode 3: Essayer avec strtotime comme dernier recours
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    // Échec de toutes les méthodes
    return null;
}



function generateNextMatricule($universite, $idAnnee, $prefix = "ET-A") {
    $count = 1;
    do {
        $nextMatricule = $prefix . str_pad($count, 8, '0', STR_PAD_LEFT);
        $existingStudent = $universite->getStudentByMatriculeAndYear($nextMatricule, $idAnnee);
        $count++;
    } while ($existingStudent);

    return $nextMatricule;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importStudentBtn'])) {
     $file = $_FILES['importFile']['tmp_name'];
     $importType = $_POST['importType'];
     $startRow = $_POST['startRow'];
     
     // Column mappings
     $matriculeColumn = !empty($_POST['matriculeColumn']) ? (int)$_POST['matriculeColumn'] : null;
     $nomsColumn = (int)$_POST['nomsColumn'];
     $sexeColumn = !empty($_POST['sexeColumn']) ? (int)$_POST['sexeColumn'] : null;
     $dateNaissanceColumn = !empty($_POST['dateNaissanceColumn']) ? (int)$_POST['dateNaissanceColumn'] : null;
     $lieuNaissanceColumn = !empty($_POST['lieuNaissanceColumn']) ? (int)$_POST['lieuNaissanceColumn'] : null;
     $nationaliteColumn = !empty($_POST['nationaliteColumn']) ? (int)$_POST['nationaliteColumn'] : null;
     $adressemailColumn = !empty($_POST['adressemailColumn']) ? (int)$_POST['adressemailColumn'] : null;
     $telephoneColumn = !empty($_POST['telephoneColumn']) ? (int)$_POST['telephoneColumn'] : null;
     $adresseColumn = !empty($_POST['adresseColumn']) ? (int)$_POST['adresseColumn'] : null;
     $personneContactColumn = !empty($_POST['personneContactColumn']) ? (int)$_POST['personneContactColumn'] : null;
     $telephoneContactColumn = !empty($_POST['telephoneContactColumn']) ? (int)$_POST['telephoneContactColumn'] : null;
     
     // Type-specific fields
     if ($importType === 'regular') {
         $idAnnee = $_POST['importIdAnnee'];
         $promotionId = $_POST['importPromotionId'];
         $prefix = "ET-A";
     } else { // preparatory
         $anneeAcademique = $_POST['anneeAcademique'];
         $prefix = $_POST['preparatoirePrefix'] ?? "ET-P";
         $idAnnee = null; // For preparatory students, we might not have an academic year ID
         $promotionId = null; // For preparatory students, we might not have a promotion
     }

     try {
         // Use auto-detection with memory-efficient settings
         $reader = IOFactory::createReaderForFile($file);
         $reader->setReadDataOnly(true);
         $spreadsheet = $reader->load($file);
         $worksheet = $spreadsheet->getActiveSheet();
         $successCount = 0;
         $errorCount = 0;
         $reEnrollmentCount = 0;
         $errors = array(); // Collect error details
         $reEnrollments = array(); // Track re-enrollments

         // Get highest row before loop
         $highestRow = $worksheet->getHighestRow();
         
         for ($row = $startRow; $row <= $highestRow; $row++) {
             // Get cell values
             $matricule = $matriculeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($matriculeColumn) . $row)->getValue() : null;
             $noms = $nomsColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($nomsColumn) . $row)->getValue() : null;
             
             // Skip row if name is empty
             if (empty($noms)) {
                 continue;
             }
             
             $sexeRaw = $sexeColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($sexeColumn) . $row)->getValue() : null;
             $sexe = standardizeSexe($sexeRaw);
             
             $dateNaissanceRaw = $dateNaissanceColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($dateNaissanceColumn) . $row)->getValue() : null;
             $dateNaissance = convertDateToYMD($dateNaissanceRaw);
             
             $lieuNaissance = $lieuNaissanceColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($lieuNaissanceColumn) . $row)->getValue() : null;
             $nationalite = $nationaliteColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($nationaliteColumn) . $row)->getValue() : null;
             $adressemail = $adressemailColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($adressemailColumn) . $row)->getValue() : null;
             $telephone = $telephoneColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($telephoneColumn) . $row)->getValue() : null;
             $adresse = $adresseColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($adresseColumn) . $row)->getValue() : null;
             $personneContact = $personneContactColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($personneContactColumn) . $row)->getValue() : null;
             $telephoneContact = $telephoneContactColumn ? $worksheet->getCell(Coordinate::stringFromColumnIndex($telephoneContactColumn) . $row)->getValue() : null;

             // Generate matricule if not provided
             if (empty($matricule)) {
                 $matricule = generateNextMatricule($universite, $idAnnee, $prefix);
             }

             // For regular students
             if ($importType === 'regular') {
                 // Check if student already exists with this matricule
                 $existingStudent = $universite->getStudentByMatriculeAndYear($matricule, $idAnnee);
                 
                 // If student exists in same year, skip
                 if ($existingStudent && !empty($existingStudent['idetudiant']) && 
                     $existingStudent['annee_acad_idannee_acad'] == $idAnnee) {
                     $errorCount++;
                     
                     // Build detailed information about existing student
                     $existingNoms = htmlspecialchars($existingStudent['noms'] ?? 'N/A');
                     $existingPromotion = htmlspecialchars($existingStudent['designationPromotion'] ?? 'N/A');
                     $existingAnnee = htmlspecialchars($existingStudent['annee'] ?? 'N/A');
                     
                     $errors[] = array(
                         'ligne' => $row,
                         'matricule' => $matricule,
                         'noms' => $noms,
                         'raison' => 'Doublon de matricule détecté',
                         'details' => 'Le matricule « ' . htmlspecialchars($matricule) . ' » existe déjà pour l\'année académique « ' . htmlspecialchars($existingAnnee) . ' ».',
                         'existant' => 'Étudiant existant: ' . $existingNoms . ' | Promotion: ' . $existingPromotion . ' | Année: ' . $existingAnnee
                     );
                     continue; // Skip this entry
                 }
                 
                 // If student exists in another year, apply re-registration logic
                 if ($existingStudent && !empty($existingStudent['idetudiant'])) {
                     try {
                         $connexion = Connexion::getInstance()->getPDO();
                         $connexion->beginTransaction();
                         
                         $idExistingStudent = $existingStudent['idetudiant'];
                         
                         // 1. Deactivate old enrollment
                         $stmtDesactiver = $connexion->prepare("
                             UPDATE etudiant 
                             SET est_actif = 0 
                             WHERE idetudiant = ?
                         ");
                         $stmtDesactiver->execute([$idExistingStudent]);
                         
                         // 2. Retrieve complete student data
                         $stmtGetEtudiant = $connexion->prepare("
                             SELECT * FROM etudiant WHERE idetudiant = ?
                         ");
                         $stmtGetEtudiant->execute([$idExistingStudent]);
                         $studentData = $stmtGetEtudiant->fetch(PDO::FETCH_ASSOC);
                         
                         // 3. Create new active enrollment for new promotion/year
                         $stmtInsertEtudiant = $connexion->prepare("
                             INSERT INTO etudiant (
                                 matricule, noms, lieuNaissance, dateNaissance, adressemail, 
                                 telephone, adresse, personne_contact, telephone_contact, photo, 
                                 pwd, sexe, nationalite, dateEnregistrement, 
                                 annee_acad_idannee_acad, promotion_idpromotion, idUser, est_actif
                             ) VALUES (
                                 :matricule, :noms, :lieuNaissance, :dateNaissance, :adressemail, 
                                 :telephone, :adresse, :personne_contact, :telephone_contact, :photo, 
                                 :pwd, :sexe, :nationalite, NOW(), 
                                 :annee_acad_idannee_acad, :promotion_idpromotion, :idUser, 1
                             )
                         ");
                         
                         $stmtInsertEtudiant->execute([
                             'matricule' => $studentData['matricule'],
                             'noms' => $studentData['noms'],
                             'lieuNaissance' => $studentData['lieuNaissance'],
                             'dateNaissance' => $studentData['dateNaissance'],
                             'adressemail' => $studentData['adressemail'],
                             'telephone' => $studentData['telephone'],
                             'adresse' => $studentData['adresse'],
                             'personne_contact' => $studentData['personne_contact'],
                             'telephone_contact' => $studentData['telephone_contact'],
                             'photo' => $studentData['photo'],
                             'pwd' => $studentData['pwd'],
                             'sexe' => $studentData['sexe'],
                             'nationalite' => $studentData['nationalite'],
                             'annee_acad_idannee_acad' => $idAnnee,
                             'promotion_idpromotion' => $promotionId,
                             'idUser' => $idUser
                         ]);
                         
                         // 4. Log the re-registration
                         $nouvelIdEtudiant = $connexion->lastInsertId();
                         $stmtJournal = $connexion->prepare("
                             INSERT INTO journal_activites (
                                 user_type, user_id, type_activite, id_element, description, date_activite
                             ) VALUES (
                                 'admin', :user_id, 'reinscription_import', :id_etudiant, 
                                 :description, NOW()
                             )
                         ");
                         
                         $stmtJournal->execute([
                             'user_id' => $idUser,
                             'id_etudiant' => $nouvelIdEtudiant,
                             'description' => "Réinscription lors de l'importation: étudiant {$studentData['noms']} (matricule: {$studentData['matricule']}) réinscrit pour nouvelle promotion et année"
                         ]);
                         
                         $connexion->commit();
                         $successCount++;
                         $reEnrollmentCount++;
                         
                         // Track re-enrollment details - get new promotion info
                         $oldPromotion = htmlspecialchars($existingStudent['designationPromotion'] ?? 'N/A');
                         $oldAnnee = htmlspecialchars($existingStudent['annee'] ?? 'N/A');
                         
                         // Get new promotion designation
                         $stmtNewPromo = $connexion->prepare("
                             SELECT p.designationPromotion, aa.designation as anneeAcad
                             FROM promotion p
                             JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                             WHERE p.idpromotion = ?
                         ");
                         $stmtNewPromo->execute([$promotionId]);
                         $newPromoData = $stmtNewPromo->fetch(PDO::FETCH_ASSOC);
                         $newPromotion = $newPromoData ? htmlspecialchars($newPromoData['designationPromotion']) : 'N/A';
                         $newAnnee = $newPromoData ? htmlspecialchars($newPromoData['anneeAcad']) : htmlspecialchars($idAnnee);
                         
                         $reEnrollments[] = array(
                             'matricule' => $matricule,
                             'noms' => $noms,
                             'anciennePromotion' => $oldPromotion,
                             'ancienneAnnee' => $oldAnnee,
                             'nouvellePromotion' => $newPromotion,
                             'nouvelleAnnee' => $newAnnee
                         );
                         } catch (Exception $e) {
                         $connexion->rollBack();
                         $errorCount++;
                         $errors[] = array(
                             'ligne' => $row,
                             'matricule' => $matricule,
                             'noms' => $noms,
                             'raison' => 'Erreur lors de la réinscription',
                             'details' => 'Erreur lors du traitement de la réinscription: ' . $e->getMessage(),
                             'action' => 'Consultez l\'administrateur si le problème persiste.'
                         );
                         }
                         continue;
                         }

                 // Verify promotion belongs to the correct academic year
                 if (!empty($promotionId)) {
                     $promotionQuery = "SELECT annee_acad_idannee_acad FROM promotion WHERE idpromotion = :promotionId";
                     $promotionStmt = Connexion::getInstance()->getPDO()->prepare($promotionQuery);
                     $promotionStmt->bindParam(':promotionId', $promotionId);
                     $promotionStmt->execute();
                     $promotionData = $promotionStmt->fetch(PDO::FETCH_ASSOC);
                     
                     if ($promotionData && $promotionData['annee_acad_idannee_acad'] != $idAnnee) {
                         $errorCount++;
                         $errors[] = array(
                             'ligne' => $row,
                             'matricule' => $matricule,
                             'noms' => $noms,
                             'raison' => 'Incohérence: Promotion et Année Académique',
                             'details' => 'La promotion sélectionnée n\'appartient pas à l\'année académique choisie. Vérifiez la promotion.',
                             'action' => 'Sélectionnez une promotion qui correspond à l\'année académique ' . htmlspecialchars($idAnnee)
                         );
                         continue;
                     }
                 }
                 
                 // Create student
                 $result = $universite->createStudent(
                     $matricule, 
                     $noms, 
                     $lieuNaissance, 
                     $dateNaissance, 
                     $adressemail, 
                     $telephone, 
                     $sexe, 
                     $nationalite, 
                     $idAnnee, 
                     $promotionId, 
                     $idUser,
                     $adresse,
                     $personneContact,
                     $telephoneContact
                 );
                 
                 if ($result) {
                     $successCount++;
                 } else {
                     $errorCount++;
                     $errors[] = array(
                         'ligne' => $row,
                         'matricule' => $matricule,
                         'noms' => $noms,
                         'raison' => 'Erreur lors de l\'enregistrement',
                         'details' => 'Une erreur s\'est produite lors de la création de l\'étudiant. Vérifiez que toutes les données obligatoires sont présentes et correctes.',
                         'action' => 'Consultez l\'administrateur si le problème persiste.'
                     );
                 }
             } 
             // For preparatory students
             else {
                 // Implement the logic for preparatory students
                 // This might involve a different method or table
                 $result = $universite->addPreparatoireStudent(
                     $matricule,
                     $noms,
                     $lieuNaissance,
                     $dateNaissance,
                     $adressemail,
                     $telephone,
                     $sexe,
                     $nationalite,
                     $anneeAcademique,
                     $idUser,
                     $adresse,
                     $personneContact,
                     $telephoneContact
                 );
                 
                 if ($result) {
                     $successCount++;
                 } else {
                     $errorCount++;
                     $errors[] = array(
                         'ligne' => $row,
                         'matricule' => $matricule,
                         'noms' => $noms,
                         'raison' => 'Erreur lors de l\'enregistrement préparatoire',
                         'details' => 'Une erreur s\'est produite lors de la création de l\'étudiant préparatoire.',
                         'action' => 'Vérifiez les données et réessayez.'
                     );
                     }
                     }
                     
                     // Free memory for large imports
                     unset($matricule, $noms, $sexe, $dateNaissance, $lieuNaissance, $nationalite, $adressemail, $telephone, $adresse, $personneContact, $telephoneContact);
                     
                     // Periodically trigger garbage collection every 50 rows
                     if ($row % 50 === 0) {
                     gc_collect_cycles();
                     }
                     }

         // Build re-enrollment message if any occurred
         $reEnrollmentMessage = '';
         if (!empty($reEnrollments)) {
             $reEnrollmentMessage = '<div style="max-height: 300px; overflow-y: auto; text-align: left;">';
             $reEnrollmentMessage .= '<strong style="color: #155724;">✓ ' . count($reEnrollments) . ' réinscription(s) effectuée(s):</strong><br><br>';
             
             foreach ($reEnrollments as $reenroll) {
                 $reEnrollmentMessage .= '<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 10px; margin: 8px 0; border-radius: 3px;">';
                 $reEnrollmentMessage .= '<strong>' . htmlspecialchars($reenroll['noms']) . '</strong> (Matricule: ' . htmlspecialchars($reenroll['matricule']) . ')<br>';
                 $reEnrollmentMessage .= '<em style="color: #666;">Ancien: ' . htmlspecialchars($reenroll['anciennePromotion']) . ' (' . htmlspecialchars($reenroll['ancienneAnnee']) . ')</em><br>';
                 $reEnrollmentMessage .= '<em style="color: #156064; font-weight: bold;">↓ Nouveau: ' . htmlspecialchars($reenroll['nouvellePromotion']) . ' (' . htmlspecialchars($reenroll['nouvelleAnnee']) . ')</em><br>';
                 $reEnrollmentMessage .= '</div>';
             }
             
             $reEnrollmentMessage .= '</div>';
         }
         
         // Build detailed error message if there are errors
         $errorMessage = '';
         if (!empty($errors)) {
             $errorMessage = '<div style="max-height: 400px; overflow-y: auto; text-align: left;">';
             $errorMessage .= '<strong>' . count($errors) . ' erreur(s) détectée(s):</strong><br><br>';
             
             foreach ($errors as $idx => $error) {
                 $errorMessage .= '<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin: 8px 0; border-radius: 3px;">';
                 $errorMessage .= '<strong>Ligne ' . $error['ligne'] . ':</strong> ' . htmlspecialchars($error['noms']) . '<br>';
                 $errorMessage .= '<strong style="color: #dc3545;">Problème:</strong> ' . htmlspecialchars($error['raison']) . '<br>';
                 $errorMessage .= '<strong>Matricule:</strong> ' . htmlspecialchars($error['matricule']) . '<br>';
                 $errorMessage .= '<strong>Détails:</strong> ' . htmlspecialchars($error['details']) . '<br>';
                 if (!empty($error['existant'])) {
                     $errorMessage .= '<em style="color: #666;">ℹ️ ' . htmlspecialchars($error['existant']) . '</em><br>';
                 }
                 if (!empty($error['action'])) {
                     $errorMessage .= '<em style="color: #666;">💡 ' . htmlspecialchars($error['action']) . '</em><br>';
                 }
                 $errorMessage .= '</div>';
             }
             
             $errorMessage .= '</div>';
         }

         // Show success/error message
         if ($errorCount > 0) {
             $resultHtml = '<div style=\"text-align: left;\">';
             $resultHtml .= '<strong>✓ Résultat: ' . ($successCount - $reEnrollmentCount) . ' étudiant(s) importé(s)</strong>';
             if ($reEnrollmentCount > 0) {
                 $resultHtml .= '<br><strong style="color: #156064;">↻ ' . $reEnrollmentCount . ' réinscription(s) effectuée(s)</strong>';
             }
             $resultHtml .= '<br><strong>✗ ' . $errorCount . ' erreur(s) rencontrée(s)</strong></div>';
             
             $combinedHtml = $resultHtml . '<br>';
             if (!empty($reEnrollmentMessage)) {
                 $combinedHtml .= $reEnrollmentMessage . '<br>';
             }
             $combinedHtml .= $errorMessage;
             
             echo "<script>
                     Swal.fire({
                         icon: 'warning',
                         title: 'Importation partielle',
                         html: '" . str_replace('"', '\\"', $combinedHtml) . "',
                         confirmButtonText: 'Fermer'
                     }).then(() => {
                         window.location.href = '../etudiants/etudiant.inscrit';
                     });
                   </script>";
         } else {
             $resultHtml = '<div style=\"text-align: left;\">';
             $resultHtml .= '<strong>✓ ' . ($successCount - $reEnrollmentCount) . ' étudiant(s) importé(s) avec succès</strong>';
             if ($reEnrollmentCount > 0) {
                 $resultHtml .= '<br><strong style="color: #156064;">↻ ' . $reEnrollmentCount . ' réinscription(s) effectuée(s)</strong>';
             }
             $resultHtml .= '</div>';
             
             $resultHtml .= $reEnrollmentMessage;
             
             echo "<script>
                     Swal.fire({
                         icon: 'success',
                         title: 'Importation réussie',
                         html: '" . str_replace('"', '\\"', $resultHtml) . "'
                     }).then(() => {
                         window.location.href = '../etudiants/etudiant.inscrit';
                     });
                   </script>";
         }
     } catch (Exception $e) {
         echo "<script>
                 Swal.fire({
                     icon: 'error',
                     title: 'Erreur d\'importation',
                     html: '<div style=\"text-align: left;\"><strong>Erreur:</strong> " . addslashes(htmlspecialchars($e->getMessage())) . "</div><br><div style=\"text-align: left; color: #666; font-size: 12px;\"><strong>Recommandations:</strong><ul><li>Vérifiez que le fichier Excel est au bon format</li><li>Vérifiez que les colonnes correspondent aux paramètres configurés</li><li>Assurez-vous que les données obligatoires sont présentes</li><li>Vérifiez les doublons de matricules dans le fichier</li></ul></div>'
                 }).then(() => {
                     window.location.href = '../etudiants/etudiant.inscrit';
                 });
               </script>";
     }
}
?>
