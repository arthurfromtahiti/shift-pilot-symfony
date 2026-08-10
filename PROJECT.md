# PROJECT.md — shift-pilot-symfony

Contrat de maintenance SHIFT pour ce dépôt. Mis à jour via PR, jamais manuellement.

## Stack

- **Type** : Symfony 5.4 (micro-API, pas d'UI)
- **Gestionnaire de paquets** : Composer
- **Runtime PHP (CI)** : 8.1 (déclaré dans `.github/workflows/ci.yml` et `deploy.yml`)
- **Runtime PHP (production/staging)** : 8.3 (confirmé par interaction Paperclip CLA-243, 2026-08-04)
- **Contrainte déclarative** : `>=8.1` (plancher, non la version runtime)
- **platform.php** : `8.1` (fixé dans `composer.json > config.platform.php`, correspond à la CI)

## Couverture Git

```yaml
git_coverage:
  composer_json: true        # suivi dans Git
  composer_lock: true        # suivi dans Git (ajouté par maintenance/shia-644-composer)
  vendor: false              # gitignored (/vendor/)
  var: false                 # gitignored (/var/)
```

## Canal d'écriture

```yaml
write_channel:
  type: git_to_staging
  trigger: push sur staging ou main
  composer_install_auto: true   # deploy.yml exécute `composer install` avant déploiement
  note: >
    Push sur staging → workflow deploy.yml → composer install → tests → contrôle de fumée
    GET /deliveries → publication dans deployed/staging/version.json.
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
  urls:
    - path: /deliveries
      expected_status: 200
  runtime_verified: false
  note: >
    Vérifié par GitHub Actions (deploy.yml) — pas d'URL externe stable pour un
    contrôle depuis une branche de PR. La CI ci.yml effectue le contrôle avant merge.
```
