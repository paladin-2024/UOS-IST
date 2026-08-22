<?php
include_once "./views/include/header.php";

// Vérifier si un ID de recours est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucun recours spécifié.'
        }).then(() => {
            window.location.href = 'deliberation/recours';
        });
    </script>";
    exit();
}

$id_recours = intval($_GET['id']);
$conn = Connexion::getInstance()->getPDO();

// Récupérer les détails du recours
$query = "SELECT r.*, 
            e.noms as nom_etudiant,
            ec.\"designationECUE\",
            u.\"designationUE\",
            s.\"designSession\",
            a.designation as annee_acad,
            p.\"designationPromotion\",
            u_creator.nomUser as nom_createur
          FROM recours r
          LEFT JOIN etudiant e ON r.matricule = e.matricule
          LEFT JOIN ecue ec ON r.id_ecue = ec.\"idECUE\"
          LEFT JOIN ue u ON ec.\"UE_idUE\" = u.\"idUE\"
          LEFT JOIN session s ON r.id_session = s.idsession
          LEFT JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
          LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
          LEFT JOIN t_users u_creator ON r.id_createur = u_creator.idUser
          WHERE r.id_recours = :id_recours";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id_recours', $id_recours);
$stmt->execute();
$recours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recours) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Recours non trouvé.'
        }).then(() => {
            window.location.href = 'deliberation/recours';
        });
    </script>";
    exit();
}

// Récupérer la réponse au recours (s'il y en a)
$query_reponse = "SELECT rr.*, 
                    a.noms as nom_enseignant,
                    u_validateur.nomUser as nom_validateur
                  FROM recours_reponse rr
                  LEFT JOIN agent a ON rr.id_enseignant = a.\"idAgent\"
                  LEFT JOIN t_users u_validateur ON rr.id_validateur = u_validateur.idUser
                  WHERE rr.id_recours = :id_recours
                  ORDER BY rr.date_reponse DESC
                  LIMIT 1";
$stmt_reponse = $conn->prepare($query_reponse);
$stmt_reponse->bindParam(':id_recours', $id_recours);
$stmt_reponse->execute();
$reponse = $stmt_reponse->fetch(PDO::FETCH_ASSOC);

// Récupérer la liste des enseignants pour le formulaire de réponse
$query_enseignants = "SELECT a.\"idAgent\", a.noms 
                     FROM agent a 
                     LEFT JOIN agent_section ags ON a.\"idAgent\" = ags.\"idAgent\"
                     JOIN enseignant_ecue ee ON a.\"idAgent\" = ee.\"idAgent\"
                     WHERE ee.\"idECUE\" = :id_ecue
                     AND a.type_agent = 'Enseignant'
                     ORDER BY a.noms";
$stmt_enseignants = $conn->prepare($query_enseignants);
$stmt_enseignants->bindParam(':id_ecue', $recours['id_ecue']);
$stmt_enseignants->execute();
$enseignants = $stmt_enseignants->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la note actuelle de l'étudiant pour cet ECUE et cette session
$query_note = "SELECT cg.CC, cg.EX, cg.MF
               FROM cotes_grille cg
               WHERE cg.\"ECUE_idECUE\" = :id_ecue
               AND cg.session_idsession = :id_session
               AND cg.matricule = :matricule
               AND cg.annee_acad_id = :id_annee";
