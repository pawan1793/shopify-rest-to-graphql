# Changelog

Tracks Shopify Admin GraphQL API version upgrades and any code-affecting changes for consumers of this package.

## 2026-04

- **Orders — `discount_allocations` may contain multiple entries per line.**
  Shopify now allows multiple product discounts to stack on a single cart line. The `discount_allocations` array returned by `OrdersEndpoints` (orders list, single order, refunds, shipping refund lines) may now contain more than one entry per line item. Consumers reading only `[0]` will lose data — iterate the array.
