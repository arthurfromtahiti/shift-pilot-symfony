# Sécurité & Robustesse — Audit

> Confiance : medium

## Compréhension globale

La surface de sécurité est quasi-inexistante à l'état actuel : l'application ne traite aucune entrée utilisateur, n'accède à aucune base de données, n'expose aucune donnée personnelle identifiable et n'appelle aucun service externe (le projet dépend bien de packages tiers déclarés dans `composer.json`, mais aucun appel réseau applicatif n'est présent dans le code). Les deux endpoints `GET /deliveries` et `GET /deliveries/pending` retournent des données statiques sans transformation. Les risques structurels relevés (pas d'auth, pas de CORS, pas de rate limiting) sont acceptables pour un pilote non exposé à des utilisateurs réels, mais à adresser avant toute mise en production.

## Résumé exécutif

**Risque actuel (code seul, sans déploiement observable)** : profil faible. Il n'y a ni entrée utilisateur à valider, ni persistance à protéger, ni secrets dans le code, ni logique d'autorisation à contourner. Aucune injection SQL (pas de BDD), aucun XSS (pas de HTML), aucun secret exposé dans le code versionné. L'app n'étant pas bootstrapable comme service HTTP réel (cf. audit Architecture), la surface d'attaque effective reste théorique à ce stade.

**Risque prospectif (si l'app est exposée en HTTP)** : deux risques structurels se profilent — (1) absence d'authentification et d'autorisation dans le code : les contrôleurs n'ont aucune garde visible ; si des données sensibles sont introduites, ce sera un vecteur d'exposition immédiat dès le premier déploiement ; (2) absence de robustesse sur les données (pas d'invariants, pas de validation de sortie) qui deviendra un problème dès l'introduction d'une source de données variable. La restriction `methods: ['GET']` est portée par les attributs de route Symfony (`src/Controller/DeliveryController.php:19,25`) — son application effective reste conditionnelle à un bootstrap Symfony réel.

## Constats détaillés

`VÉRIFIÉ_CODE` : Aucune authentification ni autorisation dans le code applicatif (`src/Controller/DeliveryController.php:19-33` — aucun attribut Symfony Security `#[IsGranted]`, aucun fichier `security.yaml` présent dans le dépôt, méthodes de contrôleur sans garde visible).

`HYPOTHÈSE` : En contexte d'exposition HTTP réelle, l'absence de garde entraînerait des endpoints pleinement publics. Le dépôt ne contient pas de bootstrap HTTP observable (voir audit Architecture), donc cette exposition effective n'a pas été vérifiée. Avec les données fictives actuelles, l'impact resterait faible même si l'accès était ouvert. Si des données réelles de livraisons (destinataires, adresses, coordonnées) sont introduites, la confidentialité serait compromise sans modification préalable.

`VÉRIFIÉ_CODE` : Aucun traitement d'entrée utilisateur dans aucune des deux méthodes du contrôleur (`src/Controller/DeliveryController.php:20,26` — signatures sans paramètres). Le risque d'injection — SQL, commande OS, XSS — est nul à l'état actuel. Si des paramètres de filtrage (`?island=`, `?status=`, `?limit=`) venaient à être ajoutés, cette surface changerait et nécessiterait validation.

`VÉRIFIÉ_CODE` : Aucun secret dans le code versionné. Le `.gitignore` exclut `.env.local` (`.gitignore:3`). Il n'y a ni fichier `.env` ni `.env.dist` dans le dépôt. Le projet ne nécessite actuellement aucune credential (pas de BDD, pas d'API externe, pas de clé de service).

`VÉRIFIÉ_CODE` : La restriction HTTP `methods: ['GET']` est déclarée via les attributs de route (`src/Controller/DeliveryController.php:19,25`). En contexte Symfony bootstrapé, une requête `POST /deliveries` produirait une réponse HTTP 405 Method Not Allowed générée par le framework. Aucune gestion applicative explicite des méthodes inattendues — comportement correct car délégué au framework.

`VÉRIFIÉ_CODE` : Aucun mécanisme de logging d'accès ni de gestion des erreurs applicatives dans `DeliveryController.php` (aucun try/catch, aucun appel à un logger, `src/Controller/DeliveryController.php:1-34`). En contexte test unitaire (instanciation directe), une exception PHP remonterait vers le runner de tests sans couche d'absorption — comportement observable dans les tests existants.

`HYPOTHÈSE` : En contexte Symfony réel bootstrapé, les erreurs non gérées remonteraient vers le kernel, et aucune couche applicative ne garantirait une réponse HTTP structurée ni un log exploitable — mais ce comportement HTTP n'a pas été observé (pas de bootstrap HTTP dans le dépôt).

`HYPOTHÈSE` : L'absence de configuration CORS n'est pas un risque immédiat (l'app n'est pas servable), mais le deviendrait si un client web (SPA, application mobile) devait consommer ces endpoints depuis un domaine différent — cas probable pour un outil de suivi de livraisons.

`HYPOTHÈSE` : Aucun rate limiting observable dans la configuration. Si l'app est exposée, la route `/deliveries` pourrait être appelée massivement. Risque faible avec des données statiques (pas de charge BDD), mais à noter pour une évolution vers des données dynamiques.

## Forces

- Surface d'attaque minimale par conception : lecture seule, données statiques, aucune entrée utilisateur
- Aucun secret dans le code versionné ; `.gitignore` exclut correctement `.env.local` (`.gitignore:3`)
- Restriction de méthode HTTP portée par le framework et non par du code applicatif fragile

## Dettes techniques

- Pas d'authentification : acceptable pour le pilote actuel, bloquant pour toute exposition en production avec des données réelles
- Pas de configuration CORS : à adresser si un client web consomme l'API
- Pas de logging d'accès ou d'erreurs : aucune visibilité sur les erreurs en production
- Pas de rate limiting
- Pas de gestion explicite des erreurs dans le contrôleur

## Zones critiques

- Si de vraies données de livraisons (destinataires, adresses, références commerciales) remplacent les données fictives, l'absence d'authentification devient une exposition immédiate — un senior examinerait ce point en priorité avant tout passage en production

## Risques

- Exposition de données sensibles en l'absence d'authentification : risque structurel, impact fort si des données personnelles ou commerciales sont introduites — probabilité élevée si le pilote devient un produit réel
- Absence de gestion d'erreur applicative : en cas de régression introduisant une exception, aucune couche ne garantit une réponse HTTP structurée ni un log exploitable pour le débogage

## Recommandations priorisées

1. Avant toute exposition HTTP réelle avec des données autres que fictives : ajouter une couche d'authentification (API key statique ou JWT via Symfony Security) — `src/Controller/DeliveryController.php:19,25`
2. Créer un `.env.dist` documentant les variables d'environnement attendues (même si aucune n'est encore requise) — bonne pratique pour préparer l'évolution vers auth et BDD
3. Ajouter un `config/packages/framework.yaml` minimal avec `error_controller` configuré, en parallèle du bootstrap Symfony, pour garantir des réponses d'erreur JSON cohérentes plutôt qu'une page HTML d'erreur Symfony

## Questions ouvertes

- L'exposition en staging mentionnée dans le README (`README.md:11`) implique-t-elle une vraie exposition HTTP ou seulement des runs de CI ? La réponse détermine l'urgence des protections auth et CORS.
- Si l'app est exposée, qui sont les consommateurs autorisés (internes, partenaires, application mobile) ? La réponse détermine la stratégie auth (API key, JWT, OAuth).
