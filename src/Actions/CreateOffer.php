<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Actions;

use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Str;

final class CreateOffer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(AffiliateSite $site, array $data): AffiliateOffer
    {
        if (config('affiliate-network.owner.enabled', false)) {
            $validatedSite = OwnerWriteGuard::findOrFailForOwner(
                AffiliateSite::class,
                (string) $site->getKey(),
                includeGlobal: false,
                message: 'Site is not accessible in the current owner scope.',
            );
        } else {
            $validatedSite = AffiliateSite::query()->whereKey($site->getKey())->firstOrFail();
        }

        $data['site_id'] = (string) $validatedSite->getKey();

        $categoryId = $data['category_id'] ?? null;

        if (is_scalar($categoryId) && (string) $categoryId !== '') {
            if (config('affiliate-network.owner.enabled', false)) {
                $validatedCategory = OwnerWriteGuard::findOrFailForOwner(
                    AffiliateOfferCategory::class,
                    (string) $categoryId,
                    includeGlobal: (bool) config('affiliate-network.owner.include_global', false),
                    message: 'Category is not accessible in the current owner scope.',
                );
            } else {
                $validatedCategory = AffiliateOfferCategory::query()->whereKey((string) $categoryId)->firstOrFail();
            }

            $data['category_id'] = (string) $validatedCategory->getKey();
        }

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (empty($data['status'])) {
            $data['status'] = config('affiliate-network.offers.require_approval', true)
                ? AffiliateOffer::STATUS_PENDING
                : AffiliateOffer::STATUS_ACTIVE;
        }

        $offer = AffiliateOffer::create($data);

        event(new OfferCreated($offer));

        return $offer;
    }
}
