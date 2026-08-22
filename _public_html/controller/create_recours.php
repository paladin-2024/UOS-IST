<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: login');
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../deliberation/recours');
    exit;
}

// Récupérer les données du formulaire
$matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
$id_ecue = isset($_POST['ecue']) ? intval($_POST['ecue']) : 0;
$id_session = isset($_POST['session']) ? intval($_POST['session']) : 0;
$id_annee_acad = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
$motif = isset($_POST['motif']) ? trim($_POST['motif']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$date_depot = isset($_POST['date_depot']) ? trim($_POST['date_depot']) : date('Y-m-d');
$est_paye = isset($_POST['est_paye']) ? intval($_POST['est_paye']) : 0;
$id_createur = $_SESSION['id'];

// Définir le statut en fonction du paiement
$statut = ($est_paye == 1) ? 'En traitement' : 'En attente';

// Valider les données obligatoires
if (empty($matricule) || $id_ecue <= 0 || $id_session <= 0 || $id_annee_acad <= 0 || empty($motif)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Tous les champs obligatoires doivent être remplis.'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
    exit();
}

// Connexion à la base de données
$conn = Connexion::getInstance()->getPDO();

// Vérifier si l'étudiant existe
$query_etudiant = "SELECT idetudiant FROM etudiant WHERE matricule = :matricule";
$stmt_etudiant = $conn->prepare($query_etudiant);
$stmt_etudiant->bindParam(':matricule', $matricule);
$stmt_etudiant->execute();

if ($stmt_etudiant->rowCount() == 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucun étudiant trouvé avec ce matricule.'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
    exit();
}

// Vérifier si un recours similaire existe déjà
$query_check = "SELECT id_recours FROM recours 
               WHERE matricule = :matricule 
               AND id_ecue = :id_ecue 
               AND id_session = :id_session 
               AND id_annee_acad = :id_annee_acad 
               AND statut != 'Rejeté'";
$stmt_check = $conn->prepare($query_check);
$stmt_check->bindParam(':matricule', $matricule);
$stmt_check->bindParam(':id_ecue', $id_ecue);
$stmt_check->bindParam(':id_session', $id_session);
$stmt_check->bindParam(':id_annee_acad', $id_annee_acad);
$stmt_check->execute();

if ($stmt_check->rowCount() > 0) {
    echo "<script>
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: 'Un recours similaire existe déjà pour cet étudiant, cet ECUE et cette session.'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
    exit();
}

// Traitement du fichier preuve
$preuve = null;
if (isset($_FILES['preuve']) && $_FILES['preuve']['error'] == 0) {
    $allowed = ['pdf'];
    $filename = $_FILES['preuve']['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);
    
    // Vérifier l'extension et la taille
    if (!in_array(strtolower($filetype), $allowed)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Seuls les fichiers PDF sont autorisés.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
        exit();
    }
    
    if ($_FILES['preuve']['size'] > 5 * 1024 * 1024) { // 5MB
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'La taille du fichier ne doit pas dépasser 5 MB.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
        exit();
    }
    
    // Créer un nom de fichier unique
    $new_filename = uniqid('recours_') . '.' . $filetype;
    $upload_dir = dirname(__DIR__) . '/uploads/recours/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Déplacer le fichier
    if (move_uploaded_file($_FILES['preuve']['tmp_name'], $upload_dir . $new_filename)) {
        $preuve = 'uploads/recours/' . $new_filename;
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors du téléchargement du fichier.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
        exit();
    }
}

try {
    // Insérer le recours dans la base de données avec le statut qui dépend du paiement
    $query = "INSERT INTO recours (matricule, id_ecue, id_session, id_annee_acad, motif, description, 
              preuve, date_creation, statut, id_createur, est_paye) 
              VALUES (:matricule, :id_ecue, :id_session, :id_annee_acad, :motif, :description, 
              :preuve, :date_creation, :statut, :id_createur, :est_paye)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':matricule', $matricule);
    $stmt->bindParam(':id_ecue', $id_ecue);
    $stmt->bindParam(':id_session', $id_session);
    $stmt->bindParam(':id_annee_acad', $id_annee_acad);
    $stmt->bindParam(':motif', $motif);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':preuve', $preuve);
    $stmt->bindParam(':date_creation', $date_depot);
    $stmt->bindParam(':statut', $statut);  // Utiliser le statut basé sur le paiement
    $stmt->bindParam(':id_createur', $id_createur);
    $stmt->bindParam(':est_paye', $est_paye);
    
    $result = $stmt->execute();
    
    if ($result) {
        // Récupérer l'ID du recours inséré
        $id_recours = $conn->lastInsertId();
        
        $message = ($est_paye == 1) 
            ? 'Le recours a été enregistré avec succès et est en traitement.' 
            : 'Le recours a été enregistré avec succès et est en attente de paiement.';
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '$message'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'enregistrement du recours.'
            }).then(() => {
                window.location.href = '../deliberation/recours';
            });
        </script>";
    }
} catch (PDOException $e) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur de base de données',
            text: 'Une erreur est survenue: " . addslashes($e->getMessage()) . "'
        }).then(() => {
            window.location.href = '../deliberation/recours';
        });
    </script>";
}
?>
