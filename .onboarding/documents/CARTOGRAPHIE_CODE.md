# Cartographie du code — shift-pilot-symfony

> **Confiance** : high — tous les fichiers mentionnés ont été lus et vérifiés. Structure déduite de l'inventaire du dépôt et du code source.

## Structure générale du projet

```
shift-pilot-symfony/
├── README.md                           # Documentation du pilote
├── composer.json                       # Configuration Composer (Symfony 5.4, PHP 8.1+)
├── src/
│   └── Controller/
│       └── DeliveryController.php      # Seul contrôleur applicatif
├── config/
│   └── routes.yaml                     # Découverte automatique des routes par attribut
├── tests/
│   └── DeliveryControllerTest.php      # Suite de tests unitaires
└── .onboarding/                        # Artefacts d'onboarding (générés post-livraison)
    ├── domaines/
    ├── workflows/
    ├── audits/
    ├── relectures/
    └── documents/                      # Présent branchement
```

**Total versionnés** : 5 fichiers applicatifs (README, composer.json, contrôleur, config routes, test) + artefacts d'onboarding.

**Absence remarquable** : aucun `src/Kernel.php` ni `public/index.php` — l'application n'est pas bootstrapée comme serveur HTTP réel.

---

## Domaine métier : suivi-livraisons

Ce domaine unique couvre la totalité du code applicatif produit.

### Arborescence par domaine

```
suivi-livraisons/
├── Point d'entrée HTTP
│   └── src/Controller/DeliveryController.php
├── Configuration de routage
│   └── config/routes.yaml
├── Tests
│   └── tests/DeliveryControllerTest.php
└── Données métier
    └── [Inline : DeliveryController::DELIVERIES]
```

---

## Fichiers critiques du domaine

### `src/Controller/DeliveryController.php`

**Rôle** : seul composant applicatif ; expose deux endpoints GET pour consulter l'état d'acheminement des livraisons.

**Responsabilités**

- Exposer l'endpoint `GET /deliveries` → liste complète des livraisons.
- Exposer l'endpoint `GET /deliveries/pending` → liste filtrée (status != 'livre').
- Héberger la source de données : constante `DELIVERIES`.

**Structure**

```php
class DeliveryController
{
    private const DELIVERIES = [
        ['id' => 1, 'island' => 'Bora Bora', 'status' => 'en_transit', 'etaDays' => 3],
        ['id' => 2, 'island' => 'Moorea', 'status' => 'livre', 'etaDays' => 0],
        ['id' => 3, 'island' => 'Huahine', 'status' => 'en_transit', 'etaDays' => 5],
    ];

    #[Route('/deliveries', methods: ['GET'])]
    public function list(): JsonResponse { ... }

    #[Route('/deliveries/pending', methods: ['GET'])]
    public function pending(): JsonResponse { ... }
}
```

**Lignes clés**

| Ligne | Signification |
|------|---------------|
| 12 | `private const DELIVERIES` — source de données unique, immutable |
| 13-17 | Tableau statique : 3 livraisons de demo |
| 19 | `#[Route('/deliveries', methods: ['GET'])]` — première déclaration de route |
| 20 | `public function list()` — première méthode métier |
| 22 | `new JsonResponse(self::DELIVERIES)` — retour brut de la constante |
| 25 | `#[Route('/deliveries/pending', methods: ['GET'])]` — deuxième déclaration |
| 26 | `public function pending()` — deuxième méthode métier |
| 28-31 | `array_filter(..., fn => $d['status'] !== 'livre')` — logique de filtrage critique |

**Points d'entrée**

- `DeliveryController::list()` — réponse brute de `DELIVERIES`, aucune transformation.
- `DeliveryController::pending()` — filtrage en mémoire, puis réindexation, puis sérialisation.

**Dépendances**

- Framework Symfony (`symfony/framework-bundle`) — déclaration de route via attributs `#[Route(...)]`, `JsonResponse`.
- Pas de service injecté, pas de repository, pas de cache, pas d'héritage de classe parente.

