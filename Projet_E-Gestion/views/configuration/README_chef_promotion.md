# Configuration des Chefs de Promotion

## Description
Ce module permet de configurer et gérer les chefs de promotion pour chaque année académique. Il respecte les droits d'accès des utilisateurs selon leurs responsabilités de section et utilise la table `chef_promotion` existante.

## Fichiers créés/modifiés

### 1. Interface utilisateur
- **`chef_promotion.php`** : Interface principale pour la gestion des chefs de promotion
  - Affichage des promotions avec leurs chefs actuels
  - Filtrage par année académique, section et recherche textuelle
  - Statistiques sur les promotions avec/sans chef
  - Modals pour assigner/retirer des chefs

### 2. Contrôleurs
- **`manage_chef_promotion.php`** : Contrôleur principal pour les actions d'assignation/retrait
  - Gestion des droits d'accès selon les sections
  - Validation des données
  - Utilise le champ `est_actif` pour gérer les chefs
  
- **`get_etudiants_promotion.php`** : API AJAX pour récupérer les étudiants d'une promotion
  - Retourne la liste des étudiants inscrits dans une promotion pour une année donnée
  - Respecte les droits d'accès de l'utilisateur

### 3. Base de données
- **`chef_promotion_tables.sql`** : Script d'adaptation de la table existante
  - Ajoute des index pour optimiser les performances
  - Crée une table d'historique optionnelle
  - Crée des vues pour faciliter les requêtes

## Fonctionnalités

### Gestion des droits d'accès
- **Responsables de section** : Peuvent uniquement gérer les chefs des promotions de leurs sections
- **Administrateurs** : Accès complet à toutes les promotions

### Assignation d'un chef
1. Sélectionner une promotion
2. Choisir un étudiant parmi ceux inscrits dans cette promotion
3. Valider l'assignation
4. L'ancien chef est automatiquement désactivé (`est_actif = 0`)

### Retrait d'un chef
1. Sélectionner une promotion ayant déjà un chef
2. Confirmer le retrait
3. Le chef est désactivé (`est_actif = 0`)

### Filtres disponibles
- **Année académique** : Sélection de l'année à gérer
- **Section** : Filtrage par section (selon les droits)
- **Recherche** : Recherche textuelle sur promotion, spécialisation ou nom du chef

### Statistiques
- Nombre total de promotions
- Nombre de promotions avec chef assigné
- Nombre de promotions sans chef
- Pourcentages correspondants

## Contraintes et validations

### Contraintes métier
- Un étudiant ne peut être chef que d'une seule promotion par année académique
- Un étudiant doit être inscrit dans la promotion pour en devenir chef
- Une promotion ne peut avoir qu'un seul chef actif par année académique

### Validations de sécurité
- Vérification des droits d'accès selon les sections gérées
- Validation de l'existence des promotions et étudiants
- Protection contre les injections SQL
- Gestion des transactions pour l'intégrité des données

## Structure de la base de données

### Table `chef_promotion` (existante)
```sql
- id_chef (PK, AUTO_INCREMENT)
- promotion_idpromotion (FK vers promotion)
- idetudiant (FK vers etudiant)
- annee_acad_idannee_acad (FK vers annee_acad)
- date_nomination (DATE)
- est_actif (BOOLEAN, DEFAULT 1)
- date_creation (DATETIME, DEFAULT current_timestamp())
- idUser (FK vers t_users)
```

### Table `chef_promotion_history` (optionnelle)
```sql
- id (PK, AUTO_INCREMENT)
- promotion_idpromotion (FK vers promotion)
- idetudiant (FK vers etudiant)
- annee_acad_idannee_acad (FK vers annee_acad)
- action (ENUM: ASSIGN, REMOVE, MODIFY)
- date_action (DATETIME)
- commentaire (TEXT)
- idUser (FK vers t_users)
```

## Installation

1. **Exécuter le script SQL** (optionnel pour optimisations) :
   ```sql
   SOURCE models/chef_promotion_tables.sql;
   ```

2. **Vérifier les permissions** :
   - S'assurer que les utilisateurs ont les bonnes responsabilités de section dans la table `responsable_section`

3. **Accéder au module** :
   - URL : `index.php?view=configuration/chef_promotion`

## Utilisation

### Pour un responsable de section
1. Se connecter avec un compte ayant des responsabilités de section
2. Accéder au module via le menu Configuration
3. Seules les promotions des sections gérées seront visibles
4. Assigner/retirer des chefs selon les besoins

### Pour un administrateur
1. Se connecter avec un compte administrateur (idRole = 1)
2. Accéder au module via le menu Configuration
3. Toutes les promotions sont visibles et modifiables
4. Possibilité de filtrer par section spécifique

## Logique de gestion des chefs

### Assignation
- Lors de l'assignation d'un nouveau chef, l'ancien chef de la même promotion/année est automatiquement désactivé (`est_actif = 0`)
- Le nouveau chef est inséré avec `est_actif = 1`
- Un étudiant ne peut être chef actif que d'une seule promotion par année

### Retrait
- Le chef est simplement désactivé (`est_actif = 0`)
- Les données historiques sont conservées
- La promotion n'a plus de chef actif

### Requêtes importantes
```sql
-- Récupérer le chef actif d'une promotion
SELECT * FROM chef_promotion 
WHERE promotion_idpromotion = ? 
AND annee_acad_idannee_acad = ? 
AND est_actif = 1;

-- Vérifier si un étudiant est déjà chef ailleurs
SELECT COUNT(*) FROM chef_promotion 
WHERE idetudiant = ? 
AND annee_acad_idannee_acad = ? 
AND est_actif = 1;
```

## Messages d'erreur

### Erreurs courantes
- **access_denied** : L'utilisateur n'a pas les droits sur cette promotion
- **student_already_chef** : L'étudiant est déjà chef d'une autre promotion
- **student_not_in_promotion** : L'étudiant n'est pas inscrit dans cette promotion
- **no_chef_to_remove** : Aucun chef actif à retirer pour cette promotion

### Messages de succès
- **chef_assigned** : Chef assigné avec succès
- **chef_removed** : Chef retiré avec succès

## Maintenance

### Nettoyage des données
- Les anciens chefs sont automatiquement désactivés (`est_actif = 0`) lors de nouvelles assignations
- Aucune suppression physique des données pour conserver l'historique

### Sauvegarde
- Sauvegarder régulièrement la table `chef_promotion`
- Cette table contient des données critiques pour la gestion académique

## Optimisations ajoutées

### Index créés
- `idx_unique_chef_promotion_actif` : Pour optimiser la recherche des chefs actifs
- `idx_chef_promotion_etudiant` : Pour les recherches par étudiant
- `idx_chef_promotion_annee` : Pour les filtres par année académique

### Vues créées
- `v_chef_promotion` : Vue simplifiée avec toutes les informations utiles
- `v_chef_promotion_history` : Vue pour l'historique (si table créée)

## Support
Pour toute question ou problème, consulter les logs d'erreur PHP et vérifier :
1. Les droits d'accès de l'utilisateur
2. L'intégrité des données de base (promotions, étudiants, inscriptions)
3. La configuration des responsabilités de section
4. Que la table `chef_promotion` existe avec la bonne structure