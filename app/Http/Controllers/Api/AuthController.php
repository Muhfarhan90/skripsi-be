<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\ResendVerificationEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Register successfully',
            'data' => [
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Login successfully',
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user'])
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logout successfully',
        ]);
    }


    public function verifyEmail(Request $request, $id)
    {
        // validate signed URL
        if (! URL::hasValidSignature($request)) {
            return $this->verificationResponse($request, false, 'Invalid or expired verification link', 400);
        }

        $user = User::findOrFail($id);

        try {
            $result = $this->authService->verifyEmail($user);
            return $this->verificationResponse($request, true, $result['message']);
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first() ?: 'Email verification failed';
            return $this->verificationResponse($request, false, $message, 422);
        }
    }

    public function resendVerificationEmail(ResendVerificationEmailRequest $request)
    {
        $result = $this->authService->resendVerificationEmail($request->input('email'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'retry_after' => $result['retry_after'] ?? null
            ]
        ]);
    }
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $result = $this->authService->forgotPassword($request->input('email'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->authService->resetPassword($request->input('email'), $request->input('token'), $request->input('password'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    private function verificationResponse(Request $request, bool $success, string $message, int $status = 200)
    {
        // Keep JSON contract for FE/API calls, but redirect browser clicks to FE success/error page.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
            ], $status);
        }

        $frontendBaseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $query = http_build_query([
            'status' => $success ? 'success' : 'error',
            'message' => $message,
        ]);

        return redirect()->away($frontendBaseUrl . '/verify-email?' . $query);
    }
}
