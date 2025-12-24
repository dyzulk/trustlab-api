<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Certificate;
use App\Models\Ticket;
use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Helper to calculate percentage change
        $getTrend = function($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        // Basic Stats
        $currentMonth = now()->startOfMonth();
        $previousMonth = now()->subMonth()->startOfMonth();
        
        // Certificates
        $totalCertificates = Certificate::count();
        $prevCertificates = Certificate::where('created_at', '<', $currentMonth)->count(); // Simplified for total growth
        
        // Active Certificates
        $activeCertificates = Certificate::where('status', 'ISSUED')->where('valid_to', '>', now())->count();
        $prevActiveCertificates = Certificate::where('status', 'ISSUED')->where('valid_to', '>', now()->subMonth())->where('created_at', '<', $currentMonth)->count();

        // Expired
        $expiredCertificates = Certificate::where('valid_to', '<', now())->count();
        
        // Tickets
        $activeTickets = Ticket::whereIn('status', ['open', 'answered'])->count();
        $prevActiveTickets = Ticket::whereIn('status', ['open', 'answered'])->where('created_at', '<', $currentMonth)->count();

        $stats = [
            'total_certificates' => [
                'value' => $totalCertificates,
                'trend' => $getTrend($totalCertificates, $prevCertificates),
                'trend_label' => 'vs last month'
            ],
            'active_certificates' => [
                'value' => $activeCertificates,
                'trend' => $getTrend($activeCertificates, $prevActiveCertificates),
                'trend_label' => 'vs last month'
            ],
            'expired_certificates' => [
                'value' => $expiredCertificates,
                'trend' => 0, // Hard to calculate meaningful trend for expired without accumulation history
                'trend_label' => 'vs last month'
            ],
            'active_tickets' => [
                'value' => $activeTickets,
                'trend' => $getTrend($activeTickets, $prevActiveTickets),
                'trend_label' => 'vs last month'
            ],
        ];

        // Admin only stats
        if ($user->isAdmin()) {
            $totalUsers = User::count();
            $prevUsers = User::where('created_at', '<', $currentMonth)->count();

            $stats['total_users'] = [
                'value' => $totalUsers,
                'trend' => $getTrend($totalUsers, $prevUsers),
                'trend_label' => 'vs last month'
            ];
            
            // Inquiries - trend calculation for "Pending" is hard, so we just wrap value to keep consistent structure
            $stats['pending_inquiries'] = [
                'value' => Inquiry::where('status', 'unread')->count(),
                // No trend for now, frontend will handle this with 'footer' or similar if needed
            ];
            
            $stats['recent_users'] = User::latest()->take(5)->get(['id', 'name', 'email', 'created_at']);
        }

        // Recent Activity (Mocked for now or from logs if available)
        // Ideally checking `authentication_log` if available or similar
        $recentActivity = [];
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'server_time' => now()->toIso8601String(),
            ]
        ]);
    }

    public function ping()
    {
        return response()->json([
            'pong' => true,
            'time' => microtime(true),
        ]);
    }
}
