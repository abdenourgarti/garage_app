# Avancement du projet

> Mis à jour par Claude Code à la fin de chaque phase : cocher, dater, résumer en 1–3 lignes, noter les écarts et décisions.

| Phase | Statut | Date | Résumé / décisions |
| --- | --- | --- | --- |
| 0 — Initialisation | ✅ Terminé | 22/10/2026 | Laravel 13.38 + starter kit React (Inertia 3, Wayfinder, Fortify, Vite+) + Pest, MariaDB 11.5 en InnoDB, locale `fr`, fuseau Africa/Algiers, `lang/fr`, Boost 2.10 (Claude Code : guidelines, 7 skills, MCP). 40 tests verts. |
| 1 — Socle UI et authentification | ✅ Terminé | 22/10/2026 | Thème oklch clair/sombre (pétrole + ambre), Inter embarquée, sidebar 248 px / icônes / barre basse 5 entrées, connexion par nom d'utilisateur, rôles gérant/employé (`Gate::before`, `auth.can`), compte gérant seedé. 12 composants partagés + `/dev/ui`, CountUp porté sur GSAP. 31 tests Pest + 11 tests JS ; vérifié à 360/768/1280 px, aucune requête externe. |
| 2 — Référentiels et paramètres | ⬜ À faire | | |
| 3 — Clients et véhicules | ⬜ À faire | | |
| 4 — Stock, huiles, achats | ⬜ À faire | | |
| 5 — Prestations | ⬜ À faire | | |
| 6 — Factures et paiements | ⬜ À faire | | |
| 7 — Recherche et historique | ⬜ À faire | | |
| 8 — Utilisateurs, sauvegardes, licence | ⬜ À faire | | |
| 9 — Packaging Windows | ⬜ À faire | | |
| 10 — Employés et salaires | ⬜ À faire | | |
| 11 — Caisse et bénéfice | ⬜ À faire | | |
| 12 — Tableau de bord | ⬜ À faire | | |
| 13 — Rendez-vous | ⬜ À faire | | |
| 14 — Bilan d'entretien et rappels | ⬜ À faire | | |
| 15 — Audit final et recette | ⬜ À faire | | |

## Décisions prises en cours de route

- **Phase 0** — Racine du projet : `garage-app/` (ouvrir l'éditeur et Claude Code sur ce dossier pour charger `CLAUDE.md` et `.mcp.json`).
- **Phase 0** — Moteur `InnoDB` forcé dans `config/database.php` (connexion `mariadb`) : le MariaDB de WAMP crée en MyISAM par défaut (pas de FK ni de transactions).
- **Phase 0** — `DB_PORT=3307` en local (MariaDB WAMP ; le 3306 est occupé par MySQL 9.1) ; `.env.example` garde 3306.
- **Phase 0** — `APP_TIMEZONE` lu dans `config/app.php` (codé en dur `UTC` dans le squelette).
- **Phase 0** — Le kit utilise Vite+ : `npm run check` (oxlint + oxfmt) et `npm run types:check` remplacent `lint` / `types` / `format` (CLAUDE.md corrigé). `docs/**` exclu du formateur.
- **Phase 0** — `CLAUDE.md`, `boost.json` et `.mcp.json` versionnés (ignorés par défaut dans le kit) ; `.claude/` reste ignoré (skills régénérées par `php artisan boost:install`). Laravel Cloud désactivé dans Boost.
- **Phase 1** — Retirés du kit : inscription, vérification e-mail, réinitialisation par e-mail, 2FA, passkeys, suppression de compte, page d'accueil publique (migrations, pages, tests et paquets `@laravel/passkeys`, `input-otp` compris). `/` redirige vers `/dashboard`.
- **Phase 1** — Migration `users` d'origine modifiée (base vide) : `username` unique, `role` enum, `is_active`, `email` facultatif. Compte inactif refusé à la connexion (`Fortify::authenticateUsing`).
- **Phase 1** — Compte gérant initial : `ReferenceDataSeeder` lit `config/garage.php` (`GARAGE_ADMIN_*` dans `.env`) pour fonctionner avec la config en cache.
- **Phase 1** — Règle de mot de passe en production : 8 caractères, lettres et chiffres. La règle du kit (`uncompromised()`) interroge une API internet → incompatible hors-ligne.
- **Phase 1** — Ambre = token `--highlight` (et non `--accent`, qui sert aux survols shadcn) ; 04-design-system mis à jour. « + Nouvelle prestation » est le seul bouton ambre.
- **Phase 1** — Toasts : `Inertia::flash('toast', …)` natif d'Inertia 3 au lieu de `session()->flash` ; 02-conception §3 mis à jour.
- **Phase 1** — Sidebar pilotée par la largeur (dépliée ≥ 1280 px, icônes de 768 à 1279 px, barre basse en dessous), sans bouton de repli ; cibles tactiles 44 px.
- **Phase 1** — CountUp de React Bits réécrit avec GSAP (l'original dépend de `motion`) ; pas de liste animée pour l'instant (alertes en phase 12). Lenis installé mais pas encore utilisé (pages de lecture : phases 6 et 14).
- **Phase 1** — Thème déclaré en `@theme inline` (sinon un bloc `.dark` imbriqué ne change pas les couleurs). PeriodPicker sur champs date natifs (pas de `react-day-picker`).
- **Phase 1** — Modules du menu servis par une page « Bientôt disponible » (`coming-soon`) le temps de leur livraison ; `/dev/ui` déclarée seulement si `APP_ENV=local`.

## Points ouverts

- Tests sur SQLite en mémoire (`phpunit.xml`, défaut du kit) : passer à une base de test MariaDB avant les phases 4–5 (`lockForUpdate`, erreurs FK 23000).
- Un utilisateur désactivé pendant sa session reste connecté jusqu'à expiration (8 h) : à traiter avec la gestion des utilisateurs (phase 8).
- Changement forcé du mot de passe gérant par défaut à la première connexion : phase 8.
- `public/favicon.ico` et `apple-touch-icon.png` sont encore ceux de Laravel (le `favicon.svg` est remplacé).

- Annulation de facture : la prestation repasse « En cours » et les pièces sont réintégrées (à confirmer avec le client).
- Nombre de postes, type d'imprimante, reprise de données, arabe en V1, modèle commercial.
