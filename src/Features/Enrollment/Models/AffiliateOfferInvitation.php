<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Enrollment\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferInvitationFactory;
use AIArmada\AffiliateNetwork\Features\Enrollment\Api\InvitationRecord;
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
 * @property string|null $affiliate_id
 * @property string $token
 * @property string|null $invited_by
 * @property bool $auto_approve
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $revoked_at
 * @property array<string, mixed>|null $metadata
 */
class AffiliateOfferInvitation extends Model implements Auditable
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

    protected static function newFactory(): AffiliateOfferInvitationFactory
    {
        return AffiliateOfferInvitationFactory::new();
    }

    protected $fillable = [
        'offer_id',
        'affiliate_id',
        'token',
        'invited_by',
        'auto_approve',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_invitations'] ?? $prefix . 'offer_invitations';
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
            'auto_approve' => 'boolean',
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null || $this->accepted_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function toRecord(): InvitationRecord
    {
        return new InvitationRecord(
            id: (string) $this->getKey(),
            offerId: (string) $this->offer_id,
            affiliateId: $this->affiliate_id,
            token: (string) $this->token,
            invitedBy: $this->invited_by,
            autoApprove: (bool) $this->auto_approve,
            expiresAt: $this->expires_at,
            acceptedAt: $this->accepted_at,
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
