<?php include "./views/include/header.php"; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>RAPPORTS FINANCIERS UNIVERSITAIRES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Rapports</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Introduction -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Centre de rapports financiers universitaires</h5>
                        <p>Bienvenue dans le centre de rapports financiers. Cette section vous permet de générer et consulter divers rapports pour analyser les paiements des étudiants, le suivi des frais académiques, le budget et la trésorerie de l'institution.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports sur les frais et paiements -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Paiements et frais académiques</h3>
            </div>

            <!-- Journal des paiements -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info-light">
                                <i class="bi bi-receipt text-info"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Journal des paiements</h5>
                                <p class="text-muted small pt-2">Historique complet des paiements reçus</p>
                            </div>
                        </div>
                        <p class="mt-3">Consultez l'ensemble des paiements effectués par les étudiants avec filtrage par période, mode de paiement et filière.</p>
                        <div class="text-end">
                            <a href="finance/rapport/paiements.journal" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- État des frais par promotion -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success-light">
                                <i class="bi bi-mortarboard text-success"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">État des frais par promotion</h5>
                                <p class="text-muted small pt-2">Suivi des frais académiques par niveau</p>
                            </div>
                        </div>
                        <p class="mt-3">Visualisez l'état de paiement des frais académiques par promotion ou niveau d'études avec taux de recouvrement.</p>
                        <div class="text-end">
                            <a href="finance/frais.promotion" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <!-- Situation financière des étudiants -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning-light">
                                <i class="bi bi-person-badge text-warning"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Situation financière des étudiants</h5>
                                <p class="text-muted small pt-2">Analyse par étudiant</p>
                            </div>
                        </div>
                        <p class="mt-3">Examinez la situation financière détaillée de chaque étudiant avec historique des paiements et soldes restants.</p>
                        <div class="text-end">
                            <a href="finance/rapport/recherche_situation" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Échéanciers et tranches -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary-light">
                                <i class="bi bi-calendar-check text-primary"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Suivi des échéanciers</h5>
                                <p class="text-muted small pt-2">État des paiements échelonnés</p>
                            </div>
                        </div>
                        <p class="mt-3">Suivez l'état des paiements échelonnés et identifiez les tranches en retard ou à échéance prochaine.</p>
                        <div class="text-end">
                            <a href="finance/rapport/echeanciers" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports de gestion budgétaire -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Gestion budgétaire</h3>
            </div>

            <!-- Suivi budgétaire -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning-light">
                                <i class="bi bi-graph-up-arrow text-warning"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Suivi budgétaire</h5>
                                <p class="text-muted small pt-2">Analyse prévu vs réalisé</p>
                            </div>
                        </div>
                        <p class="mt-3">Visualisez la performance budgétaire avec une comparaison détaillée entre les montants prévus et les montants réalisés.</p>
                        <div class="text-end">
                            <a href="finance/config_budget" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse par catégorie budgétaire -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary-light">
                                <i class="bi bi-clipboard-data text-primary"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Analyse par catégorie</h5>
                                <p class="text-muted small pt-2">Détail des dépenses et recettes</p>
                            </div>
                        </div>
                        <p class="mt-3">Analysez la répartition des dépenses et recettes par catégorie budgétaire pour identifier les tendances.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse des besoins -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger-light">
                                <i class="bi bi-file-earmark-text text-danger"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">État des besoins</h5>
                                <p class="text-muted small pt-2">Suivi des demandes budgétaires</p>
                            </div>
                        </div>
                        <p class="mt-3">Suivez l'état d'avancement des demandes de dépenses et états de besoin soumis par les départements.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports de trésorerie -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Gestion de trésorerie</h3>
            </div>

            <!-- Position de trésorerie -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-secondary-light">
                                <i class="bi bi-wallet2 text-secondary"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Position de trésorerie</h5>
                                <p class="text-muted small pt-2">État global des liquidités</p>
                            </div>
                        </div>
                        <p class="mt-3">Obtenez une vue consolidée des soldes de vos comptes bancaires et caisses à une date donnée.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transactions financières -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info-light">
                                <i class="bi bi-arrow-left-right text-info"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Journal des transactions</h5>
                                <p class="text-muted small pt-2">Historique des mouvements financiers</p>
                            </div>
                        </div>
                        <p class="mt-3">Consultez l'ensemble des transactions financières (recettes, dépenses, transferts) avec filtres avancés.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapprochement bancaire -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success-light">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Rapprochements bancaires</h5>
                                <p class="text-muted small pt-2">Suivi des réconciliations</p>
                            </div>
                        </div>
                        <p class="mt-3">Suivez l'état de vos rapprochements bancaires et identifiez les opérations en suspens ou non pointées.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                            </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports de gestion financière -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Avances et gestion financière</h3>
            </div>

            <!-- Avances au personnel -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning-light">
                                <i class="bi bi-people text-warning"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Avances au personnel académique</h5>
                                <p class="text-muted small pt-2">Suivi des avances et remboursements</p>
                            </div>
                        </div>
                        <p class="mt-3">Suivez les avances accordées au personnel enseignant et administratif et l'état des remboursements en cours.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rémunérations -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary-light">
                                <i class="bi bi-cash-stack text-primary"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Rémunérations académiques</h5>
                                <p class="text-muted small pt-2">Suivi des paiements au personnel</p>
                            </div>
                        </div>
                        <p class="mt-3">Analysez les rémunérations versées au personnel enseignant et administratif par catégorie et période.</p>
                        <div class="text-end">
                            <a href="" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableaux de bord et indicateurs -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Tableaux de bord et indicateurs</h3>
            </div>

            <!-- Dashboard financier -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger-light">
                                <i class="bi bi-speedometer2 text-danger"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Tableau de bord financier</h5>
                                <p class="text-muted small pt-2">Indicateurs clés de performance</p>
                            </div>
                        </div>
                        <p class="mt-3">Visualisez les principaux indicateurs financiers sur un tableau de bord interactif et personnalisable.</p>
                        <div class="text-end">
                            <a href="finance/dashboard" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analyse comparative -->
            <div class="col-lg-6 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info-light">
                                <i class="bi bi-bar-chart text-info"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Analyse comparative</h5>
                                <p class="text-muted small pt-2">Comparaison entre périodes académiques</p>
                            </div>
                        </div>
                        <p class="mt-3">Comparez les données financières entre différentes années académiques pour identifier les tendances et variations.</p>
                        <div class="text-end">
                            <a href="finance/rapport/comparatif" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports par faculté et consolidation -->
        <div class="row">
            <div class="col-12">
                <h3 class="mt-4 mb-3">Rapports par faculté et analyse structurelle</h3>
            </div>

            <!-- Rapports par faculté -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success-light">
                                <i class="bi bi-building text-success"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Rapports par faculté</h5>
                                <p class="text-muted small pt-2">Analyse financière par entité</p>
                            </div>
                        </div>
                        <p class="mt-3">Consultez les rapports financiers spécifiques à chaque faculté avec ventilation par filière et département.</p>
                        <div class="text-end">
                            <a href="finance/rapport/faculte" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapports par section -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning-light">
                                <i class="bi bi-diagram-3 text-warning"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Rapports par section</h5>
                                <p class="text-muted small pt-2">Analyse par filière académique</p>
                            </div>
                        </div>
                        <p class="mt-3">Visualisez la performance financière de chaque section ou filière de votre institution.</p>
                        <div class="text-end">
                            <a href="finance/rapport/sections" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapports consolidés -->
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary-light">
                                <i class="bi bi-pie-chart text-primary"></i>
                            </div>
                            <div class="ps-3">
                                <h5 class="card-title mb-0">Rapports consolidés</h5>
                                <p class="text-muted small pt-2">Vue globale de l'institution</p>
                            </div>
                        </div>
                        <p class="mt-3">Obtenez une vision consolidée des données financières de l'ensemble de l'institution universitaire.</p>
                        <div class="text-end">
                            <a href="finance/rapport/consolide" class="btn btn-sm btn-primary">Consulter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rapports personnalisés -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rapports personnalisés</h5>
                        <p>Besoin d'un rapport spécifique qui n'est pas disponible dans la liste ci-dessus ? Créez un rapport personnalisé en sélectionnant les données qui vous intéressent.</p>
                        <div class="text-end">
                            <a href="finance/rapport/custom" class="btn btn-primary">
                                <i class="bi bi-gear-fill"></i> Configurer un rapport
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aide et ressources -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Aide et informations sur les rapports</h5>
                        
                        <div class="accordion" id="accordionRapportsHelp">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                        Comment utiliser les rapports financiers universitaires ?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionRapportsHelp">
                                    <div class="accordion-body">
                                        <ul>
                                            <li><strong>Rapports de paiements :</strong> Consultez régulièrement pour suivre les recouvrements des frais académiques</li>
                                            <li><strong>Rapports budgétaires :</strong> Analysez mensuellement pour évaluer la performance par rapport aux objectifs</li>
                                            <li><strong>Situation des étudiants :</strong> Vérifiez avant les périodes d'examens pour identifier les arriérés</li>
                                            <li><strong>Position de trésorerie :</strong> Consultez hebdomadairement pour planifier les besoins en liquidités</li>
                                            <li><strong>Tableaux de bord :</strong> Utilisez pour les présentations aux conseils d'administration</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        Paramètres de filtrage disponibles
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionRapportsHelp">
                                    <div class="accordion-body">
                                        <p>Chaque rapport propose différentes options de filtrage :</p>
                                        <ul>
                                            <li><strong>Période académique :</strong> Sélection par année académique ou semestre</li>
                                            <li><strong>Structure :</strong> Filtrage par faculté, section ou promotion</li>
                                            <li><strong>Catégorie de frais :</strong> Sélection par type de frais académiques</li>
                                            <li><strong>Statut de paiement :</strong> Filtrage par état (complet, partiel, impayé)</li>
                                            <li><strong>Cycle d'études :</strong> Licence, Master ou Doctorat</li>
                                            <li><strong>Mode de paiement :</strong> Filtrage par méthode de règlement</li>
                                        </ul>
                                        <p>Ces filtres peuvent être combinés pour obtenir des rapports précis répondant à vos besoins d'analyse.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                        Options d'exportation et d'impression
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionRapportsHelp">
                                    <div class="accordion-body">
                                        <p>Tous les rapports proposent plusieurs options d'exportation :</p>
                                        <ul>
                                            <li><strong>PDF :</strong> Idéal pour l'impression et l'archivage officiel des documents</li>
                                            <li><strong>Excel :</strong> Pour manipuler les données et effectuer des analyses supplémentaires</li>
                                            <li><strong>CSV :</strong> Pour l'intégration avec d'autres systèmes de gestion</li>
                                            <li><strong>Impression directe :</strong> Pour obtenir une copie papier immédiate</li>
                                        </ul>
                                        <p>Les rapports exportés incluent automatiquement l'en-tête de l'institution, les informations de filtrage, la date de génération et les totaux calculés.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingFour">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                        Programmer des rapports récurrents
                                    </button>
                                </h2>
                                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionRapportsHelp">
                                    <div class="accordion-body">
                                        <p>Pour les rapports que vous consultez régulièrement, utilisez la fonction de programmation :</p>
                                        <ol>
                                            <li>Configurez le rapport avec les filtres souhaités</li>
                                            <li>Cliquez sur "Enregistrer ce rapport"</li>
                                            <li>Définissez la fréquence (quotidienne, hebdomadaire, mensuelle)</li>
                                            <li>Choisissez le format d'envoi (email, stockage dans documents)</li>
                                            <li>Sélectionnez les destinataires si nécessaire</li>
                                        </ol>
                                        <p>Les rapports programmés seront générés automatiquement et distribués selon vos préférences, facilitant le suivi financier régulier.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingFive">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                        Rapports pour la direction académique
                                    </button>
                                </h2>
                                <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#accordionRapportsHelp">
                                    <div class="accordion-body">
                                        <p>Les rapports spécifiques pour la direction académique incluent :</p>
                                        <ul>
                                            <li><strong>Taux de recouvrement :</strong> Pourcentage de frais recouvrés par promotion</li>
                                            <li><strong>Étudiants en règle :</strong> Liste des étudiants ayant soldé leurs frais académiques</li>
                                            <li><strong>Étudiants en défaut :</strong> Liste des étudiants avec des soldes impayés</li>
                                            <li><strong>Impact des exemptions :</strong> Analyse des exemptions et réductions accordées</li>
                                            <li><strong>Évolution annuelle :</strong> Comparaison des recouvrements sur plusieurs années académiques</li>
                                        </ul>
                                        <p>Ces rapports sont particulièrement utiles pour les délibérations et les décisions d'admission aux examens.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exportation et planification -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Exportation groupée et planification</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6><i class="bi bi-file-earmark-arrow-down me-2"></i>Exportation groupée</h6>
                                    <p>Exportez plusieurs rapports simultanément dans un format unifié pour les réunions de direction.</p>
                                    <a href="finance/rapport/export-groupe" class="btn btn-sm btn-outline-primary">Configurer exportation</a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6><i class="bi bi-clock-history me-2"></i>Rapports programmés</h6>
                                    <p>Configurez la génération automatique de rapports à intervalles réguliers.</p>
                                    <a href="finance/rapport/programme" class="btn btn-sm btn-outline-primary">Gérer planification</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Scripts spécifiques aux rapports -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes au survol
    const reportCards = document.querySelectorAll('.card');
    reportCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 20px rgba(0,0,0,0.1)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 5px 10px rgba(0,0,0,0.05)';
            this.style.transition = 'all 0.3s ease';
        });
    });
    
    // Initialiser les tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Filtrage rapide des rapports
    const searchInput = document.getElementById('rapportSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.card');
            
            cards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const description = card.querySelector('p.mt-3').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.closest('.col-lg-4, .col-lg-6').style.display = '';
                } else {
                    card.closest('.col-lg-4, .col-lg-6').style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>


