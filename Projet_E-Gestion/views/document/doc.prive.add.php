<?php
include "./views/include/header.php";

$documentModel = new Structure();
$userId = $_SESSION['id'];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$documents = $documentModel->getPrivateDocumentsByUserAccess($userId, $search);
$categories = $documentModel->getCategoriesByUserAccess($userId);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Explorateur de Documents Privés</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Documents Privés</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Mes Documents</h5>
                        <div class="d-flex mb-3">
                            <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#captureModal">
                                <i class="bi bi-camera"></i> Scanner Document
                            </button>
                            <form method="GET" action="" class="w-100">
                                <div class="input-group">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher...">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                                </div>
                            </form>
                        </div>
                        <div class="row">
                            <?php if (empty($documents)) : ?>
                                <p class="text-center">Aucun document trouvé.</p>
                            <?php else : ?>
                                <?php foreach ($documents as $document) : ?>
                                    <div class="col-md-4">
                                        <div class="card shadow-sm mb-3">
                                            <div class="card-body text-center">
                                                <i class="bi bi-file-earmark-text display-4"></i>
                                                <h6 class="mt-2"> <?= htmlspecialchars($document['titre']) ?> </h6>
                                                <p class="text-muted small">Ajouté le <?= date('d/m/Y', strtotime($document['date_ajout'])) ?></p>
                                                <p class="text-muted small">Catégorie : <?= htmlspecialchars($document['nom']) ?></p>
                                                <p class="text-muted small">Auteur : <?= htmlspecialchars($document['nomUser']) ?></p>
                                                <div class="btn-group">
                                                    <a href="uploads/<?= htmlspecialchars($document['chemin_fichier']) ?>" class="btn btn-primary btn-sm" download>
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#manageUsersModals" data-document-id="<?= $document['id_document'] ?>">
                                                        <i class="bi bi-people"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteDocument(<?= $document['id_document'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add Document Modal -->
<div class="modal fade" id="addDocumentModal" tabindex="-1" aria-labelledby="addDocumentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDocumentModalLabel">Ajouter un Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDocumentForm" method="POST" action="controller/addDocument.php" enctype="multipart/form-data">
                    <!-- Zone de dépôt de fichier -->
                    <div class="mb-3">
                        <label class="form-label">Fichier <span class="text-danger">*</span></label>
                        <div id="dropZone" class="border border-primary rounded d-flex flex-column align-items-center justify-content-center p-4 text-center" style="cursor: pointer;">
                            <i class="bi bi-cloud-upload fs-1 text-primary"></i>
                            <p class="mb-1">Glissez-déposez un fichier ici ou cliquez pour sélectionner</p>
                            <small class="text-muted">Formats acceptés : PDF, DOCX, XLSX, PNG, JPG</small>
                            <input type="file" class="form-control d-none" id="fichier" name="fichier" required>
                            <input type="hidden" id="pre_uploaded_file" name="pre_uploaded_file" value="">
                        </div>
                    </div>

                    <!-- Barre de progression -->
                    <div class="progress mb-3" style="display: none;" id="uploadProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <!-- Titre and Catégorie on the same line -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="titre" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titre" name="titre" required>
                        </div>
                        <div class="col-md-6">
                            <label for="categorie" class="form-label">Classeur <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorie" name="id_categorie" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id_categorie'] ?>"><?= htmlspecialchars($category['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btnModSave ladda-button"><span class="bi bi-save"></span> Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Capture Document Modal -->
<div class="modal fade" id="captureModal" tabindex="-1" aria-labelledby="captureModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="captureModalLabel">Scanner un Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-center mb-3">
                    <button id="start" class="btn btn-primary">Démarrer la Caméra</button>
                </div>
                <video id="video" width="100%" style="max-height: 300px; display: none;" autoplay></video>
                <canvas id="canvas" style="display:none;"></canvas>
                <div class="d-flex justify-content-between mt-3">
                    <button id="capture" class="btn btn-primary" disabled>Capturer</button>
                    <button id="stop" class="btn btn-danger" disabled>Arrêter la Caméra</button>
                    <button id="preview" class="btn btn-info" disabled>Prévisualiser PDF</button>
                    <button id="save" class="btn btn-success" disabled>Sauvegarder PDF</button>
                    <button id="upload" class="btn btn-secondary" disabled>Uploader le PDF</button>
                </div>
                <div id="preview-container" class="mt-3"></div>
                <p id="status-message" class="mt-3"></p>
            </div>
        </div>
    </div>
</div>

<style>
    .preview-image {
        width: 200px;
        height: 200px;
        object-fit: cover;
        margin: 5px;
    }
</style>

<!-- Manage Users Modal -->
<div class="modal fade" id="manageUsersModals" tabindex="-1" aria-labelledby="manageUsersModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageUsersModalLabel">Gérer les Utilisateurs Autorisés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="userList"></div>
                <form id="addUserForm" method="POST" action="controller/addUserToDocument.php">
                    <input type="hidden" id="documentId" name="id_document">
                    <div class="mb-3">
                        <label for="user" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                        <select class="form-select" id="user" name="idUser" required>
                        <?php
                        $allUsers = $documentModel->getUsers(); // Assuming this method retrieves all users
                        foreach ($allUsers as $users) {
                            echo "<option value='{$users['idUser']}'>{$users['nomUser']}</option>";
                        }
                        ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><span class="bi bi-plus-circle"></span> Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const video = document.getElementById("video");
    const canvas = document.getElementById("canvas");
    const startButton = document.getElementById("start");
    const captureButton = document.getElementById("capture");
    const stopButton = document.getElementById("stop");
    const previewButton = document.getElementById("preview");
    const saveButton = document.getElementById("save");
    const uploadButton = document.getElementById("upload");
    const previewContainer = document.getElementById("preview-container");
    const statusMessage = document.getElementById("status-message");
    const images = [];
    let stream;

    // Démarrer la caméra
    startButton.addEventListener("click", function () {
        navigator.mediaDevices.getUserMedia({ video: true })
        .then(s => {
            stream = s;
            video.srcObject = stream;
            video.style.display = "block";
            captureButton.disabled = false;
            stopButton.disabled = false;
            startButton.disabled = true;
        })
        .catch(err => {
            console.error("Erreur d'accès à la caméra", err);
            alert("Impossible d'accéder à la caméra. Vérifiez les permissions.");
        });
    });

    // Capturer une image
    captureButton.addEventListener("click", function () {
        const context = canvas.getContext("2d");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = canvas.toDataURL("image/jpeg");
        images.push(imageData);

        // Ajouter une prévisualisation
        const imgElement = document.createElement("img");
        imgElement.src = imageData;
        imgElement.classList.add("preview-image");
        previewContainer.appendChild(imgElement);

        previewButton.disabled = false;
    });

    // Arrêter la caméra
    stopButton.addEventListener("click", function () {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            video.srcObject = null;
            video.style.display = "none";
            captureButton.disabled = true;
            stopButton.disabled = true;
            startButton.disabled = false;
        }
    });

    // Prévisualiser le PDF
    previewButton.addEventListener("click", async function () {
        if (images.length === 0) {
            alert("Aucune image capturée.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        images.forEach((img, index) => {
            if (index > 0) doc.addPage();
            doc.addImage(img, "JPEG", 10, 10, 180, 150);
        });

        const pdfDataUri = doc.output('datauristring');
        const pdfWindow = window.open("");
        pdfWindow.document.write("<iframe width='100%' height='100%' src='" + pdfDataUri + "'></iframe>");

        saveButton.disabled = false;
        uploadButton.disabled = false;
    });

    // Sauvegarder en PDF
    saveButton.addEventListener("click", async function () {
        if (images.length === 0) {
            alert("Aucune image capturée.");
            return;
        }

        const fileName = prompt("Entrez le nom du fichier:", "document_scanné");
        if (!fileName) return;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        images.forEach((img, index) => {
            if (index > 0) doc.addPage();
            doc.addImage(img, "JPEG", 10, 10, 180, 150);
        });
        doc.save(`${fileName}.pdf`);
    });

    // Upload du PDF
    uploadButton.addEventListener("click", async function () {
        if (images.length === 0) {
            alert("Aucune image capturée.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        images.forEach((img, index) => {
            if (index > 0) doc.addPage();
            doc.addImage(img, "JPEG", 10, 10, 180, 150);
        });

        const pdfBlob = doc.output("blob");
        const formData = new FormData();
        formData.append("file", pdfBlob, "document_scanné.pdf");

        statusMessage.textContent = "Envoi en cours...";

        fetch("upload.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(result => {
            statusMessage.textContent = "Upload réussi: " + result;
            alert("Upload réussi: " + result);
        })
        .catch(error => {
            console.error("Erreur lors de l'upload", error);
            statusMessage.textContent = "Erreur lors de l'upload";
        });
    });
});

