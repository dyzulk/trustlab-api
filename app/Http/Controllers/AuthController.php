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

class AuthController extends Controller
{
    /**
     * Handle Login Request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Check for social-only users
        if ($user && !$user->password) {
            throw ValidationException::withMessages([
                'email' => ['This account uses social login. Please sign in with Google.'],
            ]);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->recordLoginHistory($request, $user);

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

        return response()->json([
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Redirect to Social Provider
     */
    public function socialRedirect($provider)
    {
        return response()->json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * Handle Social Provider Callback
     */
    public function socialCallback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL') . '/auth/callback?error=authentication_failed');
        }

        $nameParts = explode(' ', $socialUser->getName() ?? '', 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $user = User::updateOrCreate([
            'email' => $socialUser->getEmail(),
        ], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            $provider . '_id' => $socialUser->getId(),
            $provider . '_token' => $socialUser->token,
            $provider . '_refresh_token' => $socialUser->refreshToken,
            'avatar' => $socialUser->getAvatar(),
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->recordLoginHistory($request, $user);

        return redirect(env('FRONTEND_URL') . '/auth/callback?token=' . $token);
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
     * Record Login History
     */
    private function recordLoginHistory(Request $request, $user)
    {
        $userAgent = $request->header('User-Agent');
        $ip = $request->ip();

        // For local development testing (if IP is local, use a real one for fallback)
        $lookupIp = ($ip === '127.0.0.1' || $ip === '::1') ? '8.8.8.8' : $ip;
        $location = $this->getLocationFromIp($lookupIp);

        $info = $this->parseUserAgent($userAgent);

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device_type' => $info['device'],
            'os' => $info['os'],
            'browser' => $info['browser'],
            'city' => $location['city'],
            'country' => $location['country'],
            'country_code' => $location['country_code'],
        ]);
    }

    /**
     * Get Location from IP
     */
    private function getLocationFromIp($ip)
    {
        try {
            $response = Http::get("http://ip-api.com/json/{$ip}")->json();
            
            if ($response && $response['status'] === 'success') {
                return [
                    'city' => $response['city'] ?? 'Unknown City',
                    'country' => $response['country'] ?? 'Unknown Country',
                    'country_code' => $response['countryCode'] ?? 'UN',
                ];
            }
        } catch (\Exception $e) {
            // Fallback silently
        }

        return [
            'city' => 'Unknown City',
            'country' => 'Unknown Country',
            'country_code' => 'UN',
        ];
    }

    /**
     * Simple User Agent Parser
     */
    private function parseUserAgent($agent)
    {
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';
        $device = 'Desktop';

        // OS Parsing
        if (preg_match('/iphone|ipad|ipod/i', $agent)) {
            $os = 'iOS';
            $device = 'iOS';
        } elseif (preg_match('/android/i', $agent)) {
            $os = 'Android';
            $device = 'Android';
        } elseif (preg_match('/windows/i', $agent)) {
            $os = 'Windows';
            $device = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $agent)) {
            $os = 'Mac';
            $device = 'Mac';
        } elseif (preg_match('/linux/i', $agent)) {
            $os = 'Linux';
            $device = 'Linux';
        }

        // Browser Parsing
        if (preg_match('/msie/i', $agent) && !preg_match('/opera/i', $agent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/firefox/i', $agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/chrome/i', $agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $agent)) {
            $browser = 'Safari';
        } elseif (preg_match('/opera/i', $agent)) {
            $browser = 'Opera';
        } elseif (preg_match('/netscape/i', $agent)) {
            $browser = 'Netscape';
        }

        // More specific
        if ($os === 'iOS' && $browser === 'Safari') $browser = 'iOS Safari';
        if ($os === 'iOS' && $browser === 'Chrome') $browser = 'iOS Chrome';
        if ($os === 'Android' && $browser === 'Chrome') $browser = 'Android Chrome';
        if ($os === 'Android' && $browser === 'Firefox') $browser = 'Android Firefox';

        return [
            'os' => $os,
            'browser' => $browser,
            'device' => $device
        ];
    }
}
