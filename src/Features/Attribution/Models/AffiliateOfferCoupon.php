<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferCouponFactory;
use AIArmada\AffiliateNetwork\Features\Attribution\Api\OfferCouponRecord;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $offer_id
 * @property string $site_id
 * @property string $affiliate_id
 * @property string $code
 * @property bool $is_active
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model|null $offer
 */
class AffiliateOfferCoupon extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'offer.site';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }

    protected static function newFactory(): AffiliateOfferCouponFactory
    {
        return AffiliateOfferCouponFactory::new();
    }

    protected $fillable = [
        'offer_id',
        'site_id',
        'affiliate_id',
        'code',
        'is_active',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_coupons'] ?? $prefix . 'offer_coupons';
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('offer'), 'offer_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function isActiveAt(CarbonImmutable $at): bool
    {
        if (! (bool) $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        return ! ($this->ends_at !== null && $at->gt($this->ends_at));
    }

    public function toRecord(): OfferCouponRecord
    {
        return new OfferCouponRecord(
            id: (string) $this->getKey(),
            offerId: (string) $this->offer_id,
            siteId: (string) $this->site_id,
            affiliateId: (string) $this->affiliate_id,
            code: (string) $this->code,
            isActive: (bool) $this->is_active,
            startsAt: $this->starts_at,
            endsAt: $this->ends_at,
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