function confirmDeleteDocument(documentId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/deleteDocument.php?id=' + documentId;
        }
    });
}

document.querySelectorAll('[data-bs-target="#manageUsersModals"]').forEach(button => {
    button.addEventListener('click', function () {
        const documentId = this.getAttribute('data-document-id');
        document.getElementById('documentId').value = documentId;

        // Fetch and display users authorized for this document
        fetch(`controller/getUsersForDocument.php?id=${documentId}`)
            .then(async response => {
                const data = await response.json().catch(() => null);
                if (!response.ok) {
                    const message = data && (data.details || data.error) ? (data.details || data.error) : 'Erreur lors du chargement des utilisateurs';
                    throw new Error(message);
                }
                return data;
            })
            .then(users => {
                if (!Array.isArray(users)) {
                    throw new Error('Réponse invalide du serveur');
                }
                const userList = document.getElementById('userList');
                userList.innerHTML = '';
                if (users.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'text-muted';
                    empty.textContent = 'Aucun utilisateur autorisé pour ce document.';
                    userList.appendChild(empty);
                    return;
                }
                users.forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'd-flex justify-content-between align-items-center mb-2';
                    div.textContent = user.nomUser;
                    
                    const removeButton = document.createElement('button');
                    removeButton.className = 'btn btn-danger btn-sm';
                    removeButton.textContent = 'Supprimer';
                    removeButton.onclick = function () {
                        const formData = new FormData();
                        formData.append('idUser', user.idUser);
                        formData.append('id_document', documentId);

                        fetch(`controller/removeUserFromDocument.php`, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                div.remove();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erreur',
                                    text: data.error || 'Erreur lors de la suppression.'
                                });
                            }
                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Erreur lors de la communication avec le serveur.'
                            });
                        });
                    };
                    div.appendChild(removeButton);
                    userList.appendChild(div);
                });
            })
            .catch(error => {
                console.error('Error fetching users:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: (error && error.message) ? error.message : 'Une erreur est survenue lors du chargement des utilisateurs.'
                });
            });

        const modalElement = document.getElementById('manageUsersModals');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
    });
});


