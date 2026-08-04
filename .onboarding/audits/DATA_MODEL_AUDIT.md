# Modèle de données — Audit

> Confiance : high

## Compréhension globale

Il n'y a pas de modèle de données persisté. L'unique « base de données » du système est la constante PHP `DeliveryController::DELIVERIES` — un tableau de 3 entrées codées en dur dans le contrôleur. Aucune entité Doctrine, aucune migration, aucun schéma de base de données. Le projet ne dépend d'aucun système de stockage externe.

## Résumé exécutif

Le modèle de données se résume à une constante PHP privée (`src/Controller/DeliveryController.php:13-17`) portant 3 livraisons avec 4 champs chacune : `id` (entier littéral), `island` (chaîne libre), `status` (chaîne libre — valeurs observées : `en_transit`, `livre`), `etaDays` (entier). Aucun type fort, aucun enum PHP 8.1, aucune validation, aucune contrainte d'intégrité. Les données sont invariantes à l'exécution : `private const` garantit qu'aucun setter, aucune injection ne peut les modifier sans modifier le code source. Ce modèle est approprié pour un pilote de test mais représente la dette fonctionnelle la plus évidente si le projet doit évoluer : passer à une vraie persistance imposera de choisir un schéma, des contraintes, des migrations et un ORM, à partir de rien. L'absence de contraintes formelles entre `etaDays` et `status` est un point de fragilité latent.

## Constats détaillés

`VÉRIFIÉ_CODE` : La constante `DELIVERIES` est déclarée `private const` dans `DeliveryController` (`src/Controller/DeliveryController.php:13`). Elle porte exactement 3 entrées : `{id:1, island:'Bora Bora', status:'en_transit', etaDays:3}`, `{id:2, island:'Moorea', status:'livre', etaDays:0}`, `{id:3, island:'Huahine', status:'en_transit', etaDays:5}` (`src/Controller/DeliveryController.php:14-16`). Ces données sont les seules dans le système.

`VÉRIFIÉ_CODE` : Les champs `id`, `island`, `status`, `etaDays` sont des clés de tableaux associatifs PHP sans type défini formellement ni enum. PHP 8.1 supporte les enums natifs (`enum DeliveryStatus: string { case EnTransit = 'en_transit'; case Livre = 'livre'; }`) mais aucun enum n'est défini dans ce dépôt. Les valeurs de `status` sont des chaînes littérales (`src/Controller/DeliveryController.php:14-16`).

`VÉRIFIÉ_CODE` : Aucune contrainte formelle ne lie `etaDays` à `status`. Dans l'état actuel des données : `id:2` (Moorea) a `status:'livre'` et `etaDays:0` — cohérent logiquement (livrée, ETA atteint). `id:1` (Bora Bora) a `status:'en_transit'` et `etaDays:3`. `id:3` (Huahine) a `status:'en_transit'` et `etaDays:5`. Les 3 entrées actuelles sont cohérentes, mais aucune règle d'intégrité ne l'enforcerait si les données étaient modifiées (`src/Controller/DeliveryController.php:14-16`).

`VÉRIFIÉ_CODE` : Aucune entité Doctrine, aucun fichier de migration, aucun `doctrine/orm` ni `doctrine/dbal` dans `composer.json` (`composer.json:6-12`). Le projet n'est pas couplé à une base de données — la dépendance `symfony/yaml` est présente pour parser `config/routes.yaml`, pas pour un ORM.

`VÉRIFIÉ_CODE` : Le champ `id` est un entier attribué manuellement (1, 2, 3) sans mécanisme d'auto-incrémentation ni vérification d'unicité (`src/Controller/DeliveryController.php:14-16`). Dans le contexte statique actuel, cela ne pose pas de problème.

## Forces

- Modèle ultra-simple, aucun risque de régression de schéma ou de migration
- Données cohérentes dans leur état actuel : aucune incohérence observable entre `status` et `etaDays` sur les 3 entrées
- `private const` garantit l'immuabilité des données à l'exécution — aucun effet de bord possible via mutation externe

## Dettes techniques

- Absence de type fort sur `status` : une chaîne arbitraire peut être introduite sans erreur PHP, altérant silencieusement le comportement de `pending()` (`src/Controller/DeliveryController.php:14-16`)
- `id` est un entier manuel sans mécanisme d'unicité garanti
- Le modèle est figé dans le code : toute mise à jour (nouvelle livraison, changement de statut) requiert une modification du code source et un redéploiement — non viable pour des données opérationnelles
- Aucun schema documenté : si la persistance est introduite, les décisions de type, contraintes et index devront être prises sans base formelle

## Zones critiques

- Le champ `status` sans enum est la première dette à adresser si le pilote évolue : l'ajout d'un nouveau statut (`annule`, `retarde`, `en_douane`) passerait silencieusement dans `pending()` et altérerait les résultats sans déclencher d'erreur ni de test cassé (`src/Controller/DeliveryController.php:28-31`)

## Risques

- Extensibilité silencieuse du filtre `pending` : la logique `status !== 'livre'` inclura automatiquement tout nouveau statut non prévu — risque fonctionnel élevé lors d'une extension du modèle de statuts
- Incohérence `etaDays`/`status` non contrôlée : une livraison `en_transit` avec `etaDays:0` serait logiquement incohérente (ETA atteint mais pas marquée livrée) et retournée par `pending()` sans avertissement
- Passage à la persistance ex nihilo : aucun point de départ formalisé (pas d'entité, pas de schéma) — le coût d'une introduction de BDD est plus élevé que si un modèle partiel existait déjà

## Recommandations priorisées

1. Définir un enum PHP 8.1 `DeliveryStatus: string` avec les cas `EnTransit = 'en_transit'` et `Livre = 'livre'` avant tout ajout de statut — protège le filtre `pending()` et documente le modèle — `src/Controller/DeliveryController.php:14-16`
2. Si persistance introduite : créer une entité Doctrine `Delivery` avec colonnes typées, contrainte d'unicité sur `id`, index sur `status` et `island`; migrations Doctrine DBAL dès le départ
3. Formaliser l'invariant `etaDays ↔ status` (règle : `etaDays == 0` si et seulement si `status == livre`) dans un service de validation ou via les contraintes Doctrine, avant d'autoriser des mutations de données

## Questions ouvertes

- La liste réelle des statuts est-elle plus large que `en_transit` / `livre` ? (ex. `en_douane`, `annule`, `retarde`, `pre_livraison`) — déterminant pour le design du futur enum et la logique de `pending()`
- `etaDays` est-il un nombre de jours calculé à la demande depuis une date d'expédition (dynamique) ou une valeur saisie manuellement (statique) ? La réponse impacte le schéma persisté.
- `id` : auto-généré (UUID, séquentiel) ou fourni par un système externe de gestion de fret ?
