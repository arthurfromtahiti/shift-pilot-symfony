# Relecture — DATA_MODEL_AUDIT.md

## Verdict global
Bon — les constats sont correctement sourcés, les statuts de preuve sont tenus, et les risques restent rattachés à des observations concrètes du code. Je n'ai pas relevé d'exagération bloquante sur cette zone.

## Problèmes bloquants
Aucun.

## Problèmes mineurs
- La recommandation Doctrine dans [DATA_MODEL_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/DATA_MODEL_AUDIT.md:51) est une orientation d'implémentation, pas une conséquence imposée par la preuve. Ce n'est pas faux, mais c'est un choix d'architecture à présenter comme tel.

## Points vérifiés et corrects
- La constante `private const DELIVERIES` et son contenu exact sont bien présents dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:13).
- L'absence d'entités Doctrine et de dépendances `doctrine/orm` ou `doctrine/dbal` est conforme à [composer.json](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/composer.json:6) et à l'inventaire des fichiers du dépôt.
- Le risque lié au filtre `status !== 'livre'` est concret, car il s'appuie sur la logique réelle de [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:30), non sur une généralité abstraite.

## Recommandations de correction
- Si tu conserves la recommandation Doctrine, présente-la explicitement comme une option d'implémentation parmi d'autres pour une future persistance, plutôt que comme la suite naturelle obligée.
