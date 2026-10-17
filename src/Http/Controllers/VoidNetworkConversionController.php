<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Http\Controllers;

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Merchant conversion voiding.
 *
 * Remote merchants POST here when a previously reported order is
 * refunded or voided on their side; the network retracts the book
 * leg (companion reversal) so creator balances stop counting it.
 * Merchants authenticate with the site's catalog token. Voiding is
 * idempotent on (network link, external reference): repeat calls
 * return the already-reversed state without side effects.
 */
final class VoidNetworkConversionController extends Controller
{
    public function __invoke(Request $request, NetworkBooks $books, AffiliateIdentityResolver $identities): JsonResponse
    {
        $validated = $request->validate([
            'site' => ['required', 'string', 'max:255'],
            'link_code' => ['required', 'string', 'max:64'],
            'external_reference' => ['required', 'string', 'max:120'],
            'reason' => ['present', 'nullable', 'string', 'max:120'],
        ]);

        $site = OwnerContext::withOwner(null, fn (): ?AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey($validated['site'])
            ->orWhere('domain', $validated['site'])
            ->first());

        if (! $site instanceof AffiliateSite || ! $this->tokenMatches($site, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Invalid site credentials.'], 401);
        }

        if (! $site->isVerified()) {
            return response()->json(['message' => 'Site is not verified.'], 403);
        }

        $link = OwnerContext::withOwner(null, fn (): ?AffiliateOfferLink => AffiliateOfferLink::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->whereHas('link', fn ($query) => $query->withoutOwnerScope()->where('slug', $validated['link_code']))
            ->with([
                'link' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class),
            ])
            ->first());

        if (! $link instanceof AffiliateOfferLink || (string) $link->site_id !== (string) $site->getKey()) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        $leg = OwnerContext::withOwner(null, fn (): ?NetworkConversionLeg => NetworkConversionLeg::query()
            ->withoutOwnerScope()
            ->where('link_id', $link->getKey())
            ->where('external_reference', $validated['external_reference'])
            ->first());

        if (! $leg instanceof NetworkConversionLeg) {
            return response()->json(['message' => 'Conversion not found.'], 404);
        }

        if ($leg->status === LegStatus::Provisional) {
            return response()->json([
                'message' => 'Conversion is provisional and owned by the shared checkout flow; it cannot be voided remotely.',
            ], 422);
        }

        $reason = isset($validated['reason']) && $validated['reason'] !== '' ? $validated['reason'] : 'refund';
        $owner = $this->creatorOwner($identities, $leg);

        /** @var array{leg: NetworkConversionLeg, duplicate: bool, provisional: bool} $result */
        $result = OwnerContext::withOwner($owner, fn (): array => DB::transaction(function () use ($books, $leg, $reason): array {
            $lockedLeg = NetworkConversionLeg::query()
                ->whereKey($leg->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLeg->status === LegStatus::Provisional) {
                return ['leg' => $lockedLeg, 'duplicate' => false, 'provisional' => true];
            }

            $duplicate = $lockedLeg->status !== LegStatus::Posted;

            if (! $duplicate) {
                $books->reverse($lockedLeg, $reason);
                $lockedLeg->refresh();
            }

            return ['leg' => $lockedLeg, 'duplicate' => $duplicate, 'provisional' => false];
        }));

        if ($result['provisional']) {
            return response()->json([
                'message' => 'Conversion is provisional and owned by the shared checkout flow; it cannot be voided remotely.',
            ], 422);
        }

        $leg = $result['leg'];

        return response()->json([
            'ok' => true,
            'duplicate' => $result['duplicate'],
            'leg_status' => $leg->status->value,
            'conversion' => $leg->toArray(),
        ]);
    }

    private function creatorOwner(AffiliateIdentityResolver $identities, NetworkConversionLeg $leg): ?Model
    {
        $identity = $identities->find((string) $leg->affiliate_id);

        if ($identity === null) {
            return null;
        }

        try {
            return OwnerContext::fromTypeAndIdOrFail($identity->ownerType, $identity->ownerId);
        } catch (InvalidArgumentException | ModelNotFoundException) {
            return null;
        }
    }

    private function tokenMatches(AffiliateSite $site, string $bearerToken): bool
    {
        if ($bearerToken === '' || empty($site->catalog_token_encrypted)) {
            return false;
        }

        try {
            $expected = decrypt($site->catalog_token_encrypted);
        } catch (Throwable) {
            return false;
        }

        return is_string($expected) && $expected !== '' && hash_equals($expected, $bearerToken);
    }
}
