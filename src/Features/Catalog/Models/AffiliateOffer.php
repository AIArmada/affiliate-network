<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Models;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\DeleteOfferDependents;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
 * @property OfferVisibility $visibility
 * @property OfferSource $source
 * @property int|null $rate_base_bp
 * @property int|null $rate_fixed_minor
 * @property string|null $currency
 * @property int|null $cookie_days
 * @property array<int, array<string, mixed>>|null $volume_tiers
 * @property array<int, array<string, mixed>>|null $active_promotions
 * @property bool $is_featured
 * @property bool $requires_approval
 * @property string|null $landing_url
 * @property string|null $fallback_url
 * @property string|null $fallback_offer_id
 * @property bool $allow_deep_linking
 * @property array<string, mixed>|null $event_rates
 * @property int|null $network_fee_bp
 * @property int $locking_days
 * @property int|null $daily_conversion_cap
 * @property int|null $monthly_conversion_cap
 * @property int|null $total_conversion_cap
 * @property int|null $per_affiliate_conversion_cap
 * @property int|null $monthly_payout_cap_minor
 * @property int|null $total_payout_cap_minor
 * @property string|null $external_program_id
 * @property string|null $subject_type
 * @property string|null $subject_key
 * @property string|null $source_url
 * @property string|null $source_checksum
 * @property string|null $creatives_checksum
 * @property array<string, mixed>|null $restrictions
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, AffiliateOfferCreative> $creatives
 */
class AffiliateOffer extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByBelongsToOwner;

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
        'source',
        'network_fee_bp',
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
        'fallback_url',
        'fallback_offer_id',
        'allow_deep_linking',
        'event_rates',
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
        'locking_days',
        'daily_conversion_cap',
        'monthly_conversion_cap',
        'total_conversion_cap',
        'per_affiliate_conversion_cap',
        'monthly_payout_cap_minor',
        'total_payout_cap_minor',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offers'] ?? $prefix . 'offers';
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('site'), 'site_id');
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
     * @return HasMany<Model, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer_application'), 'offer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer_link'), 'offer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function legs(): HasMany
    {
        return $this->hasMany(self::relatedModel('conversion_leg'), 'offer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer_invitation'), 'offer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer_deal'), 'offer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(self::relatedModel('offer_coupon'), 'offer_id');
    }

    /**
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

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public static function normalizeVolumeTiers(?array $tiers, ?string $offerCurrency): ?array
    {
        if ($tiers === null) {
            return null;
        }

        $fallback = mb_strtoupper(mb_trim((string) ($offerCurrency ?? '')));
        $fallback = $fallback !== '' ? $fallback : mb_strtoupper((string) config('affiliate-network.currency.default', 'MYR'));

        $normalized = [];

        foreach (array_values($tiers) as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $code = mb_strtoupper(mb_trim((string) ($tier['currency'] ?? '')));
            $tier['currency'] = $code !== '' ? $code : $fallback;
            $normalized[] = $tier;
        }

        return $normalized;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereSiteVerified(Builder $query): Builder
    {
        $sitesTable = config(
            'affiliate-network.database.tables.sites',
            config('affiliate-network.database.table_prefix', 'affiliate_network_') . 'sites',
        );

        return $query->whereIn(
            $query->getModel()->qualifyColumn('site_id'),
            DB::table((string) $sitesTable)
                ->select('id')
                ->where('status', SiteStatus::Verified->value)
                ->whereNotNull('verified_at'),
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (self $offer): void {
            app(DeleteOfferDependents::class)->execute((string) $offer->getKey());
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'visibility' => OfferVisibility::class,
            'source' => OfferSource::class,
            'rate_base_bp' => 'integer',
            'rate_fixed_minor' => 'integer',
            'network_fee_bp' => 'integer',
            'cookie_days' => 'integer',
            'locking_days' => 'integer',
            'daily_conversion_cap' => 'integer',
            'monthly_conversion_cap' => 'integer',
            'total_conversion_cap' => 'integer',
            'per_affiliate_conversion_cap' => 'integer',
            'monthly_payout_cap_minor' => 'integer',
            'total_payout_cap_minor' => 'integer',
            'allow_deep_linking' => 'boolean',
            'volume_tiers' => 'array',
            'active_promotions' => 'array',
            'event_rates' => 'array',
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

    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->wasActiveAt(CarbonImmutable::now());
    }

    public function wasActiveAt(CarbonImmutable $at): bool
    {
        if ($this->status === OfferStatus::Draft) {
            return false;
        }

        if ($this->published_at === null) {
            return false;
        }

        if ($at->lt($this->published_at)) {
            return false;
        }

        if ($this->archived_at !== null && $at->gt($this->archived_at)) {
            return false;
        }

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        return ! ($this->ends_at !== null && $at->gt($this->ends_at));
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
                $this->currency ?? 'MYR',
            );
        }

        if ($this->rate_base_bp === null) {
            return '—';
        }

        return number_format(((int) $this->rate_base_bp) / 100, 2) . '%';
    }

    public function toRecord(): OfferRecord
    {
        return new OfferRecord(
            id: (string) $this->getKey(),
            siteId: (string) $this->site_id,
            categoryId: $this->category_id,
            name: (string) $this->name,
            slug: (string) $this->slug,
            description: $this->description,
            terms: $this->terms,
            status: $this->status,
            visibility: $this->visibility,
            source: $this->source,
            rateBaseBp: $this->rate_base_bp,
            rateFixedMinor: $this->rate_fixed_minor,
            currency: $this->currency,
            cookieDays: $this->cookie_days,
            volumeTiers: $this->volume_tiers,
            activePromotions: $this->active_promotions,
            isFeatured: (bool) $this->is_featured,
            requiresApproval: (bool) $this->requires_approval,
            landingUrl: $this->landing_url,
            fallbackUrl: $this->fallback_url,
            fallbackOfferId: $this->fallback_offer_id,
            allowDeepLinking: (bool) $this->allow_deep_linking,
            eventRates: $this->event_rates,
            restrictions: $this->restrictions,
            metadata: $this->metadata,
            networkFeeBp: $this->network_fee_bp,
            lockingDays: (int) $this->locking_days,
            dailyConversionCap: $this->daily_conversion_cap,
            monthlyConversionCap: $this->monthly_conversion_cap,
            totalConversionCap: $this->total_conversion_cap,
            perAffiliateConversionCap: $this->per_affiliate_conversion_cap,
            monthlyPayoutCapMinor: $this->monthly_payout_cap_minor,
            totalPayoutCapMinor: $this->total_payout_cap_minor,
            externalProgramId: $this->external_program_id,
            subjectType: $this->subject_type,
            subjectKey: $this->subject_key,
            sourceUrl: $this->source_url,
            startsAt: $this->starts_at,
            endsAt: $this->ends_at,
            publishedAt: $this->published_at,
            archivedAt: $this->archived_at,
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
