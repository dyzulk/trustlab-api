<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OTPHP\TOTP;
use PragmaRX\Google2FAQRCode\Google2FA;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Traits\CanTrackLogin;

class TwoFactorController extends Controller
{
    use CanTrackLogin;

    /**
     * Enable 2FA: Generate Secret & QR Code
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA is already enabled.'], 400);
        }

        $google2fa = new Google2FA();

        // Generate secret if not exists or if re-enabling
        $secret = $google2fa->generateSecretKey();
        
        // Save encrypted secret (or plain if you trust your db, but encrypted is better)
        // For simplicity with this library, it often expects raw secret. 
        // We will store it encrypted but decrypt it when needed if we were using a trait
        // But for manual implementation, lets store it temporarily in the session OR save it to DB directly?
        // Let's save it to DB but encrypt it.
        
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => null, // Reset codes
        ])->save();

        // Generate QR Code Object
        $qrCodeUrl = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code' => $qrCodeUrl,
        ]);
    }

    /**
     * Confirm 2FA: Verify initial OTP
     */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();
        $google2fa = new Google2FA();

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return response()->json(['message' => '2FA not initiated.'], 400);
        }

        $valid = $google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid 2FA code.'],
            ]);
        }

        // Generate Recovery Codes
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = \Illuminate\Support\Str::random(10) . '-' . \Illuminate\Support\Str::random(10);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ])->save();

        return response()->json([
            'message' => '2FA enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required']);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => '2FA disabled successfully.']);
    }
    /**
     * Verify 2FA during Login Challenge
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        // Check 2FA Secret
        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Exception $e) {
            return response()->json(['message' => '2FA configuration error.'], 500);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        // Check Recovery Code if TOTP failed
        if (!$valid) {
            $recoveryCodes = $user->two_factor_recovery_codes ? json_decode(decrypt($user->two_factor_recovery_codes), true) : [];
            
            if (in_array($request->code, $recoveryCodes)) {
                $valid = true;
                // Remove used recovery code
                $recoveryCodes = array_diff($recoveryCodes, [$request->code]);
                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
                ])->save();
            }
        }

        if (!$valid) {
             throw ValidationException::withMessages([
                'code' => ['Invalid code provided.'],
            ]);
        }

        // Success! 
        // 1. Establish session (for web/inertia/sanctum cookie flows)
        Auth::guard('web')->login($user, $request->boolean('remember'));

        // 2. Revoke the temp token
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        // 3. Create new full access token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Record History
        $this->recordLoginHistory($request, $user);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    /**
     * Show Recovery Codes
     */
    public function recoveryCodes(Request $request) 
    {
        if (!$request->user()->two_factor_confirmed_at) {
             return response()->json(['message' => '2FA not enabled.'], 400);
        }

        $codes = json_decode(decrypt($request->user()->two_factor_recovery_codes), true);

        return response()->json(['recovery_codes' => $codes]);
    }
}
