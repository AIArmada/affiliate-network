<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Notifications;

use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AffiliateOfferApplication $application,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $offer = $this->application->offer;

        return (new MailMessage)
            ->subject("Approved: promote {$offer->name}")
            ->line("Your application to {$offer->name} was approved. Grab your tracking link and start earning.");
    }
}
