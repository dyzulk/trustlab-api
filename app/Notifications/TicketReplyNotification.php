<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketReplyNotification extends Notification
{
    use Queueable;

    protected $ticket;
    protected $reply;
    protected $byAdmin;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticket, $reply, $byAdmin = false)
    {
        $this->ticket = $ticket;
        $this->reply = $reply;
        $this->byAdmin = $byAdmin;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reply_id' => $this->reply->id,
            'ticket_number' => $this->ticket->ticket_number,
            'message' => $this->byAdmin 
                ? 'Support replied to your ticket: ' . $this->ticket->subject
                : 'Customer replied to ticket: ' . $this->ticket->ticket_number,
            'type' => 'ticket_reply',
            'icon' => 'support-ticket',
            'url' => $this->byAdmin 
                ? '/dashboard/support/' . $this->ticket->id
                : '/dashboard/admin/tickets/' . $this->ticket->id,
        ];
    }
}
