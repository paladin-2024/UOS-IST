<?php
include "./views/include/header.php";
$universite = new Universite();
$ecue = new Ecue();
$agent=new Agent();

// Récupérer l'ID de l'ECUE
$idEcue = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idEcue <= 0) {
    echo "<script>window.location.href = '?view=enseignement/cours';</script>";
    exit;
}

// Récupérer l'année académique actuelle
$currentYear = $universite->getCurrentAcademicYear();

// Récupérer les détails de l'ECUE
$ecueDetails = $ecue->getEcueById($idEcue);
if (!$ecueDetails) {
    echo "<script>window.location.href = '?view=enseignement/cours';</script>";
    exit;
}

// Récupérer les enseignants associés à cet ECUE
$enseignants = $ecue->getEnseignantsByEcue($idEcue, $currentYear['idannee_acad']);



// Après les lignes existantes qui récupèrent les détails du cours
$userId = $_SESSION['id'] ?? 0;
$estEnseignant = false;

// Vérifier si l'utilisateur actuel est parmi les enseignants de l'ECUE
if ($userId && !empty($enseignants)) {
    // Récupérer l'ID de l'agent pour l'utilisateur connecté
    $agentId = $agent->getAgentIdByUserId($userId);
    
    foreach ($enseignants as $e) {
        // Si l'agent lié à cet utilisateur est parmi les enseignants
        if ($agentId == $e['idAgent']) {
            $estEnseignant = true;
            break;
        }
    }
}


// Récupérer les chapitres (parties) du cours
$chapitres = $ecue->getChaptersByEcue($idEcue);

// Récupérer les devoirs associés à cet ECUE
$devoirs = $ecue->getAssignmentsByEcue($idEcue, $currentYear['idannee_acad']);

