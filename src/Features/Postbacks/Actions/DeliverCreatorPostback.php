<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final class DeliverCreatorPostback
{
    public function __construct(
        private readonly CreatorPostbackTransport $transport,
    ) {}

    public function execute(string $deliveryId): CreatorPostbackDelivery
    {
        $delivery = CreatorPostbackDelivery::query()->whereKey($deliveryId)->firstOrFail();

        if ($delivery->status === PostbackDeliveryStatus::Delivered) {
            return $delivery;
        }

        $endpoint = CreatorPostbackEndpoint::query()->whereKey($delivery->endpoint_id)->first();

        if (! $endpoint instanceof CreatorPostbackEndpoint) {
            $delivery->forceFill([
                'status' => PostbackDeliveryStatus::Failed,
                'last_error' => 'endpoint_missing',
            ])->save();

            return $delivery;
        }

        $headers = [];
        $secret = $this->signingSecret($endpoint);

        if ($secret !== null) {
            $headers['X-Affiliate-Network-Signature'] = hash_hmac('sha256', $delivery->resolved_url, $secret);
        }

        $response = $this->transport->send(mb_strtoupper((string) $endpoint->http_method), (string) $delivery->resolved_url, $headers);

        $attempts = (int) $delivery->attempts + 1;
        $maxAttempts = max(1, (int) config('affiliate-network.creator_postbacks.max_attempts', 3));

        if ($response->successful) {
            $delivery->forceFill([
                'status' => PostbackDeliveryStatus::Delivered,
                'http_status' => $response->httpStatus,
                'attempts' => $attempts,
                'last_error' => null,
                'delivered_at' => CarbonImmutable::now(),
            ])->save();

            return $delivery;
        }

        $delivery->forceFill([
            'status' => PostbackDeliveryStatus::Failed,
            'http_status' => $response->httpStatus,
            'attempts' => $attempts,
            'last_error' => $response->error,
        ])->save();

        if ($attempts < $maxAttempts) {
            throw new RuntimeException('Creator postback delivery failed; retry scheduled.');
        }

        return $delivery;
    }

    private function signingSecret(CreatorPostbackEndpoint $endpoint): ?string
    {
        if ($endpoint->signing_secret_encrypted === null || $endpoint->signing_secret_encrypted === '') {
            return null;
        }

        try {
            $secret = decrypt($endpoint->signing_secret_encrypted);
        } catch (Throwable) {
            return null;
        }

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
