<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\FcmService;
use App\Services\FirebaseAccessTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_push_notification_to_active_devices(): void
    {
        config()->set('app.frontend_url', 'https://skripsi.test');

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/skripsi-1e41d/messages/123',
            ], 200),
        ]);

        $user = $this->createUser();
        $device = UserDevice::query()->create([
            'user_id' => $user->id,
            'device_id' => 'web-device-1',
            'device_type' => 'web',
            'fcm_token' => 'token-1',
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'payment.success',
            'title' => 'Pembayaran berhasil',
            'body' => 'Pesanan Anda sudah diproses.',
            'reference_type' => 'transaction',
            'reference_id' => 44,
            'data' => [
                'order_id' => 44,
                'route' => '/student/orders/44',
            ],
            'sent_at' => now(),
        ]);

        $service = new FcmService($this->fakeAccessTokenService());
        $service->sendNotification($notification);

        Http::assertSent(function ($request) use ($device) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/skripsi-1e41d/messages:send'
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && data_get($request->data(), 'message.token') === $device->fcm_token
                && data_get($request->data(), 'message.notification.title') === 'Pembayaran berhasil'
                && data_get($request->data(), 'message.webpush.headers.Urgency') === 'high'
                && data_get($request->data(), 'message.webpush.notification.title') === 'Pembayaran berhasil'
                && data_get($request->data(), 'message.webpush.notification.badge') === '/globe.svg'
                && data_get($request->data(), 'message.webpush.fcm_options.link') === 'https://skripsi.test/student/orders/44'
                && data_get($request->data(), 'message.data.order_id') === '44';
        });

        $this->assertTrue((bool) $device->fresh()->is_active);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_it_deactivates_unregistered_tokens(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'UNREGISTERED',
                    'details' => [
                        [
                            '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                            'errorCode' => 'UNREGISTERED',
                        ],
                    ],
                ],
            ], 404),
        ]);

        $user = $this->createUser();
        $device = UserDevice::query()->create([
            'user_id' => $user->id,
            'device_id' => 'web-device-2',
            'device_type' => 'web',
            'fcm_token' => 'expired-token',
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'enrollment.activated',
            'title' => 'Akses kelas aktif',
            'body' => 'Kelas siap dipelajari.',
            'reference_type' => 'enrollment',
            'reference_id' => 55,
            'data' => [
                'enrollment_id' => 55,
                'route' => '/student/enrollments/55',
            ],
            'sent_at' => now(),
        ]);

        $service = new FcmService($this->fakeAccessTokenService());
        $service->sendNotification($notification);

        Http::assertSentCount(1);
        $this->assertFalse((bool) $device->fresh()->is_active);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_it_omits_webpush_link_for_non_https_frontend_urls(): void
    {
        config()->set('app.frontend_url', 'http://localhost:3000');

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/skripsi-1e41d/messages/456',
            ], 200),
        ]);

        $user = $this->createUser();
        UserDevice::query()->create([
            'user_id' => $user->id,
            'device_id' => 'web-device-3',
            'device_type' => 'web',
            'fcm_token' => 'token-3',
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'payment.success',
            'title' => 'Pembayaran berhasil',
            'body' => 'Pesanan Anda sudah diproses.',
            'reference_type' => 'transaction',
            'reference_id' => 88,
            'data' => [
                'order_id' => 88,
                'route' => '/student/orders/88',
            ],
            'sent_at' => now(),
        ]);

        $service = new FcmService($this->fakeAccessTokenService());
        $service->sendNotification($notification);

        Http::assertSent(function ($request) {
            return data_get($request->data(), 'message.webpush.headers.Urgency') === 'high'
                && data_get($request->data(), 'message.webpush.notification.title') === 'Pembayaran berhasil'
                && data_get($request->data(), 'message.webpush.fcm_options.link') === null
                && data_get($request->data(), 'message.data.route') === '/student/orders/88';
        });
    }

    private function fakeAccessTokenService(): FirebaseAccessTokenService
    {
        return new class extends FirebaseAccessTokenService
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function getProjectId(): ?string
            {
                return 'skripsi-1e41d';
            }

            public function getAccessToken(): string
            {
                return 'fake-access-token';
            }
        };
    }

    private function createUser(): User
    {
        $role = Role::query()->create([
            'name' => 'role_' . fake()->unique()->numberBetween(1000, 9999),
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
