<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Support;

use Illuminate\Http\Client\Response;

final class BoundedHttpResponseBody
{
    public static function read(Response $response, int $maximumBytes): ?string
    {
        $maximumBytes = max(1, $maximumBytes);
        $contentLength = $response->header('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > $maximumBytes) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();
        $body = '';

        while (! $stream->eof()) {
            $remaining = $maximumBytes - mb_strlen($body, '8bit');
            $chunk = $stream->read(min(8192, $remaining + 1));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;

            if (mb_strlen($body, '8bit') > $maximumBytes) {
                return null;
            }
        }

        return $body;
    }
}
