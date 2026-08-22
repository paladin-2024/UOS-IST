<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';
use Spipu\Html2Pdf\Html2Pdf;

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
                display: inline-block;
                width: auto;
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
            /* Styles pour le tableau d\'information */
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
                font-size: 11px;
            }
            .info-table th { 
                background-color: #f5f5f5; 
                width: 30%; 
            }
            .info-table td {
                width: 70%;
            }
            
            /* Style pour la table des tâches */
            .tasks-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                margin-top: 10px;
                margin-bottom: 10px;
            }
            .tasks-table th, .tasks-table td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
                font-size: 10px;
                word-wrap: break-word;
                vertical-align: top;
            }
            .tasks-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            /* Ajuster les largeurs des colonnes pour la table des tâches */
            .tasks-table .num-col {
                width: 5%;
                text-align: center;
            }
            .tasks-table .desc-col {
                width: 55%;
            }
            .tasks-table .date-col {
                width: 15%;
                text-align: center;
            }
            .tasks-table .status-col {
                width: 15%;
                text-align: center;
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

            /* Ajout de page-break pour éviter les coupures indésirables */
            .sujet-section {
                page-break-inside: avoid;
            }
            
            /* Permettre les sauts de page dans les tables de tâches si elles sont trop longues */
            .tasks-container {
                page-break-inside: auto;
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
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoMime = mime_content_type($logoPath);
                $html .= '<img src="data:' . $logoMime . ';base64,' . $logoData . '" alt="Logo">';
            } else {
                $html .= 'Chemin logo : ' . htmlspecialchars($logoPath);
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
                <div style="border-top: 1px solid #000; margin-top: 10px;"></div>  <!-- Nouvelle div pour la ligne -->
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
                <td>' . htmlspecialchars($etudiant['orientation'] ?? $etudiant['departement'] ?? '') . '</td>
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
        // Récupérer les informations des agents (directeur et encadreur)
        $directeur = null;
        $encadreur = null;
        
        if (!empty($sujet['idDirecteur'])) {
            $directeur = $agentModel->getAgentById($sujet['idDirecteur']);
        }
        
        if (!empty($sujet['idEncadreur'])) {
            $encadreur = $agentModel->getAgentById($sujet['idEncadreur']);
        }
        
        $taches = $etudiantModel->getTaches($sujet['idsujets']);
        
        $html .= '
        <div class="sujet-section">
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
        </div>

        <h2 style="font-size: 14px; margin-top: 15px; margin-bottom: 10px;">Liste des tâches</h2>
        
        <div class="tasks-container">
            <table class="tasks-table">
                <tr>
                    <th class="num-col">N°</th>
                    <th class="desc-col">Description</th>
                    <th class="date-col">Date</th>
                    <th class="status-col">Statut</th>
                </tr>';

        $taskNumber = 1;
        foreach ($taches as $tache) {
            $statusClass = getStatusClass($tache['validation']);
            $html .= '
                <tr>
                    <td class="num-col">' . $taskNumber . '</td>
                    <td class="desc-col">' . htmlspecialchars($tache['description']) . '</td>
                    <td class="date-col">' . date('d/m/Y', strtotime($tache['dateTache'])) . '</td>
                    <td class="status-col">
                        <span class="task-status ' . $statusClass . '">' . $tache['validation'] . '</span>
                    </td>
                </tr>';
            $taskNumber++;
        }

        $html .= '
            </table>
        </div>';
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

function getStatusClass($validation) {
    return match ($validation) {
        'Validé' => 'status-valide',
        'En cours' => 'status-encours',
        'Rejeté' => 'status-rejete',
        'En attente' => 'status-attente',
        default => 'status-attente'
    };
}
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

