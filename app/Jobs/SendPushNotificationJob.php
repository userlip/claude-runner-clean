<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $title,
        public string $body,
        public string $url,
        public array $actions = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $this->user->push_notifications_enabled) {
            return;
        }

        $subscriptions = $this->user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ];

        $webPush = new WebPush($auth);

        $payload = json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'actions' => $this->actions,
        ]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh_key,
                    'authToken' => $subscription->auth_token,
                ]),
                $payload
            );
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                Log::debug("Push notification sent successfully to {$endpoint}");
            } else {
                Log::warning("Push notification failed for {$endpoint}: {$report->getReason()}");

                // If subscription is expired or invalid, remove it
                if ($report->isSubscriptionExpired()) {
                    $this->user->pushSubscriptions()
                        ->where('endpoint', $endpoint)
                        ->delete();
                    Log::info("Removed expired push subscription: {$endpoint}");
                }
            }
        }
    }
}
