<!DOCTYPE html>
@php
    $locale = str_replace('_', '-', app()->getLocale());
    $rtlLocales = ['ar', 'he', 'fa', 'ur'];
    $dir = in_array(substr($locale, 0, 2), $rtlLocales, true) ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $locale }}" dir="{{ $dir }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#C49A45">
        <meta name="msapplication-TileColor" content="#C49A45">
        <meta name="msapplication-navbutton-color" content="#C49A45">
        <link rel="manifest" href="/manifest.webmanifest">

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

        <link rel="icon" href="/favicon.svg?v=sis8" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico?v=sis8" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=sis8">
        <link rel="manifest" href="/manifest.webmanifest">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>Student Information System</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
