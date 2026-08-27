<?php
class Structure
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Récupérer toutes les structures
    public function getStructures($search = '')
    {
        $query = "SELECT * FROM structure";
        if (!empty($search)) {
            $query .= " WHERE designation LIKE :search";
        }
        $stmt = $this->db->prepare($query);
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsers()
    {
        $query = "SELECT * FROM t_users ORDER BY \"nomUser\" ASC";
        return $this->db->query($query);
    }

    public function getUserById($id)
    {
        $query = "SELECT * FROM t_users WHERE \"idUser\"='$id'";
        return $this->db->query($query);
    }

    public function getUsersByStructurePermission($structureId)
    {
        $query = "
            SELECT u.*
            FROM t_users u
            JOIN user_structure us ON u.\"idUser\" = us.\"idUser\"
            WHERE us.\"idStructure\" = :structureId
            ORDER BY u.\"nomUser\" ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserStructure($id){
        $query = "SELECT * FROM user_structure as us INNER JOIN t_users as u ON us.\"idUser\"=u.\"idUser\" WHERE \"idStructure\"='$id'";
        return $this->db->query($query);
    }

    public function getUserPermissionStructure($user,$structure){
        $query = "SELECT * FROM user_structure as us INNER JOIN t_users as u ON us.\"idUser\"=u.\"idUser\" WHERE us.\"idStructure\"='$structure' AND us.\"idUser\"='$user'";
        return $this->db->query($query);
    }

    public function addUserStructure($user,$structure,$voir){
        $query = "INSERT INTO user_structure VALUES(default,'$voir','$user','$structure')";
        return $this->db->query($query);
    }

    // Vérifier si une structure avec la même désignation existe déjà
    public function checkDuplicateStructure($designation)
    {
        $query = "SELECT COUNT(*) FROM structure WHERE designation = :designation";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    // Ajouter une nouvelle structure
    public function addStructure($designation, $adresse, $phone1, $phone2, $siteweb, $logo, $joursOuvrables, $IPR, $tauxRetenuAbsence, $nJoursRecouvrement)
    {
        $query = "INSERT INTO structure (designation, adresse, phone1, phone2, siteweb, logo, \"joursOuvrables\", IPR, taux_retenu_absence, \"nJoursRecouvrement\", \"dateEnregistrement\") 
                  VALUES (:designation, :adresse, :phone1, :phone2, :siteweb, :logo, :joursOuvrables, :IPR, :tauxRetenuAbsence, :nJoursRecouvrement, NOW())";

        $stmt = $this->db->prepare($query);

        // Bind des paramètres
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':phone1', $phone1, PDO::PARAM_STR);
        $stmt->bindParam(':phone2', $phone2, PDO::PARAM_STR);
        $stmt->bindParam(':siteweb', $siteweb, PDO::PARAM_STR);
        $stmt->bindParam(':logo', $logo, PDO::PARAM_STR);
        $stmt->bindParam(':joursOuvrables', $joursOuvrables, PDO::PARAM_INT);
        $stmt->bindParam(':IPR', $IPR, PDO::PARAM_STR);
        $stmt->bindParam(':tauxRetenuAbsence', $tauxRetenuAbsence, PDO::PARAM_STR);
        $stmt->bindParam(':nJoursRecouvrement', $nJoursRecouvrement, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Récupérer une structure par son ID
    public function getStructureById($id)
    {
        $query = "SELECT * FROM structure WHERE \"idStructure\" = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStructureByCompte($id)
    {
        $query = "SELECT c.*,s.* FROM compte as c INNER JOIN structure as s
        ON c.\"Structure_idStructure\"=s.\"idStructure\" WHERE c.idCompte = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStructureByJournal($id)
    {
        $query = "SELECT * FROM journaux as c INNER JOIN structure as s
        ON c.\"Structure_idStructure\"=s.\"idStructure\" WHERE c.idJournaux = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour une structure
    public function updateStructure($id, $designation, $adresse, $phone1, $phone2, $siteweb, $logo, $joursOuvrables, $IPR, $tauxRetenuAbsence, $nJoursRecouvrement)
    {
        $query = "UPDATE structure SET 
                    designation = :designation,
                    adresse = :adresse,
                    phone1 = :phone1,
                    phone2 = :phone2,
                    siteweb = :siteweb,
                    logo = :logo,
                    \"joursOuvrables\" = :joursOuvrables,
                    IPR = :IPR,
                    taux_retenu_absence = :tauxRetenuAbsence,
                    \"nJoursRecouvrement\" = :nJoursRecouvrement
                  WHERE \"idStructure\" = :id";

        $stmt = $this->db->prepare($query);

        // Bind des paramètres
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':phone1', $phone1, PDO::PARAM_STR);
        $stmt->bindParam(':phone2', $phone2, PDO::PARAM_STR);
        $stmt->bindParam(':siteweb', $siteweb, PDO::PARAM_STR);
        $stmt->bindParam(':logo', $logo, PDO::PARAM_STR);
        $stmt->bindParam(':joursOuvrables', $joursOuvrables, PDO::PARAM_INT);
        $stmt->bindParam(':IPR', $IPR, PDO::PARAM_STR);
        $stmt->bindParam(':tauxRetenuAbsence', $tauxRetenuAbsence, PDO::PARAM_STR);
        $stmt->bindParam(':nJoursRecouvrement', $nJoursRecouvrement, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Récupérer le logo par ID
    public function getLogoById($id)
    {
        $query = "SELECT logo FROM structure WHERE \"idStructure\" = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['logo'] : null; // Retourne le nom du logo ou null si aucun logo
    }

    public function deleteUserStructure($id){
        $query = "DELETE FROM user_structure WHERE id_user_structure='$id'";
        $stmt = $this->db->query($query);
        return $stmt;
    }
    // Vérifier si une structure existe
    public function checkStructureExists($idStructure)
    {
        $query = "SELECT COUNT(*) as count FROM structure WHERE \"idStructure\" = :idStructure";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['idStructure' => $idStructure]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    // Create a new account
    public function addCompte($numeroCompte, $intituleCompte, $typeCompte, $classeCompte, $structureId)
    {
        $query = "INSERT INTO compte (numeroCompte, intituleCompte, typeCompte, classeCompte, \"dateEnregistrement\", \"Structure_idStructure\") 
                VALUES (:numeroCompte, :intituleCompte, :typeCompte, :classeCompte, NOW(), :structureId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':numeroCompte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':intituleCompte', $intituleCompte, PDO::PARAM_STR);
        $stmt->bindParam(':typeCompte', $typeCompte, PDO::PARAM_STR);
        $stmt->bindParam(':classeCompte', $classeCompte, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Retrieve all accounts
    public function getComptes()
    {
        $query = "SELECT * FROM compte";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve an account by its ID
    public function getCompteById($idCompte)
    {
        $query = "SELECT * FROM compte WHERE idCompte = :idCompte";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idCompte', $idCompte, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update an account
    public function updateCompte($idCompte, $numeroCompte,$intitule, $typeCompte, $classeCompte, $structureId)
    {
        $query = "UPDATE compte SET 
                    numeroCompte = :numeroCompte,
                    intituleCompte = :intituleCompte,
                    typeCompte = :typeCompte,
                    classeCompte = :classeCompte,
                    \"Structure_idStructure\" = :structureId
                  WHERE idCompte = :idCompte";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idCompte', $idCompte, PDO::PARAM_INT);
        $stmt->bindParam(':numeroCompte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':intituleCompte', $intitule, PDO::PARAM_STR);
        $stmt->bindParam(':typeCompte', $typeCompte, PDO::PARAM_STR);
        $stmt->bindParam(':classeCompte', $classeCompte, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Delete an account
    public function deleteCompte($idCompte)
    {
        $query = "DELETE FROM compte WHERE idCompte = :idCompte";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idCompte', $idCompte, PDO::PARAM_INT);

        return $stmt->execute();
    }
    public function checkDuplicateCompte($numeroCompte, $structureId)
    {
        $query = "SELECT COUNT(*) FROM compte WHERE numeroCompte = :numeroCompte AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':numeroCompte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function addJournal($nomJournal, $description, $codeJournal, $structureId)
    {
        $query = "INSERT INTO journaux (nom_journal, description, code_journal, \"dateEnregistrement\", \"Structure_idStructure\") 
                VALUES (:nomJournal, :description, :codeJournal, NOW(), :structureId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':nomJournal', $nomJournal, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function checkDuplicateJournal($codeJournal, $structureId)
    {
        $query = "SELECT COUNT(*) FROM journaux WHERE code_journal = :codeJournal AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function getJournaux()
    {
        $query = "SELECT * FROM journaux";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJournauxByUserAccess($userId)
    {
        $query = "SELECT * FROM journaux j INNER JOIN structure s ON j.\"Structure_idStructure\"=s.\"idStructure\"
        INNER JOIN user_structure u ON u.\"idStructure\"=s.\"idStructure\" WHERE u.\"idUser\"='$userId'";
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateJournal($idJournaux, $nomJournal, $codeJournal, $description, $structureId)
    {
        $query = "UPDATE journaux SET 
                    nom_journal = :nomJournal,
                    code_journal = :codeJournal,
                    description = :description,
                    \"Structure_idStructure\" = :structureId
                WHERE idJournaux = :idJournaux";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idJournaux', $idJournaux, PDO::PARAM_INT);
        $stmt->bindParam(':nomJournal', $nomJournal, PDO::PARAM_STR);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteJournal($idJournaux)
    {
        $query = "DELETE FROM journaux WHERE idJournaux = :idJournaux";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idJournaux', $idJournaux, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getUsersByJournal($journalId)
    {
        $query = "SELECT u.*,uj.* FROM user_journal uj INNER JOIN t_users u ON uj.\"idUser\" = u.\"idUser\" WHERE uj.Journal_idJournal = :journalId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':journalId', $journalId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUserToJournal($userId, $journalId)
    {
        $query = "INSERT INTO user_journal (\"idUser\", Journal_idJournal, \"dateEnregistrement\") VALUES (:userId, :journalId, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':journalId', $journalId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function removeUserFromJournal($userJournalId)
    {
        $query = "DELETE FROM user_journal WHERE id_user_journal = :userJournalId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userJournalId', $userJournalId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getAuthorizedComptes($userId)
    {
        $query = "SELECT c.* FROM compte c
                INNER JOIN structure s ON c.\"Structure_idStructure\" = s.\"idStructure\"
                INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
                WHERE us.\"idUser\" = :userId ORDER BY c.numeroCompte ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getComptesByStructure($structureId)
    {
        $query = "SELECT * FROM compte WHERE \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function addClient($noms, $adresse, $email, $telephone, $solde, $structureId, $compteId)
    {
        $query = "INSERT INTO client (noms, adresse, email, telephone, solde, \"dateEnregistrement\", \"Structure_idStructure\", Compte_idCompte) 
                VALUES (:noms, :adresse, :email, :telephone, :solde, NOW(), :structureId, :compteId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':noms', $noms, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getStructuresByUserAccess($userId, $search = '', $limit = 150)
{
    $query = "
        SELECT s.*
        FROM structure s
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND s.designation LIKE :search";
    }

    $query .= " ORDER BY s.designation ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



    public function getClientsByStructure($structureId, $search = '')
    {
        $query = "
            SELECT client.*, compte.numeroCompte, compte.intituleCompte 
            FROM client 
            LEFT JOIN compte ON client.Compte_idCompte = compte.idCompte 
            WHERE client.\"Structure_idStructure\" = :structureId
        ";
        
        if (!empty($search)) {
            $query .= " AND client.noms LIKE :search";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

    public function getClientById($id)
    {
        $query = "
            SELECT client.*, compte.numeroCompte, compte.intituleCompte 
            FROM client 
            LEFT JOIN compte ON client.Compte_idCompte = compte.idCompte 
            WHERE client.idClient = :clientId
        ";
        
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':clientId', $id, PDO::PARAM_INT);
       
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }




    public function getClientsByUserAccess($userId, $search = '')
    {
        $query = "
            SELECT client.*, compte.numeroCompte, compte.intituleCompte 
            FROM client 
            LEFT JOIN compte ON client.Compte_idCompte = compte.idCompte 
            INNER JOIN structure ON client.\"Structure_idStructure\" = structure.\"idStructure\"
            INNER JOIN user_structure ON structure.\"idStructure\" = user_structure.\"idStructure\"
            WHERE user_structure.\"idUser\" = :userId
        ";
        
        if (!empty($search)) {
            $query .= " AND client.noms LIKE :search";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function checkDuplicateClient($noms, $structureId)
    {
        $query = "SELECT COUNT(*) FROM client WHERE noms = :noms AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':noms', $noms, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function updateClient($idClient, $noms, $adresse, $email, $telephone, $solde, $structureId, $compteId)
    {
        $query = "UPDATE client SET 
                    noms = :noms,
                    adresse = :adresse,
                    email = :email,
                    telephone = :telephone,
                    solde = :solde,
                    \"Structure_idStructure\" = :structureId,
                    Compte_idCompte = :compteId
                WHERE idClient = :idClient";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idClient', $idClient, PDO::PARAM_INT);
        $stmt->bindParam(':noms', $noms, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteClient($idClient)
    {
        $query = "DELETE FROM client WHERE idClient = :idClient";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idClient', $idClient, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function checkDuplicateFournisseur($nom, $structureId)
    {
        $query = "SELECT COUNT(*) FROM fournisseur WHERE nom = :nom AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function addFournisseur($nom, $adresse, $email, $telephone, $solde, $structureId, $compteId)
    {
        $query = "INSERT INTO fournisseur (nom, adresse, email, telephone, solde, \"dateEnregistrement\", \"Structure_idStructure\", Compte_idCompte) 
                VALUES (:nom, :adresse, :email, :telephone, :solde, NOW(), :structureId, :compteId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getFournisseursByStructure($structureId, $search = '')
    {
        $query = "
            SELECT fournisseur.*, compte.numeroCompte, compte.intituleCompte 
            FROM fournisseur 
            LEFT JOIN compte ON fournisseur.Compte_idCompte = compte.idCompte 
            WHERE fournisseur.\"Structure_idStructure\" = :structureId
        ";
        
        if (!empty($search)) {
            $query .= " AND fournisseur.nom LIKE :search";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateFournisseur($idFournisseur, $nom, $adresse, $email, $telephone, $solde, $structureId, $compteId)
    {
        $query = "UPDATE fournisseur SET 
                    nom = :nom,
                    adresse = :adresse,
                    email = :email,
                    telephone = :telephone,
                    solde = :solde,
                    \"Structure_idStructure\" = :structureId,
                    Compte_idCompte = :compteId
                WHERE idFournisseur = :idFournisseur";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idFournisseur', $idFournisseur, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':telephone', $telephone, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteFournisseur($idFournisseur)
    {
        $query = "DELETE FROM fournisseur WHERE idFournisseur = :idFournisseur";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idFournisseur', $idFournisseur, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function checkDuplicateInvoice($numeroFacture, $clientId)
    {
        $query = "SELECT COUNT(*) FROM facture_client WHERE numeroFacture = :numeroFacture AND Client_idClient = :clientId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':clientId', $clientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function addInvoice($dateFacture, $montant, $motif, $numeroFacture, $statut, $userId, $clientId)
    {
        $query = "INSERT INTO facture_client (dateFacture, montant, motif, numeroFacture, statut, \"dateEnregistrement\", \"idUser\", Client_idClient) 
                VALUES (:dateFacture, :montant, :motif, :numeroFacture, :statut, NOW(), :userId, :clientId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':dateFacture', $dateFacture, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':motif', $motif, PDO::PARAM_STR);
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':statut', $statut, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':clientId', $clientId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getInvoicesByUserAccess($userId, $structureId, $search = '')
    {
        $query = "
            SELECT fc.idFacture_client AS idInvoice, fc.numeroFacture, fc.dateFacture, fc.montant, 
                fc.statut, fc.Client_idClient, c.noms AS clientName, s.designation AS structureName, c.\"Structure_idStructure\",fc.motif
            FROM facture_client fc
            INNER JOIN client c ON fc.Client_idClient = c.idClient
            INNER JOIN structure s ON c.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId AND s.\"idStructure\" = :structureId
            
        ";

        if (!empty($search)) {
            $query .= " AND (fc.numeroFacture LIKE :search OR c.noms LIKE :search) ORDER BY fc.idFacture_client DESC";
        }else{
            $query .= " ORDER BY fc.idFacture_client DESC LIMIT 20";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInvoicesByUserAccess2($userId,$structureId, $searchName = '', $startDate = '', $endDate = '')
{
    $query = "
        SELECT fc.idFacture_client AS idInvoice, fc.numeroFacture, fc.dateFacture, fc.montant, 
            fc.statut, fc.Client_idClient, c.noms AS clientName, s.designation AS structureName
        FROM facture_client fc
        INNER JOIN client c ON fc.Client_idClient = c.idClient
        INNER JOIN structure s ON c.\"Structure_idStructure\" = s.\"idStructure\"
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId AND s.\"idStructure\" = :structureId
    ";

    // Add conditions for search parameters
    if (!empty($searchName)) {
        $query .= " AND c.noms LIKE :searchName";
    }
    if (!empty($startDate)) {
        $query .= " AND fc.dateFacture >= :startDate";
    }
    if (!empty($endDate)) {
        $query .= " AND fc.dateFacture <= :endDate";
    }

    $query .= " ORDER BY fc.idFacture_client DESC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

    // Bind parameters for search conditions
    if (!empty($searchName)) {
        $searchTerm = '%' . $searchName . '%';
        $stmt->bindParam(':searchName', $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($startDate)) {
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    }
    if (!empty($endDate)) {
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function updateInvoice($idInvoice, $numeroFacture, $dateFacture, $montant)
    {
        $query = "
            UPDATE facture_client SET 
                numeroFacture = :numeroFacture,
                dateFacture = :dateFacture,
                montant = :montant
            WHERE idFacture_client = :idInvoice
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':dateFacture', $dateFacture, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteInvoice($idInvoice)
    {
        $query = "DELETE FROM facture_client WHERE idFacture_client = :idInvoice";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getFournisseursByUserAccess($userId, $search = '')
    {
        $query = "
            SELECT fournisseur.*, compte.numeroCompte, compte.intituleCompte 
            FROM fournisseur 
            LEFT JOIN compte ON fournisseur.Compte_idCompte = compte.idCompte 
            INNER JOIN structure ON fournisseur.\"Structure_idStructure\" = structure.\"idStructure\"
            INNER JOIN user_structure ON structure.\"idStructure\" = user_structure.\"idStructure\"
            WHERE user_structure.\"idUser\" = :userId
        ";
        
        if (!empty($search)) {
            $query .= " AND fournisseur.nom LIKE :search";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEntreeDepot($idManifesteEntree, $dateOperation, $observation, $transporteur, $referenceDocument, $userId, $depotId, $fournisseurId)
    {
        $query = "UPDATE manifeste_entree SET 
                    dateOperation = :dateOperation,
                    observation = :observation,
                    transporteur = :transporteur,
                    reference_document = :referenceDocument,
                    \"idUser\" = :userId,
                    \"Depot_idDepot\" = :depotId,
                    Fournisseur_idFournisseur = :fournisseurId
                WHERE idManifeste_entree = :idManifesteEntree";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':transporteur', $transporteur, PDO::PARAM_STR);
        $stmt->bindParam(':referenceDocument', $referenceDocument, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);
        $stmt->bindParam(':idManifesteEntree', $idManifesteEntree, PDO::PARAM_INT);

        return $stmt->execute();
    }

    

    
    public function checkDuplicateFournisseurInvoice($numeroFacture, $fournisseurId)
    {
        $query = "SELECT COUNT(*) FROM facture_fournisseur WHERE numeroFacture = :numeroFacture AND Fournisseur_idFournisseur = :fournisseurId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function addSupplierInvoice($dateFacture, $montant, $motif, $numeroFacture, $statut, $userId, $fournisseurId)
    {
        $query = "INSERT INTO facture_fournisseur (dateFacture, montant, motif, numeroFacture, statut, \"dateEnregistrement\", \"idUser\", Fournisseur_idFournisseur) 
                VALUES (:dateFacture, :montant, :motif, :numeroFacture, :statut, NOW(), :userId, :fournisseurId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':dateFacture', $dateFacture, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':motif', $motif, PDO::PARAM_STR);
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':statut', $statut, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getSupplierInvoicesByUserAccess($userId, $structureId, $search = '')
{
    $query = "
        SELECT ff.idFacture_fournisseur AS idInvoice, ff.numeroFacture, ff.dateFacture, ff.montant, 
               ff.statut, ff.Fournisseur_idFournisseur, f.nom AS fournisseurName, 
               s.designation AS structureName, f.\"Structure_idStructure\"
        FROM facture_fournisseur ff
        INNER JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
        INNER JOIN structure s ON f.\"Structure_idStructure\" = s.\"idStructure\"
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId AND s.\"idStructure\" = :structureId
    ";

    if (!empty($search)) {
        $query .= " AND (ff.numeroFacture LIKE :search OR f.nom LIKE :search) ORDER BY ff.idFacture_fournisseur DESC";
    }else{
        $query .= " ORDER BY ff.idFacture_fournisseur DESC LIMIT 20";
    }

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSupplierInvoicesByUserAccess2($userId, $structureId, $searchName = '', $startDate = '', $endDate = '')
{
    $query = "
        SELECT ff.idFacture_fournisseur AS idInvoice, ff.numeroFacture, ff.dateFacture, ff.montant, 
               ff.statut, ff.Fournisseur_idFournisseur, f.nom AS fournisseurName, 
               s.designation AS structureName, f.\"Structure_idStructure\"
        FROM facture_fournisseur ff
        INNER JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
        INNER JOIN structure s ON f.\"Structure_idStructure\" = s.\"idStructure\"
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId AND s.\"idStructure\" = :structureId
    ";

    // Add conditions for search parameters
    if (!empty($searchName)) {
        $query .= " AND f.nom LIKE :searchName";
    }
    if (!empty($startDate)) {
        $query .= " AND ff.dateFacture >= :startDate";
    }
    if (!empty($endDate)) {
        $query .= " AND ff.dateFacture <= :endDate";
    }

    $query .= " ORDER BY ff.idFacture_fournisseur DESC";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

    // Bind parameters for search conditions
    if (!empty($searchName)) {
        $searchTerm = '%' . $searchName . '%';
        $stmt->bindParam(':searchName', $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($startDate)) {
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    }
    if (!empty($endDate)) {
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateSupplierInvoice($idInvoice, $numeroFacture, $dateFacture, $montant)
{
    $query = "
        UPDATE facture_fournisseur SET 
            numeroFacture = :numeroFacture,
            dateFacture = :dateFacture,
            montant = :montant
        WHERE idFacture_fournisseur = :idInvoice
    ";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
    $stmt->bindParam(':dateFacture', $dateFacture, PDO::PARAM_STR);
    $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);

    return $stmt->execute();
}

public function deleteSupplierInvoice($idInvoice)
{
    $query = "DELETE FROM facture_fournisseur WHERE idFacture_fournisseur = :idInvoice";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    return $stmt->execute();
}

public function addPayment($datePaiement, $montant, $libelle, $depositaire, $userId, $idInvoice, $bankId)
{
    $query = "INSERT INTO paiement_client (\"datePaiement\", montant, libelle, depositaire, \"dateEnregistrement\", \"idUser\", Facture_client_idFacture_client, Banque_idBanque) 
              VALUES (:datePaiement, :montant, :libelle, :depositaire, NOW(), :userId, :idInvoice, :bankId)";
    $stmt = $this->db->prepare($query);

    $stmt->bindParam(':datePaiement', $datePaiement, PDO::PARAM_STR);
    $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
    $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
    $stmt->bindParam(':depositaire', $depositaire, PDO::PARAM_STR);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);

    return $stmt->execute();
}

public function getPaymentsByInvoiceId($idInvoice)
{
    $query = "
        SELECT pc.*, u.\"nomUser\" AS userName
        FROM paiement_client pc
        INNER JOIN t_users u ON pc.\"idUser\" = u.\"idUser\"
        WHERE pc.Facture_client_idFacture_client = :idInvoice
        ORDER BY pc.\"datePaiement\" DESC;
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getBanksByUserAccess($userId)
{
    $query = "
        SELECT b.* 
        FROM banks b
        INNER JOIN user_bank_access uba ON b.idBank = uba.bankId
        WHERE uba.userId = :userId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getTotalPaymentsForInvoice($idInvoice)
{
    $query = "SELECT SUM(montant) as totalPaid FROM paiement_client WHERE Facture_client_idFacture_client = :idInvoice";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['totalPaid'] : 0;
}

public function getInvoiceById($idInvoice)
{
    $query = "
        SELECT fc.montant, fc.statut, fc.numeroFacture, c.noms AS clientName,c.idClient as Client_idClient
        FROM facture_client fc
        INNER JOIN client c ON fc.Client_idClient = c.idClient
        WHERE fc.idFacture_client = :idInvoice
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function updateInvoiceStatus($idInvoice, $status)
{
    $query = "UPDATE facture_client SET statut = :status WHERE idFacture_client = :idInvoice";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    return $stmt->execute();
}

public function getPaymentById($paymentId)
{
    $query = "
        SELECT pc.*, c.\"Structure_idStructure\", u.\"nomUser\" AS userName
        FROM paiement_client pc
        INNER JOIN facture_client fc ON pc.Facture_client_idFacture_client = fc.idFacture_client
        INNER JOIN client c ON fc.Client_idClient = c.idClient
        INNER JOIN t_users u ON pc.\"idUser\" = u.\"idUser\"
        WHERE pc.idPaiement_client = :paymentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Method to cancel a payment by setting its amount to zero
// Add this method to the Structure class
public function updateBankBalance($bankId, $amount)
{
    $query = "UPDATE banque SET solde = solde + :amount WHERE idBanque = :bankId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
    return $stmt->execute();
}

// Modify the cancelPayment method in the Structure class
public function cancelPayment($paymentId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Retrieve the original payment details
        $payment = $this->getPaymentById($paymentId);
        if (!$payment) {
            return false; // Payment not found
        }

        // Update the payment amount to zero
        $query = "UPDATE paiement_client SET montant = 0 WHERE idPaiement_client = :paymentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
        $stmt->execute();

        // Update the bank balance
        $this->updateBankBalance($payment['Banque_idBanque'], -$payment['montant']);

        // Optionally, update the invoice status or log the cancellation
        $totalPaid = $this->getTotalPaymentsForInvoice($payment['Facture_client_idFacture_client']);
        if ($totalPaid == 0) {
            $this->updateInvoiceStatus($payment['Facture_client_idFacture_client'], 'Non Paye');
        } else {
            $this->updateInvoiceStatus($payment['Facture_client_idFacture_client'], 'Encours');
        }

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

public function cancelPayment_fournisseur($paymentId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Retrieve the original payment details
        $payment = $this->getSupplierPaymentById($paymentId);
        if (!$payment) {
            return false; // Payment not found
        }

        // Update the payment amount to zero
        $query = "UPDATE paiement_fournisseur SET montant = 0 WHERE idPaiement_fournisseur = :paymentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
        $stmt->execute();

        // Update the bank balance
        $this->updateBankBalance($payment['Banque_idBanque'], $payment['montant']);

        // Optionally, update the invoice status or log the cancellation
        $totalPaid = $this->getTotalSupplierPaymentsForInvoice($payment['Facture_fournisseur_idFacture_fournisseur']);
        if ($totalPaid == 0) {
            $this->updateSupplierInvoiceStatus($payment['Facture_fournisseur_idFacture_fournisseur'], 'Non Paye');
        } else {
            $this->updateSupplierInvoiceStatus($payment['Facture_fournisseur_idFacture_fournisseur'], 'Encours');
        }

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

// Add a new journal entry
public function addJournalAutomatique($dateOperation, $compte, $libelleCompte, $montantDebit, $montantCredit, $libele, $numPiece, $structureId, $idUser)
{
    $query = "INSERT INTO journal_automatique (dateOperation, compte, libelle_compte, montant_debit, montant_credit, libele, numPiece, \"Structure_idStructure\", \"idUser\") 
              VALUES (:dateOperation, :compte, :libelleCompte, :montantDebit, :montantCredit, :libele, :numPiece, :structureId, :idUser)";
    $stmt = $this->db->prepare($query);

    $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
    $stmt->bindParam(':compte', $compte, PDO::PARAM_STR);
    $stmt->bindParam(':libelleCompte', $libelleCompte, PDO::PARAM_STR);
    $stmt->bindParam(':montantDebit', $montantDebit, PDO::PARAM_STR);
    $stmt->bindParam(':montantCredit', $montantCredit, PDO::PARAM_STR);
    $stmt->bindParam(':libele', $libele, PDO::PARAM_STR);
    $stmt->bindParam(':numPiece', $numPiece, PDO::PARAM_STR);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
    $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);

    return $stmt->execute();
}

// Retrieve all journal entries
public function getJournalAutomatique()
{
    $query = "SELECT * FROM journal_automatique";
    return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

// Retrieve a journal entry by its ID
public function getJournalAutomatiqueById($idJournal)
{
    $query = "SELECT * FROM journal_automatique WHERE idJournal = :idJournal";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idJournal', $idJournal, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update a journal entry
public function updateJournalAutomatique($idJournal, $dateOperation, $compte, $montantDebit, $montantCredit, $libele, $numPiece, $structureId)
{
    $query = "UPDATE journal_automatique SET 
                dateOperation = :dateOperation,
                compte = :compte,
                montant_debit = :montantDebit,
                montant_credit = :montantCredit,
                libele = :libele,
                numPiece = :numPiece,
                \"Structure_idStructure\" = :structureId
              WHERE idJournal = :idJournal";

    $stmt = $this->db->prepare($query);

    $stmt->bindParam(':idJournal', $idJournal, PDO::PARAM_INT);
    $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
    $stmt->bindParam(':compte', $compte, PDO::PARAM_STR);
    $stmt->bindParam(':montantDebit', $montantDebit, PDO::PARAM_STR);
    $stmt->bindParam(':montantCredit', $montantCredit, PDO::PARAM_STR);
    $stmt->bindParam(':libele', $libele, PDO::PARAM_STR);
    $stmt->bindParam(':numPiece', $numPiece, PDO::PARAM_STR);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

    return $stmt->execute();
}

// Delete a journal entry
public function deleteJournalAutomatique($idJournal)
{
    $query = "DELETE FROM journal_automatique WHERE idJournal = :idJournal";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idJournal', $idJournal, PDO::PARAM_INT);

    return $stmt->execute();
}

public function getFournisseursById($id)
{
    $query = "
        SELECT fournisseur.*, compte.numeroCompte, compte.intituleCompte 
        FROM fournisseur 
        LEFT JOIN compte ON fournisseur.Compte_idCompte = compte.idCompte 
        WHERE fournisseur.idFournisseur = :id
    ";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add this method to the Structure class
public function reverseInvoiceJournalEntries($idInvoice,$idUser)
{
    // Retrieve the original invoice details
    $invoice = $this->getInvoiceById($idInvoice);
    if (!$invoice) {
        return false; // Invoice not found
    }

    // Retrieve client and account details
    $client = $this->getClientById($invoice['Client_idClient']);
    if (empty($client)) {
        return false; // Client not found
    }

    $compteClient = $client[0]['numeroCompte'];
    $libelleCompteClient = $client[0]['intituleCompte'];
    $structureId = $client[0]['Structure_idStructure'];

    // Define the accounts for reversal
    $compteCredit = "70"; // Sales account
    $libelleCompteCredit = "Vente Marchandise";
    $compteTVA = "44571"; // VAT account
    $libelleCompteTVA = "TVA Collectée";

    // Calculate amounts
    $montantTTC = $invoice['montant'];
    $tauxTVA = 0.16; // 16% VAT
    $montantHT = $montantTTC / (1 + $tauxTVA);
    $montantTVA = $montantTTC - $montantHT;

    // Reverse the journal entries
    $dateOperation = date('Y-m-d');
    $libele = "Annulation facture client: " . $invoice['numeroFacture'];
    $numPiece = $invoice['numeroFacture'];

    // Reverse the client's account entry
    $this->addJournalAutomatique($dateOperation, $compteClient, $libelleCompteClient, 0, $montantTTC, $libele, $numPiece, $structureId,$idUser);

    // Reverse the sales account entry
    $this->addJournalAutomatique($dateOperation, $compteCredit, $libelleCompteCredit, $montantHT, 0, $libele, $numPiece, $structureId,$idUser);

    // Reverse the VAT account entry
    $this->addJournalAutomatique($dateOperation, $compteTVA, $libelleCompteTVA, $montantTVA, 0, $libele, $numPiece, $structureId,$idUser);

    return true;
}



public function reverseSupplierInvoiceJournalEntries($idSupplierInvoice,$idUser)
{
    // Retrieve the original supplier invoice details
    $supplierInvoice = $this->getSupplierInvoiceById($idSupplierInvoice);
    if (!$supplierInvoice) {
        return false; // Supplier invoice not found
    }

    // Retrieve supplier and account details
    $supplier = $this->getFournisseursById($supplierInvoice['Fournisseur_idFournisseur']);
    if (empty($supplier)) {
        return false; // Supplier not found
    }

    $compteSupplier = $supplier[0]['numeroCompte'];
    $libelleCompteSupplier = $supplier[0]['intituleCompte'];
    $structureId = $supplier[0]['Structure_idStructure'];

    // Define the accounts for reversal
    $compteDebit = "60"; // Purchases account
    $libelleCompteDebit = "Achat Marchandise";
    $compteTVA = "44566"; // VAT account
    $libelleCompteTVA = "TVA Déductible";

    // Calculate amounts
    $montantTTC = $supplierInvoice['montant'];
    $tauxTVA = 0.16; // 16% VAT
    $montantHT = $montantTTC / (1 + $tauxTVA);
    $montantTVA = $montantTTC - $montantHT;

    // Reverse the journal entries
    $dateOperation = date('Y-m-d');
    $libele = "Annulation facture fournisseur: " . $supplierInvoice['numeroFacture'];
    $numPiece = $supplierInvoice['numeroFacture'];

    

    // Debit the supplier's account with the net amount
    $this->addJournalAutomatique($dateOperation, $compteSupplier, $libelleCompteSupplier, $montantTTC, 0, $libele, $numPiece, $structureId,$idUser);

    // Credit the purchases account with the net amount
    $this->addJournalAutomatique($dateOperation, $compteDebit, $libelleCompteDebit, 0, $montantHT, $libele, $numPiece, $structureId,$idUser);

    // Credit the VAT account with the VAT amount
    $this->addJournalAutomatique($dateOperation, $compteTVA, $libelleCompteTVA, 0, $montantTVA, $libele, $numPiece, $structureId,$idUser);

    return true;
}


public function getBanksById($bankId)
{
    $query = "SELECT c.numeroCompte as numeroCompte,c.intituleCompte as designation FROM banque as b INNER JOIN compte as c ON b.Compte_idCompte=c.idCompte WHERE idBanque = :bankId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function reversePaymentJournalEntries($idPaiement,$idUser)
{
    // Retrieve the original payment details
    $payment = $this->getPaymentById($idPaiement);
    if (!$payment) {
        return false; // Payment not found
    }

    // Retrieve related invoice details
    $invoice = $this->getInvoiceById($payment['idInvoice']);
    if (!$invoice) {
        return false; // Invoice not found
    }

    // Retrieve client and bank account details
    $client = $this->getClientById($invoice['Client_idClient']);
    $bankDetails = $this->getBanksById($payment['bankId']);

    if (empty($client) || empty($bankDetails)) {
        return false; // Client or bank details not found
    }

    $compteClient = $client[0]['numeroCompte'];
    $libelleCompteClient = $client[0]['intituleCompte'];
    $bankAccountNumber = $bankDetails['numeroCompte'];
    $bankAccountLabel = $bankDetails['designation'];
    $structureId = $client[0]['Structure_idStructure'];

    // Reverse the journal entries
    $dateOperation = date('Y-m-d');
    $libele = "Annulation paiement: " . $payment['libelle'];
    $numPiece = $invoice['numeroFacture'];

    // Reverse the bank account entry
    $this->addJournalAutomatique($dateOperation, $bankAccountNumber, $bankAccountLabel, 0, $payment['montant'], $libele, $numPiece, $structureId,$idUser);

    // Reverse the client's account entry
    $this->addJournalAutomatique($dateOperation, $compteClient, $libelleCompteClient, $payment['montant'], 0, $libele, $numPiece, $structureId,$idUser);

    return true;
}

public function getSupplierPaymentsByInvoiceId($idInvoice)
{
    $query = "
        SELECT pf.*, u.\"nomUser\" AS userName
        FROM paiement_fournisseur pf
        INNER JOIN t_users u ON pf.\"idUser\" = u.\"idUser\"
        WHERE pf.Facture_fournisseur_idFacture_fournisseur = :idInvoice
        ORDER BY pf.\"datePaiement\" DESC;
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSupplierPaymentsById($id) {
    $query = "
        SELECT 
            pf.idPaiement_fournisseur,
            pf.montant,
            pf.\"datePaiement\",
            pf.libelle,
            pf.beneficiaire,
            pf.\"modePaiement\",
            pf.\"dateEnregistrement\",
            f.nom AS fournisseurNom,
            f.adresse AS fournisseurAdresse,
            f.email AS fournisseurEmail,
            f.telephone AS fournisseurTelephone,
            ff.numeroFacture,
            ff.montant AS factureMontant,
            ff.dateFacture
        FROM 
            paiement_fournisseur pf
        JOIN 
            facture_fournisseur ff ON pf.Facture_fournisseur_idFacture_fournisseur = ff.idFacture_fournisseur
        JOIN 
            fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
        WHERE 
            pf.idPaiement_fournisseur = :id
    ";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Retrieve a supplier invoice by its ID
public function getSupplierInvoiceById($idInvoice)
{
    $query = "
        SELECT ff.*, f.nom AS fournisseurName
        FROM facture_fournisseur ff
        INNER JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
        WHERE ff.idFacture_fournisseur = :idInvoice
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Retrieve total payments for a supplier invoice
public function getTotalSupplierPaymentsForInvoice($idInvoice)
{
    $query = "SELECT SUM(montant) as totalPaid FROM paiement_fournisseur WHERE Facture_fournisseur_idFacture_fournisseur = :idInvoice";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['totalPaid'] : 0;
}

// Add a supplier payment
public function addSupplierPayment($datePaiement, $montant, $libelle, $beneficiaire, $modePaiement, $userId, $idInvoice, $bankId)
{
    $query = "INSERT INTO paiement_fournisseur (\"datePaiement\", montant, libelle, beneficiaire, \"modePaiement\", \"dateEnregistrement\", \"idUser\", Facture_fournisseur_idFacture_fournisseur, Banque_idBanque) 
              VALUES (:datePaiement, :montant, :libelle, :beneficiaire, :modePaiement, NOW(), :userId, :idInvoice, :bankId)";
    $stmt = $this->db->prepare($query);

    $stmt->bindParam(':datePaiement', $datePaiement, PDO::PARAM_STR);
    $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
    $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
    $stmt->bindParam(':beneficiaire', $beneficiaire, PDO::PARAM_STR);
    $stmt->bindParam(':modePaiement', $modePaiement, PDO::PARAM_STR);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);

    return $stmt->execute();
}

// Update the status of a supplier invoice
public function updateSupplierInvoiceStatus($idInvoice, $status)
{
    $query = "UPDATE facture_fournisseur SET statut = :status WHERE idFacture_fournisseur = :idInvoice";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':idInvoice', $idInvoice, PDO::PARAM_INT);
    return $stmt->execute();
}

// Retrieve a supplier by its ID
public function getSupplierById($idFournisseur)
{
    $query = "
        SELECT fournisseur.*, 
               compte.numeroCompte, 
               compte.intituleCompte, 
               structure.*
        FROM fournisseur 
        LEFT JOIN compte ON fournisseur.Compte_idCompte = compte.idCompte 
        LEFT JOIN structure ON fournisseur.\"Structure_idStructure\" = structure.\"idStructure\"
        WHERE fournisseur.idFournisseur = :idFournisseur
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idFournisseur', $idFournisseur, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getSupplierPaymentById($paymentId)
{
    $query = "
        SELECT pc.*, c.\"Structure_idStructure\", u.\"nomUser\" AS userName,c.idFournisseur as Fournisseur_idFournisseur
        FROM paiement_fournisseur pc
        INNER JOIN facture_fournisseur fc ON pc.Facture_fournisseur_idFacture_fournisseur = fc.idFacture_fournisseur
        INNER JOIN fournisseur c ON fc.Fournisseur_idFournisseur = c.idFournisseur
        INNER JOIN t_users u ON pc.\"idUser\" = u.\"idUser\"
        WHERE pc.idPaiement_fournisseur = :paymentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':paymentId', $paymentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getUserCount()
    {
        $query = "SELECT COUNT(*) as count FROM t_users";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Method to get the count of structures
    public function getStructureCount()
    {
        $query = "SELECT COUNT(*) as count FROM structure";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Method to get the count of active personnel
    public function getActivePersonnelCount()
    {
        $query = "SELECT COUNT(*) as count FROM agent";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Method to get the count of permissions
    public function getPermissionCount()
    {
        $query = "SELECT COUNT(*) as count FROM t_permissions";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Method to get all statistics
    public function getStatistics()
    {
        return [
            'users' => $this->getUserCount(),
            'structures' => $this->getStructureCount(),
            'active_personnel' => $this->getActivePersonnelCount(),
            'permissions' => $this->getPermissionCount(),
        ];
    }

    // Check for duplicate groupe_depense designation within the same budget structure
    public function checkDuplicateGroupeDepense($designationGD, $budgetDepenseStructureId)
    {
        $query = "SELECT COUNT(*) FROM groupe_depense_structure WHERE \"designationGD\" = :designationGD AND \"Budget_depense_structure_idBudget_depense_structure\" = :budgetDepenseStructureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designationGD', $designationGD, PDO::PARAM_STR);
        $stmt->bindParam(':budgetDepenseStructureId', $budgetDepenseStructureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    // Add a new groupe_depense
    public function addGroupeDepense($designationGD, $soldeGD, $budgetDepenseStructureId)
    {
        $query = "INSERT INTO groupe_depense_structure (\"designationGD\", \"soldeGD\", \"Budget_depense_structure_idBudget_depense_structure\") 
                  VALUES (:designationGD, :soldeGD, :budgetDepenseStructureId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':designationGD', $designationGD, PDO::PARAM_STR);
        $stmt->bindParam(':soldeGD', $soldeGD, PDO::PARAM_STR);
        $stmt->bindParam(':budgetDepenseStructureId', $budgetDepenseStructureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Method to add a new budget depense
    public function addBudgetDepense($designation, $annee, $solde_b_depense, $userId, $structureId)
    {
        $query = "INSERT INTO budget_depense_structure (designation, annee, solde_b_depense, \"dateEnregistrement\", \"idUser\", \"Structure_idStructure\") 
                  VALUES (:designation, :annee, :solde_b_depense, NOW(), :idUser, :structureId)";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_STR);
        $stmt->bindParam(':solde_b_depense', $solde_b_depense, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Method to check for duplicate budget depense
    public function checkDuplicateBudgetDepense($designation, $annee, $structureId)
    {
        $query = "SELECT COUNT(*) FROM budget_depense_structure 
                  WHERE designation = :designation 
                  AND annee = :annee 
                  AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    // Check for duplicate budget recette designation within the same year and structure
    public function checkDuplicateBudgetRecette($designation, $annee, $structureId)
    {
        $query = "SELECT COUNT(*) FROM budget_recette_structure 
                  WHERE designation = :designation 
                  AND annee = :annee 
                  AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    // Add a new budget recette
    public function addBudgetRecette($designation, $annee, $solde_b_recette, $userId, $structureId)
    {
        $query = "INSERT INTO budget_recette_structure (designation, annee, solde_b_recette, \"dateEnregistrement\", \"idUser\", \"Structure_idStructure\") 
                  VALUES (:designation, :annee, :solde_b_recette, NOW(), :idUser, :structureId)";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':annee', $annee, PDO::PARAM_STR);
        $stmt->bindParam(':solde_b_recette', $solde_b_recette, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Retrieve budgets by structure ID
    public function getBudgetsByStructure($structureId)
    {
        $query = "SELECT * FROM budget_depense_structure WHERE \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBudgetsByUser($userId)
    {
        $query = "SELECT * FROM budget_depense_structure as bd
        INNER JOIN user_budget_depense as u ON u.\"Budget_depense_structure_idBudget_depense_structure\"=bd.idBudget_depense_structure WHERE u.\"idUser\" = :iduser";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':iduser', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to get the total of all groupe de dépense for a given budget
    public function getTotalGroupeDepense($budgetDepenseStructureId)
    {
        $query = "SELECT SUM(\"soldeGD\") as total FROM groupe_depense_structure WHERE \"Budget_depense_structure_idBudget_depense_structure\" = :budgetDepenseStructureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetDepenseStructureId', $budgetDepenseStructureId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['total'] : 0;
    }

    // Method to get the budget limit for a given budget
    public function getBudgetLimit($budgetDepenseStructureId)
    {
        $query = "SELECT solde_b_depense FROM budget_depense_structure WHERE idBudget_depense_structure = :budgetDepenseStructureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetDepenseStructureId', $budgetDepenseStructureId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['solde_b_depense'] : 0;
    }

    // Fetch recette budgets by structure ID
    public function getRecetteBudgetsByStructure($structureId)
    {
        $query = "SELECT * FROM budget_recette_structure WHERE \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecetteBudgetsByUser($userId)
    {
        $query = "SELECT * FROM budget_recette_structure as br
        INNER JOIN user_budget_recette as u ON u.\"Budget_recette_structure_idBudget_recette_structure\"=br.idBudget_recette_structure WHERE u.\"idUser\" = :iduser";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':iduser', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a new recette budget
    public function addRecetteBudget($structureId, $designation, $solde)
    {
        $query = "INSERT INTO budget_recette_structure (\"Structure_idStructure\", designation, solde) VALUES (:structureId, :designation, :solde)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->execute();

        return $this->db->lastInsertId();
    }

    // Update an existing recette budget
    public function updateRecetteBudget($budgetId, $designation, $solde)
    {
        $query = "UPDATE budget_recette_structure SET designation = :designation, solde = :solde WHERE idBudget_recette_structure = :budgetId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetId', $budgetId, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount();
    }

    // Delete a recette budget
    public function deleteRecetteBudget($budgetId)
    {
        $query = "DELETE FROM budget_recette_structure WHERE idBudget_recette_structure = :budgetId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetId', $budgetId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

     // Check for duplicate groupe_recette designation within the same budget structure
     public function checkDuplicateGroupeRecette($designationGR, $budgetRecetteStructureId)
     {
         $query = "SELECT COUNT(*) FROM groupe_recette_structure WHERE \"designationGR\" = :designationGR AND \"Budget_recette_structure_idBudget_recette_structure\" = :budgetRecetteStructureId";
         $stmt = $this->db->prepare($query);
         $stmt->bindParam(':designationGR', $designationGR, PDO::PARAM_STR);
         $stmt->bindParam(':budgetRecetteStructureId', $budgetRecetteStructureId, PDO::PARAM_INT);
         $stmt->execute();
 
         return $stmt->fetchColumn() > 0;
     }
 
     // Add a new groupe_recette
     public function addGroupeRecette($designationGR, $soldeGR, $budgetRecetteStructureId)
     {
         $query = "INSERT INTO groupe_recette_structure (\"designationGR\", \"soldeGR\", \"Budget_recette_structure_idBudget_recette_structure\") 
                   VALUES (:designationGR, :soldeGR, :budgetRecetteStructureId)";
         $stmt = $this->db->prepare($query);
 
         $stmt->bindParam(':designationGR', $designationGR, PDO::PARAM_STR);
         $stmt->bindParam(':soldeGR', $soldeGR, PDO::PARAM_STR);
         $stmt->bindParam(':budgetRecetteStructureId', $budgetRecetteStructureId, PDO::PARAM_INT);
 
         return $stmt->execute();
     }

     // Retrieve all groupes de dépenses
    public function getGroupesDepense()
    {
        $query = "SELECT * FROM groupe_depense_structure ORDER BY \"designationGD\" ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve all comptes comptables
    public function getComptesComptables()
    {
        $query = "SELECT * FROM compte ORDER BY intituleCompte ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve groupes de dépenses by user access
    public function getGroupesDepenseByUserAccess($userId)
    {
        $query = "
            SELECT gds.*, bds.designation
            FROM groupe_depense_structure gds
            INNER JOIN structure s ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            INNER JOIN budget_depense_structure bds ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
            WHERE us.\"idUser\" = :userId
            ORDER BY gds.\"designationGD\" ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGroupesDepenseByUserAccess2($userId, $searchTerm = null, $limit = null)
    {
        $query = "
            SELECT 
                gds.*, 
                bds.designation AS designation_budget
            FROM groupe_depense_structure gds
            INNER JOIN budget_depense_structure bds 
                ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
            INNER JOIN structure s 
                ON bds.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us 
                ON s.\"idStructure\" = us.\"idStructure\"
            INNER JOIN user_budget_depense ubd
                ON bds.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
            WHERE us.\"idUser\" = :userId
            AND ubd.\"idUser\" = :userId
        ";

        // Add a filter if a search term is provided
        if ($searchTerm) {
            $query .= " AND (gds.\"designationGD\" LIKE :searchTerm OR bds.designation LIKE :searchTerm OR bds.annee LIKE :searchTerm)";
        }

        $query .= " ORDER BY gds.\"designationGD\" ASC";

        // Add the limit if provided
        if ($limit) {
            $query .= " LIMIT :limit";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if ($searchTerm) {
            $searchTerm = "%$searchTerm%";
            $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
        }

        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Retrieve comptes comptables by user access
    public function getComptesComptablesByUserAccess($userId)
    {
        $query = "
            SELECT c.*
            FROM compte c
            INNER JOIN structure s ON c.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId
            ORDER BY c.intituleCompte ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a new ligne de dépense
    public function addLigneDepense($codeLigne, $designation, $montant, $solde, $groupeDepenseId, $compteId)
    {
        $query = "INSERT INTO ligne_depense_structure (codeLigne, designation, montant, solde, Groupe_depense_structure_idGroupe_depense_structure, Compte_idCompte) 
                  VALUES (:codeLigne, :designation, :montant, :solde, :groupeDepenseId, :compteId)";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':codeLigne', $codeLigne, PDO::PARAM_STR);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':groupeDepenseId', $groupeDepenseId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Retrieve groupes de recette by user access
    public function getGroupesRecetteByUserAccess($userId)
    {
        $query = "
            SELECT gr.*, brs.designation AS budgetDesignation, brs.solde_b_recette AS budgetSolde
            FROM groupe_recette_structure gr
            INNER JOIN budget_recette_structure brs ON gr.\"Budget_recette_structure_idBudget_recette_structure\" = brs.idBudget_recette_structure
            INNER JOIN structure s ON brs.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId
            ORDER BY gr.\"designationGR\" ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGroupesRecetteByUserAccess2($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT gr.*, brs.designation AS budgetDesignation, brs.solde_b_recette AS budgetSolde
            FROM groupe_recette_structure gr
            INNER JOIN budget_recette_structure brs ON gr.\"Budget_recette_structure_idBudget_recette_structure\" = brs.idBudget_recette_structure
            INNER JOIN structure s ON brs.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            INNER JOIN user_budget_recette ubr ON brs.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
            WHERE us.\"idUser\" = :userId AND ubr.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (gr.\"designationGR\" LIKE :search OR brs.designation LIKE :search OR brs.annee LIKE :search)";
        }

        $query .= " ORDER BY gr.\"designationGR\" ASC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addLigneRecette($codeLigne, $designation, $montant, $solde, $groupeRecetteId, $compteId)
    {
        $query = "INSERT INTO ligne_recette_structure (codeLigne, designation, montant, solde, Groupe_recette_structure_idGroupe_recette_structure, Compte_idCompte) 
                VALUES (:codeLigne, :designation, :montant, :solde, :groupeRecetteId, :compteId)";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':codeLigne', $codeLigne, PDO::PARAM_STR);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':groupeRecetteId', $groupeRecetteId, PDO::PARAM_INT);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getUsersByBudget($budgetId) {
        $query = "SELECT ub.*, u.*
                  FROM user_budget_depense ub
                  JOIN t_users u ON ub.\"idUser\" = u.\"idUser\"
                  WHERE ub.\"Budget_depense_structure_idBudget_depense_structure\" = :budgetId";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetId', $budgetId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUserToBudget($idUser, $idBudget) {
        try {
            $query = "INSERT INTO user_budget_depense (\"idUser\", \"Budget_depense_structure_idBudget_depense_structure\") VALUES (:idUser, :idBudget)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
            $stmt->bindParam(':idBudget', $idBudget, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function deleteUserFromBudget($idUserBudget) {
        try {
            $query = "DELETE FROM user_budget_depense WHERE iduser_budget_depense = :idUserBudget";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idUserBudget', $idUserBudget, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateBudget($idBudget, $designation, $annee, $solde) {
        try {
            $query = "UPDATE budget_depense_structure SET designation = :designation, annee = :annee, solde_b_depense = :solde WHERE idBudget_depense_structure = :idBudget";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idBudget', $idBudget, PDO::PARAM_INT);
            $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
            $stmt->bindParam(':annee', $annee, PDO::PARAM_STR);
            $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getBudgetsByStructure2($userId, $search = '', $limit = 20) {
        $query = "
            SELECT bds.*
            FROM budget_depense_structure bds
            JOIN user_structure us ON bds.\"Structure_idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId";
        
        // Ajouter la condition de recherche si $search est renseigné
        if (!empty($search)) {
            $query .= " AND (bds.designation LIKE :search OR bds.annee LIKE :search)";
        }
        
        $query .= " ORDER BY bds.idBudget_depense_structure DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
    
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    

    public function getRecetteBudgetsByStructure2($userId, $search = '', $limit = 20) {
        $query = "
            SELECT brs.*
            FROM budget_recette_structure brs
            JOIN user_structure us ON brs.\"Structure_idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId";
        
        // Ajouter la condition de recherche si $search est renseigné
        if (!empty($search)) {
            $query .= " AND (brs.designation LIKE :search OR brs.annee LIKE :search)";
        }
        
        $query .= " ORDER BY brs.idBudget_recette_structure DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
    
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    // Add a user to a budget recette
    public function addUserToBudgetRecette($idUser, $idBudgetRecette)
    {
        $query = "INSERT INTO user_budget_recette (\"idUser\", \"Budget_recette_structure_idBudget_recette_structure\") VALUES (:idUser, :idBudgetRecette)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':idBudgetRecette', $idBudgetRecette, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Retrieve users by budget recette
    public function getUsersByBudgetRecette($budgetRecetteId)
    {
        $query = "SELECT ubr.*, u.* FROM user_budget_recette ubr JOIN t_users u ON ubr.\"idUser\" = u.\"idUser\" WHERE ubr.\"Budget_recette_structure_idBudget_recette_structure\" = :budgetRecetteId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':budgetRecetteId', $budgetRecetteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Delete a user from a budget recette
    public function deleteUserFromBudgetRecette($idUserBudgetRecette)
    {
        $query = "DELETE FROM user_budget_recette WHERE idUser_budget_recette = :idUserBudgetRecette";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idUserBudgetRecette', $idUserBudgetRecette, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getLignesByGroupe($groupeId) {
        $query = "
            SELECT 
                idligne_depense_structure, 
                codeLigne, 
                designation, 
                montant, 
                solde,c.*
            FROM ligne_depense_structure as l INNER JOIN compte as c ON l.Compte_idCompte=c.idCompte
            WHERE Groupe_depense_structure_idGroupe_depense_structure = :groupeId
            ORDER BY designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':groupeId', $groupeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLignesRecetteByGroupe($groupeId) {
        $query = "
            SELECT 
                l.*,c.*
            FROM ligne_recette_structure as l INNER JOIN compte as c ON l.Compte_idCompte=c.idCompte
            WHERE Groupe_recette_structure_idGroupe_recette_structure = :groupeId
            ORDER BY designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':groupeId', $groupeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLignesRecetteById($id) {
        $query = "
            SELECT 
                l.*,c.*
            FROM ligne_recette_structure as l INNER JOIN compte as c ON l.Compte_idCompte=c.idCompte
            WHERE idligne_recette_structure = :id
            ORDER BY designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLignesDepenseById($id) {
        $query = "
            SELECT 
                l.*,c.*
            FROM ligne_depense_structure as l INNER JOIN compte as c ON l.Compte_idCompte=c.idCompte
            WHERE idligne_depense_structure = :id
            ORDER BY designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateGroupeDepense($idGroupe, $designationGD, $soldeGD)
    {
        $query = "UPDATE groupe_depense_structure SET 
                    \"designationGD\" = :designationGD,
                    \"soldeGD\" = :soldeGD
                  WHERE \"idGroupe_depense_structure\" = :idGroupe";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
        $stmt->bindParam(':designationGD', $designationGD, PDO::PARAM_STR);
        $stmt->bindParam(':soldeGD', $soldeGD, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function updateGroupeRecette($idGroupe, $designationGD, $soldeGD)
    {
        $query = "UPDATE groupe_recette_structure SET 
                    \"designationGR\" = :designationGR,
                    \"soldeGR\" = :soldeGR
                  WHERE \"idGroupe_recette_structure\" = :idGroupe";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':idGroupe', $idGroupe, PDO::PARAM_INT);
        $stmt->bindParam(':designationGR', $designationGD, PDO::PARAM_STR);
        $stmt->bindParam(':soldeGR', $soldeGD, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function updateLigneDepense($idLigne, $codeLigne, $designation, $montant)
    {
        $query = "UPDATE ligne_depense_structure SET 
                    codeLigne = :codeLigne,
                    designation = :designation,
                    montant = :montant
                WHERE idligne_depense_structure = :idLigne";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':idLigne', $idLigne, PDO::PARAM_INT);
        $stmt->bindParam(':codeLigne', $codeLigne, PDO::PARAM_STR);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function updateLigneRecette($idLigne, $codeLigne, $designation, $montant)
    {
        $query = "UPDATE ligne_recette_structure SET 
                    codeLigne = :codeLigne,
                    designation = :designation,
                    montant = :montant
                WHERE idligne_recette_structure = :idLigne";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':idLigne', $idLigne, PDO::PARAM_INT);
        $stmt->bindParam(':codeLigne', $codeLigne, PDO::PARAM_STR);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function deleteLigneDepense($idLigne)
    {
        $query = "DELETE FROM ligne_depense_structure WHERE idligne_depense_structure = :idLigne";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idLigne', $idLigne, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteLigneRecette($idLigne)
    {
        $query = "DELETE FROM ligne_recette_structure WHERE idligne_recette_structure = :idLigne";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idLigne', $idLigne, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Add a new depense
    public function addDepense($montantD, $motifD, $beneficiaire, $dateOperation, $idUser, $ligneDepenseId, $bankId, $etatDeBesoinId)
    {
        $query = "INSERT INTO depense_structure (montantD, motifD, beneficiaire, dateoperation, \"dateEnregistrement\", \"idUser\", ligne_depense_structure_idligne_depense_structure, Banque_idBanque, Etat_de_besoin_idEtat_de_besoin) 
                  VALUES (:montantD, :motifD, :beneficiaire, :dateOperation, NOW(), :idUser, :ligneDepenseId, :bankId, :etatDeBesoinId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':montantD', $montantD, PDO::PARAM_STR);
        $stmt->bindParam(':motifD', $motifD, PDO::PARAM_STR);
        $stmt->bindParam(':beneficiaire', $beneficiaire, PDO::PARAM_STR);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':ligneDepenseId', $ligneDepenseId, PDO::PARAM_INT);
        $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
        $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Update an existing depense
    public function updateDepense($idDepense, $montantD, $motifD, $beneficiaire, $dateOperation, $ligneDepenseId, $bankId, $etatDeBesoinId)
    {
        $query = "UPDATE depense_structure SET 
                    montantD = :montantD,
                    motifD = :motifD,
                    beneficiaire = :beneficiaire,
                    dateoperation = :dateOperation,
                    ligne_depense_structure_idligne_depense_structure = :ligneDepenseId,
                    Banque_idBanque = :bankId,
                    Etat_de_besoin_idEtat_de_besoin = :etatDeBesoinId
                  WHERE idDepense_structure = :idDepense";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idDepense', $idDepense, PDO::PARAM_INT);
        $stmt->bindParam(':montantD', $montantD, PDO::PARAM_STR);
        $stmt->bindParam(':motifD', $motifD, PDO::PARAM_STR);
        $stmt->bindParam(':beneficiaire', $beneficiaire, PDO::PARAM_STR);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':ligneDepenseId', $ligneDepenseId, PDO::PARAM_INT);
        $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
        $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Delete a depense
    public function deleteDepense($idDepense)
    {
        $query = "DELETE FROM depense_structure WHERE idDepense_structure = :idDepense";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idDepense', $idDepense, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Retrieve a depense by its ID
    public function getDepenseById($idDepense)
    {
        $query = "
            SELECT ds.*, 
                s.*,
                u.*
            FROM depense_structure ds
            INNER JOIN ligne_depense_structure lds ON ds.ligne_depense_structure_idligne_depense_structure = lds.idligne_depense_structure
            INNER JOIN groupe_depense_structure gds ON lds.Groupe_depense_structure_idGroupe_depense_structure = gds.\"idGroupe_depense_structure\"
            INNER JOIN budget_depense_structure bds ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
            INNER JOIN structure s ON bds.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN t_users u ON ds.\"idUser\" = u.\"idUser\"
            WHERE ds.idDepense_structure = :idDepense
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idDepense', $idDepense, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add a new recette
    public function addRecette($montantR, $motif, $depositaire, $dateOperation, $idUser, $ligneRecetteId, $bankId)
    {
        $query = "INSERT INTO recette_structure (montantR, motif, depositaire, dateOperation, \"dateEnregistrement\", \"idUser\", ligne_recette_structure_idligne_recette_structure, Banque_idBanque) 
                  VALUES (:montantR, :motif, :depositaire, :dateOperation, NOW(), :idUser, :ligneRecetteId, :bankId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':montantR', $montantR, PDO::PARAM_STR);
        $stmt->bindParam(':motif', $motif, PDO::PARAM_STR);
        $stmt->bindParam(':depositaire', $depositaire, PDO::PARAM_STR);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $idUser, PDO::PARAM_INT);
        $stmt->bindParam(':ligneRecetteId', $ligneRecetteId, PDO::PARAM_INT);
        $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Update an existing recette
    public function updateRecette($idRecette, $montantR, $motif, $depositaire, $dateOperation, $ligneRecetteId, $bankId)
    {
        $query = "UPDATE recette_structure SET 
                    montantR = :montantR,
                    motif = :motif,
                    depositaire = :depositaire,
                    dateOperation = :dateOperation,
                    ligne_recette_structure_idligne_recette_structure = :ligneRecetteId,
                    Banque_idBanque = :bankId
                  WHERE idRecette_structure = :idRecette";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idRecette', $idRecette, PDO::PARAM_INT);
        $stmt->bindParam(':montantR', $montantR, PDO::PARAM_STR);
        $stmt->bindParam(':motif', $motif, PDO::PARAM_STR);
        $stmt->bindParam(':depositaire', $depositaire, PDO::PARAM_STR);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':ligneRecetteId', $ligneRecetteId, PDO::PARAM_INT);
        $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Delete a recette
    public function deleteRecette($idRecette)
    {
        $query = "DELETE FROM recette_structure WHERE idRecette_structure = :idRecette";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idRecette', $idRecette, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Retrieve a recette by its ID
    public function getRecetteById($idRecette)
    {
        $query = "
            SELECT rs.*, 
                s.*,
                u.*
            FROM recette_structure rs
            INNER JOIN ligne_recette_structure lrs ON rs.ligne_recette_structure_idligne_recette_structure = lrs.idligne_recette_structure
            INNER JOIN groupe_recette_structure grs ON lrs.Groupe_recette_structure_idGroupe_recette_structure = grs.\"idGroupe_recette_structure\"
            INNER JOIN budget_recette_structure brs ON grs.\"Budget_recette_structure_idBudget_recette_structure\" = brs.idBudget_recette_structure
            INNER JOIN structure s ON brs.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN t_users u ON rs.\"idUser\" = u.\"idUser\"
            WHERE rs.idRecette_structure = :idRecette
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idRecette', $idRecette, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDepensesByUser($userId, $search = '', $limit = 50)
    {
        $query = "
            SELECT ds.*,lds.*
            FROM depense_structure ds
            INNER JOIN ligne_depense_structure lds ON ds.ligne_depense_structure_idligne_depense_structure = lds.idligne_depense_structure
            INNER JOIN groupe_depense_structure gds ON lds.Groupe_depense_structure_idGroupe_depense_structure = gds.\"idGroupe_depense_structure\"
            INNER JOIN budget_depense_structure bds ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
            INNER JOIN user_budget_depense ubd ON bds.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
            WHERE ubd.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (ds.motifD LIKE :search OR ds.beneficiaire LIKE :search OR ds.dateoperation LIKE :search)";
        }

        $query .= " ORDER BY ds.idDepense_structure DESC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecettesByUser($userId, $search = '', $limit = 50)
    {
        $query = "
            SELECT rs.*,lrs.*
            FROM recette_structure rs
            INNER JOIN ligne_recette_structure lrs ON rs.ligne_recette_structure_idligne_recette_structure = lrs.idligne_recette_structure
            INNER JOIN groupe_recette_structure grs ON lrs.Groupe_recette_structure_idGroupe_recette_structure = grs.\"idGroupe_recette_structure\"
            INNER JOIN budget_recette_structure brs ON grs.\"Budget_recette_structure_idBudget_recette_structure\" = brs.idBudget_recette_structure
            INNER JOIN user_budget_recette ubr ON brs.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
            WHERE ubr.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (rs.motif LIKE :search OR rs.depositaire LIKE :search OR rs.dateOperation LIKE :search)";
        }

        $query .= " ORDER BY rs.idRecette_structure DESC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve recette lines accessible by the user
    public function getLignesRecetteByUser($userId, $search = '', $limit = 50)
    {
        $query = "
            SELECT lrs.*, c.*
            FROM ligne_recette_structure lrs
            INNER JOIN compte c ON lrs.Compte_idCompte = c.idCompte
            INNER JOIN groupe_recette_structure grs ON lrs.Groupe_recette_structure_idGroupe_recette_structure = grs.\"idGroupe_recette_structure\"
            INNER JOIN budget_recette_structure brs ON grs.\"Budget_recette_structure_idBudget_recette_structure\" = brs.idBudget_recette_structure
            INNER JOIN user_budget_recette ubr ON brs.idBudget_recette_structure = ubr.\"Budget_recette_structure_idBudget_recette_structure\"
            WHERE ubr.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (lrs.designation LIKE :search OR c.intituleCompte LIKE :search)";
        }

        $query .= " ORDER BY lrs.designation ASC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve depense lines accessible by the user
    public function getLignesDepenseByUser($userId, $search = '', $limit = 50)
    {
        $query = "
            SELECT lds.*, c.*
            FROM ligne_depense_structure lds
            INNER JOIN compte c ON lds.Compte_idCompte = c.idCompte
            INNER JOIN groupe_depense_structure gds ON lds.Groupe_depense_structure_idGroupe_depense_structure = gds.\"idGroupe_depense_structure\"
            INNER JOIN budget_depense_structure bds ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
            INNER JOIN user_budget_depense ubd ON bds.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
            WHERE ubd.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (lds.designation LIKE :search OR c.intituleCompte LIKE :search)";
        }

        $query .= " ORDER BY lds.designation ASC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to add a new etat_de_besoin
    public function addEtatDeBesoin($dateElaboration, $libelle, $montant, $userId, $serviceId, $ligneDepenseId)
    {
        $query = "INSERT INTO etat_de_besoin (\"dateElaboration\", \"dateEnregistrement\", libelle, montant, \"idUser\", \"Service_idService\", \"idLigne_depense\") 
                  VALUES (:dateElaboration, NOW(), :libelle, :montant, :userId, :serviceId, :ligneDepenseId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':dateElaboration', $dateElaboration, PDO::PARAM_STR);
        $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);
        $stmt->bindParam(':ligneDepenseId', $ligneDepenseId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    // Method to add a new ligne_etat_besoin
    public function addLigneEtatBesoin($designation, $quantite, $prixUnitaire, $observation, $etatDeBesoinId)
    {
        $query = "INSERT INTO ligne_etat_besoin (designation, quantite, prixUnitaire, observation, \"dateEnregistrement\", Etat_de_besoin_idEtat_de_besoin) 
                  VALUES (:designation, :quantite, :prixUnitaire, :observation, NOW(), :etatDeBesoinId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
        $stmt->bindParam(':prixUnitaire', $prixUnitaire, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);

        return $stmt->execute();
    }

  
    public function editLigneEtatBesoin($ligne, $designation, $quantite, $prixUnitaire, $observation)
    {
        try {
            $query = "UPDATE ligne_etat_besoin 
                    SET designation = :designation,
                        quantite = :quantite,
                        prixUnitaire = :prixUnitaire,
                        observation = :observation
                    WHERE idLigne_etat_besoin = :ligne";
                    
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
            $stmt->bindParam(':quantite', $quantite, PDO::PARAM_STR);
            $stmt->bindParam(':prixUnitaire', $prixUnitaire, PDO::PARAM_STR);
            $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
            $stmt->bindParam(':ligne', $ligne, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log the error or handle it as needed
            error_log("Error updating ligne_etat_besoin: " . $e->getMessage());
            throw $e;
        }
    }

    // Method to get all services
    public function getServices()
    {
        $query = "SELECT * FROM service ORDER BY designation ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to get services by user access
    public function getServicesByUserAccess($userId)
    {
        $query = "
            SELECT DISTINCT s.*,st.designation as des_structure
            FROM service s
            INNER JOIN structure st ON s.\"Structure_idStructure\" = st.\"idStructure\"
            INNER JOIN user_structure us ON st.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId
            ORDER BY s.designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method to get états de besoin by user
    public function getEtatDeBesoinsByUser($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT edb.*, s.designation AS serviceDesignation
            FROM etat_de_besoin edb
            INNER JOIN service s ON edb.\"Service_idService\" = s.\"idService\"
            WHERE edb.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (edb.libelle LIKE :search OR s.designation LIKE :search OR edb.\"idEtat_de_besoin\" LIKE :search)";
        }

        $query .= " ORDER BY edb.\"idEtat_de_besoin\" DESC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEtatDeBesoinsByUserStructure($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT edb.*, s.designation AS serviceDesignation
            FROM etat_de_besoin edb
            INNER JOIN service s ON edb.\"Service_idService\" = s.\"idService\"
            INNER JOIN structure st ON st.\"idStructure\"=s.\"Structure_idStructure\"
            INNER JOIN user_structure us ON us.\"idStructure\"=st.\"idStructure\"
            INNER JOIN t_users u ON u.\"idUser\"=us.\"idUser\"
            WHERE us.\"idUser\" = :userId
        ";

        if (!empty($search)) {
            $query .= " AND (edb.libelle LIKE :search OR s.designation LIKE :search OR edb.\"idEtat_de_besoin\" LIKE :search)";
        }

        $query .= " ORDER BY edb.\"idEtat_de_besoin\" DESC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEtatDeBesoinsByUserAccess($userId, $search = '', $limit = 20)
{
    $query = "
        SELECT 
            edb.*, 
            s.designation AS serviceDesignation,
            d.idDepense_structure as idDepense,
            d.montantD,
            d.motifD,
            d.beneficiaire,
            d.dateoperation as dateDepense,
            b.designation as bankDesignation,
            b.numeroCompte as bankNumeroCompte,
            u.\"nomUser\" as userDepense
        FROM etat_de_besoin edb
        INNER JOIN service s ON edb.\"Service_idService\" = s.\"idService\"
        INNER JOIN ligne_depense_structure lds ON edb.\"idLigne_depense\" = lds.idligne_depense_structure
        INNER JOIN groupe_depense_structure gds ON lds.Groupe_depense_structure_idGroupe_depense_structure = gds.\"idGroupe_depense_structure\"
        INNER JOIN budget_depense_structure bds ON gds.\"Budget_depense_structure_idBudget_depense_structure\" = bds.idBudget_depense_structure
        INNER JOIN user_budget_depense ubd ON bds.idBudget_depense_structure = ubd.\"Budget_depense_structure_idBudget_depense_structure\"
        LEFT JOIN depense_structure d ON edb.\"idEtat_de_besoin\" = d.Etat_de_besoin_idEtat_de_besoin
        LEFT JOIN banque b ON d.Banque_idBanque = b.idBanque
        LEFT JOIN t_users u ON d.\"idUser\" = u.\"idUser\"
        WHERE ubd.\"idUser\" = :userId
    ";


    if (!empty($search)) {
        $query .= " AND (
            edb.libelle LIKE :search 
            OR s.designation LIKE :search 
            OR edb.montant LIKE :search 
            OR edb.\"idEtat_de_besoin\" LIKE :search
            OR d.motifD LIKE :search
            OR d.beneficiaire LIKE :search
            OR b.designation LIKE :search
        )";
    }

    $query .= " ORDER BY edb.\"idEtat_de_besoin\" DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    // Method to get lignes etat besoin by etat ID
    public function getLignesEtatBesoinByEtat($etatDeBesoinId)
    {
        $query = "
            SELECT leb.*
            FROM ligne_etat_besoin leb
            WHERE leb.Etat_de_besoin_idEtat_de_besoin = :etatDeBesoinId
            ORDER BY leb.designation ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEtatDeBesoinMontant($etatDeBesoinId, $montantLigne)
    {
        try {
            $query = "
                UPDATE etat_de_besoin
                SET montant = montant + :montantLigne
                WHERE \"idEtat_de_besoin\" = :etatDeBesoinId
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':montantLigne', $montantLigne, PDO::PARAM_STR);
            $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Log the error or handle it as needed
            error_log($e->getMessage());
            return false;
        }
    }

    // Method to get a specific ligne etat besoin by its ID
    public function getLigneEtatBesoinById($ligneId)
    {
        $query = "
            SELECT *
            FROM ligne_etat_besoin
            WHERE idLigne_etat_besoin = :ligneId
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ligneId', $ligneId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Method to delete a ligne etat besoin by its ID
    public function deleteLigneEtatBesoin($ligneId)
    {
        $query = "
            DELETE FROM ligne_etat_besoin
            WHERE idLigne_etat_besoin = :ligneId
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':ligneId', $ligneId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateEtatDeBesoin($etatDeBesoinId, $libelle,$ligne)
    {
        try {
            $query = "
                UPDATE etat_de_besoin
                SET 
                    libelle = :libelle, \"idLigne_depense\" = :ligne
                WHERE \"idEtat_de_besoin\" = :etatDeBesoinId
            ";
            $stmt = $this->db->prepare($query);

            // Bind parameters
            $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);
            $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
            $stmt->bindParam(':ligne', $ligne, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Log the error or handle it as needed
            error_log($e->getMessage());
            return false;
        }
    }

    // Method to get an État de Besoin by its ID, including related service and structure details
    public function getEtatDeBesoinById($etatDeBesoinId)
    {
        $query = "
            SELECT edb.*, 
                s.designation AS serviceDesignation, 
                st.designation AS structureDesignation,
                st.\"idStructure\",
                u.\"nomUser\" AS userName
            FROM etat_de_besoin edb
            INNER JOIN service s ON edb.\"Service_idService\" = s.\"idService\"
            INNER JOIN structure st ON s.\"Structure_idStructure\" = st.\"idStructure\"
            INNER JOIN t_users u ON edb.\"idUser\" = u.\"idUser\"
            WHERE edb.\"idEtat_de_besoin\" = :etatDeBesoinId
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':etatDeBesoinId', $etatDeBesoinId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function validateEtatDeBesoin($etatId, $userId)
    {
        $query = "
            UPDATE etat_de_besoin
            SET validation1 = :userId
            WHERE \"idEtat_de_besoin\" = :etatId
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':etatId', $etatId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getDepotsByStructure($userId, $search = '', $limit = 20)
    {
        $query = "
            SELECT d.*
            FROM depot d
            INNER JOIN structure s ON d.\"Structure_idStructure\" = s.\"idStructure\"
            INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
            WHERE us.\"idUser\" = :userId
        ";
        
        if (!empty($search)) {
            $query .= " AND (d.designation LIKE :search 
                        OR d.adresse LIKE :search 
                        OR d.typeDepot LIKE :search)";
        }
        
        $query .= " ORDER BY d.designation ASC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersByDepot($depotId)
    {
        $query = "
            SELECT u.*, ud.iduser_depot
            FROM user_depot ud
            INNER JOIN t_users u ON ud.\"idUser\" = u.\"idUser\"
            WHERE ud.\"Depot_idDepot\" = :depotId
            ORDER BY u.\"nomUser\" ASC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addUserToDepot($userId, $depotId)
    {
        $query = "INSERT INTO user_depot (\"idUser\", \"Depot_idDepot\") VALUES (:userId, :depotId)";
        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function deleteUserFromDepot($userDepotId)
    {
        $query = "DELETE FROM user_depot WHERE iduser_depot = :userDepotId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userDepotId', $userDepotId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function checkDuplicateDepot($designation, $structureId)
    {
        $query = "SELECT COUNT(*) FROM depot WHERE designation = :designation AND \"Structure_idStructure\" = :structureId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    public function addDepot($designation, $adresse, $typeDepot, $userId, $structureId)
    {
        $query = "INSERT INTO depot (designation, adresse, typeDepot, \"dateEnregistrement\", \"idUser\", \"Structure_idStructure\") 
                VALUES (:designation, :adresse, :typeDepot, NOW(), :userId, :structureId)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
        $stmt->bindParam(':typeDepot', $typeDepot, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function checkDuplicateDepotEdit($designation, $structureId, $idDepot)
{
    $query = "SELECT COUNT(*) FROM depot 
              WHERE designation = :designation 
              AND \"Structure_idStructure\" = :structureId 
              AND \"idDepot\" != :idDepot";
              
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
    $stmt->bindParam(':idDepot', $idDepot, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchColumn() > 0;
}

public function updateDepot($idDepot, $designation, $adresse, $typeDepot, $userId, $structureId)
{
    $query = "UPDATE depot 
              SET designation = :designation,
                  adresse = :adresse,
                  typeDepot = :typeDepot,
                  \"idUser\" = :userId,
                  \"Structure_idStructure\" = :structureId
              WHERE \"idDepot\" = :idDepot";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':idDepot', $idDepot, PDO::PARAM_INT);
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->bindParam(':adresse', $adresse, PDO::PARAM_STR);
    $stmt->bindParam(':typeDepot', $typeDepot, PDO::PARAM_STR);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);

    return $stmt->execute();
}

public function getEntreesByDepot($depotId)
{
    $query = "SELECT 
                me.*,
                f.nom as fournisseur
              FROM manifeste_entree me
              LEFT JOIN fournisseur f ON me.Fournisseur_idFournisseur = f.idFournisseur 
              WHERE me.\"Depot_idDepot\" = :depotId
              ORDER BY me.dateOperation DESC";
              
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEntreesByUserAccess($userId, $search = '', $limit = 100)
{
    $query = "
        SELECT 
            me.*,
            f.nom as fournisseur
        FROM manifeste_entree me
        LEFT JOIN fournisseur f ON me.Fournisseur_idFournisseur = f.idFournisseur
        INNER JOIN depot d ON me.\"Depot_idDepot\" = d.\"idDepot\"
        INNER JOIN user_depot ud ON d.\"idDepot\" = ud.\"Depot_idDepot\"
        WHERE ud.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND (me.reference_document LIKE :search OR f.nom LIKE :search)";
    }

    $query .= " ORDER BY me.idManifeste_entree DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getDetailsEntreeByManifest($manifestId)
{
    $query = "SELECT 
                de.idDetail_entree,
                de.designation,
                de.unite,
                de.quantite
              FROM detail_entree de
              WHERE de.Manifeste_entree_idManifeste_entree = :manifestId
              ORDER BY de.idDetail_entree DESC";
              
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':manifestId', $manifestId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addEntreeDepot($dateOperation, $observation, $transporteur, $referenceDocument, $userId, $depotId, $fournisseurId)
{
    try {
        $this->db->beginTransaction();

        $query = "INSERT INTO manifeste_entree (
            dateOperation, 
            observation,
            transporteur, 
            reference_document,
            \"dateEnregistrement\", 
            \"idUser\", 
            \"Depot_idDepot\", 
            Fournisseur_idFournisseur
        ) VALUES (
            :dateOperation,
            :observation, 
            :transporteur, 
            :referenceDocument,
            NOW(), 
            :userId, 
            :depotId, 
            :fournisseurId
        )";
        
        $stmt = $this->db->prepare($query);
        
        // Binding des paramètres avec vérification des valeurs nulles
        $stmt->bindParam(':dateOperation', $dateOperation);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':transporteur', $transporteur, PDO::PARAM_STR);
        $stmt->bindParam(':referenceDocument', $referenceDocument, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if ($result) {
            $lastInsertId = $this->db->lastInsertId();
            $this->db->commit();
            return $lastInsertId;
        } else {
            throw new Exception('Échec de l\'insertion du manifeste d\'entrée');
        }
    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
}


public function addDetailToEntry($manifestId, $designation, $unite, $quantite)
    {
        $query = "INSERT INTO detail_entree (Manifeste_entree_idManifeste_entree, designation, unite, quantite) 
                VALUES (:manifestId, :designation, :unite, :quantite)";
        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':manifestId', $manifestId, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':unite', $unite, PDO::PARAM_STR);
        $stmt->bindParam(':quantite', $quantite, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteEntreeDepot($entryId, $userId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Check if the entry exists
        $query = "SELECT * FROM manifeste_entree WHERE idManifeste_entree = :entryId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':entryId', $entryId, PDO::PARAM_INT);
        $stmt->execute();
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new Exception("Entry not found.");
        }

        // Delete the entry details
        $query = "DELETE FROM detail_entree WHERE Manifeste_entree_idManifeste_entree = :entryId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':entryId', $entryId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete the entry
        $query = "DELETE FROM manifeste_entree WHERE idManifeste_entree = :entryId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':entryId', $entryId, PDO::PARAM_INT);
        $stmt->execute();

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

public function deleteDetailEntry($detailId, $userId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Check if the detail entry exists
        $query = "SELECT * FROM detail_entree WHERE idDetail_entree = :detailId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':detailId', $detailId, PDO::PARAM_INT);
        $stmt->execute();
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$detail) {
            throw new Exception("Detail entry not found.");
        }

        // Delete the detail entry
        $query = "DELETE FROM detail_entree WHERE idDetail_entree = :detailId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':detailId', $detailId, PDO::PARAM_INT);
        $stmt->execute();

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

public function getEntreeById($entryId)
    {
        try {
            $query = "
                SELECT me.*, d.*,u.*
                FROM manifeste_entree me
                INNER JOIN depot d ON me.\"Depot_idDepot\" = d.\"idDepot\"
                INNER JOIN t_users u ON me.\"idUser\" = u.\"idUser\"
                WHERE me.idManifeste_entree = :entryId
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':entryId', $entryId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getSortiesByUserAccess($userId, $search = '', $limit = 100)
{
    $query = "
        SELECT 
            ms.*,
            f.noms as client
        FROM manifeste_sortie ms
        LEFT JOIN client f ON ms.Client_idClient = f.idClient
        INNER JOIN depot d ON ms.\"Depot_idDepot\" = d.\"idDepot\"
        INNER JOIN user_depot ud ON d.\"idDepot\" = ud.\"Depot_idDepot\"
        WHERE ud.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND (ms.reference_document LIKE :search OR f.noms LIKE :search)";
    }

    $query .= " ORDER BY ms.idManifeste_sortie DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getSortiesByUserAccess2($userId, $searchName = '', $startDate = '', $endDate = '', $limit = 2000)
{
    $query = "
        SELECT 
            ms.*,
            f.*,d.*
        FROM manifeste_sortie ms
        LEFT JOIN client f ON ms.Client_idClient = f.idClient
        INNER JOIN depot d ON ms.\"Depot_idDepot\" = d.\"idDepot\"
        INNER JOIN user_depot ud ON d.\"idDepot\" = ud.\"Depot_idDepot\"
        WHERE ud.\"idUser\" = :userId
    ";

    // Add conditions for search parameters
    if (!empty($searchName)) {
        $query .= " AND f.noms LIKE :searchName";
    }
    if (!empty($startDate)) {
        $query .= " AND ms.dateSortie >= :startDate";
    }
    if (!empty($endDate)) {
        $query .= " AND ms.dateSortie <= :endDate";
    }

    $query .= " ORDER BY ms.idManifeste_sortie DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    // Bind parameters for search conditions
    if (!empty($searchName)) {
        $searchTerm = '%' . $searchName . '%';
        $stmt->bindParam(':searchName', $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($startDate)) {
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    }
    if (!empty($endDate)) {
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEntreesByUserAccess2($userId, $searchName = '', $startDate = '', $endDate = '', $limit = 100)
{
    $query = "
        SELECT 
            me.*,
            f.*,d.*
        FROM manifeste_entree me
        LEFT JOIN fournisseur f ON me.Fournisseur_idFournisseur = f.idFournisseur
        INNER JOIN depot d ON me.\"Depot_idDepot\" = d.\"idDepot\"
        INNER JOIN user_depot ud ON d.\"idDepot\" = ud.\"Depot_idDepot\"
        WHERE ud.\"idUser\" = :userId
    ";

    // Add conditions for search parameters
    if (!empty($searchName)) {
        $query .= " AND f.nom LIKE :searchName";
    }
    if (!empty($startDate)) {
        $query .= " AND me.dateOperation >= :startDate";
    }
    if (!empty($endDate)) {
        $query .= " AND me.dateOperation <= :endDate";
    }

    $query .= " ORDER BY me.idManifeste_entree DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    // Bind parameters for search conditions
    if (!empty($searchName)) {
        $searchTerm = '%' . $searchName . '%';
        $stmt->bindParam(':searchName', $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($startDate)) {
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    }
    if (!empty($endDate)) {
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getDetailsSortieByManifest($manifestId)
{
    $query = "SELECT 
                ds.idDetail_sortie,
                ds.designation,
                ds.unite,
                ds.quantite
              FROM detail_sortie ds
              WHERE ds.Manifeste_sortie_idManifeste_sortie = :manifestId
              ORDER BY ds.idDetail_sortie DESC";
              
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':manifestId', $manifestId, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addSortieDepot($dateOperation, $observation, $transporteur, $referenceDocument, $userId, $depotId, $clientId)
{
    try {
        $query = "
            INSERT INTO manifeste_sortie (
                dateSortie,
                motif,
                transporteur,
                reference_document,
                \"idUser\",
                \"Depot_idDepot\",
                Client_idClient
            ) VALUES (
                :dateOperation,
                :observation,
                :transporteur,
                :referenceDocument,
                :userId,
                :depotId,
                :clientId
            )
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':transporteur', $transporteur, PDO::PARAM_STR);
        $stmt->bindParam(':referenceDocument', $referenceDocument, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        $stmt->bindParam(':clientId', $clientId, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (PDOException $e) {
        // Log the error or handle it as needed
        throw new Exception("Failed to add sortie depot: " . $e->getMessage());
    }
}

public function updateSortieDepot($idSortie, $dateOperation, $observation, $transporteur, $referenceDocument, $userId, $depotId, $clientId)
{
    try {
        $query = "
            UPDATE manifeste_sortie SET
                dateSortie = :dateOperation,
                motif = :observation,
                transporteur = :transporteur,
                reference_document = :referenceDocument,
                \"idUser\" = :userId,
                \"Depot_idDepot\" = :depotId,
                Client_idClient = :clientId
            WHERE idManifeste_sortie = :idSortie
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':idSortie', $idSortie, PDO::PARAM_INT);
        $stmt->bindParam(':dateOperation', $dateOperation, PDO::PARAM_STR);
        $stmt->bindParam(':observation', $observation, PDO::PARAM_STR);
        $stmt->bindParam(':transporteur', $transporteur, PDO::PARAM_STR);
        $stmt->bindParam(':referenceDocument', $referenceDocument, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':depotId', $depotId, PDO::PARAM_INT);
        $stmt->bindParam(':clientId', $clientId, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (PDOException $e) {
        // Log the error or handle it as needed
        error_log($e->getMessage());
        return false;
    }
}

public function addDetailToSortie($manifestId, $designation, $unite, $quantite)
{
    $query = "INSERT INTO detail_sortie (Manifeste_sortie_idManifeste_sortie, designation, unite, quantite) 
              VALUES (:manifestId, :designation, :unite, :quantite)";
    $stmt = $this->db->prepare($query);

    // Bind parameters
    $stmt->bindParam(':manifestId', $manifestId, PDO::PARAM_INT);
    $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
    $stmt->bindParam(':unite', $unite, PDO::PARAM_STR);
    $stmt->bindParam(':quantite', $quantite, PDO::PARAM_INT);

    return $stmt->execute();
}

public function deleteDetailSortie($detailId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Check if the detail sortie exists
        $query = "SELECT * FROM detail_sortie WHERE idDetail_sortie = :detailId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':detailId', $detailId, PDO::PARAM_INT);
        $stmt->execute();
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$detail) {
            throw new Exception("Detail sortie not found.");
        }

        // Delete the detail sortie
        $query = "DELETE FROM detail_sortie WHERE idDetail_sortie = :detailId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':detailId', $detailId, PDO::PARAM_INT);
        $stmt->execute();

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

public function deleteSortieDepot($sortieId, $userId)
{
    try {
        // Begin a transaction
        $this->db->beginTransaction();

        // Check if the sortie exists
        $query = "SELECT * FROM manifeste_sortie WHERE idManifeste_sortie = :sortieId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sortieId', $sortieId, PDO::PARAM_INT);
        $stmt->execute();
        $sortie = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sortie) {
            throw new Exception("Sortie not found.");
        }

        // Delete the sortie details
        $query = "DELETE FROM detail_sortie WHERE Manifeste_sortie_idManifeste_sortie = :sortieId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sortieId', $sortieId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete the sortie
        $query = "DELETE FROM manifeste_sortie WHERE idManifeste_sortie = :sortieId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sortieId', $sortieId, PDO::PARAM_INT);
        $stmt->execute();

        // Commit the transaction
        $this->db->commit();

        return true;
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $this->db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}

public function getSortieById($sortieId)
{
    try {
        $query = "
            SELECT ms.*, d.*, u.*, c.noms AS clientName
            FROM manifeste_sortie ms
            INNER JOIN depot d ON ms.\"Depot_idDepot\" = d.\"idDepot\"
            INNER JOIN t_users u ON ms.\"idUser\" = u.\"idUser\"
            LEFT JOIN client c ON ms.Client_idClient = c.idClient
            WHERE ms.idManifeste_sortie = :sortieId
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sortieId', $sortieId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

public function getEmailsByUserAccess($userId, $searchProvenance, $startDate, $endDate, $limit = 100)
{
    $query = "
        SELECT 
            cr.*
        FROM couriels_recu cr
        INNER JOIN service s ON cr.\"Service_idService\" = s.\"idService\"
        INNER JOIN structure str ON s.\"Structure_idStructure\"=str.\"idStructure\"
        INNER JOIN user_structure as us ON us.\"idStructure\"=str.\"idStructure\"
        WHERE us.\"idUser\" = :userId
    ";

    // Add conditions for search parameters
    if (!empty($searchProvenance)) {
        $query .= " AND cr.provenance LIKE :searchProvenance";
    }
    if (!empty($startDate)) {
        $query .= " AND cr.\"dateEnregistrement\" >= :startDate";
    }
    if (!empty($endDate)) {
        $query .= " AND cr.\"dateEnregistrement\" <= :endDate";
    }

    $query .= " ORDER BY cr.idcouriels_recu DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    // Bind parameters for search conditions
    if (!empty($searchProvenance)) {
        $searchTerm = '%' . $searchProvenance . '%';
        $stmt->bindParam(':searchProvenance', $searchTerm, PDO::PARAM_STR);
    }
    if (!empty($startDate)) {
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    }
    if (!empty($endDate)) {
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getEmailsByUserAccess2($userId,$limit = 100)
{
    $query = "
        SELECT 
            cr.*,a.*
        FROM couriels_recu cr
        INNER JOIN service s ON cr.\"Service_idService\" = s.\"idService\"
        INNER JOIN agent a ON cr.\"userConcerne\"=a.\"idAgent\"
        INNER JOIN structure str ON s.\"Structure_idStructure\"=str.\"idStructure\"
        INNER JOIN user_structure as us ON us.\"idStructure\"=str.\"idStructure\"
        WHERE us.\"idUser\" = :userId
    ";

   

    $query .= " ORDER BY cr.idcouriels_recu DESC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addEmail($provenance, $depositaire, $dateArrive, $serviceId, $userConcerne, $objet, $resume, $userId)
{
    try {
        $query = "
            INSERT INTO couriels_recu (
                \"dateArrive\", provenance, depositaire, objet, \"resumeCouriel\", \"dateEnregistrement\", \"idUser\", \"userConcerne\", \"Service_idService\"
            ) VALUES (
                :dateArrive, :provenance, :depositaire, :objet, :resumeCouriel, NOW(), :idUser, :userConcerne, :serviceId
            )
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':dateArrive', $dateArrive, PDO::PARAM_STR);
        $stmt->bindParam(':provenance', $provenance, PDO::PARAM_STR);
        $stmt->bindParam(':depositaire', $depositaire, PDO::PARAM_STR);
        $stmt->bindParam(':objet', $objet, PDO::PARAM_STR);
        $stmt->bindParam(':resumeCouriel', $resume, PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':userConcerne', $userConcerne, PDO::PARAM_INT);
        $stmt->bindParam(':serviceId', $serviceId, PDO::PARAM_INT);

        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error adding email: " . $e->getMessage());
        throw new Exception("Unable to add email.");
    }
}

public function deleteEmail($emailId)
{
    try {
        $query = "DELETE FROM couriels_recu WHERE idcouriels_recu = :emailId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':emailId', $emailId, PDO::PARAM_INT);
        $stmt->execute();

        // Check if any row was affected (deleted)
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error deleting email: " . $e->getMessage());
        return false;
    }
}

public function getCategoriesByUserAccess($userId, $search = '', $limit = 20)
{
    $query = "
        SELECT c.*
        FROM categories_doc c
        INNER JOIN structure s ON c.\"idStructure\" = s.\"idStructure\"
        INNER JOIN user_structure us ON s.\"idStructure\" = us.\"idStructure\"
        WHERE us.\"idUser\" = :userId AND c.\"idUser\"= :userId
    ";

    if (!empty($search)) {
        $query .= " AND c.nom LIKE :search";
    }

    $query .= " ORDER BY c.nom ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addCategory($categoryData) {
    try {
        $query = "INSERT INTO categories_doc (nom, description, \"idStructure\", date_creation,\"idUser\") 
                  VALUES (:nom, :description, :idStructure, :date_creation, :idUser)";
        
        $stmt = $this->db->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':nom', $categoryData['nom'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $categoryData['description'], PDO::PARAM_STR);
        $stmt->bindParam(':idStructure', $categoryData['idStructure'], PDO::PARAM_INT);
        $stmt->bindParam(':date_creation', $categoryData['date_creation'], PDO::PARAM_STR);
        $stmt->bindParam(':idUser', $categoryData['idUser'], PDO::PARAM_STR);
        
        // Exécution de la requête
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de la catégorie: " . $e->getMessage());
        return false;
    }
}

public function updateCategory($categoryData) {
    try {
        $query = "UPDATE categories_doc 
                  SET nom = :nom, 
                      description = :description, 
                      \"idStructure\" = :idStructure 
                  WHERE id_categorie = :id_categorie";
        
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute([
            ':id_categorie' => $categoryData['id_categorie'],
            ':nom' => $categoryData['nom'],
            ':description' => $categoryData['description'],
            ':idStructure' => $categoryData['idStructure']
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de la catégorie: " . $e->getMessage());
        return false;
    }
}

public function deleteCategory($categoryId) {
    try {
        $query = "DELETE FROM categories_doc WHERE id_categorie = :categoryId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de la catégorie: " . $e->getMessage());
        return false;
    }
}

public function getPrivateDocumentsByUserAccess($userId, $search = '', $limit = 120)
{
    $query = "
        SELECT pd.*,u.*,c.*
        FROM documents_prive pd
        INNER JOIN t_users u ON pd.\"idUser\"=u.\"idUser\"
        INNER JOIN categories_doc c ON pd.id_categorie = c.id_categorie
        WHERE pd.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND pd.titre LIKE :search";
    }

    $query .= " ORDER BY pd.titre ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPrivateDocumentsByUserAccess2($userId, $search = '', $limit = 120)
{
    $query = "
        SELECT pd.*,tu.*,c.*
        FROM documents_prive pd
        INNER JOIN user_document u ON pd.id_document=u.id_document
        INNER JOIN t_users tu ON pd.\"idUser\"=tu.\"idUser\"
        INNER JOIN categories_doc c ON pd.id_categorie = c.id_categorie
        WHERE u.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND pd.titre LIKE :search";
    }

    $query .= " ORDER BY pd.titre ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPublicDocumentsByUserAccess($userId, $search = '', $limit = 120)
{
    $query = "
        SELECT pd.*,u.*,c.*
        FROM documents_public pd
        INNER JOIN t_users u ON pd.\"idUser\"=u.\"idUser\"
        INNER JOIN categories_doc c ON pd.id_categorie = c.id_categorie
        WHERE pd.\"idUser\" = :userId
    ";

    if (!empty($search)) {
        $query .= " AND pd.titre LIKE :search";
    }

    $query .= " ORDER BY pd.titre ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPublicDocumentsByUserAccess2($userId, $search = '', $limit = 120)
{
    $query = "
        SELECT pd.*,u.*,c.*
        FROM documents_public pd
        INNER JOIN t_users u ON pd.\"idUser\"=u.\"idUser\"
        INNER JOIN categories_doc c ON pd.id_categorie = c.id_categorie
    ";

    if (!empty($search)) {
        $query .= " AND pd.titre LIKE :search";
    }

    $query .= " ORDER BY pd.titre ASC LIMIT :limit";

    $stmt = $this->db->prepare($query);

    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addDocument($title, $description, $filePath, $userId, $categoryId)
{
    try {
        $query = "
            INSERT INTO documents_prive (titre, description, chemin_fichier, date_ajout, \"idUser\", id_categorie)
            VALUES (:title, :description, :filePath, NOW(), :userId, :categoryId)
        ";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':filePath', $filePath, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error adding document: " . $e->getMessage());
        return false;
    }
}

public function addDocument_public($title, $description, $filePath, $userId, $categoryId)
{
    try {
        $query = "
            INSERT INTO documents_public (titre, description, chemin_fichier, date_ajout, \"idUser\", id_categorie)
            VALUES (:title, :description, :filePath, NOW(), :userId, :categoryId)
        ";

        $stmt = $this->db->prepare($query);

        // Bind parameters
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':filePath', $filePath, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error adding document: " . $e->getMessage());
        return false;
    }
}

public function getDocumentById($documentId)
{
    $query = "
        SELECT pd.*, c.*
        FROM documents_prive pd
        LEFT JOIN categories_doc c ON pd.id_categorie = c.id_categorie
        WHERE pd.id_document = :documentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getDocumentById_public($documentId)
{
    $query = "
        SELECT pd.*, c.*
        FROM documents_public pd
        LEFT JOIN categories_doc c ON pd.id_categorie = c.id_categorie
        WHERE pd.id_document = :documentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function deleteDocument($documentId)
{
    $query = "
        DELETE FROM documents_prive WHERE id_document = :documentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
    return $stmt->execute();
}

public function deleteDocument_public($documentId)
{
    $query = "
        DELETE FROM documents_public WHERE id_document = :documentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
    return $stmt->execute();
}

public function getUsersByDocumentId($documentId)
{
    $query = "
        SELECT u.*
        FROM t_users u
        INNER JOIN user_document ud ON u.\"idUser\" = ud.\"idUser\"
        WHERE ud.id_document = :documentId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function removeUserFromDocument($userId, $documentId)
{
    try {
        $query = "DELETE FROM user_document WHERE \"idUser\" = :userId AND id_document = :documentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error removing user from document: " . $e->getMessage());
        return false;
    }
}

public function addUserToDocument($userId, $documentId)
{
    try {
        $query = "INSERT INTO user_document (\"idUser\", id_document) VALUES (:userId, :documentId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':documentId', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error adding user to document: " . $e->getMessage());
        return false;
    }
}

// Récupérer l'id d'un document privé par son nom de fichier et son propriétaire
public function getPrivateDocumentIdByFilename($fileName, $userId)
{
    $query = "SELECT id_document FROM documents_prive WHERE chemin_fichier = :file AND \"idUser\" = :user LIMIT 1";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':file', $fileName, PDO::PARAM_STR);
    $stmt->bindParam(':user', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id_document'] : null;
}

public function getDocumentsByCategory($categoryId, $userId, $limit = 100)
{
    $query = "
        SELECT d.*, c.*
        FROM (
            SELECT id_document, titre, description, chemin_fichier, date_ajout, \"idUser\", id_categorie
            FROM documents_prive
            WHERE id_categorie = :categoryId
            AND id_document IN (
                SELECT id_document
                FROM user_document
                WHERE \"idUser\" = :userId
            )
            UNION ALL
            SELECT id_document, titre, description, chemin_fichier, date_ajout, \"idUser\", id_categorie
            FROM documents_public
            WHERE id_categorie = :categoryId
        ) AS d
        INNER JOIN categories_doc c ON d.id_categorie = c.id_categorie
        ORDER BY d.titre ASC
        LIMIT :limit
    ";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getDocumentCategories($searchTerm = '', $limit = 100)
{
    $query = "SELECT * FROM categories_doc WHERE nom LIKE :searchTerm ORDER BY nom ASC LIMIT :limit";
    $stmt = $this->db->prepare($query);
    $searchTerm = '%' . $searchTerm . '%';
    $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getJournalEntries($userId, $structureId, $startDate, $endDate) {
    $query = "
        SELECT dateOperation, compte, libelle_compte, montant_debit, montant_credit, libele, numPiece
        FROM journal_automatique
        WHERE \"Structure_idStructure\" = :structureId
        AND \"idUser\" = :userId
        AND dateOperation BETWEEN :startDate AND :endDate
    ";

    $stmt = $this->db->prepare($query);
    $stmt->execute([
        ':structureId' => $structureId,
        ':userId' => $userId,
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getInitialBalances($userId, $structureId, $startDate) {
    $query = "
        SELECT SUM(montant_debit) AS initial_debit, SUM(montant_credit) AS initial_credit
        FROM journal_automatique
        WHERE \"Structure_idStructure\" = :structureId
        AND \"idUser\" = :userId
        AND dateOperation < :startDate
    ";

    $stmt = $this->db->prepare($query);
    $stmt->execute([
        ':structureId' => $structureId,
        ':userId' => $userId,
        ':startDate' => $startDate
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function getClientReceivables($structureId, $startDate, $endDate) {
    try {
        $query = "
            SELECT c.noms, f.numeroFacture, f.montant AS total_amount, f.dateFacture AS 
            due_date, f.statut AS status, (f.montant - COALESCE(SUM(p.montant), 0)) AS 
            outstanding_amount FROM facture_client f 
            JOIN client c ON f.Client_idClient = c.idClient 
            LEFT JOIN paiement_client p ON f.idFacture_client = p.Facture_client_idFacture_client 
            WHERE c.\"Structure_idStructure\" = :structureId 
            AND f.dateFacture 
            BETWEEN :startDate AND :endDate
            GROUP BY f.idFacture_client, c.noms, f.numeroFacture, f.montant, f.dateFacture, f.statut
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            echo "No receivables found for the given criteria.";
        }

        return $results;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

public function getSupplierDebts($structureId, $startDate, $endDate) {
    try {
        $query = "
            SELECT f.nom AS supplier_name, ff.numeroFacture AS invoice_number, 
                   ff.montant AS total_amount, ff.dateFacture AS due_date, 
                   ff.statut AS status, 
                   (ff.montant - COALESCE(SUM(pf.montant), 0)) AS outstanding_amount
            FROM facture_fournisseur ff
            JOIN fournisseur f ON ff.Fournisseur_idFournisseur = f.idFournisseur
            LEFT JOIN paiement_fournisseur pf ON ff.idFacture_fournisseur = pf.Facture_fournisseur_idFacture_fournisseur
            WHERE f.\"Structure_idStructure\" = :structureId
            AND ff.dateFacture BETWEEN :startDate AND :endDate
            GROUP BY ff.idFacture_fournisseur, f.nom, ff.numeroFacture, ff.montant, ff.dateFacture, ff.statut
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error retrieving supplier debts: " . $e->getMessage());
        return [];
    }
}

public function getPeriodicRevenues($structureId, $startDate, $endDate, $budgetId = null) {
    $query = "
        SELECT 
            r.motif, 
            r.montantR, 
            r.depositaire, 
            r.dateOperation, 
            r.\"dateEnregistrement\",
            g.*
        FROM 
            recette_structure r
        JOIN 
            ligne_recette_structure l ON r.ligne_recette_structure_idligne_recette_structure = l.idligne_recette_structure
        JOIN 
            groupe_recette_structure g ON l.Groupe_recette_structure_idGroupe_recette_structure = g.\"idGroupe_recette_structure\"
        JOIN 
            budget_recette_structure b ON g.\"Budget_recette_structure_idBudget_recette_structure\" = b.idBudget_recette_structure
        WHERE 
            b.\"Structure_idStructure\" = :structureId
            AND r.dateOperation BETWEEN :startDate AND :endDate
    ";

    // Add budget filter if provided
    if ($budgetId !== null && $budgetId !== '') {
        $query .= " AND b.idBudget_recette_structure = :budgetId";
    }

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
    $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);

    // Bind budget ID if provided
    if ($budgetId !== null && $budgetId !== '') {
        $stmt->bindParam(':budgetId', $budgetId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPeriodicExpenses($structureId, $startDate, $endDate, $budgetId = null) {
    $query = "
        SELECT 
            d.motifD, 
            d.montantD, 
            d.beneficiaire, 
            d.dateoperation, 
            d.\"dateEnregistrement\",
            g.*
        FROM 
            depense_structure d
        JOIN 
            ligne_depense_structure l ON d.ligne_depense_structure_idligne_depense_structure = l.idligne_depense_structure
        JOIN 
            groupe_depense_structure g ON l.Groupe_depense_structure_idGroupe_depense_structure = g.\"idGroupe_depense_structure\"
        JOIN 
            budget_depense_structure b ON g.\"Budget_depense_structure_idBudget_depense_structure\" = b.idBudget_depense_structure
        WHERE 
            b.\"Structure_idStructure\" = :structureId
            AND d.dateoperation BETWEEN :startDate AND :endDate
    ";

    // Add budget filter if provided
    if ($budgetId !== null && $budgetId !== '') {
        $query .= " AND b.idBudget_depense_structure = :budgetId";
    }

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
    $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);

    // Bind budget ID if provided
    if ($budgetId !== null && $budgetId !== '') {
        $stmt->bindParam(':budgetId', $budgetId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getAccountHistory($structureId, $accountId)
{
    $query = "
        SELECT 
            c.*,t.*
        FROM 
            journal_automatique t
        INNER JOIN 
            compte c ON t.compte = c.numeroCompte
        INNER JOIN structure s ON t.\"Structure_idStructure\"=s.\"idStructure\"
        WHERE 
            t.\"Structure_idStructure\" = :structureId
            AND c.idCompte = :accountId
        ORDER BY 
            t.dateOperation DESC
    ";

    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
    $stmt->bindParam(':accountId', $accountId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function insertEcriture($montant, $dateEcriture, $numeroPiece, $description, $journalId, $userId) {
    $stmt = $this->db->prepare("INSERT INTO ecriture (montant, \"dateEcriture\", \"numeroPiece\", description, \"Journaux_idJournaux\", \"dateEnregistrement\", \"idUser\") VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$montant, $dateEcriture, $numeroPiece, $description, $journalId, $userId]);
    return $this->db->lastInsertId();
}

public function insertEcritureDetail($ecritureId, $compteId, $montant, $typeCompte) {
    $stmt = $this->db->prepare("INSERT INTO ecriture_detail (\"idEcriture\", compteId, montant, typeCompte) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ecritureId, $compteId, $montant, $typeCompte]);
}

public function insertJournalAutomatique($dateOperation, $compte, $libelleCompte, $montantDebit, $montantCredit, $libele, $numPiece, $structureId, $userId) {
    $stmt = $this->db->prepare("INSERT INTO journal_automatique (dateOperation, compte, libelle_compte, montant_debit, montant_credit, libele, numPiece, \"Structure_idStructure\", \"idUser\") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$dateOperation, $compte, $libelleCompte, $montantDebit, $montantCredit, $libele, $numPiece, $structureId, $userId]);
}


public function logUserActivity($userId, $actionType, $description, $ipAddress, $userAgent)
    {
        try {
            // Prepare the SQL statement
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_log (user_id, action_type, description, ip_address, user_agent)
                VALUES (:user_id, :action_type, :description, :ip_address, :user_agent)
            ");

            // Bind parameters
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action_type', $actionType, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);

            // Execute the statement
            $stmt->execute();
        } catch (PDOException $e) {
            die("Erreur lors de l'enregistrement de l'activité : " . $e->getMessage());
        }
    }

    public function getAcademicYears()
{
    $query = "SELECT * FROM annee_acad ORDER BY designation DESC";
    $stmt = $this->db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Dans la classe Structure
public function getTeacherUsers() {
    $query = "SELECT u.* 
              FROM t_users u
              JOIN agent a ON u.\"idAgent\" = a.\"idAgent\"
              WHERE a.type_agent = 'Enseignant'
              ORDER BY u.\"nomUser\" ASC";
    $stmt = $this->db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    
}
