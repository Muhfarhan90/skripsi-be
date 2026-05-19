<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Transaction;

class NotificationService
{
    public function getForUser(int $userId, int $perPage = 15)
    {
        return Notification::query()
            ->with('actor:id,fullname,email')
            ->where('user_id', $userId)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(max($perPage, 1));
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $userId, int $notificationId): Notification
    {
        $notification = Notification::query()
            ->where('user_id', $userId)
            ->findOrFail($notificationId);

        if (! $notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return $notification->fresh('actor:id,fullname,email');
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function publishTransactionSuccess(Transaction $transaction, iterable $enrollments = []): void
    {
        $transaction->loadMissing('order');
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $normalizedEnrollments = collect($enrollments)
            ->filter(fn ($enrollment) => $enrollment instanceof Enrollment)
            ->values();

        $this->saveUniqueNotification([
            'user_id' => $order->user_id,
            'type' => 'payment.success',
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ], [
            'title' => 'Pembayaran berhasil',
            'body' => 'Pembayaran Anda berhasil diproses dan akses kelas sudah diperbarui.',
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'enrollment_ids' => $normalizedEnrollments->pluck('id')->values()->all(),
                'route' => '/student/orders/' . $order->id,
            ],
            'sent_at' => now(),
        ]);

        foreach ($normalizedEnrollments as $enrollment) {
            $this->publishEnrollmentActivated($enrollment);
        }
    }

    public function publishTransactionFailed(Transaction $transaction): void
    {
        $transaction->loadMissing('order');
        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $this->saveUniqueNotification([
            'user_id' => $order->user_id,
            'type' => 'payment.failed',
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ], [
            'title' => 'Pembayaran gagal',
            'body' => 'Pembayaran Anda gagal atau ditolak. Silakan periksa detail pesanan dan kirim ulang pembayaran jika diperlukan.',
            'data' => [
                'transaction_id' => $transaction->id,
                'order_id' => $order->id,
                'route' => '/student/orders/' . $order->id,
            ],
            'sent_at' => now(),
        ]);
    }

    public function publishEnrollmentActivated(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('courseOffering.course');

        $courseTitle = $enrollment->courseOffering?->course?->title;
        $body = $courseTitle
            ? 'Akses belajar untuk "' . $courseTitle . '" sudah aktif.'
            : 'Akses belajar Anda sudah aktif.';

        $this->saveUniqueNotification([
            'user_id' => $enrollment->user_id,
            'type' => 'enrollment.activated',
            'reference_type' => 'enrollment',
            'reference_id' => $enrollment->id,
        ], [
            'title' => 'Akses kelas aktif',
            'body' => $body,
            'data' => [
                'enrollment_id' => $enrollment->id,
                'order_id' => $enrollment->order_id,
                'course_offering_id' => $enrollment->course_offering_id,
                'course_title' => $courseTitle,
                'route' => '/student/enrollments/' . $enrollment->id,
            ],
            'sent_at' => now(),
        ]);
    }

    private function saveUniqueNotification(array $identity, array $payload): Notification
    {
        $notification = Notification::query()->firstOrNew($identity);

        if ($notification->exists) {
            $notification->fill([
                'title' => $payload['title'],
                'body' => $payload['body'],
                'data' => $payload['data'] ?? null,
                'actor_id' => $payload['actor_id'] ?? $notification->actor_id,
                'sent_at' => $payload['sent_at'] ?? $notification->sent_at,
            ]);
        } else {
            $notification->fill($identity + $payload);
        }

        $notification->save();

        if ($notification->wasRecentlyCreated) {
            SendPushNotificationJob::dispatch($notification->id)->afterCommit();
        }

        return $notification;
    }
}
