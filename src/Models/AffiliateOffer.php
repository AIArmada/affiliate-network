<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferFactory;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
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
 * @property int|null $rate_base_bp
 * @property int|null $rate_fixed_minor
 * @property string $rate_source
 * @property string|null $currency
 * @property int|null $cookie_days
 * @property array<int, array{min_volume_minor: int, rate_bp: int}>|null $volume_tiers
 * @property array<int, array{id: string, name: string, ends_at: string|null}>|null $active_promotions
 * @property bool $is_featured
 * @property OfferVisibility $visibility
 * @property bool $requires_approval
 * @property string|null $landing_url
 * @property string|null $external_program_id
 * @property string|null $subject_type
 * @property string|null $subject_key
 * @property string|null $source_url
 * @property array<string, mixed>|null $restrictions
 * @property array<string, mixed>|null $metadata
 * @property string|null $source_checksum
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
    use ScopesByBelongsToOwner;

    /**
     * Set while OfferImportService writes so the rate lock hook can tell
     * importer writes apart from operator writes. Request-scoped discipline:
     * the importer always resets it in a finally block (Octane-safe).
     */
    private static bool $syncingImport = false;

    public static function setSyncingImport(bool $syncing): void
    {
        self::$syncingImport = $syncing;
    }

    /**
     * Rate-block columns owned by the merchant catalog when rate_source is
     * synced. Any operator write to these flips the lock to manual.
     *
     * @return array<int, string>
     */
    public static function rateBlockColumns(): array
    {
        return [
            'rate_base_bp',
            'rate_fixed_minor',
            'currency',
            'cookie_days',
            'volume_tiers',
            'active_promotions',
        ];
    }

    protected static function ownerViaRelation(): string
    {
        return 'site';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }

    protected $fillable = [
        'site_id',
        'category_id',
        'name',
        'slug',
        'description',
        'terms',
        'status',
        'rate_source',
        'rate_base_bp',
        'rate_fixed_minor',
        'currency',
        'cookie_days',
        'volume_tiers',
        'active_promotions',
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
        'external_program_id',
        'subject_type',
        'subject_key',
        'source_url',
        'source_checksum',
        'last_synced_at',
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

        static::updating(function (self $offer): void {
            if (self::$syncingImport) {
                return;
            }

            if ($offer->isDirty('rate_source')) {
                if ($offer->rate_source === 'synced') {
                    // Explicit unlock: drop the checksum so the next sync
                    // re-applies catalog rates instead of skipping.
                    $offer->source_checksum = null;
                }

                return;
            }

            if ($offer->isDirty(self::rateBlockColumns())) {
                $offer->rate_source = 'manual';
            }
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
            'rate_base_bp' => 'integer',
            'rate_fixed_minor' => 'integer',
            'cookie_days' => 'integer',
            'volume_tiers' => 'array',
            'active_promotions' => 'array',
            'is_featured' => 'boolean',
            'requires_approval' => 'boolean',
            'restrictions' => 'array',
            'metadata' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
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

    public function isFixed(): bool
    {
        return $this->rate_fixed_minor !== null;
    }

    public function formattedRate(): string
    {
        if ($this->isFixed()) {
            return MoneyFormatter::formatMinor(
                (int) $this->rate_fixed_minor,
                $this->currency ?? 'USD'
            );
        }

        if ($this->rate_base_bp === null) {
            return '—';
        }

        return number_format(((int) $this->rate_base_bp) / 100, 2) . '%';
    }
}
