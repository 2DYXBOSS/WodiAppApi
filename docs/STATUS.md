# État des fonctionnalités — Maison WODI Core API v1

## Opérationnel et testé

Tout ce qui suit est couvert par les 58 tests automatisés (PostgreSQL `wodi_test`).

- **Authentification**
  - Inscription client, avec rôle forcé à `client`.
  - Connexion mobile : jetons d'accès (2 h) et de renouvellement (30 j), avec rotation.
  - Connexion back-office : session HttpOnly avec CSRF.
  - Déconnexion avec révocation des jetons.
  - Mot de passe oublié sans énumération des comptes ; réinitialisation par lien à usage unique ; changement de mot de passe.
  - Comptes désactivés refusés ; limitation du débit (429).
- **Rôles et permissions**
  - Rôles `super_admin`, `admin`, `manager`, `barber` et `client`.
  - Matrice de 15 permissions back-office et 5 permissions barbier.
  - Contrôle systématique côté serveur ; 404 sur les ressources d'autrui.
- **Vitrine**
  - Catégories, prestations, équipe, galerie, `/site-content` filtré.
  - Formulaire de contact avec pot de miel, limitation du débit et notification des administrateurs.
- **Moteur de réservation**
  - Créneaux et jours disponibles, en tenant compte :
    - des horaires du salon et du professionnel, des pauses et des jours exceptionnels ;
    - des indisponibilités, des absences et des réservations existantes ;
    - du délai de prévenance, de la fenêtre de réservation et du choix « premier disponible ».
  - Création, déplacement et annulation (avec délai d'annulation).
  - Transitions de statut contrôlées, historique horodaté avec l'auteur, instantanés nom/prix/durée.
  - Anti double réservation : verrou, recalcul et contrainte d'exclusion PostgreSQL. Vérifié en charge concurrente.
- **Espace barbier**
  - Tableau de bord, liste des rendez-vous, agenda.
  - Actions confirmer, refuser, commencer, terminer, absence (selon les permissions).
  - Horaires hebdomadaires avec pauses : contrôlés par rapport aux horaires du salon ; refusés (409) s'ils laissent une réservation à venir hors horaires.
  - Indisponibilités et demandes d'absence ; activation de ses propres prestations ; profil ; activité.
- **Back-office**
  - Tableau de bord : mêmes calculs que la démo Angular.
  - Gestion complète, avec filtres et tri, de :
    - réservations, clients (CRM et statut VIP), catégories, prestations ;
    - équipe, plannings, absences (avec validation et refus) ;
    - paiements manuels, promotions, historique de fidélité, galerie ;
    - téléversement de médias, contenus, paramètres, notifications, messages de contact.
  - Comptes administrateurs : invitation par e-mail, interdiction de l'auto-promotion, protection du dernier super administrateur.
- **Paiements V1**
  - Encaissement manuel, partiel ou total, plafonné au reste à payer.
  - Refus des doubles saisies, historique, statut de paiement de la réservation.
- **Notifications**
  - Centre de notifications in-app (tous rôles) et préférences.
  - Enregistrement des appareils Expo ; push via Expo en file d'attente, désactivé tant que `EXPO_PUSH_ENABLED=false`.
  - E-mails aux administrateurs selon les paramètres ; rappels J-1 planifiés.
- **Exploitation**
  - Journal des actions sensibles (`activity_logs`).
  - OpenAPI générée depuis le code ; tableau des endpoints ; seeders ; création sécurisée du premier administrateur.

## Nécessite un prestataire externe

- **Paiement en ligne** (Orange Money, MTN MoMo, Wave, carte) : volontairement **non simulé**.
  - `onlinePaymentAvailable` vaut `false`.
  - La table `payments.transaction_reference` (unique) est prête pour des webhooks idempotents.
- **Push Expo en production** : renseigner `EXPO_PUSH_ENABLED=true` (et `EXPO_ACCESS_TOKEN` si la sécurité renforcée est activée sur le projet Expo).
- **E-mail transactionnel** : configurer `MAIL_*`. Sans configuration, les e-mails sont seulement journalisés.
- **Canal WhatsApp** des notifications administrateur : le paramètre existe, mais aucun envoi n'est implémenté faute de fournisseur (API WhatsApp Business).

## Décisions métier à prendre (valeurs par défaut prudentes en attendant)

| Sujet | Valeur actuelle | Où la changer |
|---|---|---|
| Tarifs, durées, horaires officiels | aucun (seule la démo en contient, marqués fictifs) | back-office |
| Confirmation automatique des réservations | non (`pending` → validation du barbier) | `/admin/settings` → `booking.autoConfirm` |
| Délai de prévenance / d'annulation | 2 h / 24 h | `/admin/settings` |
| Acompte | aucun (0 %) ; aucune règle d'encaissement associée | à définir avec le salon |
| Politique de no-show (pénalité, blocage) | aucune : simple statut `no_show` | à définir |
| Avantages VIP | liste vide ; passage VIP manuel | `/admin/settings` → `loyalty.vipBenefits` |
| Points de fidélité | non attribués (seules les visites sont historisées) | à définir |
| Chiffre d'affaires visible par les barbiers | non (`activity.revenue.view` non attribuée) | matrice `app/Enums/Permission.php` |
| Inscription mobile avec un téléphone déjà connu du salon | refusée (409, « contactez le salon ») pour éviter l'appropriation d'un historique | à valider ; une procédure de rattachement est possible |
| Rétention (messages de contact, journaux) | conservation illimitée | à définir (RGPD / loi ivoirienne 2013-450) |
| Vérification de l'adresse e-mail à l'inscription | non exigée | à décider |
| Remboursements | non pris en charge (statut `refunded` réservé) | avec le prestataire de paiement |
