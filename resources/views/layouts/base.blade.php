<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@hasSection('title')@yield('title') | @endif{{ setting('company_name') }}</title>
    @if (setting('logo_path'))
        <link rel="icon" href="{{ asset(setting('logo_path')) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body_class', 'min-h-screen antialiased')">
    @yield('body')
    @stack('scripts')
</body>
</html>
