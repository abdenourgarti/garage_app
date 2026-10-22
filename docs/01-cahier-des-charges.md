# Cahier des charges — Application de gestion de garage automobile

> Version validée avec le client (octobre 2026). Source de vérité fonctionnelle.

## 1. Présentation

L'application remplace le cahier papier du garage : chaque prestation est enregistrée (quoi, qui, quelles pièces, quel kilométrage),
le stock et la facture en découlent, et le gérant voit combien il a encaissé, combien il a dépensé et la différence,
par jour, semaine, mois et année. Elle tourne en local sur le PC du garage, sans internet.

### 1.1 Contexte

Garage de vidange et de mécanique générale. Aujourd'hui tout est géré à la main : historique introuvable quand un client
réclame, stock d'huile approximatif, aucune idée claire du bénéfice réel.

### 1.2 Principe directeur : la simplicité

- Peu de champs obligatoires ; tout le reste est optionnel.
- Listes déroulantes plutôt que saisie libre, gros boutons, écrans courts.
- Aucun vocabulaire comptable : entrées, dépenses, bénéfice.
- Une prestation complète (client connu, vidange, facture) se saisit en moins de deux minutes.

### 1.3 Objectifs

1. Enregistrer chaque prestation : travaux réalisés, mécanicien, pièces, kilométrage.
2. Gérer le stock automatiquement, y compris l'huile en bidon et en vrac.
3. Produire devis, factures et reçus, avec suivi des paiements et des crédits clients.
4. Savoir combien est entré dans la caisse, combien est sorti (achats, salaires, charges) et le bénéfice.
5. Retrouver tout l'historique en quelques secondes (client, véhicule, date, type de prestation).
6. Fidéliser : rappel de prochaine vidange et bilan d'entretien imprimable pour la revente du véhicule.

### 1.4 Périmètre

**Inclus :** clients et véhicules, rendez-vous, prestations, stock et achats, factures et paiements, caisse, employés et salaires,
tableau de bord, recherche, bilan d'entretien, sauvegardes.

**Exclu :** comptabilité fiscale, TVA, déclarations, application mobile native, espace client en ligne.

### 1.5 Utilisateurs

| Rôle | Peut faire |
| --- | --- |
| Gérant | Tout : caisse, bénéfice, salaires, paramètres, sauvegardes, annulation de factures |
| Employé | Clients, véhicules, rendez-vous, prestations, factures, encaissements, entrées de stock ; ne voit ni le bénéfice ni les salaires |

Les mécaniciens n'ont pas de compte : ils sont choisis dans une liste sur chaque prestation.

## 2. Référentiels

Tous les référentiels sont livrés pré-remplis et modifiables par le gérant. Un élément est **Actif** ou **Inactif** :
inactif = caché des listes de saisie mais visible dans l'historique. Suppression interdite dès qu'il est utilisé.

| Référentiel | Champs | Exemples |
| --- | --- | --- |
| Marques | Nom | Renault, Peugeot, Volkswagen, Hyundai, Toyota |
| Modèles | Marque, nom, capacité huile moteur en litres (optionnel) | Clio 4, 208, Golf 7, Accent |
| Énergies | Libellé | Essence, Diesel, GPL, Hybride, Électrique |
| Wilayas | Code, nom | 16 Alger, 09 Blida, 31 Oran |
| Types de prestations | Libellé, prix MO par défaut, kilométrage demandé (oui/non), rappel conseillé en km (optionnel) | Vidange moteur, vidange boîte, kit chaîne de distribution, croisillon, plaquettes |
| Catégories d'articles | Libellé | Huiles, filtres, distribution, freinage, transmission, divers |
| Fournisseurs | Nom, téléphone, remarque | — |
| Catégories de dépenses | Libellé | Achats pièces, salaires, avances, loyer, électricité, eau, outillage, divers |
| Modes de paiement | Libellé | Espèces, chèque, virement |

### 2.1 Articles (pièces)

Désignation, catégorie, marque, référence, prix d'achat, prix de vente, quantité en stock, seuil d'alerte (optionnel), Actif/Inactif.

### 2.2 Huiles : bidon et vrac

Une huile a une fiche (marque, viscosité ex. 5W-30 / 0W-20, usage moteur ou boîte, homologation en texte libre optionnel)
et un ou plusieurs **formats de vente**, chacun avec son prix d'achat, son prix de vente et son stock :

| Format | Unité de stock |
| --- | --- |
| Bidon 5 L, 4 L, 1 L | bidon (unité) |
| Vrac | litre — prix au litre différent de celui du bidon |

