<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Notifications;

use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NetworkConversionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NetworkConversionLeg $leg,
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
        $amount = number_format($this->leg->payout_minor / 100, 2);

        return (new MailMessage)
            ->subject("New earnings: {$this->leg->commission_currency} {$amount}")
            ->line("One of your links converted and earned {$this->leg->commission_currency} {$amount}.");
    }
}
