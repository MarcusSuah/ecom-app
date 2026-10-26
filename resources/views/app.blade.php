<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            {{-- Without SSR, crawlers still get the page's search and link-preview tags (see App\Support\PageMeta). --}}
            @php($meta = $page['props']['meta'] ?? null)
            @php($siteName = $page['props']['name'] ?? config('app.name'))
            <title>{{ ($meta['title'] ?? null) ? $meta['title'].' - '.$siteName : $siteName }}</title>
            @if ($meta)
                <meta data-inertia="description" name="description" content="{{ $meta['description'] }}">
                <link data-inertia="canonical" rel="canonical" href="{{ $meta['url'] }}">
                <meta data-inertia="og:site_name" property="og:site_name" content="{{ $siteName }}">
                <meta data-inertia="og:title" property="og:title" content="{{ $meta['title'] ?? $siteName }}">
                <meta data-inertia="og:description" property="og:description" content="{{ $meta['description'] }}">
                <meta data-inertia="og:type" property="og:type" content="{{ $meta['type'] }}">
                <meta data-inertia="og:url" property="og:url" content="{{ $meta['url'] }}">
                @if ($meta['image'])
                    <meta data-inertia="og:image" property="og:image" content="{{ $meta['image'] }}">
                @endif
                <meta data-inertia="twitter:card" name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