**Risques concentrés ici**

1. **Données figées** : la constante ne peut pas être modifiée sans redéploiement (ligne 12).
2. **Règle de filtrage implicit** : la logique `status !== 'livre'` n'est documentée nulle part (ligne 30).
3. **Absence de validation** : aucune vérification de cohérence `etaDays` / `status` (lignes 13-17).
4. **Bootstrap absent** : ce contrôleur est instanciable et testable directement, mais pas routable dans une app Symfony réelle (voir `Kernel.php` manquant).

---

### `config/routes.yaml`

**Rôle** : configuration Symfony pour la découverte automatique des routes.

**Contenu**

```yaml
controllers:
    resource: ../src/Controller/
    type: annotation
```

**Signification**

- Symfony scanne tous les fichiers dans `src/Controller/` et recherche les attributs `#[Route(...)]` sur les méthodes publiques.
- Les attributs découverts dans `DeliveryController.php:19` et `DeliveryController.php:25` sont automatiquement activés.
- Aucune route supplémentaire n'est définie ailleurs.

**Limites**

- Config minimaliste, acceptée pour un pilote.
- Aucun versioning (`/api/v1/`), aucun groupement (`/api/deliveries`).
- Pas de middleware de sécurité ou de validation centralisée.

---

### `tests/DeliveryControllerTest.php`

**Rôle** : suite de tests unitaires couvrant les deux endpoints.

**Couverture**

| Cas de test | Méthode testée | Ligne | Assertion clé |
|-------------|----------------|-------|----------------|
| `testListReturnsAllDeliveries` | `DeliveryController::list()` | 9-16 | `count($response) === 3` |
| `testPendingExcludesDelivered` | `DeliveryController::pending()` | 18-27 | `count($response) === 2` + aucun avec `status == 'livre'` |

**Approche**

- Instanciation directe du contrôleur (pas d'injection Symfony).
- Appel direct des méthodes publiques.
- Assertion sur le contenu JSON décodé.

**Limites**

- Test unitaire, pas d'intégration HTTP end-to-end (routes Symfony, middleware, bootstrap).
- Aucun test de validation de format (structure JSON, champs additionnels).
- Aucun test de règles métier (invariante `etaDays` / `status`).
- Runtime d'exécution indisponible au moment de l'analyse — statut de réussite `INCONNU`.

---

### `composer.json`

**Rôle** : déclaration des dépendances et scripts de projet.

**Éléments clés**

```json
{
  "name": "paperclip/shift-pilot-symfony",
  "description": "Pilot SHIFT/Paperclip — Delivery tracking (Polynesia)",
  "require": {
    "php": ">=8.1",
    "symfony/framework-bundle": "^5.4"
  },
  "scripts": {
    "test": "phpunit tests"
  }
}
```

**Signification**

- Pilote Paperclip/SHIFT explicitement déclaré.
- Stack : Symfony 5.4 (end-of-life 2023, mais acceptable pour un démo).
- PHP 8.1+ requis (attributs `#[Route(...)]` supportés).
- Script `test` : exécution de PHPUnit sur le répertoire `tests/`.

**Absence notable** : pas de script `server`, `dev`, `build` — pas de bootstrap HTTP intégré.

---

### `README.md`

**Rôle** : documentation du projet.

**Contenu essentiel** : déclare le projet comme un pilote SHIFT/Paperclip, énumère les éléments clés (deux endpoints, données statiques), mentionne les limitations (pas de persistance, pas de production).

**Utilité** : confirmation de l'intention du projet (démonstration, pas d'outil opérationnel).

---

## Points d'entrée API

| Endpoint | Verbe | Déclaration | Implémentation | Statut |
|----------|-------|-------------|-----------------|--------|
| `/deliveries` | GET | `src/Controller/DeliveryController.php:19` | `DeliveryController::list():JsonResponse` | Opérationnel (données statiques) |
| `/deliveries/pending` | GET | `src/Controller/DeliveryController.php:25` | `DeliveryController::pending():JsonResponse` | Opérationnel (filtrage en mémoire) |
| Autres verbes / routes | — | — | — | Non implémenté |

