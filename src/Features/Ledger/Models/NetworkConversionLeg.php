<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Models;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use AIArmada\AffiliateNetwork\Database\Factories\NetworkConversionLegFactory;
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
 * @property string|null $link_id
 * @property string $offer_id
 * @property string|null $site_id
 * @property string $affiliate_id
 * @property string|null $link_code
 * @property string $idempotency_key
 * @property string|null $parent_leg_id
 * @property int $revenue_minor
 * @property string|null $revenue_currency
 * @property int $commission_minor
 * @property string $commission_currency
 * @property int $fee_minor
 * @property int $fee_bp
 * @property int $payout_minor
 * @property int|null $tier_rate_bp
 * @property int|null $tier_min_volume_minor
 * @property string $external_reference
 * @property string $event_type
 * @property LegStatus $status
 * @property string|null $coupon_code
 * @property string|null $sub_id
 * @property string|null $sub_id_2
 * @property string|null $sub_id_3
 * @property string|null $statement_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $locks_at
 * @property CarbonImmutable|null $locked_at
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model|null $offer
 * @property-read Model|null $site
 */
class NetworkConversionLeg extends Model implements Auditable
{
    use HasCommerceAudit;

    /** @use HasFactory<NetworkConversionLegFactory> */
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

    protected static function ownerViaAffiliateIdentity(): bool
    {
        return true;
    }

    protected static function newFactory(): NetworkConversionLegFactory
    {
        return NetworkConversionLegFactory::new();
    }

    protected $fillable = [
        'link_id',
        'offer_id',
        'site_id',
        'affiliate_id',
        'link_code',
        'idempotency_key',
        'parent_leg_id',
        'revenue_minor',
        'revenue_currency',
        'commission_minor',
        'commission_currency',
        'fee_minor',
        'fee_bp',
        'payout_minor',
        'tier_rate_bp',
        'tier_min_volume_minor',
        'external_reference',
        'event_type',
        'status',
        'coupon_code',
        'sub_id',
        'sub_id_2',
        'sub_id_3',
        'statement_id',
        'metadata',
        'locks_at',
        'locked_at',
        'fulfilled_at',
        'occurred_at',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['conversion_legs'] ?? $prefix . 'conversion_legs';
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
    public function site(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('site'), 'site_id');
    }

    protected function casts(): array
    {
        return [
            'revenue_minor' => 'integer',
            'commission_minor' => 'integer',
            'fee_minor' => 'integer',
            'fee_bp' => 'integer',
            'payout_minor' => 'integer',
            'tier_rate_bp' => 'integer',
            'tier_min_volume_minor' => 'integer',
            'status' => LegStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
            'locks_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function paysOut(): bool
    {
        return $this->status instanceof LegStatus && $this->status->paysOut();
    }

    public function toSnapshot(): NetworkLegSnapshot
    {
        return new NetworkLegSnapshot(
            id: (string) $this->getKey(),
            linkId: $this->link_id,
            offerId: (string) $this->offer_id,
            siteId: $this->site_id,
            affiliateId: (string) $this->affiliate_id,
            linkCode: $this->link_code,
            revenueMinor: (int) $this->revenue_minor,
            revenueCurrency: $this->revenue_currency,
            commissionMinor: (int) $this->commission_minor,
            commissionCurrency: (string) $this->commission_currency,
            feeMinor: (int) $this->fee_minor,
            feeBp: (int) $this->fee_bp,
            payoutMinor: (int) $this->payout_minor,
            tierRateBp: $this->tier_rate_bp,
            tierMinVolumeMinor: $this->tier_min_volume_minor,
            externalReference: (string) $this->external_reference,
            eventType: (string) $this->event_type,
            status: $this->status,
            locksAt: $this->locks_at,
            lockedAt: $this->locked_at,
            fulfilledAt: $this->fulfilled_at,
            statementId: $this->statement_id,
            couponCode: $this->coupon_code,
            subId: $this->sub_id,
            subId2: $this->sub_id_2,
            subId3: $this->sub_id_3,
            metadata: $this->metadata ?? [],
            occurredAt: $this->occurred_at,
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
