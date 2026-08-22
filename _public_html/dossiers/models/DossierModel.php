<?php
/**
 * Modèle principal pour la gestion des dossiers numériques étudiants
 */
class DossierModel {
    private $db;

    public function __construct() {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // ── Authentification ──

    /**
     * Authentifier un étudiant finaliste par matricule uniquement
     */
    public function authenticateStudent($matricule) {
        try {
            $query = "SELECT e.*, p.\"designationPromotion\", p.cycle, p.est_terminale,
                             o.\"designationOrientation\", o.idorientation,
                             s.\"designationSection\", s.idsection,
                             aa.designation as annee_designation
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                      WHERE e.matricule = :matricule AND e.est_actif = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->execute();
            $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$etudiant) {
                return false;
            }

            if (!$etudiant['est_terminale']) {
                return ['error' => 'not_finaliste'];
            }

            return $etudiant;
        } catch (Exception $e) {
            error_log("DossierModel::authenticateStudent - " . $e->getMessage());
            return false;
        }
    }

    // ── Dossier CRUD ──

    /**
     * Récupérer ou créer le dossier d'un étudiant pour une année académique
     */
    public function getOrCreateDossier($etudiantId, $anneeAcadId) {
        try {
            $query = "SELECT * FROM dn_dossier 
                      WHERE etudiant_idetudiant = :etudiantId 
                      AND annee_acad_idannee_acad = :anneeAcadId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($dossier) {
                return $dossier;
            }

            $query = "INSERT INTO dn_dossier (etudiant_idetudiant, annee_acad_idannee_acad, statut)
                      VALUES (:etudiantId, :anneeAcadId, 'en_cours')";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();

            $dossierId = $this->db->lastInsertId('dn_dossier_id_seq');
            return $this->getDossierById($dossierId);
        } catch (Exception $e) {
            error_log("DossierModel::getOrCreateDossier - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un dossier par son ID
     */
    public function getDossierById($dossierId) {
        try {
            $query = "SELECT d.*, e.noms, e.matricule, e.photo,
                             p.\"designationPromotion\", p.cycle,
                             o.\"designationOrientation\",
                             s.\"designationSection\",
                             aa.designation as annee_designation
                      FROM dn_dossier d
                      JOIN etudiant e ON d.etudiant_idetudiant = e.idetudiant
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
                      WHERE d.id = :dossierId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getDossierById - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer le dossier d'un étudiant
     */
    public function getDossierByEtudiant($etudiantId, $anneeAcadId) {
        try {
            $query = "SELECT d.*, e.noms, e.matricule, e.photo,
                             p.\"designationPromotion\", p.cycle,
                             o.\"designationOrientation\",
                             s.\"designationSection\",
                             aa.designation as annee_designation
                      FROM dn_dossier d
                      JOIN etudiant e ON d.etudiant_idetudiant = e.idetudiant
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
                      WHERE d.etudiant_idetudiant = :etudiantId 
                      AND d.annee_acad_idannee_acad = :anneeAcadId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getDossierByEtudiant - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour le statut d'un dossier
     */
    public function updateDossierStatut($dossierId, $statut, $commentaire = null, $validateurId = null) {
        try {
            $query = "UPDATE dn_dossier SET statut = :statut, commentaire_admin = :commentaire";
            if (in_array($statut, ['valide', 'rejete'])) {
                $query .= ", date_validation = NOW(), validateur_id = :validateurId";
            }
            $query .= " WHERE id = :dossierId";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            if (in_array($statut, ['valide', 'rejete'])) {
                $stmt->bindParam(':validateurId', $validateurId, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::updateDossierStatut - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculer et mettre à jour le pourcentage de complétion
     */
    public function updateCompletionPercentage($dossierId) {
        try {
            // Récupérer le cycle de l'étudiant
            $dossier = $this->getDossierById($dossierId);
            if (!$dossier) return false;

            $cycle = $dossier['cycle'];

            // Compter les documents obligatoires requis
            $query = "SELECT COUNT(*) as total FROM dn_type_document 
                      WHERE est_actif = 1 AND est_obligatoire = 1 
                      AND :cycle = ANY(string_to_array(cycle_requis, ','))";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->execute();
            $totalRequired = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($totalRequired == 0) {
                $percentage = 100;
            } else {
                // Compter les documents obligatoires uploadés
                $query = "SELECT COUNT(DISTINCT du.type_document_id) as uploaded
                          FROM dn_document du
                          JOIN dn_type_document td ON du.type_document_id = td.id
                          WHERE du.dossier_id = :dossierId
                          AND td.est_obligatoire = 1
                          AND td.est_actif = 1
                          AND :cycle = ANY(string_to_array(td.cycle_requis, ','))
                          AND du.statut != 'rejete'";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
                $stmt->bindParam(':cycle', $cycle);
                $stmt->execute();
                $uploaded = $stmt->fetch(PDO::FETCH_ASSOC)['uploaded'];

                $percentage = round(($uploaded / $totalRequired) * 100, 2);
            }

            $query = "UPDATE dn_dossier SET pourcentage_completion = :pct WHERE id = :dossierId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':pct', $percentage);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::updateCompletionPercentage - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Soumettre un dossier pour validation
     */
    public function submitDossier($dossierId) {
        try {
            $query = "UPDATE dn_dossier SET statut = 'soumis', date_soumission = NOW() 
                      WHERE id = :dossierId AND statut = 'en_cours'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::submitDossier - " . $e->getMessage());
            return false;
        }
    }

    // ── Types de documents ──

    /**
     * Récupérer les documents requis pour un cycle donné
     */
    public function getRequiredDocuments($cycle) {
        try {
            $query = "SELECT * FROM dn_type_document 
                      WHERE est_actif = 1 AND :cycle = ANY(string_to_array(cycle_requis, ',')) 
                      ORDER BY ordre_affichage";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getRequiredDocuments - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les types de documents avec leur statut d'upload
     */
    public function getDocumentsWithStatus($dossierId, $cycle) {
        try {
            // Récupérer les types de documents requis
            $query = "SELECT * FROM dn_type_document 
                      WHERE est_actif = 1 AND :cycle = ANY(string_to_array(cycle_requis, ',')) 
                      ORDER BY ordre_affichage";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':cycle', $cycle);
            $stmt->execute();
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer tous les uploads pour ce dossier
            $query = "SELECT du.*, td.designation as type_designation
                      FROM dn_document du
                      JOIN dn_type_document td ON du.type_document_id = td.id
                      WHERE du.dossier_id = :dossierId
                      ORDER BY du.date_upload DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->execute();
            $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Grouper les uploads par type
            $uploadsByType = [];
            foreach ($uploads as $up) {
                $uploadsByType[$up['type_document_id']][] = $up;
            }

            // Construire le résultat
            $result = [];
            foreach ($types as $type) {
                $typeUploads = $uploadsByType[$type['id']] ?? [];
                if (empty($typeUploads)) {
                    $row = $type;
                    $row['upload_id'] = null;
                    $row['uploads'] = [];
                    $result[] = $row;
                } else {
                    $row = $type;
                    $row['upload_id'] = $typeUploads[0]['id'];
                    $row['nom_fichier_original'] = $typeUploads[0]['nom_fichier_original'];
                    $row['nom_fichier_stocke'] = $typeUploads[0]['nom_fichier_stocke'];
                    $row['chemin_fichier'] = $typeUploads[0]['chemin_fichier'];
                    $row['taille_fichier'] = $typeUploads[0]['taille_fichier'];
                    $row['type_mime'] = $typeUploads[0]['type_mime'];
                    $row['upload_statut'] = $typeUploads[0]['statut'];
                    $row['date_upload'] = $typeUploads[0]['date_upload'];
                    $row['commentaire_validation'] = $typeUploads[0]['commentaire_validation'];
                    $row['upload_date_validation'] = $typeUploads[0]['date_validation'];
                    $row['uploads'] = $typeUploads;
                    $result[] = $row;
                }
            }

            return $result;
        } catch (Exception $e) {
            error_log("DossierModel::getDocumentsWithStatus - " . $e->getMessage());
            return [];
        }
    }

    // ── Upload de documents ──

    /**
     * Enregistrer un document uploadé
     */
    public function uploadDocument($dossierId, $typeDocumentId, $fichierOriginal, $fichierStocke, $chemin, $taille, $typeMime) {
        try {
            // Supprimer un ancien document rejeté pour le même type
            $query = "SELECT id, chemin_fichier FROM dn_document 
                      WHERE dossier_id = :dossierId AND type_document_id = :typeId AND statut = 'rejete'";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->bindParam(':typeId', $typeDocumentId, PDO::PARAM_INT);
            $stmt->execute();
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($old) {
                if (file_exists($old['chemin_fichier'])) {
                    unlink($old['chemin_fichier']);
                }
                $delStmt = $this->db->prepare("DELETE FROM dn_document WHERE id = :id");
                $delStmt->bindParam(':id', $old['id'], PDO::PARAM_INT);
                $delStmt->execute();
            }

            $query = "INSERT INTO dn_document 
                      (dossier_id, type_document_id, nom_fichier_original, nom_fichier_stocke, 
                       chemin_fichier, taille_fichier, type_mime, statut)
                      VALUES (:dossierId, :typeId, :original, :stocke, :chemin, :taille, :mime, 'en_attente')";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->bindParam(':typeId', $typeDocumentId, PDO::PARAM_INT);
            $stmt->bindParam(':original', $fichierOriginal);
            $stmt->bindParam(':stocke', $fichierStocke);
            $stmt->bindParam(':chemin', $chemin);
            $stmt->bindParam(':taille', $taille, PDO::PARAM_INT);
            $stmt->bindParam(':mime', $typeMime);
            $stmt->execute();

            return $this->db->lastInsertId('dn_document_id_seq');
        } catch (Exception $e) {
            error_log("DossierModel::uploadDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un document par ID
     */
    public function getDocument($documentId) {
        try {
            $query = "SELECT du.*, td.code, td.designation as type_designation,
                             d.etudiant_idetudiant
                      FROM dn_document du
                      JOIN dn_type_document td ON du.type_document_id = td.id
                      JOIN dn_dossier d ON du.dossier_id = d.id
                      WHERE du.id = :documentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer tous les documents d'un dossier
     */
    public function getDocumentsByDossier($dossierId) {
        try {
            $query = "SELECT du.*, td.code, td.designation as type_designation
                      FROM dn_document du
                      JOIN dn_type_document td ON du.type_document_id = td.id
                      WHERE du.dossier_id = :dossierId
                      ORDER BY td.ordre_affichage";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getDocumentsByDossier - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprimer un document (retourne le chemin du fichier pour suppression physique)
     */
    public function deleteDocument($documentId) {
        try {
            $query = "SELECT chemin_fichier, dossier_id FROM dn_document WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
            $stmt->execute();
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) return false;

            $delStmt = $this->db->prepare("DELETE FROM dn_document WHERE id = :id");
            $delStmt->bindParam(':id', $documentId, PDO::PARAM_INT);
            $delStmt->execute();

            return $doc;
        } catch (Exception $e) {
            error_log("DossierModel::deleteDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valider ou rejeter un document
     */
    public function validateDocument($documentId, $statut, $commentaire, $validateurId) {
        try {
            $query = "UPDATE dn_document 
                      SET statut = :statut, commentaire_validation = :commentaire, 
                          validateur_id = :validateurId, date_validation = NOW()
                      WHERE id = :documentId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':commentaire', $commentaire);
            $stmt->bindParam(':validateurId', $validateurId, PDO::PARAM_INT);
            $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::validateDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Valider/rejeter plusieurs documents en une fois
     * @param array $documentIds Liste des IDs de documents
     */
    public function validateDocumentsBulk($documentIds, $statut, $commentaire, $validateurId) {
        try {
            if (empty($documentIds)) return 0;
            $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
            $query = "UPDATE dn_document 
                      SET statut = ?, commentaire_validation = ?, 
                          validateur_id = ?, date_validation = NOW()
                      WHERE id IN ($placeholders)";
            $stmt = $this->db->prepare($query);
            $params = [$statut, $commentaire, $validateurId];
            foreach ($documentIds as $id) {
                $params[] = intval($id);
            }
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("DossierModel::validateDocumentsBulk - " . $e->getMessage());
            return 0;
        }
    }

    // ── Requêtes admin ──

    /**
     * Récupérer les finalistes avec leurs dossiers
     */
    public function getFinalistesWithDossiers($anneeAcadId, $sectionId = null, $orientationId = null, $statut = null, $limit = null, $offset = 0) {
        try {
            $query = "SELECT e.idetudiant, e.matricule, e.noms, e.photo,
                             p.\"designationPromotion\", p.cycle,
                             o.\"designationOrientation\", o.idorientation,
                             s.\"designationSection\", s.idsection,
                             d.id as dossier_id, d.statut as dossier_statut, 
                             d.pourcentage_completion, d.date_soumission, d.date_creation as dossier_date_creation
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN dn_dossier d ON e.idetudiant = d.etudiant_idetudiant 
                           AND d.annee_acad_idannee_acad = :anneeAcadId
                      WHERE p.est_terminale = 1 AND e.est_actif = 1 
                      AND e.annee_acad_idannee_acad = :anneeAcadId2";

            $params = [':anneeAcadId' => $anneeAcadId, ':anneeAcadId2' => $anneeAcadId];

            if ($sectionId) {
                $query .= " AND s.idsection = :sectionId";
                $params[':sectionId'] = $sectionId;
            }
            if ($orientationId) {
                $query .= " AND o.idorientation = :orientationId";
                $params[':orientationId'] = $orientationId;
            }
            if ($statut) {
                if ($statut === 'non_commence') {
                    $query .= " AND d.id IS NULL";
                } else {
                    $query .= " AND d.statut = :statut";
                    $params[':statut'] = $statut;
                }
            }

            $query .= " ORDER BY e.noms ASC";

            if ($limit !== null) {
                $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
            }

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getFinalistesWithDossiers - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compter les finalistes avec filtres
     */
    public function countFinalistesWithDossiers($anneeAcadId, $sectionId = null, $orientationId = null, $statut = null) {
        try {
            $query = "SELECT COUNT(*) as total
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN dn_dossier d ON e.idetudiant = d.etudiant_idetudiant 
                           AND d.annee_acad_idannee_acad = :anneeAcadId
                      WHERE p.est_terminale = 1 AND e.est_actif = 1 
                      AND e.annee_acad_idannee_acad = :anneeAcadId2";

            $params = [':anneeAcadId' => $anneeAcadId, ':anneeAcadId2' => $anneeAcadId];

            if ($sectionId) {
                $query .= " AND s.idsection = :sectionId";
                $params[':sectionId'] = $sectionId;
            }
            if ($orientationId) {
                $query .= " AND o.idorientation = :orientationId";
                $params[':orientationId'] = $orientationId;
            }
            if ($statut) {
                if ($statut === 'non_commence') {
                    $query .= " AND d.id IS NULL";
                } else {
                    $query .= " AND d.statut = :statut";
                    $params[':statut'] = $statut;
                }
            }

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            return intval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        } catch (Exception $e) {
            error_log("DossierModel::countFinalistesWithDossiers - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Statistiques des dossiers
     */
    public function getDossierStats($anneeAcadId, $sectionId = null) {
        try {
            $where = "p.est_terminale = 1 AND e.est_actif = 1 AND e.annee_acad_idannee_acad = :anneeAcadId";
            $params = [':anneeAcadId' => $anneeAcadId];

            if ($sectionId) {
                $where .= " AND s.idsection = :sectionId";
                $params[':sectionId'] = $sectionId;
            }

            // Total finalistes
            $query = "SELECT COUNT(*) as total FROM etudiant e 
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      WHERE $where";
            $stmt = $this->db->prepare($query);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $totalFinalistes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Stats par statut
            $query = "SELECT d.statut, COUNT(*) as nb, AVG(d.pourcentage_completion) as avg_pct
                      FROM dn_dossier d
                      JOIN etudiant e ON d.etudiant_idetudiant = e.idetudiant
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      WHERE d.annee_acad_idannee_acad = :anneeAcadId AND $where
                      GROUP BY d.statut";
            $params2 = $params;
            $params2[':anneeAcadId'] = $anneeAcadId;
            $stmt = $this->db->prepare($query);
            foreach ($params2 as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $statusStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stats = [
                'total_finalistes' => $totalFinalistes,
                'dossiers_crees' => 0,
                'dossiers_soumis' => 0,
                'dossiers_valides' => 0,
                'dossiers_rejetes' => 0,
                'dossiers_en_cours' => 0,
                'moyenne_completion' => 0
            ];

            $totalPct = 0;
            $totalDossiers = 0;
            foreach ($statusStats as $row) {
                $totalDossiers += $row['nb'];
                $totalPct += $row['avg_pct'] * $row['nb'];
                switch ($row['statut']) {
                    case 'soumis': $stats['dossiers_soumis'] = $row['nb']; break;
                    case 'valide': $stats['dossiers_valides'] = $row['nb']; break;
                    case 'rejete': $stats['dossiers_rejetes'] = $row['nb']; break;
                    case 'en_cours': $stats['dossiers_en_cours'] = $row['nb']; break;
                    case 'incomplet': $stats['dossiers_en_cours'] += $row['nb']; break;
                }
            }
            $stats['dossiers_crees'] = $totalDossiers;
            $stats['moyenne_completion'] = $totalDossiers > 0 ? round($totalPct / $totalDossiers, 1) : 0;

            return $stats;
        } catch (Exception $e) {
            error_log("DossierModel::getDossierStats - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Rechercher des étudiants finalistes
     */
    public function searchEtudiants($search, $anneeAcadId) {
        try {
            $query = "SELECT e.idetudiant, e.matricule, e.noms,
                             p.\"designationPromotion\", p.cycle,
                             o.\"designationOrientation\",
                             s.\"designationSection\",
                             d.id as dossier_id, d.statut as dossier_statut, d.pourcentage_completion
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN dn_dossier d ON e.idetudiant = d.etudiant_idetudiant 
                           AND d.annee_acad_idannee_acad = :anneeAcadId
                      WHERE p.est_terminale = 1 AND e.est_actif = 1
                      AND e.annee_acad_idannee_acad = :anneeAcadId2
                      AND (e.noms LIKE :search OR e.matricule LIKE :search)
                      ORDER BY e.noms ASC";
            $stmt = $this->db->prepare($query);
            $searchParam = "%$search%";
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId2', $anneeAcadId, PDO::PARAM_INT);
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::searchEtudiants - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les sections ayant des finalistes
     */
    public function getSections($anneeAcadId) {
        try {
            $query = "SELECT DISTINCT s.idsection, s.\"designationSection\"
                      FROM section s
                      JOIN orientation o ON s.idsection = o.section_idsection
                      JOIN promotion p ON o.idorientation = p.orientation_idorientation
                      JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
                      WHERE p.est_terminale = 1 AND e.est_actif = 1
                      AND e.annee_acad_idannee_acad = :anneeAcadId
                      ORDER BY s.\"designationSection\"";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getSections - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les orientations d'une section ayant des finalistes
     */
    public function getOrientationsBySection($sectionId, $anneeAcadId) {
        try {
            $query = "SELECT DISTINCT o.idorientation, o.\"designationOrientation\"
                      FROM orientation o
                      JOIN promotion p ON o.idorientation = p.orientation_idorientation
                      JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
                      WHERE o.section_idsection = :sectionId
                      AND p.est_terminale = 1 AND e.est_actif = 1
                      AND e.annee_acad_idannee_acad = :anneeAcadId
                      ORDER BY o.\"designationOrientation\"";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sectionId', $sectionId, PDO::PARAM_INT);
            $stmt->bindParam(':anneeAcadId', $anneeAcadId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getOrientationsBySection - " . $e->getMessage());
            return [];
        }
    }

    // ── Journal d'audit ──

    /**
     * Enregistrer une action dans le journal
     */
    public function logAction($dossierId, $documentId, $utilisateurType, $utilisateurId, $action, $details = null) {
        try {
            $query = "INSERT INTO dn_journal 
                      (dossier_id, document_id, utilisateur_type, utilisateur_id, action, details, ip_address, user_agent)
                      VALUES (:dossierId, :documentId, :type, :userId, :action, :details, :ip, :ua)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
            $stmt->bindParam(':type', $utilisateurType);
            $stmt->bindParam(':userId', $utilisateurId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt->bindParam(':ip', $ip);
            $stmt->bindParam(':ua', $ua);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::logAction - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer le journal d'audit
     */
    public function getJournal($dossierId = null, $limit = 50) {
        try {
            $query = "SELECT j.*, 
                             CASE j.utilisateur_type 
                                 WHEN 'etudiant' THEN (SELECT noms FROM etudiant WHERE idetudiant = j.utilisateur_id)
                                 WHEN 'admin' THEN (SELECT noms FROM agent WHERE \"idAgent\" = j.utilisateur_id)
                             END as nom_utilisateur
                      FROM dn_journal j";
            if ($dossierId) {
                $query .= " WHERE j.dossier_id = :dossierId";
            }
            $query .= " ORDER BY j.date_action DESC LIMIT :lim";

            $stmt = $this->db->prepare($query);
            if ($dossierId) {
                $stmt->bindParam(':dossierId', $dossierId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getJournal - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les années académiques disponibles
     */
    public function getAnneesAcademiques() {
        try {
            $query = "SELECT * FROM annee_acad ORDER BY designation DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getAnneesAcademiques - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer l'ID de l'année académique en cours (est_active = 1)
     */
    public function getAnneeAcadActiveId() {
        try {
            $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? intval($row['idannee_acad']) : 0;
        } catch (Exception $e) {
            error_log("DossierModel::getAnneeAcadActiveId - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Récupérer les données détaillées pour l'export Excel
     * Inclut le statut de chaque type de document par étudiant
     */
    public function getExportDetailedData($anneeAcadId, $sectionId = null, $orientationId = null, $statut = null) {
        // Récupérer tous les étudiants sans limite
        $etudiants = $this->getFinalistesWithDossiers($anneeAcadId, $sectionId, $orientationId, $statut);
        
        // Récupérer tous les types de documents actifs
        $query = "SELECT id, code, designation, est_obligatoire, cycle_requis 
                  FROM dn_type_document WHERE est_actif = 1 ORDER BY ordre_affichage";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $typesDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque étudiant qui a un dossier, récupérer ses documents
        foreach ($etudiants as &$etu) {
            $etu['documents_detail'] = [];
            if (!empty($etu['dossier_id'])) {
                $query = "SELECT du.type_document_id, du.statut, du.date_upload, du.nom_fichier_original,
                                 COUNT(*) as nb_fichiers
                          FROM dn_document du
                          WHERE du.dossier_id = :dossierId
                          GROUP BY du.type_document_id, du.statut, du.date_upload, du.nom_fichier_original
                          ORDER BY du.type_document_id, du.date_upload DESC";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':dossierId', $etu['dossier_id'], PDO::PARAM_INT);
                $stmt->execute();
                $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($docs as $doc) {
                    $etu['documents_detail'][$doc['type_document_id']] = $doc;
                }
            }
        }
        unset($etu);
        
        return [
            'etudiants' => $etudiants,
            'types_documents' => $typesDocuments
        ];
    }

    /**
     * Récupérer les statistiques détaillées pour l'export
     */
    public function getExportStats($anneeAcadId, $sectionId = null) {
        $stats = $this->getDossierStats($anneeAcadId, $sectionId);
        
        // Stats par section
        try {
            $query = "SELECT s.\"designationSection\",
                             COUNT(e.idetudiant) as total_etudiants,
                             COUNT(d.id) as dossiers_crees,
                             SUM(CASE WHEN d.statut = 'soumis' THEN 1 ELSE 0 END) as soumis,
                             SUM(CASE WHEN d.statut = 'valide' THEN 1 ELSE 0 END) as valides,
                             SUM(CASE WHEN d.statut = 'rejete' THEN 1 ELSE 0 END) as rejetes,
                             SUM(CASE WHEN d.statut IN ('en_cours','incomplet') THEN 1 ELSE 0 END) as en_cours,
                             ROUND(AVG(d.pourcentage_completion), 1) as moy_completion
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN dn_dossier d ON e.idetudiant = d.etudiant_idetudiant 
                           AND d.annee_acad_idannee_acad = :anneeAcadId
                      WHERE p.est_terminale = 1 AND e.est_actif = 1 
                      AND e.annee_acad_idannee_acad = :anneeAcadId2";
            
            $params = [':anneeAcadId' => $anneeAcadId, ':anneeAcadId2' => $anneeAcadId];
            
            if ($sectionId) {
                $query .= " AND s.idsection = :sectionId";
                $params[':sectionId'] = $sectionId;
            }
            
            $query .= " GROUP BY s.idsection, s.\"designationSection\" ORDER BY s.\"designationSection\"";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $stats['par_section'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getExportStats - " . $e->getMessage());
            $stats['par_section'] = [];
        }
        
        // Stats par promotion
        try {
            $query = "SELECT p.\"designationPromotion\", s.\"designationSection\",
                             COUNT(e.idetudiant) as total_etudiants,
                             COUNT(d.id) as dossiers_crees,
                             SUM(CASE WHEN d.statut = 'valide' THEN 1 ELSE 0 END) as valides,
                             SUM(CASE WHEN d.statut = 'soumis' THEN 1 ELSE 0 END) as soumis,
                             SUM(CASE WHEN d.statut = 'rejete' THEN 1 ELSE 0 END) as rejetes,
                             ROUND(AVG(d.pourcentage_completion), 1) as moy_completion
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section s ON o.section_idsection = s.idsection
                      LEFT JOIN dn_dossier d ON e.idetudiant = d.etudiant_idetudiant 
                           AND d.annee_acad_idannee_acad = :anneeAcadId
                      WHERE p.est_terminale = 1 AND e.est_actif = 1 
                      AND e.annee_acad_idannee_acad = :anneeAcadId2";
            
            $params = [':anneeAcadId' => $anneeAcadId, ':anneeAcadId2' => $anneeAcadId];
            
            if ($sectionId) {
                $query .= " AND s.idsection = :sectionId";
                $params[':sectionId'] = $sectionId;
            }
            
            $query .= " GROUP BY p.idpromotion, p.\"designationPromotion\", s.\"designationSection\" 
                         ORDER BY s.\"designationSection\", p.\"designationPromotion\"";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $stats['par_promotion'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getExportStats par_promotion - " . $e->getMessage());
            $stats['par_promotion'] = [];
        }
        
        return $stats;
    }

    /**
     * Récupérer la configuration de l'université
     */
    public function getConfigurationUniversite() {
        try {
            $query = "SELECT nom, sigle, logo, type_etablissement, ville, pays, telephone, email, site_web,
                             ministere_tutelle, nom_responsable, titre_responsable
                      FROM configuration_universite WHERE id = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("DossierModel::getConfigurationUniversite - " . $e->getMessage());
            return [];
        }
    }

    // ── Types de documents CRUD ──

    /**
     * Récupérer tous les types de documents
     */
    public function getAllTypesDocuments() {
        try {
            $query = "SELECT * FROM dn_type_document ORDER BY ordre_affichage, designation";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getAllTypesDocuments - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer un type de document par ID
     */
    public function getTypeDocumentById($id) {
        try {
            $query = "SELECT * FROM dn_type_document WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("DossierModel::getTypeDocumentById - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Créer un type de document
     */
    public function createTypeDocument($data) {
        try {
            $query = "INSERT INTO dn_type_document (code, designation, description, cycle_requis, est_obligatoire, ordre_affichage, est_actif)
                      VALUES (:code, :designation, :description, :cycle_requis, :est_obligatoire, :ordre_affichage, :est_actif)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':code', $data['code']);
            $stmt->bindParam(':designation', $data['designation']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':cycle_requis', $data['cycle_requis']);
            $stmt->bindParam(':est_obligatoire', $data['est_obligatoire'], PDO::PARAM_INT);
            $stmt->bindParam(':ordre_affichage', $data['ordre_affichage'], PDO::PARAM_INT);
            $stmt->bindParam(':est_actif', $data['est_actif'], PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::createTypeDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour un type de document
     */
    public function updateTypeDocument($id, $data) {
        try {
            $query = "UPDATE dn_type_document SET code = :code, designation = :designation, description = :description,
                      cycle_requis = :cycle_requis, est_obligatoire = :est_obligatoire, ordre_affichage = :ordre_affichage, est_actif = :est_actif
                      WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':code', $data['code']);
            $stmt->bindParam(':designation', $data['designation']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':cycle_requis', $data['cycle_requis']);
            $stmt->bindParam(':est_obligatoire', $data['est_obligatoire'], PDO::PARAM_INT);
            $stmt->bindParam(':ordre_affichage', $data['ordre_affichage'], PDO::PARAM_INT);
            $stmt->bindParam(':est_actif', $data['est_actif'], PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::updateTypeDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un type de document (seulement si aucun document uploadé ne l'utilise)
     */
    public function deleteTypeDocument($id) {
        try {
            $query = "SELECT COUNT(*) as cnt FROM dn_document WHERE type_document_id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            if ($count > 0) {
                return ['error' => 'Ce type de document est utilisé par ' . $count . ' document(s) et ne peut pas être supprimé.'];
            }

            $query = "DELETE FROM dn_type_document WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("DossierModel::deleteTypeDocument - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compter les documents uploadés par type
     */
    public function countDocumentsByType($typeId) {
        try {
            $query = "SELECT COUNT(*) as cnt FROM dn_document WHERE type_document_id = :typeId";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':typeId', $typeId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        } catch (Exception $e) {
            error_log("DossierModel::countDocumentsByType - " . $e->getMessage());
            return 0;
        }
    }
}
