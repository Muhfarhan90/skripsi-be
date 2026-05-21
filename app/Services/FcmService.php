<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\UserDevice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmService
{
    public function __construct(
        private readonly FirebaseAccessTokenService $accessTokenService,
    ) {
    }

    public function sendNotification(Notification $notification): void
    {
        if (! $this->accessTokenService->isConfigured()) {
            return;
        }

        $projectId = $this->accessTokenService->getProjectId();
        if (! $projectId) {
            Log::warning('Skipping FCM push because FIREBASE_PROJECT_ID is not configured.');

            return;
        }

        $devices = UserDevice::query()
            ->where('user_id', $notification->user_id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $accessToken = $this->accessTokenService->getAccessToken();
        $endpoint = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            $projectId,
        );

        foreach ($devices as $device) {
            $this->sendToDevice($endpoint, $accessToken, $notification, $device);
        }
    }

    private function sendToDevice(
        string $endpoint,
        string $accessToken,
        Notification $notification,
        UserDevice $device,
    ): void {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post($endpoint, $this->buildMessagePayload($notification, $device));

        if ($response->successful()) {
            $device->forceFill([
                'last_seen_at' => now(),
            ])->save();

            return;
        }

        if ($this->shouldDeactivateDevice($response)) {
            $device->forceFill([
                'is_active' => false,
                'last_seen_at' => now(),
            ])->save();

            return;
        }

        if ($this->shouldRetry($response)) {
            throw new RuntimeException(sprintf(
                'FCM request failed with retryable status %d: %s',
                $response->status(),
                $response->body(),
            ));
        }

        Log::warning('FCM request failed for user device.', [
            'notification_id' => $notification->id,
            'device_id' => $device->device_id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);
    }

    /**
     * @return array{message: array<string, mixed>}
     */
    private function buildMessagePayload(Notification $notification, UserDevice $device): array
    {
        $webpushConfig = [
            'headers' => [
                'Urgency' => 'high',
            ],
            'notification' => [
                'title' => $notification->title,
                'body' => $notification->body,
                'icon' => '/globe.svg',
                'badge' => '/globe.svg',
                'tag' => sprintf('notification-%s', $notification->id),
                'renotify' => false,
            ],
        ];

        $message = [
            'token' => $device->fcm_token,
            'notification' => [
                'title' => $notification->title,
                'body' => $notification->body,
            ],
            'data' => $this->buildDataPayload($notification),
            'webpush' => $webpushConfig,
        ];

        $clickUrl = $this->resolveClickUrl($notification);
        if ($clickUrl) {
            $message['webpush']['fcm_options'] = [
                'link' => $clickUrl,
            ];
        }

        return ['message' => $message];
    }

    /**
     * @return array<string, string>
     */
    private function buildDataPayload(Notification $notification): array
    {
        $payload = [
            'notification_id' => (string) $notification->id,
            'type' => $notification->type,
        ];

        if ($notification->reference_type) {
            $payload['reference_type'] = $notification->reference_type;
        }

        if ($notification->reference_id !== null) {
            $payload['reference_id'] = (string) $notification->reference_id;
        }

        foreach ((array) $notification->data as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $stringValue = $this->stringifyPayloadValue($value);
            if ($stringValue === null) {
                continue;
            }

            $payload[$key] = $stringValue;
        }

        return $payload;
    }

    private function stringifyPayloadValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }

    private function resolveClickUrl(Notification $notification): ?string
    {
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $route = data_get($notification->data, 'route');

        if (is_string($route) && $route !== '') {
            if (str_starts_with($route, 'https://')) {
                return $route;
            }

            if ($baseUrl !== '') {
                $candidateUrl = $baseUrl . '/' . ltrim($route, '/');

                return str_starts_with($candidateUrl, 'https://') ? $candidateUrl : null;
            }
        }

        return str_starts_with($baseUrl, 'https://') ? $baseUrl : null;
    }

    private function shouldDeactivateDevice(Response $response): bool
    {
        $errorCode = $this->extractFcmErrorCode($response);

        if ($errorCode === 'UNREGISTERED') {
            return true;
        }

        if ($errorCode !== 'INVALID_ARGUMENT') {
            return false;
        }

        $message = strtolower((string) $response->json('error.message', ''));

        return str_contains($message, 'registration token');
    }

    private function shouldRetry(Response $response): bool
    {
        return in_array($response->status(), [429, 500, 503], true);
    }

    private function extractFcmErrorCode(Response $response): ?string
    {
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if (($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && is_string($detail['errorCode'] ?? null)) {
                return $detail['errorCode'];
            }
        }

        $status = $response->json('error.status');

        return is_string($status) ? $status : null;
    }
}
