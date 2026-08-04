# Relecture — SECURITY_ROBUSTNESS_AUDIT.md

## Verdict global
À corriger — l'audit pointe des sujets plausibles, mais il transforme trop facilement l'absence de briques de sécurité visibles dans le code en exposition effective prouvée. La qualification preuve/conséquence doit être resserrée.

## Problèmes bloquants
- Le constat « Les endpoints sont pleinement publics » dans [SECURITY_ROBUSTNESS_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-3858-4d53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/SECURITY_ROBUSTNESS_AUDIT.md:15) est présenté en `VÉRIFIÉ_CODE`, alors que le dépôt ne contient pas de bootstrap HTTP observable. Ce qui est prouvé par le code est plus étroit : absence d'attribut `#[IsGranted]`, absence de `security.yaml`, et deux méthodes de contrôleur sans garde visible dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-3858-4d53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:19). L'exposition effective reste une conséquence hypothétique.
- Le résumé de [SECURITY_ROBUSTNESS_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/SECURITY_ROBUSTNESS_AUDIT.md:11) affirme « profil de risque faible » puis liste des risques de prod sans ancrer explicitement leur statut prospectif. Le lecteur peut comprendre un niveau de risque actuel alors que le fichier lui-même rappelle ensuite que la surface d'attaque « reste théorique ». Ce calibrage mélange risque présent et risque conditionnel.

## Problèmes mineurs
- « Aucune dépendance externe » dans [SECURITY_ROBUSTNESS_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/SECURITY_ROBUSTNESS_AUDIT.md:7) est ambigu : le projet n'appelle aucun service externe, mais il dépend bien de packages tiers dans [composer.json](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/composer.json:6).
- Le risque « aucune couche ne garantit une réponse HTTP structurée ni un log exploitable » dans [SECURITY_ROBUSTNESS_AUDIT.md](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.onboarding/audits/SECURITY_ROBUSTNESS_AUDIT.md:50) dépasse la preuve disponible : aucun flux HTTP réel n'a été observé.

## Points vérifiés et corrects
- Il n'y a bien aucun paramètre d'entrée dans `list()` et `pending()` dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:20), ce qui soutient l'absence actuelle de surface d'injection côté contrôleur.
- Le dépôt ne contient ni `.env`, ni `.env.dist`, et `.gitignore` exclut bien `.env.local` dans [.gitignore](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/.gitignore:3).
- Les routes sont bien limitées à `GET` dans [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:19) et [src/Controller/DeliveryController.php](/paperclip/instances/default/projects/be2f6065-a710-4a1d-8bb7-531efdbc6f23/4e3d3e53-b8fd-df0d579cb6ed/shift-pilot-symfony/src/Controller/DeliveryController.php:25). La phrase de l'audit qui conditionne le `405` à un « contexte Symfony bootstrapé » est bien qualifiée.

## Recommandations de correction
- Réduire les constats `VÉRIFIÉ_CODE` à ce qui est vraiment lisible dans le dépôt, puis reformuler l'exposition publique, l'absence de protection effective et le comportement d'erreur en `HYPOTHÈSE` conditionnée à un déploiement HTTP réel.
- Séparer explicitement risque actuel et risque futur pour éviter un calibrage trop fort sur un pilote statique non observé en exécution.
