<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Frais.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérification que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Récupération des paramètres
$etudiantId = isset($_GET['etudiantId']) ? intval($_GET['etudiantId']) : 0;
$anneeAcadId = isset($_GET['anneeAcadId']) ? intval($_GET['anneeAcadId']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'academique';

if ($etudiantId <= 0 || $anneeAcadId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Paramètres invalides']);
    exit();
}

$fraisModel = new Frais();
$universite = new Universite();

// Récupérer l'étudiant
$etudiant = $universite->getEtudiantById($etudiantId);
if (!$etudiant) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Étudiant non trouvé']);
    exit();
}

$result = [];

if ($type == 'academique') {
    // Récupérer les frais pour la promotion de l'étudiant
    $fraisPromotion = $fraisModel->getFraisByPromotion($etudiant['promotion_idpromotion'], $anneeAcadId);
    
    // Récupérer les paiements de l'étudiant
    $paiements = $fraisModel->getPaiementsByEtudiant($etudiantId, $anneeAcadId);
    
    foreach ($fraisPromotion as $frais) {
        // Calculer le montant déjà payé pour ce frais
        $montantPaye = 0;
        foreach ($paiements as $paiement) {
            if ($paiement['frais_idfrais'] == $frais['idfrais']) {
                $montantPaye += $paiement['montantPaye'];
            }
        }
        
        $montantRestant = max(0, $frais['montant'] - $montantPaye);
        
        // SUPPRIMER CETTE CONDITION pour afficher tous les frais
        // même ceux dont le montant restant est 0
        $result[] = [
            'idfrais' => $frais['idfrais'],
            'designation' => $frais['designation'],
            'montant' => $frais['montant'],
            'montantPaye' => $montantPaye,
            'montantRestant' => $montantRestant,
            'devise' => $frais['devise'],
            'estObligatoire' => $frais['estObligatoire'] ?? 0
        ];
    }
} else {
    // Pour les frais de soutenance
    $fraisSoutenance = $fraisModel->getFraisSoutenanceForEtudiant($etudiantId, $anneeAcadId);
    
    foreach ($fraisSoutenance as $frais) {
        $result[] = [
            'idfrais' => $frais['idfrais_soutenance'],
            'designation' => $frais['designation'],
            'montant' => $frais['montant'],
            'montantPaye' => $frais['montantPaye'] ?? 0,
            'montantRestant' => $frais['montantRestant'] ?? $frais['montant'],
            'devise' => $frais['devise']
        ];
    }
}

// Retourner le résultat au format JSON
header('Content-Type: application/json');
echo json_encode($result);
exit();
