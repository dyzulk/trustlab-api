<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param  \Illuminate\Foundation\Auth\EmailVerificationRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(EmailVerificationRequest $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail() && !$user->pending_email) {
            return redirect(config('app.frontend_url') . '/verify-success?already_verified=1');
        }

        // If there is a pending email, promote it to the main email
        if ($user->pending_email) {
            $user->email = $user->pending_email;
            $user->pending_email = null;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/verify-success?verified=1');
    }

    /**
     * Resend the email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail() && !$user->pending_email) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        if ($user->pending_email) {
            $user->notify(new \App\Notifications\PendingEmailVerificationNotification);
        } else {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification link sent.']);
    }
}
