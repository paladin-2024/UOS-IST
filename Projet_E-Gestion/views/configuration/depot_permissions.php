<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Vérification des autorisations
if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.'
        }).then(() => {
            window.location.href = 'dashboard';
        });
    </script>";
    exit;
}

// Récupération des utilisateurs
$queryUsers = "SELECT \"idUser\", \"nomUser\", \"loginUser\" FROM t_users ORDER BY \"nomUser\"";
$stmtUsers = $db->prepare($queryUsers);
$stmtUsers->execute();
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Récupération des dépôts
$queryDepots = "SELECT id_depot, code_depot, libelle_depot FROM depot WHERE actif = 1 ORDER BY libelle_depot";
$stmtDepots = $db->prepare($queryDepots);
$stmtDepots->execute();
$depots = $stmtDepots->fetchAll(PDO::FETCH_ASSOC);

// Si un utilisateur est sélectionné, récupérer ses autorisations
$selectedUser = isset($_GET['user']) ? intval($_GET['user']) : 0;
$userPermissions = [];

if ($selectedUser > 0) {
    $queryPermissions = "SELECT id_depot, peut_consulter, peut_modifier, peut_valider 
                       FROM autorisation_depot 
                       WHERE id_user = :user_id";
    $stmtPermissions = $db->prepare($queryPermissions);
    $stmtPermissions->bindParam(':user_id', $selectedUser, PDO::PARAM_INT);
    $stmtPermissions->execute();
    
    while ($permission = $stmtPermissions->fetch(PDO::FETCH_ASSOC)) {
        $userPermissions[$permission['id_depot']] = $permission;
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES AUTORISATIONS DE DÉPÔTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Configuration</li>
                <li class="breadcrumb-item active">Autorisations Dépôts</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Sélectionner un utilisateur</h5>
                        
                        <form method="get" action="">
                            <input type="hidden" name="p" value="configuration/depot_permissions">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <select name="user" class="form-select" onchange="this.form.submit()">
                                        <option value="">Sélectionner un utilisateur</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['idUser'] ?>" <?= $selectedUser == $user['idUser'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['nomUser']) ?> (<?= $user['loginUser'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($selectedUser > 0): ?>
                            <h5 class="card-title">Autorisations des dépôts</h5>
                            <form action="controller/manage_depot_permissions.php" method="post">
                                <input type="hidden" name="id_user" value="<?= $selectedUser ?>">
                                
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Dépôt</th>
                                            <th>Consulter</th>
                                            <th>Modifier</th>
                                            <th>Valider</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($depots as $depot): 
                                            $hasPermission = isset($userPermissions[$depot['id_depot']]);
                                            $peutConsulter = $hasPermission ? $userPermissions[$depot['id_depot']]['peut_consulter'] : 0;
                                            $peutModifier = $hasPermission ? $userPermissions[$depot['id_depot']]['peut_modifier'] : 0;
                                            $peutValider = $hasPermission ? $userPermissions[$depot['id_depot']]['peut_valider'] : 0;
                                        ?>
                                                                                        <tr>
                                                <td><?= $depot['code_depot'] ?></td>
                                                <td><?= htmlspecialchars($depot['libelle_depot']) ?></td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="permissions[<?= $depot['id_depot'] ?>][peut_consulter]" 
                                                               value="1" <?= $peutConsulter ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="permissions[<?= $depot['id_depot'] ?>][peut_modifier]" 
                                                               value="1" <?= $peutModifier ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="permissions[<?= $depot['id_depot'] ?>][peut_valider]" 
                                                               value="1" <?= $peutValider ? 'checked' : '' ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary save-permission" 
                                                            data-depot="<?= $depot['id_depot'] ?>">
                                                        <i class="bi bi-save"></i> Enregistrer
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Enregistrer toutes les modifications
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
$(document).ready(function() {
    // Gestion de l'enregistrement individuel par dépôt
    $('.save-permission').on('click', function() {
        var depotId = $(this).data('depot');
        var row = $(this).closest('tr');
        var peutConsulter = row.find('input[name="permissions['+depotId+'][peut_consulter]"]').is(':checked') ? 1 : 0;
        var peutModifier = row.find('input[name="permissions['+depotId+'][peut_modifier]"]').is(':checked') ? 1 : 0;
        var peutValider = row.find('input[name="permissions['+depotId+'][peut_valider]"]').is(':checked') ? 1 : 0;
        var userId = <?= $selectedUser ?>;
        
        $.ajax({
            url: 'controller/ajax_depot_permission.php',
            type: 'POST',
            data: {
                id_user: userId,
                id_depot: depotId,
                peut_consulter: peutConsulter,
                peut_modifier: peutModifier,
                peut_valider: peutValider
            },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    Swal.fire({
                        title: 'Succès',
                        text: 'Les autorisations ont été mises à jour.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Erreur',
                        text: result.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>

