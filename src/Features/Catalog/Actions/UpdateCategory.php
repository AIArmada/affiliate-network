<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateOfferCategory $category, array $data): AffiliateOfferCategory
    {
        return DB::transaction(function () use ($category, $data): AffiliateOfferCategory {
            $category = AffiliateOfferCategory::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $validated = Validator::make($data, [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
                'parent_id' => ['sometimes', 'nullable', 'string', 'max:36'],
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_active' => ['sometimes', 'boolean'],
            ])->validate();

            if (array_key_exists('name', $validated) && empty($validated['slug'])) {
                $validated['slug'] = Str::slug((string) $validated['name']);
            }

            $category->update($validated);

            return $category->refresh();
        });
    }
}
