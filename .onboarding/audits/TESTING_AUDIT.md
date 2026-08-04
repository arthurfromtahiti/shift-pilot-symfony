# Tests — Audit

> Confiance : medium

## Compréhension globale

Le projet dispose de 2 tests unitaires dans un seul fichier (`tests/DeliveryControllerTest.php`, 28 lignes). Les tests instancient le contrôleur directement, sans bootstrap Symfony. Ils couvrent le comportement observable des deux endpoints (`list()` et `pending()`), mais sans valider la structure des données retournées ni les cas limites. Aucun test d'intégration, aucun test end-to-end, aucune configuration PHPUnit formalisée.

## Résumé exécutif

La couverture est fonctionnellement acceptable pour un pilote de 2 endpoints sur données statiques, mais superficielle dans sa forme. `testListReturnsAllDeliveries` ne vérifie que le nombre d'éléments (3), pas leur structure. `testPendingExcludesDelivered` vérifie le count (2) et l'absence du statut `livre` — c'est la meilleure assertion du suite car elle teste la règle métier réelle. Ni l'un ni l'autre ne valide la structure des éléments retournés (présence des clés `id`, `island`, `status`, `etaDays`, types). Aucun test n'exercice la configuration de routage Symfony (`config/routes.yaml`) ni le cycle HTTP complet. L'absence de `phpunit.xml.dist` signifie que PHPUnit tourne avec sa configuration par défaut — acceptable mais non formalisé. L'absence de `composer.lock` rend la version de PHPUnit non épinglée. Le message du commit initial (`233aea3`) mentionne « tests PHPUnit verts » — suggérant que les tests passaient à la création du dépôt, sans exécution vérifiée dans cet environnement.

## Constats détaillés

`VÉRIFIÉ_CODE` : `DeliveryControllerTest` étend `PHPUnit\Framework\TestCase` (`tests/DeliveryControllerTest.php:8`), et non `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`. Les tests instancient `DeliveryController` directement sans démarrer de kernel Symfony (`tests/DeliveryControllerTest.php:12,20`). Conséquence directe : le routage Symfony n'est jamais exercé. La configuration `config/routes.yaml` et les attributs `#[Route]` ne sont pas couverts par ces tests. Une erreur dans `config/routes.yaml` (ex. typo dans le chemin, type de loader invalide) ne serait pas détectée.

`VÉRIFIÉ_CODE` : `testListReturnsAllDeliveries` : seule assertion — `assertCount(3, $data)` (`tests/DeliveryControllerTest.php:15`). Ce test prouve que 3 éléments sont retournés, rien de plus. Il ne vérifie pas : la structure des éléments (clés présentes), les types des valeurs (`id` est-il bien un entier ?), le Content-Type de la réponse HTTP, ni le statut HTTP (200).

`VÉRIFIÉ_CODE` : `testPendingExcludesDelivered` : deux assertions — `assertCount(2, $data)` (`tests/DeliveryControllerTest.php:23`) et boucle `assertNotSame('livre', $delivery['status'])` (`tests/DeliveryControllerTest.php:25`). La deuxième assertion vérifie la règle métier de filtrage par statut — c'est l'assertion la plus utile car elle est indépendante du nombre exact d'entrées dans les données (dans la limite du count qui reste couplé).

`VÉRIFIÉ_CODE` : Aucun fichier `phpunit.xml` ou `phpunit.xml.dist` dans le dépôt. La commande `phpunit tests` dans `composer.json` (`composer.json:23`) utilise le répertoire `tests/` explicitement, mais sans configuration de couverture, sans bootstrap défini, sans paramètres de reporter. PHPUnit tournera avec ses défauts.

`VÉRIFIÉ_CODE` : Aucun `composer.lock` versionné. `symfony/phpunit-bridge 5.4.*` embarque une version de PHPUnit compatible (PHPUnit 9.x pour Symfony 5.4), mais sans lockfile, la version exacte installée sur chaque environnement peut différer selon la date d'installation.

`HYPOTHÈSE` : Le commit `233aea3` mentionne « tests PHPUnit verts » — les tests passaient lors de la création du dépôt. Aucune exécution observée directement dans l'environnement courant (pas de runtime PHP/Composer disponible).

## Forces

- Couverture des deux cas métier principaux (liste complète et liste filtrée)
- `testPendingExcludesDelivered` vérifie la règle métier réelle (pas uniquement un count), ce qui est plus robuste
- Tests rapides : instanciation directe du contrôleur, sans overhead de framework
- Aucun faux test vide ni test de fixture sans assertion

## Dettes techniques

- Aucun test sur la structure des éléments retournés (présence des clés `id`, `island`, `status`, `etaDays` et leurs types)
- Aucun test sur le routage Symfony (aucune couverture de `config/routes.yaml`)
- Tests couplés au nombre d'entrées dans `DELIVERIES` — un ajout de données casse les tests sans signaler une vraie régression logique
- Pas de `phpunit.xml.dist` : configuration PHPUnit non formalisée
- `composer.lock` absent : version PHPUnit non épinglée

## Zones critiques

- `tests/DeliveryControllerTest.php` dans son intégralité : les assertions de count (`assertCount(3, ...)`, `assertCount(2, ...)`) produiront des faux positifs d'échec dès qu'une entrée est ajoutée ou supprimée de `DELIVERIES`, rendant les tests bruyants et peu fiables comme signal de régression logique.

## Risques

- Tests fragiles couplés aux données : tout ajout d'entrée dans `DELIVERIES` casse `testListReturnsAllDeliveries` et potentiellement `testPendingExcludesDelivered` sans que la logique ne soit en faute — signal de régression pollué
- Couverture HTTP absente : le routage Symfony n'est jamais exercé — une erreur de configuration de routes passerait inaperçue jusqu'à la mise en production
- Version PHPUnit non épinglée : risque d'incohérence entre environnements si une mise à jour mineure de PHPUnit modifie le comportement de `json_decode` ou des assertions

## Recommandations priorisées

1. Créer un `phpunit.xml.dist` définissant le répertoire de tests (`<testsuites>`), le bootstrap (`vendor/autoload.php`), et optionnellement un minimum de couverture — prérequis pour tout pipeline CI — aucun fichier source
2. Réécrire `testListReturnsAllDeliveries` pour vérifier la structure plutôt que le count : `assertArrayHasKey('id', $data[0])`, `assertArrayHasKey('island', $data[0])`, `assertIsInt($data[0]['id'])`, `assertIsString($data[0]['status'])` — `tests/DeliveryControllerTest.php:14-15`
3. Versionner `composer.lock` pour épingler PHPUnit à une version exacte — `composer.json:14`
4. Ajouter un test Symfony `WebTestCase` (ou `KernelTestCase`) qui bootstrape l'application et vérifie le routage end-to-end — même sans `Kernel.php` pour l'instant, le créer en parallèle du bootstrap Symfony documentera l'intention

## Questions ouvertes

- Les tests passent-ils avec un `composer install` suivi de `composer test` en PHP 8.1+ ? (statut : `INCONNU` — aucun runtime disponible dans l'environnement d'analyse ; le commit initial suggère que oui)
- Un rapport de couverture de code est-il attendu en CI ? (pas de configuration de coverage dans `phpunit.xml.dist` qui n'existe pas)
- Quelle est la politique de tests pour les futurs endpoints ? Unit tests seuls ou tests HTTP avec `WebTestCase` ?
