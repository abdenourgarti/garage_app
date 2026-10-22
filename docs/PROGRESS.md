# Avancement du projet

> Mis à jour par Claude Code à la fin de chaque phase : cocher, dater, résumer en 1–3 lignes, noter les écarts et décisions.

| Phase | Statut | Date | Résumé / décisions |
| --- | --- | --- | --- |
| 0 — Initialisation | ✅ Terminé | 22/10/2026 | Laravel 13.38 + starter kit React (Inertia 3, Wayfinder, Fortify, Vite+) + Pest, MariaDB 11.5 en InnoDB, locale `fr`, fuseau Africa/Algiers, `lang/fr`, Boost 2.10 (Claude Code : guidelines, 7 skills, MCP). 40 tests verts. |
| 1 — Socle UI et authentification | ⬜ À faire | | |
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

## Points ouverts

- Tests sur SQLite en mémoire (`phpunit.xml`, défaut du kit) : passer à une base de test MariaDB avant les phases 4–5 (`lockForUpdate`, erreurs FK 23000).
- Police du kit (Instrument Sans) téléchargée depuis Bunny Fonts **au build** puis embarquée dans `public/build` : à remplacer par Inter via `@fontsource` en phase 1 (le build ne doit pas dépendre d'internet).

- Annulation de facture : la prestation repasse « En cours » et les pièces sont réintégrées (à confirmer avec le client).
- Nombre de postes, type d'imprimante, reprise de données, arabe en V1, modèle commercial.
