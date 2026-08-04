# WORKFLOW_LIVRAISONS_EN_ATTENTE — Consulter les livraisons en attente d'acheminement

## Classification
- **Type** : `api_flow`
- **Sous-type** : lecture filtrée, réponse JSON statique
- **Visibilité** : `unknown` — endpoint public, aucune authentification visible dans le code
- **Acteur principal** : client HTTP
- **Acteurs** : client HTTP (consommateur de l'API) ; `DeliveryController` (filtrage et réponse)
- **Criticité** : Basse — filtre en mémoire sur constante statique, aucune persistance, aucun effet de bord
- **Confiance** : high
- **Justification** : le contrôleur a été lu en intégralité (`src/Controller/DeliveryController.php`, 34 lignes) ; la logique de filtrage est visible sur 5 lignes (`src/Controller/DeliveryController.php:27-32`). Le test unitaire couvrant cet endpoint a été lu (`tests/DeliveryControllerTest.php:18-27`). Toutes les affirmations ci-dessous sont `VÉRIFIÉ_CODE`.

## Objectif
Permettre à un consommateur d'API d'obtenir les livraisons dont le champ `status` est différent de `'livre'` — c'est-à-dire toutes celles que le code ne marque pas encore comme livrées (`src/Controller/DeliveryController.php:30`). L'interprétation métier de ce sous-ensemble (« en cours d'acheminement », « plan de charge actif ») ne peut pas être déduite du seul code : le filtre est purement `status !== 'livre'`, et toute valeur de statut autre que `'livre'` — y compris des valeurs futures comme `annule` ou `retarde` — serait incluse.

## Acteurs
- **Client HTTP** : tout consommateur capable d'émettre un `GET /deliveries/pending`
- **DeliveryController** (`src/Controller/DeliveryController.php`) : filtre en mémoire et construit la réponse ; aucune injection de dépendances

## Points d'entrée
- `GET /deliveries/pending` — déclaré via attribut PHP `#[Route('/deliveries/pending', methods: ['GET'])]` (`src/Controller/DeliveryController.php:25`) ; découverte automatique par Symfony via `config/routes.yaml:1-3`

## Étapes principales
1. **Réception de la requête** : Symfony route `GET /deliveries/pending` vers `DeliveryController::pending()` (`src/Controller/DeliveryController.php:26`).
2. **Filtrage en mémoire** : `array_filter(self::DELIVERIES, fn(array $d) => $d['status'] !== 'livre')` (`src/Controller/DeliveryController.php:28-31`) — conserve toutes les entrées dont le champ `status` est différent de la chaîne littérale `'livre'`.
3. **Réindexation du tableau** : `array_values(...)` (`src/Controller/DeliveryController.php:28`) — réinitialise les clés numériques pour éviter que le JSON résultant ne soit un objet à clés non contiguës plutôt qu'un tableau.
4. **Sérialisation et réponse** : `new JsonResponse($pending)` (`src/Controller/DeliveryController.php:32`) — HTTP 200 + `Content-Type: application/json`.

## Règles métier
- **Définition de « en attente » par exclusion négative** : une livraison est « en attente » si et seulement si `status !== 'livre'` (`src/Controller/DeliveryController.php:30`). Toute valeur de statut autre que `'livre'` est incluse dans le résultat — y compris des valeurs futures inconnues.
- **`etaDays` non utilisé comme critère de filtre** : une livraison dont `etaDays` vaut `0` mais dont le statut n'est pas `'livre'` serait incluse dans les résultats. Le filtre est purement basé sur le champ `status`.
- **Réindexation garantie** : `array_values` assure que la réponse JSON est toujours un tableau (`[]`) et non un objet (`{}`), même si des entrées intermédiaires sont filtrées (`src/Controller/DeliveryController.php:28`).

## Données
- **`DeliveryController::DELIVERIES`** (`src/Controller/DeliveryController.php:13-17`) : source de données partagée avec `WORKFLOW_LISTE_LIVRAISONS`. Avec les données actuelles, 2 livraisons sur 3 ont le statut `en_transit` et sont retournées (`id:1` Bora Bora, `id:3` Huahine) ; `id:2` Moorea (statut `livre`) est exclue — confirmé par `testPendingExcludesDelivered` (`tests/DeliveryControllerTest.php:18-27`).
- **`$pending`** (`src/Controller/DeliveryController.php:28`) : variable locale, tableau filtré et réindexé, durée de vie limitée à la requête.

## Intégrations
Aucune intégration externe explicite visible. Aucun appel à un service, une base de données ou une API tierce.

## Risques
- **Extensibilité silencieuse du filtre** : la règle `status !== 'livre'` est ouverte. Si de nouveaux statuts sont introduits (`annule`, `retarde`, `en_douane`), ils apparaîtront automatiquement dans `pending` sans modification du code — comportement potentiellement voulu (toute livraison non terminée est « en attente ») ou source de confusion si certains statuts doivent être exclus. Scénario concret : ajout d'un statut `annule` → les livraisons annulées s'affichent dans le flux « en attente ».
- **Désynchronisation `status`/`etaDays`** : aucune contrainte ne lie `etaDays` au statut. Une livraison `en_transit` avec `etaDays:0` est logiquement incohérente (ETA atteint mais pas encore marquée `livre`) et serait retournée par `pending` sans avertissement (`src/Controller/DeliveryController.php:13-17`).
- **Données figées** (partagé avec `WORKFLOW_LISTE_LIVRAISONS`) : toute transition de statut réelle requiert un redéploiement.

## Questions ouvertes
- **Critère « en attente » exhaustif ?** La règle actuelle (`!= 'livre'`) est-elle suffisante quand la liste des statuts s'élargira ? Faut-il une liste blanche de statuts « actifs » plutôt qu'une liste noire ?
- **`etaDays` et le statut sont-ils liés ?** Une règle métier devrait-elle garantir que `etaDays == 0 ↔ status == 'livre'` ? Aujourd'hui rien n'enforece cette invariante.
- **Bootstrap HTTP absent** (partagé avec `WORKFLOW_LISTE_LIVRAISONS`) : les routes sont déclarées via des attributs PHP `#[Route(...)]` dans le contrôleur (`src/Controller/DeliveryController.php:25`) et découvertes par le chargeur Symfony configuré avec `type: annotation` dans `config/routes.yaml`. Ni `src/Kernel.php` ni `public/index.php` n'existent dans ce dépôt. Le routage n'est donc testé que par instanciation directe du contrôleur (`tests/DeliveryControllerTest.php`), jamais dans le contexte d'une application Symfony réelle.

## Preuves
- `src/Controller/DeliveryController.php:25-33` — méthode `pending()` lue (attribut de route, filtrage, réindexation, réponse)
- `src/Controller/DeliveryController.php:13-17` — constante `DELIVERIES` lue (source de données)
- `config/routes.yaml:1-3` — configuration de découverte des routes lue
- `tests/DeliveryControllerTest.php:18-27` — test `testPendingExcludesDelivered` lu (assertion : 2 éléments retournés, aucun avec `status == 'livre'`)
