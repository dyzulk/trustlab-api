<?php

namespace App\Notifications;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;

class CertificateNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $certificate;
    public $action; // 'issued' or 'revoked'

    /**
     * Create a new notification instance.
     */
    public function __construct($certificate, $action = 'issued')
    {
        $this->certificate = $certificate;
        $this->action = $action;
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
    public function toDatabase($notifiable)
    {
        $title = $this->action === 'issued' ? 'Certificate Issued' : 'Certificate Revoked';
        $message = $this->action === 'issued' 
            ? "New certificate for {$this->certificate->common_name} has been issued."
            : "Certificate for {$this->certificate->common_name} has been revoked.";

        return [
            'certificate_id' => $this->certificate->getKey(),
            'common_name' => $this->certificate->common_name,
            'title' => $title,
            'message' => $message,
            'type' => get_class($this),
            'icon' => $this->action === 'issued' ? 'check-circle' : 'trash-2',
            'url' => '/dashboard/certificates',
            'sender_name' => null,
            'sender_avatar' => null,
        ];
    }

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
