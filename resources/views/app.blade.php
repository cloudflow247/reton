<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Reton') }}</title>

        @php($description = 'Reton — the trust-first African wallet with an undo button for money. Callback-protected transfers, wrong-transfer recovery and real-time fraud checks, settled on ALAT by Wema.')
        <meta name="description" content="{{ $description }}">
        <meta name="theme-color" content="#0b7a57">
        <meta name="application-name" content="Reton">
        <meta name="apple-mobile-web-app-title" content="Reton">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        {{-- Social / link previews --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Reton">
        <meta property="og:title" content="Reton — payments you can take back">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:image" content="{{ url('/shield.svg') }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="Reton — payments you can take back">
        <meta name="twitter:description" content="{{ $description }}">

        <link rel="icon" type="image/svg+xml" href="/shield.svg">
        <link rel="apple-touch-icon" href="/shield.svg">
        <link rel="manifest" href="/site.webmanifest">

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="h-full font-sans antialiased">
        @inertia
    </body>
</html>
