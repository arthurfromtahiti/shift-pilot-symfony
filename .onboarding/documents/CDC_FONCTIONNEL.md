# Cahier des charges fonctionnel — shift-pilot-symfony

> **Confiance** : high — tous les constats sont `VÉRIFIÉ_CODE` ou dérivent de workflows validés.

## Contexte métier

**Domaine** : suivi de l'état d'acheminement des frets inter-îles (Polynésie française).

**Besoin** : permettre à un client HTTP ou un système tiers de consulter la liste des livraisons connues du système, d'identifier celles qui sont en cours d'acheminement (vs déjà livrées), et d'en inspecter les délais estimés.

**État actuel** : pilote de démonstration Paperclip/SHIFT. Données fictives, aucune persistance, scope de lecture seule.

## Acteurs et capacités

### Client HTTP (consommateur d'API)

- **Rôle** : navigateur, application tierce, outil de test, intégration serveur-à-serveur — tout système capable d'émettre des requêtes HTTP GET.
- **Peut faire** :
  - Récupérer la liste complète des livraisons connues du système.
  - Récupérer la sous-liste des livraisons dont l'acheminement n'est pas encore terminé.
  - Consulter une livraison unique par son identifiant.
  - Interpréter les champs `id`, `island`, `status`, `etaDays` de chaque livraison.
  - Faire évoluer le statut et/ou `etaDays` d'une livraison existante via `PATCH /deliveries/{id}`.