// Récupérer les supports de cours
$supports = $ecue->getSupportsByEcue($idEcue);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>DÉTAILS DU COURS: <?= htmlspecialchars($ecueDetails['designationECUE']) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=recherche/mes_cours">Cours</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Informations du cours -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Informations générales</h5>
                        <div class="course-info">
                            <p><strong>Désignation:</strong> <?= htmlspecialchars($ecueDetails['designationECUE']) ?></p>
                            <p><strong>UE:</strong> <?= htmlspecialchars($ecueDetails['designationUE']) ?></p>
                            <p><strong>Semestre:</strong> <?= htmlspecialchars($ecueDetails['numeroSemestre']) ?></p>
                            <p><strong>Promotion:</strong> <?= htmlspecialchars($ecueDetails['designationPromotion']) ?></p>
                            <p><strong>Volume horaire:</strong></p>
                            <ul>
                                <li>CMI: <?= $ecueDetails['CMI'] ?> heures</li>
                                <li>TD: <?= $ecueDetails['TD'] ?> heures</li>
                                <li>TP: <?= $ecueDetails['TP'] ?> heures</li>
                            </ul>
                        </div>
                        
                        <h5 class="card-title mt-4">Enseignants</h5>
                        <?php if (empty($enseignants)): ?>
                            <p class="text-muted">Aucun enseignant affecté pour le moment.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($enseignants as $e): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($e['noms']) ?>
                                        <span class="badge bg-primary rounded-pill"><?= $e['poste'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <?php if (!$estEnseignant): // Affiche le bouton seulement si l'utilisateur n'est pas un enseignant de ce cours ?>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                                    <i class="bi bi-person-plus"></i> Ajouter un enseignant
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Supports de cours -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">
                            Supports de cours
                            <span>
                                | <a data-bs-toggle="modal" data-bs-target="#addSupportModal" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Ajouter
                                </a>
                            </span>
                        </h5>
                        
                        <?php if (empty($supports)): ?>
                            <p class="text-muted">Aucun support de cours disponible.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($supports as $support): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($support['titre']) ?></strong>
                                            <p class="mb-0 small"><?= htmlspecialchars(substr($support['description'], 0, 50)) ?>...</p>
                                        </div>
                                        <div>
                                            <a href="uploads/supports/<?= $support['fichier'] ?>" class="btn btn-sm btn-info" target="_blank">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <?php if ($support['est_payant']): ?>
                                                <span class="badge bg-warning">Payant</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Contenu du cours -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Chapitres du cours
                            <span>
                                | <a data-bs-toggle="modal" data-bs-target="#addChapterModal" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Ajouter un chapitre
                                </a>
                            </span>
                        </h5>
                        
                        <?php if (empty($chapitres)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun chapitre n'a encore été créé. Commencez par ajouter un chapitre.
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="chaptersAccordion">
                                <?php foreach ($chapitres as $index => $chapitre): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?= $chapitre['idpartie'] ?>">
                                            <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#collapse<?= $chapitre['idpartie'] ?>" 
                                                    aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" 
                                                    aria-controls="collapse<?= $chapitre['idpartie'] ?>">
                                                <?= htmlspecialchars($chapitre['titre']) ?>
                                                <span class="ms-auto badge bg-secondary">Ordre: <?= $chapitre['ordre'] ?></span>
                                            </button>
                                        </h2>
                                        <div id="collapse<?= $chapitre['idpartie'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" 
                                             aria-labelledby="heading<?= $chapitre['idpartie'] ?>" data-bs-parent="#chaptersAccordion">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="chapter-content">
                                                        <?= preg_replace('/<(script|iframe|object|embed)\b[^>]*>.*?<\/\1>/is', '', $chapitre['description'] ?? '') ?>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-warning me-2" onclick="openEditChapterModal(<?= $chapitre['idpartie'] ?>, '<?= addslashes(htmlspecialchars($chapitre['titre'])) ?>', <?= $chapitre['ordre'] ?>)">
                                                            <i class="bi bi-pencil"></i> Modifier
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <h6>Ressources du chapitre</h6>
                                                <?php 
                                                $ressources = $ecue->getRessourcesByChapter($chapitre['idpartie']);
                                                if (empty($ressources)): 
                                                ?>
                                                    <p class="text-muted">Aucune ressource n'a encore été ajoutée.</p>
                                                <?php else: ?>
                                                    <div class="list-group">
                                                        <?php foreach ($ressources as $ressource): ?>
                                                            <div class="list-group-item">
                                                                <div class="d-flex w-100 justify-content-between">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($ressource['titre']) ?></h6>
                                                                    <small>
                                                                        <span class="badge bg-<?= $ressource['type_ressource'] === 'PDF' ? 'danger' : 
                                                                            ($ressource['type_ressource'] === 'Vidéo' ? 'success' : 
                                                                            ($ressource['type_ressource'] === 'Audio' ? 'info' : 'secondary')) ?>">
                                                                            <?= $ressource['type_ressource'] ?>
                                                                        </span>
                                                                    </small>
                                                                </div>
                                                                <p class="mb-1"><?= htmlspecialchars(substr($ressource['description'], 0, 100)) ?>...</p>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <?php if ($ressource['fichier']): ?>
                                                                        <a href="uploads/ressources/<?= $ressource['fichier'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                            <i class="bi bi-download"></i> Télécharger
                                                                        </a>
                                                                    <?php elseif ($ressource['lien_externe']): ?>
                                                                        <a href="<?= $ressource['lien_externe'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                            <i class="bi bi-link-45deg"></i> Accéder
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($ressource['est_payant']): ?>
                                                                        <span class="badge bg-warning">Accès payant</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-3">
                                                    <button class="btn btn-sm btn-success" onclick="openAddResourceModal(<?= $chapitre['idpartie'] ?>)">
                                                        <i class="bi bi-plus-circle"></i> Ajouter une ressource
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Devoirs et évaluations -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">
                            Devoirs et évaluations
                            <span>
                                | <a data-bs-toggle="modal" data-bs-target="#createAssignmentModal" class="btnPage">
                                    <i class="bi bi-plus-circle-fill"></i> Ajouter un devoir
                                </a>
                            </span>
                        </h5>
                        
                        <?php if (empty($devoirs)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun devoir n'a encore été créé pour ce cours.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Titre</th>
                                            <th>Date limite</th>
                                            <th>Statut</th>
                                            <th>Accès</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($devoirs as $devoir): 
                                            $today = new DateTime();
                                            $deadline = !empty($devoir['date_limite']) ? new DateTime($devoir['date_limite']) : null;
                                            $isExpired = ($deadline !== null) ? ($today > $deadline) : false;
                                            $statusClass = $isExpired ? 'danger' : 'success';
                                            $statusText = $isExpired ? 'Expiré' : 'En cours';
                                            $dateLimiteDisplay = !empty($devoir['date_limite']) ? date('d/m/Y H:i', strtotime($devoir['date_limite'])) : '-';
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($devoir['titre']) ?></td>
                                                <td><?= $dateLimiteDisplay ?></td>
                                                <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                                <td>
                                                    <?= $devoir['est_payant'] 
                                                        ? '<span class="badge bg-warning">Accès payant</span>' 
                                                        : '<span class="badge bg-info">Accès libre</span>' ?>
                                                </td>
                                                <td>
                                                    <a href="?view=enseignement/devoir.details&id=<?= $devoir['iddevoir'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> Détails
                                                    </a>
                                                    <button class="btn btn-sm btn-warning" onclick="openEditAssignmentModal(<?= $devoir['iddevoir'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un enseignant -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un enseignant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="teacherForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_teacher">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    <input type="hidden" name="anneeAcad" value="<?= $currentYear['idannee_acad'] ?>">
                    
                    <div class="mb-3">
                        <label for="idAgent" class="form-label">Enseignant</label>
                        <select name="idAgent" id="idAgent" class="form-select" required>
                            <option value="">Sélectionnez un enseignant</option>
                            <?php 
                            $allTeachers = $ecue->getAllTeachers();
                            foreach ($allTeachers as $teacher): 
                            ?>
                                <option value="<?= $teacher['idAgent'] ?>"><?= htmlspecialchars($teacher['noms']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un enseignant.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="poste" class="form-label">Poste</label>
                        <select name="poste" id="poste" class="form-select" required>
                            <option value="">Sélectionnez un poste</option>
                            <option value="Titulaire">Titulaire</option>
                            <option value="Assistant">Assistant</option>
                            <option value="Suppléant">Suppléant</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un poste.</div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un chapitre -->
<div class="modal fade" id="addChapterModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un chapitre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="chapterForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_chapter">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-9">
                            <label for="chapter_titre" class="form-label">Titre du chapitre</label>
                            <input type="text" name="titre" id="chapter_titre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un titre.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="ordre" class="form-label">Ordre</label>
                            <input type="number" name="ordre" id="ordre" class="form-control" value="1" min="1" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="chapter_description" class="form-label">Contenu</label>
                            <textarea name="description" id="chapter_description" class="form-control" rows="10"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour ajouter une ressource -->
<div class="modal fade" id="addResourceModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une ressource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="resourceForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="add_resource">
                    <input type="hidden" name="idpartie" id="resource_idpartie">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="resource_titre" class="form-label">Titre de la ressource</label>
                            <input type="text" name="titre" id="resource_titre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un titre.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="type_ressource" class="form-label">Type de ressource</label>
                            <select name="type_ressource" id="type_ressource" class="form-select" required onchange="toggleResourceFields()">
                                <option value="">Sélectionnez un type</option>
                                <option value="PDF">PDF</option>
                                <option value="Vidéo">Vidéo</option>
                                <option value="Audio">Audio</option>
                                <option value="Présentation">Présentation</option>
                                <option value="Lien">Lien</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="resource_description" class="form-label">Description</label>
                            <textarea name="description" id="resource_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="fichier_container">
                        <div class="col-md-12">
                            <label for="fichier" class="form-label">Fichier</label>
                            <input type="file" name="fichier" id="fichier" class="form-control">
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="lien_container" style="display: none;">
                        <div class="col-md-12">
                            <label for="lien_externe" class="form-label">Lien externe</label>
                            <input type="url" name="lien_externe" id="lien_externe" class="form-control" placeholder="https://...">
                            <div class="invalid-feedback">Veuillez entrer un lien valide.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="resource_est_payant" name="est_payant" onchange="toggleResourceFrais()">
                                <label class="form-check-label" for="resource_est_payant">
                                    Accès payant
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="resource_frais_container" style="display: none;">
                        <div class="col-md-12">
                            <label for="resource_idfrais" class="form-label">Frais requis</label>
                            <select name="idfrais" id="resource_idfrais" class="form-select">
                                <option value="">Sélectionnez un frais</option>
                                <?php 
                                $frais = $universite->getFraisByAcademicYear($currentYear['idannee_acad']);
                                foreach ($frais as $f): 
                                ?>
                                    <option value="<?= $f['idfrais'] ?>"><?= htmlspecialchars($f['designation']) ?> - <?= $f['montant'] ?> <?= $f['devise'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour éditer un chapitre -->
<div class="modal fade" id="editChapterModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un chapitre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editChapterForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_chapter">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    <input type="hidden" name="idpartie" id="edit_idpartie">
                    
                    <div class="row mb-3">
                        <div class="col-md-9">
                            <label for="edit_chapter_titre" class="form-label">Titre du chapitre</label>
                            <input type="text" name="titre" id="edit_chapter_titre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un titre.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_ordre" class="form-label">Ordre</label>
                            <input type="number" name="ordre" id="edit_ordre" class="form-control" value="1" min="1" required>
                            <div class="invalid-feedback">Veuillez entrer un nombre.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_chapter_description" class="form-label">Contenu :</label>
                            <textarea name="description" id="edit_chapter_description" class="form-control" rows="10"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<!-- Modal pour ajouter un support de cours -->
<div class="modal fade" id="addSupportModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un support de cours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supportForm" method="POST" action="controller/ecue_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="add_support">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    
                    <div class="mb-3">
                        <label for="support_titre" class="form-label">Titre du support</label>
                        <input type="text" name="titre" id="support_titre" class="form-control" required>
                        <div class="invalid-feedback">Veuillez entrer un titre.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="support_description" class="form-label">Description</label>
                        <textarea name="description" id="support_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="support_fichier" class="form-label">Fichier</label>
                        <input type="file" name="fichier" id="support_fichier" class="form-control" required>
                        <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="support_est_payant" name="est_payant" onchange="toggleSupportFrais()">
                            <label class="form-check-label" for="support_est_payant">
                                Accès payant
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="support_frais_container" style="display: none;">
                        <label for="support_idfrais" class="form-label">Frais requis</label>
                        <select name="idfrais" id="support_idfrais" class="form-select">
                            <option value="">Sélectionnez un frais</option>
                            <?php 
                            $frais = $universite->getFraisByAcademicYear($currentYear['idannee_acad']);
                            foreach ($frais as $f): 
                            ?>
                                <option value="<?= $f['idfrais'] ?>"><?= htmlspecialchars($f['designation']) ?> - <?= $f['montant'] ?> <?= $f['devise'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un devoir -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un devoir</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assignmentForm" method="POST" action="controller/devoir_controller.php" class="needs-validation" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="add_assignment">
                    <input type="hidden" name="idECUE" value="<?= $idEcue ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="assignment_titre" class="form-label">Titre du devoir</label>
                            <input type="text" name="titre" id="assignment_titre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez entrer un titre.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="assignment_description" class="form-label">Description</label>
                            <textarea name="description" id="assignment_description" class="form-control" rows="4" required></textarea>
                            <div class="invalid-feedback">Veuillez fournir une description.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_limite" class="form-label">Date limite</label>
                            <input type="datetime-local" name="date_limite" id="date_limite" class="form-control" required>
                            <div class="invalid-feedback">Veuillez définir une date limite.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="fichier" class="form-label">Fichier (PDF, DOCX, etc.)</label>
                            <input type="file" name="fichier" id="fichier" class="form-control" required>
                            <div class="invalid-feedback">Veuillez sélectionner un fichier.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="est_payant" name="est_payant" onchange="toggleFrais()">
                                <label class="form-check-label" for="est_payant">
                                    Accès payant
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="frais_container" style="display: none;">
                        <div class="col-md-12">
                            <label for="idFrais" class="form-label">Frais requis</label>
                            <select name="idFrais" id="idFrais" class="form-select">
                                <option value="">Sélectionnez un frais</option>
                                <?php 
                                $frais = $universite->getFraisByAcademicYear($currentYear['idannee_acad']);
                                foreach ($frais as $f): 
                                ?>
                                    <option value="<?= $f['idfrais'] ?>"><?= htmlspecialchars($f['designation']) ?> - <?= $f['montant'] ?> <?= $f['devise'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour ouvrir le modal d'ajout de ressource
function openAddResourceModal(idPartie) {
    document.getElementById('resource_idpartie').value = idPartie;
    document.getElementById('resource_titre').value = '';
    document.getElementById('resource_description').value = '';
    document.getElementById('type_ressource').selectedIndex = 0;
    document.getElementById('fichier').value = '';
    document.getElementById('lien_externe').value = '';
    document.getElementById('resource_est_payant').checked = false;
    
    toggleResourceFields();
    toggleResourceFrais();
    
    new bootstrap.Modal(document.getElementById('addResourceModal')).show();
}


// Variable pour stocker l'instance de l'éditeur
let chapterEditor = null;

document.addEventListener('DOMContentLoaded', function() {
    // Gérer l'initialisation de CKEditor lors de l'ouverture du modal
    document.getElementById('addChapterModal').addEventListener('shown.bs.modal', function() {
        // Détruire l'instance existante si elle existe
        if (chapterEditor) {
            chapterEditor.destroy();
            chapterEditor = null;
        }
        
        // Créer une nouvelle instance
        ClassicEditor
            .create(document.querySelector('#chapter_description'))
            .then(editor => {
                chapterEditor = editor;
            })
            .catch(error => {
                console.error('Erreur lors de l\'initialisation de CKEditor:', error);
            });
    });
    
    // Nettoyer l'éditeur à la fermeture du modal
    document.getElementById('addChapterModal').addEventListener('hidden.bs.modal', function() {
        if (chapterEditor) {
            chapterEditor.destroy();
            chapterEditor = null;
        }
    });
});

// Fonction pour ouvrir le modal d'édition de chapitre
function openEditChapterModal(idPartie, titre, ordre) {
    // Récupérer le contenu du chapitre via AJAX
    fetch(`controller/get_chapter.php?id=${idPartie}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.error
                });
                return;
            }
            
            // Remplir le formulaire avec les données du chapitre
            document.getElementById('edit_idpartie').value = idPartie;
            document.getElementById('edit_chapter_titre').value = titre;
            document.getElementById('edit_ordre').value = ordre;
            
            // Ouvrir le modal
            const editModal = new bootstrap.Modal(document.getElementById('editChapterModal'));
            editModal.show();
            
            // Initialiser CKEditor après l'ouverture du modal
            editModal._element.addEventListener('shown.bs.modal', function() {
                if (window.editChapterEditor) {
                    window.editChapterEditor.destroy();
                }
                
                ClassicEditor
                    .create(document.querySelector('#edit_chapter_description'))
                    .then(editor => {
                        window.editChapterEditor = editor;
                        editor.setData(data.description);
                    })
                    .catch(error => {
                        console.error(error);
                    });
            }, { once: true });
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur est survenue lors de la récupération des détails du chapitre.'
            });
        });
}

// Fonction pour ouvrir le modal d'édition de devoir
function openEditAssignmentModal(idDevoir) {
    // Rediriger vers la page d'édition du devoir
    window.location.href = `?view=enseignement/devoir.edit&id=${idDevoir}`;
}

// Fonction pour afficher/masquer les champs de ressource selon le type
function toggleResourceFields() {
    const typeRessource = document.getElementById('type_ressource').value;
    const fichierContainer = document.getElementById('fichier_container');
    const lienContainer = document.getElementById('lien_container');
    const fichierInput = document.getElementById('fichier');
    const lienInput = document.getElementById('lien_externe');
    
    if (typeRessource === 'Lien') {
        fichierContainer.style.display = 'none';
        lienContainer.style.display = 'block';
        fichierInput.removeAttribute('required');
        lienInput.setAttribute('required', 'required');
    } else {
        fichierContainer.style.display = 'block';
        lienContainer.style.display = 'none';
        fichierInput.setAttribute('required', 'required');
        lienInput.removeAttribute('required');
    }
}

// Fonction pour afficher/masquer le champ de frais pour les ressources
function toggleResourceFrais() {
    const estPayant = document.getElementById('resource_est_payant').checked;
    const fraisContainer = document.getElementById('resource_frais_container');
    const fraisSelect = document.getElementById('resource_idfrais');
    
    if (estPayant) {
        fraisContainer.style.display = 'block';
        fraisSelect.setAttribute('required', 'required');
    } else {
        fraisContainer.style.display = 'none';
        fraisSelect.removeAttribute('required');
    }
}

// Fonction pour afficher/masquer le champ de frais pour les supports
function toggleSupportFrais() {
    const estPayant = document.getElementById('support_est_payant').checked;
    const fraisContainer = document.getElementById('support_frais_container');
    const fraisSelect = document.getElementById('support_idfrais');
    
    if (estPayant) {
        fraisContainer.style.display = 'block';
        fraisSelect.setAttribute('required', 'required');
    } else {
        fraisContainer.style.display = 'none';
        fraisSelect.removeAttribute('required');
    }
}

// Fonction pour afficher/masquer le champ de frais pour les devoirs
function toggleFrais() {
    const estPayant = document.getElementById('est_payant').checked;
    const fraisContainer = document.getElementById('frais_container');
    const fraisSelect = document.getElementById('idFrais');
    
    if (estPayant) {
        fraisContainer.style.display = 'block';
        fraisSelect.setAttribute('required', 'required');
    } else {
        fraisContainer.style.display = 'none';
        fraisSelect.removeAttribute('required');
    }
}

// Initialisation des validations de formulaire Bootstrap
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php include "./views/include/footer.php"; ?>
