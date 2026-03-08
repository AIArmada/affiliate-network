<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Services;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Str;
use Throwable;

final class SiteVerificationService
{
    /**
     * Generate a verification token for a site.
     */
    public function generateToken(AffiliateSite $site): string
    {
        $token = 'affiliatenetwork-verify-' . Str::random(32);

        $site->update([
            'verification_token' => $token,
        ]);

        return $token;
    }

    /**
     * Verify a site using the specified method.
     */
    public function verify(AffiliateSite $site, string $method): bool
    {
        if ($site->verification_token === null) {
            return false;
        }

        $verified = match ($method) {
            'dns' => $this->verifyDns($site),
            'meta_tag' => $this->verifyMetaTag($site),
            'file' => $this->verifyFile($site),
            default => false,
        };

        if ($verified) {
            $site->update([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verification_method' => $method,
                'verified_at' => now(),
            ]);
        }

        return $verified;
    }

    /**
     * Verify via DNS TXT record.
     */
    private function verifyDns(AffiliateSite $site): bool
    {
        $records = @dns_get_record($site->domain, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && $record['txt'] === $site->verification_token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify via meta tag on the homepage.
     */
    private function verifyMetaTag(AffiliateSite $site): bool
    {
        try {
            $html = @file_get_contents("https://{$site->domain}/");

            if ($html === false) {
                $html = @file_get_contents("http://{$site->domain}/");
            }

            if ($html === false) {
                return false;
            }

            return str_contains($html, $site->verification_token);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Verify via file at well-known path.
     */
    private function verifyFile(AffiliateSite $site): bool
    {
        try {
            $content = @file_get_contents("https://{$site->domain}/.well-known/affiliate-network-verify.txt");

            if ($content === false) {
                $content = @file_get_contents("http://{$site->domain}/.well-known/affiliate-network-verify.txt");
            }

            if ($content === false) {
                return false;
            }

            return mb_trim($content) === $site->verification_token;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get instructions for verification.
     *
     * @return array<string, string>
     */
    public function getInstructions(AffiliateSite $site, string $method): array
    {
        $token = $site->verification_token ?? $this->generateToken($site);

        return match ($method) {
            'dns' => [
                'title' => 'DNS TXT Record',
                'description' => "Add a TXT record to your domain's DNS settings.",
                'record_type' => 'TXT',
                'record_name' => '@',
                'record_value' => $token,
            ],
            'meta_tag' => [
                'title' => 'HTML Meta Tag',
                'description' => 'Add this meta tag to the <head> section of your homepage.',
                'html' => "<meta name=\"affiliate-network-verify\" content=\"{$token}\">",
            ],
            'file' => [
                'title' => 'Verification File',
                'description' => 'Create a file at the following path with the token as contents.',
                'path' => '/.well-known/affiliate-network-verify.txt',
                'content' => $token,
            ],
            default => [],
        };
    }
}