- **Ne peut pas faire** :
  - Créer ou supprimer une livraison.
  - S'authentifier — accès public, sans restriction de sécurité.
  - Filtrer, trier ou paginer les résultats.
  - Persister les modifications entre requêtes (les mutations via PATCH sont éphémères : l'état est réinitialisé à chaque nouvelle requête).

### Système applicatif

- **Composant** : `DeliveryController` (`src/Controller/DeliveryController.php`).
- **Responsabilité** : exposer les deux endpoints GET et retourner des réponses JSON sérialisées.
- **Dépendances** : aucune — pas de service, pas de base de données, pas de cache, pas de système externe.

## Fonctionnalités — Parcours utilisateur

### 1. Consulter la liste complète des livraisons

**Objectif** : un client HTTP récupère en une seule requête le catalogue complet des livraisons inter-îles.

**Acteur** : client HTTP (consommateur d'API).

**Point d'entrée** : `GET /deliveries` (`src/Controller/DeliveryController.php:30`).

**Étapes**

1. Le client émet une requête HTTP `GET /deliveries`.
2. Symfony route le requête vers `DeliveryController::list()` (`src/Controller/DeliveryController.php:30`).
3. La méthode applique les filtres optionnels `island` et `maxEtaDays`, puis retourne les livraisons filtrées (`src/Controller/DeliveryController.php:33-51`).
4. Symfony sérialise le tableau en JSON et retourne HTTP 200 avec `Content-Type: application/json`.

**Résultat attendu** : tableau JSON contenant toutes les livraisons, chacune avec les champs `id`, `island`, `status`, `etaDays`.

Exemple de réponse (données actuelles) :

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

**Règles métier**

- **Filtrage optionnel par île** : le paramètre `?island=` (insensible à la casse) réduit la réponse aux livraisons destinées à cette île (`src/Controller/DeliveryController.php:34, 49`, depuis PR#7 commit `d12c873`). Absent → toutes les îles retournées.
- **Filtrage optionnel par délai maximal** : le paramètre `?maxEtaDays=N` (entier ≥ 0) réduit la réponse aux livraisons dont `etaDays <= N` (`src/Controller/DeliveryController.php:35, 40-42, 50`, depuis PR#8 commit `40a273c`). Valeur non numérique → retourne HTTP 400 avec `{"error":"maxEtaDays invalide"}`. Absent → aucune limite sur ETA.
- **Combinaison des filtres** : les deux filtres sont cumulables (`?island=Moorea&maxEtaDays=5` filtre d'abord par île, puis par ETA). Les deux peuvent être absents (catalogue exhaustif retourné).
- **Données mutables intra-requête, éphémères entre requêtes** : depuis PR#9, les données de départ (`DEFAULT_DELIVERIES`) sont copiées dans la propriété d'instance `$this->deliveries` à chaque instanciation du contrôleur (`src/Controller/DeliveryController.php:24-26`). Le endpoint `PATCH /deliveries/{id}` peut modifier cette copie pour la durée de la requête (ex. le `pendingCount` reflète immédiatement la mise à jour). En revanche, chaque nouvelle requête Symfony crée une instance fraîche du contrôleur : les modifications ne sont pas persistées entre requêtes.
- **Pas de versioning de réponse** : la route est `/deliveries` (pas `/api/v1/deliveries`). Tout changement du format JSON (ajout/suppression de champs, renommage) affectera les clients existants sans avertissement.

### 2. Consulter les livraisons en attente d'acheminement

**Objectif** : un client HTTP récupère uniquement les livraisons dont le statut ne marque pas une livraison complétée — c'est-à-dire toutes celles pour lesquelles un acheminement est encore en cours ou attendu.

**Acteur** : client HTTP (consommateur d'API).

**Point d'entrée** : `GET /deliveries/pending` (`src/Controller/DeliveryController.php:56-57`).

**Étapes**

1. Le client émet une requête HTTP `GET /deliveries/pending`.
2. Symfony route la requête vers `DeliveryController::pending()` (`src/Controller/DeliveryController.php:56`).
3. La méthode applique un filtre en mémoire : `array_filter($this->deliveries, fn(array $d) => $d['status'] !== 'livre')` (`src/Controller/DeliveryController.php:59-62`).
4. La méthode réindexe le tableau filtré : `array_values(...)` (`src/Controller/DeliveryController.php:59`), pour que le JSON résultant soit un tableau JSON et non un objet avec des clés non contiguës.
5. La réponse est sérialisée en JSON et retournée avec HTTP 200.

**Résultat attendu** : tableau JSON contenant uniquement les livraisons dont le champ `status` est différent de `'livre'`.

Exemple de réponse (données actuelles) :

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

Note : Moorea (status `livre`) est exclue du résultat.

**Règles métier**

- **Définition de « en attente » par exclusion du statut terminal** : une livraison est « en attente » si et seulement si `status !== 'livre'` (`src/Controller/DeliveryController.php:56-57`). Toute valeur de statut autre que la chaîne littérale `'livre'` qualifie la livraison comme « en attente ».
- **Filtre extensible silencieusement** : si de nouveaux statuts sont introduits dans `DELIVERIES` (ex. `annule`, `retarde`, `en_douane`), ils apparaîtront automatiquement dans la réponse `pending()` sans modification du code. Cette extensibilité silencieuse peut être intentionnelle (« toute livraison non terminée ») ou source de confusion (« les livraisons annulées doivent-elles figurer dans `pending()` ? »).
- **Champ `etaDays` non utilisé comme critère** : une livraison dont `etaDays:0` mais dont `status !== 'livre'` est incluse dans les résultats. Le filtre ne repose que sur le champ `status`.
- **Réindexation garantie** : le client reçoit toujours un tableau JSON (`[]`) bien formé, jamais un objet (`{}`), même si le filtrage réduit le nombre de résultats à zéro.

## Données — Modèle métier

### Entité : Livraison

**Modèle physique** : constante PHP `DeliveryController::DEFAULT_DELIVERIES` (`src/Controller/DeliveryController.php:14-18`), copiée dans la propriété d'instance `$this->deliveries` à chaque instanciation.

**Modèle logique** (point de vue métier) :

| Champ | Type | Signification | Contraintes actuelles | Risques |
|-------|------|---------------|----------------------|---------|
| `id` | entier | Identifiant unique de la livraison | Valeur unique implicite | Pas d'index, pas de contrainte d'unicité enforced au code |
| `island` | chaîne | Île de destination du fret | Valeur parmi les îles connues de Polynésie française | Pas de validation énumérée ; n'importe quelle chaîne est acceptée |
| `status` | chaîne | État d'acheminement de la livraison | Valeurs observées : `en_transit`, `livre` | Valeurs non énumérées ; nouvelle valeur introduite silencieusement affecte `pending()` |
| `etaDays` | entier | Nombre de jours estimés avant livraison | Inféré des données : ≥ 0 ; `livre` ↔ `etaDays:0` (enforced depuis PR#12 — SHIA-409) | Rejet HTTP 400 si `status == 'livre'` et `etaDays > 0` ; une livraison `en_transit` avec `etaDays:0` reste techniquement acceptée (règle asymétrique) |

### Données actuelles

```php
private const DEFAULT_DELIVERIES = [
    ['id' => 1, 'island' => 'Bora Bora', 'status' => 'en_transit', 'etaDays' => 3],
    ['id' => 2, 'island' => 'Moorea', 'status' => 'livre', 'etaDays' => 0],
    ['id' => 3, 'island' => 'Huahine', 'status' => 'en_transit', 'etaDays' => 5],
];
```

**Caractéristiques**

- 3 livraisons codées en dur comme données de départ.
- Structure plate : pas d'imbrication, pas de tableaux d'objets imbriqués.
- Données fictives : îles réelles (Polynésie française) mais statut et ETA imaginaires.
- Mutabilité intra-requête : copiées dans `$this->deliveries` à l'instanciation, modifiables via `PATCH /deliveries/{id}` pour la durée de la requête ; l'état de départ est restauré à chaque nouvelle requête.

## Exigences métier

### Exigence 1 : Liste complète exhaustive

**Énoncé** : un client HTTP peut récupérer l'intégralité du catalogue de livraisons inter-îles en une seule requête `GET /deliveries`.

**Preuve** : `src/Controller/DeliveryController.php:33-51` retourne les livraisons filtrées par île et maxEtaDays. Test vérifié : `tests/DeliveryControllerTest.php:9-16` (`testListReturnsAllDeliveries`) affirme que 3 livraisons sont retournées.

**Critère d'acceptation** : l'endpoint retourne HTTP 200 avec un tableau JSON contenant exactement le contenu de la constante.

---

### Exigence 2 : Filtrage des livraisons en attente

**Énoncé** : un client HTTP peut récupérer uniquement les livraisons dont le statut n'est pas `'livre'` en une seule requête `GET /deliveries/pending`.

**Preuve** : `src/Controller/DeliveryController.php:59-63` applique `array_filter(..., fn => $d['status'] !== 'livre')`. Test vérifié : `tests/DeliveryControllerTest.php:18-27` (`testPendingExcludesDelivered`) affirme que 2 livraisons sont retournées et qu'aucune n'a le statut `'livre'`.

**Critère d'acceptation** : l'endpoint retourne HTTP 200 avec un tableau JSON contenant uniquement les livraisons dont `status !== 'livre'`.

---

### Exigence 3 : Structure de réponse stable

**Énoncé** : chaque livraison dans la réponse JSON expose exactement les quatre champs `id`, `island`, `status`, `etaDays`, sans champ supplémentaire implicite.

**Preuve** : la constante `DEFAULT_DELIVERIES` (`src/Controller/DeliveryController.php:14-18`) définit la structure plate ; `JsonResponse` sérialise directement sans transformation.

**Critère d'acceptation** : chaque objet JSON de livraison contient exactement ces quatre clés, dans n'importe quel ordre.

---

### Exigence 4 : Endpoints HTTP accessibles et publics

**Énoncé** : les deux routes sont accessibles sans authentification et répondent uniquement aux méthodes HTTP GET.

**Preuve** : attributs PHP `#[Route('/deliveries', methods: ['GET'])]` et `#[Route('/deliveries/pending', methods: ['GET'])]` (`src/Controller/DeliveryController.php:30,56`) ; aucune middleware d'authentification ; découverte automatique via `config/routes.yaml:1-3`.

**Critère d'acceptation** : `GET /deliveries` et `GET /deliveries/pending` retournent HTTP 200 ; `POST`, `PUT`, `PATCH`, `DELETE` retournent HTTP 405 (Method Not Allowed) ou sont non routable.

---

## Risques métier

### Risque 1 : Extension silencieuse du filtre `pending()`

**Description** : la règle `status !== 'livre'` est une exclusion négative ouverte à extension. Tout nouveau statut introduit dans `DELIVERIES` (ex. `annule`, `retarde`, `en_douane`, `expire`) apparaîtra silencieusement dans `pending()` sans modification du code.

**Impact** : scénario concret — un opérateur de livraisons voit soudain des livraisons annulées ou expirées s'afficher dans le tableau de bord « en attente » après un déploiement contenant un changement de données uniquement. Confusion potentielle : la règle métier sous-jacente (liste noire vs liste blanche de statuts) n'a jamais été explicite.

**Mitigation actuelle** : aucune — le code n'est pas documenté, pas de test de règles de statut.

**Recommandation** : documenter explicitement avant tout ajout de statut. Décider : est-ce une liste noire (`status !== 'livre'`) suffisante, ou faut-il une liste blanche (`status in ['en_transit', ...]`) ?

**Source** : `src/Controller/DeliveryController.php:56-57`.

---

### Risque 2 : Incohérence `etaDays` / `status` — partiellement mitigée depuis PR#12

**Description** : l'invariante `(status == 'livre') → (etaDays == 0)` est enforced depuis PR#12 (SHIA-409) : PATCH /deliveries/{id} retourne HTTP 400 si le statut résultant est `livre` et que `etaDays > 0`. Le sens inverse (`etaDays == 0` n'implique pas `status == 'livre'`) n'est pas contrôlé — une livraison `en_transit` avec `etaDays:0` reste techniquement acceptée.

**Impact** : risque réduit pour le cas le plus visible (livraison marquée livrée avec ETA non nul). Le cas résiduel (`en_transit` + `etaDays:0`) reste une anomalie de données possible.

**Mitigation actuelle** : rejet 400 enforced par `src/Controller/DeliveryController.php:update()` depuis PR#12 ; 4 tests de non-régression dans `tests/DeliveryControllerTest.php` (`testUpdateStatusToLivre`, `testUpdateStatusToLivreResetEtaDaysToZero`, `testUpdateStatusToLivreWithPositiveEtaDaysReturns400`, `testUpdateEtaDaysToPositiveOnLivreDeliveryReturns400`).

**Recommandation** : évaluer si l'invariante réciproque (`etaDays == 0` → `status == 'livre'`) doit être enforced (PATCH sur `en_transit` avec `etaDays:0` devrait-il être rejeté ou accepté ?).

**Source** : `src/Controller/DeliveryController.php:14-18` (données) ; `src/Controller/DeliveryController.php:update()` (validation PR#12).

---

### Risque 3 : Mutations éphémères — persistance impossible sans base de données

**Description** : `PATCH /deliveries/{id}` permet de modifier le statut et `etaDays` d'une livraison intra-requête, mais ces changements sont perdus dès que la requête se termine. La propriété `$this->deliveries` est réinitialisée depuis `DEFAULT_DELIVERIES` à chaque nouvelle instanciation du contrôleur (chaque requête HTTP). Aucune base de données ni couche de persistance n'est présente.

**Impact** : un opérateur qui marque une livraison comme `livre` via PATCH verra l'état revenir à `en_transit` à la requête suivante. Le PATCH est fonctionnel pour démontrer la logique, mais sans effet durable.

**Mitigation actuelle** : ce pilote n'est pas opérationnel — c'est le comportement attendu d'une démonstration.

**Recommandation** : si le projet doit devenir opérationnel, introduire une base de données et une couche de persistance (Doctrine ORM) comme prérequis fondamental.

**Source** : `src/Controller/DeliveryController.php:24-26` (constructeur et propriété `$deliveries`).

---

### Risque 4 : Pas de versioning d'API

**Description** : les routes sont `/deliveries` et `/deliveries/pending` sans numérotation de version (`/api/v1/`). Tout changement du format de réponse (ajout/suppression de champ, renommage) affectera les clients existants sans période de transition.

**Impact** : rupture de compatibilité non gérée ; clients cassés lors d'une évolution.

**Mitigation actuelle** : acceptable pour un pilote ; deviendrait un problème si l'API a des clients productifs.

**Recommandation** : introduire un versioning (`/api/v1/deliveries`) avant le premier changement de format de réponse.

**Source** : `src/Controller/DeliveryController.php:30,56`.

---

### Risque 5 : Pas de contrôle d'accès

**Description** : endpoints accessibles publiquement, sans authentification ni autorisation.

**Impact** : acceptable pour des données fictives de pilote ; risque de confidentialité si les livraisons contiennent un jour des adresses ou destinataires réels.

**Mitigation actuelle** : données fictives, contexte de pilote.

**Recommandation** : introduire une couche d'authentification (`API key`, `JWT`, `OAuth2`) avant d'exposer des données réelles en production.

**Source** : `src/Controller/DeliveryController.php:30,56` — aucune middleware de sécurité.

---

## Points de divergence de la sémantique métier

### Question 1 : Statuts exhaustifs ?

Les données actuelles ne contiennent que `en_transit` et `livre`. La liste réelle des statuts métier est-elle plus large ? Candidats potentiels : `preparation`, `en_douane`, `retarde`, `annule`, `expire`. La réponse affecte l'interprétation de `pending()`.

**Preuve manquante** : aucun fichier `README.md` ou documentation métier ne précise cette liste.

---

### Question 2 : Liaison `etaDays` / `status` ?

`etaDays` est-il calculé dynamiquement (ex. depuis une date de départ enregistrée) ou saisi manuellement et figé ? Une livraison `en_transit` avec `etaDays:0` est-elle une anomalie ou logiquement cohérente (le délai a expiré mais le statut n'a pas été mis à jour) ?

**Preuve manquante** : aucun commentaire dans le code, pas de test d'invariant.

---

### Question 3 : Consultation par ID ?

Un endpoint `GET /deliveries/{id}` est-il un besoin fonctionnel, même pour ce pilote ? Aujourd'hui, impossible de récupérer une livraison unique sans charger la liste entière.

**Preuve manquante** : absence de cet endpoint, pas de demande métier.

---

## Hors périmètre (fonctionnalités conscemment absentes)

- Créer une livraison (`POST /deliveries`)
- Remplacer intégralement une livraison (`PUT /deliveries/{id}`)
- Supprimer une livraison (`DELETE /deliveries/{id}`)
- Filtrer par statut (`?status=`)
- Trier les résultats (`?sort=`)
- Paginer les résultats (`?limit=`, `?offset=`)
- Authentification ou autorisation
- Cache, compression, ou optimisation de bande passante

**Note** : les filtres `?island=` (PR#7) et `?maxEtaDays=` (PR#8) ont été implémentés et ne sont plus absents du pilote. Les capacités ci-dessus restent volontairement absentes et deviendraient des exigences prioritaires si le projet doit devenir un vrai système de suivi opérationnel.

---

## Couverture de test

| Cas de test | Source | Couverture | Résultat observé | Confiance |
|-------------|--------|-----------|------------------|-----------|
| `list()` retourne 3 livraisons | `tests/DeliveryControllerTest.php:9-16` | Test unitaire (`testListReturnsAllDeliveries`) | `$response->getContent()` décodé contient 3 éléments | High (code lu) |
| `pending()` retourne 2 livraisons | `tests/DeliveryControllerTest.php:18-27` | Test unitaire (`testPendingExcludesDelivered`) | `$response->getContent()` décodé contient 2 éléments sans aucun avec `status == 'livre'` | High (code lu) |
| Aucun test d'intégration HTTP | — | Absent | Bootstrap Symfony (`Kernel.php`, `public/index.php`) manquant | N/A |
| Aucun test de validation de sortie | — | Absent | Pas de test vérifiant l'absence de champ surprise dans la réponse | Low |
| Invariante `etaDays` / `status` (sens `livre` → `etaDays==0`) | `tests/DeliveryControllerTest.php` | 4 tests ajoutés PR#12 : `testUpdateStatusToLivre`, `testUpdateStatusToLivreResetEtaDaysToZero`, `testUpdateStatusToLivreWithPositiveEtaDaysReturns400`, `testUpdateEtaDaysToPositiveOnLivreDeliveryReturns400` | HTTP 400 si violation, auto-reset `etaDays` à 0 lors du passage à `livre` | High (code lu) |

---

## Synthèse des exigences

**Résumé en trois phrases** : l'application expose plusieurs endpoints (GET et PATCH) sur un catalogue de livraisons inter-îles. Les endpoints GET (`/deliveries`, `/deliveries/pending`, `/deliveries/{id}`, `/deliveries/pending/count`) retournent les livraisons optionnellement filtrées par île et/ou ETA maximal (rejet HTTP 400 si paramètres invalides) ; le endpoint PATCH (`/deliveries/{id}`) permet de modifier le statut et l'ETA d'une livraison intra-requête. Mutations éphémères (non persistées entre requêtes), aucune authentification — comportement cohérent pour un pilote de démonstration.
