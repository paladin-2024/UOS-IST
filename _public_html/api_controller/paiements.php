<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'connexion.php';
require_once 'auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify authentication
$auth = new Auth();
$studentId = $auth->authenticate();

if (!$studentId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $conn = Connexion::getInstance()->getPDO();
    
    // Get student info
    $stmt = $conn->prepare('SELECT e.*, p."designationPromotion", p.cycle,
                           o."designationOrientation", s."designationSection",
                           a.designation as annee_academique
                           FROM etudiant e
                           JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                           JOIN orientation o ON p.orientation_idorientation = o.idorientation
                           JOIN section s ON o.section_idsection = s.idsection
                           JOIN annee_acad a ON e.annee_acad_idannee_acad = a.idannee_acad
                           WHERE e.idetudiant = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    // Get academic years for student
    $stmt = $conn->prepare("SELECT DISTINCT a.idannee_acad, a.designation 
                           FROM inscription i
                           JOIN annee_acad a ON i.annee_acad_idannee_acad = a.idannee_acad
                           WHERE i.etudiant_idetudiant = ?
                           ORDER BY a.idannee_acad DESC");
    $stmt->execute([$studentId]);
    $academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $payments = [];
    
    // Get payments for each academic year
    foreach ($academicYears as $year) {
        // Get fee structure for this year
        $stmt = $conn->prepare("SELECT f.idfrais, f.designation, f.montant, f.devise, f.description,
                               f.est_obligatoire, f.date_echeance,
                               (SELECT SUM(p.montant) FROM paiement p 
                                WHERE p.frais_idfrais = f.idfrais 
                                AND p.etudiant_idetudiant = ?) as montant_paye
                               FROM frais f
                               JOIN frais_promotion fp ON f.idfrais = fp.frais_idfrais
                               JOIN inscription i ON fp.promotion_idpromotion = i.promotion_idpromotion
                               WHERE i.etudiant_idetudiant = ? 
                               AND i.annee_acad_idannee_acad = ?");
        $stmt->execute([$studentId, $studentId, $year['idannee_acad']]);
        $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment transactions
        $stmt = $conn->prepare('SELECT p.idpaiement, p.date_paiement, p.montant, p.devise,
                               p.reference, p.mode_paiement, p."estComplet",
                               f.designation as frais_designation
                               FROM paiement p
                               JOIN frais f ON p.frais_idfrais = f.idfrais
                               WHERE p.etudiant_idetudiant = ? 
                               AND p.annee_acad_idannee_acad = ?
                               ORDER BY p.date_paiement DESC');
        $stmt->execute([$studentId, $year['idannee_acad']]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totalDue = 0;
        $totalPaid = 0;
        $totalRemaining = 0;
        
        foreach ($fees as $fee) {
            $totalDue += $fee['montant'];
            $totalPaid += $fee['montant_paye'] ?: 0;
        }
        
        $totalRemaining = $totalDue - $totalPaid;
        
        // Get promotion for this year
        $stmt = $conn->prepare('SELECT p."designationPromotion", p.cycle
                               FROM inscription i
                               JOIN promotion p ON i.promotion_idpromotion = p.idpromotion
                               WHERE i.etudiant_idetudiant = ? 
                               AND i.annee_acad_idannee_acad = ?');
        $stmt->execute([$studentId, $year['idannee_acad']]);
        $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $payments[] = [
            'academic_year' => [
                'id' => $year['idannee_acad'],
                'name' => $year['designation']
            ],
            'promotion' => $promotion ? $promotion['designationPromotion'] : null,
            'cycle' => $promotion ? $promotion['cycle'] : null,
            'fees' => array_map(function($fee) {
                $amountPaid = $fee['montant_paye'] ?: 0;
                $amountRemaining = $fee['montant'] - $amountPaid;
                $status = ($amountPaid >= $fee['montant']) ? 'Payé' : 
                          ($amountPaid > 0 ? 'Partiel' : 'Non payé');
                
                return [
                    'id' => $fee['idfrais'],
                    'name' => $fee['designation'],
                    'amount' => $fee['montant'],
                    'currency' => $fee['devise'],
                    'description' => $fee['description'],
                    'is_mandatory' => $fee['est_obligatoire'] == 1,
                    'due_date' => $fee['date_echeance'],
                    'amount_paid' => $amountPaid,
                    'amount_remaining' => $amountRemaining,
                    'status' => $status
                ];
            }, $fees),
            'transactions' => array_map(function($transaction) {
                return [
                    'id' => $transaction['idpaiement'],
                    'date' => $transaction['date_paiement'],
                    'amount' => $transaction['montant'],
                    'currency' => $transaction['devise'],
                    'reference' => $transaction['reference'],
                    'payment_method' => $transaction['mode_paiement'],
                    'is_complete' => $transaction['estComplet'] == 1,
                    'fee_name' => $transaction['frais_designation']
                ];
            }, $transactions),
            'summary' => [
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining,
                'currency' => !empty($fees) ? $fees[0]['devise'] : 'USD',
                'payment_status' => ($totalRemaining <= 0) ? 'Payé' : 
                                   ($totalPaid > 0 ? 'Partiel' : 'Non payé')
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => [
                'id' => $student['idetudiant'],
                'matricule' => $student['matricule'],
                'name' => $student['noms'],
                'current_promotion' => $student['designationPromotion'],
                'current_cycle' => $student['cycle'],
                'orientation' => $student['designationOrientation'],
                'section' => $student['designationSection'],
                'current_academic_year' => $student['annee_academique']
            ],
            'payments' => $payments
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
