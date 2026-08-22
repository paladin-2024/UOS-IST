<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'année académique en cours
$queryCurrentYear = "SELECT * FROM annee_acad ORDER BY idannee_acad DESC LIMIT 1";
$stmtCurrentYear = $pdo->prepare($queryCurrentYear);
$stmtCurrentYear->execute();
$currentYear = $stmtCurrentYear->fetch(PDO::FETCH_ASSOC);

// Paramètres de recherche et pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Requête pour récupérer les palmarès avec pagination
$query = "SELECT pa.*, 
          COUNT(pe.id_palmares_etudiant) as nb_etudiants,
          u.\"nomUser\" as nom_utilisateur
          FROM palmares_archive pa
          LEFT JOIN palmares_etudiant pe ON pa.id_palmares = pe.id_palmares
          LEFT JOIN t_users u ON pa.\"idUser\" = u.\"idUser\"
          WHERE (pa.designation LIKE :search 
                 OR pa.promotion LIKE :search 
                 OR pa.annee_academique LIKE :search)
          GROUP BY pa.id_palmares
          ORDER BY pa.date_creation DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
$searchParam = '%' . $search . '%';
$stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$palmares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total de palmarès pour la pagination
$queryCount = "SELECT COUNT(DISTINCT id_palmares) as total FROM palmares_archive 
               WHERE designation LIKE :search 
               OR promotion LIKE :search 
               OR annee_academique LIKE :search";
$stmtCount = $pdo->prepare($queryCount);
$stmtCount->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmtCount->execute();
$totalPalmares = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalPalmares / $limit);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES PALMARÈS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?">Accueil</a></li>
                <li class="breadcrumb-item">Académique</li>
                <li class="breadcrumb-item active">Palmarès</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Liste des palmarès
                            <span>
                                | <a href="?view=academique/ajouter_palmares" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Ajouter un palmarès
                                </a>
                                | <a href="?view=academique/importer_palmares" class="btnPage">
                                    <i class="bi bi-upload"></i> Importer palmarès
                                </a>
                            </span>
                        </h5>

                        <!-- Barre de recherche -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="academique/palmares">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un palmarès...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <!-- Tableau des palmarès -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Désignation</th>
                                        <th scope="col">Promotion</th>
                                        <th scope="col">Session</th>
                                        <th scope="col">Année académique</th>
                                        <th scope="col">Nb étudiants</th>
                                        <th scope="col">Date création</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = ($page - 1) * $limit + 1;
                                    if (!empty($palmares)) {
                                        foreach ($palmares as $p) {
                                            echo "<tr>
                                                <td>{$counter}</td>
                                                <td>" . htmlspecialchars($p['designation']) . "</td>
                                                <td>" . htmlspecialchars($p['promotion']) . "</td>
                                                <td>" . htmlspecialchars($p['session']) . "</td>
                                                <td>" . htmlspecialchars($p['annee_academique']) . "</td>
                                                <td>" . $p['nb_etudiants'] . "</td>
                                                <td>" . date('d/m/Y H:i', strtotime($p['date_creation'])) . "</td>
                                                <td>
                                                    <button class='btn btn-sm btn-info' onclick='window.location.href=\"?view=academique/details_palmares&id={$p['id_palmares']}\"'>
                                                        <i class='bi bi-eye'></i> Voir
                                                    </button>
                                                    <button class='btn btn-sm btn-primary' onclick='window.location.href=\"?view=academique/imprimer_palmares&id={$p['id_palmares']}\"'>
                                                        <i class='bi bi-printer'></i> Imprimer
                                                    </button>
                                                    <button class='btn btn-sm btn-warning' onclick='window.location.href=\"?view=academique/modifier_palmares&id={$p['id_palmares']}\"'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmerSuppression({$p['id_palmares']})'>
                                                        <i class='bi bi-trash'></i> Supprimer
                                                    </button>
                                                </td>
                                            </tr>";
                                            $counter++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center'>Aucun palmarès trouvé</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-center mt-3">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?view=academique/palmares&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>" aria-label="Précédent">
                                            <span aria-hidden="true">«</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?view=academique/palmares&search=<?= urlencode($search) ?>&page=<?= $i ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?view=academique/palmares&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>" aria-label="Suivant">
                                            <span aria-hidden="true">»</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Fonction pour confirmer la suppression d'un palmarès
function confirmerSuppression(idPalmares) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera définitivement ce palmarès et toutes les données associées!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/delete_palmares.php?id=${idPalmares}`;
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>