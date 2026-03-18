<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferLinkFactory;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $offer_id
 * @property string $affiliate_id
 * @property string|null $site_id
 * @property string $code
 * @property string $target_url
 * @property string|null $custom_parameters
 * @property string|null $sub_id
 * @property string|null $sub_id_2
 * @property string|null $sub_id_3
 * @property int $clicks
 * @property int $conversions
 * @property int $revenue
 * @property bool $is_active
 * @property CarbonImmutable|null $expires_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read AffiliateOffer $offer
 * @property-read Affiliate $affiliate
 * @property-read AffiliateSite|null $site
 */
class AffiliateOfferLink extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'offer_id',
        'affiliate_id',
        'site_id',
        'code',
        'target_url',
        'custom_parameters',
        'sub_id',
        'sub_id_2',
        'sub_id_3',
        'clicks',
        'conversions',
        'revenue',
        'is_active',
        'expires_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_links'] ?? $prefix . 'offer_links';
    }

    /**
     * @return BelongsTo<AffiliateOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AffiliateSite::class, 'site_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            if (empty($link->code)) {
                $link->code = static::generateCode();
            }
        });
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
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public static function generateCode(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    public function recordConversion(int $revenueMinor): void
    {
        $this->increment('conversions');
        $this->increment('revenue', $revenueMinor);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
