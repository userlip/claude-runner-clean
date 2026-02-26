import './bootstrap';
import './native-feel';

// Dynamically import Sentry only when DSN is configured
// This saves ~150KB from the initial bundle on every page load
if (window.SENTRY_DSN) {
    import('@sentry/browser').then((Sentry) => {
        Sentry.init({
            dsn: window.SENTRY_DSN,
            environment: window.SENTRY_ENVIRONMENT || 'production',
            release: window.SENTRY_RELEASE || undefined,
            integrations: [
                Sentry.browserTracingIntegration(),
                Sentry.replayIntegration(),
            ],
            tracesSampleRate: 0.1,
            replaysSessionSampleRate: 0.1,
            replaysOnErrorSampleRate: 1.0,
        });
    });
}
