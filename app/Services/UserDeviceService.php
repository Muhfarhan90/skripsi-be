<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\DB;

class UserDeviceService
{
    public function register(User $user, array $data): UserDevice
    {
        return DB::transaction(function () use ($user, $data) {
            $device = UserDevice::firstOrNew([
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
            ]);

            $existingTokenDevice = UserDevice::query()
                ->where('fcm_token', $data['fcm_token'])
                ->first();

            if ($existingTokenDevice
                && (! $device->exists || (int) $existingTokenDevice->id !== (int) $device->id)) {
                $existingTokenDevice->delete();
            }

            $device->fill([
                'device_type' => $data['device_type'],
                'fcm_token' => $data['fcm_token'],
                'device_info' => $data['device_info'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ]);

            $device->save();

            return $device->fresh();
        });
    }

    public function deactivateByDeviceId(User $user, string $deviceId): ?UserDevice
    {
        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if (! $device) {
            return null;
        }

        $device->update([
            'is_active' => false,
            'last_seen_at' => now(),
        ]);

        return $device->fresh();
    }
}
