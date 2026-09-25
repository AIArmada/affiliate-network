<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\NetworkConversionLegFactory;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One posted money leg per network conversion.
 *
 * Append-only: legs are never edited except for status finalization
 * (provisional → posted/superseded) and reversal markers. Balances,
 * counters, and reconciliation derive from these rows.
 *
 * @property string $id
 * @property string $link_id
 * @property string $offer_id
 * @property string|null $site_id
 * @property string $affiliate_id
 * @property string $link_code
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
 * @property LegStatus $status
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $occurred_at
 */
class NetworkConversionLeg extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'affiliate';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
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
        'status',
        'metadata',
        'occurred_at',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['conversion_legs'] ?? $prefix . 'conversion_legs';
    }

    protected function casts(): array
    {
        return [
            'status' => LegStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<AffiliateOfferLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(AffiliateOfferLink::class, 'link_id');
    }

    /**
     * @return BelongsTo<AffiliateOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    /**
     * @return BelongsTo<AffiliateSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AffiliateSite::class, 'site_id');
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

    public function paysOut(): bool
    {
        return $this->status instanceof LegStatus
            ? $this->status->paysOut()
            : $this->status === LegStatus::Posted->value;
    }
}
