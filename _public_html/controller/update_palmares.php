<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $idPalmares = intval($_POST['id_palmares'] ?? 0);
    $designation = $_POST['designation'] ?? '';
    $description = $_POST['description'] ?? '';
    $anneeAcademique = $_POST['annee_academique'] ?? '';
    $promotion = $_POST['promotion'] ?? '';
    $session = $_POST['session'] ?? '';
    $anneeAcadId = intval($_POST['annee_acad_idannee_acad'] ?? 0);
    $promotionId = intval($_POST['promotion_idpromotion'] ?? 0);
    $sessionId = intval($_POST['session_idsession'] ?? 0);
    $etudiants = $_POST['etudiants'] ?? [];
    $supprimerFichier = isset($_POST['supprimer_fichier']);
    
    // Récupérer l'ID de l'utilisateur connecté
    $idUser = $_SESSION['id'] ?? 0;
    
    // Validation des données
    if (empty($designation) || empty($anneeAcademique) || empty($promotion) || empty($session) || empty($etudiants)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../?view=academique/modifier_palmares&id={$idPalmares}';
            });
        </script>";
        exit();
    }
    
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    try {
        // Récupérer les informations actuelles du palmarès
        $stmt = $pdo->prepare("SELECT fichier_scanne FROM palmares_archive WHERE id_palmares = ?");
        $stmt->execute([$idPalmares]);
        $palmaresActuel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$palmaresActuel) {
            throw new Exception("Le palmarès spécifié n'existe pas.");
        }
        
        // Gestion du fichier scanné
        $fichierScanne = $palmaresActuel['fichier_scanne'];
        
        // Supprimer le fichier si demandé
        if ($supprimerFichier && !empty($fichierScanne)) {
            $cheminFichier = dirname(__DIR__) . '/' . $fichierScanne;
            if (file_exists($cheminFichier)) {
                unlink($cheminFichier);
            }
            $fichierScanne = null;
        }
        
        // Traiter le nouveau fichier s'il est fourni
        if (isset($_FILES['fichier_scanne']) && $_FILES['fichier_scanne']['error'] == 0) {
            $upload_dir = dirname(__DIR__) . '/uploads/palmares/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Supprimer l'ancien fichier s'il existe
            if (!empty($fichierScanne)) {
                $cheminFichier = dirname(__DIR__) . '/' . $fichierScanne;
                if (file_exists($cheminFichier)) {
                    unlink($cheminFichier);
                }
            }
            
            $file_extension = pathinfo($_FILES['fichier_scanne']['name'], PATHINFO_EXTENSION);
            $new_filename = 'palmares_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['fichier_scanne']['tmp_name'], $file_path)) {
                $fichierScanne = 'uploads/palmares/' . $new_filename;
            }
        }
        
        // Mettre à jour le palmarès dans la base de données
        $query = "UPDATE palmares_archive 
                 SET designation = ?, description = ?, annee_academique = ?, 
                     promotion = ?, session = ?, fichier_scanne = ?,
                     date_modification = NOW(), \"idUser\" = ?,
                     annee_acad_idannee_acad = ?, promotion_idpromotion = ?, 
                     session_idsession = ?
                 WHERE id_palmares = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $designation,
            $description,
            $anneeAcademique,
            $promotion,
            $session,
            $fichierScanne,
            $idUser,
            $anneeAcadId,
            $promotionId,
            $sessionId,
            $idPalmares
        ]);
        
        // Enregistrer l'historique
        $query = "INSERT INTO palmares_historique 
                 (id_palmares, action, details, \"idUser\") 
                 VALUES (?, 'Modification', ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $idPalmares,
            "Modification du palmarès: $designation",
            $idUser
        ]);
        
        // Obtenir la liste des IDs d'étudiants existants
        $stmt = $pdo->prepare("SELECT id_palmares_etudiant FROM palmares_etudiant WHERE id_palmares = ?");
        $stmt->execute([$idPalmares]);
        $etudiantsExistants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Suivre les IDs d'étudiants qui sont mis à jour
        $etudiants_mis_a_jour = [];
        
        // Mettre à jour ou insérer les étudiants
        foreach ($etudiants as $etudiant) {
            $idPalmaresEtudiant = !empty($etudiant['id_palmares_etudiant']) ? intval($etudiant['id_palmares_etudiant']) : 0;
            
            // Vérifier si un étudiant avec ce matricule existe déjà
            $idEtudiant = null;
            if (!empty($etudiant['matricule'])) {
                $stmt = $pdo->prepare("SELECT idetudiant FROM etudiant WHERE matricule = ?");
                $stmt->execute([$etudiant['matricule']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $idEtudiant = $result['idetudiant'];
                }
            }
            
            if ($idPalmaresEtudiant > 0) {
                // Mettre à jour un étudiant existant
                $query = "UPDATE palmares_etudiant
                         SET nom_complet = ?, pourcentage = ?, mention = ?, rang = ?,
                             matricule = ?, idetudiant = ?, credit_obtenu = ?, credit_total = ?
                         WHERE id_palmares_etudiant = ? AND id_palmares = ?";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $etudiant['nom_complet'],
                    $etudiant['pourcentage'],
                    $etudiant['mention'],
                    $etudiant['rang'],
                    $etudiant['matricule'] ?? null,
                    $idEtudiant,
                    $etudiant['credit_obtenu'] ?? null,
                    $etudiant['credit_total'] ?? null,
                    $idPalmaresEtudiant,
                    $idPalmares
                ]);
                
                $etudiants_mis_a_jour[] = $idPalmaresEtudiant;
            } else {
                // Ajouter un nouvel étudiant
                $query = "INSERT INTO palmares_etudiant
                         (id_palmares, nom_complet, pourcentage, mention, rang,
                          matricule, idetudiant, credit_obtenu, credit_total)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $idPalmares,
                    $etudiant['nom_complet'],
                    $etudiant['pourcentage'],
                    $etudiant['mention'],
                    $etudiant['rang'],
                    $etudiant['matricule'] ?? null,
                    $idEtudiant,
                    $etudiant['credit_obtenu'] ?? null,
                    $etudiant['credit_total'] ?? null
                ]);
                
                $etudiants_mis_a_jour[] = $pdo->lastInsertId();
            }
        }
        
        // Supprimer les étudiants qui ne sont plus présents
        foreach ($etudiantsExistants as $idExistant) {
            if (!in_array($idExistant, $etudiants_mis_a_jour)) {
                $stmt = $pdo->prepare("DELETE FROM palmares_etudiant WHERE id_palmares_etudiant = ?");
                $stmt->execute([$idExistant]);
            }
        }
        
        $pdo->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le palmarès a été mis à jour avec succès.'
            }).then(() => {
                window.location.href = '../?view=academique/palmares';
            });
        </script>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la mise à jour du palmarès: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../?view=academique/modifier_palmares&id={$idPalmares}';
            });
        </script>";
    }
    
    exit();
} else {
    // Redirection si accès direct au fichier
    header("Location: ../?view=academique/palmares");
    exit();
}
?>