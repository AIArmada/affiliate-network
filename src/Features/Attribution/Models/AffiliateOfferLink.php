<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Models;

use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferLinkRecord;
use AIArmada\AffiliateNetwork\Platform\ResolvesAffiliateModel;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
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
 * @property-read Model|null $offer
 * @property-read Model|null $affiliate
 * @property-read Model|null $site
 */
class AffiliateOfferLink extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use LogsCommerceActivity;
    use ResolvesAffiliateModel;
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'affiliate';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }

    protected static function ownerFallbackRelation(): ?string
    {
        return 'offer.site';
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
     * @return BelongsTo<Model, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('offer'), 'offer_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(self::affiliateModel(), 'affiliate_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('site'), 'site_id');
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

    public function isExpired(): bool
    {
        return $this->link !== null && $this->link->isExpired();
    }

    public function trackedSlug(): ?string
    {
        return $this->link?->slug;
    }

    public function toRecord(): OfferLinkRecord
    {
        return new OfferLinkRecord(
            id: (string) $this->getKey(),
            linkId: $this->link_id,
            offerId: (string) $this->offer_id,
            affiliateId: (string) $this->affiliate_id,
            siteId: $this->site_id,
            slug: $this->trackedSlug(),
            subId: $this->sub_id,
            subId2: $this->sub_id_2,
            subId3: $this->sub_id_3,
            clicks: (int) $this->clicks,
            conversions: (int) $this->conversions,
            revenue: (int) $this->revenue,
            currency: $this->currency,
            isActive: (bool) $this->is_active,
            isExpired: $this->isExpired(),
            metadata: $this->metadata,
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
