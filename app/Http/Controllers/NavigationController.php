<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NavigationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $menuGroups = [];

        // 1. Admin Management (Admin or Owner)
        if ($user && $user->isAdminOrOwner()) {
            $menuGroups[] = [
                'title' => 'Admin Management',
                'items' => [
                    [
                        'name' => 'User Management',
                        'icon' => 'users',
                        'route' => '/dashboard/admin/users',
                    ],
                    [
                        'name' => 'Root CA Management',
                        'icon' => 'certificate',
                        'route' => '/dashboard/admin/root-ca',
                    ],
                    [
                        'name' => 'Ticket Management',
                        'icon' => 'support-ticket',
                        'route' => '/dashboard/admin/tickets',
                    ],
                    [
                        'name' => 'Legal Page Management',
                        'icon' => 'pages',
                        'route' => '/dashboard/admin/legal',
                    ],
                    [
                        'name' => 'Inquiries',
                        'icon' => 'inbox',
                        'route' => '/dashboard/admin/inquiries',
                    ],
                    [
                        'name' => 'SMTP Tester',
                        'icon' => 'smtp',
                        'route' => '/dashboard/admin/smtp-tester',
                    ],
                ]
            ];
        }

        // 2. Main Menu (Common)
        $mainItems = [
            [
                'name' => 'Dashboard',
                'icon' => 'dashboard',
                'route' => '/dashboard',
            ],
            [
                'name' => 'Certificates',
                'icon' => 'certificate',
                'route' => '/dashboard/certificates',
            ],
            [
                'name' => 'API Keys',
                'icon' => 'api-key',
                'route' => '/dashboard/api-keys',
            ],
            [
                'name' => 'Support Tickets',
                'icon' => 'support-ticket',
                'route' => '/dashboard/support', // Assuming support.index maps to /support
            ],
        ];

        // "My Services" for Customers ONLY
        if ($user && $user->role === \App\Models\User::ROLE_CUSTOMER) {
             // We can insert "My Services" if we want to keep that feature for customers
             // As per user request "ikuiti app-beta", but we also added "My Services" previously.
             // Let's keep "My Services" as it's a nice dedicated page for them, inserting it after Dashboard.
             array_splice($mainItems, 1, 0, [[
                'name' => 'My Services',
                'icon' => 'layers', 
                'route' => '/dashboard/services',
             ]]);
        }

        $menuGroups[] = [
            'title' => 'Menu',
            'items' => $mainItems,
        ];

        // 3. My Account (Common)
        $menuGroups[] = [
            'title' => 'My Account',
            'items' => [
                [
                    'name' => 'User Profile',
                    'icon' => 'user-profile',
                    'route' => '/dashboard/profile',
                ],
                [
                    'name' => 'Account Settings',
                    'icon' => 'settings',
                    'route' => '/dashboard/settings',
                ],
            ]
        ];

        return response()->json($menuGroups);
    }

    public function debug()
    {
        // Simulate a User instance for admin view
        $user = new \App\Models\User(['first_name' => 'Debug', 'last_name' => 'Admin', 'role' => 'admin']);
        
        // This is a bit of a hack since $user->isAdmin() might be a real method, 
        // but for JSON structure debugging, we'll just replicate the logic or mock it.
        
        $menuGroups = [];

        // 1. Admin Management (Simulated Admin)
        $menuGroups[] = [
            'title' => 'Admin Management',
            'items' => [
                ['name' => 'User Management', 'icon' => 'users', 'route' => '/admin/users'],
                ['name' => 'Root CA Management', 'icon' => 'certificate', 'route' => '/admin/root-ca'],
                ['name' => 'Ticket Management', 'icon' => 'support-ticket', 'route' => '/admin/tickets'],
                ['name' => 'Legal Page Management', 'icon' => 'pages', 'route' => '/dashboard/admin/legal'],
                ['name' => 'Inquiries', 'icon' => 'inbox', 'route' => '/dashboard/admin/inquiries'],
                ['name' => 'SMTP Tester', 'icon' => 'smtp', 'route' => '/dashboard/admin/smtp-tester'],
            ]
        ];

        // 2. Main Menu
        $mainItems = [
            ['name' => 'Dashboard', 'icon' => 'dashboard', 'route' => '/dashboard'],
            ['name' => 'Certificates', 'icon' => 'certificate', 'route' => '/dashboard/certificates'],
            ['name' => 'API Keys', 'icon' => 'api-key', 'route' => '/dashboard/api-keys'],
            ['name' => 'Support Tickets', 'icon' => 'support-ticket', 'route' => '/dashboard/support'],
        ];

        $menuGroups[] = [
            'title' => 'Menu',
            'items' => $mainItems,
        ];

        // 3. My Account
        $menuGroups[] = [
            'title' => 'My Account',
            'items' => [
                ['name' => 'User Profile', 'icon' => 'user-profile', 'route' => '/dashboard/profile'],
                ['name' => 'Account Settings', 'icon' => 'settings', 'route' => '/dashboard/settings'],
            ]
        ];

        return response()->json($menuGroups);
    }
}
