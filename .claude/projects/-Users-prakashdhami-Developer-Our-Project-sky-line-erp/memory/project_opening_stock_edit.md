---
name: project_opening_stock_edit
description: opening stock entries stay editable after approval only while their stock is untouched (reverse-and-reapply, voids GL journal)
metadata:
  type: project
---

Opening stock entries are editable after approval, but ONLY while the opened stock is still fully intact. Done 2026-07-17.

**Why:** Users need to fix opening-stock data-entry mistakes during go-live without deleting/re-creating, but must never edit stock that downstream transactions have already consumed.

**How to apply:**
- `OpeningStockEntryService::reverseApprovedOpeningStock()` guards via `assertOpeningStockUntouched()` — refuses (422, "already been used…") if, for any item's variant+warehouse: another stock movement exists, `on_hold > 0`, on-hand != opened qty, or layer `qty_remaining` sum != opened qty.
- On a valid reverse it zeroes+soft-deletes the opening `StockLayer`s, adjusts on-hand down via `StockQuantityService`, soft-deletes the opening `StockMovement`, and voids its GL journal via **soft-delete** (`Journal::delete()` — the audit-preserving void path the Journal model permits even in locked periods; only force-delete is period-guarded). Then sets status back to draft.
- Controller `OpeningStockEntryController::update()`: if `wasApproved`, reverse → replace items → `approve()` re-applies (fresh movement + fresh GL). All in one DB transaction; drafts skip reverse/approve.
- Frontend `opening-stock-entry/Edit.vue` now edits regardless of status (shows an approved-warning banner); mirrors Create (decimals, relaxed per-row validation, "Load all products", filter qty>0 on submit).

Related: [[project_opening_stock_import]], [[project_warehouse_leaf_only]]. GL auto-posting is `StockMovementGlPostingService` (Inventory Dr / Opening Stock Equity Cr for OPENING_STOCK IN).
