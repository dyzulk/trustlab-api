<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;

class NewInquiryNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $inquiry;

    /**
     * Create a new notification instance.
     */
    public function __construct(Inquiry $inquiry)
    {
        $this->inquiry = $inquiry;
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
        return [
            'inquiry_id' => $this->inquiry->id,
            'name' => $this->inquiry->name,
            'email' => $this->inquiry->email,
            'subject' => $this->inquiry->subject,
            'title' => 'New Inquiry: ' . $this->inquiry->subject,
            'message' => 'Received from ' . $this->inquiry->name . ' (' . $this->inquiry->email . ')',
            'type' => get_class($this),
            'icon' => 'inbox',
            'url' => '/dashboard/admin/inquiries',
            'sender_name' => $this->inquiry->name,
            'sender_avatar' => null,
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
