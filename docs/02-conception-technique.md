# Conception technique

> Complète `01-cahier-des-charges.md`. Le modèle de données détaillé est dans `03-modele-donnees.md`, l'UI dans `04-design-system.md`.

## 1. Architecture générale

Monolithe Laravel 13 + Inertia + React 19 (starter kit officiel React). Pas d'API publique, pas de SPA séparée.

```
Navigateur (Edge --app, Chrome, smartphone du réseau local)
        │  Inertia (HTML initial + JSON)
        ▼
Laravel 13 ── Controllers (minces) ── FormRequests (validation FR)
        │                               │
        │                               ▼
        │                         Actions (logique métier, transactions)
        │                               │
        ▼                               ▼
   Policies / Gates              Models Eloquent ── MariaDB 11
        │
   Services transverses : Numbering, MoneyFormatter, Settings, Backup, License, Reminder
```

### Couches

| Couche | Rôle | Règle |
| --- | --- | --- |
| `app/Http/Controllers/{Module}` | Reçoit la requête, appelle une Action, renvoie `Inertia::render` ou `redirect` | Aucune logique métier |
| `app/Http/Requests/{Module}` | Validation + messages en français | Une FormRequest par écriture |
| `app/Actions/{Module}` | Une classe = une opération métier (`handle()`) | `DB::transaction()` dès qu'on touche stock, caisse ou numéro |
| `app/Models` | Relations, casts, scopes (`active()`, `search()`) | Pas de logique de workflow |
| `app/Enums` | Enums PHP backed (`string`) avec `label()` français | Partagés avec le front via props |
| `app/Policies` | Autorisations par rôle | `gerant` passe partout via `Gate::before` |
| `app/Services` | Outils transverses sans état métier | Injectés par le conteneur |
| `app/Http/Resources` (optionnel) | Mise en forme des props Inertia | Utiliser seulement si le mapping devient lourd |

## 2. Structure des dossiers

```
app/
  Actions/{Clients,Vehicles,Stock,Purchases,ServiceOrders,Invoices,Payments,Cash,Payroll,Appointments,Admin}/
  Enums/
  Http/Controllers/{...}/  Http/Requests/{...}/
  Models/  Policies/  Services/
database/migrations/  database/seeders/ (ReferenceDataSeeder, DemoSeeder)
resources/js/
  pages/{dashboard,clients,vehicles,service-orders,invoices,stock,purchases,cash,employees,appointments,search,settings,auth}/
  components/ui/          ← shadcn/ui (généré)
  components/app/         ← composants métier partagés (voir 04-design-system.md)
  layouts/                ← AppLayout (sidebar + bottom nav), PrintLayout, AuthLayout
  lib/i18n/fr.ts  lib/format.ts  lib/animations.ts
  hooks/
resources/views/pdf/      ← vues Blade pour DomPDF (facture A4, ticket 80 mm, bilan)
tests/Feature/{Module}/  tests/Unit/
```

## 3. Conventions

- **Nommage** : code en anglais, UI en français (voir table dans `CLAUDE.md`). Routes nommées `module.action` (`service-orders.complete`). URLs en anglais kebab-case.
- **Routes** : `Route::resource` + routes d'action explicites (`POST /service-orders/{order}/complete`). Navigation et formulaires front via **Wayfinder** (pas d'URL en dur).
- **Validation** : messages FR (`lang/fr/validation.php` + `attributes`). `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=fr`, `APP_TIMEZONE=Africa/Algiers`.
- **Montants** : `decimal(12,2)`, cast `decimal:2`. Calculs dans les Actions, jamais dans React. Affichage `formatMoney()` (Intl `fr-DZ`, sans décimales si entier, suffixe « DA »).
- **Quantités** : `decimal(10,2)` (litres de vrac), affichage sans zéros inutiles.
- **Dates** : stockées en `date`/`datetime`, affichées `dd/MM/yyyy` et `HH:mm`.
- **Pagination** : 25 lignes, côté serveur, avec recherche et filtres dans la query string (partageables, retour arrière préservé).
- **Suppression** : FK `restrictOnDelete()` sur les référentiels ; l'UI capture `QueryException` 23000 → message « Cet élément est utilisé. Désactivez-le plutôt. ». Pas de `SoftDeletes` sauf mention contraire.
- **Enums** exposés au front avec `{value, label}`.
- **Messages flash** : `Inertia::flash('toast', ['type' => 'success'|'error'|'info'|'warning', 'message' => '…'])` → toasts (sonner), mécanisme natif d'Inertia 3.
- **Tests** : un test Feature Pest par Action (cas nominal + cas limite) et par écran clé (accès par rôle). Factories pour chaque modèle.

## 4. Rôles et autorisations

Enum `UserRole` : `gerant`, `employe`.

