# Avancement du projet

> Mis à jour par Claude Code à la fin de chaque phase : cocher, dater, résumer en 1–3 lignes, noter les écarts et décisions.

| Phase | Statut | Date | Résumé / décisions |
| --- | --- | --- | --- |
| 0 — Initialisation | ✅ Terminé | 22/10/2026 | Laravel 13.38 + starter kit React (Inertia 3, Wayfinder, Fortify, Vite+) + Pest, MariaDB 11.5 en InnoDB, locale `fr`, fuseau Africa/Algiers, `lang/fr`, Boost 2.10 (Claude Code : guidelines, 7 skills, MCP). 40 tests verts. |
| 1 — Socle UI et authentification | ✅ Terminé | 22/10/2026 | Thème oklch clair/sombre (pétrole + ambre), Inter embarquée, sidebar 248 px / icônes / barre basse 5 entrées, connexion par nom d'utilisateur, rôles gérant/employé (`Gate::before`, `auth.can`), compte gérant seedé. 12 composants partagés + `/dev/ui`, CountUp porté sur GSAP. 31 tests Pest + 11 tests JS ; vérifié à 360/768/1280 px, aucune requête externe. |
| 2 — Référentiels et paramètres | ✅ Terminé | 22/10/2026 | 11 tables, 9 référentiels gérés dans Paramètres > Référentiels (recherche, panneau d'ajout/modification, Actif/Inactif, suppression refusée si utilisé), catégories de dépenses système verrouillées, Paramètres > Garage (nom, logo, adresse, téléphone, seuil de rappel) via `SettingsService`. Seeder : 69 wilayas, 41 marques, 503 modèles. 59 tests Pest. |
| 3 — Clients et véhicules | ✅ Terminé | 22/10/2026 | Listes Clients et Véhicules (recherche téléphone / nom / prénom / matricule / châssis, filtres wilaya et marque, cartes sur mobile), fiche client (onglets), fiche véhicule (plaque, infos, propriétaire, historique, emplacements carnet / vidange / documents). Actions `CreateVehicle`, `CreateClientWithVehicle`, `TransferVehicleOwnership` ; création rapide réutilisable (JSON) ; recherches JSON pour les sélecteurs. 90 tests Pest + parcours navigateur complet. |
| 4 — Stock, huiles, achats | ✅ Terminé | 22/10/2026 | Articles et huiles (fiche + formats bidon 5/4/1 L et vrac, chacun avec ses prix et son stock), `StockService` (stock = somme des mouvements), ouvrir un bidon, correction par stock compté, achats Brouillon → Commandée → Reçue / Annulée, paiements fournisseurs (dépense « Achats pièces ») avec preuve et facture jointes, fiches fournisseur (reste dû). Tests sur MariaDB : 111 Pest + 13 JS, parcours navigateur complet. |
| 5 — Prestations | ✅ Terminé | 23/10/2026 | Écran « Nouvelle prestation » en sections (véhicule + création rapide, km, date, immobilisation, types fréquents en boutons, mécanicien par ligne, pièces Stock / Huile / Client / Extérieur, récapitulatif collant), numérotation PR-AAAA-00001 verrouillée, `SaveServiceOrder` + `CompleteServiceOrder` (sorties de stock à coût figé, dépense pièces extérieures, dernier km), liste filtrée, fiche, carnet sur la fiche véhicule et onglet client, mécaniciens (CRUD minimal). 132 Pest + 13 JS ; S1 = 20 clics, 8 champs. |
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
- **Phase 2** — Rôle employé élargi (demande du client) : il remplace le gérant au quotidien, y compris stock, achats et **référentiels** ; restent au gérant les employés, les paies et les paramètres du garage. CDC §1.5 et conception §4 mis à jour. Gate `manage-references` (ouvert aux deux rôles) et `manage-admin` (gérant).
- **Phase 2** — Un seul `ReferenceController` + enum `ReferenceType` (modèle, règles, recherche, tri) pour les 9 référentiels, au lieu de 9 contrôleurs / requêtes / policies identiques. Autorisation par gates `can:` sur les groupes de routes (pas de policy par modèle).
- **Phase 2** — Suppression : tentée puis refusée sur l'erreur SQL 23000 (FK `restrictOnDelete`) → « Cet élément est utilisé. Désactivez-le plutôt. » ; s'applique automatiquement aux futures tables.
- **Phase 2** — Catégories de dépenses système protégées dans le code (`isLocked()`), pas par policy : `Gate::before` laisse tout passer au gérant. `system_key` n'est pas fillable (jamais modifiable depuis un formulaire).
- **Phase 2** — Wilayas : **69** (loi n° 26-06 d'avril 2026, numérotation 59–69 du décret présidentiel n° 26-206 du 25 mai 2026, JO n° 40). Les numéros 59–69 parus dans la presse en novembre 2025 sont dépassés.
- **Phase 2** — Seeder : 41 marques, 503 modèles anciens et récents, capacité d'huile vide (dépend de la motorisation). Rappels usuels : vidange moteur 10 000 km, vidange boîte 60 000 km, kit de distribution 60 000 km (modifiables par le gérant) ; prix MO à 0.
- **Phase 2** — Logo du garage stocké en privé (`storage/app/private/garage`) et servi par `/garage/logo` : pas de `storage:link` (lien symbolique souvent refusé sous Windows). SVG refusé.
- **Phase 2 (correctif)** — Formulaire Garage : un PUT multipart (avec fichier) arrive vide côté PHP → envoyé en POST + `_method=put`. Fournisseurs : champs optionnels e-mail, adresse et lien Google Maps (http/https uniquement). Tous les formulaires de Paramètres vérifiés de bout en bout dans un vrai navigateur (ajout, modification, désactivation, suppression, refus si utilisé, envoi du logo).
- **Phase 3** — Téléphone normalisé en chiffres, `+213` / `00213` → `0` : « 0550… » et « +213 550… » sont le même client. Matricule normalisé en lettres et chiffres majuscules ; châssis (VIN) facultatif mais unique. Message de doublon nominatif (« appartient déjà au client X »).
- **Phase 3** — Pas de suppression de client ni de véhicule : désactivation seulement (ils porteront prestations et factures). Inactifs visibles dans les listes, absents des recherches JSON.
- **Phase 3** — Liste des véhicules ajoutée (demande du client) : recherche en haut, fiche détail qui accueillera kilométrages, carnet, prochaine vidange et documents. Kilométrage facultatif saisissable à la création du véhicule.
- **Phase 3** — Création rapide (`QuickClientSheet`) en JSON via `useHttp` : ne change pas de page, réutilisable dans l’écran prestation et dans « Changer de propriétaire » (création du nouveau client sur place, téléphone ou nom pré-rempli).
- **Phase 4** — Tests sur une base MariaDB dédiée `garage_app_test` (`phpunit.xml`), jamais sur `garage_app` : chaque test vide les tables. Les verrous `lockForUpdate` et les décimaux sont ainsi testés sur le vrai moteur.
- **Phase 4** — Huiles : `oil_products` (fiche sans prix ni stock) + un `article` par format (`oil_format`, `volume_l`, unité bidon ou litre). Tout le stock traite huiles et pièces de la même façon. Nom des formats généré : « Total Quartz 9000 5W-30, bidon 5 L ».
- **Phase 4** — Achats à états (demande du client) : Brouillon, Commandée, Reçue (le stock entre à la réception), Annulée (seulement avant réception et sans paiement). Paiement possible dès la commande (acompte), plafonné au reste dû ; dette fournisseur = reste dû des achats reçus.
- **Phase 4** — Pièces jointes facultatives (image ou PDF, 5 Mo, stockage privé servi par route authentifiée) : facture du fournisseur sur l’achat, preuve sur chaque paiement. Même mécanisme (`PrivateFile`) prévu pour les paiements clients (phase 6).
- **Phase 4** — Prix d’achat de l’article = dernier prix payé à la réception (pas de moyenne pondérée). Correction de stock = saisie du stock compté, l’écart devient le mouvement. Ouvrir un bidon refusé sans format vrac actif ou sans bidon en stock. Un stock négatif reste possible pour les sorties de prestation (RG-11).
- **Phase 4** — Montants calculés avec `bcmath` (`AppSupportDecimal`), jamais en flottants ; l’écran achat montre un aperçu des totaux calculé en centimes, le serveur recalcule. Types de mouvements du modèle de données (`adjustment`, `cancellation_in`) retenus, conception corrigée. `expenses.employee_id` reporté en phase 10.
- **Phase 5** — Une seule action `SaveServiceOrder` (création et modification : lignes et pièces recréées) au lieu de Create/Update ; « Terminer » enregistre puis termine dans la même transaction (tout ou rien). Modification et suppression (gérant seulement, gate `delete-service-orders`) tant que la prestation est En cours.
- **Phase 5** — Kilométrage et mécanicien exigés seulement à « Terminer » (une prestation En cours peut être incomplète). Km plus bas que le dernier connu : avertissement, pas de blocage ; `vehicles.last_mileage` mis à jour seulement s’il augmente. Stock insuffisant : avertissement, la sortie est faite (RG-11).
- **Phase 5** — Pièce « achetée à l’extérieur » : dépense « Pièces extérieures » en Espèces à la date du jour. Pièce « apportée par le client » : prix 0, hors total. Huile : choix de l’huile puis du format ; en vrac, quantité = capacité d’huile du modèle.
- **Phase 5** — Un numéro PR supprimé laisse un trou (accepté) ; les factures (phase 6) resteront continues. Numérotation testée sous concurrence réelle (deux connexions MariaDB, verrou `lockForUpdate`).
- **Phase 5** — Table `employees` créée dès maintenant (colonnes salaire comprises) avec un CRUD minimal réservé au gérant ; les paies restent en phase 10. La facture sera générée dans `CompleteServiceOrder` en phase 6.

## Points ouverts

- Accès de l’employé à la caisse et au bénéfice : à confirmer avec le client avant la phase 11 (le CDC le réserve au gérant).
- Un utilisateur désactivé pendant sa session reste connecté jusqu'à expiration (8 h) : à traiter avec la gestion des utilisateurs (phase 8).
- Changement forcé du mot de passe gérant par défaut à la première connexion : phase 8.
- `public/favicon.ico` et `apple-touch-icon.png` sont encore ceux de Laravel (le `favicon.svg` est remplacé).

- Annulation de facture : la prestation repasse « En cours » et les pièces sont réintégrées (à confirmer avec le client).
- Nombre de postes, type d'imprimante, reprise de données, arabe en V1, modèle commercial.
