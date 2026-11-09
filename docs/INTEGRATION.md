# Intégration des frontends avec Maison WODI Core API

Ce document s'adresse aux développeurs du site Angular (`Wodi Core/site`) et de l'application Expo
(`MaisonWodi`). L'API implémente **les contrats déjà codés dans les frontends** :

- vitrine : `docs/VITRINE-API.md` ;
- back-office : `docs/ADMIN-API.md` et `admin.models.ts` ;
- mobile : `docs/API-MOBILE.md`, `src/models/` et le serveur de démo `src/demo/server.ts`.

Les écrans n'ont donc pas à changer, à une exception près : l'en-tête CSRF du back-office (§ 3).

## 1. Configuration

| Frontend | Variable | Valeur (exemple production) |
|---|---|---|
| Vitrine | `environment.api.baseUrl` (`src/environments/environment.base.ts`) | `https://api.maisonwodi.com/api/v1` |
| Back-office | `environment.admin.apiBaseUrl` (même fichier) | `https://api.maisonwodi.com/api/v1` |
| Mobile | `EXPO_PUBLIC_API_URL` (ou `extra.apiUrl` dans `app.config.ts`) | `https://api.maisonwodi.com/api/v1` |

Côté API (`.env`) :

```dotenv
APP_URL=https://api.maisonwodi.com
CORS_ALLOWED_ORIGINS=https://www.maisonwodi.com,https://maisonwodi.com
SANCTUM_STATEFUL_DOMAINS=www.maisonwodi.com,maisonwodi.com   # hôtes du back-office (sans schéma)
SESSION_DOMAIN=.maisonwodi.com      # cookie XSRF-TOKEN lisible par le site (domaine parent commun)
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict            # www. et api. sont « same-site » : Strict fonctionne
ADMIN_RESET_PASSWORD_URL="https://www.maisonwodi.com/admin/reset-password?token={token}"
MOBILE_RESET_PASSWORD_URL="maisonwodi://reset-password?token={token}"
```

En développement : vitrine et back-office sur `http://localhost:4200`, API sur `http://localhost:8000`
(valeurs par défaut de `.env.example`). Pour tester le back-office contre l'API, désactiver
`admin.demoMode` dans `environment.development.ts` et renseigner `admin.apiBaseUrl`.

## 2. Écarts entre les frontends, et comment l'API les règle

| Sujet | Vitrine | Mobile | Back-office | Solution API |
|---|---|---|---|---|
| `GET /catalog/categories` | `tagline, icon, image, imageAlt` | `order` | — | union des champs ; `image` jamais `null` (illustration locale par défaut, exigée par `NgOptimizedImage`) |
| `GET /catalog/services` | `priceFrom, isPlaceholder` | `categoryName, image, conditions, bookable, staffIds` | — | union des champs |
| `GET /team` | `name, role, photoAlt, isPlaceholder` | `firstName, lastName, jobTitle, serviceIds, rating, workingDays` | — | union des champs ; aucun téléphone ni e-mail |
| `GET /gallery` | `src, alt, caption, orientation, isIllustration` | `title, url` | `published, createdAt` (admin) | union des champs |
| `POST /auth/login` | — | `{ tokens, user }` | 204 + cookie | choix selon l'origine (Sanctum « stateful ») ; session réservée aux rôles d'administration |
| `GET /auth/me` | — | `role` (`client`/`barber`/`admin`), `staffId`… | `roles[]`, `permissions[]` | union ; permissions limitées à l'espace du rôle |
| Coordonnées | `/site-content` | `/salon` | Contenus **et** Paramètres | une seule source (stockée une fois) |

Autres conventions respectées :

- JSON camelCase, **sans enveloppe** `{ data }` ;
- erreurs au format `{ message, errors: { champ: "message" } }`, avec **un seul message par champ** (et non un tableau) ;
- pagination admin `{ items, total, page, pageSize }`, avec `pageSize` jusqu'à 1000 (utilisé par `CrudService.all()`) ;
- 404 pour la ressource d'un autre utilisateur ;
- 409 pour un créneau pris ou une suppression interdite.

## 3. Modification faite dans le back-office Angular

`src/app/admin/core/http/admin-api.interceptor.ts` ajoute maintenant l'en-tête `X-XSRF-TOKEN` aux écritures
(POST/PUT/PATCH/DELETE). Le cookie est récupéré au besoin via `GET {apiBaseUrl}/sanctum/csrf-cookie`, et la
requête est rejouée une fois en cas de 419.

