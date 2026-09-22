---
title: Merchant Postbacks
---

# Merchant Postbacks

Remote merchants (separate apps/databases) report paid orders to the network
over HTTP so clicks, conversions, revenue, and ledger commissions stay in
sync without a shared database.

## Enable

```php
// config/affiliate-network.php
'postbacks' => [
    'enabled' => env('AFFILIATE_NETWORK_POSTBACKS_ENABLED', false),
    'prefix' => env('AFFILIATE_NETWORK_POSTBACKS_PREFIX', 'api/affiliate-network'),
    'middleware' => ['api', 'throttle:60,1'],
],
```

## Endpoint

`POST /api/affiliate-network/conversions`

Auth: bearer token — the site's catalog token (the same shared secret the
network uses to pull the merchant catalog, stored encrypted on the site).

```json
{
  "site": "merchant.example",
  "link_code": "a1b2c3d4e5f60001",
  "external_reference": "ORDER-1001",
  "revenue_minor": 89900,
  "currency": "MYR"
}
```

`site` accepts the site ID or domain. The link must belong to the
authenticated site, otherwise the report is rejected with `404`.

Success response:

```json
{
  "ok": true,
  "duplicate": false,
  "network": {"clicks": 12, "conversions": 3, "revenue": 179800},
  "conversion": {
    "id": "uuid",
    "affiliate_code": "PARTNER42",
    "commission_minor": 13485,
    "commission_currency": "MYR",
    "status": "approved"
  }
}
```

## Merchant SDK

Merchant apps use the SDK shipped in `aiarmada/affiliates`
(`CaptureNetworkReferral` middleware + `NetworkPostbackClient`) instead of
hand-rolled HTTP — see
[Network merchant SDK](../../affiliates/docs/15-network-merchant.md).

## Idempotency

Reporting is idempotent on (network link, external reference). Redeliveries
return `duplicate: true` with current state and never touch counters or the
ledger twice. Always send a stable merchant order reference.

## Ledger

Each first-seen report also posts an `AffiliateConversion` ledger row
(`origin: network`, commission resolved from the offer rates) so balances,
payouts, and dashboards update automatically. The `NetworkConversionRecorded`
event fires for custom automation. Expired links and inactive offers are
rejected with `410`.
