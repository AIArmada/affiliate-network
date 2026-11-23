<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Ledger\Models;

use AIArmada\AffiliateNetwork\Data\MerchantStatementSummary;
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Database\Factories\MerchantStatementFactory;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $site_id
 * @property string $statement_number
 * @property SettlementStatus $status
 * @property string $currency
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property int $leg_count
 * @property int $gross_revenue_minor
 * @property int $commission_minor
 * @property int $fee_minor
 * @property int $payout_minor
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $payment_reference
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class MerchantStatement extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliate-network.owner';

    protected static function newFactory(): MerchantStatementFactory
    {
        return MerchantStatementFactory::new();
    }

    protected $fillable = [
        'owner_type',
        'owner_id',
        'site_id',
        'statement_number',
        'status',
        'currency',
        'period_start',
        'period_end',
        'leg_count',
        'gross_revenue_minor',
        'commission_minor',
        'fee_minor',
        'payout_minor',
        'issued_at',
        'due_at',
        'paid_at',
        'payment_reference',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['merchant_statements'] ?? $prefix . 'merchant_statements';
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(self::relatedModel('site'), 'site_id');
    }

    /**
     * @return HasMany<NetworkConversionLeg, $this>
     */
    public function legs(): HasMany
    {
        return $this->hasMany(NetworkConversionLeg::class, 'statement_id');
    }

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'leg_count' => 'integer',
            'gross_revenue_minor' => 'integer',
            'commission_minor' => 'integer',
            'fee_minor' => 'integer',
            'payout_minor' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function toSummary(): MerchantStatementSummary
    {
        return new MerchantStatementSummary(
            id: (string) $this->getKey(),
            siteId: (string) $this->site_id,
            statementNumber: (string) $this->statement_number,
            status: $this->status,
            currency: (string) $this->currency,
            legCount: (int) $this->leg_count,
            grossRevenueMinor: (int) $this->gross_revenue_minor,
            commissionMinor: (int) $this->commission_minor,
            feeMinor: (int) $this->fee_minor,
            payoutMinor: (int) $this->payout_minor,
            issuedAt: $this->issued_at,
            dueAt: $this->due_at,
            paidAt: $this->paid_at,
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
