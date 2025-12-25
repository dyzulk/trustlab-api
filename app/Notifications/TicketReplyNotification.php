<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

class TicketReplyNotification extends Notification implements ShouldBroadcast
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
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
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
                ? '/dashboard/support/view?id=' . $this->ticket->id
                : '/dashboard/admin/tickets/view?id=' . $this->ticket->id,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => $this->byAdmin 
                ? 'Support replied to your ticket: ' . $this->ticket->subject
                : 'Customer replied to ticket: ' . $this->ticket->ticket_number,
            'url' => $this->byAdmin 
                ? '/dashboard/support/view?id=' . $this->ticket->id
                : '/dashboard/admin/tickets/view?id=' . $this->ticket->id,
            'type' => 'ticket_reply',
        ]);
    }
}
