# Design system et UI/UX

> Objectif : une interface moderne, rassurante et **évidente** pour un garagiste peu habitué à l'informatique,
> utilisable au comptoir (PC), à l'atelier (smartphone ou tablette) et à l'impression.

## 1. Principes UX

1. **Une action principale par écran**, toujours visible (bouton primaire en haut à droite sur PC, bouton flottant ou barre basse sur mobile).
2. **« + Nouvelle prestation » accessible en 1 clic depuis partout** (c'est l'action n°1 du garage).
3. **Recherche partout** : barre globale (Ctrl+K sur PC, icône loupe sur mobile), résultats dès 2 caractères.
4. **Formulaires courts** : champs obligatoires marqués, optionnels repliés sous « Plus d'informations ».
5. **Valeurs proposées** : prix MO du type, prix de l'article, quantité d'huile = capacité du modèle ; l'utilisateur corrige au lieu de saisir.
6. **Jamais de cul-de-sac** : création rapide d'un client / véhicule / article depuis un sélecteur (« + Ajouter »).
7. **Erreurs en langage simple**, à côté du champ, sans code technique.
8. **Confirmation uniquement pour l'irréversible** (annuler une facture, supprimer une prestation en cours).
9. **États vides utiles** : message + bouton d'action (« Aucun client. Ajouter le premier client »).
10. **Chiffres lisibles** : montants en gras, « 12 500 DA », alignés à droite dans les tableaux.

## 2. Identité visuelle (tokens)

Définir les tokens dans `resources/css/app.css` (variables du thème shadcn, en `oklch`), mode clair et sombre.

| Token | Clair | Usage |
| --- | --- | --- |
| `--primary` | Bleu pétrole `#0F5C6E` | Boutons principaux, liens, éléments actifs |
| `--highlight` | Ambre huile `#F2A516` | Mise en avant ponctuelle (bouton « + Nouvelle prestation », prestation en cours, rappels). `--accent` reste le gris neutre des survols shadcn |
| `--background` / `--card` | `#F7F8FA` / `#FFFFFF` | Fond / cartes |
| `--foreground` / `--muted-foreground` | `#111827` / `#6B7280` | Texte / texte secondaire |
| `--success` | `#15803D` | Payée, stock OK, bénéfice positif |
| `--warning` | `#B45309` | Partielle, stock bas, rappel proche |
| `--destructive` | `#B91C1C` | Impayée, annulée, bénéfice négatif |
| `--radius` | `0.75rem` | Coins arrondis cohérents |

- **Typographie** : Inter Variable via `@fontsource-variable/inter` (embarquée, hors-ligne). Base 16 px, chiffres tabulaires (`font-variant-numeric: tabular-nums`) dans les tableaux et montants.
- **Icônes** : `lucide-react` (déjà dans le starter kit), taille 20 px, toujours accompagnées d'un libellé dans la navigation.
- **Contraste** : WCAG AA minimum (4,5:1 pour le texte).

## 3. Mise en page responsive

| Largeur | Navigation | Contenu |
| --- | --- | --- |
| < 768 px (smartphone) | Barre basse 5 entrées : Accueil, Prestations, Clients, Stock, Plus | Une colonne ; tableaux → **cartes** ; actions en bas d'écran, zone du pouce |
| 768–1279 px (tablette) | Sidebar repliée en icônes + libellés au survol | Formulaires sur 2 colonnes quand pertinent |
| ≥ 1280 px (PC) | Sidebar 248 px avec libellés | Tableaux complets, panneau latéral de détail possible |

- Cibles tactiles ≥ 44 × 44 px ; espacement entre actions ≥ 8 px.
- Aucun défilement horizontal de la page ; un tableau large défile dans son propre conteneur.
- En-tête de page : titre, sous-titre court, action principale. Fil d'Ariane seulement au-delà de 2 niveaux.
- Tester chaque écran à **360 px, 768 px, 1280 px** avant de le déclarer terminé.

## 4. Composants partagés (`resources/js/components/app/`)

| Composant | Rôle |
| --- | --- |
| `PageHeader` | Titre, description, actions |
| `DataTable` | Tableau serveur (tri, recherche, filtres, pagination) qui bascule en liste de cartes sous 768 px |
| `SearchSelect` | Combobox avec recherche serveur + option « + Ajouter » (clients, véhicules, articles, types) |
| `MoneyInput` / `QuantityInput` | Saisie numérique avec séparateurs et suffixe (DA, L) |
| `MoneyText` | Affichage formaté, couleur selon signe si demandé |
| `StatusBadge` | Statuts (prestation, facture, RDV, stock) avec couleur et libellé FR |
| `StatCard` | Indicateur du tableau de bord (valeur animée, variation, icône) |
| `EmptyState` | Illustration légère + message + action |
| `ConfirmDialog` | Confirmation des actions irréversibles avec saisie du motif si besoin |
| `ActiveToggle` | Bascule Actif/Inactif des référentiels |
| `PeriodPicker` | Aujourd'hui / Semaine / Mois / Année / Du… au… |
| `GlobalSearch` | Palette de commande (Ctrl+K) |
| `PrintButton` | A4 / Ticket / PDF |

