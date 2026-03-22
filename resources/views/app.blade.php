<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('app.manifest') }}">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head>
<body>
    <div id="app" class="flex">
        <nav id="app-sidebar">
            @if(auth()->check() && auth()->user()->hasRole('admin'))
                <a href="/admin" id="admin-nav-link">Admin</a>
            @endif
        </nav>
        <main>
            @yield('content')
        </main>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/serviceworker.js');
            });
        }
    </script>
</body>
</html>
