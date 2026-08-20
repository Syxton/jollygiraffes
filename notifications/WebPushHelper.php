<?php
if (!isset($CFG)) {
    include_once('../config.php');
}
require_once __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use GuzzleHttp\Client;

class WebPushHelper
{
    private WebPush $webPush;
    private string $subscriptionsFile;

    public function __construct(string $subscriptionsFile = 'subscriptions.json')
    {
        global $CFG;
        $vapid = json_decode($CFG->vapid, true);

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

        $this->subscriptionsFile = $subscriptionsFile;

        if (!file_exists($this->subscriptionsFile)) {
            file_put_contents($this->subscriptionsFile, '[]');
        }
    }

    /** Save a new subscription (or update existing) */
    public function saveSubscription(array $subscription): void
    {
        $subs = $this->getSubscriptions();

        // Avoid duplicates by endpoint
        $subs = array_filter($subs, fn($s) => $s['endpoint'] !== $subscription['endpoint']);
        $subs[] = $subscription;

        file_put_contents($this->subscriptionsFile, json_encode(array_values($subs), JSON_PRETTY_PRINT));
    }

    /** Remove a subscription */
    public function removeSubscription(string $endpoint): void
    {
        $subs = array_filter(
            $this->getSubscriptions(),
            fn($s) => $s['endpoint'] !== $endpoint
        );
        file_put_contents($this->subscriptionsFile, json_encode(array_values($subs), JSON_PRETTY_PRINT));
    }

    /** Get all stored subscriptions */
    public function getSubscriptions(): array
    {
        return json_decode(file_get_contents($this->subscriptionsFile), true) ?: [];
    }

    /**
     * Send a notification to one or all subscriptions
     * @param string|array $payload  JSON string or array
     * @param array|null   $subscription  Single subscription or null = all
     */
    public function send($payload, ?array $subscription = null): array
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $reports = [];

        $targets = $subscription ? [$subscription] : $this->getSubscriptions();

        foreach ($targets as $sub) {
            $report = $this->webPush->sendOneNotification(
                Subscription::create($sub),
                $payload
            );

            $reports[] = [
                'endpoint' => $sub['endpoint'],
                'success'  => $report->isSuccess(),
                'reason'   => $report->getReason(),
            ];

            // Automatically clean up expired/invalid subscriptions
            if ($report->isSubscriptionExpired()) {
                $this->removeSubscription($sub['endpoint']);
            }
        }

        return $reports;
    }
}