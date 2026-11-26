<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Models;

use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Database\Factories\CreatorPostbackDeliveryFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $endpoint_id
 * @property string $leg_id
 * @property string $event_type
 * @property string $resolved_url
 * @property PostbackDeliveryStatus $status
 * @property int|null $http_status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $attempt_token
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read CreatorPostbackEndpoint|null $endpoint
 */
class CreatorPostbackDelivery extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): CreatorPostbackDeliveryFactory
    {
        return CreatorPostbackDeliveryFactory::new();
    }

    protected $fillable = [
        'endpoint_id',
        'leg_id',
        'event_type',
        'resolved_url',
        'status',
        'http_status',
        'attempts',
        'last_error',
        'attempt_token',
        'lease_expires_at',
        'delivered_at',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['postback_deliveries'] ?? $prefix . 'postback_deliveries';
    }

    /**
     * @return BelongsTo<CreatorPostbackEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(CreatorPostbackEndpoint::class, 'endpoint_id');
    }

    protected function casts(): array
    {
        return [
            'status' => PostbackDeliveryStatus::class,
            'http_status' => 'integer',
            'attempts' => 'integer',
            'lease_expires_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
