<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HubNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $event, public int $ticketId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('VP Developer Hub · '.$this->event)->greeting('Ciao '.$notifiable->name)->line('Ci sono aggiornamenti sulla richiesta #'.$this->ticketId.'. Accedi all’area riservata per consultarli.')->action('Apri la richiesta', url('/tickets/'.$this->ticketId))->line('Le richieste sono gestite secondo gli accordi del servizio; questa notifica non implica assistenza immediata.');
    }
}
