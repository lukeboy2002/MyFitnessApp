<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush as Sender;

/**
 * Pushmeldingen naar je telefoon (ook met het scherm uit), via de gratis pushdiensten van Apple/Google/Mozilla.
 * Sleutels: php artisan fitness:vapid-keys → VAPID_PUBLIC_KEY en VAPID_PRIVATE_KEY in .env.
 */
class WebPush
{
    public static function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * Stuurt naar alle apparaten van de gebruiker; verlopen abonnementen worden opgeruimd.
     *
     * @param  array{title: string, body: string, url?: string, tag?: string}  $message
     * @return int aantal apparaten dat de melding heeft aangenomen
     */
    public static function send(User $user, array $message): int
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if (! self::configured() || $subscriptions->isEmpty()) {
            return 0;
        }

        $sender = new Sender(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]], ['TTL' => 300, 'urgency' => 'high'], logger: Log::channel()); // tips van het pakket in het log i.p.v. als fout

        $payload = json_encode($message);
        foreach ($subscriptions as $subscription) {
            $sender->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        $delivered = 0;
        foreach ($sender->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', PushSubscription::hash($report->getEndpoint()))->delete();
            } else {
                report(new \RuntimeException('Pushmelding mislukt: '.$report->getReason()));
            }
        }

        return $delivered;
    }
}
