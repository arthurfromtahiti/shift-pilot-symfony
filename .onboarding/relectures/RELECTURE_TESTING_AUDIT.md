# Relecture — TESTING_AUDIT.md

## Verdict global
Acceptable avec réserves — les preuves principales sont solides et bien localisées, mais un des risques formulés sur l'absence de `composer.lock` est trop générique et insuffisamment concret. Le reste de l'analyse tient.

## Problèmes bloquants
Aucun.

## Problèmes mineurs
- Le risque « une mise à jour mineure de PHPUnit modifie le comportement de `json_decode` ou des assertions » dans [TESTING_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/TESTING_AUDIT.md:50) n'est pas assez concret. L'absence de `composer.lock` est un vrai fait, mais ici le scénario proposé paraît spéculatif et peu rattaché au code lu.
- La phrase sur le commit `233aea3` dans [TESTING_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/TESTING_AUDIT.md:25) est correctement laissée en `HYPOTHÈSE`, mais elle ajoute peu de valeur tant qu'aucune exécution n'a été observée dans l'environnement courant.

## Points vérifiés et corrects
- Les tests étendent bien `PHPUnit\Framework\TestCase` et instancient directement `DeliveryController` dans [tests/DeliveryControllerTest.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/tests/DeliveryControllerTest.php:8), donc le constat sur l'absence de couverture du routage Symfony est juste.
- Il n'existe bien ni `phpunit.xml` ni `phpunit.xml.dist` dans le dépôt ; la recommandation de formaliser la config de tests est donc bien sourcée.
- Les assertions de `testListReturnsAllDeliveries` et `testPendingExcludesDelivered` sont correctement décrites par l'audit dans [tests/DeliveryControllerTest.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/tests/DeliveryControllerTest.php:10).
- Le message de commit cité existe bien dans `git log --oneline`, ce qui rend la mention historique vérifiable même si elle ne vaut pas exécution observée aujourd'hui.

## Recommandations de correction
- Remplacer le risque trop générique sur `json_decode`/assertions par un scénario plus directement lié au projet, ou le retirer.
- Garder la mention du commit initial seulement si elle sert explicitement à justifier le statut `HYPOTHÈSE` sur l'exécution réelle.
