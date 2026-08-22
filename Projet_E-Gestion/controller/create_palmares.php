<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $designation = $_POST['designation'] ?? '';
    $description = $_POST['description'] ?? '';
    $anneeAcadId = $_POST['annee_acad_idannee_acad'] ?? '';
    $promotionId = $_POST['promotion_idpromotion'] ?? '';
    $sessionId = $_POST['session_idsession'] ?? '';
    $etudiants = $_POST['etudiants'] ?? [];
    
    // Récupérer l'ID de l'utilisateur connecté
    $idUser = $_SESSION['id'] ?? 0;
    
    // Validation des données
    if (empty($designation) || empty($anneeAcadId) || empty($promotionId) || empty($sessionId) || empty($etudiants)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Tous les champs obligatoires doivent être remplis.'
            }).then(() => {
                window.location.href = '../?view=academique/ajouter_palmares';
            });
        </script>";
        exit();
    }
    
    $pdo = Connexion::getInstance()->getPDO();
    $pdo->beginTransaction();
    
    try {
        // Gestion du fichier scanné
        $fichierScanne = null;
        if (isset($_FILES['fichier_scanne']) && $_FILES['fichier_scanne']['error'] == 0) {
            $upload_dir = dirname(__DIR__) . '/uploads/palmares/';
            
            // Créer le répertoire s'il n'existe pas
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['fichier_scanne']['name'], PATHINFO_EXTENSION);
            $new_filename = 'palmares_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['fichier_scanne']['tmp_name'], $file_path)) {
                $fichierScanne = 'uploads/palmares/' . $new_filename;
            }
        }
        
        
        // Insérer le palmarès dans la base de données
        $query = "INSERT INTO palmares_archive 
                 (designation, description, annee_academique, promotion, session, 
                  fichier_scanne, date_creation, idUser, annee_acad_idannee_acad, 
                  promotion_idpromotion, session_idsession) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $designation,
            $description,
            $anneeAcadId,
            $promotionId,
            $sessionId,
            $fichierScanne,
            $idUser,
            $anneeAcadId,
            $promotionId,
            $sessionId
        ]);
        
        $palmaresId = $pdo->lastInsertId();
        
        // Enregistrer l'historique
        $query = "INSERT INTO palmares_historique 
                 (id_palmares, action, details, idUser) 
                 VALUES (?, 'Creation', ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $palmaresId,
            "Création du palmarès: $designation pour la promotion $promotionId, session $sessionId",
            $idUser
        ]);
        
        // Insérer les résultats des étudiants
        foreach ($etudiants as $etudiant) {
            // Vérifier si un étudiant avec ce matricule existe déjà dans la base de données
            $idEtudiant = null;
            if (!empty($etudiant['matricule'])) {
                $stmt = $pdo->prepare("SELECT idetudiant FROM etudiant WHERE matricule = ?");
                $stmt->execute([$etudiant['matricule']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $idEtudiant = $result['idetudiant'];
                }
            }
            
            $query = "INSERT INTO palmares_etudiant 
                     (id_palmares, nom_complet, pourcentage, mention, rang, 
                      matricule, idetudiant, credit_obtenu, credit_total) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $palmaresId,
                $etudiant['nom_complet'],
                $etudiant['pourcentage'],
                $etudiant['mention'],
                $etudiant['rang'],
                $etudiant['matricule'] ?? null,
                $idEtudiant,
                $etudiant['credit_obtenu'] ?? null,
                $etudiant['credit_total'] ?? null
            ]);
        }
        
        $pdo->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le palmarès a été créé avec succès.'
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
                text: 'Une erreur est survenue lors de la création du palmarès: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../?view=academique/ajouter_palmares';
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

