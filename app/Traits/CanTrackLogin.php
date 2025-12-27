<?php

namespace App\Traits;

use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait CanTrackLogin
{
    /**
     * Record Login History
     */
    protected function recordLoginHistory(Request $request, $user)
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
    protected function getLocationFromIp($ip)
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
     * Helper to parse User Agent
     */
    protected function parseUserAgent($agent)
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
