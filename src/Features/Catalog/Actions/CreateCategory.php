<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AffiliateOfferCategory
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'max:36'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug((string) $validated['name']);
        }

        return AffiliateOfferCategory::query()->create($validated);
    }
}
