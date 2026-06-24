<?php

declare(strict_types=1);

namespace App\Services\Fcm;

use App\Models\DeviceToken;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * FCM (Firebase Cloud Messaging) yuboruvchi. Service account sozlanmagan
 * bo'lsa (dev) — jimgina o'tkazib yuboradi. Yaroqsiz tokenlarni o'chiradi.
 */
final class FcmSender
{
    /**
     * Berilgan tokenlarga push yuboradi. data — string map (deep-link uchun).
     *
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === [] || ! $this->configured()) {
            return;
        }

        try {
            $messaging = app(Messaging::class);

            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($data)
                // Android: heads-up popup uchun yuqori prioritet + 'orders' kanali.
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'orders',
                        'sound' => 'default',
                        'default_sound' => true,
                    ],
                ]))
                // iOS: ovoz + badge.
                ->withApnsConfig(ApnsConfig::fromArray([
                    'payload' => ['aps' => ['sound' => 'default']],
                ]));

            $report = $messaging->sendMulticast($message, $tokens);

            $invalid = array_merge(
                $report->invalidTokens(),
                $report->unknownTokens(),
            );

            if ($invalid !== []) {
                DeviceToken::query()->whereIn('token', $invalid)->delete();
            }
        } catch (MessagingException|Throwable $e) {
            report($e);
        }
    }

    /**
     * Service account credentials mavjudmi (yuborish mumkinmi).
     */
    public function configured(): bool
    {
        $credentials = config('firebase.projects.app.credentials');

        if (is_array($credentials)) {
            return $credentials !== [];
        }

        return is_string($credentials) && $credentials !== '' && is_file(base_path($credentials));
    }
}
