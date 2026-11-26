<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Models;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Database\Factories\CreatorPostbackEndpointFactory;
use AIArmada\AffiliateNetwork\Features\Postbacks\Api\CreatorPostbackRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $offer_id
 * @property string $url_template
 * @property string $http_method
 * @property array<int, string> $events
 * @property string|null $signing_secret_encrypted
 * @property bool $is_active
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CreatorPostbackEndpoint extends Model
{
    use HasFactory;
    use HasUuids;

    protected static function newFactory(): CreatorPostbackEndpointFactory
    {
        return CreatorPostbackEndpointFactory::new();
    }

    protected $hidden = ['signing_secret_encrypted'];

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'url_template',
        'http_method',
        'events',
        'signing_secret_encrypted',
        'is_active',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['creator_postbacks'] ?? $prefix . 'creator_postbacks';
    }

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<CreatorPostbackDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CreatorPostbackDelivery::class, 'endpoint_id');
    }

    public function subscribesTo(PostbackEventType $eventType): bool
    {
        return in_array($eventType->value, $this->events ?? [], true);
    }

    /**
     * @return list<PostbackEventType>
     */
    public function eventTypes(): array
    {
        $types = [];

        foreach ($this->events ?? [] as $value) {
            $type = PostbackEventType::tryFrom((string) $value);

            if ($type !== null) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function toRecord(): CreatorPostbackRecord
    {
        return new CreatorPostbackRecord(
            id: (string) $this->getKey(),
            affiliateId: (string) $this->affiliate_id,
            offerId: $this->offer_id,
            urlTemplate: (string) $this->url_template,
            httpMethod: (string) $this->http_method,
            events: $this->eventTypes(),
            hasSigningSecret: $this->signing_secret_encrypted !== null && $this->signing_secret_encrypted !== '',
            isActive: (bool) $this->is_active,
            createdAt: $this->created_at,
        );
    }
}
