<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/views/405.php';

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['idetudiant'])) {
    $document_id = intval($_GET['id']);
    $idetudiant = intval($_GET['idetudiant']);
    $commentaire = isset($_GET['commentaire']) ? $_GET['commentaire'] : '';
    $idValidateur = $_SESSION['id'];
    
    if (empty($document_id) || empty($idetudiant)) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Paramètres invalides.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
        exit();
    }
    
    try {
        $conn = Connexion::getInstance()->getPDO();
        
        // Récupérer le document actuel
        $stmt = $conn->prepare("SELECT statut FROM etudiant_documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $currentDoc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentDoc) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Document introuvable.'
                }).then(() => {
                    window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
                });
            </script>";
            exit();
        }
        
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Enregistrer l'historique
        $stmt = $conn->prepare("
            INSERT INTO etudiant_documents_historique 
            (document_id, statut_precedent, nouveau_statut, commentaire, idUser)
            VALUES (?, ?, 'Valide', ?, ?)
        ");
        $stmt->execute([
            $document_id,
            $currentDoc['statut'],
            $commentaire,
            $idValidateur
        ]);
        
        // Mettre à jour le document
        $stmt = $conn->prepare("
            UPDATE etudiant_documents 
            SET statut = 'Valide', commentaire_validation = ?, 
                date_validation = NOW(), idValidateur = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $commentaire,
            $idValidateur,
            $document_id
        ]);
        
        $conn->commit();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Le document a été validé avec succès.'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
        </script>";
    } catch (PDOException $e) {
        $conn->rollBack();
        
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue: " . $e->getMessage() . "'
            }).then(() => {
                window.location.href = '../?view=enseignement/fiche_scolarite&id=$idetudiant';
            });
                </script>";
    }
} else {
    header("Location: ../index.php");
    exit();
}
