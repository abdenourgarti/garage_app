# GarageApp — mémoire projet pour Claude Code

## Le projet en bref

Application web de gestion d'un garage automobile algérien (vidanges, réparations : croisillons, distribution, freinage…).
Elle couvre : clients et véhicules, rendez-vous, prestations, stock (dont l'huile en bidon et en vrac), achats fournisseurs,
factures et paiements, caisse (entrées − dépenses = bénéfice), employés et salaires, tableau de bord, recherche,
bilan d'entretien et rappels de vidange.

Elle est installée **en local sur le PC Windows du garage, sans internet**. Les utilisateurs sont des garagistes peu à l'aise
avec l'informatique : **la simplicité passe avant tout le reste**.

## Documents de référence (lire ceux qui concernent la tâche AVANT d'écrire du code)

| Fichier | Contenu |
| --- | --- |
| `docs/01-cahier-des-charges.md` | Besoin fonctionnel validé : source de vérité métier |
| `docs/02-conception-technique.md` | Architecture, conventions, découpage, actions métier |
| `docs/03-modele-donnees.md` | Tables, colonnes, relations, index, enums |
| `docs/04-design-system.md` | UI/UX, composants, responsive, animations |
| `docs/05-plan-modules.md` | Phases de réalisation et critères d'acceptation |
| `docs/06-deploiement-windows.md` | Packaging et installation chez le client |
| `docs/PROGRESS.md` | Avancement : à mettre à jour à la fin de chaque phase |

En cas de contradiction : le cahier des charges l'emporte sur la conception ; signale-la-moi au lieu de trancher seul.

## Stack

- Laravel 13 (PHP 8.3+), starter kit officiel React (Inertia, React 19, TypeScript, Tailwind CSS v4, shadcn/ui, Wayfinder, Fortify)
- MariaDB 11 (InnoDB, utf8mb4) — `DB_CONNECTION=mariadb`
- Tests : Pest. Qualité : Pint (PHP), Vite+ `vp check` (oxlint + oxfmt, remplace ESLint/Prettier), `tsc`
- Animations : GSAP (+ `@gsap/react`), Lenis, quelques composants React Bits — uniquement selon `docs/04-design-system.md`
- Build front avec Vite sur le poste de développement ; **Node n'est pas installé chez le client**

## Règles non négociables

1. **Hors-ligne** : en production, rien n'est chargé depuis internet (pas de CDN, pas de Google Fonts → paquets `@fontsource`).
2. **Interface 100 % en français**, textes centralisés dans `resources/js/lib/i18n/fr.ts` (l'arabe/RTL viendra plus tard).
3. **Pas de TVA, pas de comptabilité fiscale.** Bénéfice d'une période = entrées encaissées − dépenses payées.
4. **Montants en DA** : colonnes `decimal(12,2)`, cast `decimal:2`, affichage via `formatMoney()` → « 12 500 DA ».
5. **Référentiels** : colonne `is_active` ; suppression interdite dès qu'un élément est utilisé (FK `restrictOnDelete`) → l'UI propose « Désactiver ».
6. **Toute opération qui touche le stock, la caisse ou une numérotation** = une classe Action (`app/Actions`) exécutée dans `DB::transaction()` et couverte par un test Pest.
7. **Responsive mobile-first** : tout écran doit fonctionner de 360 px (smartphone) au grand écran.
8. **Simplicité** : minimum de champs obligatoires, pas de fonctionnalité absente du cahier des charges sans me demander.
9. **Pas de nouvelle dépendance** sans justification écrite ; réutiliser d'abord ce qui existe (shadcn/ui, Laravel, navigateur).
10. Le code (classes, tables, variables) est en **anglais** ; tout ce que l'utilisateur voit est en **français**.

## Vocabulaire métier ↔ code

| Métier (UI) | Code |
| --- | --- |
| Prestation | `ServiceOrder` / `service_orders` |
| Ligne de travaux | `ServiceOrderLine` |
| Pièce utilisée | `ServiceOrderPart` |
| Type de prestation | `ServiceType` |
| Article / format d'huile | `Article` (+ `OilProduct`) |
| Achat fournisseur | `Purchase` / `PurchaseItem` / `PurchasePayment` |
| Paiement client | `Payment` |
| Dépense | `Expense` |
| Autre entrée | `OtherIncome` |
| Rendez-vous | `Appointment` |

## Commandes utiles

```bash
composer run dev          # serveur + vite + queue
php artisan test          # tests Pest
vendor/bin/pint           # format PHP
npm run check             # lint + format (Vite+ : oxlint, oxfmt) ; `npm run check:fix` pour corriger
npm run types:check       # tsc --noEmit
npm run build             # build de production
```

## Méthode de travail attendue

1. Lire les sections de docs concernées, puis me proposer un **plan court** (mode plan) et attendre ma validation.
2. Implémenter par petites tranches verticales (migration → modèle → action → contrôleur → page → test).
3. Avant de dire « terminé » : `php artisan test`, `vendor/bin/pint`, `npm run check`, `npm run types:check`, `npm run build` doivent passer.
4. Vérifier l'écran en 360 px et en 1280 px (Chrome DevTools MCP si disponible).
5. Mettre à jour `docs/PROGRESS.md`, puis proposer un commit (Conventional Commits : `feat(stock): …`).
6. Finir chaque réponse par : ce qui a été fait, ce qui n'a pas été vérifié, les risques.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
