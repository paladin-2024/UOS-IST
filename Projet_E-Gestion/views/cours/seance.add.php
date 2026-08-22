<?php
include "./views/include/header.php";
$universite = new Universite();
$currentUserId = $_SESSION['id'];

$db = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours ? $anneeEnCours['idannee_acad'] : null;

$hasFullAccess = $_SESSION['idRole'] == 1;

// Récupérer les sections dont l'utilisateur est responsable
$userSections = [];
if ($anneeId) {
    $stmtSec = $db->prepare("SELECT section_idsection FROM responsable_section WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId");
    $stmtSec->bindParam(':userId', $currentUserId);
    $stmtSec->bindParam(':anneeId', $anneeId);
    $stmtSec->execute();
    $userSections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);
}

// Récupérer les ECUEs des promotions dans les sections accessibles
$ecues = [];
if ($anneeId) {
    if ($hasFullAccess) {
        $query = "SELECT ec.idECUE, ec.designationECUE, ue.designationUE, s.numeroSemestre, p.designationPromotion, sec.designationSection
                  FROM ecue ec
                  JOIN ue ON ec.UE_idUE = ue.idUE
                  JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE p.annee_acad_idannee_acad = :anneeId
                  ORDER BY sec.designationSection, p.designationPromotion, s.numeroSemestre, ec.designationECUE";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':anneeId', $anneeId);
        $stmt->execute();
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query = "SELECT ec.idECUE, ec.designationECUE, ue.designationUE, s.numeroSemestre, p.designationPromotion, sec.designationSection
                  FROM ecue ec
                  JOIN ue ON ec.UE_idUE = ue.idUE
                  JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                  JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE p.annee_acad_idannee_acad = ?
                  AND sec.idsection IN ($placeholders)
                  ORDER BY sec.designationSection, p.designationPromotion, s.numeroSemestre, ec.designationECUE";
        $stmt = $db->prepare($query);
        $params = array_merge([$anneeId], $userSections);
        $stmt->execute($params);
        $ecues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les salles disponibles
$querySalles = "SELECT * FROM salle ORDER BY designationSalle";
$stmtSalles = $db->prepare($querySalles);
$stmtSalles->execute();
$salles = $stmtSalles->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des séances de cours</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Nouvelle séance</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Créer une nouvelle séance de cours</h5>

                        <form class="row g-3" action="controller/create_seance_cours.php" method="POST">
                            <div class="col-md-6">
                                <label for="idECUE" class="form-label">Cours (ECUE)</label>
                                <select class="form-select" id="idECUE" name="idECUE" required>
                                    <option value="">Sélectionner un cours</option>
                                    <?php foreach ($ecues as $ecue): ?>
                                        <option value="<?= $ecue['idECUE'] ?>" data-nom="<?= htmlspecialchars($ecue['designationECUE']) ?>">
                                            <?= htmlspecialchars($ecue['designationECUE']) ?> 
                                            (<?= htmlspecialchars($ecue['designationPromotion']) ?> - 
                                            S<?= htmlspecialchars($ecue['numeroSemestre']) ?> - 
                                            <?= htmlspecialchars($ecue['designationSection']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="titre" class="form-label">Titre de la séance</label>
                                <input type="text" class="form-control" id="titre" name="titre" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_seance" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date_seance" name="date_seance" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="heure_debut" class="form-label">Heure de début</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="heure_fin" class="form-label">Heure de fin</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="salle" class="form-label">Salle</label>
                                <select class="form-select" id="salle" name="salle" required>
                                    <option value="">Sélectionner une salle</option>
                                    <?php foreach ($salles as $salle): ?>
                                        <option value="<?= htmlspecialchars($salle['designationSalle']) ?>">
                                            <?= htmlspecialchars($salle['designationSalle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="description" class="form-label">Description (optionnel)</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <input type="hidden" name="annee_acad_id" value="<?= $anneeId ?>">
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Créer la séance
                                </button>
                                <a href="cours/seances.list" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>

<script>
$(document).ready(function() {
    $('#idECUE').on('change select2:select select2:clear', function() {
        var selected = $(this).find(':selected');
        var titre = $('#titre');
        if (selected.val()) {
            titre.val('Cours de ' + selected.data('nom'));
        } else {
            titre.val('');
        }
    });
});
</script>
