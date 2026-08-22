<?php
include "./views/include/header.php";
$agent = new Agent();
$structure = new Structure();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>AGENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Agents</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table agents -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des agents
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="grh/agent.edit">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Noms</th>
                                        <th scope="col">Lieu de Naissance</th>
                                        <th scope="col">Date de Naissance</th>
                                        <th scope="col">Code Pointeuse</th>
                                        <th scope="col">Photo</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $listeAgent = $agent->getAgents($search);
                                    $i = 1;

                                    foreach ($listeAgent as $l) {
                                        $ver1 = $structure->getUserPermissionStructure($_SESSION['id'], $l['idStructure']);
                                        $dateN=date('d/m/Y',strtotime($l['dateNaissance']));
                                        $picture=$l['photo'];
                                        if ($ver1->fetch()) {
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$l['noms']}</td>
                                                <td>{$l['lieuNaissance']}</td>
                                                <td>{$dateN}</td>
                                                <td>{$l['codeAgent']}</td>
                                                <td><img src='uploads/agents/{$picture}' height='50px' width='50px' alt='No Photo' class='rounded' onclick='openModal(\"uploads/agents/{$picture}\")'/></td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editAgent(
                                                        {$l['idAgent']}, 
                                                        \"{$l['noms']}\",
                                                        \"{$l['lieuNaissance']}\",
                                                        \"{$l['dateNaissance']}\",
                                                        \"{$l['sexe']}\",
                                                        \"{$l['etatCivil']}\",
                                                        \"{$l['niveauEtude']}\",
                                                        \"{$l['telephone']}\",
                                                        \"{$l['email']}\",
                                                        \"{$l['codeAgent']}\",
                                                        \"{$l['photo']}\",
                                                        {$l['idStructure']},
                                                        \"{$l['type_agent']}\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                </td>
                                            </tr>";
                                            $i++;
                                        }
                                        
                                    }
                                    ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour afficher l'image en grand -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Photo de l'Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Image" class="img-fluid"/>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier un agent -->
<div class="modal fade" id="editAgentModal" tabindex="-1" role="dialog" aria-labelledby="editAgentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 800px;"> <!-- Ajustement de la taille -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_agent.php" class="needs-validation" novalidate enctype="multipart/form-data">
                    <input type="hidden" name="idAgent" id="editAgentId">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="noms" class="form-label">Noms</label>
                            <input type="text" name="noms" id="editAgentNoms" class="form-control" required>
                            
                            <!-- Champs cachés -->
                            <input type="hidden" name="lieuNaissance" id="editAgentLieuNaissance" class="form-control">
                            <input type="hidden" name="dateNaissance" id="editAgentDateNaissance" class="form-control">
                            <input type="hidden" name="sexe" id="editAgentSexe" class="form-control">
                            <input type="hidden" name="etatCivil" id="editAgentEtatCivil" class="form-control">
                            <input type="hidden" name="niveauEtude" id="editAgentNiveauEtude" class="form-control">
                            <input type="hidden" name="email" id="editEmail" class="form-control">
                            <input type="hidden" name="telephone" id="editTelephone" class="form-control">
                            <input type="hidden" name="idStructure" id="editAgentStructure" class="form-control">
                            <input type="hidden" name="type_agent" id="editTypeAgent" class="form-control">
                        </div>
                        <div class="col-md-6 d-flex justify-content-center">
                            <div id="loading-container">
                                <div id="vase">
                                    <div id="water"></div>
                                </div>
                            </div>

                            <!-- Zone d'aperçu de l'image -->
                            <img id="imagePreview" class="img-fluid" />
                            <canvas id="croppedCanvas" style="display: none;"></canvas>
                            <input type="hidden" name="croppedImage" id="croppedImageInput">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codeAgent" class="form-label">CODE POINTEUSE AGENT</label>
                            <input type="text" name="codeAgent" id="editCodeAgent" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="photoAgent" class="form-label">PHOTO AGENT</label>
                            <input type="file" name="photoAgent" id="photoInput" class="form-control" accept="image/*">
                            <input type="hidden" name="pictureAgent" id="pictureInput">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editAgentBtn2" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CSS pour limiter la taille de l'image -->
<style>
    #imagePreview {
        max-width: 100%; /* L'image ne dépasse pas la largeur du conteneur */
        max-height: 300px; /* Hauteur maximale pour éviter un débordement */
        display: block;
        margin: auto;
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    #loading-container {
    display: none;
    position: relative;
    width: 120px;
    height: 200px;
    margin: auto;
}

