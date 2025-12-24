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

            // Critical Email Alert (< 7 days)
            if ($daysRemaining < 7) {
                // Check if we already sent an email today (simple logic: one email per day near expiration)
                // For a robust system, we would log this in a separate notifications table.
                // Here we assume the scheduler runs once daily.
                if ($user->email && $user->settings_certificate_renewal) {
                     \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\CertificateExpiringMail($certificate, $daysRemaining));
                     $this->info("Sent expiration email to {$user->email} for certificate {$certificate->common_name} (Expires in {$daysRemaining} days)");
                }
            }

            // In-App Notification (Database Notification) logic would go here if we had the notifications table set up.
            // For now, we focus on the email part as requested for the automation.
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
