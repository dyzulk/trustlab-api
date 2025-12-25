<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

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
        return [
            'inquiry_id' => $this->inquiry->id,
            'name' => $this->inquiry->name,
            'email' => $this->inquiry->email,
            'subject' => $this->inquiry->subject,
            'message' => 'New inquiry from ' . $this->inquiry->name . ': ' . $this->inquiry->subject,
            'type' => 'inquiry',
            'icon' => 'inbox',
            'url' => '/dashboard/admin/inquiries', // Consistent URL style
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => 'New inquiry from ' . $this->inquiry->name,
            'url' => '/dashboard/admin/inquiries',
            'type' => 'inquiry',
        ]);
    }
}
