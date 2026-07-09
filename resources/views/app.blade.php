<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $seo = config('reton.seo', []);
            $siteName = (string) ($seo['site_name'] ?? config('app.name', 'Reton'));
            $title = (string) ($seo['title'] ?? $siteName);
            $description = (string) ($seo['description'] ?? '');
            $keywords = (string) ($seo['keywords'] ?? '');
            $robots = (string) ($seo['robots'] ?? 'index,follow');
            $locale = (string) ($seo['locale'] ?? 'en_NG');
            $ogImagePath = (string) ($seo['og_image'] ?? '/og-banner.svg');
            $ogImage = str_starts_with($ogImagePath, 'http') ? $ogImagePath : url($ogImagePath);
            $canonical = url()->current();
            $twitterSite = (string) ($seo['twitter_site'] ?? '');
            $googleVerification = (string) ($seo['google_site_verification'] ?? '');
        @endphp

        <title inertia>{{ $title }}</title>

        <meta name="description" content="{{ $description }}">
        @if ($keywords !== '')
            <meta name="keywords" content="{{ $keywords }}">
        @endif
        <meta name="robots" content="{{ $robots }}">
        <meta name="author" content="{{ $siteName }}">
        <meta name="theme-color" content="#0b7a57">
        <meta name="application-name" content="{{ $siteName }}">
        <meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <link rel="canonical" href="{{ $canonical }}">

        @if ($googleVerification !== '')
            <meta name="google-site-verification" content="{{ $googleVerification }}">
        @endif

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ $locale }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $siteName }} — {{ $description }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        @if ($twitterSite !== '')
            <meta name="twitter:site" content="{{ $twitterSite }}">
        @endif
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        {{-- JSON-LD --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => $siteName,
                'url' => config('reton.links.public_base') ?: config('app.url'),
                'description' => $description,
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'Web',
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'NGN'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>

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
