<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Notifications;

use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NetworkConversionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NotificationPayload $payload,
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
        return (new MailMessage)
            ->subject($this->payload->title)
            ->line($this->payload->body);
    }
}
