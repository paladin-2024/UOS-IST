<?php
/**
 * Classe FlexPay
 * Gère l'intégration avec la passerelle de paiement FlexPay (https://flexpay.cd)
 * Supporte les paiements Mobile Money et par carte bancaire
 */
class FlexPay
{
    private $db;
    private $config;

    public function __construct()
    {
        $this->db = Connexion::getInstance()->getPDO();
        
        // Charger la configuration depuis la base de données (configuration_universite)
        $stmt = $this->db->query("SELECT flexpay_merchant, flexpay_token, flexpay_callback_url, flexpay_timeout,
            flexpay_endpoint_mobile_money, flexpay_endpoint_card_payment, flexpay_endpoint_check_transaction, flexpay_actif
            FROM configuration_universite LIMIT 1");
        $dbConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dbConfig || empty($dbConfig['flexpay_merchant'])) {
            throw new Exception("Configuration FlexPay non trouvée. Veuillez la configurer dans Configuration > Établissement.");
        }
        
        $this->config = [
            'merchant'     => $dbConfig['flexpay_merchant'],
            'token'        => $dbConfig['flexpay_token'],
            'callback_url' => $dbConfig['flexpay_callback_url'],
            'timeout'      => intval($dbConfig['flexpay_timeout'] ?? 30),
            'endpoints'    => [
                'mobile_money'      => $dbConfig['flexpay_endpoint_mobile_money'] ?: 'https://backend.flexpay.cd/api/rest/v1/paymentService',
                'card_payment'      => $dbConfig['flexpay_endpoint_card_payment'] ?: 'https://backend.flexpay.cd/api/rest/v1.1/pay',
                'check_transaction' => $dbConfig['flexpay_endpoint_check_transaction'] ?: 'https://backend.flexpay.cd/api/rest/v1/check/',
            ],
        ];
    }

    // ==========================================
    // MÉTHODES D'APPEL API FLEXPAY
    // ==========================================

