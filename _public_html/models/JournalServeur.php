<?php
/**
 * Classe JournalServeur
 * Gère les logs et le journal d'activités du système
 */
class JournalServeur {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    /**
     * Enregistrer une action dans le journal
     */
    public function enregistrerAction(
        $typeAction,
        $module,
        $description,
        $idUtilisateur = null,
        $nomUtilisateur = null,
        $tableAffectee = null,
        $idEnregistrement = null,
        $donneeAvant = null,
        $donneeApres = null,
        $statut = 'succes',
        $messageErreur = null
    ) {
        try {
            // Récupérer les infos utilisateur si non fournis
            if ($idUtilisateur === null) {
                if (isset($_SESSION['id'])) {
                    $idUtilisateur = $_SESSION['id'];
                } elseif (isset($_SESSION['id_user'])) {
                    $idUtilisateur = $_SESSION['id_user'];
                }
            }
            if ($nomUtilisateur === null) {
                if (isset($_SESSION['nom'])) {
                    $nomUtilisateur = $_SESSION['nom'];
                } elseif (isset($_SESSION['nomAgent'])) {
                    $nomUtilisateur = $_SESSION['nomAgent'];
                }
            }

            // Récupérer l'adresse IP
            $adresseIp = $this->obtenirAdresseIp();

            // Récupérer le User-Agent
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

            // Convertir les données en JSON si ce ne sont pas des chaînes
            if (is_array($donneeAvant) || is_object($donneeAvant)) {
                $donneeAvant = json_encode($donneeAvant, JSON_UNESCAPED_UNICODE);
            }
            if (is_array($donneeApres) || is_object($donneeApres)) {
                $donneeApres = json_encode($donneeApres, JSON_UNESCAPED_UNICODE);
            }

            $sql = "INSERT INTO journal_serveur 
                    (id_utilisateur, nom_utilisateur, type_action, module, description, 
                     table_affectee, id_enregistrement, adresse_ip, user_agent, 
                     donnees_avant, donnees_apres, statut, message_erreur) 
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $idUtilisateur,
                $nomUtilisateur,
                $typeAction,
                $module,
                $description,
                $tableAffectee,
                $idEnregistrement,
                $adresseIp,
                $userAgent,
                $donneeAvant,
                $donneeApres,
                $statut,
                $messageErreur
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement du log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les données actuelles d'une table avant modification
     */
    public function obtenirDonneeAvant($table, $idEnregistrement) {
        try {
            if (empty($table) || empty($idEnregistrement)) {
                return null;
            }

            // Déterminer la colonne ID basée sur le nom de la table
            $idColumnMap = [
                'module' => 'idMod',
                'permission' => 'idPerm',
                'etudiant' => 'idetudiant',
                'agent' => 'idAgent',
                'enseignant' => 'idEnseignant',
                'promotion' => 'idpromotion',
                'orientation' => 'idorientation',
                'semestre' => 'idsemestre',
                'unite' => 'idunite',
                'evaluation' => 'idevaluation',
                'horaire' => 'idhoraire',
                'cotes_grille' => 'idECUE',
                'stage_assignments' => 'idstage',
            ];

            $idColumn = $idColumnMap[$table] ?? 'id';

            $sql = "SELECT * FROM $table WHERE $idColumn = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idEnregistrement]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des données avant: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir l'adresse IP du client
     */
    private function obtenirAdresseIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return $ip;
    }

    /**
     * Récupérer tous les logs avec filtres
     */
    public function obtenirLogs($filtres = [], $page = 1, $parPage = 50) {
        try {
            $sql = "SELECT * FROM journal_serveur WHERE 1=1";
            $sqlCount = "SELECT COUNT(*) as total FROM journal_serveur WHERE 1=1";
            $parametres = [];

            // Filtres optionnels
            if (!empty($filtres['id_utilisateur'])) {
                $clause = " AND id_utilisateur = ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['id_utilisateur'];
            }

            if (!empty($filtres['type_action'])) {
                $clause = " AND type_action = ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['type_action'];
            }

            if (!empty($filtres['module'])) {
                $clause = " AND module = ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['module'];
            }

            if (!empty($filtres['statut'])) {
                $clause = " AND statut = ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['statut'];
            }

            if (!empty($filtres['date_debut'])) {
                $clause = " AND DATE(date_creation) >= ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['date_debut'];
            }

            if (!empty($filtres['date_fin'])) {
                $clause = " AND DATE(date_creation) <= ?";
                $sql .= $clause;
                $sqlCount .= $clause;
                $parametres[] = $filtres['date_fin'];
            }

            if (!empty($filtres['recherche'])) {
                $clause = " AND (description LIKE ? OR nom_utilisateur LIKE ? OR module LIKE ?)";
                $sql .= $clause;
                $sqlCount .= $clause;
                $recherche = '%' . $filtres['recherche'] . '%';
                $parametres[] = $recherche;
                $parametres[] = $recherche;
                $parametres[] = $recherche;
            }

            // Compter le total
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($parametres);
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Pagination - LIMIT et OFFSET sans paramètres
            $offset = ($page - 1) * $parPage;
            $sql .= " ORDER BY date_creation DESC LIMIT " . intval($parPage) . " OFFSET " . intval($offset);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametres);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'logs' => $logs,
                'total' => (int)$total,
                'pages' => ceil($total / $parPage),
                'page_actuelle' => $page,
                'par_page' => $parPage
            ];
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des logs: " . $e->getMessage());
            return ['logs' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /**
     * Obtenir les statistiques des logs
     */
    public function obtenirStatistiques($dateDebut = null, $dateFin = null) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_logs,
                        SUM(CASE WHEN statut = 'succes' THEN 1 ELSE 0 END) as succes,
                        SUM(CASE WHEN statut = 'erreur' THEN 1 ELSE 0 END) as erreurs,
                        SUM(CASE WHEN statut = 'avertissement' THEN 1 ELSE 0 END) as avertissements,
                        COUNT(DISTINCT id_utilisateur) as utilisateurs_uniques,
                        COUNT(DISTINCT module) as modules_utilises
                    FROM journal_serveur
                    WHERE 1=1";
            $parametres = [];

            if ($dateDebut) {
                $sql .= " AND DATE(date_creation) >= ?";
                $parametres[] = $dateDebut;
            }

            if ($dateFin) {
                $sql .= " AND DATE(date_creation) <= ?";
                $parametres[] = $dateFin;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametres);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors du calcul des statistiques: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les logs par type d'action
     */
    public function obtenirLogsParType() {
        try {
            $sql = "SELECT type_action, COUNT(*) as nombre 
                    FROM journal_serveur 
                    GROUP BY type_action 
                    ORDER BY nombre DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des logs par type: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir les utilisateurs les plus actifs
     */
    public function obtenirUtilisateursPlusActifs($limite = 10) {
        try {
            $sql = "SELECT id_utilisateur, nom_utilisateur, COUNT(*) as nombre_actions 
                    FROM journal_serveur 
                    WHERE id_utilisateur IS NOT NULL
                    GROUP BY id_utilisateur, nom_utilisateur
                    ORDER BY nombre_actions DESC
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprimer les logs anciens
     */
    public function supprimerLogsAnciens($joursAnciens = 90) {
        try {
            $sql = "DELETE FROM journal_serveur 
                    WHERE date_creation < DATE_SUB(NOW(), INTERVAL ? DAY)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$joursAnciens]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression des logs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Exporter les logs en CSV
     */
    public function exporterEnCSV($filtres = []) {
        try {
            $donnees = $this->obtenirLogs($filtres, 1, 99999);
            
            $nom_fichier = 'logs_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            
            // En-têtes
            fputcsv($output, [
                'ID Log',
                'Utilisateur',
                'Type Action',
                'Module',
                'Description',
                'Table',
                'ID Enregistrement',
                'Adresse IP',
                'Statut',
                'Date/Heure'
            ]);
            
            // Données
            foreach ($donnees['logs'] as $log) {
                fputcsv($output, [
                    $log['id_log'],
                    $log['nom_utilisateur'],
                    $log['type_action'],
                    $log['module'],
                    $log['description'],
                    $log['table_affectee'],
                    $log['id_enregistrement'],
                    $log['adresse_ip'],
                    $log['statut'],
                    $log['date_creation']
                ]);
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            error_log("Erreur lors de l'export: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir un log spécifique
     */
    public function obtenirLog($idLog) {
        try {
            $sql = "SELECT * FROM journal_serveur WHERE id_log = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idLog]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur: " . $e->getMessage());
            return null;
        }
    }
}
?>
