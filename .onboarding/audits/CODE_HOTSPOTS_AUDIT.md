# Points chauds du code — Audit

> Confiance : high

## Compréhension globale

Le projet compte 2 fichiers de code significatifs : `src/Controller/DeliveryController.php` (34 lignes) et `tests/DeliveryControllerTest.php` (28 lignes). Il n'y a pas de hotspot au sens habituel — fichier volumineux, très couplé, critique et non testé. L'analyse se concentre sur les risques latents du seul fichier de logique applicative et sur les zones qui deviendront des points chauds si le projet grandit sans extraction de couches.

## Résumé exécutif

À ce stade du pilote, il n'y a aucun hotspot problématique : `DeliveryController.php` est 34 lignes, entièrement lisible, sans couplage externe, et couvert par 2 tests unitaires. La complexité cyclomatique est minimale. Un seul risque d'implémentation mérite attention immédiate : le `array_values` dans `pending()` est indispensable pour garantir un tableau JSON indexé (et non un objet à clés non contiguës) — son absence provoquerait un bug JSON silencieux mais visible par les clients. Ce détail est correctement implémenté. Les risques sont tous prospectifs : si le projet grandit sans extraction de couches (service, repository, enum de statut), `DeliveryController.php` deviendra naturellement un point de tout-en-un. L'import legacy (`Symfony\Component\Routing\Annotation\Route`) est à signaler pour une future migration vers Symfony 6+.

## Constats détaillés

`VÉRIFIÉ_CODE` : `DeliveryController.php` est le seul fichier de logique applicative (34 lignes, `src/Controller/DeliveryController.php:1-34`). Il cumule trois responsabilités distinctes : définir les données de référence (`DELIVERIES`, lignes 13-17), implémenter la logique de liste sans filtre (`list()`, lignes 19-23) et la logique de filtrage par statut (`pending()`, lignes 25-33). À 34 lignes, ce couplage est tolérable et attendu pour un pilote. À 100+ lignes — ajout de nouvelles routes, paramètres de filtrage, gestion d'erreurs, logs — ce cumul deviendrait un point chaud à refactorer.

`VÉRIFIÉ_CODE` : La logique de `pending()` est correcte et techniquement précise : `array_values(array_filter(self::DELIVERIES, fn(array $d) => $d['status'] !== 'livre'))` (`src/Controller/DeliveryController.php:28-31`). Le `array_values` est critique : `array_filter` préserve les clés d'origine du tableau source ; sans `array_values`, si des éléments d'indice 0 ou 1 étaient filtrés, le tableau résultant aurait des clés non contiguës (ex. `[1 => ..., 2 => ...]`), que `JsonResponse` sérialiserait en objet JSON (`{"1":..., "2":...}`) plutôt qu'en tableau JSON (`[...]`) — bug silencieux cassant pour tout client attendant un tableau. Ce détail est bien implémenté.

`VÉRIFIÉ_CODE` : L'import `use Symfony\Component\Routing\Annotation\Route;` (`src/Controller/DeliveryController.php:6`) est correct pour Symfony 5.4. Ce namespace a migré vers `Symfony\Component\Routing\Attribute\Route` à partir de Symfony 6.2. Lors d'une mise à jour majeure vers Symfony 6+, cet import devra être mis à jour — point de migration à documenter.

`VÉRIFIÉ_CODE` : Le test `testListReturnsAllDeliveries` valide uniquement `assertCount(3, $data)` (`tests/DeliveryControllerTest.php:15`). Ce count est couplé au nombre d'entrées dans la constante `DELIVERIES`. Si une 4ème livraison est ajoutée aux données fictives, le test échoue même si la logique de `list()` est correcte — c'est un couplage données/test qui produit des faux positifs d'échec.

`VÉRIFIÉ_CODE` : `testPendingExcludesDelivered` valide `assertCount(2, $data)` et `assertNotSame('livre', $delivery['status'])` pour chaque élément (`tests/DeliveryControllerTest.php:23-26`). Le count est également couplé aux données, mais la deuxième assertion (`assertNotSame`) teste réellement la règle métier de filtrage — c'est l'assertion la plus robuste du suite.

## Forces

- Code minimal et lisible ; complexité cyclomatique quasi-nulle
- `array_values` dans `pending()` correctement implémenté — détail technique critique maîtrisé (`src/Controller/DeliveryController.php:28`)
- Séparation claire des deux endpoints (`list()` / `pending()`) sans enchevêtrement

## Dettes techniques

- Données colocalisées avec la logique dans le contrôleur : anti-pattern prêt à devenir un hotspot dès l'ajout d'endpoints ou de logique supplémentaire (`src/Controller/DeliveryController.php:13-17`)
- Tests couplés aux valeurs de données plutôt qu'aux invariants logiques (`assertCount(3, $data)` ne teste pas la logique, `tests/DeliveryControllerTest.php:15`)
- Import legacy `Routing\Annotation\Route` à jour pour 5.4, obsolète pour Symfony 6+ (`src/Controller/DeliveryController.php:6`)

## Zones critiques

- `src/Controller/DeliveryController.php` deviendra le point chaud numéro 1 si des endpoints supplémentaires (`GET /deliveries/{id}`, `POST /deliveries`, mutation de statut) y sont ajoutés sans extraction d'une couche service/repository — senior surveillance à enclencher dès le 2ème endpoint.
- Le `array_values` dans `pending()` : si cette méthode est refactorée (ex. extraction vers un service), l'oubli de `array_values` dans la nouvelle implémentation produirait un bug JSON silencieux (`src/Controller/DeliveryController.php:28`).

## Risques

- Régression silencieuse du bug JSON (perte de `array_values`) lors d'un refactoring de `pending()` : impacte tous les clients HTTP qui attendent un tableau JSON — `src/Controller/DeliveryController.php:28`
- Montée en complexité du contrôleur sans refactoring préalable : risque de controller God object si le projet grandit sans plan d'extraction — signaux d'alerte : +50 lignes, 3ème méthode publique, premier appel à un service externe

## Recommandations priorisées

1. Avant d'ajouter de nouveaux endpoints : extraire `DELIVERIES` vers un `DeliveryRepository` (stub en mémoire ou réel), et la logique de filtrage vers un `DeliveryService::getPending()` — `src/Controller/DeliveryController.php:13-31`
2. Réécrire `testListReturnsAllDeliveries` pour tester la structure des éléments plutôt que le count : `assertArrayHasKey('id', $data[0])`, `assertArrayHasKey('island', $data[0])`, `assertIsInt($data[0]['id'])`, etc. — `tests/DeliveryControllerTest.php:15`
3. Documenter le rôle de `array_values` dans un commentaire inline unique sur `pending()` pour éviter sa suppression lors d'un refactoring futur — `src/Controller/DeliveryController.php:28`

## Questions ouvertes

- Y a-t-il une roadmap d'endpoints supplémentaires (`GET /deliveries/{id}`, `PUT /deliveries/{id}/status`, `POST /deliveries`) ? Si oui, le refactoring service+repository est urgent avant d'empiler du code dans le contrôleur.
- Le `array_values` est-il couvert par un test explicite (ex. test vérifiant que la réponse de `pending()` est un tableau JSON et non un objet) ? Actuellement non — `tests/DeliveryControllerTest.php:1-28`.