document.getElementById('fichier').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const fileName = file.name;
        const title = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
        document.getElementById('titre').value = title;
        document.getElementById('description').value = title;

        const reader = new FileReader();
        const progressBar = document.querySelector('#uploadProgress .progress-bar');
        document.getElementById('uploadProgress').style.display = 'block'; // Show the progress bar

        reader.onprogress = function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressBar.style.width = percentComplete + '%';
                progressBar.setAttribute('aria-valuenow', percentComplete);
            }
        };

        reader.onloadend = function() {
            progressBar.style.width = '100%';
            setTimeout(() => {
                document.getElementById('uploadProgress').style.display = 'none'; // Hide the progress bar after a short delay
            }, 500);
        };

        reader.readAsArrayBuffer(file); // Use readAsArrayBuffer for better progress tracking
    }
});

</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("fichier");

    dropZone.addEventListener("click", () => fileInput.click());
});
</script>

<script>
// Observe le message de statut pour capter le nom du fichier scanné et pré-remplir le formulaire
document.addEventListener('DOMContentLoaded', function () {
  const statusEl = document.getElementById('status-message');
  if (!statusEl) return;
  const observer = new MutationObserver(function () {
    const text = (statusEl.textContent || '').trim();
    // Format attendu: "Upload ...: NOM_FICHIER.pdf" (tolère variations d'accents)
    if (/upload\s*.*:\s*/i.test(text)) {
      const parts = text.split(':');
      const filename = (parts[1] || '').trim();
      if (filename) {
        const hidden = document.getElementById('pre_uploaded_file');
        if (hidden && !hidden.value) {
          hidden.value = filename;
          const titreInput = document.getElementById('titre');
          const descInput = document.getElementById('description');
          if (titreInput && !titreInput.value) {
            titreInput.value = 'Document scanné ' + new Date().toLocaleString();
          }
          if (descInput && !descInput.value && titreInput) {
            descInput.value = titreInput.value;
          }
          const modalEl = document.getElementById('addDocumentModal');
          if (modalEl && window.bootstrap) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
          }
        }
      }
    }
  });
  observer.observe(statusEl, { childList: true, subtree: true, characterData: true });
});
</script>

<?php include "./views/include/footer.php"; ?>
