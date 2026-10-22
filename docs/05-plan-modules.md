# Plan de réalisation par phases

> Une phase = une (ou plusieurs) session(s) Claude Code, terminée(s) par des tests verts, un `PROGRESS.md` à jour et un commit.
> Une phase ne commence que si la précédente est validée par le développeur.

| Phase | Module | Lot CDC | Dépend de |
| --- | --- | --- | --- |
| 0 | Initialisation du projet et des outils | — | — |
| 1 | Socle UI, design system, navigation, authentification, rôles | 1 | 0 |
| 2 | Référentiels et paramètres du garage | 1 | 1 |
| 3 | Clients et véhicules | 1 | 2 |
| 4 | Stock, huiles (bidon/vrac), fournisseurs, achats | 1 | 2 |
| 5 | Prestations | 1 | 3, 4 |
| 6 | Factures, paiements, crédits, impression | 1 | 5 |
| 7 | Recherche et historique (carnet du véhicule) | 1 | 6 |
| 8 | Utilisateurs, sauvegardes, licence | 1 | 1 |
| 9 | Packaging Windows (installateur) — **livraison du lot 1** | 1 | 2–8 |
| 10 | Employés, avances, salaires | 2 | 4 |
| 11 | Caisse : dépenses, autres entrées, bénéfice | 2 | 6, 10 |
| 12 | Tableau de bord | 2 | 11 |
| 13 | Rendez-vous | 3 | 5 |
| 14 | Bilan d'entretien et rappels de vidange | 3 | 7 |
| 15 | Audit final, recette, mise à jour de l'installateur | — | tout |

## Critères d'acceptation par phase

### Phase 0 — Initialisation
- [ ] Laravel 13 + starter kit React installé, `composer run dev` fonctionne, page de connexion visible.
- [ ] MariaDB configurée (`DB_CONNECTION=mariadb`), migrations OK, locale `fr`, fuseau `Africa/Algiers`.
- [ ] Laravel Boost installé (guidelines + skills + MCP pour Claude Code).
- [ ] Git initialisé, `.gitignore` correct, premier commit ; `docs/PROGRESS.md` à jour.

### Phase 1 — Socle UI
- [ ] Tokens du thème, police Inter locale, mode clair/sombre.
- [ ] `AppLayout` : sidebar PC/tablette, barre basse mobile, en-tête avec recherche et « + Nouvelle prestation ».
- [ ] Connexion par nom d'utilisateur, inscription et reset e-mail désactivés, rôles `gerant`/`employe`, menu filtré par rôle.
- [ ] Composants partagés de `04-design-system.md` §4 créés (au moins `PageHeader`, `DataTable`, `SearchSelect`, `MoneyInput`, `MoneyText`, `StatusBadge`, `EmptyState`, `ConfirmDialog`, `ActiveToggle`) avec une page de démonstration interne `/dev/ui` (dev uniquement).
- [ ] GSAP, Lenis installés ; `lib/animations.ts` avec `useReducedMotion` ; aucune ressource externe chargée (vérifié dans l'onglet Réseau).

### Phase 2 — Référentiels et paramètres
- [ ] CRUD de chaque référentiel du CDC §2, bascule Actif/Inactif, suppression refusée si utilisé (message clair).
- [ ] `ReferenceDataSeeder` complet ; catégories de dépenses système protégées.
- [ ] Paramètres du garage (nom, logo, adresse, téléphone, seuil de rappel).

### Phase 3 — Clients et véhicules
- [ ] Liste + recherche (téléphone, nom, matricule), fiche client, fiche véhicule, création rapide client + véhicule.
- [ ] Téléphone et matricule normalisés et uniques ; « Changer de propriétaire » avec historique.

### Phase 4 — Stock et achats
- [ ] Articles et fiches huile avec formats ; prix distincts bidon/vrac ; `StockService` + mouvements.
- [ ] Achat fournisseur (stock +, dépense, reste dû), paiement fournisseur, correction de stock, « Ouvrir un bidon », alertes.

### Phase 5 — Prestations
- [ ] Création/modification d'une prestation multi-travaux, pièces avec 3 origines, huile bidon/vrac, propositions automatiques.
- [ ] `CompleteServiceOrder` conforme RG-05 à RG-11 ; tests S1, S2, S3.

### Phase 6 — Factures et paiements
- [ ] Facture générée, paiement total/partiel, crédit client, annulation gérant ; impression A4, ticket, PDF ; test S7.

### Phase 7 — Recherche et historique
- [ ] Recherche globale (Ctrl+K) et recherche avancée avec tous les filtres du CDC M9, export CSV, carnet du véhicule ; test S5.

### Phase 8 — Administration
- [ ] Gestion des utilisateurs (gérant), réinitialisation de mot de passe par le gérant.
- [ ] Sauvegarde manuelle + planifiée, rotation 30 jours, restauration guidée ; licence liée à la machine.

### Phase 9 — Packaging Windows
- [ ] Installateur Inno Setup conforme à `06-deploiement-windows.md`, testé sur un Windows 10/11 vierge sans internet.

### Phase 10 — Employés et salaires
- [ ] Employés, avances, paie mensuelle (mensuel/journalier), une paie par mois et par employé, dépenses générées.

### Phase 11 — Caisse
- [ ] Dépenses manuelles, autres entrées, bénéfice par période et par catégorie, solde du jour ; test S8 ; réservé au gérant.

### Phase 12 — Tableau de bord
- [ ] Indicateurs du jour, graphique 12 mois, classements, alertes ; données financières masquées à l'employé.

### Phase 13 — Rendez-vous
- [ ] Liste jour/semaine, calendrier simple, statuts Prévu/Effectué/Annulé, « Commencer » ; test S4.

### Phase 14 — Bilan et rappels
- [ ] Bilan d'entretien A4/PDF avec et sans prix ; liste des rappels avec km estimé ; test S9.

### Phase 15 — Audit final
- [ ] Les 9 scénarios S1–S9 passent (tests + vérification manuelle), audit responsive 360/768/1280, accessibilité, performance, sécurité.
- [ ] Données de démo, guide utilisateur court, installateur mis à jour.