#vase {
    width: 100%;
    height: 100%;
    border-radius: 50px 50px 15px 15px;
    position: relative;
    overflow: hidden;
    background-color: rgba(0, 0, 0, 0.1);
}

#water {
    width: 100%;
    height: 0%;
    background: linear-gradient(180deg, #00bfff, #007acc);
    position: absolute;
    bottom: 0;
    transition: height 1s ease-in-out;
}

#water::after {
    content: "";
    width: 100%;
    height: 10px;
    position: absolute;
    top: 0;
    left: 0;
    background: rgba(255, 255, 255, 0.3);
    opacity: 0.8;
    border-radius: 50%;
    animation: wave 1.5s infinite linear;
}

@keyframes wave {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

#loading-text {
    position: absolute;
    width: 100%;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    color: white;
    top: 50%;
    transform: translateY(-50%);
}


</style>


<script>
    function editAgent(id, noms, lieuNaissance, dateNaissance, sexe, etatCivil, niveauEtude,telephone,email,code,photo, idStructure,type) {
        document.getElementById('editAgentId').value = id;
        document.getElementById('editAgentNoms').value = noms;
        document.getElementById('editAgentLieuNaissance').value = lieuNaissance;
        document.getElementById('editAgentDateNaissance').value = dateNaissance;
        document.getElementById('editAgentSexe').value = sexe;
        document.getElementById('editAgentEtatCivil').value = etatCivil;
        document.getElementById('editAgentNiveauEtude').value = niveauEtude;
        document.getElementById('editTelephone').value = telephone;
        document.getElementById('editEmail').value = email;
        document.getElementById('editCodeAgent').value = code;
        document.getElementById('editAgentStructure').value = idStructure;
        document.getElementById('editTypeAgent').value = type;
        document.getElementById('pictureInput').value = photo;

        
        new bootstrap.Modal(document.getElementById('editAgentModal')).show();
    }

    function openModal(imageSrc) {
        // Mettre l'image dans le modal
        document.getElementById('modalImage').src = imageSrc;
        
        // Afficher le modal
        var myModal = new bootstrap.Modal(document.getElementById('imageModal'));
        myModal.show();
    }


    
</script>

<script>
    let cropper;
    const photoInput = document.getElementById('photoInput');
    const imagePreview = document.getElementById('imagePreview');
    const croppedImageInput = document.getElementById('croppedImageInput');

    photoInput.addEventListener('change', function(event) {
    const file = event.target.files[0];

    if (file && file.type.startsWith('image/')) {
        const loadingContainer = document.getElementById('loading-container');
        const water = document.getElementById('water');
        const loadingText = document.createElement('div');
        loadingText.id = "loading-text";
        loadingContainer.appendChild(loadingText);

        loadingContainer.style.display = 'block';
        let progress = 0;

        // Animation dynamique du remplissage de l'eau
        const interval = setInterval(() => {
            progress += 10;
            water.style.height = `${progress}%`;
            loadingText.textContent = `${progress}%`;

            if (progress >= 100) {
                clearInterval(interval);

                setTimeout(() => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        loadingContainer.style.display = 'none';
                        water.style.height = '0%';
                        loadingText.textContent = '';

                        if (cropper) {
                            cropper.destroy(); // Détruire le cropper précédent si nécessaire
                        }

                        // Initialiser le cropper
                        cropper = new Cropper(imagePreview, {
                            aspectRatio: 1,
                            viewMode: 1,
                            ready() {
                                const canvas = cropper.getCroppedCanvas();
                                croppedImageInput.value = canvas.toDataURL('image/jpg'); // Mise à jour de l'input caché avec l'image rognée
                            }
                        });
                    };
                    reader.readAsDataURL(file);
                }, 500);
            }
        }, 200); // Chaque étape dure 200ms
    } else {
        alert("Veuillez sélectionner une image valide !");
    }
});




    function cropImage() {
        const canvas = cropper.getCroppedCanvas({ width: 150, height: 150 });
        croppedImageInput.value = canvas.toDataURL('image/jpg');
    }
</script>


<script>
    document.getElementById('searchInput').addEventListener('input', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
            let cells = row.querySelectorAll('td');
            let match = false;

            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(filter)) {
                    match = true;
                }
            });

            if (match) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
</script>

<?php include "./views/include/footer.php"; ?>