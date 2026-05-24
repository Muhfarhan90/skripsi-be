<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected TransactionService $transactionService;
    protected NotificationService $notificationService;

    public function __construct(TransactionService $transactionService, NotificationService $notificationService)
    {
        $this->transactionService = $transactionService;
        $this->notificationService = $notificationService;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getAllForAdmin(string $search = '', int $perPage = 10)
    {
        $perPage = max($perPage, 1);

        return $this->buildAdminListQuery($search)->paginate($perPage);
    }

    public function getAdminExportRows(string $search = ''): Collection
    {
        return $this->buildAdminListQuery($search)
            ->get()
            ->map(fn (Order $order) => $this->mapOrderForAdminExport($order));
    }

    public function findByIdForAdmin(int $id)
    {
        return Order::with(['user', 'items.courseOffering.course', 'transactions', 'voucher'])
            ->findOrFail($id);
    }

    public function updateStatus(int $id, string $status): Order
    {
        return DB::transaction(function () use ($id, $status) {
            $order = Order::findOrFail($id);
            $oldStatus = $order->status;
            $activatedEnrollments = collect();

            if ($oldStatus === $status) {
                return $this->freshOrderWithRelations($order->id);
            }

            $order->update(['status' => $status]);

            if ($status === 'completed') {
                $activatedEnrollments = $this->activateOrderEnrollments($order);

                $order->transactions()->where('status', 'pending')->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);

                $transaction = $order->transactions()
                    ->where('status', 'success')
                    ->latest('id')
                    ->first();

                if ($transaction) {
                    $this->notificationService->publishTransactionSuccess($transaction, $activatedEnrollments);
                }
            }

            if ($status === 'cancelled') {
                $order->enrollments()
                    ->where('status', '!=', 'completed')
                    ->update(['status' => 'cancelled']);
                $order->transactions()->where('status', 'pending')->update(['status' => 'failed']);

                $transaction = $order->transactions()
                    ->where('status', 'failed')
                    ->latest('id')
                    ->first();

                if ($transaction) {
                    $this->notificationService->publishTransactionFailed($transaction);
                }
            }

            return $this->freshOrderWithRelations($order->id);
        });
    }

    public function delete(int $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | STUDENT / USER FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getAllForStudent(int $userId, int $perPage = 10, ?string $status = null)
    {
        $perPage = max($perPage, 1);

        return Order::with(['items.courseOffering.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->where('status', '!=', 'cart')
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($perPage);
    }

    public function findByIdForStudent(int $id, int $userId)
    {
        return Order::with(['items.courseOffering.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->where('status', '!=', 'cart')
            ->findOrFail($id);
    }

    public function submitPaymentByStudent(int $userId, int $orderId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $orderId, $data) {
            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->firstOrFail();

            $transaction = $order->transactions()->latest('id')->first();
            if (! $transaction) {
                $transaction = $this->transactionService->create([
                    'order_id' => $order->id,
                    'amount' => $order->grand_total,
                    'status' => 'pending',
                    'payment_method' => 'manual',
                    'paid_at' => null,
                ]);
            }

            $transaction->update([
                'payment_reference' => $data['payment_reference'] ?? $transaction->payment_reference,
                'payment_proof' => $data['payment_proof'] ?? $transaction->payment_proof,
            ]);

            $this->notificationService->publishManualPaymentSubmitted($transaction->fresh());

            return $this->freshOrderWithRelations($order->id);
        });
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userId = $data['user_id'] ?? auth()->id();
            $status = $data['status'] ?? 'pending';
            $paymentMethod = $data['payment_method'] ?? 'manual';

            $offeringIds = $this->normalizeRequestedOfferingIds($data);
            if (count($offeringIds) === 0) {
                throw ValidationException::withMessages([
                    'course_offering_ids' => ['No valid course offerings provided'],
                ]);
            }

            $offerings = CourseOffering::with('course')
                ->whereIn('id', $offeringIds)
                ->get()
                ->keyBy('id');

            if ($offerings->count() !== count($offeringIds)) {
                throw ValidationException::withMessages([
                    'course_offering_ids' => ['Some course offerings were not found'],
                ]);
            }

            $subtotal = 0;
            $items = [];
            foreach ($offeringIds as $offeringId) {
                $offering = $offerings->get($offeringId);
                if (! $offering) {
                    continue;
                }

                $this->ensureOfferingPurchasable((int) $userId, (int) $offering->id);
                $price = $this->resolveOfferingSellingPrice($offering);

                $subtotal += $price;
                $items[] = [
                    'course_offering_id' => $offering->id,
                    'price' => $price,
                ];
            }

            $discount = 0;
            $voucherId = null;
            if (! empty($data['voucher_code'])) {
                $voucher = Voucher::where('code', $data['voucher_code'])->first();
                if ($voucher) {
                    $discountData = $this->applyVoucher($voucher, $subtotal);
                    $discount = $discountData['discount'];
                    $voucherId = $voucher->id;
                }
            }

            $grandTotal = max(0, $subtotal - $discount);

            $order = Order::create([
                'user_id' => $userId,
                'voucher_id' => $voucherId,
                'order_code' => $this->generateOrderCode(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'status' => $status,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            $transaction = $this->transactionService->create([
                'order_id' => $order->id,
                'amount' => $order->grand_total,
                'status' => match ($status) {
                    'completed' => 'success',
                    'cancelled' => 'failed',
                    default => 'pending',
                },
                'payment_method' => $paymentMethod,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_proof' => $data['payment_proof'] ?? null,
                'paid_at' => $status === 'completed' ? now() : null,
            ]);

            if ($status === 'pending' && $transaction) {
                $this->notificationService->publishOrderPlaced($order->fresh('user'), $transaction->fresh());
            }

            if ($status === 'completed') {
                $activatedEnrollments = $this->activateOrderEnrollments($order);

                if ($transaction) {
                    $this->notificationService->publishTransactionSuccess($transaction, $activatedEnrollments);
                }
            }

            return $this->freshOrderWithRelations($order->id);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function applyVoucher(Voucher $voucher, float $subtotal): array
    {
        if (! $voucher->is_active || ($voucher->expired_at && $voucher->expired_at->isPast())) {
            throw ValidationException::withMessages(['voucher_code' => 'Voucher is inactive or expired.']);
        }

        if ($voucher->usage_limit > 0) {
            $usedCount = Order::where('voucher_id', $voucher->id)
                ->whereIn('status', ['completed', 'processing'])
                ->count();

            if ($usedCount >= $voucher->usage_limit) {
                throw ValidationException::withMessages(['voucher_code' => 'Voucher usage limit reached.']);
            }
        }

        if ($subtotal < $voucher->min_purchase) {
            throw ValidationException::withMessages([
                'voucher_code' => "Minimum purchase of {$voucher->min_purchase} required for this voucher.",
            ]);
        }

        $discount = 0;
        if ($voucher->discount_type === 'percentage') {
            $discount = ($subtotal * $voucher->discount_amount) / 100;
            if ($voucher->max_discount > 0) {
                $discount = min($discount, $voucher->max_discount);
            }
        } else {
            $discount = $voucher->discount_amount;
        }

        return ['discount' => $discount];
    }

    private function generateOrderCode(): string
    {
        $date = now()->format('Ymd');
        $exists = true;
        $orderCode = '';

        while ($exists) {
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $orderCode = "ORD-{$date}-{$randomHex}";
            $exists = Order::where('order_code', $orderCode)->exists();
        }

        return $orderCode;
    }

    private function freshOrderWithRelations(int $orderId): Order
    {
        return Order::with([
            'items.courseOffering.course',
            'transactions',
            'voucher',
            'enrollments.courseOffering.course',
        ])->findOrFail($orderId);
    }

    private function normalizeRequestedOfferingIds(array $data): array
    {
        if (! empty($data['course_offering_id'])) {
            return [(int) $data['course_offering_id']];
        }

        if (! empty($data['course_id'])) {
            return [$this->resolvePurchasableOfferingIdByCourse((int) $data['course_id'])];
        }

        if (! empty($data['course_offering_ids']) && is_array($data['course_offering_ids'])) {
            return array_values(array_unique(array_map('intval', $data['course_offering_ids'])));
        }

        if (! empty($data['course_ids']) && is_array($data['course_ids'])) {
            $resolved = [];
            foreach ($data['course_ids'] as $courseId) {
                $resolved[] = $this->resolvePurchasableOfferingIdByCourse((int) $courseId);
            }

            return array_values(array_unique($resolved));
        }

        return [];
    }

    private function resolvePurchasableOfferingIdByCourse(int $courseId): int
    {
        $now = now();

        $offering = CourseOffering::query()
            ->with('academicPeriod')
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->whereHas('academicPeriod', function ($query) use ($now) {
                $query->where('is_active', true)
                    ->where(function ($builder) use ($now) {
                        $builder->whereNull('enrollment_open_at')->orWhere('enrollment_open_at', '<=', $now);
                    })->where(function ($builder) use ($now) {
                        $builder->whereNull('enrollment_close_at')->orWhere('enrollment_close_at', '>=', $now);
                    });
            })
            ->get()
            ->sortBy(function (CourseOffering $offering) {
                return $offering->academicPeriod?->start_at?->getTimestamp() ?? PHP_INT_MAX;
            })
            ->first();

        if (! $offering) {
            throw ValidationException::withMessages([
                'course_id' => ["No active offering is currently available for course ID: {$courseId}"],
            ]);
        }

        return (int) $offering->id;
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

    private function ensureOfferingPurchasable(int $userId, int $offeringId, ?int $excludeOrderId = null): void
    {
        $now = now();
        $offering = CourseOffering::with(['course', 'academicPeriod'])->findOrFail($offeringId);
        $course = $offering->course;
        $academicPeriod = $this->resolveOfferingAcademicPeriod($offering);

        if (! $course) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Offering does not have a valid course'],
            ]);
        }

        if (! $offering->is_active) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Course offering is not available for purchase'],
            ]);
        }

        if (! $academicPeriod->is_active) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Academic period is not active for this offering'],
            ]);
        }

        if ($academicPeriod->enrollment_open_at && $academicPeriod->enrollment_open_at->gt($now)) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment window has not opened for this offering'],
            ]);
        }

        if ($academicPeriod->enrollment_close_at && $academicPeriod->enrollment_close_at->lt($now)) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment window is closed for this offering'],
            ]);
        }

        if ($offering->capacity !== null && $offering->capacity > 0) {
            $enrolledCount = Enrollment::where('course_offering_id', $offering->id)
                ->whereIn('status', ['pending', 'active', 'completed'])
                ->where(function ($query) {
                    $query->whereNull('order_id')
                        ->orWhereHas('order', function ($orderQuery) {
                            $orderQuery->where('status', 'completed');
                        });
                })
                ->count();

            if ($enrolledCount >= $offering->capacity) {
                throw ValidationException::withMessages([
                    'course_offering_id' => ['Course offering capacity has been reached'],
                ]);
            }
        }

        $hasCompletedEnrollment = Enrollment::where('user_id', $userId)
            ->whereHas('courseOffering', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->where('status', 'completed')
            ->exists();

        if ($hasCompletedEnrollment) {
            throw ValidationException::withMessages([
                'course_id' => ["User already completed course ID: {$course->id}"],
            ]);
        }

        $hasCurrentAccess = Enrollment::where('user_id', $userId)
            ->whereHas('courseOffering', function ($query) use ($course) {
                $query->where('course_id', $course->id);
            })
            ->whereIn('status', ['pending', 'active'])
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereHas('order', function ($orderQuery) {
                        $orderQuery->where('status', 'completed');
                    });
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now);
            })
            ->exists();

        if ($hasCurrentAccess) {
            throw ValidationException::withMessages([
                'course_id' => ["User already has pending or active enrollment for course ID: {$course->id}"],
            ]);
        }

        $pendingOrCompletedOrderQuery = OrderItem::whereHas('order', function ($query) use ($userId, $excludeOrderId) {
            $query->where('user_id', $userId)
                ->whereIn('status', ['pending', 'completed']);

            if ($excludeOrderId !== null) {
                $query->where('id', '!=', $excludeOrderId);
            }
        })->where('course_offering_id', $offeringId);

        if ($pendingOrCompletedOrderQuery->exists()) {
            throw ValidationException::withMessages([
                'course_offering_id' => ["User already has pending or completed order for offering ID: {$offeringId}"],
            ]);
        }
    }

    public function activateOrderEnrollments(Order $order): Collection
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
                // Keep compatibility with existing API that still exposes expired_at.
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

    private function resolveOfferingSellingPrice(CourseOffering $offering): float
    {
        $basePrice = $offering->price !== null ? (float) $offering->price : 0.0;
        $discountPrice = $offering->discount_price !== null ? (float) $offering->discount_price : null;

        if ($discountPrice !== null && $discountPrice > 0 && $discountPrice < $basePrice) {
            return $discountPrice;
        }

        return $basePrice;
    }

    private function buildAdminListQuery(string $search = ''): Builder
    {
        return Order::query()
            ->with(['user', 'items.courseOffering.course', 'voucher', 'transactions'])
            ->where('status', '!=', 'cart')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('order_code', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('fullname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest();
    }

    private function mapOrderForAdminExport(Order $order): array
    {
        $latestTransaction = $order->transactions->sortByDesc('id')->first();
        $courseTitles = $order->items
            ->map(fn (OrderItem $item) => $item->courseOffering?->course?->title)
            ->filter(fn (?string $title) => filled($title))
            ->implode(' | ');

        $paymentMethod = collect([
            $latestTransaction?->payment_method,
            $latestTransaction?->payment_channel,
        ])->filter(fn (?string $value) => filled($value))->implode(' / ');

        $paymentReference = $latestTransaction?->payment_reference
            ?: $latestTransaction?->external_id
            ?: $latestTransaction?->invoice_code
            ?: '';

        return [
            'Kode Order' => $order->order_code,
            'Tanggal Order' => $this->formatAdminExportDate($order->created_at),
            'Status Order' => $order->status,
            'Nama Siswa' => $order->user?->fullname ?? '',
            'Email Siswa' => $order->user?->email ?? '',
            'Jumlah Item' => (string) $order->items->count(),
            'Daftar Course' => $courseTitles,
            'Kode Voucher' => $order->voucher?->code ?? '',
            'Subtotal (IDR)' => $this->formatAdminExportAmount($order->subtotal),
            'Diskon (IDR)' => $this->formatAdminExportAmount($order->discount),
            'Total Bayar (IDR)' => $this->formatAdminExportAmount($order->grand_total),
            'Status Pembayaran Terakhir' => $latestTransaction?->status ?? '',
            'Metode Pembayaran Terakhir' => $paymentMethod,
            'Invoice Terakhir' => $latestTransaction?->invoice_code ?? '',
            'Referensi Pembayaran Terakhir' => $paymentReference,
            'Bukti Pembayaran Terakhir' => $latestTransaction?->payment_proof ?? '',
            'Dibayar Pada' => $this->formatAdminExportDate($latestTransaction?->paid_at),
            'Catatan' => $order->note ?? '',
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