Pourquoi c'était nécessaire : l'intercepteur XSRF intégré d'Angular ignore les URL absolues. Sans cette
modification, toute écriture du back-office (y compris la connexion) recevrait un 419.

✅ Compilée et testée côté front (`ng build`, `ng test` : 3 tests dédiés). `reset-password` est exclu de la gestion du 401.

## 4. Ce qui reste à faire côté frontends

> **Mise à jour : les points 1 à 5 sont faits dans le back-office Angular** (page `/admin/reset-password`, validation des absences dans Plannings, bouton « compte mobile » sur les fiches Équipe, page `/admin/messages`, nouveaux types de notification). Les `link` de l'API sont redirigés par le routeur Angular. Le refus d'une absence reste sans motif (décision à prendre). Restent les points 6 à 8. Test de bout en bout du back-office contre l'API réelle : 37/37, plus la réinitialisation du mot de passe (3 cas) et l'invitation barbier.

Ces points sont rangés par priorité. L'API est prête pour chacun d'eux.

### Bloquant

1. **Back-office : page `/admin/reset-password?token=`.** Elle n'existe pas, alors que l'invitation d'un
   administrateur et « mot de passe oublié » envoient vers elle. Elle doit appeler
   `POST /auth/reset-password { token, password }`.

### Fonctionnel

2. **Back-office : validation des absences.** Les barbiers demandent leurs congés depuis le mobile (statut
   `pending`). Il faut ajouter des boutons qui appellent `POST /admin/unavailabilities/:id/approve` et
   `/reject`. La liste propose déjà le filtre `status=pending` et renvoie le champ `status`.
3. **Back-office : compte mobile d'un barbier.** Il faut un bouton qui appelle
   `POST /admin/staff/:id/account { email }`, ce qui envoie une invitation. Sans ce bouton, un barbier ne
   peut pas se connecter à l'application. Le champ `hasAccount` est renvoyé par l'API.
4. **Back-office : notifications.** Il faut ajouter deux types à `NotificationType` et à leurs libellés :
   - `contact_message` (message reçu depuis le site) ;
   - `absence_request` (demande d'absence d'un barbier).
5. **Back-office : messages de contact.** Il faut un écran qui consomme `/admin/contact-messages`. Les
   endpoints existent déjà : liste, détail, `read`, `status`.

### Confort

6. **Back-office : champs sans écran.** Ils sont acceptés et renvoyés par `GET/PUT /admin/settings`, avec des
   valeurs par défaut prudentes :
   - `general.tagline` ;
   - `addressDetails` (rue, quartier, ville, accès, coordonnées GPS) ;
   - `legal` (mentions légales de la vitrine) ;
   - `apps` (liens vers les stores) ;
   - `booking.allowAnyStaff`, `booking.bookingWindowDays`, `booking.paymentMethods`, `booking.platformUrl`.
7. **Back-office : affectation prestations ↔ barbiers.** Les champs `staffIds` (prestation) et `serviceIds`
   (barbier) sont acceptés par l'API. Par défaut, chaque nouvelle prestation est affectée à tous les barbiers
   actifs.
8. **Vitrine : reconstruction automatique.** Il faut brancher le webhook de build. L'API appelle
   `VITRINE_BUILD_WEBHOOK_URL`, signé en HMAC-SHA256 dans l'en-tête `X-Wodi-Signature`, regroupé sur
   2 minutes, après chaque modification publique.

### Mobile

9. **Aucune adaptation requise.** Le serveur de démo `src/demo/server.ts` et l'API ont le même
   comportement : mêmes routes, mêmes formats et mêmes règles (`allowedActions`, délais, transitions,
   409/404).

## 5. Différences assumées avec le cahier des charges initial

Le cahier des charges proposait `/client/*`, `/professional/*`, `/admin/customers`,
`/admin/professionals`, `/admin/bookings`, `PATCH …/status`, des réponses `{ data }` en snake_case et
`start_at` en date-heure ISO.

Les frontends étant déjà construits sur leurs propres contrats, l'API suit ces contrats :

- routes `/me/*`, `/pro/*`, `/admin/reservations`, `/admin/clients`, `/admin/staff` ;
- actions `POST /:id/confirm`, `/start`, etc. ;
- `date` + `time` en heure locale.

Les fonctionnalités demandées sont toutes présentes sous ces noms.
