/**
 * Service Worker for Push Notifications
 * Claude Runner PWA
 */

// Push notification received
self.addEventListener('push', function(event) {
    const data = event.data?.json() ?? {};

    const options = {
        body: data.body || 'Task completed',
        icon: '/android-chrome-192x192.png',
        badge: '/favicon-32x32.png',
        data: { url: data.url || '/' },
        actions: data.actions || [
            { action: 'view', title: 'View Task' },
            { action: 'dismiss', title: 'Dismiss' }
        ],
        requireInteraction: true,
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Claude Runner', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.action === 'dismiss') {
        return;
    }

    // For 'view' action or clicking the notification body
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                // Check if there's already a window open with this URL
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Open a new window if none found
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Service worker activation
self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});
