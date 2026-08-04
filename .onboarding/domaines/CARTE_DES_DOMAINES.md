# Carte des domaines — shift-pilot-symfony

> **Mode d'onboarding** : onboarding complet (aucun `.onboarding/` sur le distant, aucune branche `onboarding/artifacts`).
> **Dépôt à la tête** : branche par défaut `main`, SHA `233aea37ab8bf0630f9e23bae0e10edc284f3dfa`.
> **Avertissement de périmètre** : ce dépôt est un **pilote/seed** (6 fichiers versionnés, un seul contrôleur, données en dur, aucune persistance). Les preuves ne soutiennent honnêtement qu'**un seul domaine réel**. Conformément à la règle « preuves pauvres → moins de domaines en confiance basse, jamais une carte inventée », je reste **sous le plancher de 4 domaines** plutôt que de fabriquer des domaines sans preuve. Les pistes non encore matérialisées (auth, persistance, mutations) sont listées en *Incertitudes*, pas transformées en domaines.

## Nature du projet
Micro-API HTTP Symfony 5.4 (PHP ≥ 8.1) exposant en JSON le **suivi de livraisons de fret inter-îles** (Polynésie française). Déduit des noms réels : contrôleur `DeliveryController`, routes `/deliveries` et `/deliveries/pending`, et d'un jeu de livraisons `{id, island, status, etaDays}` avec des îles réelles (Bora Bora, Moorea, Huahine). Le logiciel se limite aujourd'hui à la **consultation en lecture** : lister les livraisons et filtrer celles non encore livrées. Aucune écriture, aucune base de données, aucune authentification — les données sont portées par une constante PHP.

## Domaines

### Suivi des livraisons inter-îles (`suivi-livraisons`)
- **Catégorie** : métier
- **Priorité** : cœur
- **Confiance** : high
- **Description** : consultation de l'état d'acheminement des livraisons de fret entre les îles. Deux vues aujourd'hui : la **liste complète** des livraisons et la **sous-liste des livraisons en attente** (tout statut différent de `livre`). C'est la seule raison d'être du logiciel dans son état actuel.
- **Entités** : aucune entité Doctrine / aucune table. Le modèle de données est porté **en dur** par la constante `DeliveryController::DELIVERIES` — chaque livraison = `{ id:int, island:string, status:enum(en_transit|livre), etaDays:int }` (`VÉRIFIÉ_CODE` : `src/Controller/DeliveryController.php:12-16`).
- **Routes / points d'entrée** : `GET /deliveries` → `DeliveryController::list()` ; `GET /deliveries/pending` → `DeliveryController::pending()`. Réponses `JsonResponse`. Découverte des routes par annotation via `config/routes.yaml` (`resource: ../src/Controller/`, `type: annotation`). (`VÉRIFIÉ_CODE` : `src/Controller/DeliveryController.php:18-33`, `config/routes.yaml:1-3`)
- **Indices de rattachement** : entités/classes `Delivery*` ; chemins `src/Controller/Delivery*` ; routes `/deliveries*` ; champs métier `island`, `status`, `etaDays` ; statuts `en_transit` / `livre`.
- **Types de workflows attendus** : consultation d'un état de livraison ; filtrage par statut (livrées vs en cours). Aujourd'hui **lecture seule** — aucune création, mise à jour ou transition de statut n'est implémentée dans le code.
- **Preuves** : `src/Controller/DeliveryController.php:1-38` (contrôleur + données + deux endpoints) ; `config/routes.yaml:1-3` (exposition des routes) ; `tests/DeliveryControllerTest.php:1-30` (couverture des deux endpoints). Statut d'exécution des tests : `INCONNU` — runtime PHPUnit/Composer indisponible dans l'environnement d'analyse ; assertions **lues** dans le source (`VÉRIFIÉ_CODE`) mais **non exécutées**.
- **Dépend de la base** : non. Aucune couche de persistance ; les données sont une constante PHP. Aucun des trois signaux de contenu piloté par la base n'est présent : pas de schéma (aucune base), pas d'entité étendue (aucune entité), pas de code exécutable décodant une structure arborescente (les endpoints renvoient un tableau statique, sans `json_decode` récursif ni renderer/resolver).

## Incertitudes
- **Amorce ou banc d'essai ?** Le dépôt n'expose qu'une ressource métier avec des données en dur. Est-ce l'amorce d'un vrai produit de suivi de livraisons (modèle `Delivery` persisté, mutations create/update, transitions de statut) ou un pilote de test SHIFT/Paperclip destiné à rester un banc d'essai unitaire ? Le README indique explicitement « Pilote de test SHIFT/Paperclip ».
- **Persistance à venir.** Aucune base, aucune entité, aucune migration. Si le produit doit vivre, à quoi ressemblera le modèle persisté (table `delivery`, clés, index par `island`/`status`) ? — déterminant pour un futur domaine « données/persistance ».
- **Statuts métier exhaustifs ?** Seuls `en_transit` et `livre` apparaissent dans les données d'exemple. La liste réelle des statuts (préparation, en douane, retardé, annulé…) est-elle plus large ? Impacte le filtre `pending` (aujourd'hui : « tout ce qui n'est pas `livre` »).
- **Aucune authentification/autorisation.** Les deux endpoints sont publics. Est-ce voulu pour le pilote, ou un domaine « auth/accès » est-il prévu ? — actuellement aucune preuve, donc pas un domaine.
- **Application non bootstrapable en l'état.** Pas de `src/Kernel.php`, pas de `public/index.php`, pas de configuration de framework au-delà de `config/routes.yaml`. Le dépôt est exécutable en **test unitaire** (instanciation directe du contrôleur) mais pas servable comme app HTTP réelle sans ces fichiers. Confirmer l'intention.
- **Environnement de vérification.** Fournir un environnement PHP 8.1+/Composer pour porter le statut des tests de `INCONNU` à `OBSERVÉ` (exécution réelle de `composer test`).
