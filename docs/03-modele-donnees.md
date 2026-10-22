# Modèle de données

> MariaDB 11, InnoDB, utf8mb4. Toutes les tables ont `id` (bigint) et `timestamps` sauf mention. Montants `decimal(12,2)`,
> quantités `decimal(10,2)`. `is_active` = `boolean default true`. FK des référentiels en `restrictOnDelete`.

## 1. Vue d'ensemble

```
wilayas ─┐                     brands ── vehicle_models
         ▼                        │           │
      clients ──1:n── vehicles ◄──┴───────────┘   energies
         │               │  └── vehicle_ownerships (historique des propriétaires)
         │               │
appointments ──0:1──► service_orders ──1:n── service_order_lines ──n:1── service_types
                         │      │                    │
                         │      │                    └─n:1── employees
                         │      └──1:n── service_order_parts ──n:1── articles ──n:1── oil_products
                         │                                              │
                       1:1                                       stock_movements
                         ▼                                              ▲
                     invoices ──1:n── payments ──n:1── payment_methods  │
                                                                         │
suppliers ──1:n── purchases ──1:n── purchase_items ──────────────────────┘
                      └──1:n── purchase_payments ──► expenses ──n:1── expense_categories
employees ──1:n── salary_payments ──► expenses        other_incomes
```

## 2. Référentiels

| Table | Colonnes |
| --- | --- |
| `wilayas` | `code` char(2) unique, `name`, `is_active` |
| `energies` | `name` unique, `is_active` |
| `brands` | `name` unique, `is_active` |
| `vehicle_models` | `brand_id` FK, `name`, `oil_capacity_l` decimal(4,2) null, `is_active` ; unique(`brand_id`,`name`) |
| `service_types` | `name` unique, `default_labor_price` decimal(12,2) default 0, `requires_mileage` bool, `reminder_km` int null, `is_active` |
| `article_categories` | `name` unique, `is_active` |
| `suppliers` | `name`, `phone` null, `notes` null, `is_active` |
| `expense_categories` | `name` unique, `system_key` null unique (`purchases`, `salaries`, `advances`, `external_parts`), `is_active` |
| `payment_methods` | `name` unique, `is_cash` bool, `is_active` |
| `settings` | `key` unique, `value` text null (pas de timestamps obligatoires) |
| `sequences` | `name` (`service_order`, `invoice`), `year` smallint, `last_number` int ; unique(`name`,`year`) |

Les catégories avec `system_key` ne peuvent être ni désactivées ni renommées par l'UI (utilisées par les Actions).

## 3. Utilisateurs

| Table | Colonnes |
| --- | --- |
| `users` | `name`, `username` unique, `password`, `role` enum(`gerant`,`employe`), `is_active`, `remember_token` ; `email` nullable |

## 4. Clients et véhicules

| Table | Colonnes |
| --- | --- |
| `clients` | `first_name` null, `last_name`, `phone`, `phone_normalized` unique, `wilaya_id` FK null, `notes` null, `is_active` |
| `vehicles` | `client_id` FK (propriétaire actuel), `plate`, `plate_normalized` unique, `vin` null unique, `brand_id` FK, `vehicle_model_id` FK null, `year` smallint null, `energy_id` FK null, `last_mileage` int null, `notes` null, `is_active` |
| `vehicle_ownerships` | `vehicle_id` FK, `client_id` FK, `started_at` date, `ended_at` date null |

## 5. Stock

| Table | Colonnes |
| --- | --- |
| `oil_products` | `brand`, `name` (gamme) null, `viscosity` (ex. `5W-30`), `usage` enum(`moteur`,`boite`), `approval` null, `is_active` |
| `articles` | `article_category_id` FK, `oil_product_id` FK null, `oil_format` enum(`can_5l`,`can_4l`,`can_1l`,`bulk`) null, `volume_l` decimal(4,2) null, `stock_unit` enum(`unit`,`liter`), `name`, `brand` null, `reference` null, `purchase_price`, `sale_price`, `stock_qty` decimal(10,2) default 0, `alert_threshold` decimal(10,2) null, `is_active` |
| `stock_movements` | `article_id` FK, `type` enum(`purchase_in`,`service_out`,`adjustment`,`can_opened_out`,`can_opened_in`,`cancellation_in`), `quantity` decimal(10,2) signé, `unit_cost` null, `source_type`/`source_id` (morph) null, `reason` null, `user_id` FK ; `created_at` seulement |