| Ressource | Employé | Gérant |
| --- | --- | --- |
| Clients, véhicules, rendez-vous | CRUD (sans suppression si utilisé) | Tout |
| Prestations | Créer, modifier en cours, terminer | Tout + supprimer en cours |
| Factures, paiements | Générer, encaisser, imprimer | Tout + annuler |
| Stock, achats | Tout | Tout |
| Caisse, bénéfice, tableau financier | ✗ | ✓ |
| Employés, salaires, avances | ✗ | ✓ |
| Référentiels (marques, modèles, types de prestations…) | ✓ | ✓ |
| Paramètres du garage, utilisateurs, sauvegardes | ✗ | ✓ |

Implémentation : `Gate::before` pour le gérant, Policies par modèle, middleware `can:` sur les groupes de routes,
et le menu React masque les entrées non autorisées (prop partagée `auth.can`).

## 5. Services transverses

| Service | Responsabilité |
| --- | --- |
| `NumberingService` | Numéros `PR-2026-00001` (prestations) et `FA-2026-00001` (factures) ; compteur par année dans `sequences`, `lockForUpdate()` dans la transaction |
| `SettingsService` | Lecture/écriture de `settings` (nom du garage, logo, adresse, téléphone, seuil rappel vidange…) avec cache |
| `StockService` | Écrit un `StockMovement` et met à jour `articles.stock_qty` (toujours ensemble) |
| `CashService` | Calcule entrées, dépenses, bénéfice et détail par catégorie pour une période |
| `ReminderService` | Prochaine vidange et kilométrage estimé (voir §8) |
| `BackupService` | `mysqldump` + zip vers le dossier configuré, rotation 30 jours, restauration |
| `LicenseService` | Vérifie la clé liée à l'identifiant machine (middleware) |

## 6. Actions métier (cœur du système)

