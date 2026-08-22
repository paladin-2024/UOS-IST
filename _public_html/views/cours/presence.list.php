<?php
include "./views/include/header.php";
$universite = new Universite();

// Récupérer l'ID de la séance depuis l'URL
$idSeance = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier si l'ID est valide
if (!$idSeance) {
    echo "<script>window.location.href='cours/seances.list';</script>";
    exit;
}

$db = Connexion::getInstance()->getPDO();

// Récupérer les informations de la séance
$querySeance = "SELECT s.*, e.\"designationECUE\", p.\"designationPromotion\", p.idpromotion, sem.\"numeroSemestre\"
                FROM seance_cours s
                JOIN ecue e ON s.\"idECUE\" = e.\"idECUE\"
                JOIN ue ON e.\"UE_idUE\" = ue.\"idUE\"
                JOIN semestre sem ON ue.semestre_idsemestre = sem.idsemestre
                JOIN promotion p ON sem.promotion_idpromotion = p.idpromotion
                WHERE s.idseance = :idSeance";

$stmtSeance = $db->prepare($querySeance);
$stmtSeance->bindParam(':idSeance', $idSeance);
$stmtSeance->execute();
$seance = $stmtSeance->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    echo "<script>window.location.href='cours/seances.list';</script>";
    exit;
}

// Récupérer les étudiants présents
$queryPresents = "SELECT p.*, e.matricule, e.noms, p.statut, p.commentaire, p.methode_enregistrement
                  FROM presence_cours p
                  JOIN etudiant e ON p.idetudiant = e.idetudiant
                  WHERE p.idseance = :idSeance
                  ORDER BY p.heure_arrivee";

$stmtPresents = $db->prepare($queryPresents);
$stmtPresents->bindParam(':idSeance', $idSeance);
$stmtPresents->execute();
$presents = $stmtPresents->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les étudiants de la promotion pour pouvoir marquer les absents
$queryAllEtudiants = "SELECT e.idetudiant, e.matricule, e.noms
                      FROM etudiant e
                      WHERE e.promotion_idpromotion = :idPromotion
                      ORDER BY e.noms";

