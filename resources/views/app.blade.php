<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('app.manifest') }}">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @livewireStyles
</head>
<body>
    <div id="app" class="flex">
        <nav id="app-sidebar">
            @auth
                <ul id="app-nav-items">
                    <li><a href="{{ route('app.tasks.index') }}">Tasks</a></li>
                    <li><a href="{{ route('app.repositories.index') }}">Repositories</a></li>
                    <li><a href="{{ route('app.personas.index') }}">Personas</a></li>
                    <li><a href="{{ route('app.playbooks.index') }}">Playbooks</a></li>
                    <li><a href="{{ route('app.snippets.index') }}">Snippets</a></li>
                    <li><a href="{{ route('app.proposals.index') }}">Proposals</a></li>
                    <li><a href="{{ route('app.sites.index') }}">Sites</a></li>
                    <li><a href="{{ route('app.schedules.index') }}">Schedules</a></li>
                </ul>
                @if(auth()->user()->hasRole('admin'))
                    <a href="/admin" id="admin-nav-link">Admin</a>
                @endif
                <livewire:recent-chats />
            @endauth
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
    @livewireScripts
</body>
</html>
