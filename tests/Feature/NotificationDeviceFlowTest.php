<?php

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createNotificationTestUser(): User
{
    $role = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 2], ['name' => 'user']));

    return User::factory()->create([
        'role_id' => $role->id,
        'email' => 'notification-user@example.com',
    ]);
}

it('registers and deactivates an authenticated user device', function () {
    $user = createNotificationTestUser();
    Sanctum::actingAs($user);

    $this->postJson('/api/auth/devices', [
        'device_id' => 'browser-main',
        'device_type' => 'web',
        'fcm_token' => 'fcm-token-main',
        'device_info' => [
            'browser' => 'Chrome',
            'platform' => 'Windows',
        ],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.device_id', 'browser-main')
        ->assertJsonPath('data.device_type', 'web')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'device_id' => 'browser-main',
        'device_type' => 'web',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/auth/devices/browser-main')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.device_id', 'browser-main')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('user_devices', [
        'user_id' => $user->id,
        'device_id' => 'browser-main',
        'is_active' => false,
    ]);
});

it('lists notifications and marks them as read', function () {
    $user = createNotificationTestUser();
    Sanctum::actingAs($user);

    $firstNotification = Notification::create([
        'user_id' => $user->id,
        'type' => 'payment.success',
        'title' => 'Pembayaran berhasil',
        'body' => 'Pembayaran Anda berhasil diproses.',
        'data' => [
            'order_id' => 10,
        ],
        'reference_type' => 'transaction',
        'reference_id' => 20,
        'sent_at' => now(),
    ]);

    $secondNotification = Notification::create([
        'user_id' => $user->id,
        'type' => 'enrollment.activated',
        'title' => 'Akses kelas aktif',
        'body' => 'Akses belajar Anda sudah aktif.',
        'data' => [
            'enrollment_id' => 30,
        ],
        'reference_type' => 'enrollment',
        'reference_id' => 30,
        'sent_at' => now(),
    ]);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.unread_count', 2);

    $this->patchJson("/api/notifications/{$firstNotification->id}/read")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $firstNotification->id)
        ->assertJsonPath('data.is_read', true);

    $this->assertDatabaseMissing('notifications', [
        'id' => $firstNotification->id,
        'read_at' => null,
    ]);

    $this->patchJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.updated_count', 1);

    $this->assertDatabaseMissing('notifications', [
        'id' => $secondNotification->id,
        'read_at' => null,
    ]);
});
