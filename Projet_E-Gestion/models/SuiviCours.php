<?php
class SuiviCours
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // ---------------------------------------------------------------
    // Récupère tous les ECUE d'une section avec leur statut de suivi
    // Retourne une structure groupée : promotion > semestre > UE > ECUE
    // ---------------------------------------------------------------
    public function getSuiviBySection($idsection, $idannee)
    {
        $query = "
            SELECT
                p.idpromotion,
                p.\"designationPromotion\",
                p.cycle,
                s.idsemestre,
                s.\"numeroSemestre\",
                u.\"idUE\",
                u.\"codeUE\",
                u.\"designationUE\",
                e.\"idECUE\",
                e.\"designationECUE\",
                e.CMI,
                e.TD,
                e.TP,
                COALESCE(
                    (SELECT sc.statut FROM suivi_cours sc
                     WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee
                     LIMIT 1),
                    'non_commence'
                ) AS statut,
                (SELECT sc.observations FROM suivi_cours sc
                 WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee2
                 LIMIT 1) AS observations,
                (SELECT sc.date_mise_a_jour FROM suivi_cours sc
                 WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee3
                 LIMIT 1) AS date_mise_a_jour,
                (SELECT sc.\"idUser\" FROM suivi_cours sc
                 WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee4
                 LIMIT 1) AS \"idUser\"
            FROM promotion p
            INNER JOIN orientation o   ON o.idorientation        = p.orientation_idorientation
            INNER JOIN semestre s      ON s.promotion_idpromotion = p.idpromotion
            INNER JOIN ue u            ON u.semestre_idsemestre   = s.idsemestre
            INNER JOIN ecue e          ON e.\"UE_idUE\"               = u.\"idUE\"
            WHERE o.section_idsection         = :idsection
              AND p.annee_acad_idannee_acad    = :idannee5
              AND COALESCE(e.\"estVisible\", 1)    = 1
            GROUP BY e.\"idECUE\", p.idpromotion, s.idsemestre, u.\"idUE\"
            ORDER BY p.\"designationPromotion\", s.\"numeroSemestre\", u.\"codeUE\", e.\"designationECUE\"
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idsection', $idsection, PDO::PARAM_INT);
        $stmt->bindParam(':idannee',   $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee2',  $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee3',  $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee4',  $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee5',  $idannee,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Met à jour (ou crée) l'entrée de suivi pour un ECUE
    // ---------------------------------------------------------------
    public function updateStatut($idECUE, $idannee, $statut, $observations, $idUser)
    {
        $allowed = ['non_commence', 'en_cours', 'termine'];
        if (!in_array($statut, $allowed)) {
            return false;
        }

        $query = "
            INSERT INTO suivi_cours (\"idECUE\", annee_acad_idannee, statut, observations, date_mise_a_jour, \"idUser\")
            VALUES (:idECUE, :idannee, :statut, :obs, NOW(), :idUser)
            ON DUPLICATE KEY UPDATE
                statut           = VALUES(statut),
                observations     = VALUES(observations),
                date_mise_a_jour = NOW(),
                \"idUser\"           = VALUES(\"idUser\")
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idECUE',  $idECUE,       PDO::PARAM_INT);
        $stmt->bindParam(':idannee', $idannee,       PDO::PARAM_INT);
        $stmt->bindParam(':statut',  $statut,        PDO::PARAM_STR);
        $stmt->bindParam(':obs',     $observations,  PDO::PARAM_STR);
        $stmt->bindParam(':idUser',  $idUser,        PDO::PARAM_INT);
        return $stmt->execute();
    }

    // ---------------------------------------------------------------
    // Statistiques globales toutes sections pour une année
    // ---------------------------------------------------------------
    public function getStatistiquesGlobales($idannee)
    {
        $query = "
            SELECT
                COUNT(DISTINCT e.\"idECUE\")                                              AS total,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'termine'         THEN 1 ELSE 0 END) AS termines,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'en_cours'        THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'non_commence'    THEN 1 ELSE 0 END) AS non_commences
            FROM promotion p
            INNER JOIN orientation o  ON o.idorientation        = p.orientation_idorientation
            INNER JOIN semestre s     ON s.promotion_idpromotion = p.idpromotion
            INNER JOIN ue u           ON u.semestre_idsemestre   = s.idsemestre
            INNER JOIN ecue e         ON e.\"UE_idUE\"               = u.\"idUE\"
            LEFT  JOIN suivi_cours sc ON sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee
            WHERE p.annee_acad_idannee_acad = :idannee2
              AND COALESCE(e.\"estVisible\", 1) = 1
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idannee',  $idannee, PDO::PARAM_INT);
        $stmt->bindParam(':idannee2', $idannee, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Statistiques par section pour une année
    // ---------------------------------------------------------------
    public function getStatistiquesBySection($idannee)
    {
        $query = "
            SELECT
                sec.idsection,
                sec.\"designationSection\",
                COUNT(DISTINCT e.\"idECUE\")                                                          AS total,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'termine'      THEN 1 ELSE 0 END) AS termines,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'en_cours'     THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'non_commence' THEN 1 ELSE 0 END) AS non_commences
            FROM section sec
            INNER JOIN orientation o  ON o.section_idsection      = sec.idsection
            INNER JOIN promotion p    ON p.orientation_idorientation = o.idorientation
                                     AND p.annee_acad_idannee_acad = :idannee
            INNER JOIN semestre s     ON s.promotion_idpromotion   = p.idpromotion
            INNER JOIN ue u           ON u.semestre_idsemestre     = s.idsemestre
            INNER JOIN ecue e         ON e.\"UE_idUE\"                 = u.\"idUE\"
            LEFT  JOIN suivi_cours sc ON sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee2
            WHERE COALESCE(e.\"estVisible\", 1) = 1
            GROUP BY sec.idsection, sec.\"designationSection\"
            ORDER BY sec.\"designationSection\"
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idannee',  $idannee, PDO::PARAM_INT);
        $stmt->bindParam(':idannee2', $idannee, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Statistiques par promotion pour une section et une année
    // ---------------------------------------------------------------
    public function getStatistiquesByPromotion($idsection, $idannee)
    {
        $query = "
            SELECT
                p.idpromotion,
                p.\"designationPromotion\",
                COUNT(DISTINCT e.\"idECUE\")                                                          AS total,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'termine'      THEN 1 ELSE 0 END) AS termines,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'en_cours'     THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN COALESCE(sc.statut,'non_commence') = 'non_commence' THEN 1 ELSE 0 END) AS non_commences
            FROM promotion p
            INNER JOIN orientation o  ON o.idorientation          = p.orientation_idorientation
            INNER JOIN semestre s     ON s.promotion_idpromotion   = p.idpromotion
            INNER JOIN ue u           ON u.semestre_idsemestre     = s.idsemestre
            INNER JOIN ecue e         ON e.\"UE_idUE\"                 = u.\"idUE\"
            LEFT  JOIN suivi_cours sc ON sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee
            WHERE o.section_idsection            = :idsection
              AND p.annee_acad_idannee_acad       = :idannee2
              AND COALESCE(e.\"estVisible\", 1)       = 1
            GROUP BY p.idpromotion, p.\"designationPromotion\"
            ORDER BY p.\"designationPromotion\"
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idsection', $idsection, PDO::PARAM_INT);
        $stmt->bindParam(':idannee',   $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee2',  $idannee,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Vérifie si un utilisateur est responsable d'une section
    // ---------------------------------------------------------------
    public function getUserSections($idUser, $idannee)
    {
        $query = "
            SELECT DISTINCT sec.idsection, sec.\"designationSection\"
            FROM responsable_section rs
            INNER JOIN section sec ON sec.idsection = rs.section_idsection
                                  AND sec.\"idAnnee\"   = :idannee
            WHERE rs.\"idUser\"                    = :idUser
              AND rs.annee_acad_idannee_acad   = :idannee2
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUser',   $idUser,  PDO::PARAM_INT);
        $stmt->bindParam(':idannee',  $idannee, PDO::PARAM_INT);
        $stmt->bindParam(':idannee2', $idannee, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Toutes les années académiques (pour le switch)
    // ---------------------------------------------------------------
    public function getAnneesAcad()
    {
        $stmt = $this->db->query("SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Année académique active
    // ---------------------------------------------------------------
    public function getAnneeActive()
    {
        $stmt = $this->db->query("SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $stmt2 = $this->db->query("SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1");
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        }
        return $row;
    }

    // ---------------------------------------------------------------
    // Récupère toutes les sections filtrées par année académique
    // ---------------------------------------------------------------
    public function getAllSections($idannee = null)
    {
        if ($idannee) {
            $stmt = $this->db->prepare("SELECT idsection, \"designationSection\" FROM section WHERE \"idAnnee\" = :idannee ORDER BY designationSection");
            $stmt->bindParam(':idannee', $idannee, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->query("SELECT idsection, \"designationSection\" FROM section ORDER BY designationSection");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Ajoute une orientation si inexistante
    // ---------------------------------------------------------------
    public function getOrCreateOrientation($designationOrientation, $idsection)
    {
        $stmt = $this->db->prepare("SELECT idorientation FROM orientation WHERE \"designationOrientation\" = :nom AND section_idsection = :sec LIMIT 1");
        $stmt->bindParam(':nom', $designationOrientation);
        $stmt->bindParam(':sec', $idsection, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['idorientation'];
        }
        $ins = $this->db->prepare("INSERT INTO orientation (\"designationOrientation\", \"dateCreation\", section_idsection) VALUES (:nom, NOW(), :sec)");
        $ins->bindParam(':nom', $designationOrientation);
        $ins->bindParam(':sec', $idsection, PDO::PARAM_INT);
        $ins->execute();
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Ajoute une promotion
    // ---------------------------------------------------------------
    public function addPromotion($designationPromotion, $cycle, $idorientation, $idannee, $est_terminale = 0)
    {
        $stmt = $this->db->prepare("
            INSERT INTO promotion (\"designationPromotion\", \"dateCreation\", cycle, orientation_idorientation, annee_acad_idannee_acad, est_terminale)
            VALUES (:nom, NOW(), :cycle, :idorientation, :idannee, :est_terminale)
        ");
        $stmt->bindParam(':nom',          $designationPromotion);
        $stmt->bindParam(':cycle',        $cycle);
        $stmt->bindParam(':idorientation',$idorientation, PDO::PARAM_INT);
        $stmt->bindParam(':idannee',      $idannee,       PDO::PARAM_INT);
        $stmt->bindParam(':est_terminale',$est_terminale, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Ajoute un semestre
    // ---------------------------------------------------------------
    public function addSemestre($numeroSemestre, $idpromotion)
    {
        $stmt = $this->db->prepare("
            INSERT INTO semestre (\"numeroSemestre\", \"dateEnregistrement\", promotion_idpromotion)
            VALUES (:num, NOW(), :idpromotion)
        ");
        $stmt->bindParam(':num',        $numeroSemestre);
        $stmt->bindParam(':idpromotion',$idpromotion, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Ajoute une UE
    // ---------------------------------------------------------------
    public function addUE($codeUE, $designationUE, $description, $idsemestre)
    {
        $stmt = $this->db->prepare("
            INSERT INTO ue (\"codeUE\", \"designationUE\", description, semestre_idsemestre)
            VALUES (:code, :nom, :desc, :idsemestre)
        ");
        $stmt->bindParam(':code',      $codeUE);
        $stmt->bindParam(':nom',       $designationUE);
        $stmt->bindParam(':desc',      $description);
        $stmt->bindParam(':idsemestre',$idsemestre, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Ajoute un ECUE
    // ---------------------------------------------------------------
    public function addECUE($designationECUE, $CMI, $TD, $TP, $idUE, $idCreateur)
    {
        $stmt = $this->db->prepare("
            INSERT INTO ecue (\"designationECUE\", CMI, TD, TP, \"UE_idUE\", \"idCreateur\", \"estVisible\")
            VALUES (:nom, :cmi, :td, :tp, :idUE, :idCreateur, 1)
        ");
        $stmt->bindParam(':nom',       $designationECUE);
        $stmt->bindParam(':cmi',       $CMI);
        $stmt->bindParam(':td',        $TD);
        $stmt->bindParam(':tp',        $TP);
        $stmt->bindParam(':idUE',      $idUE,       PDO::PARAM_INT);
        $stmt->bindParam(':idCreateur',$idCreateur, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Semestres d'une promotion (pour le wizard)
    // ---------------------------------------------------------------
    public function getSemestresByPromotion($idpromotion)
    {
        $stmt = $this->db->prepare("SELECT * FROM semestre WHERE promotion_idpromotion = :id ORDER BY numeroSemestre");
        $stmt->bindParam(':id', $idpromotion, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // UEs d'un semestre (pour le wizard)
    // ---------------------------------------------------------------
    public function getUEsBySemestre($idsemestre)
    {
        $stmt = $this->db->prepare("SELECT * FROM ue WHERE semestre_idsemestre = :id ORDER BY codeUE");
        $stmt->bindParam(':id', $idsemestre, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Orientations d'une section (pour le wizard)
    // ---------------------------------------------------------------
    public function getOrientationsBySection($idsection)
    {
        $stmt = $this->db->prepare("SELECT * FROM orientation WHERE section_idsection = :id ORDER BY designationOrientation");
        $stmt->bindParam(':id', $idsection, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Toutes les promotions d'une section pour une année (wizard)
    // ---------------------------------------------------------------
    public function getPromotionsBySection($idsection, $idannee)
    {
        $stmt = $this->db->prepare("
            SELECT p.idpromotion, p.\"designationPromotion\"
            FROM promotion p
            INNER JOIN orientation o ON o.idorientation = p.orientation_idorientation
            WHERE o.section_idsection          = :idsection
              AND p.annee_acad_idannee_acad     = :idannee
            ORDER BY p.\"designationPromotion\"
        ");
        $stmt->bindParam(':idsection', $idsection, PDO::PARAM_INT);
        $stmt->bindParam(':idannee',   $idannee,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------
    // Détail statut par section pour le tableau de bord détaillé
    // ---------------------------------------------------------------
    public function getDetailBySection($idsection, $idannee)
    {
        $query = "
            SELECT
                p.\"designationPromotion\",
                p.cycle,
                s.\"numeroSemestre\",
                u.\"codeUE\",
                u.\"designationUE\",
                e.\"designationECUE\",
                e.CMI, e.TD, e.TP,
                COALESCE(
                    (SELECT sc.statut FROM suivi_cours sc
                     WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee
                     LIMIT 1),
                    'non_commence'
                ) AS statut,
                (SELECT sc.observations FROM suivi_cours sc
                 WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee2
                 LIMIT 1) AS observations,
                (SELECT sc.date_mise_a_jour FROM suivi_cours sc
                 WHERE sc.\"idECUE\" = e.\"idECUE\" AND sc.annee_acad_idannee = :idannee3
                 LIMIT 1) AS date_mise_a_jour
            FROM promotion p
            INNER JOIN orientation o  ON o.idorientation          = p.orientation_idorientation
            INNER JOIN semestre s     ON s.promotion_idpromotion   = p.idpromotion
            INNER JOIN ue u           ON u.semestre_idsemestre     = s.idsemestre
            INNER JOIN ecue e         ON e.\"UE_idUE\"                 = u.\"idUE\"
            WHERE o.section_idsection          = :idsection
              AND p.annee_acad_idannee_acad     = :idannee4
              AND COALESCE(e.\"estVisible\", 1)     = 1
            GROUP BY e.\"idECUE\", p.idpromotion, s.idsemestre, u.\"idUE\"
            ORDER BY p.\"designationPromotion\", s.\"numeroSemestre\", u.\"codeUE\", e.\"designationECUE\"
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idsection', $idsection, PDO::PARAM_INT);
        $stmt->bindParam(':idannee',   $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee2',  $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee3',  $idannee,   PDO::PARAM_INT);
        $stmt->bindParam(':idannee4',  $idannee,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
