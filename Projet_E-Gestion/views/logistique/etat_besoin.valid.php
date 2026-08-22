<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$etatDeBesoins = $structure->getEtatDeBesoinsByUserAccess($userId, $searchQuery, 50); // Retrieve états de besoin with search and limit
$services = $structure->getServicesByUserAccess($userId); // Fetch available services
$budgetLines = $structure->getLignesDepenseByUser($userId); // Fetch budget lines accessible by the user

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>ÉTATS DE BESOIN</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">États de Besoin</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un état de besoin..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Validation des états de besoin</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Numéro</th>
                                    <th>Libellé</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Etat</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($etatDeBesoins as $etat) {
                                    $uniqueNumber = sprintf("EDB-%05d", $etat['idEtat_de_besoin']);
                                    $etat2 = $etat['validation1'] === null ? 'Encours' : 'Valid';
                                    $dateE = date('d-m-Y', strtotime($etat['dateElaboration']));
                                    $btnClass = $etat['validation1'] === null ? 'btn-outline-danger' : 'btn-outline-success';
                                
                                    echo "<tr>
                                        <td>{$i}</td>
                                        <td>{$uniqueNumber}</td>
                                        <td>{$etat['libelle']}</td>
                                        <td>{$etat['montant']}</td>
                                        <td>{$dateE}</td>
                                        <td><span class='btn {$btnClass}'>{$etat2} / {$etat['statut']}</span></td>
                                        <td>
                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseLignes{$etat['idEtat_de_besoin']}'>
                                                <i class='bi bi-list'></i> Lignes
                                            </button>
                                            <button class='btn btn-sm btn-success' onclick='confirmValidation({$etat['idEtat_de_besoin']},{$userId})'>
                                                <i class='bi bi-check-circle'></i> Valider
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='7'>
                                            <div class='collapse' id='collapseLignes{$etat['idEtat_de_besoin']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Désignation</th>
                                                            <th>Quantité</th>
                                                            <th>Prix Unitaire</th>
                                                            <th>Prix Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    $lignes = $structure->getLignesEtatBesoinByEtat($etat['idEtat_de_besoin']);
                                    foreach ($lignes as $ligne) {
                                        $pt = $ligne['quantite'] * $ligne['prixUnitaire'];
                                        echo "<tr>
                                                <td>{$ligne['designation']}</td>
                                                <td>{$ligne['quantite']}</td>
                                                <td>USD {$ligne['prixUnitaire']}</td>
                                                <td>USD {$pt}</td>
                                            </tr>";
                                    }
                                    echo "</tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>";
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>


<script>
function confirmValidation(etatId,userId) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Vous êtes sur le point de valider cet état de besoin!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, valider!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            validEtatBesoin(etatId,userId);
        }
    });
}

function validEtatBesoin(etatId,userId) {
    $.ajax({
        url: 'controller/validerEtatBesoin.php',
        type: 'POST',
        data: { etatId: etatId,userId: userId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire('Validé!', response.message, 'success').then(() => {
                    location.reload(); // Reload the page to reflect changes
                });
            } else {
                Swal.fire('Erreur!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Erreur!', 'Une erreur est survenue lors de la validation.', 'error');
        }
    });
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            window.location.href = 'logistique/etat_besoin.valid?search=' + encodeURIComponent(this.value.trim());
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>