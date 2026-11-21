<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Notifications;

use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
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
        $amount = MoneyFormatter::formatMinor(
            (int) $this->leg->payout_minor,
            (string) $this->leg->commission_currency,
        );

        return (new MailMessage)
            ->subject("New earnings: {$amount}")
            ->line("One of your links converted and earned {$amount}.");
    }
}