    /**
     * Initie un paiement Mobile Money via l'API FlexPay
     * @param string $telephone - Numéro de téléphone du payeur
     * @param float $montant - Montant à payer
     * @param string $devise - Devise (CDF ou USD)
     * @param string $reference - Référence unique du paiement
     * @param string $description - Description du paiement
     * @return array - Réponse de l'API FlexPay
     */
    public function initierPaiementMobile($telephone, $montant, $devise, $reference, $description)
    {
        try {
            $endpoint = $this->config['endpoints']['mobile_money'];
            $payload = [
                'merchant' => $this->config['merchant'],
                'type' => '1', // 1 = Mobile Money
                'phone' => $telephone,
                'reference' => $reference,
                'amount' => strval($montant),
                'currency' => $devise,
                'callbackUrl' => $this->config['callback_url'],
                'description' => $description,
            ];

            return $this->envoyerRequete('POST', $endpoint, $payload);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur paiement mobile: " . $e->getMessage());
            return [
                'code' => '-1',
                'message' => 'Erreur lors de l\'initiation du paiement: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Initie un paiement par carte bancaire via l'API FlexPay
     * @param float $montant - Montant à payer
     * @param string $devise - Devise (CDF ou USD)
     * @param string $reference - Référence unique du paiement
     * @param string $description - Description du paiement
     * @param string $callbackUrl - URL de callback après paiement carte
     * @return array - Réponse de l'API FlexPay
     */
    public function initierPaiementCarte($montant, $devise, $reference, $description, $callbackUrl = '')
    {
        try {
            $endpoint = $this->config['endpoints']['card_payment'];
            $payload = [
                'merchant' => $this->config['merchant'],
                'reference' => $reference,
                'amount' => strval($montant),
                'currency' => $devise,
                'callbackUrl' => $callbackUrl ?: $this->config['callback_url'],
                'description' => $description,
            ];

            return $this->envoyerRequete('POST', $endpoint, $payload);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur paiement carte: " . $e->getMessage());
            return [
                'code' => '-1',
                'message' => 'Erreur lors de l\'initiation du paiement carte: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Vérifie le statut d'une transaction via l'API FlexPay
     * @param string $orderNumber - Numéro de commande FlexPay
     * @return array - Réponse de l'API avec le statut de la transaction
     */
    public function verifierTransaction($orderNumber)
    {
        try {
            $endpoint = $this->config['endpoints']['check_transaction'] . $orderNumber;
            return $this->envoyerRequete('GET', $endpoint);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur vérification transaction: " . $e->getMessage());
            return [
                'code' => '-1',
                'message' => 'Erreur lors de la vérification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Détermine si le paiement a échoué en analysant le message de réponse
     * FlexPay peut retourner code "0" même en cas d'échec (ex: mauvais mot de passe, solde insuffisant)
     * @param string $message - Message de réponse de l'API
     * @return bool - True si le paiement a échoué
     */
    public function estPaiementEchoue($message)
    {
        if (empty($message)) {
            return false;
        }

        // Normaliser les apostrophes typographiques en apostrophes ASCII
        $messageNormalise = str_replace(["\u{2019}", "\u{2018}", "\u{0060}"], "'", $message);
        $messageLower = mb_strtolower($messageNormalise, 'UTF-8');
        
        $indicateursEchec = [
            'n\'a pas réussi',
            'n\'a pas reussi',
            'pas réussi',
            'pas reussi',
            'payment failed',
            'echec',
            'échoué',
            'failed',
            'annulé',
            'annule',
            'canceled',
            'cancelled',
            'mot de passe incorrect',
            'wrong password',
            'solde insuffisant',
            'insufficient balance',
            'compte bloqué',
            'account blocked',
            'transaction timeout',
            'délai dépassé',
            'expiré',
            'expired',
            'invalid',
            'erreur',
            'refused',
            'refusé',
            'rejected',
        ];

        foreach ($indicateursEchec as $indicateur) {
            if (mb_strpos($messageLower, $indicateur, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si le paiement est en attente de confirmation (en attente que l'utilisateur saisisse le PIN)
     * @param string $message - Message de réponse de l'API
     * @return bool - True si le paiement est en attente
     */
    public function estPaiementEnAttente($message)
    {
        if (empty($message)) {
            return false;
        }

        // Normaliser les apostrophes typographiques en apostrophes ASCII
        $messageNormalise = str_replace(["\u{2019}", "\u{2018}", "\u{0060}"], "'", $message);
        $messageLower = mb_strtolower($messageNormalise, 'UTF-8');
        
        // D'abord vérifier si c'est un échec - si oui, ce n'est PAS en attente
        // (ex: "Le paiement n'a pas réussi" ne doit pas matcher "en attente")
        if ($this->estPaiementEchoue($message)) {
            return false;
        }
        
        // Vérifier aussi si c'est un succès - si oui, ce n'est PAS en attente
        if ($this->estPaiementReussi($message)) {
            return false;
        }
        
        $indicateursAttente = [
            'en attente',
            'pending',
            'waiting',
            'en cours',
            'process',
            'initiated',
            'envoyée avec succès',
            'envoyee avec succes',
            'transaction envoyée',
            'transaction envoyee',
        ];

        foreach ($indicateursAttente as $indicateur) {
            if (mb_strpos($messageLower, $indicateur, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si le paiement est réussi (confirmé) en analysant le message
     * @param string $message - Message de réponse de l'API
     * @return bool - True si le paiement est confirmé réussi
     */
    public function estPaiementReussi($message)
    {
        if (empty($message)) {
            return false;
        }

        $messageNormalise = str_replace(["\u{2019}", "\u{2018}", "\u{0060}"], "'", $message);
        $messageLower = mb_strtolower($messageNormalise, 'UTF-8');
        
        $indicateursSucces = [
            'traité avec succès',
            'traite avec succes',
            'payment successful',
            'successfully',
            'completed',
            'approved',
        ];

        foreach ($indicateursSucces as $indicateur) {
            if (mb_strpos($messageLower, $indicateur, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne une description détaillée de l'échec pour notification utilisateur
     * @param string $message - Message de réponse de l'API
     * @return string - Message traduit et plus explicite
     */
    public function getMessageErreurPaiement($message)
    {
        if (empty($message)) {
            return 'Erreur inconnue lors du paiement.';
        }

        $messageLower = mb_strtolower($message, 'UTF-8');

        if (mb_strpos($messageLower, 'mot de passe', 0, 'UTF-8') !== false || 
            mb_strpos($messageLower, 'password', 0, 'UTF-8') !== false) {
            return 'Le paiement a été refusé : mot de passe incorrect. Veuillez réessayer avec le bon code.';
        }

        if (mb_strpos($messageLower, 'solde insuffisant', 0, 'UTF-8') !== false || 
            mb_strpos($messageLower, 'insufficient balance', 0, 'UTF-8') !== false) {
            return 'Le paiement a été refusé : solde insuffisant. Veuillez recharger votre compte Mobile Money.';
        }

        if (mb_strpos($messageLower, 'annulé', 0, 'UTF-8') !== false || 
            mb_strpos($messageLower, 'cancel', 0, 'UTF-8') !== false) {
            return 'Le paiement a été annulé par l\'utilisateur.';
        }

        if (mb_strpos($messageLower, 'expir', 0, 'UTF-8') !== false || 
            mb_strpos($messageLower, 'timeout', 0, 'UTF-8') !== false) {
            return 'Le paiement a expiré. Veuillez réessayer.';
        }

        if (mb_strpos($messageLower, 'bloqué', 0, 'UTF-8') !== false || 
            mb_strpos($messageLower, 'blocked', 0, 'UTF-8') !== false) {
            return 'Votre compte est temporairement bloqué. Veuillez contacter votre opérateur.';
        }

        return $message;
    }

    // ==========================================
    // MÉTHODES DE GESTION EN BASE DE DONNÉES
    // ==========================================

    /**
     * Enregistre une transaction FlexPay en base de données
     * @param array $data - Données de la transaction
     * @return int|false - ID de la transaction insérée ou false en cas d'erreur
     */
    public function enregistrerTransaction($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions_flexpay (
                    matricule_etudiant,
                    affectation_frais_id,
                    nature,
                    devoir_id,
                    groupe_id,
                    order_number,
                    reference,
                    montant,
                    devise,
                    telephone,
                    type_paiement,
                    statut,
                    reponse_api,
                    date_creation
                ) VALUES (
                    :matricule_etudiant,
                    :affectation_frais_id,
                    :nature,
                    :devoir_id,
                    :groupe_id,
                    :order_number,
                    :reference,
                    :montant,
                    :devise,
                    :telephone,
                    :type_paiement,
                    :statut,
                    :reponse_api,
                    NOW()
                )
            ");

            $stmt->bindParam(':matricule_etudiant', $data['matricule_etudiant']);
            $affectationId = $data['affectation_frais_id'] ?? null;
            $stmt->bindParam(':affectation_frais_id', $affectationId, PDO::PARAM_INT);
            $nature = $data['nature'] ?? 'frais';
            $stmt->bindParam(':nature', $nature);
            $devoirId = $data['devoir_id'] ?? null;
            $stmt->bindParam(':devoir_id', $devoirId, PDO::PARAM_INT);
            $groupeId = $data['groupe_id'] ?? null;
            $stmt->bindParam(':groupe_id', $groupeId, PDO::PARAM_INT);
            $stmt->bindParam(':order_number', $data['order_number']);
            $stmt->bindParam(':reference', $data['reference']);
            $stmt->bindParam(':montant', $data['montant']);
            $stmt->bindParam(':devise', $data['devise']);
            $stmt->bindParam(':telephone', $data['telephone']);
            $stmt->bindParam(':type_paiement', $data['type_paiement']);
            $statut = $data['statut'] ?? 'en_attente';
            $stmt->bindParam(':statut', $statut);
            $reponseApi = isset($data['reponse_api']) ? json_encode($data['reponse_api'], JSON_UNESCAPED_UNICODE) : null;
            $stmt->bindParam(':reponse_api', $reponseApi);

            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("FlexPay - Erreur enregistrement transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour le statut d'une transaction FlexPay
     * @param string $orderNumber - Numéro de commande
     * @param string $statut - Nouveau statut (en_attente, reussi, echoue, annule)
     * @param array|null $reponseApi - Réponse API FlexPay (optionnel)
     * @return bool - Succès ou échec
     */
    public function mettreAJourStatut($orderNumber, $statut, $reponseApi = null)
    {
        try {
            $query = "UPDATE transactions_flexpay 
                      SET statut = :statut,
                          date_mise_a_jour = NOW()";

            if ($reponseApi !== null) {
                $query .= ", reponse_api = :reponse_api,
                            code_reponse = :code_reponse,
                            message_reponse = :message_reponse";
            }

            $query .= " WHERE order_number = :order_number";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':order_number', $orderNumber);

            if ($reponseApi !== null) {
                $reponseJson = json_encode($reponseApi, JSON_UNESCAPED_UNICODE);
                $stmt->bindParam(':reponse_api', $reponseJson);
                $codeReponse = $reponseApi['code'] ?? null;
                $stmt->bindParam(':code_reponse', $codeReponse);
                $messageReponse = $reponseApi['message'] ?? null;
                $stmt->bindParam(':message_reponse', $messageReponse);
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("FlexPay - Erreur mise à jour statut: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère une transaction par son numéro de commande
     * @param string $orderNumber - Numéro de commande FlexPay
     * @return array|false - Données de la transaction ou false
     */
    public function getTransactionByOrderNumber($orderNumber)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM transactions_flexpay 
                WHERE order_number = :order_number
            ");
            $stmt->bindParam(':order_number', $orderNumber);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur récupération transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les transactions d'un étudiant par matricule
     * @param string $matricule - Matricule de l'étudiant
     * @return array - Liste des transactions
     */
    public function getTransactionsByMatricule($matricule)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT tf.*, af.montant_specifique, f.designation as frais_designation
                FROM transactions_flexpay tf
                LEFT JOIN affectation_frais af ON tf.affectation_frais_id = af.id
                LEFT JOIN frais f ON af.frais_id = f.id
                WHERE tf.matricule_etudiant = :matricule
                ORDER BY tf.date_creation DESC
            ");
            $stmt->bindParam(':matricule', $matricule);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur récupération transactions par matricule: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère une transaction par sa référence
     * @param string $reference - Référence du paiement
     * @return array|false - Données de la transaction ou false
     */
    public function getTransactionByReference($reference)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM transactions_flexpay 
                WHERE reference = :reference
            ");
            $stmt->bindParam(':reference', $reference);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("FlexPay - Erreur récupération transaction par référence: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // ==========================================

    /**
     * Envoie une requête HTTP vers l'API FlexPay via cURL
     * @param string $methode - Méthode HTTP (GET ou POST)
     * @param string $url - URL de l'endpoint
     * @param array|null $payload - Données à envoyer (pour POST)
     * @return array - Réponse décodée de l'API
     */
    private function envoyerRequete($methode, $url, $payload = null)
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['token'],
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($methode === 'POST' && $payload !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);
        curl_close($ch);

        if ($erreur) {
            error_log("FlexPay - Erreur cURL: " . $erreur);
            throw new Exception("Erreur de communication avec FlexPay: " . $erreur);
        }

        $resultat = json_decode($response, true);
        if ($resultat === null) {
            error_log("FlexPay - Réponse API invalide: " . $response);
            throw new Exception("Réponse invalide de FlexPay");
        }

        error_log("FlexPay - Réponse API [{$methode} {$url}] HTTP {$httpCode}: " . $response);

        return $resultat;
    }
}
