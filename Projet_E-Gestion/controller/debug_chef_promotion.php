<?php
session_start();
require_once '../config/Connexion.php';

// Afficher les informations de debug
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Chef de Promotion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Debug Chef de Promotion</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Informations de Session</h5>
            </div>
            <div class="card-body">
                <pre><?php print_r($_SESSION); ?></pre>
            </div>
        </div>

        <?php
        $connexion = Connexion::getInstance()->getPDO();
        
        if (isset($_SESSION['student_id'])) {
            $studentId = $_SESSION['student_id'];
            
            // Vérifier l'étudiant
            echo '<div class="card mb-4">';
            echo '<div class="card-header"><h5>Informations Étudiant</h5></div>';
            echo '<div class="card-body">';
            
            try {
                $queryEtudiant = "SELECT e.*, p.\"designationPromotion\" 
                                  FROM etudiant e 
                                  LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                                  WHERE e.idetudiant = :student_id";
                $stmtEtudiant = $connexion->prepare($queryEtudiant);
                $stmtEtudiant->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $stmtEtudiant->execute();
                $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
                
                if ($etudiant) {
                    echo '<pre>' . print_r($etudiant, true) . '</pre>';
                } else {
                    echo '<div class="alert alert-warning">Étudiant non trouvé</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur: ' . $e->getMessage() . '</div>';
            }
            
            echo '</div></div>';
            
            // Vérifier chef de promotion
            echo '<div class="card mb-4">';
            echo '<div class="card-header"><h5>Informations Chef de Promotion</h5></div>';
            echo '<div class="card-body">';
            
            try {
                $queryChef = "SELECT cp.*, e.noms as nom_etudiant, p.\"designationPromotion\", aa.designation as annee_acad
                              FROM chef_promotion cp 
                              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
                              INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                              INNER JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
                              WHERE e.idetudiant = :student_id 
                              ORDER BY cp.date_nomination DESC";
                              
                $stmtChef = $connexion->prepare($queryChef);
                $stmtChef->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $stmtChef->execute();
                $chefs = $stmtChef->fetchAll(PDO::FETCH_ASSOC);
                
                if ($chefs) {
                    echo '<h6>Tous les enregistrements chef de promotion:</h6>';
                    echo '<pre>' . print_r($chefs, true) . '</pre>';
                    
                    $chefActif = null;
                    foreach ($chefs as $chef) {
                        if ($chef['est_actif'] == 1) {
                            $chefActif = $chef;
                            break;
                        }
                    }
                    
                    if ($chefActif) {
                        echo '<h6>Chef de promotion actif:</h6>';
                        echo '<pre>' . print_r($chefActif, true) . '</pre>';
                    } else {
                        echo '<div class="alert alert-warning">Aucun chef de promotion actif trouvé</div>';
                    }
                } else {
                    echo '<div class="alert alert-warning">Aucun enregistrement chef de promotion trouvé</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur: ' . $e->getMessage() . '</div>';
            }
            
            echo '</div></div>';
            
            // Vérifier la structure de la table suivi_enseignements
            echo '<div class="card mb-4">';
            echo '<div class="card-header"><h5>Structure Table suivi_enseignements</h5></div>';
            echo '<div class="card-body">';
            
            try {
                $queryStructure = "SELECT column_name AS \"Field\", data_type AS \"Type\" FROM information_schema.columns WHERE table_name = 'suivi_enseignements'";
                $stmtStructure = $connexion->prepare($queryStructure);
                $stmtStructure->execute();
                $columns = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<pre>' . print_r($columns, true) . '</pre>';
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur: ' . $e->getMessage() . '</div>';
            }
            
            echo '</div></div>';
            
            // Test de la requête get_suivi_enseignements
            echo '<div class="card mb-4">';
            echo '<div class="card-header"><h5>Test Requête Suivi Enseignements</h5></div>';
            echo '<div class="card-body">';
            
            try {
                // Simuler l'appel à get_suivi_enseignements.php
                ob_start();
                include 'get_suivi_enseignements.php';
                $output = ob_get_clean();
                
                echo '<h6>Sortie brute:</h6>';
                echo '<pre>' . htmlspecialchars($output) . '</pre>';
                
                $data = json_decode($output, true);
                if ($data) {
                    echo '<h6>Données parsées:</h6>';
                    echo '<pre>' . print_r($data, true) . '</pre>';
                } else {
                    echo '<div class="alert alert-danger">Impossible de parser le JSON</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Erreur: ' . $e->getMessage() . '</div>';
            }
            
            echo '</div></div>';
        } else {
            echo '<div class="alert alert-warning">Aucun student_id en session</div>';
        }
        ?>
        
        <div class="mt-4">
            <a href="../views/portail/student.php" class="btn btn-primary">Retour à la page étudiant</a>
        </div>
    </div>
</body>
</html>