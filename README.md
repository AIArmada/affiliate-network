# Affiliate Network

Multi-merchant affiliate network and marketplace extension for AIArmada affiliates.

## Overview

This package extends `aiarmada/affiliates` to support multi-merchant affiliate networks where:

- **Merchants** register sites and create offers (products/services for affiliates to promote)
- **Affiliates** browse offers, apply for approval, and generate deep tracking links
- **Network operators** manage the marketplace, approve merchants, and monitor activity

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
Merchants register their domains/sites where offers are hosted. Each site tracks its own conversions and attributions.

### Offers
Products or services that affiliates can promote. Offers have their own commission structures, creatives, and approval workflows.

### Offer Applications
Affiliates apply to promote specific offers. Merchants can auto-approve or manually review applications.

### Deep Links
Once approved, affiliates generate tracking links that include site, offer, and affiliate identifiers for precise attribution.

## Documentation

See [docs/](docs/) for complete documentation.
