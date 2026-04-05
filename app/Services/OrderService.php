<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Course;
use App\Models\OrderItem;
use App\Models\Enrollment;
use App\Models\Voucher;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function getAllForAdmin()
    {
        return Order::with(['user', 'items.course', 'voucher', 'transactions'])->latest()->paginate(10);
    }

    public function findByIdForAdmin(int $id)
    {
        return Order::with(['user', 'items.course', 'transactions', 'voucher'])->findOrFail($id);
    }

    public function updateStatus(int $id, string $status): Order
    {
        return DB::transaction(function () use ($id, $status) {
            $order = Order::findOrFail($id);
            $oldStatus = $order->status;

            if ($oldStatus === $status) {
                return $order;
            }

            // 1. Update Order Status
            $order->update(['status' => $status]);

            // 2. Jika status berubah menjadi 'completed'
            if ($status === 'completed') {
                // 2a. Buat atau Aktifkan Enrollments berdasarkan Order Items
                foreach ($order->items as $item) {
                    Enrollment::updateOrCreate(
                        [
                            'user_id' => $order->user_id,
                            'course_id' => $item->course_id,
                            'order_id' => $order->id,
                        ],
                        [
                            'status' => 'active',
                        ]
                    );
                }

                // 2b. Tandai Transaksi 'success'
                $order->transactions()->where('status', 'pending')->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
            }

            // 3. Jika status berubah menjadi 'cancelled'
            if ($status === 'cancelled') {
                $order->enrollments()->update(['status' => 'cancelled']);
                $order->transactions()->where('status', 'pending')->update(['status' => 'failed']);
            }

            return $order->fresh(['items.course', 'enrollments', 'transactions', 'voucher']);
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

    public function getAllForStudent(int $userId)
    {
        return Order::with(['items.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(10);
    }

    public function findByIdForStudent(int $id, int $userId)
    {
        return Order::with(['items.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userId = $data['user_id'] ?? auth()->id();
            $courseIds = $data['course_ids'];
            $status = $data['status'] ?? 'pending';

            // 1. Ambil data kursus (Security: jangan percaya harga dari frontend)
            $courses = Course::whereIn('id', $courseIds)->get();
            
            if ($courses->isEmpty()) {
                throw new \Exception('No valid courses found for the given IDs.');
            }

            // 2. Validasi Duplikasi
            foreach ($courses as $course) {
                // Access aktif
                $hasActiveAccess = Enrollment::where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->where('status', 'active')
                    ->exists();
                
                if ($hasActiveAccess) {
                    throw new \Exception("User already has an active enrollment for course ID: {$course->id}");
                }

                // Order pending
                $hasPendingOrder = OrderItem::whereHas('order', function ($query) use ($userId) {
                        $query->where('user_id', $userId)
                            ->where('status', 'pending');
                    })
                    ->where('course_id', $course->id)
                    ->exists();

                if ($hasPendingOrder) {
                    throw new \Exception("User already has a pending order for course ID: {$course->id}. Please complete or cancel the previous order.");
                }
            }

            // 3. Hitung Subtotal
            $subtotal = 0;
            $items = [];

            foreach ($courses as $course) {
                $price = $course->discount_price ?? $course->price;
                $subtotal += $price;
                $items[] = [
                    'course_id' => $course->id,
                    'price' => $price,
                ];
            }

            // 3.1 Proses Voucher
            $discount = 0;
            $voucherId = null;

            if (!empty($data['voucher_code'])) {
                $voucher = Voucher::where('code', $data['voucher_code'])->first();
                if ($voucher) {
                    $discountData = $this->applyVoucher($voucher, $subtotal);
                    $discount = $discountData['discount'];
                    $voucherId = $voucher->id;
                }
            }

            $grandTotal = max(0, $subtotal - $discount);

            // 4. Buat Order (Header)
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

            // 5. Buat Order Items (Details)
            foreach ($items as $item) {
                $order->items()->create([
                    'course_id' => $item['course_id'],
                    'price' => $item['price'],
                ]);
            }

            // 6. Buat Transaksi Awal
            $this->transactionService->create([
                'order_id' => $order->id,
                'amount' => $order->grand_total,
                'status' => $status === 'completed' ? 'success' : 'pending',
                'payment_method' => $data['payment_method'] ?? 'manual',
                'paid_at' => $status === 'completed' ? now() : null,
            ]);

            return $order->load(['items.course', 'transactions', 'voucher']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function applyVoucher(Voucher $voucher, float $subtotal): array
    {
        if (!$voucher->is_active || ($voucher->expired_at && $voucher->expired_at->isPast())) {
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
            throw ValidationException::withMessages(['voucher_code' => "Minimum purchase of {$voucher->min_purchase} required for this voucher."]);
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
}
