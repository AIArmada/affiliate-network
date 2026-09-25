<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferLinkFactory;
use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Links\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Attribution record for one affiliate/offer pair.
 *
 * Redirect mechanics (slug, destination, signed URLs, click events) live on
 * the backing tracked link; this row owns attribution facts and counters.
 *
 * @property string $id
 * @property string|null $link_id
 * @property string $offer_id
 * @property string $affiliate_id
 * @property string|null $site_id
 * @property string|null $sub_id
 * @property string|null $sub_id_2
 * @property string|null $sub_id_3
 * @property int $clicks
 * @property int $conversions
 * @property int $revenue
 * @property string|null $currency
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Link|null $link
 * @property-read AffiliateOffer $offer
 * @property-read Model $affiliate
 * @property-read AffiliateSite|null $site
 */
class AffiliateOfferLink extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'affiliate';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }

    protected $fillable = [
        'link_id',
        'offer_id',
        'affiliate_id',
        'site_id',
        'sub_id',
        'sub_id_2',
        'sub_id_3',
        'currency',
        'is_active',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_links'] ?? $prefix . 'offer_links';
    }

    /**
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }

    /**
     * @return BelongsTo<AffiliateOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(self::affiliateModel(), 'affiliate_id');
    }

    /**
     * @return class-string<Model>
     */
    public static function affiliateModel(): string
    {
        $model = config('affiliate-network.models.affiliate');

        if (! is_string($model) || ! class_exists($model)) {
            throw AffiliatesNotInstalled::forFeature('affiliate relations');
        }

        return $model;
    }

    /**
     * @return BelongsTo<AffiliateSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AffiliateSite::class, 'site_id');
    }

    protected static function newFactory(): AffiliateOfferLinkFactory
    {
        return AffiliateOfferLinkFactory::new();
    }

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
            'conversions' => 'integer',
            'revenue' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return $this->getAuditInclude();
    }

    protected function getActivityLogName(): string
    {
        return 'affiliate-network';
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function recordConversion(int $revenueMinor): void
    {
        $this->incrementEach([
            'conversions' => 1,
            'revenue' => $revenueMinor,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->link !== null && $this->link->isExpired();
    }

    public function trackedSlug(): ?string
    {
        return $this->link?->slug;
    }
}
