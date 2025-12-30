<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
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
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
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

        // Handle Email Change Logic
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $pendingEmail = $validated['email'];
            
            // Basic check to avoid duplication with other pending_emails if necessary, 
            // but unique:users,email already covers the main one.
            
            $user->pending_email = $pendingEmail;
            $user->save();

            // Send notification to the NEW email
            $user->notify(new \App\Notifications\PendingEmailVerificationNotification);
            
            // Remove email from validated so it doesn't update the primary email yet
            unset($validated['email']);
        }

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
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');
        
        $extension = $file->getClientOriginalExtension();
        
        // Requirement 1: URL avatar yang sedang digunakan adalah clean uuid
        // Output: avatars/{user-uuid}.{ext}
        $newFilename = "{$user->id}.{$extension}";
        $newPath = "avatars/{$newFilename}";

        // Requirement 2: Jika ganti, pindahkan lama ke trash/{user-uuid}-{trash-random}-{original-filename}
        if ($user->avatar) {
            $oldPath = $this->getRelativePath($user->avatar);
            
            if ($oldPath) {
                // If it's on R2, move it to trash
                if (Storage::disk('r2')->exists($oldPath)) {
                    $trashRandom = Str::random(10);
                    $oldBasename = basename($oldPath);
                    $trashPath = "trash/{$user->id}-{$trashRandom}-{$oldBasename}";
                    
                    // S3/R2 copy + delete is more reliable than move in some environments
                    Storage::disk('r2')->copy($oldPath, $trashPath);
                    Storage::disk('r2')->delete($oldPath);
                } 
                // If it's still on local storage (migration case), just delete it
                elseif (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }

        // Upload to R2
        // Upload to R2 with Cache-Control to prevent long caching
        $path = $file->storeAs('avatars', $newFilename, [
            'disk' => 'r2',
            'CacheControl' => 'no-cache, no-store, max-age=0, must-revalidate',
        ]);
        $url = Storage::disk('r2')->url($path);

        $user->update(['avatar' => $url]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'avatar_url' => $url,
        ]);
    }

    /**
     * Helper to extract relative path from full URL
     */
    private function getRelativePath($url)
    {
        if (!$url) return null;

        $baseUrl = config('filesystems.disks.r2.url');
        
        // Strip query string if any before processing
        $urlWithoutQuery = explode('?', $url)[0];
        
        if (str_starts_with($urlWithoutQuery, $baseUrl)) {
            return ltrim(str_replace($baseUrl, '', $urlWithoutQuery), '/');
        }
        
        // Handle legacy local storage URLs if any
        if (str_contains($urlWithoutQuery, '/storage/')) {
            return 'avatars/' . basename($urlWithoutQuery);
        }
        
        return $urlWithoutQuery;
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
        if ($user->avatar) {
             $oldPath = $this->getRelativePath($user->avatar);
             if ($oldPath && Storage::disk('r2')->exists($oldPath)) {
                 $trashRandom = Str::random(10);
                 $oldBasename = basename($oldPath);
                 $trashPath = "trash/deleted_user_{$user->id}-{$trashRandom}-{$oldBasename}";
                 Storage::disk('r2')->move($oldPath, $trashPath);
             }
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
