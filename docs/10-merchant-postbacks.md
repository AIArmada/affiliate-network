---
title: Merchant Postbacks
---

# Merchant Postbacks

Remote merchants (separate apps/databases) report paid orders to the network
over HTTP, and the network delivers signed events to creators. Both directions
are covered here.

## Merge fields: link vs coupon attribution

Every merchant endpoint accepts **either** a `link_code` **or** a
`coupon_code` (`required_without` each other). Linkless conversion is a
first-class path: a creator-assigned promo coupon drives attribution with no
click.

## Enable the merchant endpoints

```php
// config/affiliate-network.php
'postbacks' => [
    'enabled' => env('AFFILIATE_NETWORK_POSTBACKS_ENABLED', false),
    'prefix' => env('AFFILIATE_NETWORK_POSTBACKS_PREFIX', 'api/affiliate-network'),
    'middleware' => ['api', 'throttle:60,1'],
],
```

Routes (registered only when enabled):

| Method | Path | Route name |
|---|---|---|
| `POST` | `{prefix}/conversions` | `affiliate-network.conversions.report` |
| `POST` | `{prefix}/conversions/void` | `affiliate-network.conversions.void` |
| `POST` | `{prefix}/conversions/adjust` | `affiliate-network.conversions.adjust` |

## Authentication

Bearer token: the site's **catalog token** — the same secret the network uses to
pull the merchant catalog, stored encrypted on the site.

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\IssueCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;

$plain = app(IssueCatalogToken::class)->execute($site);   // shown once
$new = app(RotateCatalogToken::class)->execute($site);    // previous dies immediately
```

Each request is authenticated with `$sites->matchesCatalogToken($site->id, $request->bearerToken())`
(constant-time, fail-closed). Unknown/incorrect token → `401`; unverified site →
`403`.

## `POST /conversions`

```json
{
  "site": "merchant.example",
  "link_code": "a1b2c3d4e5f60001",
  "external_reference": "ORDER-1001",
  "revenue_minor": 89900,
  "currency": "MYR",
  "event_type": "purchase",
  "sub_id": "blog-post-1",
  "sub_id_2": "sidebar",
  "sub_id_3": "banner-728x90",
  "country": "MY",
  "channel": "web",
  "device": "mobile",
  "engine_touch_at": "2026-10-17T08:55:00+08:00",
  "network_clicked_at": "2026-10-17T08:57:00+08:00"
}
```

- `site` accepts the site ID or domain.
- `link_code` / `coupon_code`: one is required.
- `event_type` defaults to `purchase`; it participates in the idempotency key,
  so `signup` and `purchase` for one order are distinct legs (multi-event CPA).
- `sub_id`/`sub_id_2`/`sub_id_3` ride onto the leg and outbound postbacks.
- `engine_touch_at` is required and nullable: send `null` when the merchant
  engine has no touch.
- `network_clicked_at` is required and must be the server-observed instant
  captured with the network link code.
- Both timestamps must parse; touches more than five minutes in the future are
  rejected (`422`).

The winner is decided on the reported instants: the newer touch wins, an absent
engine touch gives the network the win, and an exact tie goes to the engine
(`>=`). An engine win records the leg as `superseded`; a network win records it
`posted`.

The link must belong to the authenticated site (`404` otherwise), the offer must
have been active at the network click time (`410` otherwise), and the conversion
target must resolve through the link or coupon (`404`).

### Success response

```json
{
  "ok": true,
  "duplicate": false,
  "network": { "clicks": 1250, "conversions": 45, "revenue": 267955, "currency": "MYR", "conversion_rate": 3.6, "revenue_per_click": 214.36 },
  "conversion": { "id": "…", "external_reference": "ORDER-1001", "status": "posted" },
  "decision": {
    "attribution_winner": "network",
    "attribution_decision_reason": "engine_unattributed"
  }
}
```

`network` is `null` for coupon-only conversions. `conversion` is the
`NetworkLegSnapshot` array.

### Decisions and idempotency

Reporting is idempotent on `(link|coupon, external_reference, event_type)`.
Redeliveries return `duplicate: true` with the original conversion and decision.
A retry cannot change the winner even if it reports different touch times.
Reasons: `last_touch` (both touches present, engine won),
`network_last_touch` (network won on a later touch), `engine_unattributed`
(engine reported `null`, network wins by default).

Superseded legs remain in link conversion counters (matching local checkout
accounting) but never enter `CreatorBalancesQuery` and are never fulfilled.
Only posted legs pay.

## `POST /conversions/void`

Call when a reported order is refunded or voided on the merchant side.

```json
{
  "site": "merchant.example",
  "link_code": "a1b2c3d4e5f60001",
  "external_reference": "ORDER-1001",
  "event_type": "purchase",
  "reason": "customer_refund"
}
```

- `reason` is required as a key and may be `null`; `null`/empty records `refund`.
- A posted leg becomes `reversed` with its negated companion leg; creator
  balances and merchant postings update immediately.
- Idempotent: repeat calls return `duplicate: true` with the already-reversed
  leg. Voiding a `superseded` leg is a no-op acknowledgement.
- A `provisional` leg (owned by the shared checkout flow) returns `422`.
- Unknown reference returns `404`. Link expiry does not block voiding.

Response: `{"ok": true, "duplicate": false|true, "conversion": {...}}`.

## `POST /conversions/adjust`

Correct a previously reported conversion (partial refund, price fix).

```json
{
  "site": "merchant.example",
  "link_code": "a1b2c3d4e5f60001",
  "external_reference": "ORDER-1001",
  "event_type": "purchase",
  "adjustment_reference": "ADJ-1",
  "delta_revenue_minor": -1000,
  "new_revenue_minor": null,
  "reason": "partial refund"
}
```

- Provide **either** `delta_revenue_minor` **or** `new_revenue_minor`
  (`required_without` each other).
- Idempotent on the adjustment reference (`adj:{legId}:{reference}`).
- Only `pending` or `posted` original legs may be adjusted; companion legs and
  terminal legs return `422`.
- The adjustment posts a companion leg with proportional commission/fee/payout,
  updates link counters, and adjusts site prepaid balance when one is set.
- Unknown conversion returns `404`.

Response: `{"ok": true, "conversion": {...}}`.

## `event_type` and multi-event CPA

`event_type` (default `purchase`) is part of the idempotency key and selects the
offer's `event_rates[event_type]` when present. This lets one order produce a
`signup` leg and a later `purchase` leg, each priced independently, without
colliding.

## Outbound creator S2S postbacks

Creators register endpoints; the network delivers signed callbacks for
`conversion.recorded`, `conversion.locked`, `conversion.reversed`, and
`conversion.adjusted`.

```php
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\ConfigureCreatorPostback;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;