$stmt_note = $conn->prepare($query_note);
$stmt_note->bindParam(':id_ecue', $recours['id_ecue']);
$stmt_note->bindParam(':id_session', $recours['id_session']);
$stmt_note->bindParam(':matricule', $recours['matricule']);
$stmt_note->bindParam(':id_annee', $recours['id_annee_acad']);
$stmt_note->execute();
$note_actuelle = $stmt_note->fetch(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails du Recours</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="deliberation/recours">Recours</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Détails du recours -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations sur le Recours</h5>
                        
                        <!-- Statut du recours avec badge coloré -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6>Statut: 
                                    <?php 
                                    $statut_class = '';
                                    switch($recours['statut']) {
                                        case 'En attente': $statut_class = 'badge bg-warning'; break;
                                        case 'En traitement': $statut_class = 'badge bg-info'; break;
                                        case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                        case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                        default: $statut_class = 'badge bg-secondary';
                                    }
                                    ?>
                                    <span class="<?= $statut_class ?>"><?= htmlspecialchars($recours['statut']) ?></span>
                                </h6>
                            </div>
                        </div>
                        
                        <!-- Informations sur l'étudiant -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Matricule:</label>
                                <p><?= htmlspecialchars($recours['matricule']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Nom de l'étudiant:</label>
                                <p><?= htmlspecialchars($recours['nom_etudiant']) ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Promotion:</label>
                                <p><?= htmlspecialchars($recours['designationPromotion'] ?? 'Non spécifiée') ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Année académique:</label>
                                <p><?= htmlspecialchars($recours['annee_acad']) ?></p>
                            </div>
                        </div>
                        
                        <!-- Informations sur le cours -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">ECUE concerné:</label>
                                <p><?= htmlspecialchars($recours['designationECUE']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">UE:</label>
                                <p><?= htmlspecialchars($recours['designationUE']) ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Session:</label>
                                <p><?= htmlspecialchars($recours['designSession']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Motif du recours:</label>
                                <p><?= htmlspecialchars($recours['motif']) ?></p>
                            </div>
                        </div>
                        
                        <!-- Note actuelle -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Note actuelle:</label>
                                <?php if ($note_actuelle): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Contrôle continu (CC)</th>
                                                <th>Examen (EX)</th>
                                                <th>Moyenne finale (MF)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= $note_actuelle['CC'] ? number_format($note_actuelle['CC'], 2) : 'N/A' ?></td>
                                                <td><?= $note_actuelle['EX'] ? number_format($note_actuelle['EX'], 2) : 'N/A' ?></td>
                                                <td class="fw-bold"><?= $note_actuelle['MF'] ? number_format($note_actuelle['MF'], 2) : 'N/A' ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">Aucune note disponible pour cet ECUE dans cette session.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description du recours -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Description détaillée:</label>
                                <p><?= nl2br(htmlspecialchars($recours['description'] ?? 'Aucune description fournie.')) ?></p>
                            </div>
                        </div>
                        
                        <!-- Document joint -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Document joint:</label>
                                <?php if (!empty($recours['preuve'])): ?>
                                <p>
                                    <a href="uploads/recours/<?= $recours['preuve'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-pdf"></i> Voir le document
                                    </a>
                                </p>
                                <?php else: ?>
                                <p class="text-muted">Aucun document joint.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Informations administratives -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Date de dépôt:</label>
                                <p><?= date('d/m/Y', strtotime($recours['date_creation'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Encodé par:</label>
                                <p><?= htmlspecialchars($recours['nom_createur']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de réponse et réponses existantes -->
            <div class="col-lg-4">
                <?php if ($reponse): ?>
                <!-- Afficher la réponse existante -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Réponse au Recours</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Nouvelles notes:</label>
                                <?php if (!is_null($reponse['nouvelle_note_cc']) || !is_null($reponse['nouvelle_note_ex'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Contrôle continu (CC)</th>
                                                <th>Examen (EX)</th>
                                                <th>Moyenne finale (MF) calculée</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= $reponse['nouvelle_note_cc'] !== null ? number_format($reponse['nouvelle_note_cc'], 2) : 'Inchangée' ?></td>
                                                <td><?= $reponse['nouvelle_note_ex'] !== null ? number_format($reponse['nouvelle_note_ex'], 2) : 'Inchangée' ?></td>
                                                <td>
                                                    <?php 
                                                    // Calculer la MF si les deux notes sont fournies ou afficher "Recalculée" ou "Inchangée"
                                                    if ($reponse['nouvelle_note_cc'] !== null && $reponse['nouvelle_note_ex'] !== null) {
                                                        // Récupérer les pondérations CC et EX (à adapter selon votre logique)
                                                        // Récupérer les pondérations depuis la configuration
$db = Connexion::getInstance()->getPDO();
$configPondQuery = $db->query("SELECT ponderation_cc_defaut, ponderation_ex_defaut FROM configuration_universite LIMIT 1");
$configPond = $configPondQuery->fetch(PDO::FETCH_ASSOC);
$ponderation_cc = $configPond && isset($configPond['ponderation_cc_defaut']) ? (float)$configPond['ponderation_cc_defaut'] : 0.4;
$ponderation_ex = $configPond && isset($configPond['ponderation_ex_defaut']) ? (float)$configPond['ponderation_ex_defaut'] : 0.6;
                                                        
                                                        // Calculer la moyenne pondérée
                                                        $mf = ($reponse['nouvelle_note_cc'] * $ponderation_cc) + 
                                                            ($reponse['nouvelle_note_ex'] * $ponderation_ex);
                                                        
                                                        echo '<strong>' . number_format($mf, 2) . '</strong>';
                                                    } elseif ($reponse['nouvelle_note_cc'] !== null || $reponse['nouvelle_note_ex'] !== null) {
                                                        echo '<em>Recalculée</em>';
                                                    } else {
                                                        echo 'Inchangée';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">Aucun changement de notes proposé.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Commentaire:</label>
                                <p><?= nl2br(htmlspecialchars($reponse['commentaire'] ?? 'Aucun commentaire.')) ?></p>
                            </div>
                        </div>
                <?php elseif ($recours['statut'] == 'En attente' || $recours['statut'] == 'En traitement'): ?>
                <!-- Formulaire pour ajouter une réponse -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répondre au Recours</h5>
                        
                        <form action="controller/add_recours_reponse.php" method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="id_recours" value="<?= $id_recours ?>">
                            
                            <!-- Sélection de l'enseignant (si l'utilisateur est admin ou jury) -->
                            <div class="mb-3">
                                <label for="id_enseignant" class="form-label">Enseignant responsable</label>
                                <select class="form-select" id="id_enseignant" name="id_enseignant" required>
                                    <option value="">Sélectionner un enseignant...</option>
                                    <?php foreach($enseignants as $enseignant): ?>
                                        <option value="<?= $enseignant['idAgent'] ?>"><?= htmlspecialchars($enseignant['noms']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un enseignant.
                                </div>
                            </div>
                            
                            <!-- Nouvelles notes (CC et EX) -->
                            <div class="mb-3">
                                <label class="form-label">Nouvelles notes (laissez vide si aucun changement)</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">CC</span>
                                            <input type="number" class="form-control" id="nouvelle_note_cc" name="nouvelle_note_cc" 
                                                min="0" max="20" step="0.01" placeholder="Note de contrôle continu">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text">EX</span>
                                            <input type="number" class="form-control" id="nouvelle_note_ex" name="nouvelle_note_ex" 
                                                min="0" max="20" step="0.01" placeholder="Note d'examen">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">
                                    Entrez des valeurs entre 0 et 20. La moyenne finale sera recalculée automatiquement.
                                </div>
                            </div>
                            
                            <!-- Commentaire -->
                            <div class="mb-3">
                                <label for="commentaire" class="form-label">Commentaire</label>
                                <textarea class="form-control" id="commentaire" name="commentaire" rows="4" required></textarea>
                                <div class="invalid-feedback">
                                    Veuillez fournir un commentaire.
                                </div>
                            </div>
                            
                            <!-- Bouton de soumission -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Soumettre la réponse
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Boutons d'action -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Actions</h5>
                        
                        <div class="d-grid gap-2">
                            <a href="javascript:history.back();" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Retour à la liste
                            </a>

                            
                            <?php if ($recours['statut'] == 'En attente'): ?>
                            <form action="controller/update_recours_status.php" method="POST">
                                <input type="hidden" name="id_recours" value="<?= $id_recours ?>">
                                <input type="hidden" name="nouveau_statut" value="En traitement">
                                <button type="submit" class="btn btn-info w-100">
                                    <i class="bi bi-hourglass-split"></i> Marquer en traitement
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if (($recours['statut'] == 'En attente' || $recours['statut'] == 'En traitement') && 
                                    isset($_SESSION['role']) && ($_SESSION['role'] == 'Administrateur' || $_SESSION['role'] == 'Jury')): ?>
                            <form action="controller/update_recours_status.php" method="POST">
                                <input type="hidden" name="id_recours" value="<?= $id_recours ?>">
                                <input type="hidden" name="nouveau_statut" value="Rejeté">
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir rejeter ce recours?')">
                                    <i class="bi bi-x-circle"></i> Rejeter le recours
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Validation du formulaire Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include_once "./views/include/footer.php"; ?>
