<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Models;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CreativeRecord;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $offer_id
 * @property CreativeType $type
 * @property string $name
 * @property string|null $description
 * @property string|null $external_creative_id
 * @property string|null $source_asset_url
 * @property string|null $destination_url
 * @property int|null $width
 * @property int|null $height
 * @property string|null $html_code
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 */
class AffiliateOfferCreative extends Model implements Auditable, HasMedia
{
    use HasCommerceAudit;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
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

    protected $fillable = [
        'offer_id',
        'external_creative_id',
        'destination_url',
        'type',
        'name',
        'description',
        'source_asset_url',
        'width',
        'height',
        'html_code',
        'is_active',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_creatives'] ?? $prefix . 'offer_creatives';
    }

    /**
     * @return BelongsTo<AffiliateOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('creative_asset')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'video/mp4',
                'video/webm',
                'application/pdf',
                'application/zip',
            ]);
    }

    public function getAssetUrl(): ?string
    {
        return $this->external_creative_id !== null
            ? $this->source_asset_url
            : $this->getFirstMedia('creative_asset')?->getFullUrl();
    }

    protected function casts(): array
    {
        return [
            'type' => CreativeType::class,
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function toRecord(): CreativeRecord
    {
        return new CreativeRecord(
            id: (string) $this->getKey(),
            offerId: (string) $this->offer_id,
            type: $this->type,
            name: (string) $this->name,
            description: $this->description,
            externalCreativeId: $this->external_creative_id,
            sourceAssetUrl: $this->source_asset_url,
            destinationUrl: $this->destination_url,
            width: $this->width,
            height: $this->height,
            htmlCode: $this->html_code,
            isActive: (bool) $this->is_active,
            sortOrder: (int) $this->sort_order,
            metadata: $this->metadata,
        );
    }
}