---

## Flux de requête (simplifié)

```
Requête HTTP GET /deliveries
  ↓
Symfony dispatcher (config/routes.yaml:1-3)
  ↓
DeliveryController::list() trouvée via attribut #[Route(...)]
  ↓
Accès à self::DELIVERIES (ligne 22)
  ↓
Sérialisation JsonResponse (ligne 22)
  ↓
HTTP 200 + Content-Type: application/json
```

**Différence pour `/deliveries/pending`**

```
Requête HTTP GET /deliveries/pending
  ↓
Symfony dispatcher (config/routes.yaml:1-3)
  ↓
DeliveryController::pending() trouvée via attribut #[Route(...)]
  ↓
Filtrage array_filter(..., fn => $d['status'] !== 'livre') (ligne 28-31)
  ↓
Réindexation array_values(...) (ligne 28)
  ↓
Sérialisation JsonResponse (ligne 32)
  ↓
HTTP 200 + Content-Type: application/json
```

---

## Absence de composants critiques

### Bootstrap HTTP manquant

**Fichiers attendus** : `src/Kernel.php`, `public/index.php`.

**Impact** : l'application n'est pas exécutable comme serveur HTTP réel. Elle est testable par instanciation directe du contrôleur (tests unitaires) mais pas servable via `bin/console server:run` ou un serveur reverse-proxy.

**Conséquence** : les routes déclarées via attributs PHP ne sont pas validées end-to-end ; seule la logique des contrôleurs est testée.

### Pas de persistance

**Fichiers attendus** : `src/Entity/Delivery.php` (entité Doctrine), `config/packages/doctrine.yaml`, migrations, repository.

**Impact** : données codées en dur, immutables sans redéploiement.

### Pas de middleware de sécurité

**Fichiers attendus** : aucun ; aucune couche d'authentification/autorisation.

**Impact** : endpoints publics, accessibles sans token.

### Pas de documentation d'API

**Fichiers attendus** : `openapi.yaml`, `swagger.json`, ou commentaires PHPDoc sur les méthodes.

**Impact** : aucune spécification d'API formelle ; clients doivent déduire le contrat de la source ou des tests.

---

## Dépendances technologiques

| Composant | Source | Version | Rôle |
|-----------|--------|---------|------|
| **Symfony Framework** | `composer.json` | 5.4 | Framework HTTP ; routing via attributs, JsonResponse |
| **PHP** | `composer.json` | ≥ 8.1 | Language ; support des attributs natifs (`#[Route(...)]`) |
| **symfony/phpunit-bridge** | `composer.json` (require-dev) | — | Bridge PHPUnit-Symfony pour l'exécution de tests |

**Absence remarquable** : pas de `symfony/console`, pas de `symfony/orm-pack` (Doctrine), pas de dépendances externes au framework principal.

---

## Patterns Symfony en place

### Attributs de routing

Symfony 5.4 supporte les attributs PHP natifs (`#[Route(...)]`) en lieu et place des annotations docblock anciennes. Ici utilisé pour `#[Route('/deliveries', methods: ['GET'])]`.

**Bénéfice** : syntaxe moderne, type-safe, pas de dépendance à une librairie d'annotations.

### JsonResponse

Pattern Symfony pour retourner JSON avec en-têtes HTTP appropriés. Utilisé dans les deux endpoints.

**Bénéfice** : sérialisation automatique de tableaux PHP en JSON, gestion d'en-têtes, respect des conventions HTTP.

---

## Limites et risques techniques

### 1. Pas d'abstraction métier

Le contrôleur porte directement la donnée (constante `DELIVERIES`) et la logique (filtrage). Pas de service, pas de repository, pas de use case — une future persistance obligerait à refactoriser le contrôleur lui-même.

