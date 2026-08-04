# Architecture — Audit

> Confiance : low

## Compréhension globale

shift-pilot-symfony est une micro-stub Symfony 5.4 déclarée en `composer.json` mais non bootstrapable en tant qu'application HTTP réelle. L'unique couche de code applicatif est un contrôleur unique (`DeliveryController.php`, 34 lignes) exposant deux endpoints en lecture seule sur des données statiques codées en dur. L'absence de `Kernel.php`, `public/index.php`, `config/bundles.php` et `config/services.yaml` confirme que le projet est un banc d'essai, non un service prêt à la production.

## Résumé exécutif

L'architecture est mono-couche délibérément minimaliste pour un pilote : un contrôleur, pas de service, pas de repository, pas de couche domaine. La déclaration de `symfony/framework-bundle` dans `composer.json` suggère une intention de croissance vers une vraie application Symfony, mais les pièces manquantes (`src/Kernel.php`, `public/index.php`, `config/bundles.php`, `config/services.yaml`) rendent l'app non-servable à ce stade — seule l'instanciation directe du contrôleur en test unitaire fonctionne. Le couplage données/logique dans le contrôleur (données en constante privée, filtrage en méthode) est un anti-pattern pour toute app qui évoluerait, mais acceptable et assumé pour ce pilote. La configuration de routage (`type: annotation`, `config/routes.yaml:3`) est cohérente avec Symfony 5.4 bien que la syntaxe héritée. Aucune injection de dépendance active, pas de container de services exploité. Absence de `composer.lock` : builds non reproductibles.

## Constats détaillés

`VÉRIFIÉ_CODE` : Le fichier `composer.json` déclare `symfony/framework-bundle: 5.4.*` en dépendance de production (`composer.json:8`), ce qui implique normalement un Kernel Symfony complet. Or, ni `src/Kernel.php`, ni `public/index.php`, ni `config/bundles.php`, ni `config/services.yaml` n'existent dans le dépôt (inventaire exhaustif confirmé). L'instanciation directe du contrôleur dans les tests (`tests/DeliveryControllerTest.php:12`) contourne entièrement le bootstrap Symfony — stratégie valide pour un pilote, mais qui ne valide pas le routage Symfony end-to-end.

`HYPOTHÈSE` : Une application Symfony `framework-bundle` sans Kernel est normalement non-servable via HTTP. En l'absence d'exécution réelle observée, les conséquences runtime restent inférées : si le projet était bootstrapé, `composer install` réussirait mais aucun serveur HTTP ne pourrait acheminer les requêtes sans `public/index.php` et `src/Kernel.php`.

`VÉRIFIÉ_CODE` : `config/routes.yaml` (3 lignes) configure le chargement des routes avec `type: annotation` et `resource: ../src/Controller/` (`config/routes.yaml:1-3`). En Symfony 5.4, ce loader supporte à la fois les annotations docblock (`@Route`) et les attributs PHP 8 (`#[Route]`). Le contrôleur utilise des attributs PHP 8 (`src/Controller/DeliveryController.php:19,25`) et importe `Symfony\Component\Routing\Annotation\Route` (`src/Controller/DeliveryController.php:6`) — namespace correct pour Symfony 5.4 (renommé en `Symfony\Component\Routing\Attribute\Route` uniquement à partir de Symfony 6.2). La configuration est cohérente avec la version déclarée.

`VÉRIFIÉ_CODE` : `symfony/yaml` figure dans les dépendances de production (`composer.json:12`), nécessaire pour parser `config/routes.yaml` à l'exécution. Pour un pilote non-servable via HTTP, cette dépendance reste sans objet à l'exécution réelle, mais sa présence serait correcte si le projet était bootstrapé.

`VÉRIFIÉ_CODE` : `symfony/framework-bundle` est présent en `require` mais `doctrine/annotations` est absent de `composer.json`. En Symfony 5.4, le loader `type: annotation` fonctionne avec les attributs PHP 8 natifs sans `doctrine/annotations`, mais nécessite que Symfony puisse charger les contrôleurs via son propre mécanisme. Sans Kernel, ce point est sans objet à l'exécution.

`HYPOTHÈSE` : L'absence de `composer.lock` (non trouvé dans le dépôt — `VÉRIFIÉ_CODE`; `.gitignore` n'exclut que `/vendor/`, `/var/` et `.env.local`, donc le lockfile n'est pas ignoré par git mais simplement jamais créé) signifie que les versions exactes des dépendances ne sont pas épinglées. Le README mentionne un déploiement en staging via Git (`README.md:11`). Sur un environnement de staging, l'absence de lockfile expose à des régressions silencieuses si Symfony publie une mise à jour mineure de `5.4.*` introduisant un breaking change — hypothèse : `composer.lock` est absent car le projet est considéré comme un pilote sans déploiement réel.

## Forces

- Architecture simplissime appropriée au périmètre pilote : aucune sur-ingénierie, code lisible en 34 lignes
- Séparation correcte entre `src/` (code applicatif) et `tests/` (tests), conforme aux conventions PSR-4 (`composer.json:16-21`)
- Déclaration de dépendances précise et minimale : quatre composants Symfony 5.4.* exactement nécessaires (`composer.json:8-12`)

## Dettes techniques

- Absence de `src/Kernel.php`, `public/index.php`, `config/bundles.php`, `config/services.yaml` : le projet n'est pas une application Symfony bootstrapable — dette structurelle majeure si l'intention est d'en faire un service HTTP réel
- Données métier stockées dans une constante de contrôleur (`src/Controller/DeliveryController.php:13-17`) : toute évolution des données requiert une modification du code source et un redéploiement — anti-pattern à extraire
- Absence de `composer.lock` : builds non reproductibles entre environnements

## Zones critiques

- `composer.json` déclare `framework-bundle` sans les fichiers requis par le framework : un senior vérifierait si c'est intentionnel (pilote pur) ou un oubli (app incomplète). La réponse détermine si la dette bootstrap doit être soldée ou assumée.

## Risques

- Si le projet doit évoluer vers un vrai service HTTP, la dette de bootstrap (Kernel, index.php, bundles.php, services.yaml) devra être comblée avant tout développement fonctionnel — risque fort si ce travail est sous-estimé lors d'une reprise
- Absence de `composer.lock` : risque d'incohérence de dépendances entre développement et staging, notamment sur la version de PHPUnit embarquée par `symfony/phpunit-bridge`

## Recommandations priorisées

1. Clarifier l'intention du projet avant toute évolution — si vrai service HTTP : créer `src/Kernel.php`, `public/index.php`, `config/bundles.php`, `config/services.yaml`; si pilote permanent : documenter explicitement cette limite dans le README — `composer.json:8`, `README.md`
2. Versionner `composer.lock` pour garantir des builds reproductibles — `composer.json:8-12`
3. Les deux endpoints existants (`/deliveries` et `/deliveries/pending`) partagent déjà les mêmes données statiques et une logique de filtrage dans le contrôleur (`src/Controller/DeliveryController.php:13-31`). Dès qu'une troisième route ou une logique additionnelle est introduite : extraire les données vers un `DeliveryRepository` et la logique métier vers un `DeliveryService`, en sortant des responsabilités du contrôleur.

## Questions ouvertes

- Le projet est-il destiné à rester un banc d'essai de test unitaire, ou à devenir un vrai service HTTP ? La réponse détermine si la dette de bootstrap est à solder ou assumée.
- Le déploiement staging mentionné dans le README (`README.md:11`) implique-t-il une vraie exécution HTTP ou seulement des runs de CI/test ?