| Action | Effets (dans une transaction) |
| --- | --- |
| `CreateClientWithVehicle` | Client + premier véhicule (création rapide depuis une prestation) |
| `TransferVehicleOwnership` | Clôt l'`ownership` courante, en ouvre une nouvelle, met à jour `vehicles.client_id` ; prestations inchangées |
| `RecordPurchase` | Achat (brouillon, commandé ou reçu directement) + lignes, total recalculé ; à la réception (`ReceivePurchase`) `StockService::in()` par ligne et prix d'achat de l'article = dernier prix payé ; si montant payé > 0 → `PaySupplier` |
| `ReceivePurchase` / `CancelPurchase` | Réception : entrée en stock, statut `received`. Annulation : seulement brouillon ou commandé, et sans paiement |
| `PaySupplier` | `PurchasePayment` + `Expense` sur un achat ayant un reste dû |
| `AdjustStock` | Mouvement `adjustment` (écart entre le stock compté et le stock enregistré) signé + motif obligatoire |
| `OpenOilCan` | Bidon −1 (mouvement `can_opened_out`) ; vrac + `volume_l` (mouvement `can_opened_in`) ; refuse si le format n'a pas de vrac lié |
| `SaveServiceOrder` | Création et modification : en-tête + lignes + pièces (recréées) ; numéro attribué à la création ; modifiable tant que `en_cours`. « Terminer et facturer » = `SaveServiceOrder` puis `CompleteServiceOrder` dans la même transaction |
| `CompleteServiceOrder` | Contrôles RG-06/07 ; sorties de stock des pièces `stock` (coût figé = prix d'achat courant) ; `Expense` « Pièces extérieures » rattachée à la prestation pour ce qui n'est pas déjà dépensé ; met à jour `vehicles.last_mileage` ; statut `terminee` ; crée la facture via `GenerateInvoice` |
| `GenerateInvoice` | Calcule sous-totaux MO / pièces, total ; numéro `FA-…` ; statut `impayee` ; refuse une 2e facture active |
| `ApplyDiscount` | Remise globale en DA, entre 0 et le sous-total, tant qu'aucun paiement n'est enregistré |
| `RecordPayment` | `Payment` ; recalcule `amount_paid` et statut (`impayee` / `partielle` / `payee`) ; refuse un montant > reste à payer |
| `CancelInvoice` (gérant) | Motif obligatoire ; paiements marqués annulés ; facture `annulee` ; prestation repasse `en_cours` ; pièces stock réintégrées (mouvement `cancellation_in`) ; dépenses des pièces externes conservées |
| `RecordExpense` / `RecordOtherIncome` | Écriture simple datée |
| `RecordSalaryAdvance` | `Expense` catégorie système « Avances » liée à l'employé |
| `PaySalary` | Calcule base (mensuel, ou journalier × jours saisis) − avances du mois ; `SalaryPayment` + `Expense` « Salaires » ; une seule paie par employé et par mois |
| `StartAppointment` | Crée la prestation pré-remplie, lie `appointments.service_order_id`, statut `effectue` |
| `RunBackup` / `RestoreBackup` | Voir `06-deploiement-windows.md` |

> **Décision validée (phase 6)** : l'annulation d'une facture remet la prestation « En cours »
> et réintègre les pièces au stock, pour permettre une correction puis une nouvelle facture (nouveau numéro).
> Les dépenses des pièces extérieures sont rattachées à la prestation : à la nouvelle facturation, seule la différence est dépensée.

## 7. Caisse et bénéfice (RG-14)

- **Entrées** d'une période = `payments.amount` (non annulés, `paid_at` dans la période) + `other_incomes.amount`.
- **Dépenses** d'une période = `expenses.amount` (`spent_at` dans la période).
- **Bénéfice** = Entrées − Dépenses. Détail des dépenses par catégorie, des entrées par mode de paiement.
- **Solde de caisse du jour** = entrées espèces du jour − dépenses espèces du jour (indicatif, sans clôture).
- Périodes prêtes : aujourd'hui, cette semaine (lundi→dimanche), ce mois, cette année, intervalle libre.

## 8. Rappels de vidange (RG-15)

- Pour chaque véhicule, dernière ligne de prestation terminée dont le type a `reminder_km` → `next_due_km = mileage + reminder_km`.
- Kilométrage estimé aujourd'hui = dernier km + (km/jour moyen entre les deux dernières prestations avec km) × jours écoulés ; si une seule prestation : dernier km connu.
- Le véhicule apparaît dans « Rappels » quand `estimé ≥ next_due_km − seuil` (paramètre `reminder_threshold_km`, défaut 1 000 km).
- Calcul à la volée (requête + calcul PHP), pas de tâche planifiée nécessaire.

## 9. Recherche

- Barre globale (Ctrl+K / bouton loupe) : une requête qui cherche en parallèle clients (téléphone, nom, prénom), véhicules
  (matricule, châssis), factures (numéro) ; résultats groupés, 5 par groupe.
- Recherche avancée des prestations : filtres du CDC M9, query string, export CSV.
- Index MariaDB sur les colonnes recherchées ; `LIKE 'terme%'` en priorité, `LIKE '%terme%'` en second ; normaliser le matricule
  et le téléphone (sans espaces ni tirets) dans des colonnes `*_normalized`.

## 10. Impression et PDF

- **Une seule mise en page par document** : vues Blade `resources/views/documents/*` (gabarit `layout.blade.php`), servies en
  trois formats par la même route : `a4` et `ticket` (HTML, `@page` A4 ou 80 mm, `?print=1` lance `window.print()`) et `pdf`
  (`barryvdh/laravel-dompdf`, A4). L'aperçu de la fiche facture est ce même document dans un cadre : écran, impression et PDF ne divergent pas.
- Polices : DejaVu Sans fournie avec dompdf (aucun téléchargement, sous-ensemble intégré au PDF) ; Arial à l'impression navigateur. Logo en data URI.
- Documents : facture A4, ticket 80 mm, reçu de paiement, bilan d'entretien (avec ou sans prix).

## 11. Sécurité

- Auth Fortify du starter kit, connexion par **nom d'utilisateur** (pas d'e-mail obligatoire, pas d'inscription publique), throttling de connexion.
- Désactiver : inscription, vérification e-mail, reset par e-mail (pas d'internet) → réinitialisation du mot de passe par le gérant.
- Session : expiration après 8 h d'inactivité (paramétrable).
- CSRF, hash bcrypt/argon, `APP_DEBUG=false` en production, en-têtes de sécurité de base.

## 12. Performance

- Eager loading systématique (`with()`), `preventLazyLoading()` en local.
- Index : toutes les FK, `clients.phone_normalized`, `vehicles.plate_normalized`, `vehicles.vin`, `service_orders.date`,
  `invoices.number`, `payments.paid_at`, `expenses.spent_at`.
- Objectif : écran < 2 s et recherche < 1 s sur 10 ans de données d'un garage.

## 13. Données de référence et de démo

- `ReferenceDataSeeder` (toujours) : wilayas, énergies, marques et modèles courants en Algérie, types de prestations,
  catégories (dont catégories système de dépenses), modes de paiement, compte gérant initial.
- `DemoSeeder` (dev uniquement, `php artisan db:seed --class=DemoSeeder`) : passe par les actions métier ; chaque partie ne s'ajoute
  qu'une fois, on le relance après chaque phase. À ce jour : 14 clients, 16 véhicules, 26 prestations sur 3 mois, stock, achats,
  factures et paiements (crédits, remise, annulation). À étoffer (12 mois) pour le tableau de bord.