**Recommandation** : si le projet évolue, introduire un service `DeliveryService` pour séparer la logique métier du transport HTTP.

### 2. Pas de validation de sortie

Aucune vérification de la structure retournée. Une future mutation de la constante (ex. ajout de champ) ne sera pas détectée au test.

**Recommandation** : ajouter un test de validation du schéma JSON (ex. avec JSON Schema ou assertion sur les clés).

### 3. Pas de gestion d'erreur applicative

Les deux endpoints n'ont aucun bloc `try/catch` — aucune erreur applicative n'est gérée. Acceptable pour du code sans effet de bord ; deviendrait un risque en cas de base de données.

**Recommandation** : si le projet évolue, introduire une couche d'exception et un error handler centralisé.

### 4. Pas de versioning d'API

Routes nues `/deliveries`, `/deliveries/pending` sans `/api/v1/`. Tout changement de format affectera les clients existants.

**Recommandation** : introduire `/api/v1/` dès le prochain changement de structure de réponse.

### 5. Pas de cache ni d'optimisation de bande passante

Pas de `ETag`, pas de `Last-Modified`, pas de gzip. Acceptable pour 3 livraisons statiques ; problème à l'échelle en production.

**Recommandation** : si déployé en production, ajouter une politique de cache HTTP et une compression de réponse.

---

## Éléments de qualité présents

- ✅ Code lisible et concis (34 lignes pour le contrôleur complet).
- ✅ Noms explicites (`DELIVERIES`, `list()`, `pending()`, `island`, `status`, `etaDays`).
- ✅ Tests présents et lisibles (2 cas de test couvrant les deux endpoints).
- ✅ Conformité Symfony moderne (attributs, JsonResponse, `AbstractController`).
- ✅ Configuration minimale, facile à comprendre.

---

## Checklist de complétude pour une évolution opérationnelle

Si ce pilote doit devenir un système de suivi réel, voici les composants manquants prioritaires :

- [ ] `src/Kernel.php` — bootstrap Symfony
- [ ] `public/index.php` — point d'entrée HTTP
- [ ] `src/Entity/Delivery.php` — modèle persisté (Doctrine ORM)
- [ ] `config/packages/doctrine.yaml` — configuration base de données
- [ ] `migrations/` — scripts de schéma
- [ ] `src/Repository/DeliveryRepository.php` — accès aux données
- [ ] `src/Service/DeliveryService.php` — logique métier, indépendante du contrôleur
- [ ] `config/packages/security.yaml` — authentification/autorisation
- [ ] `openapi.yaml` ou `swagger.json` — documentation d'API
- [ ] `/api/v1/deliveries`, `/api/v1/deliveries/pending` — versioning de route
- [ ] `GET /api/v1/deliveries/{id}` — consultation par identifiant
- [ ] Tests d'intégration (requêtes HTTP complètes, pas juste unitaires)
- [ ] Gestion d'erreurs centralisée (error handler, exception mapping)

---

## Synthèse structurelle

| Aspect | État | Confiance |
|--------|------|-----------|
| **Contrôleur** | Présent, logique simple et lisible | High |
| **Routes** | Déclarées via attributs, découverte automatique | High |
| **Tests** | Présents, pas d'intégration HTTP | Medium |
| **Persistance** | Absente (constante statique) | High (volontaire) |
| **Sécurité** | Absence d'auth (acceptable pour pilote) | High (volontaire) |
| **Documentation** | Minimale, aucune spécification d'API | Low |
| **Bootstrap HTTP** | Absent | High (volontaire, bloque production) |
| **Scalabilité** | Non applicable (données figées, 3 éléments) | N/A |

**Constatation finale** : structure technique cohérente avec l'intention de pilote. Prête pour une démo ou un test de concept. Non opérationnelle en l'état ; convertible en système réel avec l'ajout des composants manquants dans la checklist ci-dessus.
