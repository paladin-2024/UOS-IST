# Gestion des Chefs de Promotion - Documentation Finale

## Vue d'ensemble

Le système de gestion des chefs de promotion permet d'assigner et de gérer les chefs de promotion pour chaque promotion et année académique. **Une promotion ne peut avoir qu'un seul chef actif à la fois**.

## Fonctionnalités principales

### 1. Assignation d'un chef de promotion
- Sélection d'un étudiant inscrit dans la promotion
- Vérification automatique que l'étudiant n'est pas déjà chef d'une autre promotion
- Désactivation automatique de l'ancien chef s'il y en a un
- Validation des droits d'accès selon les sections gérées

### 2. Retrait d'un chef de promotion
- Désactivation du chef actuel
- Confirmation obligatoire avant suppression
- Possibilité d'ajouter un commentaire/motif

### 3. Gestion des droits d'accès
- **Administrateurs** : Accès complet à toutes les promotions
- **Responsables de section** : Accès uniquement aux promotions de leurs sections
- Vérification automatique des droits à chaque action

### 4. Filtrage et recherche
- Filtrage par année académique
- Filtrage par section (selon les droits)
- Recherche textuelle sur promotions, orientations, noms et matricules des chefs

## Structure de la base de données

### Table `chef_promotion`
```sql
CREATE TABLE IF NOT EXISTS `chef_promotion` (
  `id_chef` int(11) NOT NULL AUTO_INCREMENT,
  `idetudiant` int(11) NOT NULL,
  `promotion_idpromotion` int(11) NOT NULL,
  `annee_acad_idannee_acad` int(11) NOT NULL,
  `date_nomination` date NOT NULL,
  `est_actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `idUser` int(11) NOT NULL,
  PRIMARY KEY (`id_chef`),
  UNIQUE KEY `idx_chef_unique_actif` (`promotion_idpromotion`, `annee_acad_idannee_acad`, `est_actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Contrainte d'unicité
La contrainte `idx_chef_unique_actif` garantit qu'une promotion ne peut avoir qu'un seul chef actif (`est_actif = 1`) pour une année académique donnée.

## Fichiers du système

### Fichiers principaux
- **`chef_promotion.php`** : Interface principale de gestion
- **`manage_chef_promotion.php`** : Contrôleur pour les actions d'assignation/retrait
- **`get_etudiants_promotion.php`** : API AJAX pour récupérer les étudiants d'une promotion

### Fichiers de maintenance
- **`verify_chef_promotion_constraints.php`** : Vérification de l'intégrité des données
- **`fix_chef_promotion_duplicates.php`** : Correction automatique des doublons
- **`test_chef_promotion_functionality.php`** : Tests de fonctionnalité

## Utilisation

### Pour les administrateurs
1. Accéder à "Configuration > Chefs de Promotion"
2. Sélectionner l'année académique et la section (optionnel)
3. Cliquer sur "Assigner" ou "Modifier" pour une promotion
4. Sélectionner l'étudiant dans la liste déroulante
5. Confirmer l'assignation

### Pour les responsables de section
1. Accéder à "Configuration > Chefs de Promotion"
2. Seules les promotions de leurs sections sont visibles
3. Même processus d'assignation que les administrateurs

## Validation et sécurité

### Validations côté serveur
- Vérification de l'existence de la promotion et de l'étudiant
- Contrôle des droits d'accès selon les sections
- Vérification que l'étudiant est inscrit dans la promotion
- Contrôle d'unicité (un seul chef par promotion/année)

### Validations côté client
- Sélection obligatoire d'un étudiant
- Confirmation avant suppression
- Messages d'erreur informatifs
- Désactivation des boutons pendant le chargement

## Messages d'erreur et de succès

### Messages de succès
- `chef_assigned` : Chef de promotion assigné avec succès
- `chef_removed` : Chef de promotion retiré avec succès

### Messages d'erreur
- `access_denied` : Accès refusé pour cette promotion
- `student_already_chef` : L'étudiant est déjà chef d'une autre promotion
- `student_not_in_promotion` : L'étudiant n'est pas inscrit dans cette promotion
- `no_chef_to_remove` : Aucun chef à retirer
- `database_error` : Erreur de base de données

## Maintenance et dépannage

### Vérification de l'intégrité
Exécuter `verify_chef_promotion_constraints.php` pour :
- Vérifier la structure de la table
- Détecter les doublons
- Contrôler les contraintes d'unicité
- Afficher les statistiques

### Correction des doublons
Exécuter `fix_chef_promotion_duplicates.php` pour :
- Identifier automatiquement les doublons
- Garder le chef le plus récent
- Désactiver les autres
- Ajouter la contrainte d'unicité si nécessaire

### Tests de fonctionnalité
Exécuter `test_chef_promotion_functionality.php` pour :
- Tester toutes les fonctionnalités
- Vérifier les performances
- Valider l'intégrité des données
- Obtenir un rapport complet

## Bonnes pratiques

### Pour les utilisateurs
1. Toujours vérifier l'année académique sélectionnée
2. S'assurer que l'étudiant sélectionné est bien actif
3. Ajouter un commentaire lors du retrait d'un chef
4. Vérifier les statistiques après les modifications

### Pour les administrateurs système
1. Exécuter régulièrement les scripts de vérification
2. Sauvegarder avant les modifications importantes
3. Surveiller les logs d'erreur
4. Maintenir la contrainte d'unicité active

## Dépendances

### Tables requises
- `chef_promotion` : Table principale
- `etudiant` : Informations des étudiants
- `promotion` : Informations des promotions
- `orientation` : Orientations des promotions
- `section` : Sections des orientations
- `annee_acad` : Années académiques
- `responsable_section` : Droits d'accès des responsables

### Bibliothèques JavaScript
- Bootstrap 5 (pour les modals et alertes)
- SweetAlert2 (optionnel, pour les confirmations)

## Support

En cas de problème :
1. Vérifier les logs d'erreur PHP
2. Exécuter les scripts de diagnostic
3. Vérifier les droits d'accès de l'utilisateur
4. Contrôler l'intégrité des données

## Évolutions futures possibles

1. Historique des nominations/retraits
2. Notifications automatiques aux chefs nommés
3. Rapports de gestion des chefs
4. Interface mobile optimisée
5. API REST pour intégration externe