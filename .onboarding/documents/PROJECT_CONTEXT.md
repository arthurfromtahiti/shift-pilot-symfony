# Contexte projet — shift-pilot-symfony

> **Confiance** : high — tous les constats dérivent du code lu et des workflows validés. Domaines et limites établis par preuve, pas par hypothèse.

## Nature du projet

**Micro-API HTTP de suivi de livraisons inter-îles** (Polynésie française). Technologie : Symfony 5.4 (PHP ≥ 8.1), exposition JSON sur deux endpoints GET.

Le projet est explicitement un **pilote/seed SHIFT/Paperclip** : dépôt de démonstration fonctionnelle, données fictives, aucune persistance, aucune production. Vocation : valider le pattern d'onboarding technique et la lisibilité des workflows amont.

### Métier en une phrase

Consultation de l'état d'acheminement des frets vers les îles (Bora Bora, Moorea, Huahine) — statut courant (en transit / livrées) et délai estimé jusqu'à livraison.

## Domaines clés

**Un seul domaine métier confirmé** : `suivi-livraisons` (cœur fonctionnel, confiance haute).

### Suivi des livraisons inter-îles

- **Scope** : consultation de l'état d'acheminement. Deux vues : la liste complète des livraisons connues ; la sous-liste des livraisons dont le statut n'est pas `'livre'` (« en attente »).
- **Points d'entrée** : 
  - `GET /deliveries` → liste complète (source : `src/Controller/DeliveryController.php:22`)
  - `GET /deliveries/pending` → filtre `status !== 'livre'` (source : `src/Controller/DeliveryController.php:28-31`)
- **Données** : constante PHP `DeliveryController::DELIVERIES` (`src/Controller/DeliveryController.php:13-17`), structure fixe `{ id: int, island: string, status: enum(en_transit|livre), etaDays: int }`.
- **Acteurs** : client HTTP (navigateur, app, outil de test) ; pas d'authentification, endpoints publics.
- **Responsabilités** : aucune mutation (création, modification, suppression) — lecture seule.

## Limites du périmètre

### Ce qui n'existe pas (volontairement)

- **Pas de persistance** : pas de base de données, pas d'entité Doctrine, pas de repository. Les données sont une constante PHP codée en dur (`src/Controller/DeliveryController.php:13-17`). Toute mise à jour réelle d'une livraison nécessite un redéploiement du code.
- **Pas de mutations** : aucun endpoint pour créer (`POST`), modifier (`PATCH/PUT`) ou supprimer (`DELETE`) une livraison. Le système est une fenêtre de consultation figée.
- **Pas de consultation par ID** : impossible de récupérer une livraison unique via `GET /deliveries/{id}`. Un client doit charger la liste entière et filtrer côté client.
- **Pas d'authentification/autorisation** : endpoints accessibles sans token. Acceptable pour un pilote ; deviendrait un risque si les données contenaient un jour des adresses ou destinataires sensibles.
- **Pas de pagination, tri, filtrage paramétrique** : `GET /deliveries` retourne toujours les 3 livraisons sans possibilité de limiter les résultats, de trier ou de filtrer par île/statut. Sur un vrai catalogue, ce serait une limitation majeure.
- **Bootstrap HTTP incomplet** : ni `src/Kernel.php` ni `public/index.php` n'existent dans ce dépôt. Les endpoints sont testables par instanciation directe du contrôleur (tests unitaires) mais n'ont pas été validés dans le contexte d'une application Symfony réelle exécutée comme serveur HTTP.

### Ce qui est à confirmer

- **Évolution vers la persistance** : le pilote doit-il devenir une API opérationnelle avec une base de données réelle, ou restera-t-il un banc d'essai ?
- **Statuts métier exhaustifs** : la liste réelle des statuts (`en_transit`, `livre`, et possiblement `annule`, `en_douane`, `retarde`) est-elle fixée ? La règle de filtrage `pending` = `status !== 'livre'` peut inclure silencieusement tout nouveau statut.
- **Liaison `etaDays` ↔ `status`** : existe-t-il une contrainte métier qui force `etaDays == 0 ↔ status == 'livre'` ? Aujourd'hui, rien n'empêche une incohérence (ex. : `en_transit` avec `etaDays:0`).

