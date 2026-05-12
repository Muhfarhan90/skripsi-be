<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function getAll()
    {
        return Transaction::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Transaction::findOrFail($id);
    }

    public function create(array $data)
    {
        $data['invoice_code'] = $this->generateInvoiceCode();

        $data['status'] = $this->normalizeStatus($data['status'] ?? 'pending');
        $data['expired_at'] = $data['expired_at'] ?? now()->addDay();

        return Transaction::create($data);
    }

    private function generateInvoiceCode(): string
    {
        $date = now()->format('Ymd');
        $exists = true;
        $invoiceCode = '';

        while ($exists) {
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $invoiceCode = "INV-{$date}-{$randomHex}";
            $exists = Transaction::where('invoice_code', $invoiceCode)->exists();
        }

        return $invoiceCode;
    }

    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $transaction = $this->findById($id);
            $oldStatus = $transaction->status;

            if (array_key_exists('status', $data)) {
                $data['status'] = $this->normalizeStatus((string) $data['status']);
            }

            $transaction->update($data);

            if ($oldStatus !== 'success' && $transaction->status === 'success') {
                $order = $transaction->order;
                if ($order) {
                    if ($order->status !== 'completed') {
                        $order->update(['status' => 'completed']);
                    }

                    $this->activateOrderEnrollments($order);
                }

                if (! $transaction->paid_at) {
                    $transaction->update(['paid_at' => now()]);
                }
            }

            if ($transaction->status === 'failed') {
                $order = $transaction->order;
                if ($order && $order->status !== 'completed') {
                    $order->update(['status' => 'cancelled']);
                }
            }

            return $transaction->fresh();
        });
    }

    public function delete(int $id)
    {
        $transaction = $this->findById($id);
        $transaction->delete();

        return true;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower($status);

        if ($normalized === 'paid') {
            return 'success';
        }

        if ($normalized === 'refunded') {
            return 'failed';
        }

        return $normalized;
    }

    private function activateOrderEnrollments(Order $order): void
    {
        $order->loadMissing('items.courseOffering.course');

        foreach ($order->items as $item) {
            $offering = $this->resolveOfferingForOrderItem($item);

            $startedAt = $offering->start_at ?? now();
            $endedAt = $this->resolveEnrollmentEndAt($offering);
            $status = $this->resolveEnrollmentStatus($startedAt, $endedAt);

            $enrollment = Enrollment::firstOrNew([
                'user_id' => $order->user_id,
                'course_offering_id' => $offering->id,
            ]);

            $payload = [
                'course_offering_id' => $offering->id,
                'order_id' => $order->id,
                'status' => $enrollment->status === 'completed' ? 'completed' : $status,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'expired_at' => $endedAt,
            ];

            if ($enrollment->status === 'completed') {
                $payload['completed_at'] = $enrollment->completed_at ?? now();
            }

            $enrollment->fill($payload);
            $enrollment->save();
        }
    }

    private function resolveOfferingForOrderItem(OrderItem $item): CourseOffering
    {
        if ($item->courseOffering) {
            return $item->courseOffering;
        }

        if ($item->course_offering_id) {
            return CourseOffering::with('course')->findOrFail((int) $item->course_offering_id);
        }

        throw ValidationException::withMessages([
            'course_offering_id' => ['Order item is missing course offering reference'],
        ]);
    }

    private function resolveEnrollmentEndAt(CourseOffering $offering): ?Carbon
    {
        if ($offering->end_at) {
            return $offering->end_at;
        }

        return null;
    }

    private function resolveEnrollmentStatus(?Carbon $startedAt, ?Carbon $endedAt): string
    {
        $now = now();
        if ($startedAt && $now->lt($startedAt)) {
            return 'pending';
        }
        if ($endedAt && $now->gt($endedAt)) {
            return 'expired';
        }

        return 'active';
    }
}
