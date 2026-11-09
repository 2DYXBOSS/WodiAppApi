# Endpoints — Maison WODI Core API (`/api/v1`)

> Généré par `php artisan wodi:openapi` à partir des routes. Détail : `/api/docs` (Swagger UI).

## Back-office

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `GET` | `/admin/categories` | Catégories | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `POST` | `/admin/categories` | Créer une catégorie | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `GET` | `/admin/categories/{category}` | Catégorie | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `DELETE` | `/admin/categories/{category}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `PATCH` | `/admin/categories/{category}` | Modifier une catégorie | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `GET` | `/admin/clients` | Clients | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `POST` | `/admin/clients` | Créer un client | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `GET` | `/admin/clients/{client}` | Client | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `DELETE` | `/admin/clients/{client}` | Supprimer (si aucune réservation) | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `PATCH` | `/admin/clients/{client}` | Modifier un client | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `GET` | `/admin/clients/{client}/bookings` | Réservations du client | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `GET` | `/admin/clients/{client}/loyalty` | Historique fidélité du client | Authentification requise · rôle `admin` · permission(s) `clients.manage` |
| `POST` | `/admin/clients/{client}/loyalty` | Statut fidélité | Authentification requise · rôle `admin` · permission(s) `clients.manage`, `loyalty.manage` |
| `GET` | `/admin/clients/{client}/payments` | Paiements du client | Authentification requise · rôle `admin` · permission(s) `clients.manage`, `payments.view` |
| `GET` | `/admin/contact-messages` | Messages de contact | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `GET` | `/admin/contact-messages/{message}` | Message | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `POST` | `/admin/contact-messages/{message}/read` | Marquer comme lu | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `PATCH` | `/admin/contact-messages/{message}/status` | Changer le statut | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `GET` | `/admin/dashboard` | Tableau de bord | Authentification requise · rôle `admin` · permission(s) `dashboard.view` |
| `GET` | `/admin/gallery` | Galerie | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `POST` | `/admin/gallery` | Ajouter une image | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `GET` | `/admin/gallery/{image}` | Image | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `DELETE` | `/admin/gallery/{image}` | Supprimer (et le fichier) | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `PATCH` | `/admin/gallery/{image}` | Modifier | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `POST` | `/admin/gallery/{image}/toggle` | Publier / dépublier | Authentification requise · rôle `admin` · permission(s) `gallery.manage` |
| `GET` | `/admin/loyalty/events` | Historique fidélité | Authentification requise · rôle `admin` · permission(s) `loyalty.manage` |
| `POST` | `/admin/media` | Téléverser une image | Authentification requise · rôle `admin` · limitation : uploads |
| `GET` | `/admin/notifications` | Mes notifications | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `POST` | `/admin/notifications/read-all` | Tout marquer comme lu | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `GET` | `/admin/notifications/unread-count` | Nombre de non lues | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `GET` | `/admin/notifications/{notification}` | Notification | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `POST` | `/admin/notifications/{notification}/read` | Marquer comme lue | Authentification requise · rôle `admin` · permission(s) `notifications.view` |
| `GET` | `/admin/payments` | Paiements | Authentification requise · rôle `admin` · permission(s) `payments.view` |
| `POST` | `/admin/payments` | Enregistrer un paiement MANUEL | Authentification requise · rôle `admin` · permission(s) `payments.manage` |
| `GET` | `/admin/payments/{payment}` | Paiement | Authentification requise · rôle `admin` · permission(s) `payments.view` |
| `GET` | `/admin/permissions` | Liste des permissions | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `GET` | `/admin/promotions` | Promotions | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `POST` | `/admin/promotions` | Créer | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `GET` | `/admin/promotions/{promotion}` | Promotion | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `DELETE` | `/admin/promotions/{promotion}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `PATCH` | `/admin/promotions/{promotion}` | Modifier | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `POST` | `/admin/promotions/{promotion}/toggle` | Activer / désactiver | Authentification requise · rôle `admin` · permission(s) `promotions.manage` |
| `GET` | `/admin/reservations` | Réservations | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `POST` | `/admin/reservations` | Créer une réservation | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `GET` | `/admin/reservations/{reservation}` | Réservation | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `DELETE` | `/admin/reservations/{reservation}` | Interdit (409) : annuler à la place | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `PATCH` | `/admin/reservations/{reservation}` | Modifier une réservation | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `POST` | `/admin/reservations/{reservation}/{action}` | Action : confirm | start | complete | no-show | cancel | Authentification requise · rôle `admin` · permission(s) `reservations.manage` |
| `GET` | `/admin/roles` | Rôles et permissions associées | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `GET` | `/admin/services` | Prestations | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `POST` | `/admin/services` | Créer une prestation | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `GET` | `/admin/services/{service}` | Prestation | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `DELETE` | `/admin/services/{service}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `PATCH` | `/admin/services/{service}` | Modifier une prestation | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `POST` | `/admin/services/{service}/toggle` | Activer / désactiver | Authentification requise · rôle `admin` · permission(s) `services.manage` |
| `GET` | `/admin/settings` | Paramètres du salon | Authentification requise · rôle `admin` · permission(s) `settings.manage` |
| `PUT` | `/admin/settings` | Modifier les paramètres | Authentification requise · rôle `admin` · permission(s) `settings.manage` |
| `GET` | `/admin/site-content` | Contenus du site | Authentification requise · rôle `admin` · permission(s) `content.manage` |
| `PUT` | `/admin/site-content` | Modifier les contenus | Authentification requise · rôle `admin` · permission(s) `content.manage` |
| `GET` | `/admin/staff` | Équipe | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `POST` | `/admin/staff` | Ajouter un professionnel | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `GET` | `/admin/staff/{staff}` | Professionnel | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `DELETE` | `/admin/staff/{staff}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `PATCH` | `/admin/staff/{staff}` | Modifier | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `POST` | `/admin/staff/{staff}/account` | Créer le compte mobile du barbier (invitation) | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `GET` | `/admin/staff/{staff}/schedule` | Horaires hebdomadaires | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `PUT` | `/admin/staff/{staff}/schedule` | Modifier les horaires | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `POST` | `/admin/staff/{staff}/toggle` | Activer / désactiver | Authentification requise · rôle `admin` · permission(s) `team.manage` |
| `GET` | `/admin/unavailabilities` | Congés / absences | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `POST` | `/admin/unavailabilities` | Saisir une absence (validée d’office) | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `GET` | `/admin/unavailabilities/{unavailability}` | Absence | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `DELETE` | `/admin/unavailabilities/{unavailability}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `PATCH` | `/admin/unavailabilities/{unavailability}` | Modifier | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `POST` | `/admin/unavailabilities/{unavailability}/approve` | Valider une demande | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `POST` | `/admin/unavailabilities/{unavailability}/reject` | Refuser une demande | Authentification requise · rôle `admin` · permission(s) `schedules.manage` |
| `GET` | `/admin/users` | Comptes du back-office | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `POST` | `/admin/users` | Inviter un administrateur | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `GET` | `/admin/users/{user}` | Compte | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `DELETE` | `/admin/users/{user}` | Supprimer | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `PATCH` | `/admin/users/{user}` | Modifier | Authentification requise · rôle `admin` · permission(s) `users.manage` |
| `POST` | `/admin/users/{user}/toggle` | Activer / désactiver | Authentification requise · rôle `admin` · permission(s) `users.manage` |

## Authentification

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `POST` | `/auth/change-password` | Changement de mot de passe | Authentification requise · limitation : auth-sensitive |
| `POST` | `/auth/forgot-password` | Mot de passe oublié | Public (sans authentification) · limitation : auth-sensitive |
| `POST` | `/auth/login` | Connexion | Public (sans authentification) · limitation : login |
| `POST` | `/auth/logout` | Déconnexion (révoque le jeton et son jeton de renouvellement, ou la session) | Authentification requise |
| `GET` | `/auth/me` | Utilisateur connecté | Authentification requise |
| `POST` | `/auth/refresh` | Renouvellement des jetons (rotation) | Public (sans authentification) · limitation : login |
| `POST` | `/auth/register` | Inscription client | Public (sans authentification) · limitation : auth-sensitive |
| `POST` | `/auth/reset-password` | Réinitialisation | Public (sans authentification) · limitation : auth-sensitive |

## Disponibilités

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `GET` | `/availability/days` | Jours disponibles | Authentification requise |
| `GET` | `/availability/slots` | Créneaux réservables | Authentification requise |

## Public

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `GET` | `/booking-rules` | Règles publiques de réservation | Public (sans authentification) |
| `GET` | `/catalog/categories` | Catégories actives (triées) | Public (sans authentification) |
| `GET` | `/catalog/services` | Prestations actives | Public (sans authentification) |
| `GET` | `/catalog/services/{service}` | Détail d’une prestation | Public (sans authentification) |
| `POST` | `/contact-messages` | Message du formulaire de contact | Public (sans authentification) · limitation : contact |
| `GET` | `/gallery` | Galerie publiée | Public (sans authentification) |
| `GET` | `/promotions` | Promotions en cours | Public (sans authentification) |
| `GET` | `/salon` | Informations du salon (mobile) | Public (sans authentification) |
| `GET` | `/site-content` | Informations publiques du salon (vitrine) | Public (sans authentification) |
| `GET` | `/team` | Équipe publique | Public (sans authentification) |
| `GET` | `/team/{professional}` | Membre de l’équipe | Public (sans authentification) |

## Compte

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `PATCH` | `/me` | Modifier son profil | Authentification requise |
| `POST` | `/me/avatar` | Photo de profil | Authentification requise · limitation : uploads |
| `POST` | `/me/devices` | Enregistrer un jeton Expo Push | Authentification requise |
| `DELETE` | `/me/devices/{token}` | Retirer un appareil (déconnexion) | Authentification requise |
| `GET` | `/me/notification-preferences` | Préférences de notification | Authentification requise |
| `PUT` | `/me/notification-preferences` | Modifier les préférences | Authentification requise |
| `GET` | `/me/notifications` | Mes notifications (100 dernières) | Authentification requise |
| `POST` | `/me/notifications/read-all` | Tout marquer comme lu | Authentification requise |
| `POST` | `/me/notifications/{notification}/read` | Marquer comme lue | Authentification requise |

## Client

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `GET` | `/me/bookings` | Mes réservations | Authentification requise · rôle `client` |
| `POST` | `/me/bookings` | Réserver | Authentification requise · rôle `client` |
| `GET` | `/me/bookings/{booking}` | Ma réservation | Authentification requise · rôle `client` |
| `POST` | `/me/bookings/{booking}/cancel` | Annuler | Authentification requise · rôle `client` |
| `POST` | `/me/bookings/{booking}/reschedule` | Déplacer | Authentification requise · rôle `client` |
| `GET` | `/me/loyalty` | Ma fidélité | Authentification requise · rôle `client` |
| `GET` | `/me/payments` | Mes paiements | Authentification requise · rôle `client` |

## Professionnel

| Méthode | Route | Description | Accès |
|---|---|---|---|
| `GET` | `/pro/absences` | Mes absences | Authentification requise · rôle `barber` |
| `POST` | `/pro/absences` | Demander une absence | Authentification requise · rôle `barber` |
| `DELETE` | `/pro/absences/{absence}` | Retirer une demande | Authentification requise · rôle `barber` |
| `GET` | `/pro/activity` | Activité | Authentification requise · rôle `barber` |
| `GET` | `/pro/agenda` | Agenda sur une période | Authentification requise · rôle `barber` |
| `GET` | `/pro/bookings` | Mes rendez-vous | Authentification requise · rôle `barber` |
| `GET` | `/pro/bookings/{booking}` | Rendez-vous | Authentification requise · rôle `barber` |
| `POST` | `/pro/bookings/{booking}/{action}` | Action : confirm | decline | start | complete | no-show | Authentification requise · rôle `barber` |
| `GET` | `/pro/dashboard` | Tableau de bord du jour | Authentification requise · rôle `barber` |
| `GET` | `/pro/profile` | Mon profil professionnel | Authentification requise · rôle `barber` |
| `PATCH` | `/pro/profile` | Modifier les champs autorisés | Authentification requise · rôle `barber` |
| `GET` | `/pro/schedule` | Horaires hebdomadaires | Authentification requise · rôle `barber` |
| `PUT` | `/pro/schedule` | Modifier ses horaires | Authentification requise · rôle `barber` |
| `GET` | `/pro/services` | Mes prestations | Authentification requise · rôle `barber` |
| `POST` | `/pro/services/{service}/toggle` | Activer / retirer une prestation | Authentification requise · rôle `barber` |
| `GET` | `/pro/unavailabilities` | Mes indisponibilités à venir | Authentification requise · rôle `barber` |
| `POST` | `/pro/unavailabilities` | Ajouter une indisponibilité | Authentification requise · rôle `barber` |
| `DELETE` | `/pro/unavailabilities/{unavailability}` | Supprimer | Authentification requise · rôle `barber` |
