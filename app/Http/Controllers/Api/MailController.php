<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class MailController extends Controller
{
    /**
     * Send a test email.
     */
    public function sendTestEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'mailer' => 'required|string|in:smtp,support',
        ]);

        $mailer = $request->mailer;
        $recipient = $request->email;
        $host = Config::get("mail.mailers.{$mailer}.host");

        try {
            Mail::mailer($mailer)->to($recipient)->send(new TestMail($mailer, $host));

            return response()->json([
                'success' => true,
                'message' => "Test email successfully sent via {$mailer} mailer.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to send email: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current mailer configurations (excluding passwords).
     */
    public function getConfigurations()
    {
        $configs = [
            'smtp' => [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from' => config('mail.from.address'),
            ],
            'support' => [
                'host' => config('mail.mailers.support.host'),
                'port' => config('mail.mailers.support.port'),
                'encryption' => config('mail.mailers.support.encryption'),
                'from' => config('mail.mailers.support.from.address'),
            ],
        ];

        return response()->json($configs);
    }
}
