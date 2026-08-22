<?php
/**
 * Configuration pour l'import des grilles anciennes
 * Ce fichier permet de configurer le mapping entre les colonnes Excel et la structure de données
 */

class ImportGrilleConfig {
    
    /**
     * Templates de structures Excel reconnues
     */
    public static $templates = [
        'standard' => [
            'description' => 'Format standard avec UE et ECUE',
            'structure' => [
                'lignes_entete' => 3, // Nombre de lignes d'en-tête avant les données
                'colonnes' => [
                    'A' => 'numero',
                    'B' => 'matricule',
                    'C' => 'nom_etudiant',
                    // Les colonnes suivantes sont dynamiques (UE/ECUE)
                ],
                'pattern_ue' => '/^UE\s*\d+/i',
                'pattern_ecue' => '/^ECUE\s*\d+/i',
                'ligne_credits' => true // Si une ligne contient les crédits/coefficients
            ]
        ],
        
        'simple' => [
            'description' => 'Format simple avec notes directes',
            'structure' => [
                'lignes_entete' => 1,
                'colonnes' => [
                    'A' => 'matricule',
                    'B' => 'nom_etudiant',
                    // Colonnes C et suivantes = matières
                ],
                'pattern_ue' => null,
                'pattern_ecue' => null,
                'ligne_credits' => false
            ]
        ],
        
        'avec_sessions' => [
            'description' => 'Format avec sessions principale et rattrapage',
            'structure' => [
                'lignes_entete' => 2,
                'colonnes' => [
                    'A' => 'matricule',
                    'B' => 'nom_etudiant',
                    // Colonnes suivantes avec suffixe _P (principale) et _R (rattrapage)
                ],
                'pattern_session_principale' => '/_P$/i',
                'pattern_session_rattrapage' => '/_R$/i',
                'ligne_credits' => true
            ]
        ]
    ];
    
