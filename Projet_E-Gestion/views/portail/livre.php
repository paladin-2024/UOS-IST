<?php
require_once dirname(__DIR__) . '/../config/Connexion.php';
require_once dirname(__DIR__) . '/../models/Universite.php';

$universite = new Universite();

// Récupérer les filtres
$departementId = isset($_GET['departement']) ? intval($_GET['departement']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Préparer les filtres pour la requête
$filters = [
    'type_document' => 'Livre',
    'est_public' => 1,
    'statut' => 'Validé'
];

if ($departementId > 0) $filters['departement_id'] = $departementId;
if ($anneeId > 0) $filters['annee_academique_id'] = $anneeId;

// Récupérer les livres
$livres = $universite->getTravaux($search, $filters);

// Récupérer les données pour les filtres
$departements = $universite->getAllDepartments();
$academicYears = $universite->getAcademicYears();

// Calculer les statistiques
$stats = [
    'total_livres' => count($livres),
    'total_auteurs' => count(array_unique(array_column($livres, 'nom_auteur'))),
    'total_consultations' => array_sum(array_column($livres, 'nb_consultations')),
    'total_departements' => count(array_unique(array_column($livres, 'departement_id')))
];

include 'header2.php';
?>

<main class="py-5">
    <!-- Fil d'Ariane -->
    <div class="container mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item active">Livres</li>
            </ol>
        </nav>
    </div>

    <!-- En-tête de la page -->
    <div class="bg-success text-white py-4 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">Livres</h1>
                    <p class="lead mb-0">Ouvrages et publications académiques</p>
                </div>
                <div class="col-md-4">
                    <div class="row text-center g-2">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_livres']) ?></div>
                                <small>Livres</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <div class="h3 mb-0"><?= number_format($stats['total_auteurs']) ?></div>
                                <small>Auteurs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filtres de recherche -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" placeholder="Titre, auteur, mots-clés..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Département</label>
                        <select name="departement" class="form-select">
                            <option value="">Tous les départements</option>
                            <?php foreach ($departements as $dept): ?>
                                <option value="<?= $dept['iddepartement'] ?>" <?= $departementId === $dept['iddepartement'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['designationDepartement']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Année académique</label>
                        <select name="annee" class="form-select">
                            <option value="">Toutes les années</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId === $year['idannee_acad'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-search me-2"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des livres -->
        <div class="row g-4">
            <?php if (!empty($livres)): ?>
                <?php foreach ($livres as $livre): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-book me-2"></i>
                                Livre
                            </div>

                            <div class="card-body">
                                <!-- Titre -->
                                <h5 class="card-title mb-3">
                                    <a href="voir_travail?id=<?= $livre['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($livre['titre']) ?>
                                    </a>
                                </h5>

                                <!-- Auteur(s) -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user-edit text-muted me-2"></i>
                                    <span>
                                        <?= htmlspecialchars($livre['nom_auteur']) ?>
                                    </span>
                                </div>

                                <!-- Spécialisation -->
                                <?php if (!empty($livre['specialisation'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-bookmark text-muted me-2"></i>
                                    <span class="small">
                                        <?= htmlspecialchars($livre['specialisation']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <!-- Département et année -->
                                <div class="small text-muted mb-3">
                                    <i class="fas fa-university me-2"></i>
                                    <?= htmlspecialchars($livre['designationDepartement']) ?>
                                    <br>
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?= $livre['annee'] ?>
                                </div>

                                <!-- Résumé -->
                                <p class="card-text small">
                                    <?= htmlspecialchars(substr($livre['resume'], 0, 100)) ?>...
                                </p>

                                <!-- Mots-clés -->
                                <?php if (!empty($livre['mots_cles'])): ?>
                                <div class="mt-2">
                                    <?php foreach (explode(',', $livre['mots_cles']) as $motCle): ?>
                                        <span class="badge bg-light text-dark me-1">
                                            <?= trim(htmlspecialchars($motCle)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <a href="voir_travail?id=<?= $livre['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-eye me-1"></i> Consulter
                                </a>
                                <div class="text-muted small">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    <?= number_format($livre['nb_consultations']) ?> vues
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun livre ne correspond à vos critères de recherche.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-5px);
}

.card-title {
    font-size: 1.1rem;
    line-height: 1.4;
    height: 3.1em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
}

.bg-success {
    background: linear-gradient(135deg, var(--bs-success) 0%, #198754 100%);
}
</style>

<?php include 'footer.php'; ?>