Règles :
- Un format d'huile = un `article` avec `oil_product_id` + `oil_format`. Bidons : `stock_unit=unit`, `volume_l` = 5 / 4 / 1. Vrac : `stock_unit=liter`, `volume_l` null.
- Unique(`oil_product_id`,`oil_format`) quand `oil_product_id` non null.
- `articles.stock_qty` est **toujours** modifié par `StockService` avec un `stock_movements` (jamais directement).
- `OpenOilCan` exige un article vrac actif pour le même `oil_product_id`.

## 6. Achats

| Table | Colonnes |
| --- | --- |
| `purchases` | `supplier_id` FK, `purchased_at` date, `supplier_invoice_ref` null, `total`, `amount_paid` default 0, `notes` null, `user_id` FK |
| `purchase_items` | `purchase_id` FK cascade, `article_id` FK, `quantity`, `unit_price`, `line_total` |
| `purchase_payments` | `purchase_id` FK, `amount`, `paid_at` date, `payment_method_id` FK, `user_id` FK |

Reste dû d'un achat = `total − amount_paid` ; dette fournisseur = somme des restes.

## 7. Prestations

| Table | Colonnes |
| --- | --- |
| `service_orders` | `number` unique (`PR-2026-00001`), `client_id` FK, `vehicle_id` FK, `date` date, `mileage` int null, `immobilization_value` smallint null, `immobilization_unit` enum(`hours`,`days`) null, `notes` null, `advice` null, `status` enum(`en_cours`,`terminee`), `completed_at` null, `user_id` FK |
| `service_order_lines` | `service_order_id` FK cascade, `service_type_id` FK, `employee_id` FK, `description` null, `labor_price`, `position` smallint |
| `service_order_parts` | `service_order_id` FK cascade, `service_order_line_id` FK null, `origin` enum(`stock`,`client`,`external`), `article_id` FK null, `designation` (copie lisible), `quantity`, `unit_price` (0 si `client`), `unit_cost` null (figé à la sortie de stock ; prix d'achat pour `external`), `line_total` |

`client_id` est copié sur la prestation au moment de la création (propriétaire à cette date) ; le carnet du véhicule passe par `vehicle_id`.

## 8. Factures et paiements

| Table | Colonnes |
| --- | --- |
| `invoices` | `number` unique (`FA-2026-00001`), `service_order_id` FK unique, `client_id` FK, `issued_at` date, `labor_total`, `parts_total`, `discount` default 0, `total`, `amount_paid` default 0, `status` enum(`impayee`,`partielle`,`payee`,`annulee`), `cancelled_at` null, `cancel_reason` null, `cancelled_by` FK null |
| `payments` | `invoice_id` FK, `client_id` FK, `amount`, `payment_method_id` FK, `paid_at` datetime, `cancelled_at` null, `notes` null, `user_id` FK |

Crédit client = somme de (`total − amount_paid`) des factures non annulées du client.

## 9. Caisse et personnel

| Table | Colonnes |
| --- | --- |
| `expenses` | `expense_category_id` FK, `label`, `amount`, `spent_at` date, `payment_method_id` FK null, `employee_id` FK null, `source_type`/`source_id` (morph : PurchasePayment, SalaryPayment, ServiceOrderPart) null, `notes` null, `user_id` FK |
| `other_incomes` | `label`, `amount`, `received_at` date, `payment_method_id` FK null, `notes` null, `user_id` FK |
| `employees` | `name`, `position` null, `phone` null, `salary_type` enum(`monthly`,`daily`), `salary_amount`, `is_active` |
| `salary_payments` | `employee_id` FK, `period` char(7) (`2026-10`), `days_worked` smallint null, `base_amount`, `advances_total`, `net_paid`, `paid_at` date, `user_id` FK ; unique(`employee_id`,`period`) |

Une avance = une `expense` de catégorie système `advances` avec `employee_id`.

## 10. Rendez-vous

| Table | Colonnes |
| --- | --- |
| `appointments` | `client_id` FK, `vehicle_id` FK null, `scheduled_at` datetime, `planned_services` text null, `notes` null, `status` enum(`prevu`,`effectue`,`annule`), `service_order_id` FK null, `user_id` FK |

## 11. Enums PHP (app/Enums)

`UserRole`, `OilFormat`, `OilUsage`, `StockUnit`, `StockMovementType`, `ServiceOrderStatus`, `PartOrigin`,
`ImmobilizationUnit`, `InvoiceStatus`, `SalaryType`, `AppointmentStatus` — chacun avec `label(): string` en français
et une méthode statique `options()` renvoyant `[{value, label}]` pour le front.
