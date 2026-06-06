<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Services\MidtransPaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransPaymentService $midtransPaymentService)
    {
        try {
            $transaction = $midtransPaymentService->handleNotification($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Midtrans notification processed successfully',
                'data' => new TransactionResource($transaction),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        } catch (Throwable $exception) {
            Log::error('Midtrans notification processing failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Midtrans notification processing failed',
            ], 500);
        }
    }
}
