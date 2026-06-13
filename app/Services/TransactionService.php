<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getAllForAdmin(string $search = '', int $perPage = 10, ?string $status = null)
    {
        $perPage = max($perPage, 1);

        return $this->buildAdminListQuery($search, $status)->paginate($perPage);
    }

    public function getAdminExportRows(string $search = '', ?string $status = null): Collection
    {
        return $this->buildAdminListQuery($search, $status)
            ->get()
            ->map(fn (Transaction $transaction) => $this->mapTransactionForAdminExport($transaction));
    }

    public function findById(int $id)
    {
        return Transaction::with(['order.user'])->findOrFail($id);
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
            $activatedEnrollments = collect();

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

                    $activatedEnrollments = $this->activateOrderEnrollments($order);
                }

                if (! $transaction->paid_at) {
                    $transaction->update(['paid_at' => now()]);
                }

                $this->notificationService->publishTransactionSuccess(
                    $transaction->fresh(),
                    $activatedEnrollments
                );
            }

            if ($oldStatus !== 'failed' && $transaction->status === 'failed') {
                $order = $transaction->order;
                if ($order && $order->status !== 'completed') {
                    $order->update(['status' => 'cancelled']);
                }

                $this->notificationService->publishTransactionFailed($transaction->fresh());
            }

            return $this->findById($transaction->id);
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

    private function activateOrderEnrollments(Order $order): Collection
    {
        $order->loadMissing('items.courseOffering.course', 'items.courseOffering.academicPeriod');
        $activatedEnrollments = collect();

        foreach ($order->items as $item) {
            $offering = $this->resolveOfferingForOrderItem($item);

            $startedAt = now();
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
            $activatedEnrollments->push($enrollment->fresh(['courseOffering.course']));
        }

        return $activatedEnrollments;
    }

    private function resolveOfferingForOrderItem(OrderItem $item): CourseOffering
    {
        if ($item->courseOffering) {
            return $item->courseOffering;
        }

        if ($item->course_offering_id) {
            return CourseOffering::with(['course', 'academicPeriod'])->findOrFail((int) $item->course_offering_id);
        }

        throw ValidationException::withMessages([
            'course_offering_id' => ['Order item is missing course offering reference'],
        ]);
    }

    private function resolveEnrollmentEndAt(CourseOffering $offering): ?Carbon
    {
        return $this->resolveOfferingAcademicPeriod($offering)->end_at;
    }

    private function resolveOfferingAcademicPeriod(CourseOffering $offering)
    {
        $offering->loadMissing('academicPeriod');

        if (! $offering->academicPeriod) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Offering does not have a valid academic period'],
            ]);
        }

        return $offering->academicPeriod;
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

    private function buildAdminListQuery(string $search = '', ?string $status = null): Builder
    {
        return Transaction::query()
            ->with(['order.user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('invoice_code', 'like', "%{$search}%")
                        ->orWhere('payment_reference', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('payment_channel', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('order', function ($orderQuery) use ($search) {
                            $orderQuery->where('order_code', 'like', "%{$search}%")
                                ->orWhereHas('user', function ($userQuery) use ($search) {
                                    $userQuery->where('fullname', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest();
    }

    private function mapTransactionForAdminExport(Transaction $transaction): array
    {
        $paymentMethod = collect([
            $transaction->payment_method,
            $transaction->payment_channel,
        ])->filter(fn (?string $value) => filled($value))->implode(' / ');

        $paymentReference = $transaction->payment_reference
            ?: $transaction->external_id
            ?: $transaction->invoice_code;

        return [
            'Kode Invoice' => $transaction->invoice_code,
            'Tanggal Transaksi' => $this->formatAdminExportDate($transaction->created_at),
            'Status Transaksi' => $transaction->status,
            'Nominal (IDR)' => $this->formatAdminExportAmount($transaction->amount),
            'Metode Pembayaran' => $paymentMethod,
            'Referensi Pembayaran' => $paymentReference,
            'Order Code' => $transaction->order?->order_code ?? '',
            'Status Order' => $transaction->order?->status ?? '',
            'Nama Siswa' => $transaction->order?->user?->fullname ?? '',
            'Email Siswa' => $transaction->order?->user?->email ?? '',
            'Dibayar Pada' => $this->formatAdminExportDate($transaction->paid_at),
            'Kedaluwarsa Pada' => $this->formatAdminExportDate($transaction->expired_at),
        ];
    }

    private function formatAdminExportDate(?Carbon $value): string
    {
        if (! $value) {
            return '';
        }

        return $value->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s');
    }

    private function formatAdminExportAmount(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, '.', '');
    }
}
