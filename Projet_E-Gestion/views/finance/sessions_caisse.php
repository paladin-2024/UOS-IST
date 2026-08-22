<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer les caisses auxquelles l'utilisateur a accès
$stmt = $connexion->prepare("
    SELECT DISTINCT c.* 
    FROM caisses c
    LEFT JOIN droits_acces_finances d ON (d.entite_id = c.id OR d.entite_id IS NULL) AND d.type = 'Caisse'
    WHERE d.idUser = :idUser AND d.est_actif = 1 
    AND (d.date_debut IS NULL OR d.date_debut <= CURRENT_DATE) 
    AND (d.date_fin IS NULL OR d.date_fin >= CURRENT_DATE)
    ORDER BY c.designation ASC
");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$caisses_accessibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la caisse sélectionnée (si présente dans l'URL)
$caisse_id = isset($_GET['caisse_id']) ? intval($_GET['caisse_id']) : null;

// Vérifier si l'utilisateur a accès à cette caisse spécifique
$a_access = false;
$niveau_acces = 'Lecture';
$caisse_selected = null;

if ($caisse_id) {
    foreach ($caisses_accessibles as $caisse) {
        if ($caisse['id'] == $caisse_id) {
            $a_access = true;
            $caisse_selected = $caisse;
            break;
        }
    }
    
    // Récupérer le niveau d'accès pour cette caisse
    $stmt = $connexion->prepare("
        SELECT niveau 
        FROM droits_acces_finances 
        WHERE idUser = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1
        ORDER BY entite_id DESC, niveau DESC
        LIMIT 1
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $droit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($droit) {
        $niveau_acces = $droit['niveau'];
    }
} elseif (!empty($caisses_accessibles)) {
    // Si aucune caisse n'est sélectionnée, utiliser la première caisse accessible
    $caisse_id = $caisses_accessibles[0]['id'];
    $caisse_selected = $caisses_accessibles[0];
    $a_access = true;
    
    // Récupérer le niveau d'accès pour cette caisse
    $stmt = $connexion->prepare("
        SELECT niveau 
        FROM droits_acces_finances 
        WHERE idUser = :idUser AND type = 'Caisse' 
        AND (entite_id = :caisse_id OR entite_id IS NULL)
        AND est_actif = 1
        ORDER BY entite_id DESC, niveau DESC
        LIMIT 1
    ");
    $stmt->bindParam(':idUser', $idUser);
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $droit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($droit) {
        $niveau_acces = $droit['niveau'];
    }
}

// Récupérer les informations de l'agent connecté
$agent_id = null;
$stmt = $connexion->prepare("SELECT idAgent FROM t_users WHERE idUser = :idUser");
$stmt->bindParam(':idUser', $idUser);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user_data && $user_data['idAgent']) {
    $agent_id = $user_data['idAgent'];
}

// Récupérer la session active de l'utilisateur pour la caisse sélectionnée
$session_active = null;
$transactions_session = [
    'entrees' => 0,
    'sorties' => 0,
    'solde_theorique' => 0
];

if ($a_access && $caisse_id && $agent_id) {
    $stmt = $connexion->prepare("
        SELECT s.*, a.noms as agent_nom, a.matricule as agent_matricule
        FROM sessions_caisse s
        LEFT JOIN agent a ON s.idAgent = a.idAgent
        WHERE s.caisse_id = :caisse_id AND s.idAgent = :agent_id AND s.statut = 'Ouverte'
        ORDER BY s.date_ouverture DESC
        LIMIT 1
    ");
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->bindParam(':agent_id', $agent_id);
    $stmt->execute();
    $session_active = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si une session est active, calculer le solde théorique
    if ($session_active) {
        // Récupérer les transactions de la session active
        // Récupérer les transactions de la session active
        $stmt = $connexion->prepare("
        SELECT 
            SUM(CASE WHEN type = 'Recette' THEN montant ELSE 0 END) as total_entrees,
            SUM(CASE WHEN type IN ('Dépense', 'Transfert') THEN montant ELSE 0 END) as total_sorties
        FROM transactions 
        WHERE session_caisse_id = :session_id AND statut != 'Annulée'
        ");
        $stmt->bindParam(':session_id', $session_active['id']);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $transactions_session['entrees'] = floatval($result['total_entrees'] ?? 0);
        $transactions_session['sorties'] = floatval($result['total_sorties'] ?? 0);
        $transactions_session['solde_theorique'] = $session_active['montant_ouverture'] + $transactions_session['entrees'] - $transactions_session['sorties'];

    }
}


// Récupérer l'historique des sessions de la caisse sélectionnée
$sessions_history = [];
if ($a_access && $caisse_id) {
    $stmt = $connexion->prepare("
        SELECT s.*,
               a.noms as agent_nom, a.matricule as agent_matricule,
               v.noms as validateur_nom, v.matricule as validateur_matricule
        FROM sessions_caisse s
        LEFT JOIN agent a ON s.idAgent = a.idAgent
        LEFT JOIN agent v ON s.idValidateur = v.idAgent
        WHERE s.caisse_id = :caisse_id AND s.statut != 'Ouverte'
        ORDER BY s.date_ouverture DESC
        LIMIT 50
    ");
    $stmt->bindParam(':caisse_id', $caisse_id);
    $stmt->execute();
    $sessions_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Sessions de Caisse</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Sessions de Caisse</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!$a_access && empty($caisses_accessibles)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Accès non autorisé</h4>
                <p>Vous n'avez pas d'accès à une caisse. Veuillez contacter votre administrateur.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Sélection de caisse -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Sélectionner une caisse</h5>
                            <form action="" method="GET" class="row g-3">
                                <input type="hidden" name="view" value="finance/sessions_caisse">
                                <div class="col-md-8">
                                    <select name="caisse_id" id="caisse_id" class="form-select" required>
                                        <?php foreach ($caisses_accessibles as $caisse): ?>
                                            <option value="<?= $caisse['id'] ?>" <?= ($caisse_id == $caisse['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($caisse['designation']) ?> (<?= $caisse['devise'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">Sélectionner</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php if ($caisse_selected): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Informations de la caisse</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Nom:</strong> <?= htmlspecialchars($caisse_selected['designation']) ?></p>
                                    <p><strong>Devise:</strong> <?= htmlspecialchars($caisse_selected['devise']) ?></p>
                                    <p><strong>Solde actuel:</strong> <?= number_format($caisse_selected['solde_actuel'], 2) ?> <?= htmlspecialchars($caisse_selected['devise']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Plafond:</strong> <?= ($caisse_selected['plafond_caisse']) ? number_format($caisse_selected['plafond_caisse'], 2) . ' ' . htmlspecialchars($caisse_selected['devise']) : 'Non défini' ?></p>
                                    <p><strong>Localisation:</strong> <?= htmlspecialchars($caisse_selected['localisation'] ?? 'Non définie') ?></p>
                                    <p><strong>Statut:</strong> 
                                        <?php if ($caisse_selected['est_actif']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($caisse_selected): ?>
                <!-- Session Active -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title d-flex justify-content-between align-items-center">
                                    Session Active
                                    <?php if (!$session_active && $niveau_acces != 'Lecture' && $agent_id): ?>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#openSessionModal">
                                            <i class="bi bi-unlock-fill"></i> Ouvrir une Session
                                        </button>
                                    <?php endif; ?>
                                </h5>

                                <?php if ($session_active): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID Session:</strong> #<?= $session_active['id'] ?></p>
                                        <p><strong>Date d'ouverture:</strong> <?= date('d/m/Y H:i', strtotime($session_active['date_ouverture'])) ?></p>
                                        <p><strong>Montant d'ouverture:</strong> <?= number_format($session_active['montant_ouverture'], 2) ?> <?= htmlspecialchars($caisse_selected['devise']) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Caissier:</strong> <?= htmlspecialchars($session_active['agent_nom']) ?> (<?= htmlspecialchars($session_active['agent_matricule']) ?>)</p>
                                        <p><strong>Durée:</strong> <?= calculerDuree($session_active['date_ouverture']) ?></p>
                                        
                                        <?php if ($niveau_acces != 'Lecture' && $agent_id == $session_active['idAgent']): ?>
                                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#closeSessionModal">
                                                <i class="bi bi-lock-fill"></i> Fermer la Session
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    Aucune session active pour cette caisse. Utilisez le bouton ci-dessus pour ouvrir une nouvelle session.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historique des Sessions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Historique des Sessions</h5>
                                
                                <?php if (empty($sessions_history)): ?>
                                <div class="alert alert-info">
                                    Aucun historique de session disponible pour cette caisse.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date Ouverture</th>
                                            <th>Date Fermeture</th>
                                            <th>Montant Ouverture</th>
                                            <th>Montant Fermeture</th>
                                            <th>Différence</th>
                                            <th>Caissier</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions_history as $session): ?>
                                        <tr>
                                            <td><?= $session['id'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($session['date_ouverture'])) ?></td>
                                            <td><?= $session['date_fermeture'] ? date('d/m/Y H:i', strtotime($session['date_fermeture'])) : '-' ?></td>
                                            <td class="text-end"><?= number_format($session['montant_ouverture'], 2) ?></td>
                                            <td class="text-end"><?= $session['montant_fermeture'] ? number_format($session['montant_fermeture'], 2) : '-' ?></td>
                                            <td class="text-end">
                                                <?php if ($session['difference']): ?>
                                                    <?php $class = $session['difference'] < 0 ? 'text-danger' : ($session['difference'] > 0 ? 'text-success' : ''); ?>
                                                    <span class="<?= $class ?>"><?= number_format($session['difference'], 2) ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($session['agent_nom']) ?> (<?= htmlspecialchars($session['agent_matricule']) ?>)</td>
                                            <td>
                                                <?php 
                                                    $statusBadge = '';
                                                    switch($session['statut']) {
                                                        case 'Fermée':
                                                            $statusBadge = 'bg-success';
                                                            break;
                                                        case 'Annulée':
                                                            $statusBadge = 'bg-danger';
                                                            break;
                                                        default:
                                                            $statusBadge = 'bg-secondary';
                                                    }
                                                ?>
                                                <span class="badge <?= $statusBadge ?>"><?= $session['statut'] ?></span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-session" data-id="<?= $session['id'] ?>" data-bs-toggle="modal" data-bs-target="#viewSessionModal">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                
                                                <?php if ($session['statut'] == 'Fermée' && !$session['date_validation'] && $niveau_acces == 'Validation' || $niveau_acces == 'Administration'): ?>
                                                <button type="button" class="btn btn-sm btn-success validate-session" data-id="<?= $session['id'] ?>">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- Modal pour ouvrir une session -->
<div class="modal fade" id="openSessionModal" tabindex="-1" aria-labelledby="openSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/session_caisse_operations.php" method="POST">
                <input type="hidden" name="action" value="ouvrir">
                <input type="hidden" name="caisse_id" value="<?= $caisse_id ?>">
                <input type="hidden" name="idAgent" value="<?= $agent_id ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="openSessionModalLabel">Ouvrir une nouvelle session de caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Affichage du montant attendu en caisse -->
                    <div class="alert alert-info mb-3">
                        <strong>Montant attendu en caisse:</strong> 
                        <span id="montant_attendu_ouverture"><?= number_format($caisse_selected['solde_actuel'], 2) ?></span> <?= htmlspecialchars($caisse_selected['devise']) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="montant_ouverture" class="form-label">Montant d'ouverture <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="montant_ouverture" name="montant_ouverture" value="<?= number_format($caisse_selected['solde_actuel'], 2, '.', '') ?>" required>
                            <span class="input-group-text"><?= htmlspecialchars($caisse_selected['devise']) ?></span>
                        </div>
                    </div>
                    
                    <!-- Affichage de la différence calculée -->
                    <div id="difference_ouverture_container" class="mb-3" style="display: none;">
                        <div class="alert alert-warning">
                            <strong>Différence détectée: </strong>
                            <span id="difference_ouverture">0.00</span> <?= htmlspecialchars($caisse_selected['devise']) ?>
                            <div id="type_difference_ouverture"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle"></i> 
                            En ouvrant cette session, vous confirmez que vous avez compté physiquement le montant indiqué dans la caisse.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ouvrir la session</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal pour fermer une session -->
<div class="modal fade" id="closeSessionModal" tabindex="-1" aria-labelledby="closeSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/session_caisse_operations.php" method="POST">
                <input type="hidden" name="action" value="fermer">
                <input type="hidden" name="session_id" value="<?= $session_active ? $session_active['id'] : '' ?>">
                <input type="hidden" name="caisse_id" value="<?= $caisse_id ?>">
                <input type="hidden" id="montant_calcule" name="montant_calcule" value="<?= number_format($transactions_session['solde_theorique'], 2, '.', '') ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="closeSessionModalLabel">Fermer la session de caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Affichage du montant calculé attendu en caisse -->
                    <div class="alert alert-info mb-3">
                        <strong>Montant attendu en caisse:</strong> 
                        <span id="montant_attendu_fermeture"><?= number_format($transactions_session['solde_theorique'], 2) ?></span>
                        <div><small>(Le montant attendu est basé sur le solde d'ouverture - aucune transaction n'est prise en compte)</small></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="montant_fermeture" class="form-label">Montant de fermeture <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="montant_fermeture" name="montant_fermeture" required>
                            <span class="input-group-text"><?= htmlspecialchars($caisse_selected['devise']) ?></span>
                        </div>
                        <small class="text-muted">Entrez le montant total compté physiquement dans la caisse.</small>
                    </div>
                    
                    <!-- Affichage de la différence calculée -->
                    <div id="difference_fermeture_container" class="mb-3" style="display: none;">
                        <div class="alert" id="difference_alert">
                            <strong>Différence détectée: </strong>
                            <span id="difference_fermeture">0.00</span> <?= htmlspecialchars($caisse_selected['devise']) ?>
                            <div id="type_difference_fermeture"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="explication_difference" class="form-label">Explication de la différence (si applicable)</label>
                        <textarea class="form-control" id="" name="explication_difference" rows="3"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="bi bi-exclamation-triangle"></i> 
                            La fermeture de session nécessite un comptage précis de la caisse. Toute différence devra être expliquée.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Fermer la session</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal pour voir les détails d'une session -->
<div class="modal fade" id="viewSessionModal" tabindex="-1" aria-labelledby="viewSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSessionModalLabel">Détails de la session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sessionDetailsContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour valider une session -->
<div class="modal fade" id="validateSessionModal" tabindex="-1" aria-labelledby="validateSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/session_caisse_operations.php" method="POST">
                <input type="hidden" name="action" value="valider">
                <input type="hidden" name="session_id" id="validate_session_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="validateSessionModalLabel">Valider la session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir valider cette session de caisse?</p>
                    <p>La validation confirme que vous avez vérifié les montants et approuvez la fermeture.</p>
                    
                    <div class="mb-3">
                        <label for="commentaire_validation" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire_validation" name="commentaire_validation" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Valider la session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Afficher les détails d'une session
    const viewButtons = document.querySelectorAll('.view-session');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sessionId = this.getAttribute('data-id');
            const container = document.getElementById('sessionDetailsContainer');
            
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p>Chargement des détails...</p>
                </div>
            `;
            
            fetch(`controller/get_session_details.php?id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    // Formatage des dates
                    const dateOuverture = new Date(data.date_ouverture).toLocaleString();
                    const dateFermeture = data.date_fermeture ? new Date(data.date_fermeture).toLocaleString() : 'Non définie';
                    const dateValidation = data.date_validation ? new Date(data.date_validation).toLocaleString() : 'Non validée';
                    
                    // Classe CSS pour la différence
                    let differenceClass = '';
                    if (data.difference < 0) differenceClass = 'text-danger';
                    else if (data.difference > 0) differenceClass = 'text-success';
                    
                    // Construction du HTML de détails
                    container.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Informations générales</h6>
                                <p><strong>Session ID:</strong> #${data.id}</p>
                                <p><strong>Caisse:</strong> ${data.caisse_nom}</p>
                                <p><strong>Caissier:</strong> ${data.agent_nom} (${data.agent_matricule})</p>
                                <p><strong>Statut:</strong> <span class="badge ${data.statut === 'Fermée' ? 'bg-success' : 'bg-danger'}">${data.statut}</span></p>
                                <p><strong>Date d'ouverture:</strong> ${dateOuverture}</p>
                                <p><strong>Date de fermeture:</strong> ${dateFermeture}</p>
                                <p><strong>Date de validation:</strong> ${dateValidation}</p>
                                                                ${data.validateur_nom ? `<p><strong>Validé par:</strong> ${data.validateur_nom} (${data.validateur_matricule})</p>` : ''}
                            </div>
                            <div class="col-md-6">
                                <h6>Montants</h6>
                                <p><strong>Montant d'ouverture:</strong> ${parseFloat(data.montant_ouverture).toLocaleString('fr-FR', {minimumFractionDigits: 2})} ${data.devise}</p>
                                <p><strong>Montant de fermeture:</strong> ${data.montant_fermeture ? parseFloat(data.montant_fermeture).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' ' + data.devise : 'Non défini'}</p>
                                <p><strong>Montant calculé:</strong> ${data.montant_calcule ? parseFloat(data.montant_calcule).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' ' + data.devise : 'Non défini'}</p>
                                <p><strong>Différence:</strong> <span class="${differenceClass}">${data.difference ? parseFloat(data.difference).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' ' + data.devise : 'Non définie'}</span></p>
                            </div>
                        </div>
                        
                        ${data.explication_difference ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Explication de la différence</h6>
                                <div class="alert alert-info">
                                    ${data.explication_difference}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${data.commentaire ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Commentaire</h6>
                                <div class="alert alert-light">
                                    ${data.commentaire}
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    `;
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    container.innerHTML = `<div class="alert alert-danger">Une erreur s'est produite lors du chargement des données.</div>`;
                });
        });
    });
    
    // Validation d'une session
    const validateButtons = document.querySelectorAll('.validate-session');
    validateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sessionId = this.getAttribute('data-id');
            document.getElementById('validate_session_id').value = sessionId;
            
            // Afficher la modal de confirmation
            const validateModal = new bootstrap.Modal(document.getElementById('validateSessionModal'));
            validateModal.show();
        });
    });
    
    // Calculer la différence lors de la fermeture de session
    const montantFermetureInput = document.getElementById('montant_fermeture');
    if (montantFermetureInput) {
        montantFermetureInput.addEventListener('input', function() {
            const montantFermeture = parseFloat(this.value) || 0;
            const montantCalcule = <?= $session_active ? $session_active['montant_ouverture'] : 0 ?>;
            const difference = montantFermeture - montantCalcule;
            
            // Afficher une alerte en fonction de la différence
            const explicationField = document.getElementById('explication_difference');
            if (difference !== 0) {
                explicationField.setAttribute('required', 'required');
                explicationField.parentElement.classList.add('required-field');
                
                if (difference < 0) {
                    explicationField.parentElement.querySelector('label').innerHTML = 
                        `Explication du manque de ${Math.abs(difference).toFixed(2)} ${<?= json_encode($caisse_selected['devise'] ?? '') ?>} <span class="text-danger">*</span>`;
                } else {
                    explicationField.parentElement.querySelector('label').innerHTML = 
                        `Explication de l'excédent de ${difference.toFixed(2)} ${<?= json_encode($caisse_selected['devise'] ?? '') ?>} <span class="text-danger">*</span>`;
                }
            } else {
                explicationField.removeAttribute('required');
                explicationField.parentElement.classList.remove('required-field');
                explicationField.parentElement.querySelector('label').innerHTML = 'Explication de la différence (si applicable)';
            }
        });
    }
});




