<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferApplicationFactory;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $offer_id
 * @property string $affiliate_id
 * @property string $status
 * @property string|null $reason
 * @property string|null $rejection_reason
 * @property string|null $reviewed_by
 * @property \Carbon\CarbonImmutable|null $reviewed_at
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read AffiliateOffer $offer
 * @property-read Affiliate $affiliate
 */
class AffiliateOfferApplication extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByAffiliateOwner;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'offer_id',
        'affiliate_id',
        'status',
        'reason',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_applications'] ?? $prefix . 'offer_applications';
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

    protected static function newFactory(): AffiliateOfferApplicationFactory
    {
        return AffiliateOfferApplicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'immutable_datetime',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