Sur une prestation, on choisit l'huile puis le format : « 1 bidon 5 L » ou « 4,5 L en vrac ». Le stock vrac est alimenté par
l'achat d'un fût ou par le bouton **« Ouvrir un bidon »** (retire 1 bidon, ajoute sa contenance en litres au vrac).

## 3. Modules fonctionnels

### M1. Clients et véhicules

- Client : nom, prénom, téléphone (obligatoire, unique), wilaya, remarque.
- Véhicule : matricule (obligatoire), châssis (recommandé), marque, modèle, année, énergie. Dernier kilométrage repris de la dernière prestation.
- Un client a plusieurs véhicules. « Changer de propriétaire » transfère le véhicule avec tout son historique.
- Fiche client : véhicules, prestations, factures, crédit restant. Fiche véhicule : carnet d'entretien, prochaine vidange.

### M2. Rendez-vous

- Liste du jour / de la semaine + vue calendrier simple. Saisie : client, véhicule, date, heure, prestations prévues et remarque (optionnelles).
- Statuts : Prévu, Effectué, Annulé. « Commencer » crée la prestation pré-remplie et passe le RDV à Effectué.

### M3. Prestations

- En-tête : numéro auto, client, véhicule, date, kilométrage (obligatoire si un type le demande), durée d'immobilisation en heures ou jours (optionnelle), remarque.
- Travaux : une ou plusieurs lignes (type, mécanicien, prix MO proposé et modifiable).
- Pièces, avec origine : **Stock** (article + quantité, prix proposé, huile bidon ou vrac) · **Apportée par le client** (historique seulement, sans prix ni stock) · **Achetée à l'extérieur** (désignation, prix d'achat compté en dépense, prix facturé).
- Conseil pour la prochaine visite (optionnel), repris sur facture et bilan.
- Statuts : En cours → Terminée. À la fin : sortie de stock et facture générée en un clic.

### M4. Stock et achats

- Achat fournisseur (fournisseur, date, articles, quantités, prix) → stock augmenté ; montant payé = dépense ; reste dû sur la fiche fournisseur.
- Sorties automatiques à la fin des prestations ; correction de stock avec motif ; « Ouvrir un bidon » ; alertes seuil ; historique par article.

### M5. Factures et paiements

- Facture issue de la prestation : MO, pièces, remise, total en DA. Pas de TVA.
- Numéro auto ; non modifiable après paiement ; seul le gérant annule, avec motif.
- Paiement total ou partiel ; le reste alimente le crédit client.
- Impression A4 ou ticket 80 mm, export PDF. En-tête : nom, logo, adresse, téléphone du garage.

### M6. Employés et salaires

- Fiche : nom, poste, téléphone, salaire mensuel ou journalier, Actif/Inactif.
- Avances saisies au jour le jour. Fin de mois : salaire − avances = reste à verser → dépense.

### M7. Caisse

- Entrées : paiements clients (auto) + autre entrée manuelle.
- Dépenses : achats, salaires, avances (auto) + loyer, électricité, etc. (catégorie + montant).
- Bénéfice = entrées − dépenses, par jour, semaine, mois, année ou entre deux dates, détail par catégorie. Solde de caisse du jour.

### M8. Tableau de bord

- Aujourd'hui : entrées, dépenses, bénéfice, nb prestations, RDV du jour, véhicules en cours.
- Graphique 12 derniers mois (entrées, dépenses, bénéfice). Classements : prestations, huiles, pièces.
- À surveiller : stock bas, crédits clients, vidanges à échéance.

### M9. Recherche, historique, bilan

- Barre unique (téléphone, nom, matricule, châssis, n° facture) + filtres : téléphone/nom/prénom, wilaya, matricule/châssis,
  marque/modèle/année, date du… au…, type de prestation (multiple).
- Bilan d'entretien imprimable (option « sans les prix »). Liste des rappels de vidange avec téléphone.

### Paramètres

Nom, logo, adresse, téléphone du garage ; comptes utilisateurs ; référentiels ; sauvegarde et restauration.

## 4. Scénarios d'utilisation (cas de recette)