$endpoint = app(ConfigureCreatorPostback::class)->execute($affiliateId, [
    'offer_id' => null,                 // null = all offers
    'url_template' => 'https://creator.app/hook?leg={leg_id}&ref={external_reference}&sub1={sub1}&event={event_type}',
    'http_method' => 'POST',            // GET | POST
    'events' => [
        PostbackEventType::ConversionRecorded->value,
        PostbackEventType::ConversionAdjusted->value,
    ],
    'signing_secret' => 'shared-secret',
    'is_active' => true,
]);
```

### Macros

`url_template` supports these tokens, each URL-encoded before substitution:

`{leg_id}`, `{offer_id}`, `{link_code}`, `{external_reference}`,
`{event_type}`, `{status}`, `{revenue_minor}`, `{commission_minor}`,
`{payout_minor}`, `{currency}`, `{sub1}`, `{sub2}`, `{sub3}`, `{coupon_code}`.

The template must be an HTTP(S) URL after substituting tokens with `x`.

### Signing

When `signing_secret` is set, each request carries:

```
X-Affiliate-Network-Signature: hash_hmac('sha256', $resolvedUrl, $secret)
```

Verify by recomputing the HMAC over the exact URL requested.

### Transport safety

Delivery uses `Drivers\HttpCreatorPostbackDriver`, which validates the URL
through `commerce-support`'s `PublicHttpUrlGuard` (HTTP/HTTPS only, public
DNS/IPs, no credentials/fragments), pins the resolved address, disables
redirects, applies the configured timeouts/retries, and caps the response body
(`http.max_response_bytes`). Delivery attempts and errors are stored on
`CreatorPostbackDelivery`; retries are queued (`creator_postbacks.queue`).

Each delivery attempt claims a short lease (`attempt_token`, `lease_expires_at`)
before the transport call and finalizes only while its token and lease still
match. Overlapping retries do not call the transport concurrently, and a stale
worker cannot overwrite a newer attempt. The transport call runs outside the
database transaction. Attempts are counted once per claimed send and stored
errors are sanitized; delivery is at-least-once from the network's perspective,
not an external exactly-once guarantee.

## Merchant SDK (`aiarmada/affiliates`)

Merchant apps use `CaptureNetworkReferral` (middleware) plus
`NetworkPostbackGateway` instead of hand-rolled HTTP:

```php
use AIArmada\Affiliates\Contracts\NetworkPostbackGateway;

$client = app(NetworkPostbackGateway::class);

$result = $client->report(
    linkCode: $linkCode,
    externalReference: $order->number,
    revenueMinor: $order->total_minor,
    networkClickedAt: $networkClickedAt,
    engineTouchAt: $engineTouchAt,
    currency: $order->currency,
    couponCode: null,
    eventType: 'purchase',
    subIds: ['sub1' => 'blog', 'sub2' => 'sidebar'],
);

$result->ok;        // bool
$result->decision;  // parsed decision array or null

$client->adjustConversion(
    linkCode: $linkCode,
    externalReference: $order->number,
    adjustmentReference: 'ADJ-1',
    deltaRevenueMinor: -1000,
    reason: 'partial refund',
);

$client->voidConversion(
    linkCode: $linkCode,
    externalReference: $order->number,
    reason: 'customer_refund',
);
```

`report()` returns `['ok','status','body','decision','error']`;
`adjustConversion()` and `voidConversion()` return
`['ok','status','body','leg_status','error']`. The client short-circuits with
`error: 'no network attribution'` when neither a link code nor coupon is
present, and `error: 'network merchant not configured'` when the merchant config
is missing.

The SDK has no outbox: each merchant host must persist failed reports and retry
them with the same order reference.
