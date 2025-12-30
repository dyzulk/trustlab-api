<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Certificate;
use App\Models\Ticket;
use App\Models\Inquiry;
use App\Models\ActivityLog;
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
        
        // Certificates (Scoped to User)
        $totalCertificates = Certificate::where('user_id', $user->id)->count();
        $prevCertificates = Certificate::where('user_id', $user->id)->where('created_at', '<', $currentMonth)->count();
        
        // Active Certificates (Scoped to User)
        $activeCertificates = Certificate::where('user_id', $user->id)->where('status', 'ISSUED')->where('valid_to', '>', now())->count();
        $prevActiveCertificates = Certificate::where('user_id', $user->id)->where('status', 'ISSUED')->where('valid_to', '>', now()->subMonth())->where('created_at', '<', $currentMonth)->count();

        // Expired (Scoped to User)
        $expiredCertificates = Certificate::where('user_id', $user->id)->where('valid_to', '<', now())->count();
        
        // Tickets (Role Based)
        $ticketQuery = Ticket::query()->whereIn('status', ['open', 'answered']);
        if (!$user->isAdminOrOwner()) {
            $ticketQuery->where('user_id', $user->id);
        }
        $activeTickets = $ticketQuery->count();

        // Previous Tickets (Role Based)
        $prevTicketQuery = Ticket::query()->whereIn('status', ['open', 'answered'])->where('created_at', '<', $currentMonth);
         if (!$user->isAdminOrOwner()) {
            $prevTicketQuery->where('user_id', $user->id);
        }
        $prevActiveTickets = $prevTicketQuery->count();

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
                'trend' => 0, 
                'trend_label' => 'vs last month'
            ],
            'active_tickets' => [
                'value' => $activeTickets,
                'trend' => $getTrend($activeTickets, $prevActiveTickets),
                'trend_label' => 'vs last month'
            ],
        ];

        // Admin only stats
        if ($user->isAdminOrOwner()) {
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
            ];

            // CA Certificate Downloads
            $caDownloads = \App\Models\CaCertificate::select('ca_type', 'download_count')->get();
            foreach ($caDownloads as $ca) {
                $stats['ca_downloads_' . $ca->ca_type] = [
                    'value' => $ca->download_count ?? 0,
                    'label' => str_replace('_', ' ', strtoupper($ca->ca_type)) . ' Downloads'
                ];
            }
            
            $stats['recent_users'] = User::latest()->take(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']);
        }

        // Recent Activity
        $activityLogQuery = ActivityLog::with('user:id,first_name,last_name,email,avatar')
            ->latest()
            ->take(10);
            
        if (!$user->isAdminOrOwner()) {
            $activityLogQuery->where('user_id', $user->id);
        }
        
        $recentActivity = $activityLogQuery->get()->map(function($log) {
            return [
                'id' => $log->id,
                'user_name' => $log->user ? $log->user->first_name . ' ' . $log->user->last_name : 'System',
                'user_avatar' => $log->user ? $log->user->avatar : null,
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        // Chart Data (Certificate Issuance Trend - Last 7 Days)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $countQuery = Certificate::whereDate('created_at', $date);
            if (!$user->isAdminOrOwner()) {
                $countQuery->where('user_id', $user->id);
            }
            
            $chartData[] = [
                'date' => $date,
                'day' => now()->subDays($i)->format('D'),
                'count' => $countQuery->count()
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'chart_data' => $chartData,
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
