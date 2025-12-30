<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class CertificateExpiringNotification extends Notification
{
    use Queueable;

    public $certificate;
    public $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct($certificate, $daysRemaining)
    {
        $this->certificate = $certificate;
        $this->daysRemaining = $daysRemaining;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        $channels = ['database'];

        if ($this->daysRemaining < 7) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new \App\Mail\CertificateExpiringMail($this->certificate, $this->daysRemaining))
            ->to($notifiable->email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'certificate_expiring',
            'certificate_id' => $this->certificate->id,
            'common_name' => $this->certificate->common_name,
            'days_remaining' => $this->daysRemaining,
            'valid_to' => $this->certificate->valid_to,
            'message' => "Certificate '{$this->certificate->common_name}' expires in {$this->daysRemaining} days.",
        ];
    }
}
