<?php
class Soutenance
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Ajouter un frais de soutenance
    public function addFraisSoutenance($designation, $montant, $devise, $description, $idAnneeAcad, $idUser)
    {
        $query = "INSERT INTO frais_soutenance (designation, montant, devise, description, 
                 annee_acad_idannee_acad, \"idUser\") 
                 VALUES (:designation, :montant, :devise, :description, :idAnneeAcad, :idUser)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'designation' => $designation,
            'montant' => $montant,
            'devise' => $devise,
            'description' => $description,
            'idAnneeAcad' => $idAnneeAcad,
            'idUser' => $idUser
        ]);
    }

    // Vérifier si un étudiant a payé les frais de soutenance
    public function verifierPaiementSoutenance($idEtudiant, $idAnneeAcad)
    {
        // D'abord, récupérer la section de l'étudiant
        $querySection = "SELECT s.idsection 
                FROM etudiant e 
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                JOIN section s ON o.section_idsection = s.idsection
                WHERE e.idetudiant = :idEtudiant";
        $stmtSection = $this->db->prepare($querySection);
        $stmtSection->execute(['idEtudiant' => $idEtudiant]);
        $sectionId = $stmtSection->fetchColumn();

        // Récupérer tous les frais obligatoires pour cette section et année académique
        $query = "SELECT fs.*, 
                  ps.\"estComplet\", 
                  ps.\"datePaiement\",
                  ps.\"montantPaye\",
                  ps.idpaiement_soutenance
                  FROM frais_soutenance fs
                  LEFT JOIN paiement_soutenance ps ON fs.idfrais_soutenance = ps.frais_soutenance_id 
                      AND ps.etudiant_id = :idEtudiant 
                      AND ps.annee_acad_id = :idAnneeAcad
                  WHERE fs.annee_acad_id = :idAnneeAcad
                  AND (fs.section_id = :sectionId OR fs.section_id IS NULL)
                  AND fs.\"estObligatoire\" = 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEtudiant' => $idEtudiant,
            'idAnneeAcad' => $idAnneeAcad,
            'sectionId' => $sectionId
        ]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier si tous les frais obligatoires sont payés
        $tousPayes = true;
        $fraisManquants = [];

        foreach ($result as $frais) {
            // Un frais est considéré comme payé si :
            // 1. Il existe un enregistrement de paiement (idpaiement_soutenance n'est pas null)
            // 2. Le paiement est marqué comme complet (estComplet est à 1)
            if (!isset($frais['idpaiement_soutenance']) || empty($frais['idpaiement_soutenance']) || $frais['estComplet'] != 1) {
                $tousPayes = false;
                $fraisManquants[] = $frais['designation'];
            }
        }

        return [
            'frais' => $result,
            'est_en_ordre' => $tousPayes,
            'frais_manquants' => $fraisManquants
        ];
    }

    // Programmer une soutenance
    public function programmerSoutenance($dateSoutenance, $lieu, $idSujet, $idUser)
    {
        // Vérifier si le sujet est validé
        $query = "SELECT s.*, e.idetudiant, e.noms as etudiant_nom
                 FROM sujets s
                 LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                 WHERE s.idsujets = :idSujet";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSujet' => $idSujet]);
        $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sujet || $sujet['statut_validation'] !== 'Validé') {
            return ['success' => false, 'message' => 'Le sujet doit être validé pour programmer une soutenance'];
        }

        // Vérifier si l'étudiant est en ordre de frais
        $anneeAcad = $sujet['annee_acad_idannee_acad'];
        $idEtudiant = $sujet['idetudiant'];

        $paiementStatus = $this->verifierPaiementSoutenance($idEtudiant, $anneeAcad);
        if (!$paiementStatus['est_en_ordre']) {
            return ['success' => false, 'message' => 'L\'étudiant n\'est pas en ordre de paiement des frais de soutenance'];
        }

        // Programmer la soutenance
        $query = "INSERT INTO soutenance (date_soutenance, lieu, sujets_idsujets, statut, \"idUser\") 
                 VALUES (:dateSoutenance, :lieu, :idSujet, 'Programmée', :idUser)";
        $stmt = $this->db->prepare($query);
        $success = $stmt->execute([
            'dateSoutenance' => $dateSoutenance,
            'lieu' => $lieu,
            'idSujet' => $idSujet,
            'idUser' => $idUser
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Erreur lors de la programmation de la soutenance'];
        }

        $idSoutenance = $this->db->lastInsertId();
        return ['success' => true, 'id' => $idSoutenance];
    }

    // Ajouter un membre du jury
    public function ajouterMembreJury($idSoutenance, $idEnseignant, $role)
    {
        $query = "INSERT INTO jury_soutenance (idsoutenance, idenseignant, role) 
                 VALUES (:idSoutenance, :idEnseignant, :role)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idEnseignant' => $idEnseignant,
            'role' => $role
        ]);
    }

    // Récupérer le programme des soutenances
    public function getProgrammeSoutenances($filters = [])
    {
        $query = "SELECT s.*, j.lieu, j.date_soutenance, j.statut as statut_soutenance,
                 e.noms as etudiant_nom, e.matricule, 
                 d.noms as directeur_nom, en.noms as encadreur_nom,
                 sp.designation as specialisation
                 FROM soutenance j
                 JOIN sujets s ON j.sujets_idsujets = s.idsujets
                 JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                 LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
                 LEFT JOIN agent en ON s.\"idEncadreur\" = en.\"idAgent\"
                 LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
                 WHERE 1=1";

        $params = [];

        if (!empty($filters['annee_acad'])) {
            $query .= " AND s.annee_acad_idannee_acad = :anneeAcad";
            $params['anneeAcad'] = $filters['annee_acad'];
        }

        if (!empty($filters['statut'])) {
            $query .= " AND j.statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['date_debut']) && !empty($filters['date_fin'])) {
            $query .= " AND j.date_soutenance BETWEEN :dateDebut AND :dateFin";
            $params['dateDebut'] = $filters['date_debut'];
            $params['dateFin'] = $filters['date_fin'];
        }

        if (!empty($filters['specialisation'])) {
            $query .= " AND s.\"idSpecialisation\" = :specialisation";
            $params['specialisation'] = $filters['specialisation'];
        }

        $query .= " ORDER BY j.date_soutenance";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer le jury d'une soutenance
    public function getJurySoutenance($idSoutenance)
    {
        $query = "SELECT j.*, a.noms, a.grade_id, g.designation as grade
                 FROM jury_soutenance j
                 JOIN agent a ON j.idenseignant = a.\"idAgent\"
                 LEFT JOIN grade g ON a.grade_id = g.idgrade
                 WHERE j.idsoutenance = :idSoutenance
                 ORDER BY FIELD(j.role, 'Président', 'Secrétaire', 'Membre')";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getFraisSoutenance($idAnneeAcad)
    {
        $query = "SELECT fs.*, 
              COUNT(ps.idpaiement_soutenance) as nombre_paiements,
              SUM(CASE WHEN ps.est_complet = 1 THEN 1 ELSE 0 END) as paiements_complets
              FROM frais_soutenance fs
              LEFT JOIN paiement_soutenance ps ON fs.idfrais_soutenance = ps.idfrais_soutenance
              WHERE fs.annee_acad_idannee_acad = :idAnneeAcad
              GROUP BY fs.idfrais_soutenance
              ORDER BY fs.designation";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getPaiementsSoutenance($idAnneeAcad)
    {
        $query = "SELECT ps.*, 
              e.noms, e.matricule,
              fs.designation, fs.montant, fs.devise
              FROM paiement_soutenance ps
              JOIN etudiant e ON ps.idetudiant = e.idetudiant
              JOIN frais_soutenance fs ON ps.idfrais_soutenance = fs.idfrais_soutenance
              WHERE ps.annee_acad_idannee_acad = :idAnneeAcad
              ORDER BY ps.date_paiement DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idAnneeAcad', $idAnneeAcad, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ajoutez ces méthodes à la classe Soutenance existante

    // Créer un jury
    public function createJury($designation, $idPresident, $idSecretaire, $idAnneeAcad, $idSection = null)
    {
        $query = "INSERT INTO jury (designation, id_president, id_secretaire, annee_acad_id, section_id) 
             VALUES (:designation, :idPresident, :idSecretaire, :idAnneeAcad, :idSection)";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'designation' => $designation,
            'idPresident' => $idPresident,
            'idSecretaire' => $idSecretaire,
            'idAnneeAcad' => $idAnneeAcad,
            'idSection' => $idSection
        ]);

        if ($result) {
            return ['success' => true, 'id' => $this->db->lastInsertId(), 'message' => 'Le jury a été créé avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création du jury'];
        }
    }


    // Obtenir tous les jurys
    public function getAllJurys($anneeAcad, $sectionId = null)
    {
        $query = "SELECT j.*,
             p.noms as president_nom,
             s.noms as secretaire_nom,
             sec.idsection as section_id,
             sec.\"designationSection\" as section_nom
             FROM jury j
             JOIN agent p ON j.id_president = p.\"idAgent\"
             JOIN agent s ON j.id_secretaire = s.\"idAgent\"
             LEFT JOIN section sec ON j.section_id = sec.idsection
             WHERE j.annee_acad_id = :anneeAcad";

        $params = ['anneeAcad' => $anneeAcad];

        if ($sectionId) {
            $query .= " AND (j.section_id = :sectionId OR j.section_id IS NULL)";
            $params['sectionId'] = $sectionId;
        }

        $query .= " ORDER BY j.date_creation DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Assigner des lecteurs à une soutenance
    public function assignerLecteurs($idSoutenance, $idLecteur1, $idLecteur2)
    {
        // Supprimer les anciens lecteurs s'ils existent
        $query = "DELETE FROM lecteurs_soutenance WHERE idsoutenance = :idSoutenance";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);

        // Ajouter le premier lecteur
        $query = "INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur) 
             VALUES (:idSoutenance, :idLecteur, 1)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idLecteur' => $idLecteur1
        ]);

        // Ajouter le deuxième lecteur
        $query = "INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur) 
             VALUES (:idSoutenance, :idLecteur, 0)";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idLecteur' => $idLecteur2
        ]);

        return $result;
    }

    // Enregistrer une note pour une soutenance (par un lecteur ou le directeur)
    public function enregistrerNote($idSoutenance, $idEnseignant, $typeNotation, $noteFond = null, $noteForme = null, $noteSoutenance = null, $commentaire = null)
    {
        $query = "INSERT INTO notes_soutenance 
             (idsoutenance, idenseignant, type_notation, note_fond, note_forme, note_soutenance, commentaire) 
             VALUES (:idSoutenance, :idEnseignant, :typeNotation, :noteFond, :noteForme, :noteSoutenance, :commentaire)
             ON DUPLICATE KEY UPDATE 
             note_fond = :noteFond, 
             note_forme = :noteForme, 
             note_soutenance = :noteSoutenance, 
             commentaire = :commentaire,
             date_notation = NOW()";

        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idEnseignant' => $idEnseignant,
            'typeNotation' => $typeNotation,
            'noteFond' => $noteFond,
            'noteForme' => $noteForme,
            'noteSoutenance' => $noteSoutenance,
            'commentaire' => $commentaire
        ]);

        return $result;
    }

    // Calculer la moyenne des notes pour une soutenance
    public function calculerMoyenneSoutenance($idSoutenance)
    {
        // Récupérer les notes des lecteurs
        $query = "SELECT 
             AVG(note_fond) as moyenne_fond,
             AVG(note_forme) as moyenne_forme
             FROM notes_soutenance
             WHERE idsoutenance = :idSoutenance AND type_notation = 'Lecteur'";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $moyennesLecteurs = $stmt->fetch(PDO::FETCH_ASSOC);

        // Récupérer la note du directeur
        $query = "SELECT note_soutenance
             FROM notes_soutenance
             WHERE idsoutenance = :idSoutenance AND type_notation = 'Directeur'";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $noteDirecteur = $stmt->fetch(PDO::FETCH_ASSOC);

        $moyenneFond = $moyennesLecteurs['moyenne_fond'] ?? 0;
        $moyenneForme = $moyennesLecteurs['moyenne_forme'] ?? 0;
        $noteSoutenance = $noteDirecteur['note_soutenance'] ?? 0;

        // Calculer la moyenne finale (vous pouvez ajuster la formule selon vos besoins)
        $moyenne = ($moyenneFond * 0.4) + ($moyenneForme * 0.3) + ($noteSoutenance * 0.3);

        return [
            'moyenne_fond' => $moyenneFond,
            'moyenne_forme' => $moyenneForme,
            'note_soutenance' => $noteSoutenance,
            'moyenne_finale' => $moyenne
        ];
    }

    // Valider les notes d'une soutenance
    public function validerNotesSoutenance($idSoutenance, $idValidateur, $estValide, $commentaire = null, $estVisible = false)
    {
        // Vérifier si l'entrée existe déjà
        $query = "SELECT id FROM validation_notes_soutenance WHERE idsoutenance = :idSoutenance";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            // Mettre à jour l'entrée existante
            $query = "UPDATE validation_notes_soutenance 
                 SET est_valide = :estValide,
                 date_validation = NOW(),
                 id_validateur = :idValidateur,
                 commentaire = :commentaire,
                 est_visible = :estVisible
                 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'estValide' => $estValide ? 1 : 0,
                'idValidateur' => $idValidateur,
                'commentaire' => $commentaire,
                'estVisible' => $estVisible ? 1 : 0,
                'id' => $existingId
            ]);
        } else {
            // Créer une nouvelle entrée
            $query = "INSERT INTO validation_notes_soutenance 
                 (idsoutenance, est_valide, date_validation, id_validateur, commentaire, est_visible) 
                 VALUES (:idSoutenance, :estValide, NOW(), :idValidateur, :commentaire, :estVisible)";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'idSoutenance' => $idSoutenance,
                'estValide' => $estValide ? 1 : 0,
                'idValidateur' => $idValidateur,
                'commentaire' => $commentaire,
                'estVisible' => $estVisible ? 1 : 0
            ]);
        }

        // Si validation réussie et notes validées, mettre à jour la note dans la table soutenance
        if ($result && $estValide) {
            $moyennes = $this->calculerMoyenneSoutenance($idSoutenance);
            $query = "UPDATE soutenance SET note_finale = :noteFinal, statut = 'Terminée' WHERE idsoutenance = :idSoutenance";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'noteFinal' => $moyennes['moyenne_finale'],
                'idSoutenance' => $idSoutenance
            ]);
        }

        return $result;
    }

    // Récupérer les détails d'une soutenance avec ses notes
    public function getSoutenanceAvecNotes($idSoutenance)
    {
        // Récupérer les informations de base de la soutenance
        $query = "SELECT s.*, 
             j.lieu, j.date_soutenance, j.statut as statut_soutenance, j.note_finale,
             e.noms as etudiant_nom, e.matricule, 
             d.noms as directeur_nom, d.\"idAgent\" as directeur_id,
             sp.designation as specialisation
             FROM soutenance j
             JOIN sujets s ON j.sujets_idsujets = s.idsujets
             JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
             LEFT JOIN agent d ON s.\"idDirecteur\" = d.\"idAgent\"
             LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
             WHERE j.idsoutenance = :idSoutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$soutenance) {
            return null;
        }

        // Récupérer les lecteurs
        $query = "SELECT ls.*, a.noms, a.grade_id, g.designation as grade
             FROM lecteurs_soutenance ls
             JOIN agent a ON ls.idenseignant = a.\"idAgent\"
             LEFT JOIN grade g ON a.grade_id = g.idgrade
             WHERE ls.idsoutenance = :idSoutenance
             ORDER BY ls.est_premier_lecteur DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $lecteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les notes
        $query = "SELECT ns.*, a.noms
             FROM notes_soutenance ns
             JOIN agent a ON ns.idenseignant = a.\"idAgent\"
             WHERE ns.idsoutenance = :idSoutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer le statut de validation
        $query = "SELECT v.*, a.noms as validateur_nom
             FROM validation_notes_soutenance v
             LEFT JOIN agent a ON v.id_validateur = a.\"idAgent\"
             WHERE v.idsoutenance = :idSoutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $validation = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculer les moyennes
        $moyennes = [
            'moyenne_fond' => 0,
            'moyenne_forme' => 0,
            'note_soutenance' => 0,
            'moyenne_finale' => 0
        ];

        // Si des notes existent, calculer les moyennes
        if (!empty($notes)) {
            $notesFond = [];
            $notesForme = [];
            $noteSoutenance = null;

            foreach ($notes as $note) {
                if ($note['type_notation'] == 'Lecteur') {
                    if (isset($note['note_fond']) && is_numeric($note['note_fond'])) {
                        $notesFond[] = floatval($note['note_fond']);
                    }
                    if (isset($note['note_forme']) && is_numeric($note['note_forme'])) {
                        $notesForme[] = floatval($note['note_forme']);
                    }
                } else if ($note['type_notation'] == 'Directeur' && isset($note['note_soutenance']) && is_numeric($note['note_soutenance'])) {
                    $noteSoutenance = floatval($note['note_soutenance']);
                }
            }

            // Calculer les moyennes s'il y a des notes
            if (!empty($notesFond)) {
                $moyennes['moyenne_fond'] = array_sum($notesFond) / count($notesFond);
            }
            if (!empty($notesForme)) {
                $moyennes['moyenne_forme'] = array_sum($notesForme) / count($notesForme);
            }
            if ($noteSoutenance !== null) {
                $moyennes['note_soutenance'] = $noteSoutenance;
            }

            // Calculer la moyenne finale selon la formule
            $moyennes['moyenne_finale'] = ($moyennes['moyenne_fond'] * 0.4) +
                ($moyennes['moyenne_forme'] * 0.3) +
                ($moyennes['note_soutenance'] * 0.3);
        }

        // Retourner tous les résultats ensemble
        return [
            'soutenance' => $soutenance ?: [],
            'lecteurs' => $lecteurs ?: [],
            'notes' => $notes ?: [],
            'validation' => $validation ?: null,
            'moyennes' => $moyennes
        ];
    }


    // Récupérer les soutenances pour un jury spécifique
    public function getSoutenancesParJury($idJury, $anneeAcad)
    {
        $query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                         sj.intitule as sujet, sj.idsujets,
                         e.noms as etudiant_nom, e.matricule,
                         d.noms as directeur_nom,
                         (SELECT COUNT(*) FROM notes_soutenance WHERE idsoutenance = s.idsoutenance) as nb_notes,
                         v.est_valide, v.est_visible
                         FROM soutenance s
                         JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                         JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                         LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
                         LEFT JOIN validation_notes_soutenance v ON s.idsoutenance = v.idsoutenance
                         WHERE s.jury_id = :idJury
                         AND sj.annee_acad_idannee_acad = :anneeAcad
                         ORDER BY s.date_soutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idJury' => $idJury,
            'anneeAcad' => $anneeAcad
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Récupérer les jurys pour président ou secrétaire
    public function getJurysByRole($idEnseignant, $anneeAcad)
    {
        $query = "SELECT * FROM jury 
                         WHERE (id_president = :idEnseignant OR id_secretaire = :idEnseignant)
                         AND annee_acad_id = :anneeAcad
                         AND est_actif = 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEnseignant' => $idEnseignant,
            'anneeAcad' => $anneeAcad
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les soutenances pour un lecteur
    public function getSoutenancesParLecteur($idEnseignant, $anneeAcad)
    {
        $query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                         sj.intitule as sujet, sj.idsujets,
                         e.noms as etudiant_nom, e.matricule,
                         d.noms as directeur_nom,
                         ls.est_premier_lecteur,
                         (SELECT COUNT(*) FROM notes_soutenance 
                          WHERE idsoutenance = s.idsoutenance AND idenseignant = :idEnseignant) as a_note
                         FROM soutenance s
                         JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                         JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                         LEFT JOIN agent d ON sj.\"idDirecteur\" = d.\"idAgent\"
                         JOIN lecteurs_soutenance ls ON s.idsoutenance = ls.idsoutenance
                         WHERE ls.idenseignant = :idEnseignant
                         AND sj.annee_acad_idannee_acad = :anneeAcad
                         ORDER BY s.date_soutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEnseignant' => $idEnseignant,
            'anneeAcad' => $anneeAcad
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Récupérer les soutenances pour un directeur
    public function getSoutenancesParDirecteur($idEnseignant, $anneeAcad)
    {
        $query = "SELECT s.idsoutenance, s.date_soutenance, s.lieu, s.statut,
                         sj.intitule as sujet, sj.idsujets,
                         e.noms as etudiant_nom, e.matricule,
                         (SELECT COUNT(*) FROM notes_soutenance 
                          WHERE idsoutenance = s.idsoutenance AND idenseignant = :idEnseignant) as a_note
                         FROM soutenance s
                         JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                         JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
                         WHERE sj.\"idDirecteur\" = :idEnseignant
                         AND sj.annee_acad_idannee_acad = :anneeAcad
                         ORDER BY s.date_soutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idEnseignant' => $idEnseignant,
            'anneeAcad' => $anneeAcad
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }




    // Ajouter ces méthodes à la classe Soutenance

    /**
     * Mettre à jour le jury d'une soutenance
     * @param int $idSoutenance ID de la soutenance
     * @param int $idJury ID du jury
     * @return bool Succès de l'opération
     */
    public function updateJurySoutenance($idSoutenance, $idJury)
    {
        $query = "UPDATE soutenance SET jury_id = :idJury WHERE idsoutenance = :idSoutenance";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'idJury' => $idJury,
            'idSoutenance' => $idSoutenance
        ]);
    }

    /**
     * Vérifier si un enseignant est lecteur pour une soutenance
     * @param int $idSoutenance ID de la soutenance
     * @param int $idEnseignant ID de l'enseignant
     * @return bool Vrai si l'enseignant est lecteur
     */
    public function isLecteurForSoutenance($idSoutenance, $idEnseignant)
    {
        $query = "SELECT id FROM lecteurs_soutenance 
             WHERE idsoutenance = :idSoutenance AND idenseignant = :idEnseignant";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'idSoutenance' => $idSoutenance,
            'idEnseignant' => $idEnseignant
        ]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Vérifier si un enseignant est directeur pour une soutenance
     * @param int $idSoutenance ID de la soutenance
     * @param int $idEnseignant ID de l'enseignant
     * @return bool Vrai si l'enseignant est directeur
     */
    public function isDirecteurForSoutenance($idSoutenance, $idEnseignant)
    {
        $query = "SELECT s.\"idDirecteur\" 
             FROM soutenance so
             JOIN sujets s ON so.sujets_idsujets = s.idsujets
             WHERE so.idsoutenance = :idSoutenance";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $directeurId = $stmt->fetchColumn();

        return $directeurId == $idEnseignant;
    }

    /**
     * Récupère les frais requis pour le dépôt de mémoire d'une promotion
     */
    public function getRequiredFeesForMemoire($promotionId) {
        try {
            $query = "SELECT fm.id, fm.frais_id, f.designation, f.montant, f.devise, f.description
                      FROM frais_memoire fm
                      JOIN frais f ON fm.frais_id = f.id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'memoire'";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['promotionId' => $promotionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getRequiredFeesForMemoire: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les frais requis pour le dépôt de sujet d'une promotion
     */
    public function getRequiredFeesForSujet($promotionId) {
        try {
            $query = "SELECT fm.id, fm.frais_id, f.designation, f.montant, f.devise, f.description
                      FROM frais_memoire fm
                      JOIN frais f ON fm.frais_id = f.id
                      WHERE fm.promotion_idpromotion = :promotionId AND fm.type = 'sujet'";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['promotionId' => $promotionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getRequiredFeesForSujet: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les informations d'une promotion
     */
    public function getPromotion($promotionId) {
        try {
            $query = "SELECT * FROM promotion WHERE idpromotion = :promotionId";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['promotionId' => $promotionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erreur getPromotion: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifier si un enseignant est président du jury pour une soutenance
     * @param int $idSoutenance ID de la soutenance
     * @param int $idEnseignant ID de l'enseignant
     * @return bool Vrai si l'enseignant est président
     */
    public function isPresidentForSoutenance($idSoutenance, $idEnseignant)
    {
        $query = "SELECT j.id_president 
             FROM soutenance s
             JOIN jury j ON s.jury_id = j.idjury
             WHERE s.idsoutenance = :idSoutenance";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);
        $presidentId = $stmt->fetchColumn();

        return $presidentId == $idEnseignant;
    }

    /**
     * Récupère une soutenance par son ID avec toutes les informations associées
     */
    public function getSoutenanceById($idSoutenance)
    {
        $query = "SELECT s.*, j.designation as jury_designation, j.idjury as id_jury,
              jp.\"idAgent\" as id_president, jp.noms as president_nom,
              js.\"idAgent\" as id_secretaire, js.noms as secretaire_nom
              FROM soutenance s
              LEFT JOIN jury j ON s.jury_id = j.idjury
              LEFT JOIN agent jp ON j.id_president = jp.\"idAgent\"
              LEFT JOIN agent js ON j.id_secretaire = js.\"idAgent\"
              WHERE s.idsoutenance = :idSoutenance";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les lecteurs associés à une soutenance
     */
    public function getLecteursParSoutenance($idSoutenance)
    {
        $query = "SELECT ls.idenseignant, ls.est_premier_lecteur, a.noms
              FROM lecteurs_soutenance ls
              JOIN agent a ON ls.idenseignant = a.\"idAgent\"
              WHERE ls.idsoutenance = :idSoutenance
              ORDER BY ls.est_premier_lecteur DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['idSoutenance' => $idSoutenance]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour une soutenance existante
     */
    public function updateSoutenance($idSoutenance, $dateSoutenance, $lieu, $statut, $notePrincipale = null, $commentaire = null, $idJury = null, $lecteurs = null)
    {
        // Commencer une transaction
        $this->db->beginTransaction();

        try {
            // Mettre à jour la soutenance
            $query = "UPDATE soutenance 
                 SET date_soutenance = :dateSoutenance,
                     lieu = :lieu,
                     statut = :statut";

            $params = [
                'idSoutenance' => $idSoutenance,
                'dateSoutenance' => $dateSoutenance,
                'lieu' => $lieu,
                'statut' => $statut
            ];

            // Ajouter les champs optionnels
            if ($notePrincipale !== null) {
                $query .= ", note_finale = :notePrincipale";
                $params['notePrincipale'] = $notePrincipale;
            }

            if ($commentaire !== null) {
                $query .= ", commentaire = :commentaire";
                $params['commentaire'] = $commentaire;
            }

            if ($idJury !== null) {
                $query .= ", jury_id = :idJury";
                $params['idJury'] = $idJury;
            }

            $query .= " WHERE idsoutenance = :idSoutenance";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            // Si des lecteurs sont fournis, mettre à jour les lecteurs
            if ($lecteurs !== null && is_array($lecteurs)) {
                // Supprimer les lecteurs existants
                $stmt = $this->db->prepare("DELETE FROM lecteurs_soutenance WHERE idsoutenance = :idSoutenance");
                $stmt->execute(['idSoutenance' => $idSoutenance]);

                // Ajouter les nouveaux lecteurs
                foreach ($lecteurs as $index => $lecteur) {
                    $stmt = $this->db->prepare("INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur) 
                                          VALUES (:idSoutenance, :idEnseignant, :estPremier)");
                    $stmt->execute([
                        'idSoutenance' => $idSoutenance,
                        'idEnseignant' => $lecteur['id_enseignant'],
                        'estPremier' => ($index === 0) ? 1 : 0
                    ]);
                }
            }

            // Valider la transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->db->rollBack();
            throw $e;
        }
    }
}
