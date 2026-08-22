<?php
session_start();
require_once dirname(dirname(__FILE__)) . '/config/Connexion.php';

// Vérification des droits d'accès
if (!isset($_SESSION['idRole']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    die("Accès non autorisé");
}

$idDette = isset($_GET['id_dette']) ? intval($_GET['id_dette']) : 0;

if (!$idDette) {
    die("ID de dette manquant");
}

$db = Connexion::getInstance()->getPDO();

// Récupérer les informations de la dette
$sql = "SELECT 
            d.*,
            e.noms,
            e.matricule,
            ec.designationECUE,
            d.credits_ecue as credits,  -- Utilisation directe du champ credits_ecue
            s.numeroSemestre,
            aa.designation as annee_academique
        FROM dette_etudiant d
        JOIN etudiant e ON d.matricule = e.matricule
        JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
        JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
        JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
        WHERE d.id_dette = :dette";

$stmt = $db->prepare($sql);
$stmt->execute([':dette' => $idDette]);
$dette = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dette) {
    die("Dette non trouvée");
}

// Récupérer les évaluations existantes
$sql = "SELECT * FROM dette_evaluation 
        WHERE id_dette = :dette
        ORDER BY type_evaluation";
$stmt = $db->prepare($sql);
$stmt->execute([':dette' => $idDette]);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$noteCC = null;
$noteEX = null;
foreach ($evaluations as $eval) {
    if ($eval['type_evaluation'] == 'CC') {
        $noteCC = $eval['note'];
    } elseif ($eval['type_evaluation'] == 'EX') {
        $noteEX = $eval['note'];
    }
}

// Récupérer les sessions disponibles
$sql = "SELECT * FROM session ORDER BY idsession";
$stmt = $db->query($sql);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique active
$sql = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmt = $db->query($sql);
$anneeActive = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<form id="formEncoderNotes" onsubmit="return enregistrerNotes(event)">
    <input type="hidden" name="id_dette" value="<?= $idDette ?>">
    
    <div class="mb-3">
        <label class="form-label"><strong>Étudiant:</strong></label>
        <p><?= htmlspecialchars($dette['noms']) ?> (<?= htmlspecialchars($dette['matricule']) ?>)</p>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>ECUE:</strong></label>
        <p><?= htmlspecialchars($dette['designationECUE']) ?> - <?= $dette['credits'] ?> crédits</p>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>Note obtenue:</strong></label>
        <p><?= number_format($dette['note_obtenue'], 2) ?>/20</p>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="note_cc" class="form-label">Note CC <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="note_cc" name="note_cc" 
                       min="0" max="20" step="0.01" required
                       value="<?= $noteCC ?>">
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="note_ex" class="form-label">Note EX <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="note_ex" name="note_ex" 
                       min="0" max="20" step="0.01" required
                       value="<?= $noteEX ?>">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
        <select class="form-select" id="session" name="session" required>
            <option value="">Sélectionner une session</option>
            <?php foreach ($sessions as $session): ?>
                <option value="<?= $session['idsession'] ?>">
                    <?= htmlspecialchars($session['designSession']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="mb-3">
        <label for="annee" class="form-label">Année académique</label>
        <input type="hidden" name="annee" value="<?= $anneeActive['idannee_acad'] ?>">
        <input type="text" class="form-control" readonly 
               value="<?= htmlspecialchars($anneeActive['designation']) ?>">
    </div>
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> 
        La moyenne finale sera calculée automatiquement: MF = (CC × 40%) + (EX × 60%)
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Enregistrer
        </button>
    </div>
</form>

<script>
function enregistrerNotes(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Calculer la moyenne finale
    const noteCC = parseFloat(formData.get('note_cc'));
    const noteEX = parseFloat(formData.get('note_ex'));
    // Récupérer les pondérations depuis la configuration de l'institution
// Les pondérations doivent être récupérées depuis le serveur
const moyenneFinale = (noteCC * currentPonderations.cc) + (noteEX * currentPonderations.ex);
    
    // Confirmation
    Swal.fire({
        title: 'Confirmer l\'enregistrement',
        html: `
            <p>Note CC: <strong>${noteCC.toFixed(2)}/20</strong></p>
            <p>Note EX: <strong>${noteEX.toFixed(2)}/20</strong></p>
            <p>Moyenne finale: <strong>${moyenneFinale.toFixed(2)}/20</strong></p>
            <p>Statut: <strong>${moyenneFinale >= 10 ? 'Validée' : 'En cours'}</strong></p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'controller/save_dette_notes.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    console.log('Réponse du serveur:', response);
                    
                    // Vérifier si la réponse est bien du JSON
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erreur parsing JSON:', e);
                            Swal.fire('Erreur', 'Réponse invalide du serveur', 'error');
                            return;
                        }
                    }
                    
                    if (response.success) {
                        Swal.fire('Succès', 'Les notes ont été enregistrées avec succès', 'success');
                        $('#modalEncoder').modal('hide');
                        // Recharger la page pour afficher les changements
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        console.error('Erreur serveur:', response);
                        let errorMessage = response.message || 'Une erreur est survenue';
                        if (response.debug) {
                            console.error('Debug:', response.debug);
                        }
                        Swal.fire('Erreur', errorMessage, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', status, error);
                    console.error('Réponse:', xhr.responseText);
                    Swal.fire('Erreur', 'Impossible d\'enregistrer les notes: ' + error, 'error');
                }
            });
        }
    });
    
    return false;
}
</script>