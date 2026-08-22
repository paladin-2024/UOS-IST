<?php
class Stage {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Get user's responsibilities (promotions they manage)
    public function getUserResponsibilities($userId) {
        $sql = "SELECT rs.*, s.\"designationSection\", p.\"designationPromotion\" as promotionDesignation
                FROM responsable_section rs
                JOIN section s ON rs.idsection = s.idsection
                JOIN promotion p ON rs.idpromotion = p.idpromotion
                WHERE rs.\"idUser\" = :userId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetchAll();
    }

    // Get promotions for user in a year
    public function getUserPromotions($userId, $yearId) {
        $sql = "SELECT DISTINCT p.*
                FROM promotion p
                JOIN responsable_section rs ON p.idpromotion = rs.idpromotion
                WHERE rs.\"idUser\" = :userId AND rs.annee_acad_idannee_acad = :yearId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId, 'yearId' => $yearId]);
        return $stmt->fetchAll();
    }

    // Get active academic year
    public function getActiveAcademicYear() {
        $sql = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }

    // Get all academic years
    public function getAcademicYears() {
        $sql = "SELECT * FROM annee_acad ORDER BY designation DESC";
        return $this->db->query($sql)->fetchAll();
    }

    // Get promotion details
    public function getPromotion($promotionId) {
        $sql = "SELECT * FROM promotion WHERE idpromotion = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $promotionId]);
        return $stmt->fetch();
    }

