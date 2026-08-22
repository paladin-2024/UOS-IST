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
$querySeance = "SELECT s.*, e.designationECUE, p.designationPromotion, p.idpromotion, sem.numeroSemestre
                FROM seance_cours s
                JOIN ecue e ON s.idECUE = e.idECUE
                JOIN ue ON e.UE_idUE = ue.idUE
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

// Récupérer tous les étudiants de la promotion
$queryEtudiants = "SELECT e.idetudiant, e.matricule, e.noms
                   FROM etudiant e
                   WHERE e.promotion_idpromotion = :idPromotion
                   ORDER BY e.noms";

$stmtEtudiants = $db->prepare($queryEtudiants);
$stmtEtudiants->bindParam(':idPromotion', $seance['idpromotion']);
$stmtEtudiants->execute();
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Encoder une présence</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item"><a href="cours/seances.list">Séances de cours</a></li>
                <li class="breadcrumb-item"><a href="cours/presence.list&id=<?= $idSeance ?>">Présences</a></li>
                <li class="breadcrumb-item active">Encoder</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Encoder manuellement une présence</h5>

                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Cours:</strong> <?= htmlspecialchars($seance['designationECUE']) ?><br>
                                    <strong>Promotion:</strong> <?= htmlspecialchars($seance['designationPromotion']) ?> - Semestre <?= $seance['numeroSemestre'] ?><br>
                                    <strong>Date:</strong> <?= (new DateTime($seance['date_seance']))->format('d/m/Y') ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Horaire:</strong> <?= (new DateTime($seance['heure_debut']))->format('H:i') ?> - <?= (new DateTime($seance['heure_fin']))->format('H:i') ?><br>
                                    <strong>Salle:</strong> <?= htmlspecialchars($seance['salle']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4 mt-4">
                            <div class="col-md-8 mx-auto">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-search"></i> Recherche par matricule
                                        </h5>
                                        <div class="input-group mb-3">
                                            <input type="text" id="searchMatricule" class="form-control" placeholder="Entrez le matricule de l'étudiant">
                                            <button class="btn btn-primary" type="button" id="btnSearchMatricule">
                                                <i class="bi bi-search"></i> Rechercher
                                            </button>
                                        </div>
                                        <div id="searchResult" class="mt-3" style="display: none;">
                                            <div class="alert alert-info">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <strong>Matricule:</strong> <span id="resultMatricule"></span><br>
                                                        <strong>Nom:</strong> <span id="resultNom"></span>
                                                    </div>
                                                    <div>
                                                        <button id="btnMarkPresent" class="btn btn-success">
                                                            <i class="bi bi-check-circle"></i> Marquer présent
                                                        </button>
                                                        <button id="btnMarkExcused" class="btn btn-info">
                                                            <i class="bi bi-exclamation-circle"></i> Marquer excusé
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="errorResult" class="mt-3" style="display: none;">
                                            <div class="alert alert-danger">
                                                <i class="bi bi-exclamation-triangle"></i> 
                                                Étudiant non trouvé ou n'appartient pas à cette promotion.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="card-title">
                            <i class="bi bi-list-check"></i> Liste des étudiants
                        </h5>

                        <div class="table-responsive">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Matricule</th>
                                        <th scope="col">Nom & Prénom</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($etudiants as $etudiant): 
                                    ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($etudiant['matricule']) ?></td>
                                        <td><?= htmlspecialchars($etudiant['noms']) ?></td>
                                        <td>
                                            <button onclick="markPresent(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Présent
                                            </button>
                                            <button onclick="markExcused(<?= $etudiant['idetudiant'] ?>)" class="btn btn-sm btn-info">
                                                <i class="bi bi-exclamation-circle"></i> Excusé
                                            </button>
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
    </section>
</main>

<script>
    $(document).ready(function() {
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
            },
            "pageLength": 25
        });

        // Recherche par matricule
        $('#btnSearchMatricule').click(function() {
            searchStudent();
        });

        $('#searchMatricule').keypress(function(e) {
            if (e.which === 13) {
                searchStudent();
            }
        });

        function searchStudent() {
            const matricule = $('#searchMatricule').val().trim();
            if (!matricule) return;

            // Rechercher l'étudiant dans le tableau
            let found = false;
            let studentId = 0;
            let studentName = '';

            <?php foreach ($etudiants as $etudiant): ?>
                if ('<?= $etudiant['matricule'] ?>'.toLowerCase() === matricule.toLowerCase()) {
                    found = true;
                    studentId = <?= $etudiant['idetudiant'] ?>;
                    studentName = '<?= addslashes($etudiant['noms']) ?>';
                }
            <?php endforeach; ?>

            if (found) {
                $('#resultMatricule').text(matricule);
                $('#resultNom').text(studentName);
                $('#searchResult').show();
                $('#errorResult').hide();

                // Configurer les boutons
                $('#btnMarkPresent').off('click').click(function() {
                    markPresent(studentId);
                });

                $('#btnMarkExcused').off('click').click(function() {
                    markExcused(studentId);
                });
            } else {
                $('#searchResult').hide();
                $('#errorResult').show();
            }
        }
    });

    function markPresent(idEtudiant) {
        Swal.fire({
            title: 'Marquer présent',
            text: "Confirmer la présence de cet étudiant ?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/mark_presence.php?idSeance=<?= $idSeance ?>&idEtudiant=${idEtudiant}&statut=Présent&methode=Manuel`;
            }
        });
    }

    function markExcused(idEtudiant) {
        Swal.fire({
            title: 'Marquer excusé',
            text: "Motif de l'excuse",
            input: 'text',
            inputPlaceholder: 'Entrez le motif...',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `controller/mark_presence.php?idSeance=<?= $idSeance ?>&idEtudiant=${idEtudiant}&statut=Excusé&commentaire=${encodeURIComponent(result.value)}&methode=Manuel`;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
