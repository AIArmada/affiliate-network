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
  "currency": "MYR",
  "engine_touch_at": "2026-10-17T08:55:00+08:00",
  "network_clicked_at": "2026-10-17T08:57:00+08:00"
}
```

`engine_touch_at` is required and nullable: send `null` when the merchant
engine has no attribution touch for this order. `network_clicked_at` is
required and is the server-observed instant captured with the network link
code. Both values must be parseable timestamps. Touches more than five minutes
in the future are rejected, matching the local checkout snapshot rule.

The network decides the winner from these reported instants. The newer touch
wins; an absent engine touch gives the network the win. When both touches are
equal, the engine wins because the local `RecordCommissionForOrder` protocol
uses `>=` to break ties in favor of the engine. The timestamps are
merchant-asserted on the same trust basis as reported revenue today.

`site` accepts the site ID or domain. The link must belong to the
authenticated site, otherwise the report is rejected with `404`.

The success response keeps the existing `network` and `conversion` members and
adds a limited `decision` object:

```json
{"decision": {"winner": "network", "reason": "last_touch"}}
```

## Void endpoint

`POST /api/affiliate-network/conversions/void`

Call this when a previously reported order is refunded or voided on the
merchant side. Same bearer-token auth as reporting.

```json
{
  "site": "merchant.example",
  "link_code": "a1b2c3d4e5f60001",
  "external_reference": "ORDER-1001",
  "reason": "customer_refund"
}
```

`reason` is required as a key and nullable; `null` records `refund`. A posted
leg is reversed with its companion reversal leg (creator balances and
merchant postings update immediately), and the response returns
`{ok, duplicate, leg_status, conversion}`. Voiding is idempotent on (network
link, external reference): repeat calls return `duplicate: true` with the
already-reversed leg. Voiding a `superseded` leg is a no-op acknowledgement
(nothing was ever payable). An unknown reference returns `404`; a
`provisional` leg (owned by the shared checkout flow) returns `422`. Link
expiry does not block voiding — refunds legitimately arrive after a link
expires.

## Merchant SDK

Merchant apps use the SDK shipped in `aiarmada/affiliates`
(`CaptureNetworkReferral` middleware + `NetworkPostbackClient`) instead of
hand-rolled HTTP — see
[Network merchant SDK](../../affiliates/docs/15-network-merchant.md).

## Decisions and idempotency

Reporting is idempotent on (network link, external reference). Redeliveries
return `duplicate: true` with the original conversion and decision. A retry
cannot change the winner even when it reports different touch times. Always
send a stable merchant order reference.
This applies to legs created with this decision contract. Older legs have no
stored winner metadata, so their duplicate response cannot include a
historical decision; no winner is inferred from their prior `posted` status.

An engine win records the network leg as `superseded` with the decision winner
and reason in leg metadata. Superseded legs remain in link conversion
counters, matching local checkout accounting, but never enter `CreatorBalances`
and are never fulfilled. Only posted legs pay. The decision object exposes
only the winner and reason. The `conversion` member retains its existing
`NetworkConversionLeg::toArray()` representation.

Reasons match the local attribution protocol: `last_touch` when both touches
are present, and `engine_unattributed` when the engine reports `null` and the
network wins by default.

## Ledger

Each first-seen report posts a `NetworkConversionLeg` (commission resolved
from the offer rates, fee carved out) so balances and dashboards update.
When `aiarmada/affiliates` is installed, engine fulfillment also posts the
merchant ledger row (`origin: marketplace`, linked back via `source_ref`).
Standalone legs remain unfulfilled until the host records a payout run with
a non-empty reference.
The `NetworkConversionRecorded` event fires for custom automation. Expired
links and inactive offers are rejected with `410`.

When the reported currency differs from the link's pricing currency, the
leg preserves the reported revenue but earns zero commission, fee, and
payout; the cached link revenue excludes that mismatched amount.

Merchant-ledger reconciliation compares active conversion statuses only:
`pending`, `qualified`, `approved`, and `paid`. Rejected and reversed rows
are settled and do not count as active postings.

The SDK returns transport and non-success responses as `ok: false`; each
merchant host must persist failed reports and retry them with the same order
reference. The SDK does not provide an outbox.
