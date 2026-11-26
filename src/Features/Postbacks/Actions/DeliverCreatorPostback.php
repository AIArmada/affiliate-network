<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Data\CreatorPostbackResponse;
use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class DeliverCreatorPostback
{
    public function __construct(
        private CreatorPostbackTransport $transport,
    ) {}

    public function execute(string $deliveryId): CreatorPostbackDelivery
    {
        $connection = (new CreatorPostbackDelivery)->getConnection();
        $maxAttempts = max(1, (int) config('affiliate-network.creator_postbacks.max_attempts', 3));

        $claim = $connection->transaction(function () use ($deliveryId, $maxAttempts): array {
            $delivery = CreatorPostbackDelivery::query()->lockForUpdate()->findOrFail($deliveryId);

            if ($delivery->status === PostbackDeliveryStatus::Delivered) {
                return ['delivery' => $delivery];
            }

            if ($delivery->lease_expires_at?->isFuture()) {
                throw new RuntimeException('Creator postback delivery is already in progress.');
            }

            if ((int) $delivery->attempts >= $maxAttempts) {
                if ($delivery->status !== PostbackDeliveryStatus::Failed) {
                    $delivery->forceFill([
                        'status' => PostbackDeliveryStatus::Failed,
                        'last_error' => 'max_attempts_exhausted',
                    ])->save();
                }

                return ['delivery' => $delivery];
            }

            $endpoint = CreatorPostbackEndpoint::query()->whereKey($delivery->endpoint_id)->first();

            if (! $endpoint instanceof CreatorPostbackEndpoint) {
                $delivery->forceFill([
                    'status' => PostbackDeliveryStatus::Failed,
                    'last_error' => 'endpoint_missing',
                ])->save();

                return ['delivery' => $delivery];
            }

            if (! $endpoint->is_active || ! config('affiliate-network.creator_postbacks.enabled', true)) {
                $delivery->forceFill([
                    'status' => PostbackDeliveryStatus::Failed,
                    'last_error' => 'endpoint_disabled',
                ])->save();

                return ['delivery' => $delivery];
            }

            $secret = $this->signingSecret($endpoint);

            if ($secret === null && $endpoint->signing_secret_encrypted !== null && $endpoint->signing_secret_encrypted !== '') {
                $delivery->forceFill([
                    'status' => PostbackDeliveryStatus::Failed,
                    'last_error' => 'signing_secret_invalid',
                ])->save();

                return ['delivery' => $delivery];
            }

            $headers = $secret === null
                ? []
                : ['X-Affiliate-Network-Signature' => hash_hmac('sha256', (string) $delivery->resolved_url, $secret)];

            $token = (string) Str::uuid();
            $delivery->forceFill([
                'attempt_token' => $token,
                'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
                'attempts' => (int) $delivery->attempts + 1,
                'last_error' => null,
            ])->save();

            return [
                'token' => $token,
                'lease' => $delivery->fresh()?->lease_expires_at?->toISOString(),
                'method' => mb_strtoupper((string) $endpoint->http_method),
                'url' => (string) $delivery->resolved_url,
                'headers' => $headers,
            ];
        }, attempts: 3);

        if (isset($claim['delivery'])) {
            return $claim['delivery'];
        }

        try {
            $response = $this->transport->send($claim['method'], $claim['url'], $claim['headers']);
        } catch (Throwable $throwable) {
            report($throwable);
            $response = new CreatorPostbackResponse(false, null, 'transport_failed');
        }

        $outcome = $connection->transaction(function () use ($deliveryId, $claim, $response, $maxAttempts): array {
            $delivery = CreatorPostbackDelivery::query()->lockForUpdate()->findOrFail($deliveryId);

            if ($delivery->status === PostbackDeliveryStatus::Delivered) {
                return ['delivery' => $delivery, 'retry' => false];
            }

            if ($delivery->attempt_token !== $claim['token']
                || $delivery->lease_expires_at?->toISOString() !== $claim['lease']) {
                return ['delivery' => $delivery, 'retry' => false];
            }

            if ($response->successful) {
                $delivery->forceFill([
                    'status' => PostbackDeliveryStatus::Delivered,
                    'http_status' => $response->httpStatus,
                    'last_error' => null,
                    'delivered_at' => CarbonImmutable::now(),
                    'attempt_token' => null,
                    'lease_expires_at' => null,
                ])->save();

                return ['delivery' => $delivery, 'retry' => false];
            }

            $delivery->forceFill([
                'status' => PostbackDeliveryStatus::Failed,
                'http_status' => $response->httpStatus,
                'last_error' => $this->safeError($response->error),
                'attempt_token' => null,
                'lease_expires_at' => null,
            ])->save();

            return [
                'delivery' => $delivery,
                'retry' => (int) $delivery->attempts < $maxAttempts,
            ];
        }, attempts: 3);

        if ($outcome['retry']) {
            throw new RuntimeException('Creator postback delivery failed; retry scheduled.');
        }

        return $outcome['delivery'];
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

    private function safeError(?string $error): ?string
    {
        if ($error === null || mb_trim($error) === '') {
            return null;
        }

        $safe = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $error) ?? '';

        return mb_substr($safe === '' ? 'delivery_failed' : $safe, 0, 128);
    }
}