    // Get students in stage for a promotion
    public function getStudentsInStage($promotionId, $yearId) {
        $sql = "SELECT s.*, e.noms, e.matricule,
                       enc.nom as encadreur_nom, lec.nom as lecteur_nom,
                       s.cote_lecteur, s.cote_entreprise, s.lieu_stage, s.nom_stage,
                       p.\"designationPromotion\" as promotion
                FROM stage_assignments s
                JOIN etudiant e ON s.idetudiant = e.idetudiant
                LEFT JOIN enseignant enc ON s.idencadreur = enc.idenseignant
                LEFT JOIN enseignant lec ON s.idlecteur = lec.idenseignant
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                WHERE e.promotion_idpromotion = :promotionId AND e.annee_acad_idannee_acad = :yearId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['promotionId' => $promotionId, 'yearId' => $yearId]);
        return $stmt->fetchAll();
    }

    // Get required fees for promotion
    public function getRequiredFeesForPromotion($promotionId, $academicYearId = null) {
        $sql = "SELECT DISTINCT f.* FROM frais f
        JOIN stage_required_fees srf ON f.id = srf.idfrais
        WHERE srf.idpromotion = :promotionId";

        $params = ['promotionId' => $promotionId];

        if ($academicYearId) {
            $sql .= " AND f.annee_acad_id = :academicYearId";
            $params['academicYearId'] = $academicYearId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Legacy method for backward compatibility (returns first fee)
    public function getRequiredFeeForPromotion($promotionId) {
        $fees = $this->getRequiredFeesForPromotion($promotionId);
        return $fees ? $fees[0] : null;
    }

    // Set required fees for promotion (replace existing)
    public function setRequiredFeesForPromotion($promotionId, $feeIds) {
        // First, remove existing
        $sql = "DELETE FROM stage_required_fees WHERE idpromotion = :promotionId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['promotionId' => $promotionId]);

        // Then, insert new ones
        if (!empty($feeIds)) {
            $sql = "INSERT INTO stage_required_fees (idpromotion, idfrais) VALUES ";
            $placeholders = [];
            $params = ['promotionId' => $promotionId];
            foreach ($feeIds as $index => $feeId) {
                $placeholders[] = "(:promotionId, :feeId{$index})";
                $params["feeId{$index}"] = $feeId;
            }
            $sql .= implode(', ', $placeholders);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    // Get eligible students for stage (paid all required fees)
    public function getEligibleStudentsForStage($promotionId, $requiredFees) {
        if (empty($requiredFees)) {
            // If no fees required, all students in promotion are eligible
            $sql = "SELECT idetudiant, noms, matricule FROM etudiant WHERE promotion_idpromotion = ? AND est_actif=1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$promotionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $feeIds = array_column($requiredFees, 'id');
        $placeholders = str_repeat('?,', count($feeIds) - 1) . '?';

        // Récupérer tous les étudiants de la promotion
        $sql = "SELECT DISTINCT e.idetudiant, e.noms, e.matricule
                FROM etudiant e
                WHERE e.promotion_idpromotion = ?
                AND e.est_actif = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$promotionId]);
        $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eligibleStudents = [];

        // Pour chaque étudiant, vérifier qu'il a payé tous les frais requis
        foreach ($allStudents as $student) {
            $isEligible = true;

            foreach ($feeIds as $feeId) {
                // Récupérer l'affectation du frais pour cet étudiant
                $sqlAffectation = "SELECT af.id, af.est_exempte,
                                          CASE WHEN af.montant_specifique > 0 THEN af.montant_specifique ELSE f.montant END as montant_total
                                   FROM affectation_frais af
                                   JOIN frais f ON af.frais_id = f.id
                                   WHERE af.frais_id = ?
                                   AND (af.matricule_etudiant = ? OR (af.promotion_id = ? AND af.matricule_etudiant IS NULL))
                                   LIMIT 1";

                $stmtAff = $this->db->prepare($sqlAffectation);
                $stmtAff->execute([$feeId, $student['matricule'], $promotionId]);
                $affectation = $stmtAff->fetch(PDO::FETCH_ASSOC);

                // Si le frais n'est pas affecté à cet étudiant, il n'est pas éligible
                if (!$affectation) {
                    $isEligible = false;
                    break;
                }

                // Si l'étudiant est exempté, continuer au frais suivant
                if ($affectation['est_exempte'] == 1) {
                    continue;
                }

                // Calculer le montant payé pour ce frais par cet étudiant
                $sqlPaiement = "SELECT COALESCE(SUM(pf.montant), 0) as montant_paye
                                FROM paiements_frais pf
                                WHERE pf.affectation_id = ?
                                AND pf.matricule_etudiant = ?
                                AND pf.est_confirme = 1";

                $stmtPay = $this->db->prepare($sqlPaiement);
                $stmtPay->execute([$affectation['id'], $student['matricule']]);
                $paiement = $stmtPay->fetch(PDO::FETCH_ASSOC);

                // Si le montant payé est inférieur au montant total, l'étudiant n'est pas éligible
                if ($paiement['montant_paye'] < $affectation['montant_total']) {
                    $isEligible = false;
                    break;
                }
            }

            // Si l'étudiant a payé tous les frais requis, l'ajouter à la liste des éligibles
            if ($isEligible) {
                $eligibleStudents[] = $student;
            }
        }

        return $eligibleStudents;
    }

    // Get available supervisors (agents of type 'Enseignant')
    public function getAvailableSupervisors() {
    try {
        $sql = "SELECT a.\"idAgent\", a.noms as nom_complet
        FROM agent a
        WHERE a.type_agent = 'Enseignant'
        ORDER BY a.noms ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getAvailableSupervisors result: " . print_r($result, true));
            return $result;
        } catch (Exception $e) {
            error_log("Error in getAvailableSupervisors: " . $e->getMessage());
            return [];
        }
    }

    // Assign students to supervisor
    public function assignStudentsToSupervisor($supervisorId, $studentIds, $location = null) {
        $sql = "INSERT INTO stage_assignments (idetudiant, idencadreur, lieu_stage, nom_stage)
                VALUES (:studentId, :supervisorId, :location, CONCAT('Stage ', (SELECT designation FROM promotion WHERE idpromotion = (SELECT promotion_idpromotion FROM etudiant WHERE idetudiant = :studentId))))
                ON DUPLICATE KEY UPDATE idencadreur = :supervisorId, lieu_stage = :location";
        $stmt = $this->db->prepare($sql);

        foreach ($studentIds as $studentId) {
            $stmt->execute([
                'studentId' => $studentId,
                'supervisorId' => $supervisorId,
                'location' => $location
            ]);
        }
    }

    // Get submitted reports
    public function getSubmittedReports() {
        $sql = "SELECT s.*, e.noms, p.\"designationPromotion\" as promotion
                FROM stage_assignments s
                JOIN etudiant e ON s.idetudiant = e.idetudiant
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                WHERE s.rapport_path IS NOT NULL AND s.idlecteur IS NULL";
        return $this->db->query($sql)->fetchAll();
    }

    // Assign reader to report
    public function assignReaderToReport($stageId, $readerId) {
        $sql = "UPDATE stage_assignments SET idlecteur = :readerId WHERE idstage = :stageId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['readerId' => $readerId, 'stageId' => $stageId]);
    }

    // Get reports assigned to reader
    public function getReportsForReader($readerId) {
        $sql = "SELECT s.*, e.noms, p.\"designationPromotion\" as promotion
                FROM stage_assignments s
                JOIN etudiant e ON s.idetudiant = e.idetudiant
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                WHERE s.idlecteur = :readerId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['readerId' => $readerId]);
        return $stmt->fetchAll();
    }

    // Update reader's grade
    public function updateReaderGrade($stageId, $grade) {
        $sql = "UPDATE stage_assignments SET cote_lecteur = :grade WHERE idstage = :stageId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['grade' => $grade, 'stageId' => $stageId]);
    }

    // Update company grade (for supervisors)
    public function updateCompanyGrade($stageId, $grade) {
        $sql = "UPDATE stage_assignments SET cote_entreprise = :grade WHERE idstage = :stageId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['grade' => $grade, 'stageId' => $stageId]);
    }

    // Get student's stages
    public function getStudentStages($studentId, $yearId) {
        $sql = "SELECT s.*, p.\"designationPromotion\" as promotion, p.est_terminale,
        enc.nom as encadreur_nom, lec.nom as lecteur_nom
        FROM stage_assignments s
        JOIN etudiant e ON s.idetudiant = e.idetudiant
        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
        LEFT JOIN enseignant enc ON s.idencadreur = enc.idenseignant
        LEFT JOIN enseignant lec ON s.idlecteur = lec.idenseignant
        WHERE s.idetudiant = :studentId AND e.annee_acad_idannee_acad = :yearId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['studentId' => $studentId, 'yearId' => $yearId]);
        return $stmt->fetchAll();
    }

    // Check if student has paid stage fee
    public function hasStudentPaidStageFee($studentId, $stageId) {
    // Get the required fee for the stage's promotion
    $sql = "SELECT f.id FROM frais f
    JOIN affectation_frais af ON f.id = af.frais_id
    WHERE af.promotion_id = (SELECT promotion_idpromotion FROM etudiant WHERE idetudiant = :studentId)
    AND f.designation LIKE '%stage%'
            LIMIT 1";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['studentId' => $studentId]);
        $fee = $stmt->fetch();

        if (!$fee) return true; // No fee required

    // Check if student has paid this fee using paiements_frais table
    $sql = "SELECT COUNT(*) FROM paiements_frais pf
            JOIN affectation_frais af ON pf.affectation_id = af.id
            WHERE af.frais_id = :feeId AND af.etudiant_id = :studentId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['studentId' => $studentId, 'feeId' => $fee['id']]);
        return $stmt->fetchColumn() > 0;
    }

    // Upload report
    public function uploadReport($stageId, $filePath) {
        $sql = "UPDATE stage_assignments SET rapport_path = :path WHERE idstage = :stageId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['path' => $filePath, 'stageId' => $stageId]);
    }

    // Get teacher by user ID
    public function getTeacherByUserId($userId) {
        $sql = "SELECT e.* FROM enseignant e JOIN users u ON e.\"idUser\" = u.\"idUser\" WHERE u.\"idUser\" = :userId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userId' => $userId]);
        return $stmt->fetch();
    }

    // Get stages for supervisor
    public function getStagesForSupervisor($teacherId) {
    $sql = "SELECT s.*, e.noms, p.\"designationPromotion\" as promotion, p.est_terminale,
    ag.noms as lecteur_nom
    FROM stage_assignments s
    JOIN etudiant e ON s.idetudiant = e.idetudiant
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN enseignant lec ON s.idlecteur = lec.idenseignant
    LEFT JOIN agent ag ON lec.\"idAgent\" = ag.\"idAgent\"
    WHERE s.idencadreur = :teacherId";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['teacherId' => $teacherId]);
        return $stmt->fetchAll();
    }
}
?>
