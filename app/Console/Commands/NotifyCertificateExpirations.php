<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NotifyCertificateExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:notify-expiring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $certificates = \App\Models\Certificate::where('valid_to', '>', $now)
            ->where('valid_to', '<', $now->copy()->addDays(30))
            ->with('user')
            ->get();

        foreach ($certificates as $certificate) {
            $user = $certificate->user;
            if (!$user || !$user->settings_certificate_renewal) {
                continue;
            }

            $daysRemaining = $now->diffInDays($certificate->valid_to, false);
            $daysRemaining = (int) ceil($daysRemaining); // Ensure integer

            // Check if we already sent a notification TODAY for this certificate to avoid spamming
            $alreadyNotifiedToday = $user->notifications()
                ->where('type', 'App\Notifications\CertificateExpiringNotification')
                ->where('data->certificate_id', $certificate->id)
                ->where('created_at', '>=', $now->copy()->startOfDay())
                ->exists();

            if ($alreadyNotifiedToday) {
                continue;
            }

            // Send Notification (Handles both Database/Bell and Mail channels based on days remaining)
            $user->notify(new \App\Notifications\CertificateExpiringNotification($certificate, $daysRemaining));

            $this->info("Sent notification to {$user->email} for certificate {$certificate->common_name} (Expires in {$daysRemaining} days)");
        }

        // 2. Check for ALREADY EXPIRED certificates that haven't been notified of expiration yet
        $expiredCertificates = \App\Models\Certificate::where('valid_to', '<', $now)
            ->whereNull('expired_notification_sent_at')
            ->with('user')
            ->get();
            
        foreach ($expiredCertificates as $certificate) {
            $user = $certificate->user;
             if (!$user || !$user->settings_certificate_renewal) {
                continue;
            }
            
            if ($user->email) {
                 \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\CertificateExpiredMail($certificate));
                 
                 // Mark as notified so we don't spam
                 $certificate->update(['expired_notification_sent_at' => $now]);
                 
                 $this->info("Sent EXPIRED email to {$user->email} for certificate {$certificate->common_name}");
            }
        }

        $this->info('Certificate expiration check completed.');
    }
}
