<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Catalog\Actions;

use AIArmada\AffiliateNetwork\Data\CreativeType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SyncOfferCreatives
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalize(mixed $creatives): array
    {
        $types = array_map(static fn (CreativeType $type): string => $type->value, CreativeType::cases());
        $types[] = 'text_link';

        $validated = Validator::make(['creatives' => $creatives], [
            'creatives' => ['present', 'array', 'list'],
            'creatives.*' => ['required', 'array'],
            'creatives.*.id' => ['required', 'string', 'max:255', 'distinct:strict'],
            'creatives.*.type' => ['required', Rule::in($types)],
            'creatives.*.name' => ['required', 'string', 'max:255'],
            'creatives.*.description' => ['present', 'nullable', 'string'],
            'creatives.*.asset_url' => ['present', 'nullable', 'url:http,https', 'max:2048'],
            'creatives.*.destination_url' => ['present', 'nullable', 'url:http,https', 'max:2048'],
            'creatives.*.width' => ['present', 'nullable', 'integer', 'min:1', 'max:65535'],
            'creatives.*.height' => ['present', 'nullable', 'integer', 'min:1', 'max:65535'],
            'creatives.*.tracking_code' => ['required', 'string', 'max:255'],
            'creatives.*.metadata' => ['present', 'nullable', 'array'],
        ])->validate();

        $rows = [];

        foreach ($validated['creatives'] as $creative) {
            $type = $creative['type'] === 'text_link'
                ? CreativeType::Text
                : CreativeType::from((string) $creative['type']);

            $rows[] = [
                'external_creative_id' => $creative['id'],
                'type' => $type,
                'name' => $creative['name'],
                'description' => $creative['description'],
                'source_asset_url' => $creative['asset_url'],
                'destination_url' => $creative['destination_url'],
                'width' => $creative['width'],
                'height' => $creative['height'],
                'html_code' => null,
                'metadata' => array_merge(is_array($creative['metadata'] ?? null) ? $creative['metadata'] : [], [
                    'merchant_type' => $creative['type'],
                    'merchant_tracking_code' => $creative['tracking_code'],
                ]),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strcmp($left['external_creative_id'], $right['external_creative_id']));

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $creatives
     */
    public function execute(AffiliateOffer $offer, array $creatives): void
    {
        DB::transaction(function () use ($offer, $creatives): void {
            $offer = AffiliateOffer::query()->whereKey($offer->getKey())->lockForUpdate()->firstOrFail();
            $existing = $offer->creatives()->whereNotNull('external_creative_id')->get()->keyBy('external_creative_id');
            $incomingIds = [];

            foreach ($creatives as $data) {
                $id = $data['external_creative_id'];
                $incomingIds[] = $id;
                $creative = $existing->get($id);

                if ($creative !== null) {
                    $creative->fill($data)->save();
                } else {
                    $offer->creatives()->create($data);
                }
            }

            foreach ($existing as $id => $creative) {
                if (! in_array($id, $incomingIds, true)) {
                    $creative->delete();
                }
            }
        });
    }
}
