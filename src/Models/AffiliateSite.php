<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateSiteFactory;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateOffer> $offers
 */
class AffiliateSite extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

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
            $site->offers()->delete();
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
}
