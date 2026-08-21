<?php
if (!isset($CFG)) {
    include_once('../config.php');
}
if (!isset($DBLIB)) {
    include_once $CFG->dirroot . '/lib/dblib.php';
}
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/notification_lib.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use GuzzleHttp\Client;

class WebPushHelper
{
    private WebPush $webPush;

    public function __construct()
    {
        global $CFG;
        $vapid = notifications_get_vapid_keys();

        $client = new Client([
            // optional: timeout, proxy, etc.
            'timeout' => 30,
        ]);

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => $CFG->siteemail, // change this
                'publicKey'  => $vapid['publicKey'],
                'privateKey' => $vapid['privateKey'],
            ],
        ], [], $client);
    }

    /** Save a new subscription (or update existing) under its identifier */
    public function saveSubscription(string $identifier, array $subscription): void
    {
        notifications_set($identifier, $subscription);
    }

    /** Remove a subscription by identifier */
    public function removeSubscription(string $identifier): void
    {
        notifications_delete($identifier);
    }

    /** Get all stored subscriptions, keyed by identifier */
    public function getSubscriptions(): array
    {
        return notifications_all_subscriptions();
    }

    /**
     * Send a payload to a specific set of subscriptions, e.g. the result
     * of status_push_subscriptions_for_account() - every device one
     * particular family has enabled, rather than every subscription on
     * the site.
     * @param array $subscriptions  identifier => subscription array
     * @param string|array $payload JSON string or array
     */
    public function sendToSubscriptions(array $subscriptions, $payload): array
    {
        return $this->dispatch($subscriptions, $payload);
    }

    /**
     * Send a notification to one or all subscriptions
     * @param string|array $payload  JSON string or array
     * @param array|null   $subscription  Single subscription or null = all
     */
    public function send($payload, ?array $subscription = null): array
    {
        $targets = $subscription ? ['_single' => $subscription] : $this->getSubscriptions();
        return $this->dispatch($targets, $payload);
    }

    /** @param array $targets identifier => subscription array */
    private function dispatch(array $targets, $payload): array
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $reports = [];

        foreach ($targets as $identifier => $sub) {
            $report = $this->webPush->sendOneNotification(
                Subscription::create($sub),
                $payload
            );

            $reports[] = [
                'identifier' => $identifier,
                'endpoint'   => $sub['endpoint'],
                'success'    => $report->isSuccess(),
                'reason'     => $report->getReason(),
            ];

            // Automatically clean up expired/invalid subscriptions
            if ($report->isSubscriptionExpired() && $identifier !== '_single') {
                $this->removeSubscription($identifier);
            }
        }

        return $reports;
    }
}