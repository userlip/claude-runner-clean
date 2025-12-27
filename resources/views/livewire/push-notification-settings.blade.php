<x-filament::section :aside="true" heading="Push Notifications" description="Receive browser notifications when tasks complete.">
    <div class="space-y-4">
        <!-- Toggle -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-950 dark:text-white">Enable Push Notifications</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Get notified when Claude Code tasks complete or fail.</p>
            </div>
            <button
                type="button"
                role="switch"
                wire:click="toggleEnabled"
                aria-checked="{{ $enabled ? 'true' : 'false' }}"
                style="position: relative; display: inline-flex; height: 24px; width: 44px; flex-shrink: 0; cursor: pointer; border-radius: 9999px; border: 2px solid transparent; transition: background-color 200ms ease-in-out; background-color: {{ $enabled ? 'rgb(59, 130, 246)' : 'rgb(156, 163, 175)' }};"
            >
                <span
                    style="pointer-events: none; display: inline-block; height: 20px; width: 20px; border-radius: 9999px; background-color: white; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); transition: transform 200ms ease-in-out; transform: {{ $enabled ? 'translateX(20px)' : 'translateX(0)' }};"
                ></span>
            </button>
        </div>

        @if($enabled)
            @if($subscriptionEndpoint)
            <div style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 8px; background-color: rgba(34, 197, 94, 0.1); color: rgb(34, 197, 94);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Push notifications active on this device.</span>
            </div>
            @else
            <div style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 8px; background-color: rgba(245, 158, 11, 0.1); color: rgb(245, 158, 11);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; flex-shrink: 0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <span>Click the button below to register this device.</span>
            </div>
            <button
                type="button"
                x-data
                @click="window.registerPushSubscription()"
                style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; background-color: rgb(59, 130, 246); color: white; font-weight: 500; font-size: 14px; border-radius: 8px; border: none; cursor: pointer;"
            >
                Register This Device
            </button>
            @endif
        @endif
    </div>

    <script>
        window.registerPushSubscription = async function() {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('Please allow notifications to receive push alerts.');
                    return;
                }

                const registration = await navigator.serviceWorker.ready;
                const vapidKey = @js(config('services.vapid.public_key'));

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: window.urlBase64ToUint8Array(vapidKey)
                });

                const response = await fetch('/api/push/subscribe', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(subscription.toJSON())
                });

                if (response.ok) {
                    @this.subscriptionRegistered(subscription.endpoint);
                    alert('Device registered successfully!');
                } else {
                    const text = await response.text();
                    console.error('Failed to register:', response.status, text);
                    alert('Failed to register device. Please try again.');
                }
            } catch (error) {
                console.error('Registration error:', error);
                alert('Error: ' + error.message);
            }
        };

        window.urlBase64ToUint8Array = function(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        };
    </script>
</x-filament::section>
