<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Contracts;

use AIArmada\AffiliateNetwork\Data\CreatorPostbackResponse;

interface CreatorPostbackTransport
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $body
     */
    public function send(string $method, string $url, array $headers = [], ?array $body = null): CreatorPostbackResponse;
}