- **S1 Nouveau client pour une vidange** : création client + véhicule, prestation avec km, vidange moteur, huile « 1 bidon 5 L » ou « 4,5 L vrac », filtre, Terminer → stock + facture, paiement, impression.
- **S2 Plusieurs travaux** : une prestation, trois lignes (vidange moteur, vidange boîte, kit chaîne), chacune son mécanicien et ses pièces, durée « 2 jours », une seule facture.
- **S3 Pièce du client / achetée dehors** : ligne « apportée par le client » (MO seule facturée) ; ligne « achetée à l'extérieur » (prix d'achat en dépense, prix facturé).
- **S4 Rendez-vous** : création, apparition le jour J, « Commencer » crée la prestation ; client absent → Annulé.
- **S5 Réclamation** : recherche par téléphone/matricule, carnet du véhicule, reprise gratuite (MO 0 DA).
- **S6 Achat fournisseur** : 10 bidons 5 L de 5W-30 ; montant payé → dépense, reste → dette fournisseur ; stock augmenté.
- **S7 Paiement partiel** : facture 45 000 DA, versement 30 000 DA, crédit 15 000 DA, règlement ultérieur.
- **S8 Dépenses, salaires, bénéfice** : loyer et électricité, avances, validation des salaires, bénéfice du mois par catégorie.
- **S9 Vente du véhicule** : bilan sans prix ; « Changer de propriétaire » transfère véhicule + historique.

## 5. Règles de gestion

- **RG-01** Tout référentiel est Actif ou Inactif (inactif = caché des listes, visible dans l'historique).
- **RG-02** Suppression interdite dès qu'un élément est utilisé ; le message propose de le désactiver.
- **RG-03** Téléphone unique par client ; matricule unique par véhicule.
- **RG-04** L'historique appartient au véhicule : un changement de propriétaire ne déplace ni ne supprime aucune prestation.
- **RG-05** Prestation : En cours → Terminée. En cours : supprimable ; Terminée : non.
- **RG-06** Kilométrage obligatoire si un type le demande ; inférieur au précédent → avertissement non bloquant.
- **RG-07** Chaque ligne de travaux a un mécanicien et un prix MO (0 DA autorisé).
- **RG-08** Les pièces sortent du stock quand la prestation passe à Terminée.
- **RG-09** Chaque format d'huile a son prix d'achat, son prix de vente et son stock ; vrac en litres, bidons à l'unité.
- **RG-10** « Ouvrir un bidon » retire 1 bidon et ajoute sa contenance en litres au vrac.
- **RG-11** Stock insuffisant → alerte, sans bloquer la prestation.
- **RG-12** Numérotation continue par année. Facture payée/partiellement payée non modifiable ; seul le gérant annule (motif), ce qui annule aussi ses entrées en caisse.
- **RG-13** Reste à payer = total − paiements ; somme des restes = crédit client.
- **RG-14** Bénéfice d'une période = entrées encaissées − dépenses payées ; achats, salaires, avances comptent le jour du paiement.
- **RG-15** Prochaine vidange = km de la dernière vidange + rappel conseillé du type (ex. 10 000 km).

## 6. Exigences non fonctionnelles

| Domaine | Exigence |
| --- | --- |
| Simplicité | Français, gros boutons, listes déroulantes, minimum de champs obligatoires, recherche instantanée, pas de jargon |
| Hors-ligne | Tout fonctionne sans internet ; aucune ressource externe |
| Démarrage | Serveur et base démarrent avec Windows ; double-clic sur une icône |
| Plusieurs postes | Optionnel : un 2e PC (ou smartphone) via le réseau local |
| Sécurité | Un compte par utilisateur, 2 rôles ; bénéfice et salaires réservés au gérant |
| Sauvegardes | Quotidiennes automatiques vers disque D: ou clé USB, 30 jours ; bouton « Sauvegarder maintenant » ; restauration testée |
| Coupures | Base transactionnelle ; onduleur conseillé |
| Impression | Facture A4 ou ticket, bilan d'entretien ; export PDF |
| Mises à jour | Sur clé USB ; sauvegarde de la base avant mise à jour |
| Licence | Clé liée au PC du garage |

## 7. Architecture retenue

Apache 2.4 + PHP (mod_php) et MariaDB installés comme **services Windows à démarrage automatique** ; icône bureau
`msedge.exe --app=http://garage.local` (fenêtre sans barre d'adresse). Installateur Inno Setup. Détail : `06-deploiement-windows.md`.

## 8. Lots

| Lot | Contenu |
| --- | --- |
| 0. Conception | Validation CDC, maquettes, modèle de données |
| 1. Atelier | Référentiels, clients/véhicules, prestations, stock et huiles, achats, factures et paiements, recherche, installateur, sauvegarde |
| 2. Caisse | Dépenses, employés, avances, salaires, bénéfice, tableau de bord |
| 3. Fidélisation | Rendez-vous, bilan d'entretien, rappels |

Questions encore ouvertes : nombre de postes, imprimante (A4/ticket), reprise de données, arabe en V1, modèle commercial.
