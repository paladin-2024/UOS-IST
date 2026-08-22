<?php
session_start();
require_once dirname(__DIR__) . '/views/405.php';
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Connexion à la base de données
        $connexion = Connexion::getInstance()->getPDO();
        
        // Récupérer les données du formulaire
        $nomUser = trim($_POST['nomUser'] ?? '');
        $loginUser = trim($_POST['loginUser'] ?? '');
        $roles = $_POST['roles'] ?? [];
        $principalRole = intval($_POST['principalRole'] ?? 0);
        $idAgent = intval($_POST['idAgent'] ?? 0);
        $etatUser = isset($_POST['etatUser']) ? intval($_POST['etatUser']) : 1;
        
        // Mot de passe par défaut
        $defaultPassword = "12345678";
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $dernier_connexion = date('Y-m-d'); // Date seulement, pas datetime

        // Validation des champs requis
        if (empty($loginUser) || empty($roles) || $idAgent === 0) {
        echo "<script>
        Swal.fire({
        icon: 'error',
        title: 'Erreur',
        text: 'Tous les champs obligatoires doivent être remplis.'
        }).then(() => {
        window.location.href = '../grh/agent.add.rapide';
        });
        </script>";
        exit();
        }

        // Récupérer le nom de l'agent si vide
        if (empty($nomUser)) {
            $agentQuery = "SELECT noms FROM agent WHERE idAgent = :idAgent";
            $agentStmt = $connexion->prepare($agentQuery);
            $agentStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
            $agentStmt->execute();
            $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($agent) {
                $nomUser = $agent['noms'];
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Agent non trouvé.'
                    }).then(() => {
                        window.location.href = '../grh/agent.add.rapide';
                    });
                </script>";
                exit();
            }
        }

        // Vérifier si les rôles existent
        foreach ($roles as $roleId) {
            $roleQuery = "SELECT idRole FROM t_roles WHERE idRole = :idRole";
        $roleStmt = $connexion->prepare($roleQuery);
            $roleStmt->bindParam(':idRole', $roleId, PDO::PARAM_INT);
        $roleStmt->execute();
        
        if (!$roleStmt->fetch()) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Un des rôles sélectionnés n\'existe pas.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
                }
                   }

        // Vérifier si l'agent a déjà un utilisateur
        $checkUserQuery = "SELECT idUser FROM t_users WHERE idAgent = :idAgent";
        $checkUserStmt = $connexion->prepare($checkUserQuery);
        $checkUserStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $checkUserStmt->execute();
        
        if ($checkUserStmt->fetch()) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Cet agent possède déjà un accès utilisateur.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
        }

        // Vérifier les doublons pour le login
        $checkLoginQuery = "SELECT idUser FROM t_users WHERE loginUser = :loginUser";
        $checkLoginStmt = $connexion->prepare($checkLoginQuery);
        $checkLoginStmt->bindParam(':loginUser', $loginUser, PDO::PARAM_STR);
        $checkLoginStmt->execute();
        
        if ($checkLoginStmt->fetch()) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Ce nom d\'utilisateur existe déjà.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
        }

        // Traitement de l'image (si fournie)
        $imageFile = isset($_FILES['imageUser']) ? $_FILES['imageUser'] : null;
        $imageUrl = '';
        
        if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (in_array($imageFile['type'], $allowedTypes) && $imageFile['size'] <= $maxSize) {
                $uploadDir = dirname(__DIR__) . '/uploads/users/';
                
                // Créer le répertoire s'il n'existe pas
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExtension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
                $fileName = 'user_' . $idAgent . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($imageFile['tmp_name'], $uploadPath)) {
                    $imageUrl = 'uploads/users/' . $fileName;
                }
            }
        }

        // Insérer le nouvel utilisateur - CORRECTION DES NOMS DE COLONNES
        $insertQuery = "INSERT INTO t_users (nomUser, loginUser, pw, imageUser, etatUser, dernier_connexion, idAgent)
        VALUES (:nomUser, :loginUser, :pw, :imageUser, :etatUser, :dernier_connexion, :idAgent)";
        
        $insertStmt = $connexion->prepare($insertQuery);

        $insertStmt->bindParam(':nomUser', $nomUser, PDO::PARAM_STR);
        $insertStmt->bindParam(':loginUser', $loginUser, PDO::PARAM_STR);
        $insertStmt->bindParam(':pw', $hashedPassword, PDO::PARAM_STR); // CORRECTION: pw au lieu de motPasseUser
        $insertStmt->bindParam(':imageUser', $imageUrl, PDO::PARAM_STR);
        $insertStmt->bindParam(':etatUser', $etatUser, PDO::PARAM_INT);
        $insertStmt->bindParam(':dernier_connexion', $dernier_connexion, PDO::PARAM_STR);
        $insertStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        
        if ($insertStmt->execute()) {
        $idUser = $connexion->lastInsertId();

        // Insérer les rôles
        foreach ($roles as $roleId) {
        $isPrincipal = ($roleId == $principalRole) ? 1 : 0;
        $insertRoleQuery = "INSERT INTO t_user_roles (idUser, idRole, isPrincipal) VALUES (:idUser, :idRole, :isPrincipal)";
        $insertRoleStmt = $connexion->prepare($insertRoleQuery);
        $insertRoleStmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $insertRoleStmt->bindParam(':idRole', $roleId, PDO::PARAM_INT);
            $insertRoleStmt->bindParam(':isPrincipal', $isPrincipal, PDO::PARAM_INT);
                $insertRoleStmt->execute();
            }

            // Succès
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Accès utilisateur créé avec succès!',
                    html: '<p>Accès utilisateur créé avec succès!</p><p><strong>Identifiant:</strong> {$loginUser}</p><p><strong>Mot de passe:</strong> {$defaultPassword}</p>',
                    showConfirmButton: true
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
        } else {
            throw new Exception("Erreur lors de l'insertion dans la base de données");
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO dans assign_user_access.php: " . $e->getMessage());
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur de base de données',
                text: 'Erreur: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../grh/agent.add.rapide';
            });
        </script>";
    } catch (Exception $e) {
        error_log("Erreur dans assign_user_access.php: " . $e->getMessage());
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Une erreur inattendue est survenue: " . addslashes($e->getMessage()) . "'
            }).then(() => {
                window.location.href = '../grh/agent.add.rapide';
            });
        </script>";
    }

} else {
    // Rediriger si accès direct sans soumission du formulaire
    header("Location: ../grh/agent.add.rapide");
    exit();
}
?>
