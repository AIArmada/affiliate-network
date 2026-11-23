<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Models;

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\ApplicationRecord;
use AIArmada\AffiliateNetwork\Platform\ResolvesAffiliateModel;
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
 * @property string|null $invitation_id
 * @property ApplicationStatus $status
 * @property string|null $reason
 * @property string|null $rejection_reason
 * @property string|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $revoked_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class AffiliateOfferApplication extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use LogsCommerceActivity;
    use ResolvesAffiliateModel;
    use ScopesByBelongsToOwner;

    protected static function ownerViaRelation(): string
    {
        return 'offer.site';
    }

    protected static function ownerTableConfigKey(): string
    {
        return 'affiliate-network.owner';
    }

    protected $fillable = [
        'offer_id',
        'affiliate_id',
        'invitation_id',
        'status',
        'reason',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_applications'] ?? $prefix . 'offer_applications';
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

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === ApplicationStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ApplicationStatus::Approved;
    }

    public function toRecord(): ApplicationRecord
    {
        return new ApplicationRecord(
            id: (string) $this->getKey(),
            offerId: (string) $this->offer_id,
            affiliateId: (string) $this->affiliate_id,
            invitationId: $this->invitation_id,
            status: $this->status,
            reason: $this->reason,
            rejectionReason: $this->rejection_reason,
            reviewedBy: $this->reviewed_by,
            reviewedAt: $this->reviewed_at,
            approvedAt: $this->approved_at,
            rejectedAt: $this->rejected_at,
            revokedAt: $this->revoked_at,
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
