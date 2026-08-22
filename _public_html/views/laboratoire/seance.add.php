<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'ID du laboratoire
$idLabo = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idLabo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer l'année académique en cours
$anneeEnCours = $universite->getCurrentAcademicYear();
$anneeId = $anneeEnCours['idannee_acad'];

// Récupérer les informations du laboratoire
$queryLabo = "SELECT * FROM laboratoire WHERE idlabo = :idLabo AND annee_acad_id = :anneeId";
$stmtLabo = $db->prepare($queryLabo);
$stmtLabo->bindParam(':idLabo', $idLabo);
$stmtLabo->bindParam(':anneeId', $anneeId);
$stmtLabo->execute();
$labo = $stmtLabo->fetch(PDO::FETCH_ASSOC);

if (!$labo) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer les responsables potentiels (agents autorisés)
$queryResponsables = "SELECT a.idAgent, a.noms 
                      FROM agent a 
                      JOIN autorisation_labo al ON a.idAgent = al.idAgent
                      WHERE al.idlabo = :idLabo 
                      AND al.est_active = 1
                      AND (al.date_fin IS NULL OR al.date_fin >= CURDATE())
                      AND al.date_debut <= CURDATE()
                      ORDER BY a.noms";
$stmtResponsables = $db->prepare($queryResponsables);
$stmtResponsables->bindParam(':idLabo', $idLabo);
$stmtResponsables->execute();
$responsables = $stmtResponsables->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Nouvelle séance de laboratoire</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item"><a href="laboratoire/seance.list&id=<?= $idLabo ?>">Séances</a></li>
                <li class="breadcrumb-item active">Nouvelle séance</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations de la séance</h5>
                        
                        <div class="alert alert-info mb-4">
                            <strong>Laboratoire:</strong> <?= htmlspecialchars($labo['nom']) ?> (<?= htmlspecialchars($labo['localisation']) ?>)
                        </div>

                        <form method="POST" action="controller/create_seance_labo.php" class="row g-3">
                            <input type="hidden" name="idlabo" value="<?= $idLabo ?>">
                            
                            <div class="col-md-6">
                                <label for="titre" class="form-label">Titre de la séance</label>
                                <input type="text" class="form-control" id="titre" name="titre" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="date_seance" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date_seance" name="date_seance" required 
                                    value="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="heure_debut" class="form-label">Heure de début</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="heure_fin" class="form-label">Heure de fin</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>

                            <div class="col-md-12">
                                <label for="idresponsable" class="form-label">Responsable</label>
                                <select class="form-select" id="idresponsable" name="idresponsable" required>
                                    <option value="">Sélectionner un responsable</option>
                                    <?php foreach ($responsables as $resp): ?>
                                        <option value="<?= $resp['idAgent'] ?>"><?= htmlspecialchars($resp['noms']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="activer_qrcode" name="activer_qrcode" value="1" checked>
                                    <label class="form-check-label" for="activer_qrcode">
                                        Activer le scanner QR Code pour cette séance
                                    </label>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                                <a href="laboratoire/seance.list&id=<?= $idLabo ?>" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    $(document).ready(function() {
        // Validation des heures
        $('#heure_fin').on('change', function() {
            const heureDebut = $('#heure_debut').val();
            const heureFin = $(this).val();
            
            if (heureDebut && heureFin && heureDebut >= heureFin) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: "L'heure de fin doit être postérieure à l'heure de début"
                });
                $(this).val('');
            }
        });
        
        $('#heure_debut').on('change', function() {
            const heureDebut = $(this).val();
            const heureFin = $('#heure_fin').val();
            
            if (heureDebut && heureFin && heureDebut >= heureFin) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: "L'heure de début doit être antérieure à l'heure de fin"
                });
                $('#heure_fin').val('');
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>
