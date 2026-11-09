# Maison WODI Core API

Backend central (Laravel 13 · PHP 8.4 · PostgreSQL 17 · Sanctum · Spatie Permission) qui alimente :

| Client | Projet | Contrat respecté | Authentification |
|---|---|---|---|
| Site vitrine Angular | `Wodi Core/site` | `docs/VITRINE-API.md` | aucune (public) |
| Back-office Angular `/admin` | `Wodi Core/site` | `docs/ADMIN-API.md` | cookie de session HttpOnly + CSRF |
| Application mobile Expo (client + barbier) | `MaisonWodi` | `docs/API-MOBILE.md` | jetons Bearer (accès + renouvellement) |

Une seule API (`/api/v1`), une seule base, les mêmes règles métier pour tous : une réservation faite sur
mobile apparaît dans le planning du barbier et dans le back-office ; une indisponibilité saisie par un
barbier retire immédiatement les créneaux proposés aux clients.

- **Intégration des frontends** (configuration, écarts traités, points ouverts) : [`docs/INTEGRATION.md`](docs/INTEGRATION.md)
- **Tableau des endpoints** (généré) : [`docs/ENDPOINTS.md`](docs/ENDPOINTS.md) · **Swagger UI** : `GET /api/docs`
- **Déploiement, sauvegardes, restauration** : [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)

## Démarrage local

Prérequis : PHP 8.4 avec `pdo_pgsql`, `mbstring`, `openssl`, `intl`, `curl`, `zip` ; Composer ; PostgreSQL ≥ 14
(extension `btree_gist`, fournie par `postgresql-contrib`).

```bash
composer install
cp .env.example .env && php artisan key:generate
createdb wodi && createdb wodi_test        # base de dev + base de tests séparée
php artisan migrate --seed                 # rôles et permissions uniquement
php artisan db:seed --class=DemoSeeder     # (optionnel) données FICTIVES de démonstration
php artisan storage:link
php artisan serve                          # http://localhost:8000/api/v1
php artisan queue:work                     # e-mails, push Expo, webhook vitrine
```

Comptes de démonstration (`DemoSeeder`, refusé en production) : `admin@demo.wodi`, `manager@demo.wodi`,
`barbier@demo.wodi`, `client@demo.wodi` — mot de passe `Demo1234`. Tarifs, durées et horaires de démo
sont fictifs et marqués `isPlaceholder`.

Premier administrateur réel (installation) : `php artisan wodi:create-super-admin prenom.nom@maisonwodi.com`
(mot de passe saisi de façon masquée, 12 caractères minimum). Aucun compte administrateur n'est créé par les seeders.

## Commandes

| Commande | Rôle |
|---|---|
| `php artisan test` | 58 tests (PostgreSQL `wodi_test`, jamais la base de dev) |
| `php artisan wodi:openapi` | régénère `public/docs/openapi.json` et `docs/ENDPOINTS.md` depuis les routes |
| `php artisan wodi:create-super-admin {email}` | premier super administrateur |
| `php artisan wodi:send-reminders` | rappels J-1 (planifié toutes les 15 min) |
| `vendor/bin/pint` | formatage du code |

## Architecture

Monolithe Laravel modulaire, sans couche superflue :

```
app/
  Enums/            statuts (réservation, paiement, absence), rôles, matrice de permissions
  Http/Controllers/Api/V1/{Public,Auth,Me,Client,Pro,Admin}   contrôleurs légers
  Http/Requests/    validation (Form Requests), messages en français
  Http/Resources/   formats JSON exacts attendus par les frontends (camelCase, sans enveloppe)
  Http/Middleware/  rôle (role.is), compte actif + type de jeton (active), JSON UTF-8
  Services/         règles métier : AvailabilityService (créneaux), BookingService (réservations),
                    PaymentService, ScheduleService, SettingsService, Notifier, AuthTokenService…
  Jobs/             push Expo, webhook de reconstruction de la vitrine
  Support/          horloge du salon (Africa/Abidjan), pagination, médias
database/migrations PostgreSQL : UUID, clés étrangères, contraintes CHECK, contrainte d'exclusion
docs/               intégration, déploiement, endpoints, métadonnées OpenAPI
```

### Anti double réservation (3 niveaux)

1. Verrou `SELECT … FOR UPDATE` sur le(s) professionnel(s) concerné(s) pendant la transaction ;
2. recalcul complet de la disponibilité **dans** la transaction ;
3. contrainte PostgreSQL `bookings_no_overlap` (`EXCLUDE USING gist`) : deux réservations actives d'un même
   professionnel ne peuvent jamais se chevaucher → HTTP 409.

Vérifié : 10 requêtes simultanées sur le même créneau → 1 × 201, 9 × 409, une seule ligne en base.

### Conventions

- Dates `YYYY-MM-DD` et heures `HH:mm` dans le fuseau du salon ; stockage UTC (`timestamptz`) ; horodatages ISO 8601.
- Montants : entiers XOF. Instantanés du nom et du prix de la prestation sur chaque réservation.
- Erreurs : `{ "message": "…", "errors": { "champ": "message" } }` ; 401/403/404/409/422/429 selon le contrat.
- Une ressource d'autrui renvoie **404** (et non 403) pour ne pas révéler son existence.
