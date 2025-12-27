<x-filament::section :aside="true" heading="Push Notifications" description="Receive browser notifications when tasks complete.">
    <div class="space-y-4" x-data="pushNotificationSettings()" x-init="init()">
        <!-- Browser Support Check -->
        <div x-show="!browserSupported" class="rounded-lg bg-warning-50 dark:bg-warning-500/10 p-4 text-warning-700 dark:text-warning-400">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                <span>Push notifications are not supported in this browser.</span>
            </div>
        </div>

        <div x-show="browserSupported" class="space-y-4">
            <!-- Permission Status -->
            <div x-show="permissionDenied" class="rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-danger-700 dark:text-danger-400">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-x-circle class="w-5 h-5" />
                    <span>Notification permission was denied. Please enable it in your browser settings.</span>
                </div>
            </div>

            <!-- Toggle -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">Enable Push Notifications</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Get notified when Claude Code tasks complete or fail.</p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="enabled.toString()"
                    @click="toggle()"
                    :style="{ backgroundColor: enabled ? 'rgb(59, 130, 246)' : 'rgb(156, 163, 175)' }"
                    style="position: relative; display: inline-flex; height: 24px; width: 44px; flex-shrink: 0; cursor: pointer; border-radius: 9999px; border: 2px solid transparent; transition: background-color 200ms ease-in-out;"
                    :disabled="loading || permissionDenied"
                >
                    <span
                        :style="{ transform: enabled ? 'translateX(20px)' : 'translateX(0)' }"
                        style="pointer-events: none; display: inline-block; height: 20px; width: 20px; border-radius: 9999px; background-color: white; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); transition: transform 200ms ease-in-out;"
                    ></span>
                </button>
            </div>

            <!-- Subscription Status -->
            <div x-show="enabled && subscribed" class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 text-success-700 dark:text-success-400">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5" />
                    <span>Push notifications are active on this device.</span>
                </div>
            </div>

            <!-- Test Button -->
            <div x-show="enabled && subscribed" class="pt-2">
                <button
                    type="button"
                    @click="testNotification()"
                    class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    Send test notification
                </button>
            </div>
        </div>
    </div>

    <script>
        function pushNotificationSettings() {
            return {
                browserSupported: false,
                permissionDenied: false,
                enabled: @js($enabled),
                subscribed: false,
                loading: false,
                vapidPublicKey: @js(config('services.vapid.public_key')),

                init() {
                    this.browserSupported = 'serviceWorker' in navigator && 'PushManager' in window;

                    if (!this.browserSupported) {
                        return;
                    }

                    if (Notification.permission === 'denied') {
                        this.permissionDenied = true;
                        return;
                    }

                    this.checkSubscription();
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
                            await this.requestPermissionAndSubscribe();
                        } else {
                            await this.unsubscribe();
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                async requestPermissionAndSubscribe() {
                    try {
                        await navigator.serviceWorker.register('/sw.js');
                    } catch (error) {
                        console.error('Service worker registration failed:', error);
                    }

                    const permission = await Notification.requestPermission();

                    if (permission === 'denied') {
                        this.permissionDenied = true;
                        return;
                    }

                    if (permission !== 'granted') {
                        return;
                    }

                    await this.subscribe();
                    @this.toggleEnabled();
                },

                async subscribe() {
                    try {
                        const registration = await navigator.serviceWorker.ready;

                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                        });

                        const csrfToken = document.querySelector('meta[name="csrf-token"]');
                        const response = await fetch('/api/push/subscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
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
                            const csrfToken = document.querySelector('meta[name="csrf-token"]');
                            await fetch('/api/push/unsubscribe', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken ? csrfToken.content : ''
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
            };
        }
    </script>
</x-filament::section>
