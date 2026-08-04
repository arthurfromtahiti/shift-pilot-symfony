# Cahier de recette — shift-pilot-symfony

> **Confiance** : high — tous les cas de test dérivent des workflows validés et du code lu. Résultats attendus basés sur le code source de `DeliveryController` et les données de la constante `DELIVERIES`.

**Responsable de test** : tester les deux endpoints GET et valider leurs réponses.

**Environnement de test** : application Symfony 5.4 bootstrapée (requiert `src/Kernel.php` et `public/index.php`, actuellement absents — voir limitations à la fin).

**Runtime** : PHP 8.1+, Symfony 5.4, PHPUnit (via `composer test`).

---

## Parcours 1 — Consulter la liste complète des livraisons

**Workflow correspondant** : `WORKFLOW_LISTE_LIVRAISONS` (validé).

### Scénario 1.1 — Récupérer toutes les livraisons

**Titre** : GET /deliveries retourne la liste complète

**Objectif** : vérifier que le client HTTP reçoit l'intégralité du catalogue de livraisons sans filtre.

**Préconditions**

- Application Symfony bootstrapée et servie (ou testée via instanciation directe du contrôleur).
- Data source : `DeliveryController::DELIVERIES` avec 3 livraisons de test (`src/Controller/DeliveryController.php:13-17`).

**Étapes**

1. Émettre une requête `GET /deliveries`.
2. Attendre la réponse HTTP.
3. Décoder le contenu JSON.
4. Valider la structure et le contenu.

**Résultat attendu**

- **Code HTTP** : 200 OK.
- **Content-Type** : `application/json`.
- **Corps JSON** : tableau contenant exactement 3 objets (pas plus, pas moins).

