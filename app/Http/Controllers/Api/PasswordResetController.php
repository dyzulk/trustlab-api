<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // We return a "success" message anyway to prevent email enumeration
            return response()->json(['message' => 'Jika email tersebut terdaftar, kami akan mengirimkan link reset password.']);
        }

        // Generate a token
        $token = Str::random(64);

        // Store token in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now()
            ]
        );

        // Send Email
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        Mail::send('emails.password-reset', ['url' => $resetUrl, 'name' => $user->first_name], function ($message) use ($request) {
            $message->to($request->email);
            $message->subject('Reset Password - TrustLab');
        });

        return response()->json(['message' => 'Reset link sent to your email.']);
    }

    /**
     * Reset the given user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        // Check expiry (e.g., 60 minutes)
        if (Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
             DB::table('password_reset_tokens')->where('email', $request->email)->delete();
             return response()->json(['message' => 'Token has expired.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
             return response()->json(['message' => 'User not found.'], 404);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful.']);
    }
}
