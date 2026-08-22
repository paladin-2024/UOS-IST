<?php
// ========================================
// MODIFICATIONS POUR grille_notes.php
// Pour gérer correctement les dettes
// ========================================

// Dans la section où on affiche les notes des ECUE, modifier la logique pour marquer les dettes
// Remplacer la partie qui vérifie si un ECUE est une dette

// Au lieu de simplement vérifier si la note < 10, vérifier aussi la moyenne de l'UE
// Voici le code à utiliser pour déterminer si un ECUE est une dette :

// Pour chaque ECUE affiché
$estDette = false;
$classeDette = '';

if ($note !== null && $note < 10) {
    // L'ECUE est en échec, vérifier si c'est une dette
    // Calculer la moyenne de l'UE
    $moyenneUE = $deliberation->calculerMoyenneUE($matricule, $ueId, $sessionId, $anneeId);
    
    if ($moyenneUE !== false && $moyenneUE < 10) {
        // L'UE a une moyenne < 10, donc l'ECUE est une dette
        $estDette = true;
        $classeDette = 'dette-ecue';
    }
}

// Dans le CSS, ajouter un style pour les ECUE qui sont des dettes
?>
<style>
.dette-ecue {
    background-color: #ffcccc !important;
    font-weight: bold;
}

.dette-ecue::after {
    content: " (D)";
    color: #cc0000;
    font-size: 0.8em;
}

/* Style pour les UE non validées */
.ue-non-validee {
    background-color: #ffe6e6;
}

/* Légende pour les dettes */
.legende-dette {
    display: inline-block;
    margin: 10px;
    padding: 5px 10px;
    background-color: #ffcccc;
    border: 1px solid #cc0000;
    border-radius: 3px;
}
</style>

<?php
// Ajouter une légende pour expliquer les dettes
?>
<div class="alert alert-info">
    <h5>Légende :</h5>
    <ul>
        <li><span class="legende-dette">ECUE en dette (D)</span> : ECUE en échec (note < 10) appartenant à une UE non validée (moyenne UE < 10)</li>
        <li><strong>ECUE en échec simple</strong> : ECUE en échec mais compensé par l'UE (moyenne UE ≥ 10)</li>
    </ul>
</div>

<?php
// Fonction pour vérifier si un étudiant peut passer avec des dettes
function peutPasserAvecDettes($matricule, $promotionId, $sessionId, $anneeId, $deliberation) {
    // Récupérer les informations de la promotion
    $promotion = $deliberation->getPromotionById($promotionId);
    $estTerminale = $promotion['est_terminale'] ?? false;
    
    // Calculer le total des crédits et les crédits validés
    $semestres = $deliberation->getSemestresByPromotion($promotionId);
    $totalCredits = 0;
    $creditsValides = 0;
    
    foreach ($semestres as $semestre) {
        $ues = $deliberation->getUEsBySemestre($semestre['idsemestre']);
        
        foreach ($ues as $ue) {
            $ecues = $deliberation->getECUEByUE($ue['idUE']);
            $creditsUE = 0;
            
            foreach ($ecues as $ecue) {
                $credits = ($ecue['CMI'] + $ecue['TD'] + $ecue['TP']) / 25; // Diviseur par défaut
                $creditsUE += $credits;
            }
            
            $totalCredits += $creditsUE;
            
            // Vérifier si l'UE est validée
            $moyenneUE = $deliberation->calculerMoyenneUE($matricule, $ue['idUE'], $sessionId, $anneeId);
            if ($moyenneUE !== false && $moyenneUE >= 10) {
                $creditsValides += $creditsUE;
            }
        }
    }
    
    $pourcentageValide = ($totalCredits > 0) ? ($creditsValides / $totalCredits) * 100 : 0;
    
    // Règles de passage
    if ($estTerminale) {
        // Promotion terminale : doit avoir 100% des crédits
        return [
            'peut_passer' => $pourcentageValide == 100,
            'message' => $pourcentageValide == 100 
                ? 'Tous les crédits validés' 
                : "Promotion terminale : impossible de finir avec des dettes. Crédits validés: " . number_format($pourcentageValide, 2) . "%",
            'pourcentage' => $pourcentageValide
        ];
    } else {
        // Promotion non terminale : peut passer avec 75% minimum
        return [
            'peut_passer' => $pourcentageValide >= 75,
            'message' => $pourcentageValide >= 75 
                ? "Peut monter avec dettes. Crédits validés: " . number_format($pourcentageValide, 2) . "%" 
                : "Ne peut pas monter. Crédits validés: " . number_format($pourcentageValide, 2) . "% (minimum requis: 75%)",
            'pourcentage' => $pourcentageValide
        ];
    }
}

// Exemple d'utilisation dans la grille
// Après avoir calculé toutes les moyennes, vérifier le statut de passage
$statutPassage = peutPasserAvecDettes($matricule, $promotionId, $sessionId, $anneeId, $deliberation);

if ($statutPassage['peut_passer']) {
    echo '<span class="badge badge-success">' . $statutPassage['message'] . '</span>';
} else {
    echo '<span class="badge badge-danger">' . $statutPassage['message'] . '</span>';
}
?>