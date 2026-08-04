# Relecture — FUNCTIONAL_AUDIT.md

## Verdict global
Acceptable avec réserves — l'audit lit correctement les deux comportements implémentés et ses risques principaux sont concrets. Quelques recommandations et formulations vont cependant plus loin que ce que la preuve permet d'imposer.

## Problèmes bloquants
Aucun.

## Problèmes mineurs
- La formulation « complétude fonctionnelle nulle en dehors du pilote » dans [FUNCTIONAL_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/FUNCTIONAL_AUDIT.md:37) est un peu trop absolue pour un dépôt qui couvre tout de même deux cas d'usage de consultation clairement identifiés.
- « Tout ajout de route sans versioning rendra la rétrocompatibilité impossible à garantir » dans [FUNCTIONAL_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/FUNCTIONAL_AUDIT.md:40) est trop fort. C'est une recommandation défendable, mais la preuve disponible ne permet pas d'affirmer une impossibilité.
- La recommandation faisant de `GET /deliveries/{id}` le « prérequis fonctionnel le plus basique » dans [FUNCTIONAL_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/FUNCTIONAL_AUDIT.md:55) relève d'un choix produit/roadmap, pas d'un manque prouvé par le code seul.

## Points vérifiés et corrects
- `list()` retourne bien l'intégralité de `DELIVERIES` sans transformation autre que le `JsonResponse` dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:20).
- `pending()` applique bien le filtre négatif `status !== 'livre'` dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:30), et le scénario de dérive avec un futur statut terminal est un risque concret car directement dérivé de cette logique.
- L'absence d'endpoint de mutation ou de consultation unitaire est correctement sourcée par la lecture complète de [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:1).

## Recommandations de correction
- Garder les constats sur les deux endpoints tels quels, mais assouplir les recommandations qui relèvent d'un choix de roadmap ou de gouvernance d'API.
- Reformuler la dette sur le versioning comme recommandation d'anticipation, pas comme impossibilité démontrée.
