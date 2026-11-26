<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\CatalogApi;
use AIArmada\AffiliateNetwork\Features\Catalog\Api\OfferRecord;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Platform\AffiliateProfileGuard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

final readonly class ConfigureCreatorPostback
{
    public function __construct(
        private AffiliateProfileGuard $affiliateProfileGuard,
        private CatalogApi $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(string $affiliateId, array $data, ?string $endpointId = null): CreatorPostbackEndpoint
    {
        Validator::make(['affiliate_id' => $affiliateId], ['affiliate_id' => ['required', 'string', 'max:255']])->validate();

        $validated = Validator::make($data, [
            'offer_id' => ['nullable', 'string', 'max:36'],
            'url_template' => ['required', 'string', 'max:2048', 'regex:/^https?:\/\//i'],
            'http_method' => ['required', 'string', Rule::in(['GET', 'POST'])],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', Rule::enum(PostbackEventType::class)],
            'signing_secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        $urlTemplate = (string) $validated['url_template'];

        if (filter_var(str_replace($this->tokens(), 'x', $urlTemplate), FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Postback URL template must be a valid http(s) template.');
        }

        $events = array_values(array_unique(array_map(
            static fn (mixed $event): string => $event instanceof PostbackEventType ? $event->value : (string) $event,
            $validated['events'],
        )));

        $secret = $validated['signing_secret'] ?? null;
        $offerId = $validated['offer_id'] ?? null;

        if ($offerId !== null && ! $this->catalog->findOfferUnscoped($offerId) instanceof OfferRecord) {
            throw new RuntimeException('The selected offer does not exist.');
        }

        $connection = (new CreatorPostbackEndpoint)->getConnection();

        return $connection->transaction(function () use ($affiliateId, $endpointId, $validated, $urlTemplate, $events, $secret, $offerId, $connection): CreatorPostbackEndpoint {
            $this->affiliateProfileGuard->lockForWrite($affiliateId, spl_object_id($connection));

            $endpoint = $endpointId === null
                ? new CreatorPostbackEndpoint(['affiliate_id' => $affiliateId])
                : CreatorPostbackEndpoint::query()->whereKey($endpointId)->where('affiliate_id', $affiliateId)->lockForUpdate()->firstOrFail();

            $endpoint->fill([
                'offer_id' => $offerId,
                'url_template' => $urlTemplate,
                'http_method' => mb_strtoupper((string) $validated['http_method']),
                'events' => $events,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if (is_string($secret) && $secret !== '') {
                $endpoint->signing_secret_encrypted = encrypt($secret);
            }

            $endpoint->save();

            return $endpoint;
        });
    }

    /**
     * @return list<string>
     */
    private function tokens(): array
    {
        return [
            '{leg_id}',
            '{offer_id}',
            '{link_code}',
            '{external_reference}',
            '{event_type}',
            '{status}',
            '{revenue_minor}',
            '{commission_minor}',
            '{payout_minor}',
            '{currency}',
            '{sub1}',
            '{sub2}',
            '{sub3}',
            '{coupon_code}',
        ];
    }
}
