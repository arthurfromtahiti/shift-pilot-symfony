# WORKFLOW_LISTE_LIVRAISONS — Consulter la liste complète des livraisons inter-îles

## Classification
- **Type** : `api_flow`
- **Sous-type** : lecture seule, réponse JSON statique
- **Visibilité** : `unknown` — endpoint public, aucune authentification visible dans le code ; l'audience réelle (consommateur interne, partenaire, application mobile) n'est pas déterminable depuis le code seul
- **Acteur principal** : client HTTP
- **Acteurs** : client HTTP (consommateur de l'API) ; `DeliveryController` (traitement serveur)
- **Criticité** : Basse — les données sont une constante PHP ; aucune persistance, aucun effet de bord, aucun système externe
- **Confiance** : high
- **Justification** : le contrôleur a été lu en intégralité (`src/Controller/DeliveryController.php`, 34 lignes). Le fichier de routes a été lu (`config/routes.yaml`, 3 lignes). Le test unitaire couvrant cet endpoint a été lu (`tests/DeliveryControllerTest.php:9-16`). Toutes les affirmations ci-dessous sont `VÉRIFIÉ_CODE`.

## Objectif
Permettre à un consommateur d'API de récupérer en une seule requête l'intégralité des livraisons de fret inter-îles connues du système, avec pour chaque livraison son identifiant, l'île de destination, le statut d'acheminement et le nombre de jours estimés avant livraison. Dans l'état actuel du pilote, le catalogue est figé (3 livraisons codées en dur) et la réponse est identique à chaque appel.

## Acteurs
- **Client HTTP** : tout consommateur capable d'émettre un `GET /deliveries` (navigateur, application, outil de test)
- **DeliveryController** (`src/Controller/DeliveryController.php`) : seul composant serveur impliqué ; instanciable sans injection de dépendances

## Points d'entrée
- `GET /deliveries` — déclaré via attribut PHP `#[Route('/deliveries', methods: ['GET'])]` (`src/Controller/DeliveryController.php:19`) ; découverte automatique par Symfony via `config/routes.yaml:1-3` (`resource: ../src/Controller/`, `type: annotation`)

## Étapes principales
1. **Réception de la requête** : Symfony route `GET /deliveries` vers `DeliveryController::list()` (`src/Controller/DeliveryController.php:20`).
2. **Lecture du catalogue statique** : la méthode retourne directement la constante `self::DELIVERIES` (`src/Controller/DeliveryController.php:22`) — aucune base de données, aucune logique de filtrage, aucune transformation.
3. **Sérialisation et réponse** : `new JsonResponse(self::DELIVERIES)` (`src/Controller/DeliveryController.php:22`) — Symfony sérialise le tableau PHP en JSON et renvoie HTTP 200 avec `Content-Type: application/json`.

## Règles métier
- **Catalogue exhaustif fourni sans filtre** : `list()` retourne `self::DELIVERIES` sans condition (`src/Controller/DeliveryController.php:22`) ; il n'existe aucun paramètre de filtrage, de pagination ou de tri.
- **Structure imposée par la constante** : chaque livraison expose exactement quatre champs — `id` (entier), `island` (chaîne), `status` (chaîne — valeurs observées : `en_transit`, `livre`), `etaDays` (entier) (`src/Controller/DeliveryController.php:13-17`). Aucune validation de sortie explicite.
- **Données invariantes à l'exécution** : la constante `DELIVERIES` est déclarée `private const` ; aucun setter, aucune injection — les données ne peuvent pas être modifiées sans modifier le code source (`src/Controller/DeliveryController.php:13-17`).

## Données
- **`DeliveryController::DELIVERIES`** (`src/Controller/DeliveryController.php:13-17`) : constante PHP de type tableau associatif, unique source de données du système. Contient 3 entrées au moment de l'analyse : `{id:1, island:'Bora Bora', status:'en_transit', etaDays:3}`, `{id:2, island:'Moorea', status:'livre', etaDays:0}`, `{id:3, island:'Huahine', status:'en_transit', etaDays:5}`.

## Intégrations
Aucune intégration externe explicite visible. Le contrôleur n'appelle aucun service, aucune API tierce, aucune base de données.

## Risques
- **Données figées** : toute mise à jour du catalogue (nouvelle livraison, changement de statut, correction d'ETA) nécessite une modification du code source et un redéploiement. Scénario concret : une livraison réelle change de statut `en_transit` → `livre` ; la réponse API reste `en_transit` jusqu'au prochain déploiement (`src/Controller/DeliveryController.php:13-17`).
- **Pas de contrôle de méthode HTTP au niveau applicatif** : la restriction `methods: ['GET']` est portée par l'attribut de route Symfony (`src/Controller/DeliveryController.php:19`) ; un `POST /deliveries` produira une 405 Framework, mais aucune gestion applicative explicite n'est visible — comportement acceptable mais à vérifier si le framework est bootstrapé en situation réelle (voir *Questions ouvertes*).
- **Absence d'authentification** : l'endpoint est accessible sans jeton ni credential. Si des livraisons contiennent un jour des données sensibles (adresses, destinataires), cette absence devient un risque de confidentialité.

## Questions ouvertes
- **Aucun Kernel ni `public/index.php`** : le dépôt est exécutable en test unitaire (instanciation directe du contrôleur, `tests/DeliveryControllerTest.php`) mais non servable comme application HTTP sans `src/Kernel.php` et `public/index.php`. Les tests vérifient le comportement interne du contrôleur ; ils ne valident pas le routage Symfony end-to-end. Est-ce volontaire pour le pilote ?
- **Évolution vers la persistance** : le catalogue passera-t-il un jour à une base de données (entité `Delivery`, repository) ? Si oui, quels sont le modèle (colonnes, index), les contraintes d'intégrité et les volumes attendus ?
- **Pagination et filtrage** : pour un catalogue réel de livraisons, un `GET /deliveries` sans limite de résultats deviendra-t-il un problème de performance ou d'UX ?
- **Statuts exhaustifs** : `en_transit` et `livre` sont les seules valeurs visibles dans les données. La liste réelle (`annule`, `en_douane`, `retarde`…) est-elle plus large ?

## Preuves
- `src/Controller/DeliveryController.php:1-34` — contrôleur complet lu (classe, constante, deux méthodes, attributs de route)
- `config/routes.yaml:1-3` — configuration de découverte des routes lue
- `tests/DeliveryControllerTest.php:9-16` — test `testListReturnsAllDeliveries` lu (assertion : 3 éléments retournés)
- `composer.json:1-25` — dépendances et scripts lus (confirmation Symfony 5.4, PHP ≥ 8.1, `phpunit tests` comme script `test`)
