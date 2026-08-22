<?php
include "./views/include/header.php";

// Vérifier si l'utilisateur est connecté et a les droits d'administration
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../login');
    exit();
}

$db = Connexion::getInstance()->getPDO();
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les tentatives de fraude
$stmt = $db->prepare("
    SELECT tf.*, 
           CASE 
               WHEN tf.type_seance = 'cours' THEN sc.titre
               WHEN tf.type_seance = 'labo' THEN sl.titre
               ELSE 'Inconnu'
           END as seance_titre,
           e.noms as nom_etudiant,
           e.matricule as matricule_etudiant
    FROM tentatives_fraude_presence tf
    LEFT JOIN seance_cours sc ON tf.type_seance = 'cours' AND CONVERT(tf.idseance USING utf8mb4) = CONVERT(sc.idseance USING utf8mb4)
    LEFT JOIN seance_labo sl ON tf.type_seance = 'labo' AND CONVERT(tf.idseance USING utf8mb4) = CONVERT(sl.idseance_labo USING utf8mb4)
    LEFT JOIN etudiant e ON CONVERT(tf.matricule_tente USING utf8mb4) = CONVERT(e.matricule USING utf8mb4)
    ORDER BY tf.date_tentative DESC
");

$stmt->execute();
$fraudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Gestion des fraudes de présence</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard">Accueil</a></li>
                    <li class="breadcrumb-item">Administration</li>
                    <li class="breadcrumb-item active">Fraudes de présence</li>
                </ol>
            </nav>
        </div>
        
        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tentatives de fraude détectées</h5>
                            
                            <?php if (empty($fraudes)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucune tentative de fraude n'a été détectée.
                                </div>
                            <?php else: ?>
                                <!-- Contrôles de recherche et pagination -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" id="searchFraude" class="form-control" placeholder="Rechercher par matricule, nom, séance...">
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <div class="d-flex justify-content-end align-items-center">
                                            <label for="entriesPerPage" class="me-2">Afficher</label>
                                            <select id="entriesPerPage" class="form-select form-select-sm" style="width: auto;">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>
                                            <span class="ms-2">entrées</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tableFraudes">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Matricule</th>
                                                <th>Étudiant</th>
                                                <th>Séance</th>
                                                <th>Type</th>
                                                <th>Adresse IP</th>
                                                <th>Détails</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fraudes as $fraude): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($fraude['date_tentative'])) ?></td>
                                                    <td><?= htmlspecialchars($fraude['matricule_tente']) ?></td>
                                                    <td><?= htmlspecialchars($fraude['nom_etudiant'] ?? 'Inconnu') ?></td>
                                                    <td><?= htmlspecialchars($fraude['seance_titre']) ?></td>
                                                    <td>
                                                        <?php if ($fraude['type_seance'] === 'cours'): ?>
                                                            <span class="badge bg-primary">Cours</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">Laboratoire</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($fraude['ip_address']) ?></td>
                                                    <td><?= htmlspecialchars($fraude['details']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div id="tableInfo" class="dataTables_info">
                                            Affichage de <span id="startEntry">1</span> à <span id="endEntry">10</span> sur <span id="totalEntries"><?= count($fraudes) ?></span> entrées
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end" id="pagination">
                                                <!-- Les boutons de pagination seront générés par JavaScript -->
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                
                                <!-- Message pour aucun résultat de recherche -->
                                <div id="noResultsMessage" class="alert alert-info mt-3 d-none">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Aucun résultat ne correspond à votre recherche.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Inclure le footer -->
    <?php include_once "./views/include/footer.php"; ?>

    <!-- Script pour la recherche dynamique et la pagination -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Éléments DOM
        const searchInput = document.getElementById('searchFraude');
        const entriesPerPageSelect = document.getElementById('entriesPerPage');
        const table = document.getElementById('tableFraudes');
        const tbody = table ? table.querySelector('tbody') : null;
        const rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
        const noResultsMessage = document.getElementById('noResultsMessage');
        const pagination = document.getElementById('pagination');
        const startEntrySpan = document.getElementById('startEntry');
        const endEntrySpan = document.getElementById('endEntry');
        const totalEntriesSpan = document.getElementById('totalEntries');
        
        // Variables de pagination
        let currentPage = 1;
        let entriesPerPage = 10;
        let filteredRows = [...rows];
        
        // Initialisation
        if (rows.length > 0) {
            // Fonction pour filtrer les lignes
            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                
                filteredRows = rows.filter(row => {
                    const text = row.textContent.toLowerCase();
                    return text.includes(searchTerm);
                });
                
                // Afficher ou masquer le message "Aucun résultat"
                if (noResultsMessage) {
                    if (filteredRows.length === 0) {
                        noResultsMessage.classList.remove('d-none');
                    } else {
                        noResultsMessage.classList.add('d-none');
                    }
                }
                
                // Mettre à jour la pagination
                currentPage = 1;
                updatePagination();
                displayRows();
            }
            
            // Fonction pour afficher les lignes de la page actuelle
            function displayRows() {
                const startIndex = (currentPage - 1) * entriesPerPage;
                const endIndex = Math.min(startIndex + entriesPerPage, filteredRows.length);
                
                // Masquer toutes les lignes
                rows.forEach(row => {
                    row.style.display = 'none';
                });
                
                // Afficher uniquement les lignes de la page actuelle
                for (let i = startIndex; i < endIndex; i++) {
                    filteredRows[i].style.display = '';
                }
                
                // Mettre à jour les informations d'affichage
                if (startEntrySpan && endEntrySpan && totalEntriesSpan) {
                    if (filteredRows.length > 0) {
                        startEntrySpan.textContent = startIndex + 1;
                        endEntrySpan.textContent = endIndex;
                    } else {
                        startEntrySpan.textContent = '0';
                        endEntrySpan.textContent = '0';
                    }
                    totalEntriesSpan.textContent = filteredRows.length;
                }
            }
            
            // Fonction pour mettre à jour la pagination
            function updatePagination() {
                if (!pagination) return;
                
                const totalPages = Math.ceil(filteredRows.length / entriesPerPage);
                let paginationHTML = '';
                
                // Bouton précédent
                paginationHTML += `
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Précédent">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                `;
                
                // Pages
                const maxVisiblePages = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                
                if (endPage - startPage + 1 < maxVisiblePages) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }
                
                if (startPage > 1) {
                    paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                    if (startPage > 2) {
                        paginationHTML += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
                    }
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    paginationHTML += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `;
                }
                
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        paginationHTML += `<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`;
                    }
                    paginationHTML += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
                }
                
                // Bouton suivant
                paginationHTML += `
                    <li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Suivant">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                `;
                
                pagination.innerHTML = paginationHTML;
                
                // Ajouter les écouteurs d'événements aux boutons de pagination
                pagination.querySelectorAll('.page-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = parseInt(this.getAttribute('data-page'));
                        if (!isNaN(page) && page !== currentPage && page > 0 && page <= totalPages) {
                            currentPage = page;
                            displayRows();
                            updatePagination();
                        }
                    });
                });
            }
            
            // Écouteurs d'événements
            if (searchInput) {
                searchInput.addEventListener('keyup', filterRows);
            }
            
            if (entriesPerPageSelect) {
                entriesPerPageSelect.addEventListener('change', function() {
                    entriesPerPage = parseInt(this.value);
                    currentPage = 1;
                    updatePagination();
                    displayRows();
                });
            }
            
            // Initialisation
            updatePagination();
            displayRows();
        }
    });
    </script>
</body>
</html>
