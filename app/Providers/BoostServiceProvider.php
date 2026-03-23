<?php

namespace App\Providers;

use Laravel\Boost\BoostServiceProvider as BaseProvider;

class BoostServiceProvider extends BaseProvider
{
    /**
     * Determine if Boost should run.
     *
     * We override this to allow Boost to run when BOOST_ENABLED=true
     * regardless of the environment or debug settings.
     */
    protected function shouldRun(): bool
    {
        // Respect explicit disable
        if (! config('boost.enabled', true)) {
            return false;
        }

        // Don't run in unit tests
        if (app()->runningUnitTests()) {
            return false;
        }

        // Allow if explicitly enabled via env (for production use by AI agents)
        // Note: env() returns the actual type (bool true, not string 'true')
        if (env('BOOST_ENABLED') === true || env('BOOST_ENABLED') === 'true') {
            return true;
        }

        // Original behavior: only enable on local or when debug is true
        if (! app()->environment('local') && config('app.debug', false) !== true) {
            return false;
        }

        return true;
    }
}
