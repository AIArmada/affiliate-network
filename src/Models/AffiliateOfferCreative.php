<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferCreativeFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $offer_id
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property string|null $url
 * @property string|null $file_path
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property string|null $html_code
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read AffiliateOffer $offer
 */
class AffiliateOfferCreative extends Model
{
    use HasFactory;
    use HasUuids;

    public const TYPE_BANNER = 'banner';

    public const TYPE_TEXT = 'text';

    public const TYPE_EMAIL = 'email';

    public const TYPE_HTML = 'html';

    public const TYPE_VIDEO = 'video';

    protected $fillable = [
        'offer_id',
        'type',
        'name',
        'description',
        'url',
        'file_path',
        'width',
        'height',
        'alt_text',
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
