<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

class NewTicketNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct($ticket)
    {
        $this->ticket = $ticket;
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
        $user = $this->ticket->user;
        $userName = trim($user->first_name . ' ' . $user->last_name) ?: 'Unknown User';
        
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'title' => 'New Ticket #' . $this->ticket->ticket_number,
            'message' => "Subject: {$this->ticket->subject}. From: {$userName}",
            'sender_name' => $userName,
            'sender_avatar' => $user->avatar,
            'type' => 'ticket',
            'icon' => 'support-ticket',
            'url' => '/dashboard/admin/tickets/view?id=' . $this->ticket->id,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'New Ticket #' . $this->ticket->ticket_number,
            'message' => "Subject: {$this->ticket->subject}. From: {$this->ticket->user->name}",
            'url' => '/dashboard/admin/tickets/view?id=' . $this->ticket->id,
            'type' => 'ticket',
        ]);
    }
}
