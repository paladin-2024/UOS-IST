<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

// Récupérer l'ID de la séance
$idSeance = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$idSeance) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer les informations de la séance
$querySeance = "SELECT sl.*, l.nom as nom_labo, l.localisation, a.noms as responsable_nom
                FROM seance_labo sl
                JOIN laboratoire l ON sl.idlabo = l.idlabo
                LEFT JOIN agent a ON l.responsable_id = a.\"idAgent\"
                WHERE sl.idseance_labo = :idSeance";


$stmtSeance = $db->prepare($querySeance);
$stmtSeance->bindParam(':idSeance', $idSeance);
$stmtSeance->execute();
$seance = $stmtSeance->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    echo "<script>window.location.href='laboratoire/laboratoire.list';</script>";
    exit;
}

// Récupérer la liste des présences pour cette séance
$queryPresences = "SELECT pl.*, e.noms, e.matricule, e.photo
                  FROM presence_labo pl
                  JOIN etudiant_tempon e ON pl.idetudiant = e.idetudiant
                  WHERE pl.idseance_labo = :idSeance
                  ORDER BY pl.heure_arrivee DESC";

$stmtPresences = $db->prepare($queryPresences);
$stmtPresences->bindParam(':idSeance', $idSeance);
$stmtPresences->execute();
$presences = $stmtPresences->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Liste des présences</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item"><a href="laboratoire/laboratoire.list">Laboratoires</a></li>
                <li class="breadcrumb-item"><a href="laboratoire/seance.list&id=<?= $seance['idlabo'] ?>">Séances</a></li>
                <li class="breadcrumb-item active">Présences</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Présences pour la séance: <?= htmlspecialchars($seance['titre']) ?>
                            <span>| <a target="_blank" href="controller/export_presences_labo.php?id=<?= $idSeance ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-file-pdf"></i> Exporter (PDF)
                            </a></span>
                        </h5>

                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Laboratoire:</strong> <?= htmlspecialchars($seance['nom_labo']) ?><br>
                                    <strong>Localisation:</strong> <?= htmlspecialchars($seance['localisation']) ?><br>
                                    <strong>Responsable:</strong> <?= htmlspecialchars($seance['responsable_nom']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Date:</strong> <?= (new DateTime($seance['date_seance']))->format('d/m/Y') ?><br>
                                    <strong>Horaire:</strong> <?= (new DateTime($seance['heure_debut']))->format('H:i') ?> - <?= (new DateTime($seance['heure_fin']))->format('H:i') ?><br>
                                    <strong>Total présences:</strong> <?= count($presences) ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped datatable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Étudiant</th>
                                        <th>Matricule</th>
                                        <th>Heure d'arrivée</th>
                                        <th>Méthode</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($presences as $presence): 
                                        $heureArrivee = new DateTime($presence['heure_arrivee']);
                                        
                                        // Déterminer la méthode d'enregistrement
                                        $methodeIcon = '';
                                        $methodeText = '';
                                        
                                        if ($presence['methode_enregistrement'] == 'QR') {
                                            $methodeIcon = '<i class="bi bi-qr-code text-primary me-1"></i>';
                                            $methodeText = 'Scan QR Code';
                                        } elseif ($presence['methode_enregistrement'] == 'MANUEL') {
                                            $methodeIcon = '<i class="bi bi-person-check text-success me-1"></i>';
                                            $methodeText = 'Ajout manuel';
                                        } else {
                                            $methodeIcon = '<i class="bi bi-journal-check text-info me-1"></i>';
                                            $methodeText = 'Système';
                                        }
                                        
                                        // Chemin de la photo
                                        $photoPath = !empty($presence['photo']) ? '' . $presence['photo'] : 'uploads/user.png';
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td>
                                            <img src="<?= $photoPath ?>" alt="Photo" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                        </td>
                                        <td><?= htmlspecialchars($presence['noms']) ?></td>
                                        <td><?= htmlspecialchars($presence['matricule']) ?></td>
                                        <td><?= $heureArrivee->format('H:i:s') ?></td>
                                        <td>
                                            <?= $presence['methode_enregistrement'] ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deletePresence(<?= $presence['idpresence_labo'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Bouton pour ajouter une présence manuellement -->
                        <div class="mt-4">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPresenceModal">
                                <i class="bi bi-plus-circle"></i> Ajouter une présence manuellement
                            </button>
                            <a href="laboratoire/seance.list&id=<?= $seance['idlabo'] ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Retour aux séances
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter une présence manuellement -->
<div class="modal fade" id="addPresenceModal" tabindex="-1" aria-labelledby="addPresenceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPresenceModalLabel">Ajouter une présence manuellement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addPresenceForm" action="controller/add_manual_presence_labo.php" method="POST">
                    <input type="hidden" name="idSeance" value="<?= $idSeance ?>">
                    
                    <div class="mb-3">
                        <label for="etudiantSelect" class="form-label">Sélectionner un étudiant</label>
                        <select class="form-select select2" id="etudiantSelect" name="idetudiant" required>
                            <option value="">Sélectionner un étudiant</option>
                            <?php
                            // Récupérer tous les étudiants actifs
                            $queryEtudiants = "SELECT e.idetudiant, e.noms, e.matricule 
                                              FROM etudiant_tempon e 
                                              JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                                              ORDER BY e.noms ASC";
                            $stmtEtudiants = $db->query($queryEtudiants);
                            
                            while ($etudiant = $stmtEtudiants->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $etudiant['idetudiant'] . '">' . 
                                     htmlspecialchars($etudiant['noms']) . ' (' . htmlspecialchars($etudiant['matricule']) . ')' . 
                                     '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="heureArrivee" class="form-label">Heure d'arrivée</label>
                        <input type="time" class="form-control" id="heureArrivee" name="heureArrivee" 
                               value="<?= (new DateTime())->format('H:i') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer la présence</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialisation de DataTable
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            }
        });
        
        // Initialisation de Select2 pour améliorer la recherche dans le select
        $('.select2').select2({
            dropdownParent: $('#addPresenceModal'),
            placeholder: 'Rechercher un étudiant...',
            allowClear: true,
            width: '100%'
        });
    });
    
    function deletePresence(idPresence) {
        Swal.fire({
            title: 'Confirmation',
            text: "Êtes-vous sûr de vouloir supprimer cette présence ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/delete_presence_labo.php?id=${idPresence}&idSeance=<?= $idSeance ?>`;
            }
        });
    }
</script>


<?php include "./views/include/footer.php"; ?>

