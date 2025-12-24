<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json([
            [
                'id' => 1,
                'name' => 'SSL Certificate - Standard',
                'status' => 'Active',
                'expiry' => '2026-12-23',
                'domain' => 'example.com'
            ],
            [
                'id' => 2,
                'name' => 'Code Signing - Pro',
                'status' => 'Pending',
                'expiry' => 'N/A',
                'domain' => 'N/A'
            ],
            [
                'id' => 3,
                'name' => 'Wildcard SSL',
                'status' => 'Expired',
                'expiry' => '2025-01-10',
                'domain' => '*.web.dev'
            ]
        ]);
    }
}