    /**
     * Mapper automatiquement la structure d'un fichier Excel
     */
    public static function mapStructure($worksheet) {
        $mapping = [
            'type' => 'unknown',
            'colonnes_fixes' => [],
            'colonnes_dynamiques' => [],
            'ues' => [],
            'ecues' => [],
            'sessions' => [],
            'credits' => []
        ];
        
        // Analyser les 10 premières lignes pour comprendre la structure
        $maxRow = min(10, $worksheet->getHighestRow());
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Détecter le type de structure
        $hasUE = false;
        $hasECUE = false;
        $hasSession = false;
        $currentUE = null;
        
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $cellValue = $worksheet->getCell($colLetter . $row)->getValue();
                
                if ($cellValue === null || trim($cellValue) === '') {
                    continue;
                }
                
                $value = trim($cellValue);
                $valueLower = strtolower($value);
                
                // Détecter les colonnes fixes
                if (preg_match('/matricule|mat\b/i', $value)) {
                    $mapping['colonnes_fixes']['matricule'] = $colLetter;
                } elseif (preg_match('/nom|prenom|etudiant/i', $value)) {
                    $mapping['colonnes_fixes']['nom'] = $colLetter;
                }
                
                // Détecter les UE
                if (preg_match('/^UE\s*[\d\.]+/i', $value)) {
                    $hasUE = true;
                    $currentUE = [
                        'colonne' => $colLetter,
                        'nom' => $value,
                        'ecues' => []
                    ];
                    $mapping['ues'][] = $currentUE;
                }
                
                // Détecter les ECUE
                if (preg_match('/^ECUE\s*[\d\.]+/i', $value) || 
                    (preg_match('/^[A-Z]{2,}/i', $value) && !preg_match('/UE|CREDIT|MOY|TOTAL/i', $value))) {
                    $hasECUE = true;
                    $ecue = [
                        'colonne' => $colLetter,
                        'nom' => $value,
                        'ue_parent' => $currentUE ? $currentUE['nom'] : null
                    ];
                    $mapping['ecues'][] = $ecue;
                    
                    if ($currentUE) {
                        $currentUE['ecues'][] = $ecue;
                    }
                }
                
                // Détecter les sessions
                if (preg_match('/session|principale|rattrapage/i', $value)) {
                    $hasSession = true;
                    $mapping['sessions'][] = [
                        'colonne' => $colLetter,
                        'type' => $value
                    ];
                }
                
                // Détecter les crédits
                if (preg_match('/credit|coef|pond/i', $value)) {
                    $mapping['credits'][] = [
                        'colonne' => $colLetter,
                        'type' => $value
                    ];
                }
            }
        }
        
        // Déterminer le type de structure
        if ($hasUE && $hasECUE) {
            $mapping['type'] = 'hierarchique_complet';
        } elseif ($hasUE) {
            $mapping['type'] = 'hierarchique_simple';
        } elseif ($hasSession) {
            $mapping['type'] = 'avec_sessions';
        } else {
            $mapping['type'] = 'simple';
        }
        
        return $mapping;
    }
    
    /**
     * Valider la structure détectée
     */
    public static function validateStructure($mapping) {
        $errors = [];
        $warnings = [];
        
        // Vérifications obligatoires
        if (!isset($mapping['colonnes_fixes']['matricule']) && 
            !isset($mapping['colonnes_fixes']['nom'])) {
            $errors[] = "Aucune colonne d'identification des étudiants trouvée (matricule ou nom)";
        }
        
        if (empty($mapping['ues']) && empty($mapping['ecues'])) {
            $warnings[] = "Aucune structure UE/ECUE détectée. Les colonnes seront traitées comme des matières simples.";
        }
        
        if (empty($mapping['credits'])) {
            $warnings[] = "Aucune colonne de crédits/coefficients détectée. Des valeurs par défaut seront utilisées.";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Obtenir les instructions pour préparer un fichier Excel
     */
    public static function getInstructions() {
        return [
            'format_standard' => [
                'titre' => 'Format Standard (Recommandé)',
                'description' => 'Structure hiérarchique avec UE et ECUE',
                'instructions' => [
                    '1. Ligne 1: En-têtes principaux (Matricule, Nom, UE1, UE2, etc.)',
                    '2. Ligne 2: Sous-en-têtes pour les ECUE',
                    '3. Ligne 3: Coefficients ou crédits (optionnel)',
                    '4. Lignes suivantes: Données des étudiants'
                ],
                'exemple' => [
                    ['Matricule', 'Nom', 'UE1: Mathématiques', '', '', 'UE2: Informatique', '', ''],
                    ['', '', 'Algèbre', 'Analyse', 'Géométrie', 'Programmation', 'BD', 'Réseaux'],
                    ['', '', '2', '3', '2', '3', '3', '2'],
                    ['2021001', 'DUPONT Jean', '15', '12', '14', '16', '13', '15']
                ]
            ],
            
            'format_simple' => [
                'titre' => 'Format Simple',
                'description' => 'Liste simple des matières',
                'instructions' => [
                    '1. Ligne 1: En-têtes (Matricule, Nom, Matière1, Matière2, etc.)',
                    '2. Lignes suivantes: Données des étudiants'
                ],
                'exemple' => [
                    ['Matricule', 'Nom', 'Mathématiques', 'Informatique', 'Physique'],
                    ['2021001', 'DUPONT Jean', '15', '14', '13']
                ]
            ],
            
            'format_sessions' => [
                'titre' => 'Format avec Sessions',
                'description' => 'Incluant session principale et rattrapage',
                'instructions' => [
                    '1. Ligne 1: En-têtes avec indication de session',
                    '2. Utiliser _P pour principale, _R pour rattrapage',
                    '3. Lignes suivantes: Données des étudiants'
                ],
                'exemple' => [
                    ['Matricule', 'Nom', 'Math_P', 'Math_R', 'Info_P', 'Info_R'],
                    ['2021001', 'DUPONT Jean', '8', '12', '15', '']
                ]
            ]
        ];
    }
    
    /**
     * Convertir une note textuelle en numérique
     */
    public static function parseNote($value) {
        if ($value === null || trim($value) === '') {
            return null;
        }
        
        // Nettoyer la valeur
        $value = trim($value);
        
        // Gérer les mentions textuelles
        $mentions = [
            'A+' => 18, 'A' => 16, 'A-' => 15,
            'B+' => 14, 'B' => 13, 'B-' => 12,
            'C+' => 11, 'C' => 10, 'C-' => 9,
            'D' => 8, 'E' => 6, 'F' => 0
        ];
        
        if (isset($mentions[strtoupper($value)])) {
            return $mentions[strtoupper($value)];
        }
        
        // Essayer de convertir en nombre
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        // Extraire un nombre d'une chaîne (ex: "15/20")
        if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*20/', $value, $matches)) {
            return floatval($matches[1]);
        }
        
        // Extraire un pourcentage
        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $value, $matches)) {
            return floatval($matches[1]) * 0.2; // Convertir en note sur 20
        }
        
        return null;
    }
}
?>