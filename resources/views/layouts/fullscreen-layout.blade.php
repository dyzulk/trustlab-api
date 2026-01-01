<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Unified Management' }} | {{ config('app.name', 'DyDev Admin') }}</title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="@yield('meta_description', 'Professional Certificate Authority and API Management System for modern developers. Issue SSL/TLS certificates and manage API keys with ease.')">
    <meta name="keywords" content="@yield('meta_keywords', 'certificate authority, ssl manager, api key management, trustlab, security portal')">
    <meta name="robots" content="@yield('robots', 'noindex, nofollow, noarchive')">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? 'Unified Management' }} | {{ config('app.name', 'DyDev Admin') }}">
    <meta property="og:description" content="@yield('meta_description', 'Professional Certificate Authority and API Management System for modern developers. Issue SSL/TLS certificates and manage API keys with ease.')">
    <meta property="og:image" content="{{ asset('images/og-share.png') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="{{ $title ?? 'Unified Management' }} | {{ config('app.name', 'DyDev Admin') }}">
    <meta property="twitter:description" content="@yield('meta_description', 'Professional Certificate Authority and API Management System for modern developers. Issue SSL/TLS certificates and manage API keys with ease.')">
    <meta property="twitter:image" content="{{ asset('images/og-share.png') }}">

    @yield('meta')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                        'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                // Initialize based on screen size
                isExpanded: window.innerWidth >= 1280, // true for desktop, false for mobile
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            
            // Add to html immediately (works in <head>)
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            // Add to body after it's available
            document.addEventListener('DOMContentLoaded', () => {
                if (theme === 'dark') {
                    document.body.classList.add('dark', 'bg-gray-900');
                } else {
                    document.body.classList.remove('dark', 'bg-gray-900');
                }
            });
        })();
    </script>
</head>

<body x-data="{ 'loaded': true}" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
const checkMobile = () => {
    if (window.innerWidth < 1280) {
        $store.sidebar.setMobileOpen(false);
        $store.sidebar.isExpanded = false;
    } else {
        $store.sidebar.isMobileOpen = false;
        $store.sidebar.isExpanded = true;
    }
};
window.addEventListener('resize', checkMobile);">

    {{-- Flash Message Component --}}
    <x-ui.flash-message />

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    @yield('content')

</body>

@stack('scripts')

</html>
