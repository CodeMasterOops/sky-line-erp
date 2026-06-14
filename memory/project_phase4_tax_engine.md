---
name: project-phase4-tax-engine
description: Phase 4 Tax Engine Enhancement — new tables, TaxCalculationEngine, per-tax GL posting, frontend tax group selector
metadata:
  type: project
---

Phase 4 of the sales module audit roadmap (Items 23–27) was completed on 2026-06-14.

**Why:** Audit report `docs/sales-module-audit.md` identified the single-tax-per-line architecture as insufficient for inclusive/compound/multi-tax scenarios.

**What was implemented:**

- **Item 23 (Migrations + Models):**
  - `taxes` table: added `calculation_type`, `is_inclusive`, `is_compound`, `sequence`, `gl_account_id`
  - New `tax_groups` table (company_id, name, description, is_active, softDeletes)
  - New `tax_group_members` table (tax_group_id, tax_id, sequence; unique constraint)
  - New `product_taxes` table (product_id, tax_id; default tax per product)
  - New `party_tax_exemptions` table (company_id, party_id, tax_id, exemption_reason, valid_from, valid_to)
  - `invoice_items.tax_group_id` FK to tax_groups (nullable)
  - Models: `TaxGroup`, `TaxGroupMember`, `ProductTax`, `PartyTaxExemption`; `Tax` model updated

- **Item 24 (TaxCalculationEngine):**
  - `app/Services/Tax/TaxCalculationEngine.php` — supports exclusive, inclusive, compound taxes and party exemptions
  - `app/Services/Tax/TaxCalculationResult.php` — DTO with taxableAmount, totalTaxAmount, lines[]
  - Key: inclusive: `tax = price - price/(1+rate/100)`, compound: uses running total as base

- **Item 25 (InvoiceRequest multi-tax):**
  - `InvoiceRequest` now accepts `items.*.tax_group_id` (nullable TRule exists on tax_groups)
  - `InvoiceService::resolveItemTax()` computes `tax_amount` server-side when `tax_group_id` is present

- **Item 26 (Per-tax GL posting):**
  - `InvoiceGlPostingService::buildTaxGroupGlLines()` — for items with tax_group_id, posts to each tax's `gl_account_id` (fallback: global `vat_account_id`)
  - Items without tax_group_id use the existing single-account VAT posting

- **Item 27 (Frontend):**
  - `TaxGroupController` (CRUD for tax groups) + `GET /api/admin/tax-group`
  - `POST /api/admin/tax/calculate` — TaxCalculationController for frontend async computation
  - `tax.js` store: added `taxGroups` state + `getTaxGroups()` + `calculateTaxGroup()` actions
  - `Create.vue`: optgroup dropdown shows Tax Groups / Individual Taxes; async fetch on group select
  - `useLineOrderDiscountTotals.js`: `calcLineTax` returns `item.tax_amount` for group items; `syncTaxAmounts` skips recomputation for group items

**Tests:** 17 passing in `tests/Feature/Phase4TaxEngineTest.php`; full suite 481 passed.

**How to apply:** When working on tax calculations or invoice GL posting, check TaxCalculationEngine for inclusive/compound logic. Tax groups are company-scoped (MultiTenant). Party exemptions are date-ranged.
