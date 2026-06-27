<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferFactory;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesBySiteOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $terms
 * @property OfferStatus $status
 * @property string $commission_type
 * @property int $commission_rate
 * @property string|null $currency
 * @property int|null $cookie_days
 * @property bool $is_featured
 * @property OfferVisibility $visibility
 * @property bool $requires_approval
 * @property string|null $landing_url
 * @property array<string, mixed>|null $restrictions
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read AffiliateSite $site
 * @property-read AffiliateOfferCategory|null $category
 * @property-read Collection<int, AffiliateOfferCreative> $creatives
 * @property-read Collection<int, AffiliateOfferApplication> $applications
 * @property-read Collection<int, AffiliateOfferLink> $links
 */
class AffiliateOffer extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasContactMethods;
    use HasFactory;
    use HasSocialProfiles;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesBySiteOwner;

    protected $fillable = [
        'site_id',
        'category_id',
        'name',
        'slug',
        'description',
        'terms',
        'status',
        'commission_type',
        'commission_rate',
        'currency',
        'cookie_days',
        'is_featured',
        'visibility',
        'requires_approval',
        'landing_url',
        'restrictions',
        'metadata',
        'starts_at',
        'ends_at',
        'published_at',
        'archived_at',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offers'] ?? $prefix . 'offers';
    }

    /**
     * @return BelongsTo<AffiliateSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AffiliateSite::class, 'site_id');
    }

    /**
     * @return BelongsTo<AffiliateOfferCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AffiliateOfferCategory::class, 'category_id');
    }

    /**
     * @return HasMany<AffiliateOfferCreative, $this>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(AffiliateOfferCreative::class, 'offer_id');
    }

    /**
     * @return HasMany<AffiliateOfferApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(AffiliateOfferApplication::class, 'offer_id');
    }

    /**
     * @return HasMany<AffiliateOfferLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(AffiliateOfferLink::class, 'offer_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $offer): void {
            $offer->creatives()->delete();
            $offer->applications()->delete();
            $offer->links()->delete();
        });
    }

    protected static function newFactory(): AffiliateOfferFactory
    {
        return AffiliateOfferFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'visibility' => OfferVisibility::class,
            'commission_rate' => 'integer',
            'cookie_days' => 'integer',
            'is_featured' => 'boolean',
            'requires_approval' => 'boolean',
            'restrictions' => 'array',
            'metadata' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isActive(): bool
    {
        if ($this->status !== OfferStatus::Published) {
            return false;
        }

        $now = CarbonImmutable::now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
