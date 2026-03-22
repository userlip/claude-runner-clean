<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="{{ route('workbench.manifest') }}">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/css/filament/chat.css', 'resources/js/app.js'])

</head>
<body class="h-screen bg-base-200 overscroll-none overflow-hidden">

    <x-nav sticky full-width>
        <x-slot:brand>
            <label for="main-drawer" class="mr-3 cursor-pointer lg:hidden">
                <x-icon name="o-bars-3" class="h-6 w-6" />
            </label>
            <a href="/" class="font-bold text-lg">{{ config('app.name') }}</a>
        </x-slot:brand>
        <x-slot:actions>
            <x-theme-toggle />
        </x-slot:actions>
    </x-nav>

    <x-toast />
    <x-main full-width with-nav>
        <x-slot:sidebar drawer="main-drawer" class="bg-base-100">
            @auth
                <x-menu activate-by-route>
                    <x-menu-item title="Tasks" icon="o-clipboard-document-list" :link="route('workbench.tasks.index')" />
                    <x-menu-item title="Repositories" icon="o-code-bracket" :link="route('workbench.repositories.index')" />
                    <x-menu-item title="Personas" icon="o-user-circle" :link="route('workbench.personas.index')" />
                    <x-menu-item title="Playbooks" icon="o-book-open" :link="route('workbench.playbooks.index')" />
                    <x-menu-item title="Snippets" icon="o-code-bracket-square" :link="route('workbench.snippets.index')" />
                    <x-menu-item title="Proposals" icon="o-document-text" :link="route('workbench.proposals.index')" />
                    <x-menu-item title="Sites" icon="o-globe-alt" :link="route('workbench.sites.index')" />
                    <x-menu-item title="Schedules" icon="o-clock" :link="route('workbench.schedules.index')" />
                    <x-menu-item title="Analytics" icon="o-chart-bar" :link="route('workbench.analytics.index')" />
                    <x-menu-item title="Settings" icon="o-adjustments-horizontal" :link="route('workbench.settings.index')" />

                    @if(auth()->user()->hasRole('admin'))
                        <x-menu-separator />
                        <x-menu-item title="Users" icon="o-users" :link="route('workbench.users.index')" />
                        <x-menu-item title="Scrapp APIs" icon="o-bolt" :link="route('workbench.scrapp-apis.index')" />
                        <x-menu-item title="Admin" icon="o-cog-6-tooth" link="/admin" no-wire-navigate />
                    @endif
                </x-menu>

                <div class="mt-auto border-t border-base-300 pt-2">
                    <livewire:recent-chats />
                </div>
            @endauth
        </x-slot:sidebar>

        <x-slot:content class="!p-0 !overflow-hidden">
            {{ $slot }}
        </x-slot:content>
    </x-main>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/serviceworker.js');
            });
        }
    </script>

</body>
</html>
