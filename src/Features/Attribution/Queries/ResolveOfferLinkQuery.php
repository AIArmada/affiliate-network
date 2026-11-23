<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Attribution\Queries;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;

final class ResolveOfferLinkQuery
{
    public function byIdUnscoped(string $linkId): ?AffiliateOfferLink
    {
        return AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereKey($linkId)
            ->first();
    }

    public function bySlug(string $slug): ?AffiliateOfferLink
    {
        return OwnerContext::withOwner(null, fn (): ?AffiliateOfferLink => $this->slugQuery($slug)
            ->where('is_active', true)
            ->first());
    }

    public function bySlugUnscoped(string $slug, bool $requireActive = false): ?AffiliateOfferLink
    {
        $query = $this->slugQuery($slug);

        if ($requireActive) {
            $query->where('is_active', true);
        }

        return $query->first();
    }

    /**
     * @return Builder<AffiliateOfferLink>
     */
    private function slugQuery(string $slug): Builder
    {
        return AffiliateOfferLink::query()
            ->withoutGlobalScopes()
            ->whereHas('link', fn (Builder $query): Builder => $query
                ->withoutGlobalScope(OwnerScope::class)
                ->where('slug', $slug))
            ->with([
                'link' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            ]);
    }
}
