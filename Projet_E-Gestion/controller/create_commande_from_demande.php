<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Vérifier si l'ID de la demande est fourni
if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $demandeId = intval($_GET['id']);
    
    try {
        // Récupérer les informations de la demande de prix
        $queryDemande = "SELECT dp.*, f.nom_fournisseur 
                         FROM demande_prix dp 
                         JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur 
                         WHERE dp.id_demande_prix = :id AND dp.etat = 'Validé'";
        $stmtDemande = $db->prepare($queryDemande);
        $stmtDemande->bindParam(':id', $demandeId, PDO::PARAM_INT);
        $stmtDemande->execute();
        $demande = $stmtDemande->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande) {
            throw new Exception("Demande de prix non trouvée ou non validée");
        }
        
        // Récupérer les lignes de la demande
        $queryLignes = "SELECT ldp.*, p.code_produit, p.libelle_produit 
                        FROM ligne_demande_prix ldp 
                        JOIN produit p ON ldp.id_produit = p.id_produit 
                        WHERE ldp.id_demande_prix = :id_demande";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_demande', $demandeId, PDO::PARAM_INT);
        $stmtLignes->execute();
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);
        
        // Stocker les données en session pour les utiliser dans le formulaire de création
        $_SESSION['commande_from_demande'] = [
            'id_demande_prix' => $demandeId,
            'id_fournisseur' => $demande['id_fournisseur'],
            'nom_fournisseur' => $demande['nom_fournisseur'],
            'lignes' => $lignes
        ];
        
        // Rediriger vers le formulaire de création de commande
        header('Location: ../achats/commandes/commandes.add');
        exit();
        
    } catch (Exception $e) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '" . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../achats/demandes/demandes.list';
            });
        </script>";
        exit();
    }
} else {
    header('Location: ../achats/demandes/demandes.list');
    exit();
}
