<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;

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
        $sender = $this->reply->user;
        $senderName = $sender ? trim($sender->first_name . ' ' . $sender->last_name) : 'Support Team';
        if (empty($senderName)) $senderName = 'Support Team';
        
        // URL determines based on recipient role
        $url = ($notifiable->role === 'admin') 
            ? '/dashboard/admin/tickets/view?id=' . $this->ticket->id 
            : '/dashboard/support/view?id=' . $this->ticket->id;

        return [
            'ticket_id' => $this->ticket->id,
            'reply_id' => $this->reply->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => 'Reply to Ticket #' . $this->ticket->ticket_number,
            'message' => $this->byAdmin 
                ? 'Support replied: ' . $this->ticket->subject
                : $senderName . ' replied: ' . $this->ticket->ticket_number,
            'sender_name' => $senderName,
            'sender_avatar' => $sender ? $sender->avatar : null,
            'type' => get_class($this),
            'icon' => 'support-ticket',
            'url' => $url,
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
