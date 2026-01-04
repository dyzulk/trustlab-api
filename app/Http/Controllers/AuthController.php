<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Traits\CanTrackLogin;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use CanTrackLogin, LogsActivity;

    /**
     * Handle Login Request
     */
    /**
     * Handle Login Request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // 2FA Check
        if ($user->two_factor_confirmed_at) {
            // Return Temporary Token with "2fa" capability
            // We DO NOT call Auth::login() here to prevent session cookie creation
            $tempToken = $user->createToken('2fa_temp_token', ['2fa-required'])->plainTextToken;
            
            return response()->json([
                'two_factor_required' => true,
                'temp_token' => $tempToken,
            ]);
        }

        // Standard Login - Establish session and issue token
        Auth::guard('web')->login($user, $request->boolean('remember'));

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->recordLoginHistory($request, $user);
        $this->logActivity('login', 'User logged in to the dashboard');

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Handle Registration Request
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'fname' => 'required|string|max:255',
            'lname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'first_name' => $validated['fname'],
            'last_name' => $validated['lname'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->recordLoginHistory($request, $user);
        $this->logActivity('register', 'User registered a new account');

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Redirect to Social Provider
     */
    public function socialRedirect(Request $request, $provider)
    {
        // Store context (signin, signup, connect) in session
        $context = $request->query('context', 'signin'); // Default to signin if missing
        session(['social_context' => $context]);

        // Secure Link Flow: If a link_token is provided, verify it and store the user ID in session
        if ($context === 'connect' && $request->has('link_token')) {
            $token = $request->query('link_token');
            $userId = Cache::get("link_token_{$token}");
            
            if ($userId) {
                session(['social_auth_user_id' => $userId]);
                Cache::forget("link_token_{$token}"); // Consume token
            }
        }

        $driver = Socialite::driver($provider)->stateless();

        if ($provider === 'google') {
            $driver->with(['prompt' => 'select_account consent', 'access_type' => 'offline']);
        } else {
             // Attempt to force consent for others
            $driver->with(['prompt' => 'consent']);
        }

        return $driver->redirect();
    }

    /**
     * Generate Link Token for Secure Connection
     */
    public function getLinkToken(Request $request)
    {
        $token = Str::random(40);
        // Cache user ID for 2 minutes
        Cache::put("link_token_{$token}", $request->user()->id, 120);

        return response()->json(['token' => $token]);
    }

    /**
     * Handle Social Provider Callback
     */
    public function socialCallback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/auth/callback?error=' . urlencode($e->getMessage()));
        }

        $context = session('social_context', 'signin'); // Default to strict signin if session lost
        // request()->session()->forget('social_context'); // Optional: Clear it, but typical session lifecycle handles this.

        // ---------------------------------------------------------
        // CASE 1: CONNECT ACCOUNT (User is already logged in)
        // ---------------------------------------------------------
        // Explicitly check context or Auth::check()
        // If context is 'connect', they MUST be logged in.
        if ($context === 'connect' || Auth::check() || session('social_auth_user_id')) {
            
            // Debug Logging: Trace why connection flow might be failing
            \Illuminate\Support\Facades\Log::info('Social Callback Connect Flow:', [
                'context' => $context,
                'auth_check' => Auth::check(),
                'session_user_id' => session('social_auth_user_id'),
                'provider' => $provider
            ]);

            // If strictly unauthenticated but we have a session user ID from the link token
            if (!Auth::check() && session('social_auth_user_id')) {
                Auth::loginUsingId(session('social_auth_user_id'));
            }

            if (!Auth::check()) {
                 return redirect(config('app.frontend_url') . '/signin?error=login_required_to_connect');
            }

            $currentUser = Auth::user();
            
            // Check if this social account is already linked to *any* user
            $existingAccount = \App\Models\SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($existingAccount) {
                if ($existingAccount->user_id === $currentUser->id) {
                    return redirect(config('app.frontend_url') . '/dashboard/settings?error=already_connected');
                } else {
                    return redirect(config('app.frontend_url') . '/dashboard/settings?error=connected_to_other_account');
                }
            }

            // Link the account
            $currentUser->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            return redirect(config('app.frontend_url') . '/dashboard/settings?success=account_connected');
        }

        // ---------------------------------------------------------
        // CASE 2: SOCIAL SIGN IN / SIGN UP (Guest)
        // ---------------------------------------------------------

        // 1. Check if SocialAccount exists (Already Linked)
        $socialAccount = \App\Models\SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            // Account linked -> ALWAYS ALLOW LOGIN
            // (Even if context=signup, we can just log them in, or strictly say "Already registered")
            $user = $socialAccount->user;

            // Auto-verify if not verified (Social provider already verified the email)
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            Auth::login($user);

            $token = $user->createToken('auth_token')->plainTextToken;
            $this->recordLoginHistory($request, $user);

            return redirect(config('app.frontend_url') . '/auth/callback?token=' . $token);
        }

        // 2. Check if User with this email exists (BUT NOT LINKED)
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            // ERROR: Account exists but not linked
            return redirect(config('app.frontend_url') . '/auth/callback?error=account_exists_please_login');
        }

        // 3. HANDLE NEW USERS
        // STRICT CHECK: If context is 'signin', DO NOT register new user.
        if ($context === 'signin') {
             return redirect(config('app.frontend_url') . '/auth/callback?error=account_not_found_please_signup');
        }

        // 4. REGISTER (Only if context == 'signup')
        $nameParts = explode(' ', $socialUser->getName() ?? '', 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'email_verified_at' => now(),
            // Password is null initially
        ]);

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'avatar' => $socialUser->getAvatar(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_at' => isset($socialUser->expiresIn) ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        Auth::login($user);
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->recordLoginHistory($request, $user);

        // Redirect to Set Password page
        return redirect(config('app.frontend_url') . '/auth/callback?token=' . $token . '&action=set_password');
    }

    /**
     * Set Password for Social Users
     */
    public function setPassword(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Security check: Only allow setting password if it's currently null
        // or provide a way to override if we want to allow simple password resets from this endpoint (unlikely for security)
        if ($user->password && !Hash::check('', $user->password)) { // Check if password is not empty string/null effectively
             // If user already has a password, they should use the update-password endpoint which requires current_password
             // However, for this specific flow "set password after social login", we can allow it IF implementation allows.
             // Stricter: Only if password is NULL.
        }
        
        if ($user->password !== null && $user->password !== '') {
             return response()->json(['message' => 'Password already set. Use update password.'], 403);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password set successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Handle Logout Request
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Disconnect Social Account (Revoke Token)
     */
    public function disconnectSocial(Request $request, $provider)
    {
        $user = $request->user();

        $account = $user->socialAccounts()->where('provider', $provider)->first();

        if (!$account) {
            return response()->json(['message' => 'Account not linked.'], 404);
        }

        // 1. Revoke Logic
        try {
            if ($provider === 'google' && $account->token) {
                // Google: Revoke via POST to oauth2.googleapis.com
                Http::post('https://oauth2.googleapis.com/revoke', [
                    'token' => $account->token,
                ]);
            } 
            elseif ($provider === 'github' && $account->token) {
                // GitHub: Revoke via Basic Auth with Client ID/Secret
                // Requires client_id:client_secret base64 encoded
                $clientId = config('services.github.client_id');
                $clientSecret = config('services.github.client_secret');
                
                if ($clientId && $clientSecret) {
                   Http::withBasicAuth($clientId, $clientSecret)
                       ->delete("https://api.github.com/applications/{$clientId}/grant", [
                           'access_token' => $account->token
                       ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but proceed to delete local record
            \Illuminate\Support\Facades\Log::error("Failed to revoke {$provider} token: " . $e->getMessage());
        }

        // 2. Delete Local Record
        $account->delete();

        return response()->json(['message' => 'Account disconnected successfully.']);
    }
}
