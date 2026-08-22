<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/models/Universite.php';

header('Content-Type: application/json');

// Initialiser la réponse
$response = [
    'success' => false,
    'message' => 'Erreur lors du téléchargement des documents',
    'documents' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données
    $studentId = isset($_POST['studentId']) ? intval($_POST['studentId']) : 0;
    $cycle = isset($_POST['cycle']) ? trim($_POST['cycle']) : 'Premier';
    
    if ($studentId <= 0) {
        $response['message'] = 'ID étudiant invalide';
        echo json_encode($response);
        exit;
    }
    
    // Récupérer les IDs des documents obligatoires
    $docObligatoireIds = isset($_POST['doc_obligatoire_ids']) ? $_POST['doc_obligatoire_ids'] : [];
    
    if (empty($docObligatoireIds)) {
        $response['message'] = 'Aucun document à télécharger';
        echo json_encode($response);
        exit;
    }
    
    $universite = new Universite();
    
    // Récupérer l'année académique en cours
    $currentAcademicYear = $universite->getCurrentAcademicYear();
    
    // Récupérer les informations de l'étudiant
    $student = $universite->getStudentById($studentId);
    
    if (!$student) {
        $response['message'] = 'Étudiant non trouvé';
        echo json_encode($response);
        exit;
    }
    
    // Récupérer les documents déjà téléchargés
    $existingDocuments = $universite->getStudentDocuments($studentId);
    
    // Télécharger les nouveaux documents
    $uploadedDocuments = [];
    $uploadErrors = [];
    
    foreach ($docObligatoireIds as $docId) {
        $fieldName = "document_" . $docId;
        
        // Vérifier si un fichier a été téléchargé
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/documents/';
                        // Créer le répertoire s'il n'existe pas
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Générer un nom de fichier unique
                        $originalFileName = basename($_FILES[$fieldName]['name']);
                        $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                        $newFileName = 'doc_' . $studentId . '_' . $docId . '_' . time() . '.' . $fileExtension;
                        $uploadFile = $uploadDir . $newFileName;
                        
                        // Vérifier le type de fichier
                        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
                        if (!in_array($fileExtension, $allowedTypes)) {
                            $uploadErrors[] = "Le format du document #$docId n'est pas accepté (formats autorisés: PDF, JPG, PNG)";
                            continue;
                        }
                        
                        // Vérifier la taille du fichier (max 5 Mo)
                        if ($_FILES[$fieldName]['size'] > 5242880) {
                            $uploadErrors[] = "La taille du document #$docId dépasse 5 Mo";
                            continue;
                        }
                        
                        // Récupérer les informations sur le document obligatoire
                        $documentInfo = $universite->getRequiredDocumentById($docId);
                        
                        if (!$documentInfo) {
                            $uploadErrors[] = "Type de document #$docId non reconnu";
                            continue;
                        }
                        
                        // Vérifier si ce document existe déjà pour cet étudiant
                        $existingDoc = null;
                        if ($existingDocuments) {
                            foreach ($existingDocuments as $doc) {
                                if ($doc['document_obligatoire_id'] == $docId) {
                                    $existingDoc = $doc;
                                    break;
                                }
                            }
                        }
                        
                        // Télécharger le fichier
                        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $uploadFile)) {
                            $relativePath = 'uploads/documents/' . $newFileName;
                            
                            // Créer ou mettre à jour l'entrée du document
                            $documentData = [
                                'idetudiant' => $studentId,
                                'matricule' => $student['matricule'],
                                'document_obligatoire_id' => $docId,
                                'type_document' => $documentInfo['designation'],
                                'titre' => $originalFileName,
                                'description' => "Document " . $documentInfo['designation'],
                                'chemin_fichier' => $relativePath,
                                'annee_acad_id' => $currentAcademicYear['idannee_acad'],
                                'statut' => 'En attente de validation'
                            ];
                            
                            if ($existingDoc) {
                                // Mettre à jour le document existant
                                $documentData['id'] = $existingDoc['id'];
                                $result = $universite->updateStudentDocument($documentData);
                                
                                // Supprimer l'ancien fichier si la mise à jour a réussi
                                if ($result && file_exists('../' . $existingDoc['chemin_fichier'])) {
                                    unlink('../' . $existingDoc['chemin_fichier']);
                                }
                            } else {
                                // Créer un nouveau document
                                $result = $universite->addStudentDocument($documentData);
                            }
                            
                            if ($result) {
                                $uploadedDocuments[] = $documentData;
                            } else {
                                $uploadErrors[] = "Erreur lors de l'enregistrement du document #$docId";
                            }
                        } else {
                            $uploadErrors[] = "Erreur lors du téléchargement du document #$docId";
                        }
                    }
                }
                
                // Récupérer la liste mise à jour des documents
                $updatedDocuments = $universite->getStudentDocuments($studentId);
                
                // Préparer la réponse
                if (!empty($uploadedDocuments)) {
                    $response['success'] = true;
                    $response['message'] = count($uploadedDocuments) . ' document(s) téléchargé(s) avec succès.';
                    
                    if (!empty($uploadErrors)) {
                        $response['message'] .= ' Certains documents n\'ont pas pu être téléchargés.';
                        $response['errors'] = $uploadErrors;
                    }
                    
                    $response['documents'] = $updatedDocuments;
                } else {
                    if (!empty($uploadErrors)) {
                        $response['message'] = 'Erreurs lors du téléchargement des documents: ' . implode(', ', $uploadErrors);
                    } else {
                        $response['message'] = 'Aucun document n\'a été téléchargé.';
                    }
                }
            }
            
            echo json_encode($response);
            