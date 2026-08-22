<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID manquant.</div>';
    exit();
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    $inscription_id = intval($_GET['id']);
    
    // Récupérer les documents de l'inscription
    $stmt = $connexion->prepare("
        SELECT die.*, lid.designation as nom_document, lid.est_obligatoire
        FROM documents_inscription_externe die
        LEFT JOIN lien_inscription_documents lid ON die.lien_document_id = lid.id
        WHERE die.inscription_externe_id = ?
        ORDER BY lid.ordre_affichage
    ");
    $stmt->execute([$inscription_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($documents)) {
        echo '<div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>
                Aucun document n\'a été soumis pour cette inscription.
              </div>';
    } else {
        echo '<div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Fichier</th>
                            <th>Taille</th>
                            <th>Date d\'ajout</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($documents as $doc) {
            $badge_class = [
                'En attente' => 'warning',
                'Validé' => 'success',
                'Rejeté' => 'danger'
            ];
            $badge = $badge_class[$doc['statut_validation']] ?? 'secondary';
            
            $taille = $doc['taille_fichier'] ? round($doc['taille_fichier'] / 1024, 2) . ' KB' : 'N/A';
            
            echo '<tr>
                    <td>
                        <strong>' . htmlspecialchars($doc['nom_document']) . '</strong>';
            if ($doc['est_obligatoire']) {
                echo ' <span class="badge bg-danger">Obligatoire</span>';
            }
            echo '    </td>
                    <td>' . htmlspecialchars($doc['nom_fichier_original']) . '</td>
                    <td>' . $taille . '</td>
                    <td>' . date('d/m/Y H:i', strtotime($doc['date_upload'])) . '</td>
                    <td><span class="badge bg-' . $badge . '">' . htmlspecialchars($doc['statut_validation']) . '</span></td>
                    <td>
                        <a href="' . htmlspecialchars($doc['chemin_fichier']) . '" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> Voir
                        </a>
                        <a href="' . htmlspecialchars($doc['chemin_fichier']) . '" download class="btn btn-sm btn-success">
                            <i class="bi bi-download"></i> Télécharger
                        </a>
                    </td>
                  </tr>';
        }
        
        echo '    </tbody>
                </table>
              </div>';
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Erreur lors du chargement des documents : ' . htmlspecialchars($e->getMessage()) . '
          </div>';
}
?>