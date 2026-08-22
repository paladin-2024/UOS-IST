<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'année académique actuelle (active)
$currentYear = null;
$queryCurrent = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtCurrent = $connexion->prepare($queryCurrent);
$stmtCurrent->execute();
$resultCurrent = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
if ($resultCurrent) {
    $currentYear = $resultCurrent['idannee_acad'];
}

// Récupérer la liste des promotions (filtrées par année courante)
if ($currentYear) {
    $stmt = $connexion->prepare("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle,
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        WHERE aa.idannee_acad = ?
        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"
    ");
    $stmt->execute([$currentYear]);
} else {
    $stmt = $connexion->query("
        SELECT p.idpromotion, p.\"designationPromotion\", p.cycle,
               o.\"designationOrientation\", s.\"designationSection\",
               aa.designation as annee_academique
        FROM promotion p
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
        ORDER BY s.\"designationSection\", o.\"designationOrientation\", p.\"designationPromotion\"
    ");
}
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la promotion sélectionnée
$selectedPromo = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;

// Récupérer les informations sur les documents obligatoires et les étudiants si une promotion est sélectionnée
$etudiants = [];
$documentsObligatoires = [];

if ($selectedPromo > 0) {
    // Récupérer le cycle de la promotion sélectionnée
    $stmt = $connexion->prepare("
        SELECT cycle FROM promotion WHERE idpromotion = ?
    ");
    $stmt->execute([$selectedPromo]);
    $cyclePromo = $stmt->fetchColumn();
    
    // Récupérer les documents obligatoires pour ce cycle
    $stmt = $connexion->prepare("
        SELECT * FROM documents_obligatoires 
        WHERE cycle = ? OR cycle = 'Tous'
        ORDER BY designation
    ");
    $stmt->execute([$cyclePromo]);
    $documentsObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les étudiants de cette promotion
    $stmt = $connexion->prepare("
        SELECT e.idetudiant, e.matricule, e.noms, e.sexe, e.telephone, e.adressemail
        FROM etudiant e
        WHERE e.promotion_idpromotion = ?
        ORDER BY e.noms
    ");
    $stmt->execute([$selectedPromo]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les documents fournis par ces étudiants
    $documentsParEtudiant = [];
    if (!empty($etudiants)) {
        $matricules = array_column($etudiants, 'matricule');
        $placeholders = str_repeat('?,', count($matricules) - 1) . '?';
        
        $stmt = $connexion->prepare("
            SELECT ed.*, do.designation as nom_doc_obligatoire
            FROM etudiant_documents ed
            LEFT JOIN documents_obligatoires do ON ed.document_obligatoire_id = do.id
            WHERE ed.matricule IN ($placeholders)
        ");
        $stmt->execute($matricules);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as $doc) {
            if (!isset($documentsParEtudiant[$doc['matricule']])) {
                $documentsParEtudiant[$doc['matricule']] = [];
            }
            
            if ($doc['document_obligatoire_id']) {
                $documentsParEtudiant[$doc['matricule']][$doc['document_obligatoire_id']] = [
                    'id' => $doc['id'],
                    'statut' => $doc['statut'],
                    'date_ajout' => $doc['date_ajout'],
                    'chemin_fichier' => $doc['chemin_fichier']
                ];
            }
        }
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Suivi des Documents Étudiants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Suivi des Documents</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionner une promotion</h5>
                        
                        <form method="GET" action="">
                            <input type="hidden" name="view" value="etudiants/suivi_documents_etudiants">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <select class="form-select" name="promotion" id="promotion">
                                        <option value="">Sélectionnez une promotion...</option>
                                        <?php foreach ($promotions as $promo): ?>
                                            <option value="<?= $promo['idpromotion'] ?>" <?= $selectedPromo == $promo['idpromotion'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($promo['designationSection'] . ' - ' . $promo['designationOrientation'] . ' - ' . $promo['designationPromotion'] . ' (' . $promo['annee_academique'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary">Afficher</button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($selectedPromo > 0): ?>
                            <?php if (empty($etudiants)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun étudiant trouvé dans cette promotion.
                                </div>
                            <?php elseif (empty($documentsObligatoires)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Aucun document obligatoire défini pour ce cycle.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" style="vertical-align: middle;">Matricule</th>
                                                <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
                                                <th colspan="<?= count($documentsObligatoires) ?>" class="text-center">Documents obligatoires</th>
                                            </tr>
                                            <tr>
                                                <?php foreach ($documentsObligatoires as $doc): ?>
                                                    <th class="text-center" title="<?= htmlspecialchars($doc['description'] ?? '') ?>"><?= htmlspecialchars($doc['designation']) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($etudiants as $etudiant): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                    <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                    
                                                    <?php foreach ($documentsObligatoires as $doc): 
                                                        $statutClass = '';
                                                        $statutIcon = '';
                                                        $actionBtn = '';
                                                        
                                                        if (isset($documentsParEtudiant[$etudiant['matricule']][$doc['id']])) {
                                                            $docEtudiant = $documentsParEtudiant[$etudiant['matricule']][$doc['id']];
                                                            
                                                            switch ($docEtudiant['statut']) {
                                                                case 'Valide':
                                                                    $statutClass = 'bg-success text-white';
                                                                    $statutIcon = '<i class="bi bi-check-circle-fill"></i>';
                                                                    $actionBtn = '<a href="' . $docEtudiant['chemin_fichier'] . '" target="_blank" class="btn btn-sm btn-light" title="Voir le document"><i class="bi bi-eye"></i></a>';
                                                                    break;
                                                                case 'En attente de validation':
                                                                    $statutClass = 'bg-warning';
                                                                    $statutIcon = '<i class="bi bi-clock"></i>';
                                                                    $actionBtn = '<a href="' . $docEtudiant['chemin_fichier'] . '" target="_blank" class="btn btn-sm btn-light" title="Voir le document"><i class="bi bi-eye"></i></a>';
                                                                    break;
                                                                case 'Rejeté':
                                                                    $statutClass = 'bg-danger text-white';
                                                                    $statutIcon = '<i class="bi bi-x-circle-fill"></i>';
                                                                    $actionBtn = '<a href="' . $docEtudiant['chemin_fichier'] . '" target="_blank" class="btn btn-sm btn-light" title="Voir le document"><i class="bi bi-eye"></i></a>';
                                                                    break;
                                                                default:
                                                                    $statutClass = 'bg-secondary text-white';
                                                                    $statutIcon = '<i class="bi bi-question-circle-fill"></i>';
                                                            }
                                                        } else {
                                                            $statutClass = 'bg-danger text-white';
                                                            $statutIcon = '<i class="bi bi-x-circle-fill"></i>';
                                                            $actionBtn = '<button type="button" class="btn btn-sm btn-primary" onclick="demandDocument(' . $etudiant['idetudiant'] . ', ' . $doc['id'] . ')" title="Demander le document"><i class="bi bi-envelope"></i></button>';
                                                        }
                                                    ?>
                                                        <td class="text-center <?= $statutClass ?>">
                                                            <div><?= $statutIcon ?></div>
                                                            <div class="mt-1"><?= $actionBtn ?></div>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <h6>Légende:</h6>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div><span class="badge bg-success"><i class="bi bi-check-circle-fill"></i></span> Document validé</div>
                                        <div><span class="badge bg-warning"><i class="bi bi-clock"></i></span> En attente de validation</div>
                                        <div><span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i></span> Document manquant/rejeté</div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="button" class="btn btn-primary" id="btnEnvoyerRappel">
                                        <i class="bi bi-envelope me-1"></i> Envoyer un rappel aux étudiants avec documents manquants
                                    </button>
                                    <button type="button" class="btn btn-success" id="btnExporter">
                                        <i class="bi bi-file-excel me-1"></i> Exporter en Excel
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Veuillez sélectionner une promotion pour voir les documents des étudiants.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour la confirmation d'envoi de rappel -->
<div class="modal fade" id="modalRappel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Envoyer un rappel de documents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/envoyer_rappel_documents.php" method="POST">
                <input type="hidden" name="promotion_id" value="<?= $selectedPromo ?>">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Un email formaté sera envoyé à tous les étudiants qui n'ont pas fourni de documents ou dont les documents ont été rejetés.
                    </div>
                    
                    <div class="mb-3">
                        <label for="objet_email" class="form-label">Objet de l'email</label>
                        <input type="text" class="form-control" id="objet_email" name="objet_email" required 
                            value="IMPORTANT: Documents obligatoires manquants">
                    </div>
                    
                    <div class="mb-3">
                        <label for="contenu_email" class="form-label">Contenu de l'email</label>
                        <div class="alert alert-secondary mb-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Variables disponibles:</strong> 
                            <code>[NOM_ETUDIANT]</code> (nom de l'étudiant), 
                            <code>[MATRICULE]</code> (matricule de l'étudiant), 
                            <code>[LISTE_DOCUMENTS]</code> (liste formatée des documents manquants)
                        </div>
                        <textarea class="form-control" id="contenu_email" name="contenu_email" rows="8" required>Cher(e) [NOM_ETUDIANT],

Nous constatons que certains documents obligatoires sont manquants dans votre dossier pour l'année académique en cours. Veuillez les fournir dans les plus brefs délais.

Les documents suivants sont requis:
[LISTE_DOCUMENTS]

Vous pouvez soumettre ces documents en vous connectant à votre espace de gestion documentaire avec votre matricule [MATRICULE].

Cordialement,
Le Service Académique</textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="copie_moi" name="copie_moi" value="1">
                                <label class="form-check-label" for="copie_moi">M'envoyer une copie des emails</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="text-muted small"><i class="bi bi-envelope"></i> Les emails seront envoyés au format HTML avec le logo de l'établissement</p>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Envoyer les rappels
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestionnaire pour le bouton de rappel
    const btnRappel = document.getElementById('btnEnvoyerRappel');
    if (btnRappel) {
        btnRappel.addEventListener('click', function() {
            var rappelModal = new bootstrap.Modal(document.getElementById('modalRappel'));
            rappelModal.show();
        });
    }
    
    // Gestionnaire pour le bouton d'export Excel
    const btnExport = document.getElementById('btnExporter');
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            window.location.href = `controller/exporter_suivi_documents.php?promotion_id=${<?= $selectedPromo ?>}`;
        });
    }
    
    // Fonction pour demander un document spécifique à un étudiant
    window.demandDocument = function(etudiantId, documentId) {
        Swal.fire({
            title: 'Demander un document',
            html: `
                <form id="demandDocumentForm">
                    <div class="mb-3">
                        <label for="objet" class="form-label">Objet</label>
                        <input type="text" class="form-control" id="objet" value="Demande de document obligatoire">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" rows="4">Bonjour,

Veuillez fournir le document manquant dans votre dossier étudiant.
Merci de le télécharger via votre espace étudiant dès que possible.

Cordialement,
Le Service Académique</textarea>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Envoyer',
            cancelButtonText: 'Annuler',
            preConfirm: () => {
                return {
                    objet: document.getElementById('objet').value,
                    message: document.getElementById('message').value
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Envoyer la demande via AJAX
                fetch('controller/demander_document.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        etudiant_id: etudiantId,
                        document_id: documentId,
                        objet: result.value.objet,
                        message: result.value.message
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Envoyé!', 'La demande a été envoyée avec succès.', 'success');
                    } else {
                        Swal.fire('Erreur!', data.message || 'Une erreur est survenue lors de l\'envoi de la demande.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Erreur!', 'Une erreur est survenue lors de l\'envoi de la demande.', 'error');
                });
            }
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>