document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'ouverture de session
    const montantOuvertureInput = document.getElementById('montant_ouverture');
    if (montantOuvertureInput) {
        montantOuvertureInput.addEventListener('input', function() {
            const montantSaisi = parseFloat(this.value) || 0;
            const montantAttendu = parseFloat(<?= json_encode($caisse_selected['solde_actuel']) ?>) || 0;
            const difference = montantSaisi - montantAttendu;
            
            const containerDifference = document.getElementById('difference_ouverture_container');
            const spanDifference = document.getElementById('difference_ouverture');
            const typeDifference = document.getElementById('type_difference_ouverture');
            
            if (difference === 0) {
                containerDifference.style.display = 'none';
            } else {
                containerDifference.style.display = 'block';
                spanDifference.textContent = Math.abs(difference).toFixed(2);
                
                if (difference < 0) {
                    typeDifference.innerHTML = '<span class="text-danger">Manquant en caisse</span>';
                    containerDifference.querySelector('.alert').className = 'alert alert-danger';
                } else {
                    typeDifference.innerHTML = '<span class="text-success">Excédent en caisse</span>';
                    containerDifference.querySelector('.alert').className = 'alert alert-warning';
                }
            }
        });
    }
    
    // Gestion de la fermeture de session
    const montantFermetureInput = document.getElementById('montant_fermeture');
    if (montantFermetureInput) {
        montantFermetureInput.addEventListener('input', function() {
            // Récupérer les montants
            const montantSaisi = parseFloat(this.value) || 0;
            const montantAttendu = parseFloat(document.getElementById('montant_calcule').value) || 0;
            const difference = montantSaisi - montantAttendu;
            
            // Référence aux éléments DOM
            const containerDifference = document.getElementById('difference_fermeture_container');
            const spanDifference = document.getElementById('difference_fermeture');
            const typeDifference = document.getElementById('type_difference_fermeture');
            const alertElement = document.getElementById('difference_alert');
            const explicationField = document.getElementById('explication_difference');
            
            // Afficher la différence s'il y en a une
            if (difference === 0) {
                containerDifference.style.display = 'none';
                explicationField.removeAttribute('required');
            } else {
                containerDifference.style.display = 'block';
                spanDifference.textContent = Math.abs(difference).toFixed(2);
                
                // Mettre en forme selon le type de différence
                if (difference < 0) {
                    typeDifference.innerHTML = '<span class="text-danger">Manquant en caisse</span>';
                    alertElement.className = 'alert alert-danger';
                    explicationField.setAttribute('required', 'required');
                    explicationField.parentElement.querySelector('label').innerHTML = 
                        `Explication du manque de ${Math.abs(difference).toFixed(2)} ${<?= json_encode($caisse_selected['devise'] ?? '') ?>} <span class="text-danger">*</span>`;
                } else {
                    typeDifference.innerHTML = '<span class="text-success">Excédent en caisse</span>';
                    alertElement.className = 'alert alert-warning';
                    explicationField.setAttribute('required', 'required');
                    explicationField.parentElement.querySelector('label').innerHTML = 
                        `Explication de l'excédent de ${difference.toFixed(2)} ${<?= json_encode($caisse_selected['devise'] ?? '') ?>} <span class="text-danger">*</span>`;
                }
                
                // Calculer l'écart en pourcentage pour l'avertissement
                const ecartPct = Math.abs(difference / montantAttendu * 100);
                if (ecartPct > 10) {
                    // Ajouter un avertissement pour un écart important
                    if (!document.getElementById('ecart_important_alert')) {
                        const alertDiv = document.createElement('div');
                        alertDiv.id = 'ecart_important_alert';
                        alertDiv.className = 'alert alert-danger mt-3';
                        alertDiv.innerHTML = `
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            <strong>Attention!</strong> L'écart représente plus de 10% du montant attendu. 
                            Veuillez revérifier votre comptage et justifier cette différence importante.
                        `;
                        containerDifference.appendChild(alertDiv);
                    }
                } else {
                    // Supprimer l'avertissement si l'écart est réduit
                    const alertImportant = document.getElementById('ecart_important_alert');
                    if (alertImportant) {
                        alertImportant.remove();
                    }
                }
            }
        });
    }
});

</script>

<?php
// Fonction pour calculer la durée depuis l'ouverture de la session
function calculerDuree($dateOuverture) {
    $now = new DateTime();
    $ouverture = new DateTime($dateOuverture);
    $difference = $now->diff($ouverture);
    
    if ($difference->days > 0) {
        return $difference->format('%a jours, %h heures, %i minutes');
    } else {
        return $difference->format('%h heures, %i minutes');
    }
}

include "./views/include/footer.php";
?>

