import './bootstrap';
import * as Sentry from '@sentry/browser';

// Initialize Sentry if DSN is configured
if (window.SENTRY_DSN) {
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
}
