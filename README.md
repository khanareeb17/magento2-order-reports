# Sales Orders Cost Report

Magento 2 module that extends **Reports → Sales → Orders** with a **Cost** column. The value is the sum of *(product catalog cost × net quantity ordered)* for all line items in each report period, aligned with how other monetary columns use the store’s **base → global** conversion.

**Module name:** `AkStackPro_SalesOrdersCostReport`  
**Composer package:** `akstackpro/module-sales-orders-cost-report` (see `composer.json`)

---

## Table of contents

1. [Overview](#overview)  
2. [Features](#features)  
3. [Compatibility](#compatibility)  
4. [Requirements](#requirements)  
5. [Installation](#installation)  
6. [Usage (admin)](#usage-admin)  
7. [How it works](#how-it-works)  
8. [Database](#database)  
9. [Technical architecture](#technical-architecture)  
10. [Refreshing report data](#refreshing-report-data)  
11. [Troubleshooting](#troubleshooting)  
12. [Uninstall notes](#uninstall-notes)  
13. [License](#license)  
14. [Changelog](#changelog)

---

## Overview

The native Sales Orders report is built from **aggregated** tables (`sales_order_aggregated_created` / `sales_order_aggregated_updated`). This module:

- Adds a new decimal column **`total_item_cost_amount`** to both tables.
- Extends the **order aggregation** logic so that column is filled when statistics are refreshed (cron or manual).
- Adds a **Cost** grid column that appears only when **Show Actual Values** is set to **Yes** (same behaviour as Revenue, Profit, Tax “actual” columns).

Missing product cost is treated as **0** (line items are still counted; no silent row drop).

---

## Features

- **Cost column** on Reports → Sales → Orders, visible when **Show Actual Values = Yes**.
- **Column order**: Cost is placed immediately after **Revenue** when both columns are present (safe handling so the grid does not break when actual columns are hidden).
- **Totals row**: Cost participates in the footer **sum** like other numeric totals.
- **Export**: CSV / Excel XML exports include the new column when it is visible on the grid.
- **Both report types**: Works for **Order Created** and **Order Updated** report modes (created vs updated aggregation tables).
- **Catalog cost source**: Uses the standard **`cost`** EAV attribute on products (`catalog_product_entity_decimal`, **global** / `store_id = 0` value).
- **Configurable / bundle aware**: Only **parent** order items are included (`parent_item_id IS NULL`) so child SKUs are not double-counted.

---

## Compatibility

| Area | Details |
|------|---------|
| **Magento Open Source** | Intended for **2.4.x**; `composer.json` constraints match a **2.4.7-p5** stack (`magento/framework` ~103.0, `module-sales` ~103.0, `module-reports` ~100.4, `module-catalog` ~104.0, `module-eav` ~102.1). |
| **Adobe Commerce** | Not separately certified; same core modules apply—install on a staging instance first. |
| **PHP** | `~8.1.0 \|\| ~8.2.0 \|\| ~8.3.0 \|\| ~8.4.0` (per `composer.json`). |
| **MySQL / MariaDB** | Uses declarative schema (`db_schema.xml`); InnoDB tables only. |

Older 2.4.x may work if dependency versions in your `composer.lock` satisfy the ranges; always verify on a copy of production.

---

## Requirements

- Magento modules: **Sales**, **Reports**, **Catalog**, **Eav** (declared in `module.xml` sequence).
- Products should have **`cost`** populated in the catalog for meaningful numbers (otherwise cost contributes **0**).
- After install, **report statistics must be refreshed** at least once so `total_item_cost_amount` is populated (see [Refreshing report data](#refreshing-report-data)).

---

## Installation

### Option A — Copy into `app/code` (traditional)

1. Copy the `AkStackPro/SalesOrdersCostReport` folder under `app/code/`.
2. Enable the module and upgrade the database:

   ```bash
   bin/magento module:enable AkStackPro_SalesOrdersCostReport
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```

3. In **production** mode, also run:

   ```bash
   bin/magento setup:di:compile
   bin/magento setup:static-content:deploy -f
   ```

### Option B — Composer (path repository)

If you publish or symlink the package, add a path repository in the project root `composer.json` and require `akstackpro/module-sales-orders-cost-report`, then run `composer update` and `bin/magento setup:upgrade`.

---

## Usage (admin)

1. Go to **Reports → Sales → Orders**.
2. Set **Show Actual Values** to **Yes**.
3. Choose date range, store scope, order status, and report type (**Order Created** or **Order Updated**).
4. Click **Show Report**.

You should see **Cost** next to **Revenue**, with a total in the grid footer when totals are enabled.

---

## How it works

### Aggregation formula (per order, then rolled into period / store / status)

For each **parent** `sales_order_item` row:

- **Net qty** = `qty_ordered - IFNULL(qty_canceled, 0)`
- **Unit cost** = catalog `cost` at **global** scope (`catalog_product_entity_decimal`, `store_id = 0`), or **0** if missing
- **Line cost (base)** ≈ `net_qty × unit_cost`

These are summed per order in a subquery join, then multiplied by the order’s **`base_to_global_rate`** when writing **`total_item_cost_amount`** into the aggregated row—consistent with other amount columns on the same report.

### Cost attribute ID

The **`cost`** attribute ID is resolved at runtime via `Magento\Eav\Model\Config` (no hard-coded attribute id).

---

## Database

| Table | Change |
|-------|--------|
| `sales_order_aggregated_created` | Column **`total_item_cost_amount`** `DECIMAL(20,4)` NOT NULL default `0` |
| `sales_order_aggregated_updated` | Same column |

`etc/db_schema_whitelist.json` documents the additive schema for tooling / deployments.

---

## Technical architecture

| Mechanism | Purpose |
|-----------|---------|
| **Preference** `Magento\Sales\Model\ResourceModel\Report\Order\Createdat` | Extends `_aggregateByField()` to compute and persist `total_item_cost_amount`. |
| **Preference** `Magento\Sales\Model\ResourceModel\Report\Order\Updatedat` | Same logic for the **updated_at** aggregation table. |
| **Preference** `Magento\Sales\Model\ResourceModel\Report\Order\Collection` | Adds `SUM(total_item_cost_amount)` to the non-totals SELECT. |
| **Preference** `…\Order\Updatedat\Collection` | Points at `sales_order_aggregated_updated` while inheriting the extended collection. |
| **Preference** `Magento\Reports\Block\Adminhtml\Sales\Sales\Grid` | Adds the **Cost** column + safe reorder after **Revenue** when both exist. |

No core `vendor/` files are modified; behaviour is swapped via `app/code/.../etc/di.xml`.

---

## Refreshing report data

Aggregates are **not** live on every page load for the **Order Created** path; they must be rebuilt.

- **Admin:** **Reports → Statistics** (or equivalent menu in your build) → refresh **Orders** / lifetime statistics as per your Magento version.  
- **Cron:** Ensure Magento cron is running; order report aggregation runs on schedule.

Until aggregation runs, **Cost** may show **0** even when products have cost.

---

## Troubleshooting

| Symptom | What to check |
|---------|----------------|
| **Cost always 0** | Product **Cost** attribute filled? Run report statistics refresh. Check `sales_order_aggregated_*`.`total_item_cost_amount` in the DB after refresh. |
| **Column missing** | **Show Actual Values** must be **Yes** (by design). |
| **CLI errors / missing Proxy classes** | File permissions on `generated/` and `var/`: web server or CLI user must be able to write generated code. |
| **Wrong totals after catalog cost change** | Re-run aggregation for the affected date range so aggregated rows are rebuilt. |

---

## Uninstall notes

Removing the module cleanly requires:

1. Disable the module and run `setup:upgrade` **after** you have removed or reversed the DB column (Magento declarative schema does not automatically drop columns on module disable).

2. Manually drop **`total_item_cost_amount`** from both aggregated tables if you no longer need the data, or restore from backup.

3. Remove preferences by uninstalling the module code so `di.xml` is no longer merged.

Plan this on a staging database first.

---

## License

Open Software License **OSL-3.0** and Academic Free License **AFL-3.0** (see `composer.json`). Use and redistribute according to those licenses and your Magento license terms.

---

## Changelog

### 1.0.0

- Initial release: `total_item_cost_amount` on aggregated order tables, aggregation logic, report grid **Cost** column with **Show Actual Values** visibility, safe column ordering fix for admin grid.

---

## Support & author

- **Author:** Areeb Khan (SR. Backend Developer)
- **Homepage:** [http://akstackpro.com/](http://akstackpro.com/)

For issues, confirm Magento version, PHP version, and whether report statistics have been refreshed after installing the module.
