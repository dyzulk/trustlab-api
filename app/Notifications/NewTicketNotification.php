<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;

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
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }



    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        $user = $this->ticket->user;
        $userName = $user ? trim($user->first_name . ' ' . $user->last_name) : 'Unknown User';
        if (empty($userName)) $userName = 'Unknown User';
        
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'title' => 'New Ticket #' . $this->ticket->ticket_number,
            'message' => "Subject: {$this->ticket->subject}. From: {$userName}",
            'sender_name' => $userName,
            'sender_avatar' => $user ? $user->avatar : null,
            'type' => get_class($this),
            'icon' => 'support-ticket',
            'url' => '/dashboard/admin/tickets/view?id=' . $this->ticket->id,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        $db = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'title' => $db['title'],
            'message' => $db['message'],
            'url' => $db['url'],
            'type' => get_class($this),
            'data' => $db
        ]);
    }
}
