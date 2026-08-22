<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

$universite = new Universite();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricule = $_POST['matricule'] ?? '';
    $noms = $_POST['noms'] ?? '';
    $lieuNaissance = $_POST['lieuNaissance'] ?? '';
    $dateNaissance = $_POST['dateNaissance'] ?? '';
    $adressemail = $_POST['adressemail'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $sexe = $_POST['sexe'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $idAnnee = $_POST['idAnnee'] ?? '';
    $promotionId = $_POST['promotionId'] ?? '';
    $idUser = $_SESSION['id'] ?? null; // Retrieve idUser from session

    // Validate input
    if (empty($matricule) || empty($noms) || empty($sexe) || empty($nationalite) || empty($idAnnee) || empty($promotionId) || $idUser === null) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Veuillez remplir tous les champs obligatoires.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
        exit();
    }

    // Check for duplicate matricule globally
    $existingStudent = $universite->getStudentByMatricule($matricule);
    if ($existingStudent) {
        // Generate the next available matricule
        $count = 1;
        $prefix = "ET-A";
        do {
            $nextMatricule = $prefix . str_pad($count, 8, '0', STR_PAD_LEFT);
            $existingStudent = $universite->getStudentByMatricule($nextMatricule);
            $count++;
        } while ($existingStudent);

        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Matricule déjà existant',
                text: 'Un étudiant avec ce matricule existe déjà pour cette année académique. Voulez-vous utiliser le matricule suivant : $nextMatricule ?',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'create_etudiant.php';
                    form.id = 'studentForm';

                    let fields = {
                        matricule: '$nextMatricule',
                        noms: '$noms',
                        lieuNaissance: '$lieuNaissance',
                        dateNaissance: '$dateNaissance',
                        adressemail: '$adressemail',
                        telephone: '$telephone',
                        sexe: '$sexe',
                        nationalite: '$nationalite',
                        idAnnee: '$idAnnee',
                        promotionId: '$promotionId'
                    };

                    for (let key in fields) {
                        let input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = fields[key];
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    window.location.href = '../etudiants/etudiant.inscrit';
                }
            });
        </script>";
        exit();
    }

    // Create the student
    $result = $universite->createStudent($matricule, $noms, $lieuNaissance, $dateNaissance, $adressemail, $telephone, $sexe, $nationalite, $idAnnee, $promotionId, $idUser);

    if ($result) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'Étudiant ajouté avec succès.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Erreur lors de l\'ajout de l\'étudiant.'
            }).then(() => {
                window.location.href = '../etudiants/etudiant.inscrit';
            });
        </script>";
    }
    exit();
} else {
    header("Location: ../etudiants/etudiant.inscrit");
    exit();
}
?>