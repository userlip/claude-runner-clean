<x-filament::section :aside="true" heading="Push Notifications" description="Receive browser notifications when tasks complete.">
    <div class="space-y-4" x-data="pushNotifications(@js(config('services.vapid.public_key')))">
        <!-- Browser Support Check -->
        <template x-if="!browserSupported">
            <div class="rounded-lg bg-warning-50 dark:bg-warning-500/10 p-4 text-warning-700 dark:text-warning-400">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                    <span>Push notifications are not supported in this browser.</span>
                </div>
            </div>
        </template>

        <template x-if="browserSupported">
            <div class="space-y-4">
                <!-- Permission Status -->
                <template x-if="permissionDenied">
                    <div class="rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-danger-700 dark:text-danger-400">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-x-circle class="w-5 h-5" />
                            <span>Notification permission was denied. Please enable it in your browser settings.</span>
                        </div>
                    </div>
                </template>

                <!-- Toggle -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">Enable Push Notifications</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Get notified when Claude Code tasks complete or fail.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="enabled"
                        x-on:click="toggle"
                        :class="enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading || permissionDenied"
                    >
                        <span
                            :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                            class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        >
                            <span
                                :class="enabled ? 'opacity-0 duration-100 ease-out' : 'opacity-100 duration-200 ease-in'"
                                class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                            >
                                <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 12 12">
                                    <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            <span
                                :class="enabled ? 'opacity-100 duration-200 ease-in' : 'opacity-0 duration-100 ease-out'"
                                class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity"
                            >
                                <svg class="h-3 w-3 text-primary-600" fill="currentColor" viewBox="0 0 12 12">
                                    <path d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z" />
                                </svg>
                            </span>
                        </span>
                    </button>
                </div>

                <!-- Subscription Status -->
                <template x-if="enabled && subscribed">
                    <div class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 text-success-700 dark:text-success-400">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            <span>Push notifications are active on this device.</span>
                        </div>
                    </div>
                </template>

                <!-- Test Button -->
                <template x-if="enabled && subscribed">
                    <div class="pt-2">
                        <button
                            type="button"
                            x-on:click="testNotification"
                            class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            Send test notification
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pushNotifications', (vapidPublicKey) => ({
                browserSupported: false,
                permissionDenied: false,
                enabled: @js($enabled),
                subscribed: false,
                loading: false,
                vapidPublicKey: vapidPublicKey,

                async init() {
                    // Check browser support
                    this.browserSupported = 'serviceWorker' in navigator && 'PushManager' in window;

                    if (!this.browserSupported) {
                        return;
                    }

                    // Check current permission
                    if (Notification.permission === 'denied') {
                        this.permissionDenied = true;
                        return;
                    }

                    // Check existing subscription
                    await this.checkSubscription();

                    // If enabled but not subscribed, try to subscribe
                    if (this.enabled && !this.subscribed && Notification.permission === 'granted') {
                        await this.subscribe();
                    }
                },

                async checkSubscription() {
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        this.subscribed = !!subscription;
                    } catch (error) {
                        console.error('Error checking subscription:', error);
                    }
                },

                async toggle() {
                    if (this.loading) return;

                    this.loading = true;

                    try {
                        if (!this.enabled) {
                            // Turning on
                            await this.requestPermissionAndSubscribe();
                        } else {
                            // Turning off
                            await this.unsubscribe();
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                async requestPermissionAndSubscribe() {
                    // Register service worker first
                    try {
                        await navigator.serviceWorker.register('/sw.js');
                    } catch (error) {
                        console.error('Service worker registration failed:', error);
                    }

                    // Request permission
                    const permission = await Notification.requestPermission();

                    if (permission === 'denied') {
                        this.permissionDenied = true;
                        return;
                    }

                    if (permission !== 'granted') {
                        return;
                    }

                    // Subscribe
                    await this.subscribe();

                    // Update server-side setting
                    @this.toggleEnabled();
                },

                async subscribe() {
                    try {
                        const registration = await navigator.serviceWorker.ready;

                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                        });

                        // Send subscription to server
                        const response = await fetch('/api/push/subscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(subscription.toJSON())
                        });

                        if (response.ok) {
                            this.subscribed = true;
                            this.enabled = true;
                            @this.subscriptionRegistered(subscription.endpoint);
                        }
                    } catch (error) {
                        console.error('Subscription failed:', error);
                    }
                },

                async unsubscribe() {
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();

                        if (subscription) {
                            // Remove from server first
                            await fetch('/api/push/unsubscribe', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ endpoint: subscription.endpoint })
                            });

                            await subscription.unsubscribe();
                        }

                        this.subscribed = false;
                        this.enabled = false;
                        @this.toggleEnabled();
                        @this.subscriptionRemoved();
                    } catch (error) {
                        console.error('Unsubscribe failed:', error);
                    }
                },

                testNotification() {
                    if (Notification.permission === 'granted') {
                        new Notification('Claude Runner', {
                            body: 'Push notifications are working!',
                            icon: '/android-chrome-192x192.png'
                        });
                    }
                },

                urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding)
                        .replace(/-/g, '+')
                        .replace(/_/g, '/');

                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);

                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }

                    return outputArray;
                }
            }));
        });
    </script>
    @endpush
</x-filament::section>
