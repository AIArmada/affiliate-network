<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateSiteFactory;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $domain
 * @property string|null $description
 * @property string $status
 * @property string|null $verification_method
 * @property string|null $verification_token
 * @property CarbonImmutable|null $verified_at
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model|null $owner
 * @property-read Collection<int, AffiliateOffer> $offers
 */
class AffiliateSite extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasContactMethods;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliate-network.owner';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

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
     * @return HasMany<AffiliateOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(AffiliateOffer::class, 'site_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $site): void {
            // Delete per-offer so AffiliateOffer::deleting fires and removes
            // creatives, applications, and links instead of orphaning them.
            $site->offers()->chunkById(200, function ($offers): void {
                foreach ($offers as $offer) {
                    $offer->delete();
                }
            });
        });
    }

    protected static function newFactory(): AffiliateSiteFactory
    {
        return AffiliateSiteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
            'catalog_token_issued_at' => 'immutable_datetime',
            'settings' => 'array',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->verified_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Context-independent verification check for enforcement gates.
     *
     * Verification status is a network fact, not tenant data: approval,
     * publishing, and link flows run under affiliate, merchant, or operator
     * contexts, and none of them may change the answer.
     */
    public static function isVerifiedKey(int | string $key): bool
    {
        $site = static::query()
            ->withoutGlobalScopes()
            ->whereKey($key)
            ->first();

        return $site instanceof self && $site->isVerified();
    }

    /**
     * Issue the site's catalog token, returning the plaintext once.
     *
     * The token authenticates merchant postbacks and catalog pulls. Only
     * the encrypted form is stored; show the return value to the
     * operator immediately — it cannot be recovered later.
     */
    public function issueCatalogToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'catalog_token_encrypted' => encrypt($token),
            'catalog_token_issued_at' => CarbonImmutable::now(),
        ])->save();

        return $token;
    }

    /**
     * Rotate the catalog token. The previous token stops working
     * immediately; only one token is ever valid per site.
     */
    public function rotateCatalogToken(): string
    {
        return $this->issueCatalogToken();
    }

    public function hasCatalogToken(): bool
    {
        return ! empty($this->catalog_token_encrypted);
    }
}