## Points d'attention critiques

### 1. Règle de filtrage `pending()` implicite et extensible

La définition de « en attente » repose sur une exclusion négative : tout ce qui n'est pas `'livre'` est considéré en attente. Cette règle est ouverte : si un nouveau statut `annule` ou `expire` est introduit, il apparaîtra silencieusement dans `pending()` sans modification du code.

**Risque concret** : un opérateur de livraisons voit soudain des livraisons annulées s'afficher dans son tableau de bord « en attente » sans que personne n'ait touché le code applicatif.

**Source** : `src/Controller/DeliveryController.php:30` — `fn(array $d) => $d['status'] !== 'livre'`

### 2. Incohérence `etaDays` / `status` non contrôlée

Les données statiques coexistent : `{id, island, status, etaDays}`. Aucune règle d'intégrité ne lie ces deux champs. Une livraison peut être marquée `en_transit` avec `etaDays:0` (logiquement incohérente : le délai estimé a expiré mais la livraison n'est pas marquée livrée). Une telle incohérence serait retournée par `pending()` et visible dans l'API sans avertissement.

**Risque concret** : fausses données dans le flux de travail des opérateurs ; confusion sur le vrai statut d'une livraison.

**Source** : `src/Controller/DeliveryController.php:13-17` — constante sans validation ; aucun test d'invariant dans `DeliveryControllerTest.php`.

### 3. Absence de documentation d'API

Pas d'OpenAPI/Swagger, pas de PHPDoc, pas de versioning de route. Si les endpoints évoluent, les clients existants ne sauront pas à quel moment les URLs ou formats de réponse changeront.

**Source** : `src/Controller/DeliveryController.php:19,25` — routes nues `/deliveries`, `/deliveries/pending` sans versioning.

## Acteurs et capacités

### Client HTTP (externe)

- **Peut** : émettre `GET /deliveries` et `GET /deliveries/pending`, consommer les réponses JSON.
- **Ne peut pas** : modifier les données, créer une livraison, consulter une livraison unique par ID, s'authentifier (endpoints publics).

### Système

- **`DeliveryController`** (`src/Controller/DeliveryController.php`) : seul composant applicatif. Instanciation simple (pas d'injection de dépendances), deux méthodes publiques (`list()`, `pending()`), une source de données (constante `DELIVERIES`).

## Couverture de test

**Statut observé** : deux tests unitaires couvrent les deux endpoints (`tests/DeliveryControllerTest.php:9-16` pour `list()`, `18-27` pour `pending()`). Assertions lues et validées (`VÉRIFIÉ_CODE`), mais l'infrastructure d'exécution (PHP 8.1+, Composer, PHPUnit) n'était pas disponible au moment de l'analyse — statut de runtime reste `INCONNU`.

**Limites** : aucun test d'intégration end-to-end (application Symfony réelle servie comme HTTP) — voir *Bootstrap HTTP incomplet* ci-dessus.

## Confiance et maturité

| Dimension | Niveau | Justification |
|-----------|--------|---------------|
| **Fonctionnel** | High | Code relu, workflows validés, comportement prévisible pour le périmètre de pilote déclaré |
| **Persistence** | N/A | Pas de base de données ; cette dimension n'existe pas |
| **Sécurité** | Low | Endpoints publics, aucune authentification. Acceptable pour un pilote ; risque si données sensibles. |
| **Performance** | N/A | Données statiques, 3 entrées, pas de requête externe — performance non mesurable ni critique |
| **Opérabilité** | Low | Application non exécutable en l'état comme serveur HTTP réel ; bootstrap absent |

## Prochaines étapes suggérées (hors périmètre de ce onboarding)

1. **Confirmer l'intention** : ce pilote doit-il devenir une API opérationnelle ?
2. **Documenter la règle `pending`** : liste noire (`status !== 'livre'`) ou liste blanche de statuts « actifs » ?
3. **Ajouter `GET /deliveries/{id}`** : premier besoin fonctionnel minimal pour un vrai système de suivi.
4. **Introduire versioning d'API** (`/api/v1/deliveries`) avant tout changement de format de réponse.
5. **Fournir bootstrap HTTP réel** (`src/Kernel.php`, `public/index.php`) et valider les routes end-to-end.
