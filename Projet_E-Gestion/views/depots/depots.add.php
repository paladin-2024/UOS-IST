<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Vérification des autorisations de l'utilisateur
$userId = $_SESSION['id'];
$userRole = $_SESSION['idRole']; // Supposant que le rôle de l'utilisateur est stocké en session

// Les administrateurs (rôle 1 par exemple) ont accès à tout
$isAdmin = ($userRole == 1); // Ajustez selon votre logique de rôles

if (!$isAdmin) {
    // Vérifier si l'utilisateur a l'autorisation d'ajouter des dépôts
    $stmt = $db->prepare("SELECT COUNT(*) FROM autorisation_depot 
                         WHERE id_user = :user_id AND peut_modifier = 1");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'avez pas l\'autorisation d\'ajouter des dépôts.'
            }).then(() => {
                window.location.href = 'depots/depots.list';
            });
        </script>";
        exit;
    }
}
?>

<!-- Le reste du code existant -->

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AJOUTER UN DÉPÔT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item"><a href="depots/depots.list">Dépôts</a></li>
                <li class="breadcrumb-item active">Ajouter</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations du dépôt</h5>

                        <form action="controller/create_depot.php" method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label for="code_depot" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code_depot" name="code_depot" required>
                            </div>

                            <div class="col-md-6">
                                <label for="libelle_depot" class="form-label">Libellé <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="libelle_depot" name="libelle_depot" required>
                            </div>

                            <div class="col-md-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="responsable" class="form-label">Responsable</label>
                                <input type="text" class="form-control" id="responsable" name="responsable">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1" checked>
                                    <label class="form-check-label" for="actif">
                                        Dépôt actif
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                                <a href="depots/depots.list" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
