# Biblio

Monorepo: **frontend** (Next.js) + **backend** (Laravel + JWT + Swagger UI).

## Description (ce que fait le système)

Biblio est une application de gestion de bibliothèque en ligne avec 2 espaces:

- **Espace usager**: consulter/rechercher le catalogue, demander un emprunt, suivre ses demandes, consulter ses amendes et son historique.
- **Espace admin**: gérer le catalogue et les utilisateurs, piloter les emprunts/retours, paramétrer les règles, suivre les relances et le reporting.

### Fonctionnalités principales

- **Authentification JWT**: inscription/connexion, routes protégées, rôle `admin`.
- **Catalogue**: liste/recherche des livres (titre, auteur, ISBN) avec stock (exemplaires dispo / total).
- **Emprunts (workflow)**:
  - l’usager lance une *demande d’emprunt* depuis la recherche
  - **la date de retour est choisie au moment de la demande** (avant validation), bornée par la règle `loan.max_days`
  - protection anti-doublons (une seule demande active par usager/livre) + blocage si aucun exemplaire disponible
  - l’usager peut ensuite demander le retour depuis “Mon compte” (statut `return_requested`), et l’admin confirme le retour (statut `returned`, ré-incrémente le stock)

### Modules avancés (règles, sanctions, relances, reporting)

- **Paramétrage** des règles en base (durée max, délais de grâce, amende/jour, plafond, fréquence des relances, politiques de blocage) via `/admin` et l’API.
- **Sanctions / amendes**:
  - calcul automatique des amendes sur les emprunts en retard (planifié)
  - blocage configurable des nouveaux emprunts en cas de retards et/ou d’amendes impayées au-delà d’un seuil
  - gestion admin (marquer payé / annuler)
- **Historique / audit**: journalisation des événements clés (demandes, changements de statut, actions liées aux amendes) visible côté usager et admin.
- **Relances**: rappels avant échéance + relances de retard (planifié) avec dédoublonnage en base.
- **Reporting**: endpoint d’aperçu pour l’admin (vue synthétique de l’activité).

## Prérequis

- Node.js 20+
- PHP 8.3+
- Composer 2+

## Backend (Laravel)

```powershell
cd backend
composer install
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --seed
php artisan l5-swagger:generate
php artisan serve --host=127.0.0.1 --port=8000
# alternativement : PHP built-in server + routeur Laravel (évite les 404 sur /docs/asset/*)
php -S 127.0.0.1:8000 -t public server.php

# automatisations (amendes + relances)
# - en dev, tu peux lancer le scheduler en continu:
php artisan schedule:work
# - ou déclencher une exécution ponctuelle:
php artisan schedule:run
# - commandes directes:
php artisan biblio:process-overdues
php artisan biblio:send-reminders
```

- API: `http://localhost:8000/api`
- Swagger UI: `http://localhost:8000/api/documentation`
- Compte admin seedé:
  - Email: `admin@biblio.local`
  - Mot de passe: `admin12345`
  - (modifiable via `ADMIN_EMAIL` / `ADMIN_PASSWORD` dans `backend/.env`)

> Note: par défaut, `backend/.env` utilise SQLite (rapide pour dev). Pour MySQL, changez `DB_CONNECTION` et renseignez `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD`.

> Relances email: configure `MAIL_*` dans `backend/.env`. En dev, le plus simple est `MAIL_MAILER=log` (les emails sont écrits dans les logs au lieu d'être envoyés).

## Frontend (Next.js)

```powershell
cd frontend
npm install
npm run dev
```

- Front: `http://localhost:3000`
- Base API configurée via `frontend/.env.local` (`NEXT_PUBLIC_API_BASE_URL`).

## Parcours

- Landing: `/`
- Inscription usager: `/signup`
- Connexion: `/login`
- Recherche: `/search`
- Mon compte: `/me`
- Admin: `/admin`
