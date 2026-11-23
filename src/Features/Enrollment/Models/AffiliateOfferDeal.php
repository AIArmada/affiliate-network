<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferDealFactory;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\DealRateOverride;
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
 * @property string $affiliate_id
 * @property int|null $rate_base_bp
 * @property int|null $rate_fixed_minor
 * @property int|null $network_fee_bp
 * @property string|null $currency
 * @property array<int, array<string, mixed>>|null $volume_tiers
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_active
 * @property string|null $notes
 */
class AffiliateOfferDeal extends Model implements Auditable
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

    protected static function newFactory(): AffiliateOfferDealFactory
    {
        return AffiliateOfferDealFactory::new();
    }

    protected $fillable = [
        'offer_id',
        'affiliate_id',
        'rate_base_bp',
        'rate_fixed_minor',
        'network_fee_bp',
        'currency',
        'volume_tiers',
        'starts_at',
        'ends_at',
        'is_active',
        'notes',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_deals'] ?? $prefix . 'offer_deals';
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
            'rate_base_bp' => 'integer',
            'rate_fixed_minor' => 'integer',
            'network_fee_bp' => 'integer',
            'volume_tiers' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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

    public function toOverride(): DealRateOverride
    {
        return new DealRateOverride(
            rateBaseBp: $this->rate_base_bp,
            rateFixedMinor: $this->rate_fixed_minor,
            networkFeeBp: $this->network_fee_bp,
            currency: $this->currency,
            volumeTiers: $this->volume_tiers,
            startsAt: $this->starts_at,
            endsAt: $this->ends_at,
            isActive: (bool) $this->is_active,
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
