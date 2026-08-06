# Fonctionnel — Audit

> Confiance : high

## Compréhension globale

Le projet implémente cinq fonctionnalités : quatre en lecture (la liste complète des livraisons inter-îles avec filtrage optionnel par île via `?island=` et par délai via `?maxEtaDays=`, la consultation d'une livraison par identifiant, la liste des livraisons non encore livrées, et leur comptage) et une de mutation (`PATCH /deliveries/{id}` pour faire évoluer le statut et/ou `etaDays`). Les données de départ sont fictives (3 livraisons codées en dur), mutables intra-requête via PATCH, mais éphémères : elles se réinitialisent à chaque nouvelle instance (chaque requête Symfony). L'application est fonctionnellement cohérente pour son périmètre de pilote déclaré.

## Résumé exécutif

Les cinq endpoints implémentés font ce qu'ils déclarent. La cohérence fonctionnelle est bonne au regard du périmètre pilote. Deux points méritent attention d'un tech lead : (1) la définition de « en attente » par exclusion négative (`status !== 'livre'`) est une règle implicite et ouverte qui inclura silencieusement tout nouveau statut non prévu lors d'une extension du modèle — risque fonctionnel concret identifié dès la conception ; (2) le champ `etaDays` n'est pas lié au statut par une contrainte d'intégrité : une livraison `en_transit` avec `etaDays:0` serait retournée dans `pending` sans avertissement. L'application est explicitement déclarée comme pilote dans `README.md:3` et `composer.json:3`.

## Constats détaillés

`VÉRIFIÉ_CODE` : `GET /deliveries` accepte deux paramètres optionnels de filtrage. (1) `?island=` filtre les livraisons par île, comparaison insensible à la casse (`strcasecmp`) depuis `2cb3e1d` : `?island=moorea`, `?island=MOOREA` et `?island=Moorea` renvoient tous le même résultat. (2) `?maxEtaDays=N` (entier) filtre les livraisons dont `etaDays <= N` ; valeur non numérique ignorée. Les deux filtres sont cumulables (`?island=Moorea&maxEtaDays=5`). Sans paramètre, les 3 livraisons sont retournées intactes.

`VÉRIFIÉ_CODE` : `GET /deliveries/{id}` retourne la livraison correspondant à l'identifiant ou une erreur 404 si l'identifiant est inconnu (`src/Controller/DeliveryController.php` — route avec contrainte `\d+`). Couvert par `testShowReturnsDeliveryById` et `testShowReturns404ForUnknownId`.

`VÉRIFIÉ_CODE` : `GET /deliveries/pending` retourne `array_values(array_filter(DELIVERIES, fn => $d['status'] !== 'livre'))` (`src/Controller/DeliveryController.php`). Le filtre est purement négatif : toute livraison dont le statut n'est pas la chaîne littérale `'livre'` est considérée « en attente ». Avec les données actuelles, Bora Bora (en_transit, etaDays:3) et Huahine (en_transit, etaDays:5) sont retournées ; Moorea (livre, etaDays:0) est exclue — résultat correct et confirmé par le test `testPendingExcludesDelivered`.

`VÉRIFIÉ_CODE` : `GET /deliveries/pending/count` retourne le nombre de livraisons en attente sous la forme `{"count": N}`. Cohérent avec `GET /deliveries/pending` — même logique de filtre.

`VÉRIFIÉ_CODE` : `PATCH /deliveries/{id}` accepte un payload JSON avec `status` (valeurs valides : `en_transit`, `livre`) et/ou `etaDays` (entier ≥ 0). La méthode `update()` valide le payload (`array_key_exists`, `in_array strict`, `is_int + ≥ 0`), applique la mutation par référence sur `$this->deliveries`, et retourne 200 avec la livraison mise à jour, 400 en cas de validation échouée, ou 404 si l'identifiant est inconnu. Les modifications sont effectives intra-requête (ex. `pendingCount` diminue après un passage à `livre`), mais éphémères : `$this->deliveries` est réinitialisé depuis `DEFAULT_DELIVERIES` à chaque instanciation du contrôleur (chaque requête Symfony crée une instance fraîche). Aucun endpoint `POST /deliveries`, `PUT /deliveries/{id}`, `DELETE /deliveries/{id}` n'est implémenté.

`VÉRIFIÉ_CODE` : Pas de pagination, pas de tri, pas de filtrage par `?status=` ou `?limit=`. Sur 3 livraisons statiques, aucun problème. Sur un vrai catalogue de livraisons inter-îles à forte volumétrie, ce serait une limitation fonctionnelle et de performance.

`VÉRIFIÉ_CODE` : Aucune documentation d'API (pas de fichier OpenAPI/Swagger, pas de commentaires PHPDoc sur les méthodes), aucun versioning de route (`/api/v1/`).

`HYPOTHÈSE` : La règle de filtrage `status !== 'livre'` est ouverte. Si un statut `annule` ou `expire` est introduit, les livraisons portant ce statut apparaîtront automatiquement dans `pending` sans modification du code. Selon l'intention métier, ces statuts terminaux devraient peut-être être exclus. Cette ambiguïté mérite une décision explicite avant tout ajout de statut.

## Forces

- Fonctionnalités implémentées cohérentes avec la description du projet pilote (lecture seule, suivi de l'état d'acheminement)
- `array_values` dans `pending()` garantit un tableau JSON correct même avec filtrage intermédiaire — détail d'implémentation correct
- Périmètre honnêtement documenté : le README et le `composer.json` déclarent explicitement la nature de pilote (`README.md:3`, `composer.json:3`)

## Dettes techniques

- Mutations éphémères non persistées : les changements via `PATCH` sont perdus entre requêtes (propriété d'instance réinitialisée) — acceptable pour le pilote, bloquant pour un usage opérationnel réel
- Règle de filtrage de `pending()` implicite et non documentée : `status !== 'livre'` ouverte à extension silencieuse
- Pas de versioning d'API : tout ajout de route sans versioning rendra la rétrocompatibilité impossible à garantir
- Pas de documentation d'API
- Pas de filtrage par `?status=` ni pagination

## Zones critiques

- La règle de filtrage `status !== 'livre'` dans `pending()` est la zone fonctionnelle la plus risquée du code : implicite, non documentée, extensible silencieusement. Un senior examinerait l'intention derrière ce choix (liste noire vs liste blanche de statuts) avant tout ajout d'un nouveau statut — `src/Controller/DeliveryController.php:30`

## Risques

- Extension silencieuse du filtre `pending` : tout nouveau statut non `livre` apparaît dans `pending` sans que le code ne change — risque fonctionnel élevé lors de l'extension du modèle de statuts. Scénario concret : ajout d'un statut `annule` pour les livraisons annulées → les livraisons annulées s'affichent dans le flux « en attente » sans avertissement.
- Incohérence `etaDays`/`status` non contrôlée : une livraison `en_transit` avec `etaDays:0` est logiquement incohérente (ETA atteint mais non marquée livrée) et serait retournée par `pending()` sans indication d'anomalie — risque de fausses données dans le flux de travail des opérateurs

## Recommandations priorisées

1. Documenter explicitement la règle de filtrage de `pending()` et décider si c'est une liste noire (`!= 'livre'`) ou une liste blanche de statuts actifs (`in ['en_transit', ...]`) — cette décision doit précéder tout ajout de statut
2. Introduire un versioning de route (`/api/v1/deliveries`) avant d'ajouter des endpoints supplémentaires, pour ne pas verrouiller les clients existants sur des URLs non versionnées
3. Ajouter un filtrage par `?status=` si le pilote évolue vers un usage opérationnel

## Questions ouvertes

- La « liste des livraisons en attente » doit-elle inclure les livraisons annulées ou expirées, ou seulement les livraisons actives en cours d'acheminement ? La réponse détermine si `status !== 'livre'` est suffisant ou si une liste blanche de statuts actifs est nécessaire.
- `etaDays` est-il calculé dynamiquement depuis une date d'expédition ou saisi manuellement ? Si calculé : qui le calcule, quand, et quel est le référentiel de dates ?
- Le format de réponse (`{id, island, status, etaDays}`) est-il stabilisé ou susceptible d'évoluer (ajout de `carrier`, `recipient`, `departureDate`) ? La réponse conditionne la nécessité d'un versioning d'API précoce.
