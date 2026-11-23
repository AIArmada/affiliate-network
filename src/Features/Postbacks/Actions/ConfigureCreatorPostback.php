<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\Actions;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

final class ConfigureCreatorPostback
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(string $affiliateId, array $data): CreatorPostbackEndpoint
    {
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

        return DB::transaction(fn (): CreatorPostbackEndpoint => CreatorPostbackEndpoint::create([
            'affiliate_id' => $affiliateId,
            'offer_id' => $validated['offer_id'] ?? null,
            'url_template' => $urlTemplate,
            'http_method' => mb_strtoupper((string) $validated['http_method']),
            'events' => $events,
            'signing_secret_encrypted' => is_string($secret) && $secret !== '' ? encrypt($secret) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]));
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
