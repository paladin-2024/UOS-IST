<?php
include "./views/include/header.php";
$db = Connexion::getInstance()->getPDO();

// Récupération des catégories
$query = "SELECT * FROM categorie_produit ORDER BY libelle_categorie";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>CATÉGORIES DE PRODUITS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item">Stock</li>
                <li class="breadcrumb-item active">Catégories</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <!-- Liste des catégories -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des catégories</h5>
                        
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Code</th>
                                    <th scope="col">Libellé</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($categories as $categorie) {
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$categorie['code_categorie']}</td>
                                        <td>{$categorie['libelle_categorie']}</td>
                                        <td>" . (empty($categorie['description']) ? '-' : $categorie['description']) . "</td>
                                        <td>
                                            <button onclick='editCategorie(" . json_encode($categorie) . ")' class='btn btn-sm btn-warning'>
                                                <i class='bi bi-pencil-square'></i>
                                            </button>
                                            <button onclick='confirmDeleteCategorie({$categorie['id_categorie']})' class='btn btn-sm btn-danger'>
                                                <i class='bi bi-trash'></i>
                                            </button>
                                        </td>
                                    </tr>";
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Formulaire d'ajout/modification -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title" id="formTitle">Ajouter une catégorie</h5>
                        
                        <form id="categorieForm" action="controller/create_categorie.php" method="POST" class="row g-3 needs-validation" novalidate>
                            <input type="hidden" id="id_categorie" name="id_categorie" value="">
                            
                            <div class="col-12">
                                <label for="code_categorie" class="form-label">Code catégorie</label>
                                <input type="text" class="form-control" id="code_categorie" name="code_categorie" required>
                                <div class="invalid-feedback">Veuillez saisir un code pour la catégorie.</div>
                            </div>
                            
                            <div class="col-12">
                                <label for="libelle_categorie" class="form-label">Libellé</label>
                                <input type="text" class="form-control" id="libelle_categorie" name="libelle_categorie" required>
                                <div class="invalid-feedback">Veuillez saisir un libellé pour la catégorie.</div>
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="button" id="resetFormBtn" class="btn btn-secondary">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<script>
    // Fonction pour éditer une catégorie
    function editCategorie(categorie) {
        // Changer le titre du formulaire
        document.getElementById('formTitle').textContent = 'Modifier une catégorie';
        
        // Remplir le formulaire avec les données de la catégorie
        document.getElementById('id_categorie').value = categorie.id_categorie;
        document.getElementById('code_categorie').value = categorie.code_categorie;
        document.getElementById('libelle_categorie').value = categorie.libelle_categorie;
        document.getElementById('description').value = categorie.description || '';
        
        // Changer l'action du formulaire
        document.getElementById('categorieForm').action = 'controller/update_categorie.php';
        
        // Faire défiler jusqu'au formulaire
        document.getElementById('categorieForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Réinitialiser le formulaire
    document.getElementById('resetFormBtn').addEventListener('click', function() {
        document.getElementById('formTitle').textContent = 'Ajouter une catégorie';
        document.getElementById('categorieForm').reset();
        document.getElementById('id_categorie').value = '';
        document.getElementById('categorieForm').action = 'controller/create_categorie.php';
    });
    
    // Confirmation de suppression
    function confirmDeleteCategorie(idCategorie) {
        Swal.fire({
            title: 'Êtes-vous sûr?',
            text: "Cette action ne peut pas être annulée!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_categorie.php?id=' + idCategorie;
            }
        });
    }
    
    // Validation du formulaire
    (function () {
        'use strict'
        
        var forms = document.querySelectorAll('.needs-validation')
        
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
