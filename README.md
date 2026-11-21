# Affiliate Network

Standalone multi-merchant affiliate network and marketplace for Laravel.

## Overview

This package runs on its own so merchants can publish offers and creators can
discover, apply, and promote them. Install `aiarmada/affiliates` alongside it to
light up merchant identity, the conversion ledger, core programs, and catalog
mirroring through the network seam.

- **Merchants** register sites and create offers (products/services for creators to promote)
- **Creators** browse offers, apply for approval, and generate deep tracking links
- **Network operators** verify merchants, approve applications, and reconcile network books

## Installation

```bash
composer require aiarmada/affiliate-network
```

Publish and run migrations:

```bash
php artisan migrate
```

## Key Concepts

### Sites
Merchants register their domains/sites where offers are hosted. Each site owns its catalog credential and verification lifecycle.

### Offers
Products or services that creators can promote. Offers have their own commission structures, creatives, and approval workflows.

### Offer Applications
Creators apply to promote specific offers. Merchants can auto-approve or manually review applications.

### Deep Links
Once approved, creators generate tracking links that include site, offer, and affiliate identifiers for precise attribution.

### Money Legs
Every conversion with an order reference posts an append-only money leg: revenue, commission, network fee, and payout. Balances derive from posted legs at read time; reversals post negated companions. Exactly one payer fulfills each posted leg.

## Documentation

See [docs/](docs/) for complete documentation.
