<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferCreativeFactory;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Carbon\CarbonImmutable;
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
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property string|null $external_creative_id
 * @property string|null $destination_url
 * @property string|null $source_asset_url
 * @property int|null $width
 * @property int|null $height
 * @property string|null $html_code
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read AffiliateOffer $offer
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

    public const TYPE_BANNER = 'banner';

    public const TYPE_TEXT = 'text';

    public const TYPE_EMAIL = 'email';

    public const TYPE_HTML = 'html';

    public const TYPE_VIDEO = 'video';

    public const TYPE_IMAGE = 'image';

    public const TYPE_DOCUMENT = 'document';

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('creative_asset')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'video/mp4', 'video/webm', 'application/pdf', 'application/zip',
            ]);
    }

    public function getAssetUrl(): ?string
    {
        return $this->external_creative_id !== null
            ? $this->source_asset_url
            : $this->getFirstMedia('creative_asset')?->getFullUrl();
    }

    public function getEmbedCode(string $trackingUrl): ?string
    {
        $href = htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $asset = $this->getAssetUrl();

        if (in_array($this->type, [self::TYPE_BANNER, self::TYPE_IMAGE], true)) {
            if ($asset === null) {
                return null;
            }

            $src = htmlspecialchars($asset, ENT_QUOTES, 'UTF-8');

            return sprintf('<a href="%s" rel="sponsored"><img src="%s" alt="%s"></a>', $href, $src, $name);
        }

        if (in_array($this->type, [self::TYPE_HTML, self::TYPE_EMAIL], true) && $this->html_code !== null) {
            return str_replace('{{tracking_url}}', $href, $this->html_code);
        }

        return sprintf('<a href="%s" rel="sponsored">%s</a>', $href, $name);
    }

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

    protected static function newFactory(): AffiliateOfferCreativeFactory
    {
        return AffiliateOfferCreativeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
