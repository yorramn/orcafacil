<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tenantName, private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Convite para '.$this->tenantName)->line('Você recebeu um convite para participar da equipe.')->action('Aceitar convite', url('/invitations/accept?token='.$this->token));
    }
}
