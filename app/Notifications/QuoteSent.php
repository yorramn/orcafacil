<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class QuoteSent extends Notification
{
    public function __construct(
        private readonly string $quoteNumber,
        private readonly string $title,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Orçamento '.$this->quoteNumber.' disponível')
            ->line('Seu orçamento '.$this->title.' está disponível para consulta.')
            ->action('Visualizar orçamento', route('public.quotes.show', ['token' => $this->token]));
    }
}
