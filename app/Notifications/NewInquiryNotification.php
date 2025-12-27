<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;

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
            'inquiry_id' => $this->inquiry->id,
            'name' => $this->inquiry->name,
            'email' => $this->inquiry->email,
            'subject' => $this->inquiry->subject,
            'title' => 'New Inquiry: ' . $this->inquiry->subject,
            'message' => 'Received from ' . $this->inquiry->name . ' (' . $this->inquiry->email . ')',
            'type' => 'inquiry',
            'icon' => 'inbox',
            'url' => '/dashboard/admin/inquiries',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'New Inquiry: ' . $this->inquiry->subject,
            'message' => 'Received from ' . $this->inquiry->name,
            'url' => '/dashboard/admin/inquiries',
            'type' => 'inquiry',
        ]);
    }
}
