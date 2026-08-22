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
        $idAgent = intval($_POST['idAgent'] ?? 0);
        $idUser = intval($_POST['idUser'] ?? 0);
        $loginUser = trim($_POST['loginUser'] ?? '');
        $roles = $_POST['roles'] ?? [];
        $principalRole = intval($_POST['principalRole'] ?? 0);
        $etatUser = intval($_POST['etatUser'] ?? 1);
        $newPassword = trim($_POST['newPassword'] ?? '');
        
        // Validation des champs requis
        if ($idAgent === 0 || $idUser === 0) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Données manquantes pour identifier l\'utilisateur.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
        }

        // Vérifier si l'utilisateur existe
        $checkUserQuery = "SELECT u.*, a.noms FROM t_users u 
                          INNER JOIN agent a ON u.\"idAgent\" = a.\"idAgent\" 
                          WHERE u.\"idUser\" = :idUser AND u.\"idAgent\" = :idAgent";
        $checkUserStmt = $connexion->prepare($checkUserQuery);
        $checkUserStmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $checkUserStmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
        $checkUserStmt->execute();
        
        $existingUser = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingUser) {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Utilisateur non trouvé ou non associé à cet agent.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
        }

        // Traitement en fonction du bouton cliqué
        if (isset($_POST['updateUserAccessBtn'])) {
            // MISE À JOUR DE L'ACCÈS UTILISATEUR
            
            // Validation des champs requis pour la mise à jour
            if (empty($loginUser) || empty($roles)) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'L\'identifiant et le rôle sont obligatoires.'
                    }).then(() => {
                        window.location.href = '../grh/agent.add.rapide';
                    });
                </script>";
                exit();
            }

            // Vérifier si les rôles existent
            foreach ($roles as $roleId) {
                $roleQuery = "SELECT \"idRole\" FROM t_roles WHERE \"idRole\" = :idRole";
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

            // Vérifier les doublons pour le login (exclure l'utilisateur actuel)
            $checkLoginQuery = "SELECT \"idUser\" FROM t_users WHERE \"loginUser\" = :loginUser AND \"idUser\" != :idUser";
            $checkLoginStmt = $connexion->prepare($checkLoginQuery);
            $checkLoginStmt->bindParam(':loginUser', $loginUser, PDO::PARAM_STR);
            $checkLoginStmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
            $checkLoginStmt->execute();
            
            if ($checkLoginStmt->fetch()) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Ce nom d\'utilisateur est déjà utilisé par un autre compte.'
                    }).then(() => {
                        window.location.href = '../grh/agent.add.rapide';
                    });
                </script>";
                exit();
            }

            // Préparer la requête de mise à jour
            $updateFields = [
                'loginUser = :loginUser',
                'etatUser = :etatUser'
            ];
            $updateParams = [
                ':loginUser' => $loginUser,
                ':etatUser' => $etatUser,
                ':idUser' => $idUser
            ];

            // Ajouter le mot de passe si fourni
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateFields[] = 'pw = :pw';
                $updateParams[':pw'] = $hashedPassword;
            }

            $updateQuery = "UPDATE t_users SET " . implode(', ', $updateFields) . " WHERE \"idUser\" = :idUser";
            $updateStmt = $connexion->prepare($updateQuery);
            
            foreach ($updateParams as $key => $value) {
                $updateStmt->bindValue($key, $value);
            }
            
            if ($updateStmt->execute()) {
            // Mettre à jour les rôles
                $deleteRolesQuery = "DELETE FROM t_user_roles WHERE \"idUser\" = :idUser";
                $deleteStmt = $connexion->prepare($deleteRolesQuery);
                $deleteStmt->bindParam(':idUser', $idUser);
                $deleteStmt->execute();

                foreach ($roles as $roleId) {
                $isPrincipal = ($roleId == $principalRole) ? 1 : 0;
                $insertRoleQuery = "INSERT INTO t_user_roles (\"idUser\", \"idRole\", \"isPrincipal\") VALUES (:idUser, :idRole, :isPrincipal)";
                $insertStmt = $connexion->prepare($insertRoleQuery);
                $insertStmt->bindParam(':idUser', $idUser);
                $insertStmt->bindParam(':idRole', $roleId);
                    $insertStmt->bindParam(':isPrincipal', $isPrincipal, PDO::PARAM_INT);
                    $insertStmt->execute();
                }

                $successMessage = "Accès utilisateur mis à jour avec succès!";
                if (!empty($newPassword)) {
                    $successMessage .= "<br><strong>Nouveau mot de passe:</strong> " . htmlspecialchars($newPassword);
                }
                
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        html: '$successMessage',
                        showConfirmButton: true
                    }).then(() => {
                        window.location.href = '../grh/agent.add.rapide';
                    });
                </script>";
            } else {
                throw new Exception("Erreur lors de la mise à jour de l'utilisateur");
            }

        } elseif (isset($_POST['deleteUserAccessBtn'])) {
            // SUPPRESSION DE L'ACCÈS UTILISATEUR
            
            // Vérifier que l'utilisateur connecté n'essaie pas de supprimer son propre accès
            if ($idUser == $_SESSION['id']) {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Vous ne pouvez pas supprimer votre propre accès.'
                    }).then(() => {
                        window.location.href = '../grh/agent.add.rapide';
                    });
                </script>";
                exit();
            }

            // Supprimer d'abord les références dans les tables liées si nécessaire
            // (user_structure, user_journal, user_banque, etc.)
            $deleteRelatedQueries = [
                "DELETE FROM user_structure WHERE \"idUser\" = :idUser",
                "DELETE FROM user_journal WHERE \"idUser\" = :idUser", 
                "DELETE FROM user_banque WHERE \"idUser\" = :idUser",
                "DELETE FROM user_depot WHERE \"idUser\" = :idUser",
                "DELETE FROM user_budget WHERE \"idUser\" = :idUser"
            ];

            $connexion->beginTransaction();
            
            try {
                // Supprimer les références dans les tables liées
                foreach ($deleteRelatedQueries as $query) {
                    $stmt = $connexion->prepare($query);
                    $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                // Supprimer l'utilisateur
                $deleteUserQuery = "DELETE FROM t_users WHERE \"idUser\" = :idUser";
                $deleteUserStmt = $connexion->prepare($deleteUserQuery);
                $deleteUserStmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
                
                if ($deleteUserStmt->execute()) {
                    $connexion->commit();
                    echo "<script>
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Accès utilisateur supprimé avec succès!',
                            showConfirmButton: true
                        }).then(() => {
                            window.location.href = '../grh/agent.add.rapide';
                        });
                    </script>";
                } else {
                    throw new Exception("Erreur lors de la suppression de l'utilisateur");
                }
                
            } catch (Exception $e) {
                $connexion->rollback();
                throw $e;
            }

        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Action non reconnue.'
                }).then(() => {
                    window.location.href = '../grh/agent.add.rapide';
                });
            </script>";
            exit();
        }

    } catch (PDOException $e) {
        if (isset($connexion) && $connexion->inTransaction()) {
            $connexion->rollback();
        }
        error_log("Erreur PDO dans manage_user_access.php: " . $e->getMessage());
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
        if (isset($connexion) && $connexion->inTransaction()) {
            $connexion->rollback();
        }
        error_log("Erreur dans manage_user_access.php: " . $e->getMessage());
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