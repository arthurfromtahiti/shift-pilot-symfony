# Fonctionnel — Audit

> Confiance : high

## Compréhension globale

Le projet implémente exactement deux fonctionnalités : la liste complète des livraisons inter-îles et la liste des livraisons non encore livrées. Les données sont statiques et fictives (3 livraisons codées en dur). L'application est fonctionnellement cohérente pour son périmètre de pilote déclaré, mais incomplète comme outil métier réel : aucune opération de création, modification, suppression, ni consultation par identifiant n'est implémentée.

## Résumé exécutif

Les deux endpoints implémentés font ce qu'ils déclarent. La cohérence fonctionnelle est bonne au regard du périmètre pilote. Deux points méritent attention d'un tech lead : (1) la définition de « en attente » par exclusion négative (`status !== 'livre'`) est une règle implicite et ouverte qui inclura silencieusement tout nouveau statut non prévu lors d'une extension du modèle — risque fonctionnel concret identifié dès la conception ; (2) le champ `etaDays` n'est pas lié au statut par une contrainte d'intégrité : une livraison `en_transit` avec `etaDays:0` serait retournée dans `pending` sans avertissement. L'application ne répond qu'au besoin de consultation en lecture et est explicitement déclarée comme pilote dans `README.md:3` et `composer.json:3`.

## Constats détaillés

`VÉRIFIÉ_CODE` : `GET /deliveries` retourne la constante `DELIVERIES` intacte sans filtre, sans tri, sans pagination (`src/Controller/DeliveryController.php:22`) — 3 livraisons, réponse identique à chaque appel. Fonctionnellement correct pour le cas d'usage « lister toutes les livraisons connues du système ».

`VÉRIFIÉ_CODE` : `GET /deliveries/pending` retourne `array_values(array_filter(DELIVERIES, fn => $d['status'] !== 'livre'))` (`src/Controller/DeliveryController.php:28-31`). Le filtre est purement négatif : toute livraison dont le statut n'est pas la chaîne littérale `'livre'` est considérée « en attente ». Avec les données actuelles, Bora Bora (en_transit, etaDays:3) et Huahine (en_transit, etaDays:5) sont retournées ; Moorea (livre, etaDays:0) est exclue — résultat correct et confirmé par le test `testPendingExcludesDelivered` (`tests/DeliveryControllerTest.php:18-27`).

`VÉRIFIÉ_CODE` : Aucune opération de mutation n'est implémentée — aucun endpoint `POST /deliveries`, `PUT /deliveries/{id}`, `PATCH /deliveries/{id}/status`, `DELETE /deliveries/{id}` (`src/Controller/DeliveryController.php:1-34`). Le système est strictement en lecture.

`VÉRIFIÉ_CODE` : Aucun endpoint `GET /deliveries/{id}` pour consulter une livraison individuelle par son identifiant (`src/Controller/DeliveryController.php:1-34`). Un client qui veut le détail d'une livraison précise doit charger l'intégralité de la liste et filtrer côté client.

`VÉRIFIÉ_CODE` : Pas de pagination, pas de tri, pas de filtrage paramétrique (`?island=`, `?status=`, `?limit=`) sur les deux endpoints (`src/Controller/DeliveryController.php:19-23`). Sur 3 livraisons statiques, aucun problème. Sur un vrai catalogue de livraisons inter-îles à forte volumétrie, ce serait une limitation fonctionnelle et de performance.

`VÉRIFIÉ_CODE` : Aucune documentation d'API (pas de fichier OpenAPI/Swagger, pas de commentaires PHPDoc sur les méthodes), aucun versioning de route (`/api/v1/`) (`src/Controller/DeliveryController.php:19,25` — routes directes `/deliveries` et `/deliveries/pending`).

`HYPOTHÈSE` : La règle de filtrage `status !== 'livre'` est ouverte. Si un statut `annule` ou `expire` est introduit, les livraisons portant ce statut apparaîtront automatiquement dans `pending` sans modification du code. Selon l'intention métier, ces statuts terminaux devraient peut-être être exclus. Cette ambiguïté mérite une décision explicite avant tout ajout de statut.

## Forces

- Fonctionnalités implémentées cohérentes avec la description du projet pilote (lecture seule, suivi de l'état d'acheminement)
- `array_values` dans `pending()` garantit un tableau JSON correct même avec filtrage intermédiaire — détail d'implémentation correct
- Périmètre honnêtement documenté : le README et le `composer.json` déclarent explicitement la nature de pilote (`README.md:3`, `composer.json:3`)

## Dettes techniques

- Aucune opération de mutation : complétude fonctionnelle nulle en dehors du pilote — premier blocage si le projet doit devenir opérationnel
- Aucun endpoint de consultation par identifiant (`GET /deliveries/{id}`)
- Règle de filtrage de `pending()` implicite et non documentée : `status !== 'livre'` ouverte à extension silencieuse (`src/Controller/DeliveryController.php:30`)
- Pas de versioning d'API : tout ajout de route sans versioning rendra la rétrocompatibilité impossible à garantir
- Pas de documentation d'API

## Zones critiques

- La règle de filtrage `status !== 'livre'` dans `pending()` est la zone fonctionnelle la plus risquée du code : implicite, non documentée, extensible silencieusement. Un senior examinerait l'intention derrière ce choix (liste noire vs liste blanche de statuts) avant tout ajout d'un nouveau statut — `src/Controller/DeliveryController.php:30`

## Risques

- Extension silencieuse du filtre `pending` : tout nouveau statut non `livre` apparaît dans `pending` sans que le code ne change — risque fonctionnel élevé lors de l'extension du modèle de statuts. Scénario concret : ajout d'un statut `annule` pour les livraisons annulées → les livraisons annulées s'affichent dans le flux « en attente » sans avertissement.
- Incohérence `etaDays`/`status` non contrôlée : une livraison `en_transit` avec `etaDays:0` est logiquement incohérente (ETA atteint mais non marquée livrée) et serait retournée par `pending()` sans indication d'anomalie — risque de fausses données dans le flux de travail des opérateurs

## Recommandations priorisées

1. Documenter explicitement la règle de filtrage de `pending()` et décider si c'est une liste noire (`!= 'livre'`) ou une liste blanche de statuts actifs (`in ['en_transit', ...]`) — `src/Controller/DeliveryController.php:30` ; cette décision doit précéder tout ajout de statut
2. Si le pilote évolue vers un outil opérationnel : ajouter `GET /deliveries/{id}` comme premier endpoint supplémentaire, avant toute opération de mutation — il est le prérequis fonctionnel le plus basique
3. Introduire un versioning de route (`/api/v1/deliveries`) dès le premier endpoint supplémentaire, pour ne pas verrouiller les clients existants sur des URLs non versionnées

## Questions ouvertes

- La « liste des livraisons en attente » doit-elle inclure les livraisons annulées ou expirées, ou seulement les livraisons actives en cours d'acheminement ? La réponse détermine si `status !== 'livre'` est suffisant ou si une liste blanche de statuts actifs est nécessaire.
- `etaDays` est-il calculé dynamiquement depuis une date d'expédition ou saisi manuellement ? Si calculé : qui le calcule, quand, et quel est le référentiel de dates ?
- Y a-t-il un besoin d'un endpoint `GET /deliveries/{id}` dans la roadmap immédiate ? Il est fonctionnellement manquant même pour un pilote.
- Le format de réponse (`{id, island, status, etaDays}`) est-il stabilisé ou susceptible d'évoluer (ajout de `carrier`, `recipient`, `departureDate`) ? La réponse conditionne la nécessité d'un versioning d'API précoce.
