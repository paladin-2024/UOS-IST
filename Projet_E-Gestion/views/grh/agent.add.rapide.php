<?php
include "./views/include/header.php";

// Utiliser la classe Connexion pour les requêtes directes
$connexion = Connexion::getInstance()->getPDO();

// Récupérer les rôles directement
$rolesQuery = "SELECT * FROM t_roles ORDER BY \"nomRole\" ASC";
$rolesStmt = $connexion->prepare($rolesQuery);
$rolesStmt->execute();
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les structures avec permissions
$structuresQuery = "SELECT s.* FROM structure s 
                   INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\" 
                   WHERE us.\"idUser\" = :userId";
$structuresStmt = $connexion->prepare($structuresQuery);
$structuresStmt->bindParam(':userId', $_SESSION['id']);
$structuresStmt->execute();
$structures = $structuresStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les grades
$gradesQuery = "SELECT * FROM grade ORDER BY designation ASC";
$gradesStmt = $connexion->prepare($gradesQuery);
$gradesStmt->execute();
$grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filterAccess = isset($_GET['filter_access']) ? $_GET['filter_access'] : '';

// Requête pour récupérer les agents avec informations d'accès utilisateur
$sql = "SELECT a.*,
s.designation as designationStructure,
srv.designation as designationService,
g.designation as gradeDesignation,
u.\"idUser\",
u.\"loginUser\",
u.\"etatUser\",
STRING_AGG(DISTINCT r.\"nomRole\", ', ' ORDER BY r.\"nomRole\") AS \"allRoles\",
CASE
WHEN u.\"idUser\" IS NOT NULL THEN 'Oui'
ELSE 'Non'
END as \"hasAccess\",
CASE
WHEN u.\"etatUser\" = 1 THEN 'Actif'
WHEN u.\"etatUser\" = 0 THEN 'Inactif'
ELSE 'Aucun'
END as \"statutUser\"
FROM agent a
LEFT JOIN structure s ON a.\"idStructure\" = s.\"idStructure\"
LEFT JOIN service srv ON a.\"idService\" = srv.\"idService\"
LEFT JOIN grade g ON a.grade_id = g.idgrade
LEFT JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\"
LEFT JOIN t_user_roles ur ON u.\"idUser\" = ur.\"idUser\"
LEFT JOIN t_roles r ON ur.\"idRole\" = r.\"idRole\"
INNER JOIN user_structure us ON a.\"idStructure\" = us.\"idStructure\"
WHERE us.\"idUser\" = :userId
         GROUP BY a.\"idAgent\", s.designation, srv.designation, g.designation, u.\"idUser\", u.\"loginUser\", u.\"etatUser\"";

$params = [':userId' => $_SESSION['id']];

// Ajouter le filtre de recherche
if (!empty($search)) {
    $sql .= " AND a.noms LIKE :search";
    $params[':search'] = "%$search%";
}

// Ajouter le filtre d'accès
if ($filterAccess === 'avec_acces') {
    $sql .= " AND u.\"idUser\" IS NOT NULL";
} elseif ($filterAccess === 'sans_acces') {
    $sql .= " AND u.\"idUser\" IS NULL";
}

$sql .= " ORDER BY a.noms ASC";

$stmt = $connexion->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$listeAgent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les statistiques
$totalAgents = count($listeAgent);
$agentsAvecAcces = 0;
$agentsActifs = 0;

foreach ($listeAgent as $agent_item) {
    if ($agent_item['hasAccess'] === 'Oui') {
        $agentsAvecAcces++;
        if ($agent_item['etatUser'] == 1) $agentsActifs++;
    }
}
$agentsSansAcces = $totalAgents - $agentsAvecAcces;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Agents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Agents</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                        <h3 class="mb-1"><?= $totalAgents ?></h3>
                        <p class="text-muted mb-0">Total Agents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="bi bi-person-check fs-1"></i>
                        </div>
                        <h3 class="mb-1"><?= $agentsAvecAcces ?></h3>
                        <p class="text-muted mb-0">Avec Accès</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="bi bi-person-check-fill fs-1"></i>
                        </div>
                        <h3 class="mb-1"><?= $agentsActifs ?></h3>
                        <p class="text-muted mb-0">Actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="bi bi-person-x fs-1"></i>
                        </div>
                        <h3 class="mb-1"><?= $agentsSansAcces ?></h3>
                        <p class="text-muted mb-0">Sans Accès</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des agents -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Liste des Agents</h5>
                    <div>
                        <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#createAgentModal">
                            <i class="bi bi-plus-circle"></i> Ajouter
                        </button>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#importAgentModal">
                            <i class="bi bi-file-earmark-excel"></i> Importer
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtres -->
                <form method="GET" action="" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="hidden" name="view" value="grh/agent.add.rapide">
                            <div class="input-group">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                    class="form-control" placeholder="Rechercher par nom...">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="filter_access" class="form-select" onchange="this.form.submit()">
                                <option value="">Tous les agents</option>
                                <option value="avec_acces" <?= $filterAccess === 'avec_acces' ? 'selected' : '' ?>>
                                    Avec accès (<?= $agentsAvecAcces ?>)
                                </option>
                                <option value="sans_acces" <?= $filterAccess === 'sans_acces' ? 'selected' : '' ?>>
                                    Sans accès (<?= $agentsSansAcces ?>)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <?php if (!empty($search) || !empty($filterAccess)): ?>
                                <a href="?view=grh/agent.add.rapide" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Tableau -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Agent</th>
                                <th>Matricule</th>
                                <th>Type/Grade</th>
                                <th>Contact</th>
                                <th>Campus/Service</th>
                                <th>Statut Accès</th>
                                <th>Login/Rôle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            // Remplacez la section du tableau (lignes 196-260 environ) par ce code corrigé

                            foreach ($listeAgent as $l) {
                                $accessBadge = '';
                                $rowClass = '';

                                if ($l['hasAccess'] === 'Oui') {
                                    if ($l['etatUser'] == 1) {
                                        $accessBadge = '<span class="badge bg-success">Actif</span>';
                                        $rowClass = 'table-success-light';
                                    } else {
                                        $accessBadge = '<span class="badge bg-warning">Inactif</span>';
                                        $rowClass = 'table-warning-light';
                                    }
                                } else {
                                    $accessBadge = '<span class="badge bg-secondary">Aucun</span>';
                                }

                                // Échapper correctement toutes les valeurs pour JavaScript
                                $js_id = $l['idAgent'];
                                $js_noms = htmlspecialchars(json_encode($l['noms']), ENT_QUOTES, 'UTF-8');
                                $js_matricule = htmlspecialchars(json_encode($l['matricule'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $js_type_agent = htmlspecialchars(json_encode($l['type_agent'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $js_grade_id = htmlspecialchars(json_encode($l['grade_id'] ?? null), ENT_QUOTES, 'UTF-8');
                                $js_telephone = htmlspecialchars(json_encode($l['telephone'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $js_email = htmlspecialchars(json_encode($l['email'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $js_idStructure = htmlspecialchars(json_encode($l['idStructure']), ENT_QUOTES, 'UTF-8');
                                $js_idService = htmlspecialchars(json_encode($l['idService'] ?? null), ENT_QUOTES, 'UTF-8');
                                $js_idUser = htmlspecialchars(json_encode($l['idUser'] ?? null), ENT_QUOTES, 'UTF-8');
                                $js_loginUser = htmlspecialchars(json_encode($l['loginUser'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $js_etatUser = htmlspecialchars(json_encode($l['etatUser'] ?? 0), ENT_QUOTES, 'UTF-8');
                                $js_allRoles = htmlspecialchars(json_encode($l['allRoles'] ?? ''), ENT_QUOTES, 'UTF-8');

                                echo "<tr class='{$rowClass}'>
        <td>{$i}</td>
        <td>
            <div class='fw-bold'>{$l['noms']}</div>
            " . (!empty($l['email']) ? "<small class='text-muted'>{$l['email']}</small>" : "") . "
        </td>
        <td>" . ($l['matricule'] ?: '<span class="text-muted">-</span>') . "</td>
        <td>
            <div>" . ($l['type_agent'] ?: '<span class="text-muted">-</span>') . "</div>
            <small class='text-muted'>" . ($l['gradeDesignation'] ?: '-') . "</small>
        </td>
        <td>" . ($l['telephone'] ?: '<span class="text-muted">-</span>') . "</td>
        <td>
            <div>" . $l['designationStructure'] . "</div>
            " . ($l['designationService'] ? "<small class='text-muted'>{$l['designationService']}</small>" : "") . "
        </td>
        <td>{$accessBadge}</td>
        <td>";

                                if ($l['hasAccess'] === 'Oui') {
                                    echo "<div class='fw-bold'>{$l['loginUser']}</div>";
                                    echo "<small class='text-muted'>" . ($l['allRoles'] ?? 'Aucun rôle') . "</small>";
                                } else {
                                    echo '<span class="text-muted">Aucun accès</span>';
                                }

                                echo "</td>
        <td>
            <div class='btn-group btn-group-sm'>
                <button class='btn btn-outline-primary' 
                        onclick=\"editAgent({$js_id}, {$js_noms}, {$js_matricule}, {$js_type_agent}, {$js_grade_id}, {$js_telephone}, {$js_email}, {$js_idStructure}, {$js_idService})\" 
                        title='Modifier'>
                    <i class='bi bi-pencil'></i>
                </button>";

                                if ($l['hasAccess'] === 'Oui') {
                                    echo "<button class='btn btn-outline-info' 
                      onclick=\"manageUserAccess({$js_id}, {$js_idUser}, {$js_noms}, {$js_loginUser}, {$js_etatUser}, {$js_allRoles})\"
                      title=\"Gérer l'accès\">
                <i class='bi bi-gear'></i>
              </button>";
                                } else {
                                    echo "<button class='btn btn-outline-success' 
                      onclick=\"assignUserAccess({$js_id}, {$js_noms})\" 
                      title='Attribuer un accès'>
                <i class='bi bi-person-plus'></i>
              </button>";
                                }

                                echo "<button class='btn btn-outline-danger' 
                  onclick=\"confirmDelete({$js_id})\" 
                  title='Supprimer'>
            <i class='bi bi-trash'></i>
          </button>
        </div>
    </td>
</tr>";
                                $i++;
                            }

                            if ($i === 1) {
                                echo "<tr><td colspan='9' class='text-center text-muted py-4'>
        <i class='bi bi-inbox fs-1'></i><br>
        Aucun agent trouvé
    </td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">
                        Affichage de <?= $i - 1 ?> agent(s)
                        <?php if (!empty($search)): ?>
                            pour "<strong><?= htmlspecialchars($search) ?></strong>"
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal Ajouter Agent -->
<div class="modal fade" id="createAgentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="controller/create_agent.php" class="needs-validation" novalidate id="createAgentForm">
                <div class="modal-body">
                    <!-- Alerte d'enregistrement rapide -->
                    <div class="alert alert-info" id="rapideModeAlert" style="display: none;">
                        <i class="bi bi-info-circle"></i> <strong>Mode rapide:</strong> Les informations supplémentaires pourront être ajoutées ultérieurement.
                    </div>

                    <!-- Champ caché pour dateNaissance (auto-rempli en mode rapide) -->
                    <input type="hidden" name=dateNaissance id=dateNaissance value="">
                    <input type="hidden" name="isRapideMode" id="isRapideMode" value="1">

                    <div class="row g-3">
                        <!-- Champs obligatoires pour le mode rapide -->
                        <div class="col-md-6">
                            <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" name="noms" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select name="sexe" class="form-select" required>
                                <option value="">Sélectionner</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Type d'agent <span class="text-danger">*</span></label>
                            <select name="type_agent" id="type_agent" class="form-select" required onchange="updateGradeOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Recherche">Recherche</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Campus <span class="text-danger">*</span></label>
                            <select name=idStructure id=idStructure class="form-select" required onchange="updateServiceOptions()">
                                <option value="">Sélectionner</option>
                                <?php foreach ($structures as $s): ?>
                                    <option value="<?= $s['idStructure'] ?>"><?= $s['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Champs optionnels (cachés par défaut en mode rapide) -->
                        <div class="col-md-6" id="matriculeField">
                            <label class="form-label">Matricule</label>
                            <input type="text" name="matricule" class="form-control">
                        </div>

                        <div class="col-md-6" id="gradeField">
                            <label class="form-label">Grade</label>
                            <select name="grade_id" id="grade_id" class="form-select">
                                <option value="">Sélectionner</option>
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?= $g['idgrade'] ?>" data-type="<?= $g['type_agent'] ?>">
                                        <?= $g['designation'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6" id="telephoneField">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control">
                        </div>

                        <div class="col-md-6" id="serviceField">
                            <label class="form-label">Service</label>
                            <select name=idService id=idService class="form-select">
                                <option value="">Sélectionner</option>
                            </select>
                        </div>

                        <div class="col-12" id="emailField">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="addAgentBtn" class="btn btn-success" id="btnRapideSave">
                        <i class="bi bi-lightning-fill"></i> Enregistrement Rapide
                    </button>
                    <button type="button" class="btn btn-primary" id="btnMoreFields">
                        <i class="bi bi-plus"></i> Ajouter plus de champs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Agent -->
<div class="modal fade" id="editAgentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="controller/edit_agent.php" class="needs-validation" novalidate>
                <input type="hidden" name=idAgent id="editAgentId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom complet</label>
                            <input type="text" name="noms" id="editAgentNoms" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Matricule</label>
                            <input type="text" name="matricule" id="editAgentMatricule" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type d'agent</label>
                            <select name="type_agent" id="editAgentType" class="form-select" onchange="updateEditGradeOptions()">
                                <option value="">Sélectionner</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Recherche">Recherche</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Grade</label>
                            <select name="grade_id" id="editAgentGrade" class="form-select">
                                <option value="">Sélectionner</option>
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?= $g['idgrade'] ?>" data-type="<?= $g['type_agent'] ?>">
                                        <?= $g['designation'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" id="editTelephone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus</label>
                            <select name=idStructure id="editAgentStructure" class="form-select" onchange="updateEditServiceOptions()">
                                <?php foreach ($structures as $s): ?>
                                    <option value="<?= $s['idStructure'] ?>"><?= $s['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service</label>
                            <select name=idService id="editAgentService" class="form-select">
                                <option value="">Sélectionner</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="editAgentBtn" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gérer Accès -->
<div class="modal fade" id="manageUserAccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gérer l'Accès Utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="controller/manage_user_access.php">
                <input type="hidden" name=idAgent id="manageUserIdAgent">
                <input type="hidden" name=idUser id="manageUserIdUser">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong id="manageUserAgentName"></strong> possède déjà un accès.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Identifiant</label>
                        <input type="text" name=loginUser id="manageLoginUser" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôles</label>
                        <div id="manageUserRoles" class="border p-3 rounded">
                            <div class="d-flex align-items-center mb-3 border-bottom pb-2">
                                <span class="flex-grow-1 fw-bold">Rôles à assigner</span>
                                <span class="ms-3 fw-bold">Principal</span>
                            </div>
                            <?php foreach ($roles as $role): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <input class="form-check-input me-3" type="checkbox" name="roles[]" value="<?= $role['idRole'] ?>" id="role_<?= $role['idRole'] ?>">
                                    <span class="flex-grow-1 fw-bold"><?= $role['nomRole'] ?></span>
                                    <input class="form-check-input ms-3" type="radio" name="principalRole" value="<?= $role['idRole'] ?>" id="principal_<?= $role['idRole'] ?>" title="Sélectionner comme rôle principal">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Cochez les rôles à assigner et sélectionnez le rôle principal avec le bouton radio.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Statut</label>
                        <select name=etatUser id="manageUserStatus" class="form-select">
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="newPassword" class="form-control" placeholder="Laisser vide pour conserver">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="updateUserAccessBtn" class="btn btn-primary">Mettre à jour</button>
                    <button type="submit" name="deleteUserAccessBtn" class="btn btn-danger"
                        onclick="return confirm('Supprimer cet accès ?')">Supprimer l'accès</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Attribuer Accès -->
<div class="modal fade" id="assignUserAccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attribuer un Accès</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="controller/assign_user_access.php">
                <input type="hidden" name=idAgent id="assignUserIdAgent">
                <div class="modal-body">
                    <div class="alert alert-success">
                        Créer un accès pour: <strong id="assignUserDisplayName"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Identifiant <span class="text-danger">*</span></label>
                        <input type="text" name=loginUser id=loginUser class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôles <span class="text-danger">*</span></label>
                        <div class="border p-3 rounded">
                            <div class="d-flex align-items-center mb-3 border-bottom pb-2">
                                <span class="flex-grow-1 fw-bold">Rôles à assigner</span>
                                <span class="ms-3 fw-bold">Principal</span>
                            </div>
                            <?php foreach ($roles as $role): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <input class="form-check-input me-3" type="checkbox" name="roles[]" value="<?= $role['idRole'] ?>" id="assign_role_<?= $role['idRole'] ?>">
                                    <span class="flex-grow-1 fw-bold"><?= $role['nomRole'] ?></span>
                                    <input class="form-check-input ms-3" type="radio" name="principalRole" value="<?= $role['idRole'] ?>" id="assign_principal_<?= $role['idRole'] ?>" title="Sélectionner comme rôle principal">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Cochez les rôles à assigner et sélectionnez le rôle principal avec le bouton radio.</small>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Le mot de passe par défaut sera : <strong>12345678</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="assignUserAccessBtn" class="btn btn-primary">Créer l'accès</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Importer Agents -->
<div class="modal fade" id="importAgentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des Agents</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="controller/import_agent.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Téléchargez un fichier Excel (.xlsx, .xls) contenant les données des agents.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fichier Excel <span class="text-danger">*</span></label>
                        <input type="file" name="importFile" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="importAgentBtn" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .table-success-light {
        background-color: rgba(25, 135, 84, 0.05) !important;
    }

    .table-warning-light {
        background-color: rgba(255, 193, 7, 0.05) !important;
    }

    .card {
        border-radius: 10px;
    }

    .btn-group-sm>.btn {
        border-radius: 6px;
        margin: 0 1px;
    }

    .badge {
        font-size: 0.75em;
        padding: 0.35em 0.65em;
    }
</style>

<script>
    // Variables globales
    const DEFAULT_PASSWORD = '12345678';

    // Fonctions de gestion des services
    function updateServiceOptions() {
        const structureSelect = document.getElementById('idStructure');
        const serviceSelect = document.getElementById('idService');
        const structureId = structureSelect.value;

        serviceSelect.innerHTML = '<option value="">Sélectionner</option>';

        if (structureId) {
            fetch(`controller/get_services.php?idStructure=${structureId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.idService;
                        option.textContent = service.designation;
                        serviceSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Erreur:', error));
        }
    }

    function updateEditServiceOptions() {
        const structureSelect = document.getElementById('editAgentStructure');
        const serviceSelect = document.getElementById('editAgentService');
        const structureId = structureSelect.value;
        const currentServiceId = serviceSelect.getAttribute('data-current-service');

        serviceSelect.innerHTML = '<option value="">Sélectionner</option>';

        if (structureId) {
            fetch(`controller/get_services.php?idStructure=${structureId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.idService;
                        option.textContent = service.designation;
                        if (service.idService == currentServiceId) {
                            option.selected = true;
                        }
                        serviceSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Erreur:', error));
        }
    }

    // Fonctions de gestion des grades
    function updateGradeOptions() {
        const typeSelect = document.getElementById('type_agent');
        const gradeSelect = document.getElementById('grade_id');
        const selectedType = typeSelect.value;

        Array.from(gradeSelect.options).forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                const gradeType = option.getAttribute('data-type');
                option.style.display = (gradeType === selectedType || !selectedType) ? 'block' : 'none';
            }
        });

        // Reset si le grade actuel n'est plus valide
        const currentGrade = gradeSelect.value;
        let isCurrentGradeValid = false;

        Array.from(gradeSelect.options).forEach(option => {
            if (option.value === currentGrade && option.style.display !== 'none') {
                isCurrentGradeValid = true;
            }
        });

        if (!isCurrentGradeValid) {
            gradeSelect.value = '';
        }
    }

    function updateEditGradeOptions() {
        const typeSelect = document.getElementById('editAgentType');
        const gradeSelect = document.getElementById('editAgentGrade');
        const selectedType = typeSelect.value;

        Array.from(gradeSelect.options).forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                const gradeType = option.getAttribute('data-type');
                option.style.display = (gradeType === selectedType || !selectedType) ? 'block' : 'none';
            }
        });

        const currentGrade = gradeSelect.value;
        let isCurrentGradeValid = false;

        Array.from(gradeSelect.options).forEach(option => {
            if (option.value === currentGrade && option.style.display !== 'none') {
                isCurrentGradeValid = true;
            }
        });

        if (!isCurrentGradeValid) {
            gradeSelect.value = '';
        }
    }

    // Fonctions de gestion des modales
    function editAgent(id, noms, matricule, type_agent, grade_id, telephone, email, idStructure, idService) {
        document.getElementById('editAgentId').value = id;
        document.getElementById('editAgentNoms').value = noms;
        document.getElementById('editAgentMatricule').value = matricule || '';
        document.getElementById('editAgentType').value = type_agent || '';
        document.getElementById('editTelephone').value = telephone || '';
        document.getElementById('editEmail').value = email || '';

        // Sélectionner la structure
        const structureSelect = document.getElementById('editAgentStructure');
        structureSelect.value = idStructure;

        // Sélectionner le grade
        if (grade_id) {
            document.getElementById('editAgentGrade').value = grade_id;
        }

        // Stocker l'ID du service pour le sélectionner après chargement
        const serviceSelect = document.getElementById('editAgentService');
        serviceSelect.setAttribute('data-current-service', idService || '');

        // Mettre à jour les options
        updateEditGradeOptions();
        updateEditServiceOptions();

        new bootstrap.Modal(document.getElementById('editAgentModal')).show();
    }

    function manageUserAccess(idAgent, idUser, agentName, loginUser, etatUser, allRoles) {
        document.getElementById('manageUserIdAgent').value = idAgent;
        document.getElementById('manageUserIdUser').value = idUser;
        document.getElementById('manageUserAgentName').textContent = agentName;
        document.getElementById('manageLoginUser').value = loginUser;
        document.getElementById('manageUserStatus').value = etatUser;

        // Récupérer et cocher les rôles actuels
        fetch(`controller/get_user_roles.php?idUser=${idUser}`)
            .then(response => response.json())
            .then(userRoles => {
                // Décocher tous d'abord
                const checkboxes = document.querySelectorAll('#manageUserRoles input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = false);
                const radios = document.querySelectorAll('#manageUserRoles input[type="radio"]');
                radios.forEach(rb => rb.checked = false);

                // Cocher les rôles de l'utilisateur et le principal
                userRoles.forEach(roleData => {
                    const cb = document.getElementById(`role_${roleData.idRole}`);
                    if (cb) cb.checked = true;
                    if (roleData.isPrincipal) {
                        const rb = document.getElementById(`principal_${roleData.idRole}`);
                        if (rb) rb.checked = true;
                    }
                });

                new bootstrap.Modal(document.getElementById('manageUserAccessModal')).show();
            })
            .catch(error => console.error('Erreur:', error));
    }

    function assignUserAccess(idAgent, nomUser) {
        document.getElementById('assignUserIdAgent').value = idAgent;
        document.getElementById('assignUserDisplayName').textContent = nomUser;

        // Générer un login par défaut
        const defaultLogin = nomUser.toLowerCase()
            .replace(/\s+/g, '.')
            .replace(/[^a-z0-9.]/g, '');
        document.getElementById('loginUser').value = defaultLogin;

        // Cocher par défaut le premier rôle comme principal
        const firstRadio = document.querySelector('#assignUserAccessModal input[type="radio"][name="principalRole"]');
        if (firstRadio) {
            firstRadio.checked = true;
        }

        new bootstrap.Modal(document.getElementById('assignUserAccessModal')).show();
    }

    function confirmDelete(idAgent) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Cette action supprimera l'agent et son accès utilisateur !",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_agent.php?idAgent=${idAgent}`;
                }
            });
        } else {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet agent ?')) {
                window.location.href = `controller/delete_agent.php?idAgent=${idAgent}`;
            }
        }
    }

    // Mode rapide - Gestion de l'affichage des champs optionnels
    let isRapideMode = true;

    function toggleRapideMode() {
        const optionalFields = ['matriculeField', 'gradeField', 'telephoneField', 'serviceField', 'emailField'];
        const alert = document.getElementById('rapideModeAlert');
        const btnMoreFields = document.getElementById('btnMoreFields');
        const btnRapideSave = document.getElementById('btnRapideSave');
        const isRapidemodeInput = document.getElementById('isRapideMode');

        if (isRapideMode) {
            // Passer en mode complet
            optionalFields.forEach(fieldId => {
                document.getElementById(fieldId).style.display = 'block';
            });
            alert.style.display = 'none';
            btnMoreFields.innerHTML = '<i class="bi bi-dash"></i> Masquer champs optionnels';
            btnMoreFields.classList.remove('btn-primary');
            btnMoreFields.classList.add('btn-outline-primary');
            btnRapideSave.classList.add('btn-outline-success');
            btnRapideSave.classList.remove('btn-success');
            isRapideMode = false;
            isRapidemodeInput.value = '0';
        } else {
            // Revenir en mode rapide
            optionalFields.forEach(fieldId => {
                document.getElementById(fieldId).style.display = 'none';
            });
            alert.style.display = 'block';
            btnMoreFields.innerHTML = '<i class="bi bi-plus"></i> Ajouter plus de champs';
            btnMoreFields.classList.add('btn-primary');
            btnMoreFields.classList.remove('btn-outline-primary');
            btnRapideSave.classList.remove('btn-outline-success');
            btnRapideSave.classList.add('btn-success');
            isRapideMode = true;
            isRapidemodeInput.value = '1';
        }
    }

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        // Configuration du bouton "Ajouter plus de champs"
        const btnMoreFields = document.getElementById('btnMoreFields');
        if (btnMoreFields) {
            btnMoreFields.addEventListener('click', toggleRapideMode);
        }

        // Validation des formulaires Bootstrap
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        // Initialiser les filtres de grade
        updateGradeOptions();

        // Masquer les champs optionnels au démarrage (mode rapide)
        const optionalFields = ['matriculeField', 'gradeField', 'telephoneField', 'serviceField', 'emailField'];
        optionalFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.style.display = 'none';
            }
        });

        // Afficher l'alerte du mode rapide
        document.getElementById('rapideModeAlert').style.display = 'block';

        // Actualisation automatique toutes les 5 minutes
        setInterval(() => {
            const modals = document.querySelectorAll('.modal.show');
            if (modals.length === 0) {
                location.reload();
            }
        }, 300000);
    });

    // Fonction d'affichage des notifications
    function showNotification(message, type = 'success') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';

        const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="bi bi-${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

        const main = document.getElementById('main');
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = alertHtml;
        main.insertBefore(tempDiv.firstElementChild, main.firstElementChild);

        // Auto-supprimer après 5 secondes
        setTimeout(() => {
            const alert = main.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
</script>

<?php include "./views/include/footer.php"; ?>