<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';

try {
    $soutenanceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$soutenanceId) {
        throw new Exception('ID de soutenance manquant');
    }
    
    $connexion = Connexion::getInstance()->getPDO();
    
    $query = "SELECT 
                s.idsoutenance,
                s.date_soutenance,
                s.lieu,
                s.sujets_idsujets,
                s.jury_id,
                sj.intitule as titre_memoire,
                e.noms,
                e.matricule,
                e.photo,
                p.\"designationPromotion\",
                p.idpromotion,
                p.orientation_idorientation,
                sp.designation as specialisation,
                ag.noms as directeur_noms,
                gag.designation as directeur_grade,
                j.idjury,
                apres.noms as president_nom,
                gpres.designation as president_grade,
                asec.noms as secretaire_nom,
                gsec.designation as secretaire_grade
              FROM soutenance s
              JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.idspecialisation
              LEFT JOIN agent ag ON sj.\"idDirecteur\" = ag.idagent
              LEFT JOIN grade gag ON ag.grade_id = gag.idgrade
              LEFT JOIN jury j ON s.jury_id = j.idjury
              LEFT JOIN agent apres ON j.id_president = apres.idagent
              LEFT JOIN grade gpres ON apres.grade_id = gpres.idgrade
              LEFT JOIN agent asec ON j.id_secretaire = asec.idagent
              LEFT JOIN grade gsec ON asec.grade_id = gsec.idgrade
              WHERE s.idsoutenance = :id";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute(['id' => $soutenanceId]);
    $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$soutenance) {
        throw new Exception('Soutenance non trouvée');
    }
    
    // Récupérer les lecteurs
    $queryLecteurs = "SELECT 
                        ag.noms as lecteur_noms,
                        g.designation as grade,
                        l.est_premier_lecteur
                      FROM lecteurs_soutenance l
                      JOIN agent ag ON l.idenseignant = ag.idagent
                      LEFT JOIN grade g ON ag.grade_id = g.idgrade
                      WHERE l.idsoutenance = :id
                      ORDER BY l.est_premier_lecteur DESC, l.id ASC";
    
    $stmtLecteurs = $connexion->prepare($queryLecteurs);
    $stmtLecteurs->execute(['id' => $soutenanceId]);
    $lecteurs = $stmtLecteurs->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les informations de l'université
    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Récupérer les informations de la section
    $sectionInfo = null;
    if (isset($soutenance['orientation_idorientation'])) {
        $query = "SELECT s.* FROM section s 
                  INNER JOIN orientation o ON s.idsection = o.section_idsection
                  WHERE o.idorientation = :idorientation";
        $stmt = $connexion->prepare($query);
        $stmt->execute(['idorientation' => $soutenance['orientation_idorientation']]);
        $sectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Formater la date
    $dateSoutenance = new DateTime($soutenance['date_soutenance']);
    $joursSemaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    
    // Photo URL
    $photoUrl = '';
    if (!empty($soutenance['photo'])) {
        $photoPath = dirname(__DIR__) . '/uploads/' . $soutenance['photo'];
        if (file_exists($photoPath)) {
            $photoUrl = 'uploads/' . $soutenance['photo'];
        }
    }
    
    // Logo URL
    $logoUrl = '';
    if ($configUniversite && !empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $logoUrl = $configUniversite['logo'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'universite' => [
                'nom' => $configUniversite['nom'] ?? 'UNIVERSITÉ',
                'ministere' => $configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR ET UNIVERSITAIRE',
                'logo' => $logoUrl
            ],
            'section' => $sectionInfo ? $sectionInfo['designationSection'] : null,
            'etudiant' => [
                'noms' => $soutenance['noms'],
                'matricule' => $soutenance['matricule'],
                'photo' => $photoUrl
            ],
            'memoire' => [
                'titre' => $soutenance['titre_memoire'],
                'specialisation' => $soutenance['specialisation'] ?? 'Non définie'
            ],
            'soutenance' => [
                'date' => $dateSoutenance->format('Y-m-d H:i:s'),
                'jour_semaine' => $joursSemaine[(int)$dateSoutenance->format('w')],
                'date_formatee' => $dateSoutenance->format('d') . ' ' . $mois[(int)$dateSoutenance->format('n')] . ' ' . $dateSoutenance->format('Y'),
                'heure' => $dateSoutenance->format('H:i'),
                'lieu' => $soutenance['lieu'] ?? 'À préciser'
            ],
            'encadrement' => [
                'directeur' => $soutenance['directeur_noms'] ?? 'Non assigné',
                'directeur_grade' => $soutenance['directeur_grade'] ?? ''
            ],
            'jury' => [
                'president' => $soutenance['president_nom'] ?? null,
                'president_grade' => $soutenance['president_grade'] ?? '',
                'secretaire' => $soutenance['secretaire_nom'] ?? null,
                'secretaire_grade' => $soutenance['secretaire_grade'] ?? ''
            ],
            'lecteurs' => $lecteurs
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Erreur get_defense_poster_html.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
