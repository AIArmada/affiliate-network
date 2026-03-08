---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the affiliate-network package.

## Site Verification Issues

### DNS Verification Fails

**Symptoms:** `verify($site, 'dns')` returns false despite TXT record being set.

**Solutions:**

1. Wait for DNS propagation (up to 48 hours for some providers).

2. Verify TXT record exists:
```bash
dig TXT yourdomain.com +short
```

3. Check the record value matches exactly:
```php
$site->verification_token; // Must match TXT record value
```

4. Some DNS providers require the record name to be `@` or the bare domain.

### Meta Tag Verification Fails

**Symptoms:** Meta tag verification returns false.

**Solutions:**

1. Ensure the site is accessible over HTTPS:
```bash
curl -I https://yourdomain.com
```

2. Check the meta tag is in the `<head>` section:
```html
<head>
    <meta name="affiliate-network-verify" content="affiliatenetwork-verify-xxx">
</head>
```

3. Verify no caching is hiding the meta tag.

### File Verification Fails

**Symptoms:** File verification returns false.

**Solutions:**

1. Ensure file exists at correct path:
```bash
curl https://yourdomain.com/.well-known/affiliate-network-verify.txt
```

2. File content must be exactly the token (no extra whitespace).

3. Ensure `.well-known` directory is accessible (not blocked by web server).

## Tracking Link Issues

### Links Return 404

**Symptoms:** `/affiliate-network/go/{code}` returns 404.

**Solutions:**

1. Verify routes are registered:
```bash
php artisan route:list | grep affiliate-network
```

2. Ensure service provider is loaded:
```php
// Check config
config('affiliate-network.database.table_prefix');
```

3. Clear route cache:
```bash
php artisan route:clear
```

### Links Return 410 (Expired)

**Symptoms:** Links return "Link has expired" error.

**Solutions:**

1. Check link expiration:
```php
$link = AffiliateOfferLink::find($id);
$link->expires_at;  // Check if past
$link->isExpired(); // Should return true
```

2. Extend default TTL:
```env
AFFILIATE_NETWORK_LINK_TTL=129600  # 90 days
```

3. Create links without expiration:
```php
$linkService->createLink($offer, $affiliate, [
    'expires_at' => null,
]);
```

### Links Return 410 (Offer Inactive)

**Symptoms:** "Offer is no longer active" error.

**Solutions:**

1. Check offer status:
```php
$offer->status;     // Should be 'active'
$offer->isActive(); // Should return true
```

2. Check offer date range:
```php
$offer->starts_at;  // Should be past or null
$offer->ends_at;    // Should be future or null
```

## Application Issues

### Can't Reapply After Rejection

**Symptoms:** Reapplication throws error.

**Solution:** Cooldown period applies. Wait or adjust config:

```env
AFFILIATE_NETWORK_APPLICATIONS_COOLDOWN_DAYS=0  # Disable cooldown
```

Check when reapplication is allowed:
```php
$application->updated_at->addDays(7)->isPast(); // Must be true
```

### Auto-Approve Not Working

**Symptoms:** Applications stay pending despite config.

**Solutions:**

1. Check offer requires approval:
```php
$offer->requires_approval; // If true, still requires approval
```

2. Check global auto-approve:
```env
AFFILIATE_NETWORK_APPLICATIONS_AUTO_APPROVE=true
```

Both conditions must allow auto-approval.

## Multi-Tenancy Issues

### Cross-Tenant Data Visible

**Symptoms:** Users see data from other tenants.

**Solutions:**

1. Verify owner scoping is enabled:
```env
AFFILIATE_NETWORK_OWNER_ENABLED=true
```

2. Check OwnerResolverInterface is bound:
```php
app(OwnerResolverInterface::class)->resolve();
```

3. Ensure models use correct traits:
   - `AffiliateSite`, `AffiliateOfferCategory`: `HasOwner`
   - `AffiliateOffer`: `ScopesBySiteOwner`
   - `AffiliateOfferApplication`, `AffiliateOfferLink`: `ScopesByAffiliateOwner`

### RuntimeException on Create

**Symptoms:** "Cannot create record for a site owned by a different owner."

**Cause:** Attempting to create record linking to entity from different tenant.

**Solution:** Ensure foreign keys point to same-tenant entities:

```php
// Verify site belongs to current owner before creating offer
$site = AffiliateSite::findOrFail($siteId);
// Global scope ensures this is current owner's site
```

## Database Issues

### Table Not Found

**Symptoms:** "Table not found" errors.

**Solutions:**

1. Run migrations:
```bash
php artisan migrate
```

2. Check table prefix matches:
```php
config('affiliate-network.database.table_prefix');
```

3. Verify migration files exist:
```bash
ls vendor/aiarmada/affiliate-network/database/migrations/
```

### JSON Column Errors (PostgreSQL)

**Symptoms:** JSON operations fail on PostgreSQL.

**Solution:** Use `jsonb` column type:

```env
AFFILIATE_NETWORK_JSON_COLUMN_TYPE=jsonb
```

## Debugging Tips

### Enable Query Logging

```php
DB::enableQueryLog();

// Perform operations
$sites = AffiliateSite::all();

dd(DB::getQueryLog());
```

### Check Global Scopes

```php
$model = new AffiliateSite();
$scopes = $model->getGlobalScopes();
dd($scopes);
```

### Verify Service Registration

```php
app(SiteVerificationService::class);
app(OfferManagementService::class);
app(OfferLinkService::class);
```

## Getting Help

If issues persist:

1. Check the [configuration docs](03-configuration.md)
2. Verify dependencies are installed
3. Review error logs: `storage/logs/laravel.log`
4. Open an issue with:
   - PHP/Laravel versions
   - Package version
   - Full error message
   - Steps to reproduce
