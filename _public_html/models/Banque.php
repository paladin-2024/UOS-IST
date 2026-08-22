<?php
class Banque
{
    private $db;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
    }

    // Add a new bank
    public function addBanque($designation, $numeroCompte, $solde, $compteId)
    {
        $query = "INSERT INTO banque (designation, numeroCompte, solde, dateEnregistrement, Compte_idCompte) 
                  VALUES (:designation, :numeroCompte, :solde, NOW(), :compteId)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':numeroCompte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Add a user to a bank
    public function addUserToBanque($userId, $banqueId)
    {
        $query = "INSERT INTO user_banque (idUser, Banque_idBanque) VALUES (:userId, :banqueId)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':banqueId', $banqueId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Remove a user from a bank
    public function removeUserFromBanque($userBanqueId)
    {
        $query = "DELETE FROM user_banque WHERE iduser_banque = :userBanqueId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':userBanqueId', $userBanqueId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Get users by bank
    public function getUsersByBanque($banqueId)
    {
        $query = "SELECT u.*, ub.* FROM user_banque ub INNER JOIN t_users u ON ub.idUser = u.idUser WHERE ub.Banque_idBanque = :banqueId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':banqueId', $banqueId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     // Get banks by structure
     // Get banks by structure through accounts
    public function getBanksByStructure($structureId)
    {
        $query = "
            SELECT b.* 
            FROM banque b
            INNER JOIN compte c ON b.Compte_idCompte = c.idCompte
            WHERE c.Structure_idStructure = :structureId
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':structureId', $structureId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateBanque($idBanque, $designation, $numeroCompte, $solde, $compteId)
    {
        $query = "UPDATE banque SET 
                    designation = :designation,
                    numeroCompte = :numeroCompte,
                    solde = :solde,
                    Compte_idCompte = :compteId
                WHERE idBanque = :idBanque";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':idBanque', $idBanque, PDO::PARAM_INT);
        $stmt->bindParam(':designation', $designation, PDO::PARAM_STR);
        $stmt->bindParam(':numeroCompte', $numeroCompte, PDO::PARAM_STR);
        $stmt->bindParam(':solde', $solde, PDO::PARAM_STR);
        $stmt->bindParam(':compteId', $compteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function deleteBanque($idBanque) {
        try {
            // Start a transaction
            $this->db->beginTransaction();
    
            // Delete related records in user_banque
            $query = "DELETE FROM user_banque WHERE Banque_idBanque = :idBanque";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idBanque', $idBanque, PDO::PARAM_INT);
            $stmt->execute();
    
            // Delete the bank
            $query = "DELETE FROM banque WHERE idBanque = :idBanque";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':idBanque', $idBanque, PDO::PARAM_INT);
            $stmt->execute();
    
            // Commit the transaction
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            // Rollback the transaction if something failed
            $this->db->rollBack();
            // Log the error or handle it as needed
            error_log($e->getMessage());
            return false;
        }
    }

    public function getBanksByUserAccess($userId)
{
    $query = "
        SELECT b.* 
        FROM banque b
        INNER JOIN user_banque ub ON b.idBanque = ub.Banque_idBanque
        WHERE ub.idUser = :userId
    ";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateBankBalance($bankId, $amount)
{
    $query = "UPDATE banque SET solde = solde + :amount WHERE idBanque = :bankId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
    return $stmt->execute();
}
public function getBanksById($bankId)
{
    $query = "SELECT c.numeroCompte as numeroCompte,c.intituleCompte as designation FROM banque as b INNER JOIN compte as c ON b.Compte_idCompte=c.idCompte WHERE idBanque = :bankId";
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':bankId', $bankId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}