# Déploiement — Maison WODI Core API

Ce guide s'adresse à la personne qui installe et exploite le serveur.

## Environnements

| | Développement | Préproduction | Production |
|---|---|---|---|
| Base | `wodi` locale + `wodi_test` (tests) | base dédiée | PostgreSQL managé ou dédié, sauvegardé |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `staging` / `false` | `production` / `false` |
| Données | `DemoSeeder` autorisé | données de test | réelles (`DemoSeeder` refusé) |
| Swagger `/api/docs` | activé | activé | désactivé (`API_DOCS_ENABLED=false`) |
| E-mail | `MAIL_MAILER=log` | boîte de test | SMTP / fournisseur transactionnel |

## Serveur (Linux)

Composants à installer :

- **Logiciels** : Nginx, PHP-FPM 8.4 (`pdo_pgsql`, `mbstring`, `intl`, `curl`, `zip`, `openssl`, `fileinfo`), PostgreSQL ≥ 14 avec `postgresql-contrib`, Composer.
- **HTTPS** : obligatoire (Let's Encrypt).
- **Redis** : facultatif (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`).

### Première installation

```bash
git clone … /var/www/wodi-api && cd /var/www/wodi-api
composer install --no-dev --optimize-autoloader
cp .env.example .env    # renseigner : APP_URL, DB_*, MAIL_*, CORS/SANCTUM/SESSION (voir docs/INTEGRATION.md)
php artisan key:generate
# l'extension btree_gist doit être créée par un superutilisateur si le rôle applicatif ne le peut pas :
sudo -u postgres psql -d wodi -c 'CREATE EXTENSION IF NOT EXISTS btree_gist'
php artisan migrate --force --seed          # schéma + rôles/permissions
php artisan wodi:create-super-admin direction@maisonwodi.com
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

### Processus permanents

Deux processus tournent en continu :

- **Cron** : `* * * * * cd /var/www/wodi-api && php artisan schedule:run >> /dev/null 2>&1`. Il lance les rappels toutes les 15 min et la purge des jetons expirés.
- **Queue worker**, sous systemd ou Supervisor : `php artisan queue:work --tries=3 --max-time=3600`. Il traite les e-mails, les push Expo et le webhook de la vitrine.

### Nginx (extrait)

```nginx
server {
    server_name api.maisonwodi.com;
    root /var/www/wodi-api/public;
    client_max_body_size 6m;            # images : 5 Mo max côté API
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; }
    add_header X-Content-Type-Options nosniff;
    add_header Strict-Transport-Security "max-age=31536000" always;
}
```

## Mise à jour (déploiement courant)

```bash
php artisan down --retry=30
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
php artisan queue:restart
php artisan up
```

Lancer d'abord `php artisan test` en CI, sur une base de test, jamais sur la production.

## Stockage des images

- **Configuration par défaut** : `MEDIA_DISK=public`, fichiers dans `storage/app/public/media/…`, servis par `public/storage`.
- **Stockage S3** (OVH, Scaleway, AWS…) : renseigner `AWS_*`, puis passer `MEDIA_DISK=s3` avec un bucket public en lecture pour `media/`.
- Les URL renvoyées sont absolues (HTTPS), comme l'exigent les frontends.

## Sauvegarde et restauration

```bash
# Sauvegarde quotidienne (cron 02:30) — conserver 30 jours, copie hors serveur chiffrée
pg_dump -Fc -d wodi -f /backups/wodi-$(date +%F).dump
tar czf /backups/media-$(date +%F).tgz -C /var/www/wodi-api/storage/app/public media

# Restauration (sur une base vide)
createdb wodi_restore && psql -d wodi_restore -c 'CREATE EXTENSION IF NOT EXISTS btree_gist'
pg_restore -d wodi_restore --no-owner /backups/wodi-AAAA-MM-JJ.dump
```

Testez une restauration au moins une fois par trimestre.

## Sécurité : vérifications avant mise en production

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS forcé, `SESSION_SECURE_COOKIE=true`.
- [ ] `CORS_ALLOWED_ORIGINS` et `SANCTUM_STATEFUL_DOMAINS` limités aux domaines Maison WODI.
- [ ] `API_DOCS_ENABLED=false` (ou accès protégé).
- [ ] Aucun compte `@demo.wodi` en base ; `DemoSeeder` jamais exécuté.
- [ ] Sauvegardes automatiques actives et restauration testée.
- [ ] Surveillance des erreurs : journaux `storage/logs` centralisés, ou Sentry à ajouter si souhaité.
- [ ] Rétention des données : décider la durée de conservation des messages de contact et des journaux d'activité (voir les décisions ouvertes).
