<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Models;

use AIArmada\AffiliateNetwork\Features\Catalog\Actions\DeleteCategoryDependents;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CategoryRecord;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

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
 * @property-read AffiliateOfferCategory|null $parent
 * @property-read Collection<int, AffiliateOfferCategory> $children
 * @property-read Collection<int, AffiliateOffer> $offers
 */
class AffiliateOfferCategory extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

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

        return $this->table ?? ($tables['offer_categories'] ?? $prefix . 'offer_categories');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
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
            app(DeleteCategoryDependents::class)->execute((string) $category->getKey());
        });
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

    public function toRecord(): CategoryRecord
    {
        return new CategoryRecord(
            id: (string) $this->getKey(),
            parentId: $this->parent_id,
            name: (string) $this->name,
            slug: (string) $this->slug,
            description: $this->description,
            icon: $this->icon,
            sortOrder: (int) $this->sort_order,
            isActive: (bool) $this->is_active,
        );
    }
}
