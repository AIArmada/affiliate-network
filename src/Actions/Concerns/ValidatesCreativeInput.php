<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions\Concerns;

trait ValidatesCreativeInput
{
    /**
     * @return array<string, mixed>
     */
    private static function operatorCreativeRules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function manualCreativeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:banner,text,image,video,document,email,html'],
            'description' => ['nullable', 'string'],
            'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
            'width' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'height' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'html_code' => ['nullable', 'string'],
        ];
    }
}