$stmtAllEtudiants = $db->prepare($queryAllEtudiants);
$stmtAllEtudiants->bindParam(':idPromotion', $seance['idpromotion']);
$stmtAllEtudiants->execute();
$allEtudiants = $stmtAllEtudiants->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau d'étudiants présents pour faciliter la vérification
$etudiantsPresents = [];
foreach ($presents as $present) {
    $etudiantsPresents[$present['idetudiant']] = $present;
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Liste des présences</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item"><a href="cours/seances.list">Séances de cours</a></li>
                <li class="breadcrumb-item active">Présences</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card presence-compact">
                    <div class="card-body">
                        <style>
                            .presence-compact .card-body { padding: 0.5rem; }
                            .presence-compact .card-title { font-size: 0.9rem; margin-bottom: 0.2rem; padding-top: 0.5rem; }
                            .presence-compact .alert { padding: 0.4rem 0.6rem; font-size: 0.78rem; margin-bottom: 0.5rem; }
                            .presence-compact .table { font-size: 0.78rem; margin-bottom: 0; }
                            .presence-compact .table th, .presence-compact .table td { padding: 0.2rem 0.4rem; vertical-align: middle; }
                            .presence-compact .btn-sm { padding: 0.1rem 0.3rem; font-size: 0.72rem; }
                            .presence-compact .badge { font-size: 0.68rem; padding: 0.15rem 0.35rem; }
                            .presence-compact .nav-tabs .nav-link { font-size: 0.78rem; padding: 0.3rem 0.6rem; }
                            .presence-compact .dataTable-wrapper .dataTable-top,
                            .presence-compact .dataTable-wrapper .dataTable-bottom { font-size: 0.78rem; padding: 0.3rem 0; }
                        </style>

                        <h5 class="card-title"><?= htmlspecialchars($seance['titre']) ?></h5>

                        <div class="alert alert-info mb-2 py-1">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Cours:</strong> <?= htmlspecialchars($seance['designationECUE']) ?> |
                                    <strong>Promotion:</strong> <?= htmlspecialchars($seance['designationPromotion']) ?> - S<?= $seance['numeroSemestre'] ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Date:</strong> <?= (new DateTime($seance['date_seance']))->format('d/m/Y') ?> |
                                    <strong>Horaire:</strong> <?= (new DateTime($seance['heure_debut']))->format('H:i') ?>-<?= (new DateTime($seance['heure_fin']))->format('H:i') ?> |
                                    <strong>Salle:</strong> <?= htmlspecialchars($seance['salle']) ?> |
                                    <strong>Présents:</strong> <?= count($presents) ?>/<?= count($allEtudiants) ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <a target="_blank" href="controller/generate_qrcode_seance.php?id=<?= $idSeance ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-qr-code"></i> QR Code
                            </a>
                            <a target="_blank" href="controller/export_presences.php?id=<?= $idSeance ?>" class="btn btn-secondary btn-sm">
                                <i class="bi bi-file-earmark-pdf"></i> Exporter
                            </a>
                            <a target="_blank" href="controller/export_liste_presence_pdf.php?id=<?= $idSeance ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-file-earmark-pdf"></i> Liste de présence
                            </a>
                        </div>

                        <ul class="nav nav-tabs" id="presenceTab">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#presents">
                                    <i class="bi bi-check-circle"></i> Présents (<?= count($presents) ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#absents">
                                    <i class="bi bi-x-circle"></i> Absents (<?= count($allEtudiants) - count($presents) ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#all">
                                <i class="bi bi-people"></i> Tous les étudiants (<?= count($allEtudiants) ?>)
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <!-- Onglet des étudiants présents -->
                            <div class="tab-pane fade show active" id="presents">
                                <div class="table-responsive">
                                    <table class="table table-striped datatable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom & Prénom</th>
                                                <th>Heure d'arrivée</th>
                                                <th>Statut</th>
                                                <th>Méthode</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $i = 1;
                                            foreach ($presents as $present): 
                                                $heureArrivee = new DateTime($present['heure_arrivee']);
                                                $statutClass = '';
                                                switch ($present['statut']) {
                                                    case 'Présent': $statutClass = 'bg-success'; break;
                                                    case 'Retard': $statutClass = 'bg-warning'; break;
                                                    case 'Excusé': $statutClass = 'bg-info'; break;
                                                    default: $statutClass = 'bg-secondary';
                                                }
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= htmlspecialchars($present['matricule']) ?></td>
                                                <td><?= htmlspecialchars($present['noms']) ?></td>
                                                <td><?= $heureArrivee->format('H:i:s') ?></td>
                                                <td><span class="badge <?= $statutClass ?>"><?= $present['statut'] ?></span></td>
                                                <td>
                                                    <?php if ($present['methode_enregistrement'] == 'QR Code'): ?>
                                                        <span class="badge bg-primary"><i class="bi bi-qr-code"></i> QR Code</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="bi bi-pencil"></i> Manuel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button onclick="changeStatus(<?= $present['idpresence'] ?>, '<?= $present['statut'] ?>')" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button onclick="removePresence(<?= $present['idpresence'] ?>)" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Onglet des étudiants absents -->
                            <div class="tab-pane fade" id="absents">
                                <div class="table-responsive">
                                    <table class="table table-striped datatable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom & Prénom</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $i = 1;
                                            foreach ($allEtudiants as $etudiant): 
                                                // Ne montrer que les absents
                                                if (isset($etudiantsPresents[$etudiant['idetudiant']])) continue;
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                <td>
                                                    <button onclick="markPresent(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check-circle"></i> Marquer présent
                                                    </button>
                                                    <button onclick="markExcused(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-info">
                                                        <i class="bi bi-exclamation-circle"></i> Marquer excusé
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Onglet de tous les étudiants -->
                            <div class="tab-pane fade" id="all">
                                <div class="table-responsive">
                                    <table class="table table-striped datatable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom & Prénom</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $i = 1;
                                            foreach ($allEtudiants as $etudiant): 
                                                $estPresent = isset($etudiantsPresents[$etudiant['idetudiant']]);
                                                $statut = $estPresent ? $etudiantsPresents[$etudiant['idetudiant']]['statut'] : 'Absent';
                                                $statutClass = '';
                                                
                                                switch ($statut) {
                                                    case 'Présent': $statutClass = 'bg-success'; break;
                                                    case 'Retard': $statutClass = 'bg-warning'; break;
                                                    case 'Excusé': $statutClass = 'bg-info'; break;
                                                    case 'Absent': $statutClass = 'bg-danger'; break;
                                                    default: $statutClass = 'bg-secondary';
                                                }
                                            ?>
                                            <tr>
                                                <td><?= $i++ ?></td>
                                                <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                                <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                                <td><span class="badge <?= $statutClass ?>"><?= $statut ?></span></td>
                                                <td>
                                                    <?php if ($estPresent): ?>
                                                        <button onclick="changeStatus(<?= $etudiantsPresents[$etudiant['idetudiant']]['idpresence'] ?>, '<?= $statut ?>')" class="btn btn-sm btn-warning">
                                                            <i class="bi bi-pencil"></i> Modifier
                                                        </button>
                                                        <button onclick="removePresence(<?= $etudiantsPresents[$etudiant['idetudiant']]['idpresence'] ?>)" class="btn btn-sm btn-danger">
                                                            <i class="bi bi-trash"></i> Supprimer
                                                        </button>
                                                    <?php else: ?>
                                                        <button onclick="markPresent(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-success">
                                                            <i class="bi bi-check-circle"></i> Présent
                                                        </button>
                                                        <button onclick="markExcused(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-info">
                                                            <i class="bi bi-exclamation-circle"></i> Excusé
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>

<script>
    var idSeance = <?= $idSeance ?>;

    function ajaxPresence(data) {
        return $.post('controller/ajax_presence_cours.php', data);
    }

    function refreshPage() {
        // Recharger uniquement le contenu sans animation de page
        var activeTab = $('.nav-tabs .nav-link.active').attr('href');
        $.get(window.location.href, function(html) {
            var newDoc = $(html);
            $('.tab-content').html(newDoc.find('.tab-content').html());
            $('.alert-info').html(newDoc.find('.alert-info').html());
            // Réactiver l'onglet
            if (activeTab) {
                $('.nav-tabs .nav-link').removeClass('active');
                $('.nav-tabs .nav-link[href="' + activeTab + '"]').addClass('active');
                $('.tab-pane').removeClass('show active');
                $(activeTab).addClass('show active');
            }
            // Mettre à jour les compteurs des onglets
            var newTabs = newDoc.find('.nav-tabs');
            $('.nav-tabs').html(newTabs.html());
            $('.nav-tabs .nav-link').removeClass('active');
            $('.nav-tabs .nav-link[href="' + activeTab + '"]').addClass('active');
        });
    }

    function markPresent(idEtudiant) {
        Swal.fire({
            title: 'Marquer présent',
            text: "Confirmer la présence de cet étudiant ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler'
        }).then(function(result) {
            if (result.isConfirmed) {
                ajaxPresence({ action: 'mark', idSeance: idSeance, idEtudiant: idEtudiant, statut: 'Présent' })
                .done(function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Succès', text: res.message, timer: 1200, showConfirmButton: false });
                        refreshPage();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: res.message });
                    }
                });
            }
        });
    }

    function markExcused(idEtudiant) {
        Swal.fire({
            title: 'Marquer excusé',
            input: 'text',
            inputPlaceholder: 'Motif...',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler'
        }).then(function(result) {
            if (result.isConfirmed) {
                ajaxPresence({ action: 'mark', idSeance: idSeance, idEtudiant: idEtudiant, statut: 'Excusé', commentaire: result.value || '' })
                .done(function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Succès', text: res.message, timer: 1200, showConfirmButton: false });
                        refreshPage();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: res.message });
                    }
                });
            }
        });
    }

    function changeStatus(idPresence, currentStatus) {
        Swal.fire({
            title: 'Modifier le statut',
            html: '<select id="newStatus" class="form-select mb-2">' +
                  '<option value="Présent"' + (currentStatus === 'Présent' ? ' selected' : '') + '>Présent</option>' +
                  '<option value="Retard"' + (currentStatus === 'Retard' ? ' selected' : '') + '>Retard</option>' +
                  '<option value="Excusé"' + (currentStatus === 'Excusé' ? ' selected' : '') + '>Excusé</option>' +
                  '</select><textarea id="swalCommentaire" class="form-control" placeholder="Commentaire (optionnel)"></textarea>',
            showCancelButton: true,
            confirmButtonText: 'Modifier',
            cancelButtonText: 'Annuler',
            preConfirm: function() {
                return { status: document.getElementById('newStatus').value, comment: document.getElementById('swalCommentaire').value };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                ajaxPresence({ action: 'update', idPresence: idPresence, statut: result.value.status, commentaire: result.value.comment })
                .done(function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Succès', text: res.message, timer: 1200, showConfirmButton: false });
                        refreshPage();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: res.message });
                    }
                });
            }
        });
    }

    function removePresence(idPresence) {
        Swal.fire({
            title: 'Supprimer la présence',
            text: "Êtes-vous sûr ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler'
        }).then(function(result) {
            if (result.isConfirmed) {
                ajaxPresence({ action: 'delete', idPresence: idPresence })
                .done(function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Succès', text: res.message, timer: 1200, showConfirmButton: false });
                        refreshPage();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: res.message });
                    }
                });
            }
        });
    }
</script>