Toujours partir des composants shadcn/ui existants (`Button`, `Input`, `Dialog`, `Sheet`, `Command`, `Table`, `Badge`, `Tabs`, `Calendar`, `Sonner`).

## 5. Écrans clés (intentions)

- **Accueil / tableau de bord** : 4 `StatCard` (entrées, dépenses, bénéfice — gérant uniquement — et prestations du jour), véhicules en cours, RDV du jour, alertes ; graphique 12 mois.
- **Nouvelle prestation** : étape unique en sections : 1) Client et véhicule (recherche par téléphone ou matricule, création rapide), 2) Kilométrage et durée, 3) Travaux (ajout de lignes par boutons des types fréquents), 4) Pièces (sélecteur avec origine Stock / Client / Extérieur ; pour l'huile : huile puis format bidon/vrac), 5) Récapitulatif collant avec total et bouton **Terminer et facturer**.
- **Fiche véhicule** : en-tête (matricule en grand, marque/modèle/année, propriétaire), prochaine vidange, carnet chronologique (cartes), bouton Bilan d'entretien.
- **Facture** : aperçu fidèle à l'impression, paiements, reste à payer, boutons Encaisser / Imprimer / PDF.
- **Caisse** : `PeriodPicker`, trois totaux, détail par catégorie, liste des mouvements, boutons + Dépense / + Entrée.

## 6. Animations (GSAP, Lenis, React Bits) — règles strictes

L'application est un outil de saisie : l'animation doit **guider**, jamais ralentir.

| Outil | Autorisé pour | Interdit |
| --- | --- | --- |
| **GSAP** + `@gsap/react` (`useGSAP`) | Entrée douce des cartes du tableau de bord, apparition des lignes ajoutées dans une prestation, feedback « Terminé » | Animations bloquantes, durées > 400 ms, animations au scroll dans les écrans de saisie |
| **Lenis** (`lenis/react`) | Uniquement sur les pages de lecture longues (bilan d'entretien, aperçu facture) si cela apporte un vrai confort | Écrans avec tableaux, formulaires, `Dialog`/`Sheet` (conflits de scroll) ; désactivé sur mobile si le scroll natif est meilleur |
| **React Bits** | 2–3 composants maximum : compteur animé (`CountUp`) pour les `StatCard`, une liste animée pour les alertes, un effet discret sur l'écran de connexion | Fonds animés lourds, effets 3D, tout ce qui consomme du GPU en continu |

Règles communes :
- Respecter `prefers-reduced-motion` (aucune animation si activé) via un hook `useReducedMotion` centralisé dans `lib/animations.ts`.
- Durées 150–300 ms, easing `power2.out` ; pas d'animation sur chaque frappe clavier.
- Les composants React Bits sont **copiés dans le projet** (`components/app/bits/`), adaptés aux tokens et typés en TypeScript ; ils ne doivent rien charger depuis internet.
- Mesurer : l'écran « Nouvelle prestation » doit rester fluide sur un PC modeste (4 Go RAM).

## 7. Formulaires

- Inertia `useForm` (ou composant `<Form>`), validation serveur FR, erreurs sous le champ, focus sur la première erreur.
- Entrée = valider ; Échap = fermer un dialogue ; navigation clavier complète sur PC.
- Champs numériques : clavier numérique sur mobile (`inputMode="decimal"`).
- Sauvegarde explicite (pas d'autosave), avertissement si on quitte avec des modifications non enregistrées sur la prestation.

## 8. Impression

- `PrintLayout` sans navigation ; styles `@media print` ; A4 portrait et ticket 80 mm.
- En-tête garage (logo, nom, adresse, téléphone), numéro, date, client, véhicule, km, tableau des lignes, totaux, conseil, pied de page.
- Le bilan d'entretien a une version « sans les prix » (option à cocher avant impression).

## 9. Accessibilité et langue

- Libellés visibles sur tous les champs (pas seulement des placeholders), `aria-label` sur les boutons icône.
- Textes dans `lib/i18n/fr.ts` ; prévoir `dir="rtl"` pour l'arabe plus tard (utiliser les utilitaires logiques Tailwind `ms-`/`me-`/`ps-`/`pe-`).
