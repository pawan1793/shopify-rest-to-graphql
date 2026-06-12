# Changelog

Tracks Shopify Admin GraphQL API version upgrades and any code-affecting changes for consumers of this package.

## 2026-04

- **Inventory — mutations updated for mandatory idempotency, `changeFromQuantity`, and compare-and-swap redesign.**
  Shopify made several inventory changes mandatory on `2026-04`. `InventoryEndpoints` was updated to comply; behavior for consumers is unchanged:
  - `@idempotent` directive is now required on inventory mutations. Added a UUID v4 idempotency key to `inventorySetQuantities()` and `inventoryAdjustQuantities()` (the set-quantities retry after activation uses a fresh key so it isn't treated as a duplicate).
  - `ignoreCompareQuantity` / `compareQuantity` were removed (compare-and-swap redesign); `ignoreCompareQuantity` was dropped from `inventorySetQuantities()`.
  - `changeFromQuantity` is now required; added as `null` (skips the concurrency check, preserving the prior overwrite behavior) on `inventorySetQuantities()` and `inventoryAdjustQuantities()`.
  - Note: `inventoryLevels` now returns active levels only by default (`includeInactive` defaults to `false`); pass `includeInactive: true` if you need inactive locations.

- **Orders — `discount_allocations` may contain multiple entries per line.**
  Shopify now allows multiple product discounts to stack on a single cart line. The `discount_allocations` array returned by `OrdersEndpoints` (orders list, single order, refunds, shipping refund lines) may now contain more than one entry per line item. Consumers reading only `[0]` will lose data — iterate the array.
