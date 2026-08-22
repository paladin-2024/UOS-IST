<?php
include "./views/include/header.php";
$structure = new Structure();
$superUser = new SuperUser();
$universite = new Universite();
$config = $universite->getConfigurationUniversite();

// Vérifier si c'est la première connexion de l'utilisateurs
if ($superUser->isFirstLogin($_SESSION['id'])) {
    // Rediriger vers la page de changement de mot de passe
    echo "<meta http-equiv='refresh' content='0;URL=password'>";
    exit();
}

// Get statistics
$statistics = $structure->getStatistics();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
    <h1><?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#"><?= mb_strtoupper($nomRole) ?></a></li>
                <li class="breadcrumb-item active">Accueil</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Bienvenu -->
            <div class="col-md-12">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Bonjour, <strong><?= mb_strtoupper($nomU) ?></strong> <i class="bi bi-emoji-smile-fill"></i></h5>
                        <p class="card-text">Bienvenu dans votre session de travail.</p>
                    </div>
                </div>
            </div><!-- End Bienvenue -->

            <div class="col-lg-12">
                <div class="row">
                    <!-- Statistique 1 -->
                    <div class="col-md-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Utilisateurs</h5>
                                <div class="d-flex align-items-center">
                                    <div class="icon text-primary"><i class="bi bi-people"></i></div>
                                    <div class="ps-3">
                                        <h6>+<?php echo htmlspecialchars($statistics['users']); ?></h6>
                                        <span class="text-success small">Utilisateurs</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Statistique 1 -->

                    <!-- Statistique 2 -->
                    <div class="col-md-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Campus</h5>
                                <div class="d-flex align-items-center">
                                    <div class="icon text-success"><i class="bi bi-file-earmark-check"></i></div>
                                    <div class="ps-3">
                                        <h6>+<?php echo htmlspecialchars($statistics['structures']); ?></h6>
                                        <span class="text-success small">Campus</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Statistique 2 -->

                    <!-- Statistique 3 -->
                    <div class="col-md-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Personnels Actifs</h5>
                                <div class="d-flex align-items-center">
                                    <div class="icon text-warning"><i class="bi bi-person-check"></i></div>
                                    <div class="ps-3">
                                        <h6>+<?php echo htmlspecialchars($statistics['active_personnel']); ?></h6>
                                        <span class="text-warning small">Personnels Actifs</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Statistique 3 -->

                    <!-- Statistique 4 -->
                    <div class="col-md-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Fonctionnalités</h5>
                                <div class="d-flex align-items-center">
                                    <div class="icon text-warning"><i class="bi bi-window-stack"></i></div>
                                    <div class="ps-3">
                                        <h6>+<?php echo htmlspecialchars($statistics['permissions']); ?></h6>
                                        <span class="text-warning small">Permissions</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- End Statistique 4 -->
                </div>
            </div><!-- End Statistiques rapides -->

            <!-- Section à propos -->
            <div class="col-lg-12 mt-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">À propos de <?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?></h5>
                        <p>
                        <?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?> est un système de gestion universitaire complet conçu pour optimiser l'administration des établissements d'enseignement supérieur. Notre plateforme allie efficacité, simplicité et conformité académique pour répondre aux besoins spécifiques des universités, écoles et centres de formation.
                        </p>
                        <p>
                        Avec <?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?>, vous pouvez gérer l'ensemble de vos opérations académiques avec des outils avancés, tels que :
                        </p>
                        <ul>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion des Étudiants :</strong> Inscription, suivi des parcours académiques, gestion des dossiers individuels et statistiques de performance.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion des Enseignants :</strong> Suivi des ressources pédagogiques, affectation des cours, gestion des horaires et évaluation des enseignants.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion des Cours et Programmes :</strong> Création et organisation des programmes d'études, gestion des unités d'enseignement (UE) et éléments constitutifs (ECUE).</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion des Notes et Évaluations :</strong> Saisie des notes, calcul des moyennes, génération de bulletins et relevés de notes.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion des Examens :</strong> Planification des examens, gestion des salles, surveillance et archivage des résultats.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion Administrative :</strong> Suivi des inscriptions, gestion des promotions, traitement des demandes spéciales et archivage des documents.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Tableau de Bord :</strong> Visualisation en temps réel des indicateurs académiques clés (KPI), alertes sur les performances et graphiques de suivi pédagogique.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Rapports et Statistiques :</strong> Génération de rapports détaillés sur les performances étudiantes, analyses statistiques et prévisions pour une prise de décision éclairée.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i><strong>Gestion Financière Étudiante :</strong> Suivi des frais de scolarité, gestion des bourses, paiements et dettes étudiantes.</li>
                        </ul>
                        <p>
                        Notre objectif est d'offrir une plateforme complète et flexible, capable de s'adapter à l'évolution des normes académiques et pédagogiques, afin de garantir une gestion optimale de l'établissement.
                        </p>
                        <p>
                        En intégrant <?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?> à vos processus académiques, vous bénéficiez non seulement d'une solution robuste et sécurisée, mais aussi d'une équipe dédiée à l'amélioration continue de l'expérience éducative.
                        </p>
                    </div>
                </div>
            </div><!-- End Section à propos -->

        </div>
    </section>
</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>