# PROJECT.md — shift-pilot-symfony

Contrat de maintenance SHIFT pour ce dépôt. Mis à jour via PR, jamais manuellement.

## Stack

- **Type** : Symfony 5.4 (micro-API, pas d'UI)
- **Gestionnaire de paquets** : Composer
- **Runtime PHP** : 8.3 (confirmé par interaction Paperclip, 2026-08-04 — voir CLA-243)
- **Contrainte déclarative** : `>=8.1` (plancher, non la version runtime)
- **platform.php** : `8.3` (fixé dans `composer.json > config.platform.php`)

## Couverture Git

```yaml
git_coverage:
  composer_json: true        # suivi dans Git
  composer_lock: true        # suivi dans Git (ajouté par CLA-243)
  vendor: false              # gitignored (/vendor/)
  var: false                 # gitignored (/var/)
```

## Canal d'écriture

```yaml
write_channel:
  type: git_to_staging       # README : "Géré par Git -> staging"
  composer_install_auto: unknown   # non confirmé (CLA-243 interaction 2026-08-04)
  note: >
    Le déploiement Git → staging est confirmé.
    L'exécution automatique de `composer install` après merge n'est pas confirmée.
    À clarifier pour garantir que le lockfile est pris en compte au déploiement.
```

## Mises à jour autorisées

```yaml
allowed_updates:
  composer:
    patch_within_same_major: true
    minor_within_same_major: true
    major: report_only
```

## Contrôle de fumée

```yaml
smoke:
  urls: []     # aucune URL de preview/staging déclarée pour l'instant
  runtime_verified: false
  note: Pas d'environnement de preview accessible depuis une branche de PR.
```
