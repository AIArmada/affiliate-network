<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Features\Postbacks\EntryPoints\Jobs;

use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeliverCreatorPostback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeliverCreatorPostbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public function __construct(
        public readonly string $deliveryId,
    ) {
        $this->tries = max(1, (int) config('affiliate-network.creator_postbacks.max_attempts', 3));
    }

    public function handle(DeliverCreatorPostback $deliver): void
    {
        $deliver->execute($this->deliveryId);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $backoff = config('affiliate-network.creator_postbacks.backoff_seconds', [10, 60, 300]);

        if (! is_array($backoff)) {
            return [10, 60, 300];
        }

        return array_values(array_map(static fn (mixed $seconds): int => (int) $seconds, $backoff));
    }
}
