<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property string $link_id
 * @property string $offer_id
 * @property string|null $affiliate_id
 * @property string|null $site_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $link_owner_type
 * @property string|null $link_owner_id
 * @property int $revenue
 * @property string $currency
 * @property string $reference
 * @property CarbonImmutable $converted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read AffiliateOfferLink $link
 * @property-read AffiliateOffer $offer
 */
class ConversionLedger extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'link_id',
        'offer_id',
        'affiliate_id',
        'site_id',
        'owner_type',
        'owner_id',
        'link_owner_type',
        'link_owner_id',
        'revenue',
        'currency',
        'reference',
        'converted_at',
    ];

    public $timestamps = true;

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['conversion_ledger'] ?? $prefix . 'conversion_ledger';
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

    protected function casts(): array
    {
        return [
            'revenue' => 'integer',
            'converted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
