<?php
include "./views/include/header.php";

$idRole = isset($_GET['r']) ? intval($_GET['r']) : 0;

if ($idRole == 0) {
    echo "<script>window.history.back();</script>";
    exit;
}

$userModel = new User();
$userData = $userModel->getRolesById($idRole);
$nomRole = $userData['nomRole'];

$module=new Module();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= mb_strtoupper($nomRole) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Permissions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashbord -->
    <section class="section dashboard">
        <div class="row">
            <!-- TAbele data -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table service -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Attribuer les permissions à un rôle
                                    <span>
                                        | <a href="index.php?view=configuration/roles" class="btnPageReturn"><i class="bi bi-arrow-return-left"></i> Retour</a>
                                    </span>
                                </h5>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    </div>
                                </div>
                                <form method="POST" action="controller/savePermission_user.php" class="tab-pane ladda-form">
                                <table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>N°</th>
            <th>Permission</th>
            <th>Code</th>
            <th>Description</th>
            <th>Cocher</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $listePerm = $module->getPermissionsByRole($idRole);
        $i = 1;
        $currentModule = ''; // Variable pour suivre le module actuel
        
        // Démarre une seule fois le tbody
        while ($l = $listePerm->fetch()) {
            $idPerm = $l['idPerm'];
            $module = $l['nomMod']; // Nom du module
            $permission = $l['package'] . '/' . $l['nomPerm'];
            $code = $l['nomPerm']; // Code lié à la permission
            $description = $l['descPerm']; // Description de la permission
            $checked = $l['is_checked'] == 1 ? 'checked' : ''; // Vérifie si la case doit être cochée

            // Si on change de module, afficher un nouveau séparateur avec un bouton déroulant et une case à cocher pour tout sélectionner
            if ($module !== $currentModule) {
                // Si ce n'est pas le premier module, on ferme le tbody précédent
                if ($currentModule !== '') {
                    echo "</tbody>";
                }

                // Met à jour le module actuel
                $currentModule = $module;
                
                // Crée un identifiant slug sans espaces pour les attributs HTML
                $moduleSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $module);

                // En-tête du nouveau module avec un bouton pour dérouler/masquer et une case pour tout cocher
                echo "
                    <tr class='table-primary'>
                        <td colspan='5'>
                            <button class='btn btn-link text-decoration-none' type='button' data-bs-toggle='collapse' data-bs-target='#module-{$moduleSlug}' aria-expanded='true'>
                                <i class='bi bi-chevron-down me-2'></i> <!-- Icône pour dérouler/masquer -->
                                <strong>{$module}</strong>
                            </button>
                            <input type='checkbox' class='form-check-input ms-3' id='checkAll-{$moduleSlug}' onclick='toggleModulePermissions(\"module-{$moduleSlug}\", this)'>
                            <label for='checkAll-{$moduleSlug}' class='form-check-label ms-1'>Tout cocher</label>
                        </td>
                    </tr>
                    <tbody id='module-{$moduleSlug}' class='collapse show'>
                ";
            }
            
            // Crée un identifiant slug pour la classe des checkboxes
            $moduleSlugForClass = preg_replace('/[^a-zA-Z0-9]/', '_', $module);

            // Afficher les permissions du module
            echo "
                <tr>
                    <td>{$i}</td>
                    <td>{$permission}</td>
                    <td>{$code}</td>
                    <td>{$description}</td>
                    <td>
                        <input type='hidden' name='idRole' value='{$idRole}'>
                        <input type='checkbox' class='module-{$moduleSlugForClass}' name='permissions[]' value='{$idPerm}' {$checked}>
                    </td>
                </tr>
            ";
            $i++;
        }

        // Fermer le dernier tableau ouvert
        if ($currentModule !== '') {
            echo "</tbody>";
        }
        ?>
    </tbody>
</table>


<script>
    // Fonction pour cocher ou décocher toutes les cases d'un module
    function toggleModulePermissions(moduleClass, masterCheckbox) {
        const checkboxes = document.querySelectorAll(`.${moduleClass}`);
        checkboxes.forEach(checkbox => {
            checkbox.checked = masterCheckbox.checked;
        });
    }
</script>




                                    <div class="modal-footer">
                                        <button type="submit" name="save_permissions" class="btnModSave ladda-button" data-style="zoom-out">
                                            <div class="ladda-label">Enregistrer les permissions</div>
                                        </button>
                                    </div>
                                </form>

                                <div id="loading" class="text-center d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </div>

                                

                            </div>

                        </div>
                    </div><!-- End Recent messages -->

                </div>
            </div><!-- End table data -->
        </div>
    </section>

</main><!-- End #main -->

<script>
        document.addEventListener('DOMContentLoaded', function() {
        // Déclarez une variable JavaScript qui récupère la valeur PHP
        const roleId = <?php echo $idRole; ?>;

        // Initialiser le loader pour les userpermissions
        const permLoader = new DataLoader({
            tableBodyId: 'UserPermTableBody',
            loadingIndicatorId: 'loading',
            searchInputId: 'searchInput',
            loadMoreButtonId: 'loadMoreButton',
            endpoint: `${APP_CONFIG.baseUrl}${APP_CONFIG.apiEndpoint}?type=userpermissions&r=${roleId}`,
            columns: [{
                    field: 'index',
                    render: (value) => value
                },
                {
                    field: 'nomMod'
                },
                {
                    field: 'package',
                    render: (value, item) => `${item.package}/${item.nomPerm}`
                },
                {
                    field: 'codePerm'
                },
                {
                    field: 'descPerm'
                },
            ],
            actions: [{
                render: (item) => `
                           <input type="hidden" name="idRole" value="${roleId}">
                            <input type="checkbox" name="permissions[]" value="${item.idPerm}" 
                            ${item.is_checked ? 'checked' : ''}>
                        `
            }],
            dataKey: 'userpermission',
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>