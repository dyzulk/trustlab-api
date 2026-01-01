@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $title = 'System Status';
        $currentYear = date('Y');
    @endphp
    <div class="relative flex flex-col items-center justify-center min-h-screen p-6 overflow-hidden z-1">
        <x-common.common-grid-shape />
        
        <div class="mx-auto w-full max-w-[242px] text-center sm:max-w-[472px]">
            <h1 class="mb-4 text-3xl font-bold text-gray-800 dark:text-white/90">
                {{ config('app.name') }} API
            </h1>
            
            <div class="inline-flex items-center gap-2 px-3 py-1 mb-8 text-sm font-medium text-green-700 bg-green-100 rounded-full dark:bg-green-500/10 dark:text-green-400">
                <span class="relative flex w-2 h-2">
                  <span class="absolute inline-flex w-full h-full bg-green-400 rounded-full opacity-75 animate-ping"></span>
                  <span class="relative inline-flex w-2 h-2 bg-green-500 rounded-full"></span>
                </span>
                All Systems Operational
            </div>

            <p class="mb-8 text-base text-gray-700 dark:text-gray-400 sm:text-lg">
                The API service is running normally.<br>
                For the user interface, please visit our main application.
            </p>

            <a href="{{ config('app.frontend_url') }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                Go to TrustLab Web
            </a>
        </div>
        
        <p class="absolute text-sm text-center text-gray-500 -translate-x-1/2 bottom-6 left-1/2 dark:text-gray-400">
            &copy; 2025 - {{ $currentYear }} {{ config('app.name') }} All Rights Reserved
        </p>
    </div>
@endsection
