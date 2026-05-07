<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Course;
use App\Models\OrderItem;
use App\Models\Enrollment;
use App\Models\Voucher;
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
        return Order::with(['user', 'items.course', 'voucher', 'transactions'])
            ->where('status', '!=', 'cart')
            ->latest()
            ->paginate(10);
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
                $this->activateOrderEnrollments($order);

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
            ->where('status', '!=', 'cart')
            ->latest()
            ->paginate(10);
    }

    public function findByIdForStudent(int $id, int $userId)
    {
        return Order::with(['items.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->where('status', '!=', 'cart')
            ->findOrFail($id);
    }

    public function getCartForStudent(int $userId): ?Order
    {
        return Order::with(['items.course', 'transactions', 'voucher'])
            ->where('user_id', $userId)
            ->where('status', 'cart')
            ->latest()
            ->first();
    }

    public function addCourseToCart(int $userId, int $courseId): Order
    {
        return DB::transaction(function () use ($userId, $courseId) {
            $course = Course::findOrFail($courseId);
            $this->ensureCoursePurchasable($userId, $course->id);

            $cart = $this->lockCartForUser($userId, true);
            if (! $cart) {
                throw new \Exception('Failed to initialize cart');
            }

            $alreadyInCart = $cart->items()->where('course_id', $course->id)->exists();
            if ($alreadyInCart) {
                throw ValidationException::withMessages([
                    'course_id' => ['Course already exists in cart'],
                ]);
            }

            $price = $this->resolveCourseSellingPrice($course);
            $cart->items()->create([
                'course_id' => $course->id,
                'price' => $price,
            ]);

            $this->recalculateCartTotals($cart);

            return $this->freshOrderWithRelations($cart->id);
        });
    }

    public function removeCourseFromCart(int $userId, int $courseId): ?Order
    {
        return DB::transaction(function () use ($userId, $courseId) {
            $cart = $this->lockCartForUser($userId, false);
            if (! $cart) {
                return null;
            }

            $deleted = $cart->items()->where('course_id', $courseId)->delete();
            if ($deleted === 0) {
                throw ValidationException::withMessages([
                    'course_id' => ['Course not found in cart'],
                ]);
            }

            $hasItems = $cart->items()->exists();
            if (! $hasItems) {
                $cart->delete();
                return null;
            }

            $this->recalculateCartTotals($cart);

            return $this->freshOrderWithRelations($cart->id);
        });
    }

    public function checkoutCart(int $userId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $data) {
            $cart = $this->lockCartForUser($userId, false);
            if (! $cart) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty'],
                ]);
            }

            $cart->load('items.course');
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty'],
                ]);
            }

            $subtotal = 0;
            foreach ($cart->items as $item) {
                $course = $item->course ?? Course::findOrFail($item->course_id);
                $this->ensureCoursePurchasable($userId, (int) $course->id, (int) $cart->id);

                $latestPrice = $this->resolveCourseSellingPrice($course);
                if ((float) $item->price !== (float) $latestPrice) {
                    $item->update(['price' => $latestPrice]);
                }
                $subtotal += $latestPrice;
            }

            $discount = 0;
            $voucherId = null;
            if (!empty($data['voucher_code'])) {
                $voucher = Voucher::where('code', $data['voucher_code'])->first();
                if (!$voucher) {
                    throw ValidationException::withMessages([
                        'voucher_code' => ['Voucher code is invalid'],
                    ]);
                }

                $discountData = $this->applyVoucher($voucher, $subtotal);
                $discount = $discountData['discount'];
                $voucherId = $voucher->id;
            }

            $grandTotal = max(0, $subtotal - $discount);

            $cart->voucher_id = $voucherId;
            $cart->subtotal = $subtotal;
            $cart->discount = $discount;
            $cart->note = $data['note'] ?? null;
            $cart->grand_total = $grandTotal;
            $cart->status = 'pending';
            $cart->save();

            $this->transactionService->create([
                'order_id' => $cart->id,
                'amount' => $cart->grand_total,
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'manual',
                'paid_at' => null,
            ]);

            return $this->freshOrderWithRelations($cart->id);
        });
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

            return $this->freshOrderWithRelations($order->id);
        });
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userId = $data['user_id'] ?? auth()->id();
            $courseIds = $data['course_ids'];
            $status = $data['status'] ?? 'pending';
            $paymentMethod = $data['payment_method'] ?? 'manual';

            // 1. Ambil data kursus (Security: jangan percaya harga dari frontend)
            $courses = Course::whereIn('id', $courseIds)->get();
            
            if ($courses->isEmpty()) {
                throw new \Exception('No valid courses found for the given IDs.');
            }

            // 2. Validasi Duplikasi
            foreach ($courses as $course) {
                $this->ensureCoursePurchasable($userId, (int) $course->id);
            }

            // 3. Hitung Subtotal
            $subtotal = 0;
            $items = [];

            foreach ($courses as $course) {
                $price = $this->resolveCourseSellingPrice($course);
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
            if ($status !== 'cart') {
                $this->transactionService->create([
                    'order_id' => $order->id,
                    'amount' => $order->grand_total,
                    'status' => $status === 'completed' ? 'success' : 'pending',
                    'payment_method' => $paymentMethod,
                    'paid_at' => $status === 'completed' ? now() : null,
                ]);
            }

            if ($status === 'completed') {
                $this->activateOrderEnrollments($order);
            }

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

    private function freshOrderWithRelations(int $orderId): Order
    {
        return Order::with(['items.course', 'transactions', 'voucher'])
            ->findOrFail($orderId);
    }

    private function ensureCoursePurchasable(int $userId, int $courseId, ?int $excludeOrderId = null): void
    {
        $course = Course::findOrFail($courseId);
        if ($course->status !== 'published') {
            throw ValidationException::withMessages([
                'course_id' => ["Course ID {$courseId} is not available for purchase"],
            ]);
        }

        $hasActiveAccess = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        if ($hasActiveAccess) {
            throw ValidationException::withMessages([
                'course_id' => ["User already has active enrollment for course ID: {$courseId}"],
            ]);
        }

        $pendingOrCompletedOrderQuery = OrderItem::whereHas('order', function ($query) use ($userId, $excludeOrderId) {
            $query->where('user_id', $userId)
                ->whereIn('status', ['pending', 'completed']);

            if ($excludeOrderId !== null) {
                $query->where('id', '!=', $excludeOrderId);
            }
        })->where('course_id', $courseId);

        if ($pendingOrCompletedOrderQuery->exists()) {
            throw ValidationException::withMessages([
                'course_id' => ["User already has pending or completed order for course ID: {$courseId}"],
            ]);
        }
    }

    private function lockCartForUser(int $userId, bool $createIfMissing): ?Order
    {
        $carts = Order::where('user_id', $userId)
            ->where('status', 'cart')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $cart = $carts->first();
        if ($cart && $carts->count() > 1) {
            foreach ($carts->slice(1) as $duplicateCart) {
                $duplicateCart->delete();
            }
        }

        if (! $cart && $createIfMissing) {
            $cart = Order::create([
                'user_id' => $userId,
                'voucher_id' => null,
                'order_code' => $this->generateOrderCode(),
                'subtotal' => 0,
                'discount' => 0,
                'grand_total' => 0,
                'status' => 'cart',
            ]);
        }

        return $cart;
    }

    private function recalculateCartTotals(Order $cart): void
    {
        $subtotal = (float) $cart->items()->sum('price');
        $cart->voucher_id = null;
        $cart->discount = 0;
        $cart->subtotal = $subtotal;
        $cart->grand_total = $subtotal;
        $cart->save();
    }

    private function activateOrderEnrollments(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            Enrollment::updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'course_id' => $item->course_id,
                ],
                [
                    'order_id' => $order->id,
                    'status' => 'active',
                ]
            );
        }
    }

    private function resolveCourseSellingPrice(Course $course): float
    {
        $basePrice = (float) ($course->price ?? 0);
        $discountPrice = $course->discount_price !== null ? (float) $course->discount_price : null;

        if ($discountPrice !== null && $discountPrice > 0 && $discountPrice < $basePrice) {
            return $discountPrice;
        }

        return $basePrice;
    }
}
