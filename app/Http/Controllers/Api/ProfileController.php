<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'job_title' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'city_state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'facebook' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'settings_email_alerts' => 'sometimes|boolean',
            'settings_certificate_renewal' => 'sometimes|boolean',
            'default_landing_page' => 'sometimes|string|max:255',
            'theme' => 'sometimes|string|in:light,dark,system',
            'language' => 'sometimes|string|max:10',
        ]);

        // Sanitize social links to store only usernames
        if (isset($validated['facebook'])) $validated['facebook'] = $this->extractUsername($validated['facebook'], 'facebook.com');
        if (isset($validated['twitter'])) $validated['twitter'] = $this->extractUsername($validated['twitter'], ['twitter.com', 'x.com']);
        if (isset($validated['linkedin'])) $validated['linkedin'] = $this->extractUsername($validated['linkedin'], 'linkedin.com/in');
        if (isset($validated['instagram'])) $validated['instagram'] = $this->extractUsername($validated['instagram'], 'instagram.com');

        // Log the update attempt for debugging
        \Illuminate\Support\Facades\Log::info('Profile Update Request:', ['user_id' => $user->id, 'payload' => $validated]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            // Delete old avatar if it exists in storage
            $oldPath = str_replace(url('/storage/'), '', $user->avatar);
            Storage::disk('public')->delete($oldPath);
        }

        $filename = 'avatars/' . $file->hashName();

        // Compression Logic if file > 2MB
        if ($file->getSize() > 2 * 1024 * 1024) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file);

            // Downscale if resolution is unnecessarily high
            if ($image->width() > 1200 || $image->height() > 1200) {
                $image->scale(width: 1200, height: 1200);
            }

            // Encode appropriately
            $isPng = $file->getClientOriginalExtension() === 'png' || $file->getMimeType() === 'image/png';
            
            if ($isPng) {
                // Re-encode as PNG to maintain transparency
                // PNG is lossless, so reduction comes primarily from downscaling
                $encoded = $image->toPng();
            } else {
                // JPEG compression
                $encoded = $image->toJpeg(75);
            }

            Storage::disk('public')->put($filename, (string) $encoded);
        } else {
            // Direct store if under 2MB
            $path = $file->store('avatars', 'public');
            $filename = $path;
        }

        $url = Storage::disk('public')->url($filename);
        $user->update(['avatar' => $url]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'avatar_url' => $url,
        ]);
    }

    /**
     * Get Login History (Last 1 month, max 10)
     */
    public function getLoginHistory(Request $request)
    {
        $history = $request->user()->loginHistories()
            ->where('created_at', '>=', now()->subMonth())
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($history);
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Optional: Perform any cleanup here (delete avatar, certificates, etc.)
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
             $oldPath = str_replace(url('/storage/'), '', $user->avatar);
             Storage::disk('public')->delete($oldPath);
        }

        // Revoke Social Tokens
        foreach ($user->socialAccounts as $account) {
            // Re-use logic or call external helper? For simplicity/speed, implementing inline revocation or calling AuthController logic if possible.
            // Better: Iterate and manually revoke to ensure clean slate.
            try {
                if ($account->provider === 'google' && $account->token) {
                    \Illuminate\Support\Facades\Http::post('https://oauth2.googleapis.com/revoke', ['token' => $account->token]);
                }
                // GitHub revocation is more complex inline without config access handy, but we attempt basic cleanup
            } catch (\Exception $e) {
                // Continue deletion
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }

    /**
     * Get active sessions from database
     */
    public function getActiveSessions(Request $request)
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(function ($session) use ($request) {
                $info = $this->parseUserAgent($session->user_agent);
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'browser' => $info['browser'],
                    'os' => $info['os'],
                    'device_type' => $info['device'],
                    'last_active' => $session->last_activity,
                    'is_current' => $session->id === $request->session()->getId(),
                ];
            });

        return response()->json($sessions);
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(Request $request, $id)
    {
        // Don't allow revoking current session via this endpoint for safety
        if ($id === $request->session()->getId()) {
             return response()->json(['message' => 'Cannot revoke current session.'], 400);
        }

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Session revoked successfully.']);
    }

    /**
     * Simple User Agent Parser (Copied from AuthController for consistency)
     */
    private function parseUserAgent($agent)
    {
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';
        $device = 'Desktop';

        if (!$agent) return ['os' => $os, 'browser' => $browser, 'device' => $device];

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
        }

        return [
            'os' => $os,
            'browser' => $browser,
            'device' => $device
        ];
    }

    /**
     * Helper to extract username from social media URLs.
     */
    private function extractUsername(?string $value, $domains): ?string
    {
        if (!$value) return null;

        $value = trim($value);
        if (empty($value)) return null;

        // If it doesn't look like a URL, assume it's already a username
        if (!str_contains($value, '/') && !str_contains($value, '.')) {
            return ltrim($value, '@');
        }

        $domains = (array) $domains;
        foreach ($domains as $domain) {
            // Clean domain for regex (e.g. linkedin.com/in)
            $safeDomain = str_replace('/', '\/', preg_quote($domain));
            $pattern = "/(?:https?:\/\/)?(?:www\.)?{$safeDomain}\/([^\/\?#]+)/i";
            
            if (preg_match($pattern, $value, $matches)) {
                return $matches[1];
            }
        }

        // If it's a URL but doesn't match the expected domain, just return the last part or the value itself
        // to avoid losing data if the user inputs something slightly different
        $parts = explode('/', rtrim($value, '/'));
        return end($parts);
    }
}
