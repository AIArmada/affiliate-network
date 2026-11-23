<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Sites\Models;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Data\SiteSyncStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\DeleteSiteDependents;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SiteRecord;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;
use Throwable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $domain
 * @property SiteStatus $status
 * @property SiteSyncStatus $sync_status
 * @property string|null $verification_method
 * @property string|null $verification_token
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_synced_at
 * @property CarbonImmutable|null $catalog_token_issued_at
 * @property string|null $catalog_url
 * @property string|null $catalog_token_encrypted
 * @property int $default_locking_days
 * @property int|null $prepaid_balance_minor
 * @property string|null $budget_currency
 * @property int|null $monthly_spend_cap_minor
 * @property bool $require_settled_for_payout
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $metadata
 */
class AffiliateSite extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliate-network.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'domain',
        'description',
        'status',
        'verification_method',
        'verification_token',
        'verified_at',
        'settings',
        'metadata',
        'catalog_url',
        'catalog_token_encrypted',
        'catalog_token_issued_at',
        'sync_status',
        'last_synced_at',
        'default_locking_days',
        'prepaid_balance_minor',
        'budget_currency',
        'monthly_spend_cap_minor',
        'require_settled_for_payout',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['sites'] ?? $prefix . 'sites';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer'), 'site_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $site): void {
            app(DeleteSiteDependents::class)->execute((string) $site->getKey());
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'sync_status' => SiteSyncStatus::class,
            'default_locking_days' => 'integer',
            'prepaid_balance_minor' => 'integer',
            'monthly_spend_cap_minor' => 'integer',
            'require_settled_for_payout' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'catalog_token_issued_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === SiteStatus::Verified && $this->verified_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === SiteStatus::Pending;
    }

    public function isSuspended(): bool
    {
        return $this->status === SiteStatus::Suspended;
    }

    public function isRejected(): bool
    {
        return $this->status === SiteStatus::Rejected;
    }

    public function hasCatalogToken(): bool
    {
        return $this->catalog_token_encrypted !== null && $this->catalog_token_encrypted !== '';
    }

    public function matchesCatalogToken(?string $token): bool
    {
        if ($token === null || $token === '' || $this->catalog_token_encrypted === null) {
            return false;
        }

        try {
            $expected = decrypt($this->catalog_token_encrypted);
        } catch (Throwable) {
            return false;
        }

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    public function toRecord(): SiteRecord
    {
        return new SiteRecord(
            id: (string) $this->getKey(),
            name: (string) $this->name,
            domain: (string) $this->domain,
            status: $this->status,
            verifiedAt: $this->verified_at,
            verificationMethod: $this->verification_method,
            verificationToken: $this->verification_token,
            catalogUrl: $this->catalog_url,
            catalogTokenEncrypted: $this->catalog_token_encrypted,
            syncStatus: $this->sync_status,
            defaultLockingDays: (int) $this->default_locking_days,
            prepaidBalanceMinor: $this->prepaid_balance_minor,
            budgetCurrency: $this->budget_currency,
            monthlySpendCapMinor: $this->monthly_spend_cap_minor,
            requireSettledForPayout: (bool) $this->require_settled_for_payout,
            ownerType: $this->owner_type,
            ownerId: $this->owner_id,
        );
    }

    /**
     * @return class-string<Model>
     */
    private static function relatedModel(string $key): string
    {
        $model = config('affiliate-network.models.' . $key);

        return is_string($model) && $model !== '' ? $model : Model::class;
    }
}
