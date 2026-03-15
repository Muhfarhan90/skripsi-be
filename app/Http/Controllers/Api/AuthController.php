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
            return response()->json(['success' => false, 'message' => 'Invalid or expired verification link'], 400);
        }

        $user = User::findOrFail($id);

        $result = $this->authService->verifyEmail($user);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
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
}
