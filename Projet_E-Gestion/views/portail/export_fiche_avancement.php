<?php
require_once './assets/html2pdf/vendor/autoload.php';
use Spipu\Html2Pdf\Html2Pdf;

// Vérifier si l'ID de l'étudiant est passé en paramètre
if (isset($_GET['etudiant_id'])) {
    $etudiantId = intval($_GET['etudiant_id']);
    $universite = new Universite();
    $etudiantModel = new Etudiant();
    $agentModel = new Agent();

    // Récupérer les informations de l'étudiant
    $etudiant = $etudiantModel->getEtudiantById($etudiantId);
    if (!$etudiant) {
        echo 'Étudiant non trouvé';
        exit;
    }

    // Récupérer les informations de l'université
    $configUniversite = $universite->getConfigurationUniversite();

    // Récupérer les sujets de l'étudiant
    $sujetsEtudiant = $universite->getSujetsByEtudiant($etudiantId);

    // Calculer la progression globale
    $progression = calculerProgression($sujetsEtudiant, $etudiantModel);

    // Générer le contenu HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Fiche d\'avancement - ' . htmlspecialchars($etudiant['noms']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 10px; }
            h2 { font-size: 16px; margin-top: 20px; }
            .header { text-align: center; margin-bottom: 20px; }
            
            .timeline-item { 
                margin-bottom: 15px;
                padding-left: 20px;
                border-left: 3px solid #ddd;
            }
            
            .task-header {
                font-weight: bold;
                margin-bottom: 5px;
            }
            .task-date {
                color: #666;
                font-size: 11px;
            }
            .task-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                color: white;
            }
            .status-valide { background-color: #28a745; }
            .status-encours { background-color: #ffc107; }
            .status-attente { background-color: #6c757d; }
            .status-rejete { background-color: #dc3545; }
            .exchange {
                margin: 10px 0;
                padding: 8px;
                background-color: #f8f9fa;
                border-radius: 4px;
            }
            /* Nouveaux styles pour le tableau */
        .info-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
            table-layout: fixed; /* Fixe la largeur des colonnes */
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .info-table th, .info-table td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left;
            word-wrap: break-word; /* Permet le retour à la ligne automatique */
        }
        .info-table th { 
            background-color: #f5f5f5; 
            width: 30%; 
        }
        .info-table td {
            width: 70%;
        }
        
        /* Style spécifique pour la barre de progression */
        .progress-container {
            width: 100%;
            padding: 0;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            margin: 0;
            overflow: hidden; /* Empêche le débordement */
        }
        .progress-value {
            height: 100%;
            background-color: #0d6efd;
            border-radius: 10px;
            text-align: center;
            color: white;
            font-size: 12px;
            line-height: 20px;
            min-width: 20px; /* Largeur minimale pour afficher le pourcentage */
        }

        .entete-institutionnel {
            text-align: center;
            margin: 0 auto;
            margin-bottom: 5px;
            padding-bottom: 10px;
            width: 100%;
            display: block;
            position: relative;
        }

        .logo-container {
            margin: 0 auto;
            margin-bottom: 10px;
            text-align: center;
            width: 100%;
            display: block;
        }

        .logo-container img {
            max-height: 80px;
            margin: 0 auto;
            display: block;
        }

        .institution-name {
            font-size: 16px;
            font-weight: bold;
            margin: 5px auto;
            width: 100%;
            text-align: center;
            display: block;
        }

        .institution-details {
            font-size: 12px;
            margin: 3px auto;
            width: 100%;
            text-align: center;
            display: block;
        }

        .ministere {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            width: 100%;
            text-align: center;
            display: block;
        }
        </style>
    </head>
    <body>
    <div style="width: 100%; max-width: 100%; margin: 0 auto; position: relative;">
            <div class="entete-institutionnel">
                    ' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? '') . '
                </div>
                <div class="logo-container">';

        // Affichage du logo
        if (!empty($configUniversite['logo'])) {
            $logoPath = './' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $html .= '<img src="'.$logoPath.'" alt="Logo">';
            } else {
                $html .= 'Chemin logo : '.htmlspecialchars($logoPath);
            }
        }

        $html .= '
                </div>
                <div class="institution-name">
                    ' . htmlspecialchars($configUniversite['nom'] ?? '') . '
                </div>
                
                <div class="institution-details">
                    ' . (!empty($configUniversite['adresse']) ? htmlspecialchars($configUniversite['adresse']) : '') . '
                    ' . (!empty($configUniversite['ville']) ? htmlspecialchars($configUniversite['ville']) : '') . '
                    ' . (!empty($configUniversite['telephone']) ? 'Tél: ' . htmlspecialchars($configUniversite['telephone']) : '') . '
                    ' . (!empty($configUniversite['email']) ? ' | Email: ' . htmlspecialchars($configUniversite['email']) : '') . '
                </div>
                <div style="border-top: 1px solid #000; margin-top: 10px;"></div>
            </div>
        <div class="header">
            <h1>Fiche d\'avancement</h1>
        </div>

        <table class="info-table">
            <tr>
                <th>Nom de l\'étudiant</th>
                <td>' . htmlspecialchars($etudiant['noms']) . '</td>
            </tr>
            <tr>
                <th>Matricule</th>
                <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
            </tr>
            <tr>
                <th>Promotion</th>
                <td>' . htmlspecialchars($etudiant['promotion'] ?? '') . '</td>
            </tr>
            <tr>
                <th>Orientation</th>
                <td>' . htmlspecialchars($etudiant['departement'] ?? '') . '</td>
            </tr>
            <tr>
                <th>Année académique</th>
                <td>' . htmlspecialchars($etudiant['annee_academique'] ?? '') . '</td>
            </tr>
            <tr>
                <th>Progression globale</th>
                <td>
                    <div class="progress-bar">
                        <div class="progress-value" style="width: ' . $progression . '%">
                            ' . $progression . '%
                        </div>
                    </div>
                </td>
            </tr>
        </table>';

    // Pour chaque sujet
    foreach ($sujetsEtudiant as $sujet) {
        // Récupérer les informations des encadrants (agents)
        $directeur = $agentModel->getAgentById($sujet['idDirecteur']);
        $encadreur = $sujet['idEncadreur'] ? $agentModel->getAgentById($sujet['idEncadreur']) : null;
        
        $taches = $etudiantModel->getTaches($sujet['idsujets']);
        
        $html .= '
        <table class="info-table">
            <tr>
                <th>Sujet</th>
                <td>' . htmlspecialchars($sujet['intitule']) . '</td>
            </tr>
            <tr>
                <th>Directeur</th>
                <td>' . htmlspecialchars($directeur ? $directeur['designation'].' '.$directeur['noms'] : 'Non défini') . '</td>
            </tr>
            <tr>
                <th>Encadreur</th>
                <td>' . htmlspecialchars($encadreur ? $encadreur['designation'].' '.$encadreur['noms'] : 'Non défini') . '</td>
            </tr>
            <tr>
                <th>Spécialisation</th>
                <td>' . htmlspecialchars($sujet['specialisation'] ?? '') . '</td>
            </tr>
        </table>
        <div class="timeline">';

        // Initialiser le compteur de tâches pour ce sujet
        $taskNumber = 1;

        foreach ($taches as $tache) {
            $statusClass = getStatusClass($tache['validation']);
            $echanges = $etudiantModel->getEchangesTache($tache['idtaches']);

            $html .= '
            <div class="timeline-item">
                <div class="task-header">
                    <span class="task-number">Tâche ' . $taskNumber . ':</span> 
                    ' . htmlspecialchars($tache['description']) . '
                    <span class="task-status ' . $statusClass . '">' . $tache['validation'] . '</span>
                </div>
                <div class="task-date">' . date('d/m/Y', strtotime($tache['dateTache'])) . '</div>';

            // Dans la boucle des échanges
            if (!empty($echanges)) {
                $html .= '<div class="exchanges">';
                foreach ($echanges as $echange) {
                    // Déterminer l'appellation en fonction du type d'auteur
                    $appelation = match($echange['type_auteur']) {
                        'Directeur' => 'Le Directeur',
                        'Encadreur' => 'L\'Encadreur',
                        'Etudiant' => 'L\'Étudiant',
                        default => $echange['type_auteur']
                    };

                    $html .= '
                    <div class="exchange">
                        <small>' . $appelation . ' - 
                        ' . date('d/m/Y H:i', strtotime($echange['dateEchange'])) . '</small>
                        <p>' . nl2br(htmlspecialchars($echange['commentaire'])) . '</p>
                    </div>';
                }
                $html .= '</div>';
                // Incrémenter le compteur de tâches
                $taskNumber++;
            }

            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '
        <div style="text-align: right; margin-top: 30px; font-size: 11px;">
            <p>Généré le ' . date('d/m/Y à H:i') . '</p>
        </div>
    </body>
    </html>';

    try {
        $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(15, 15, 15, 15));
        $html2pdf->writeHTML($html);
        $html2pdf->output('fiche_avancement_' . $etudiant['matricule'] . '.pdf', 'I');
    } catch (Exception $e) {
        echo 'Erreur: ' . $e->getMessage();
    }
} else {
    echo 'Requête invalide';
}

/**
 * Obtient la classe CSS correspondant au statut de validation
 */
function getStatusClass($validation) {
    return match ($validation) {
        'Validé' => 'status-valide',
        'En cours' => 'status-encours',
        'Rejeté' => 'status-rejete',
        'En attente' => 'status-attente',
        'En cours de validation' => 'status-attente',
        default => 'status-en-erreur'
    };
}
/**
 * Calcule le pourcentage de progression basé sur les tâches validées
 */
function calculerProgression($sujets, $etudiantModel) {
    $totalTaches = 0;
    $tachesValidees = 0;

    foreach ($sujets as $sujet) {
        $taches = $etudiantModel->getTaches($sujet['idsujets']);
        $totalTaches += count($taches);
        foreach ($taches as $tache) {
            if ($tache['validation'] === 'Validé') {
                $tachesValidees++;
            }
        }
    }

    return $totalTaches > 0 ? round(($tachesValidees / $totalTaches) * 100) : 0;
}
?>
