# Changelog

Tracks Shopify Admin GraphQL API version upgrades and any code-affecting changes for consumers of this package.

## Unreleased

- **Bulk Operations endpoint.** New `Endpoints\BulkOperationsEndpoints`:
  `runQuery($query, $clientIdentifier)`, `stagedUploadJsonl($path)` (performs the multipart
  POST with the returned parameters, returns the `stagedUploadPath`), `runMutation($mutation,
  $stagedUploadPath, $clientIdentifier)`, `get($id)` (`bulkOperation(id:)` with status,
  errorCode, type, objectCount, rootObjectCount, fileSize, url, partialDataUrl, createdAt,
  completedAt), `current($type)`, `cancel($id)`, `downloadToFile($url, $path, $timeout = 600)`
  (Guzzle `sink`), `subscribeFinishWebhook($address)` (`bulk_operations/finish` through
  `WebhooksEndpoints`). userErrors are thrown as `GraphqlException` code 400 with the
  userErrors array in `getErrors()`.
- **Typed responses.** `GraphqlService::query($query, $variables)` returns a `GraphqlResponse`
  (`data()`, `errors()`, `extensions()`, `cost()`, `requestedQueryCost()`, `actualQueryCost()`,
  `throttleStatus()`, `toArray()`, ArrayAccess). `graphqlQueryThalia()` is unchanged.
- **Throttle details on exceptions.** `GraphqlException::getThrottleStatus()` carries
  `extensions.cost.throttleStatus` of a THROTTLED answer (null when Shopify did not send one);
  `isThrottled()` and `isRetryable()` (throttled, 5xx, connection failures) help callers decide
  on a retry.
- **Cost observer.** `GraphqlService::setCostLogger(callable)` is called after every request with
  `($query, $cost, $milliseconds, $shopDomain)`; exceptions from the logger are swallowed.

- **HTTP timeouts on every request.** `GraphqlService` now builds its Guzzle client with
  `timeout => 90` and `connect_timeout => 10` by default (previously none, so a stalled
  Shopify response could hold a PHP worker indefinitely). The constructor gained an optional
  third argument `array $options = []` merged over the defaults (e.g. `['timeout' => 300]`),
  and `GraphqlService::setDefaultOptions([...])` / `getDefaultOptions()` change the defaults
  for every instance, including the ones the `*Endpoints` classes create internally.
  Existing two-argument calls are unaffected.
- **OAuth — support for Shopify expiring offline access tokens.**
  Shopify is replacing non-expiring offline tokens with expiring ones (public apps created before
  2026-04-01 must migrate by 2027-01-01, after which non-expiring token requests error out;
  custom/merchant-created apps are exempt). `OauthEndpoints` adds the following (all additive —
  existing methods and behavior are unchanged):
  - `getExpiringAccessToken(string $code): array` — authorization-code grant with `expiring=1`;
    returns the full response (`access_token`, `expires_in`, `refresh_token`,
    `refresh_token_expires_in`, `scope`). Use this instead of `getAccessToken()` for new installs.
  - `refreshOfflineAccessToken(string $refreshToken, ?string $clientId = null, ?string $clientSecret = null): array` —
    conformant refresh (`grant_type=refresh_token`); `client_id`/`client_secret` default to the
    constructor values. Returns a new access token AND a new refresh token; the previous refresh
    token is invalidated immediately.
  - `migrateToExpiringToken(string $nonExpiringToken, ?string $clientId = null, ?string $clientSecret = null): array` —
    one-time token exchange to upgrade an existing non-expiring token. The original token is
    revoked on success (irreversible).
  - `OauthEndpoints::toStorage(array $response, ?int $now = null): array` — maps the relative
    `expires_in` / `refresh_token_expires_in` to absolute `expires_at` / `refresh_token_expires_at`
    for persistence.
  - `getAccessToken()` (non-expiring, bare-string return) is unchanged. `refreshAccessToken()` is
    **deprecated** (superseded by `refreshOfflineAccessToken()`; it was non-conformant and unused)
    and left untouched for backward compatibility.
  - **Consumers must persist three new fields** alongside the access token: `expires_at`,
    `refresh_token`, `refresh_token_expires_at`. Refresh proactively before expiry and reactively
    on an HTTP 401.

## 2026-04

- **Inventory — mutations updated for mandatory idempotency, `changeFromQuantity`, and compare-and-swap redesign.**
  Shopify made several inventory changes mandatory on `2026-04`. `InventoryEndpoints` was updated to comply; behavior for consumers is unchanged:
  - `@idempotent` directive is now required on inventory mutations. Added a UUID v4 idempotency key to `inventorySetQuantities()` and `inventoryAdjustQuantities()` (the set-quantities retry after activation uses a fresh key so it isn't treated as a duplicate).
  - `ignoreCompareQuantity` / `compareQuantity` were removed (compare-and-swap redesign); `ignoreCompareQuantity` was dropped from `inventorySetQuantities()`.
  - `changeFromQuantity` is now required; added as `null` (skips the concurrency check, preserving the prior overwrite behavior) on `inventorySetQuantities()` and `inventoryAdjustQuantities()`.
  - Note: `inventoryLevels` now returns active levels only by default (`includeInactive` defaults to `false`); pass `includeInactive: true` if you need inactive locations.

- **Orders — `discount_allocations` may contain multiple entries per line.**
  Shopify now allows multiple product discounts to stack on a single cart line. The `discount_allocations` array returned by `OrdersEndpoints` (orders list, single order, refunds, shipping refund lines) may now contain more than one entry per line item. Consumers reading only `[0]` will lose data — iterate the array.
