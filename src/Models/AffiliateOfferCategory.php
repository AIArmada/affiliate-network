<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Models;

use AIArmada\AffiliateNetwork\Database\Factories\AffiliateOfferCategoryFactory;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property-read Model|null $owner
 * @property-read AffiliateOfferCategory|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateOfferCategory> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateOffer> $offers
 */
class AffiliateOfferCategory extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'affiliate-network.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    public function getTable(): string
    {
        $tables = config('affiliate-network.database.tables', []);
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        return $tables['offer_categories'] ?? $prefix . 'offer_categories';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    /**
     * @return BelongsTo<AffiliateOfferCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AffiliateOfferCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AffiliateOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(AffiliateOffer::class, 'category_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $category): void {
            $category->children()->update(['parent_id' => $category->parent_id]);
            $category->offers()->update(['category_id' => null]);
        });
    }

    protected static function newFactory(): AffiliateOfferCategoryFactory
    {
        return AffiliateOfferCategoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