**Contenu exact attendu** (au moment de l'analyse) :

```json
[
  {
    "id": 1,
    "island": "Bora Bora",
    "status": "en_transit",
    "etaDays": 3
  },
  {
    "id": 2,
    "island": "Moorea",
    "status": "livre",
    "etaDays": 0
  },
  {
    "id": 3,
    "island": "Huahine",
    "status": "en_transit",
    "etaDays": 5
  }
]
```

**Validations**

- [ ] Code HTTP est 200.
- [ ] En-têtes incluent `Content-Type: application/json`.
- [ ] Contenu est un tableau JSON (démarre par `[`, finit par `]`).
- [ ] Tableau contient exactement 3 objets.
- [ ] Chaque objet contient exactement les clés `id`, `island`, `status`, `etaDays` (pas de champ surprise).
- [ ] Tous les types correspondent : `id` = entier, `island` = chaîne, `status` = chaîne, `etaDays` = entier.
- [ ] Valeurs de `id` : 1, 2, 3 (uniques, en ordre).
- [ ] Valeurs de `status` : parmi `{en_transit, livre}`.
- [ ] Valeurs de `etaDays` : non-négatives.

**Référence source**

- Déclaration de route : `src/Controller/DeliveryController.php:19`
- Implémentation : `src/Controller/DeliveryController.php:22` — `return new JsonResponse(self::DELIVERIES);`
- Test unitaire : `tests/DeliveryControllerTest.php:9-16` (`testListReturnsAllDeliveries`)

---

### Scénario 1.2 — Vérifier l'invariance des données

**Titre** : GET /deliveries retourne des données identiques à chaque appel

**Objectif** : s'assurer que les données sont figées (pas de dynamique, pas d'effet de bord).

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre une première requête `GET /deliveries`.
2. Stocker la réponse JSON.
3. Attendre quelques secondes (ou effectuer d'autres opérations).
4. Émettre une deuxième requête `GET /deliveries`.
5. Comparer les deux réponses.

**Résultat attendu**

- Les deux réponses sont strictement identiques (byte par byte, ou structure par structure après désérialisation).

**Validations**

- [ ] Réponse 1 == Réponse 2 (comparaison JSON ou byte).
- [ ] Aucun champ temporel (pas de `timestamp`, `requestId`, `generatedAt`).

**Justification**

Les données proviennent d'une constante PHP immutable (`private const DELIVERIES`) — aucun état modifiable n'existe.

---

### Scénario 1.3 — Validation de la méthode HTTP

**Titre** : Seule la méthode GET est acceptée pour /deliveries

**Objectif** : vérifier que les autres verbes HTTP retournent une erreur.

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre une requête `POST /deliveries` (avec ou sans corps).
2. Noter le code de réponse.
3. Répéter avec `PUT /deliveries`, `DELETE /deliveries`, `PATCH /deliveries`.

**Résultat attendu**

- `POST /deliveries` retourne HTTP 405 (Method Not Allowed) ou est non-routable (HTTP 404).
- `PUT /deliveries` retourne HTTP 405 ou 404.
- `DELETE /deliveries` retourne HTTP 405 ou 404.
- `PATCH /deliveries` retourne HTTP 405 ou 404.

**Validations**

- [ ] Tous les verbes autres que GET retournent ≥ 400 (erreur client) ou absence de route.

**Justification**

La route est déclarée avec l'attribut `methods: ['GET']` (`src/Controller/DeliveryController.php:19`) — Symfony rejette les autres verbes.

---

## Parcours 2 — Consulter les livraisons en attente

**Workflow correspondant** : `WORKFLOW_LIVRAISONS_EN_ATTENTE` (validé).

### Scénario 2.1 — Récupérer les livraisons dont status != 'livre'

**Titre** : GET /deliveries/pending retourne les livraisons en attente

**Objectif** : vérifier que le filtre `status !== 'livre'` fonctionne correctement.

**Préconditions**

- Application Symfony bootstrapée.
- Data source : `DeliveryController::DELIVERIES` avec les 3 livraisons de test.

**Étapes**

1. Émettre une requête `GET /deliveries/pending`.
2. Attendre la réponse HTTP.
3. Décoder le contenu JSON.
4. Valider la structure et le contenu.

**Résultat attendu**

- **Code HTTP** : 200 OK.
- **Content-Type** : `application/json`.
- **Corps JSON** : tableau contenant exactement 2 objets (les livraisons avec `status == 'en_transit'`).

**Contenu exact attendu** (au moment de l'analyse) :

```json
[
  {
    "id": 1,
    "island": "Bora Bora",
    "status": "en_transit",
    "etaDays": 3
  },
  {
    "id": 3,
    "island": "Huahine",
    "status": "en_transit",
    "etaDays": 5
  }
]
```

**Note** : l'objet avec `id: 2` (Moorea, `status: 'livre'`) est **absent** du résultat.

**Validations**

- [ ] Code HTTP est 200.
- [ ] En-têtes incluent `Content-Type: application/json`.
- [ ] Contenu est un tableau JSON.
- [ ] Tableau contient exactement 2 objets.
- [ ] Aucun objet n'a le champ `status == 'livre'` (tous les éléments ont `status == 'en_transit'`).
- [ ] Chaque objet contient exactement les clés `id`, `island`, `status`, `etaDays`.
- [ ] Tous les types correspondent (id=entier, island=chaîne, status=chaîne, etaDays=entier).
- [ ] Ordre des objets : id=1 en premier, id=3 en deuxième (réindexation correcte après filtrage).

**Référence source**

- Déclaration de route : `src/Controller/DeliveryController.php:25`
- Implémentation : `src/Controller/DeliveryController.php:28-31` — `array_filter(..., fn => $d['status'] !== 'livre')`
- Test unitaire : `tests/DeliveryControllerTest.php:18-27` (`testPendingExcludesDelivered`)

---

### Scénario 2.2 — Vérifier que la livraison livrée est bien exclue

**Titre** : GET /deliveries/pending exclut les livraisons avec status = 'livre'

**Objectif** : valider spécifiquement que Moorea (id=2, status='livre') n'apparaît **jamais** dans la réponse.

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre une requête `GET /deliveries/pending`.
2. Décoder le JSON.
3. Parcourir chaque objet du tableau.
4. Vérifier qu'aucun n'a `id == 2` ET `status == 'livre'`.

**Résultat attendu**

- Moorea (id=2) n'est pas présente dans le résultat.
- Toutes les autres livraisons (id=1, id=3) sont présentes avec leur statut inchangé.

**Validations**

- [ ] `id == 2` n'existe pas dans le tableau JSON.
- [ ] `island == 'Moorea'` n'existe pas dans le tableau JSON.

**Justification**

La règle de filtrage est `status !== 'livre'` (`src/Controller/DeliveryController.php:30`). Moorea a `status == 'livre'`, donc elle est exclue.

---

### Scénario 2.3 — Validation de la réindexation

**Titre** : Les clés du tableau filtré sont correctement réinitialisées

**Objectif** : vérifier que le JSON retournée est un tableau valide (clés numériques `0`, `1`, ...), pas un objet avec clés non contiguës.

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre une requête `GET /deliveries/pending`.
2. Décoder le JSON brut (pas parse automatique — conserver les clés).
3. Inspecter la structure des clés.

**Résultat attendu**

- Le JSON est un **tableau** (délimité par `[...]`, pas `{...}`).
- Les indices sont `0`, `1`, ... en continu (pas `0`, `2` après le filtrage de l'index `1`).

**Validations**

- [ ] JSON commence par `[` et finit par `]` (pas `{` et `}`).
- [ ] Contenu parse-t-il correctement en tableau de 2 éléments avec indices `0` et `1`.

**Justification**

La méthode utilise `array_values(...)` pour réindexer après filtrage (`src/Controller/DeliveryController.php:28`) — garantit un tableau JSON bien formé.

---

### Scénario 2.4 — Validation de la méthode HTTP

**Titre** : Seule la méthode GET est acceptée pour /deliveries/pending

**Objectif** : vérifier que les autres verbes HTTP retournent une erreur.

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre une requête `POST /deliveries/pending`.
2. Noter le code de réponse.
3. Répéter avec `PUT`, `DELETE`, `PATCH`.

**Résultat attendu**

- Tous les verbes autres que GET retournent HTTP 405 ou 404.

**Validations**

- [ ] `POST /deliveries/pending` retourne ≥ 400.
- [ ] `PUT /deliveries/pending` retourne ≥ 400.
- [ ] `DELETE /deliveries/pending` retourne ≥ 400.
- [ ] `PATCH /deliveries/pending` retourne ≥ 400.

**Justification**

La route est déclarée avec `methods: ['GET']` (`src/Controller/DeliveryController.php:25`).

---

## Parcours 3 — Format et cohérence des données

### Scénario 3.1 — Validation de la structure de chaque livraison

**Titre** : Chaque livraison contient exactement les quatre champs attendus

**Objectif** : s'assurer qu'aucun champ surprise n'est retourné, et qu'aucun champ attendu n'est absent.

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre `GET /deliveries`.
2. Décoder le JSON.
3. Pour chaque objet du tableau, énumérer les clés.
4. Comparer avec l'ensemble attendu : `{id, island, status, etaDays}`.

**Résultat attendu**

- Chaque objet contient **exactement** 4 clés.
- Les clés sont `id`, `island`, `status`, `etaDays` (l'ordre n'a pas d'importance en JSON).
- Pas de champ supplémentaire (ex. `createdAt`, `updatedAt`, `internal_flag`).
- Pas de champ absent.

**Validations**

- [ ] Chaque objet a un champ `id`.
- [ ] Chaque objet a un champ `island`.
- [ ] Chaque objet a un champ `status`.
- [ ] Chaque objet a un champ `etaDays`.
- [ ] Aucun autre champ ne figure.

**Recommandation**

Mettre en œuvre un test de schéma JSON (ex. JSONSchema validator) pour valider ce point automatiquement.

---

### Scénario 3.2 — Validation des types de données

**Titre** : Chaque champ a le type attendu

**Objectif** : vérifier que les valeurs ne sont pas sérialisées de manière inattendue (ex. `id` en chaîne au lieu d'entier).

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre `GET /deliveries`.
2. Décoder le JSON.
3. Pour chaque objet, vérifier les types de chaque champ.

**Résultat attendu**

| Champ | Type attendu | Validations |
|-------|--------------|--------------|
| `id` | Entier | > 0, unique par livraison |
| `island` | Chaîne (string) | Non vide, caractères alphanumériques/espaces |
| `status` | Chaîne (string) | Valeur parmi `{en_transit, livre}` |
| `etaDays` | Entier | ≥ 0 |

**Validations**

- [ ] `id` est de type `number` (JSON) et entier (pas décimal).
- [ ] `island` est de type `string`.
- [ ] `status` est de type `string`.
- [ ] `etaDays` est de type `number` et entier.

**Justification**

Symfony `JsonResponse` sérialise les tableaux PHP en JSON en respectant les types PHP. Vérifier que la sérialisation est correcte.

---

### Scénario 3.3 — Validation des valeurs de statut

**Titre** : Le champ status contient uniquement des valeurs attendues

**Objectif** : s'assurer que les valeurs de `status` sont limitées à l'ensemble connu (`en_transit`, `livre`).

**Préconditions** : application Symfony bootstrapée.

**Étapes**

1. Émettre `GET /deliveries`.
2. Décoder le JSON.
3. Pour chaque objet, récupérer le champ `status`.
4. Vérifier qu'il figure parmi les valeurs connues.

**Résultat attendu**

- Toutes les valeurs de `status` sont dans `{en_transit, livre}`.

**Validations**

- [ ] `status` en position 0 (Bora Bora) vaut `'en_transit'`.
- [ ] `status` en position 1 (Moorea) vaut `'livre'`.
- [ ] `status` en position 2 (Huahine) vaut `'en_transit'`.
- [ ] Aucune autre valeur (`cancelled`, `pending`, `unknown`) n'apparaît.

**Justification**

Bien que le code n'énumère pas strictement les valeurs de `status`, les données statiques de la constante ne contiennent que deux valeurs. Ce test valide que la sérialisation ne génère pas de valeur inattendue.

---


## Limitations connues du cahier de recette

### Bootstrap HTTP absent

L'application Symfony n'a pas de `src/Kernel.php` ni `public/index.php`. **Les tests d'intégrité HTTP complète (routing end-to-end, middleware) ne peuvent pas être exécutés.**

Actuellement, les tests sont unitaires (instanciation directe du contrôleur, appel des méthodes) — suffisant pour valider la logique, pas pour valider le routage et la déserialisation Symfony réelle.

**Implication** : certaines validations ci-dessus (ex. codes HTTP 405 sur les mauvais verbes) dépendent du routeur Symfony fonctionnel. Les assertions `GET /deliveries/pending` retourne 404 avec `DELETE` ne peuvent être validées sans bootstrap HTTP réel.

### Données statiques

Les données ne changeront jamais sans redéploiement du code. Les scénarios de mutation (création, modification, suppression) ne peuvent pas être testés.

### Pas d'authentification

Il n'y a aucune forme d'authentification à tester. Un futur système d'authentification requerrait des scénarios supplémentaires.

---

## Priorisation des cas de test

| Priorité | Cas | Raison |
|----------|-----|--------|
| **1 — Critique** | 1.1, 2.1 | Valident les fonctionnalités core : liste complète et filtrage. |
| **2 — Haute** | 1.2, 2.2, 3.1, 3.2, 3.3 | Valident l'intégrité des données et le respect des structures. |
| **3 — Moyenne** | 2.3 | Valident les détails d'implémentation (réindexation). |
| **4 — Basse** | 1.3, 2.4 | Valident les protections HTTP (secondaires). |

---

## Commandes de test

Exécution des tests unitaires existants :

```bash
composer test
# Exécute : phpunit tests/
```

**Note** : les tests existants (`DeliveryControllerTest.php`) couvrent les scénarios 1.1 et 2.1 (retour de données correctes). Les autres scénarios (validation de type, de structure, validation des verbes HTTP) requerraient des tests supplémentaires ou une infrastructure HTTP bootstrapée.

---

## Synthèse du cahier de recette

| Parcours | Objectif | Scénarios | Statut |
|----------|----------|-----------|--------|
| **Parcours 1** | Lister toutes les livraisons | 3 scénarios | Validable via tests unitaires + HTTP |
| **Parcours 2** | Filtrer les livraisons en attente | 4 scénarios | Validable via tests unitaires + HTTP |
| **Parcours 3** | Valider l'intégrité des données | 3 scénarios | Validable via tests unitaires + HTTP |

**Total** : 10 scénarios de test couvrant les axes fonctionnel, structurel et de validation des données.
