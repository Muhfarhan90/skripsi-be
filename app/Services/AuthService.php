<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Notifications\VerifyApiEmail;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data)
    {
        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'fullname' => $data['fullname'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
                'is_active' => true
            ]);

            // create a signed verification URL (API route)
            $signedUrl = URL::temporarySignedRoute(
                'auth.verify',
                Carbon::now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            );

            // optional frontend URL (if you want the link to point to frontend)
            $frontend = env('FRONTEND_URL');

            $response = [
                'user' => $user,
                'message' => 'Registration successful, please check your email for verification link',
                'signed_url' => $signedUrl,
                'frontend_url' => $frontend ?: null,
            ];

            // in local environment return verification link for simulation
            if (config('app.env') === 'local' || config('app.debug')) {
                $response['verification_url'] = $signedUrl;
                if ($frontend) {
                    $response['frontend_verification_url'] = rtrim($frontend, '/') . '/?verify_url=' . urlencode($signedUrl);
                }
            }

            return $response;
        });

        // Notification should run in background queue and must not block registration success.
        try {
            $result['user']->notify(new VerifyApiEmail($result['signed_url'], $result['frontend_url']));
            $result['email_notification_status'] = 'queued';
        } catch (\Throwable $e) {
            report($e);
            $result['email_notification_status'] = 'failed_to_queue';
            $result['message'] = 'Registration successful, but we could not queue verification email. Please use resend verification endpoint.';
        }

        unset($result['signed_url'], $result['frontend_url']);

        return $result;
    }

    public function login(array $data)
    {
        if (!Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials']
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account is inactive']
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email not verified']
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
    }

    public function verifyEmail(User $user)
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email already verified']
            ]);
        }

        $user->markEmailAsVerified();

        return [
            'message' => 'Email verified successfully'
        ];
    }

    public function resendVerificationEmail(string $email)
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['User not found'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email already verified']
            ]);
        }

        $cooldownSeconds = 60;
        $throttleKey = 'auth:resend-verification:' . sha1(strtolower($user->email));

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Please wait {$retryAfter} seconds before requesting another verification email."],
                'retry_after' => [$retryAfter],
            ]);
        }

        RateLimiter::hit($throttleKey, $cooldownSeconds);

        $signedUrl = URL::temporarySignedRoute(
            'auth.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $frontend = env('FRONTEND_URL');
        try {
            $user->notify(new VerifyApiEmail($signedUrl, $frontend ?: null));
            $notificationStatus = 'queued';
        } catch (\Throwable $e) {
            report($e);
            $notificationStatus = 'failed_to_queue';
        }

        $response = [
            'message' => 'Verification email sent',
            'retry_after' => $cooldownSeconds,
            'email_notification_status' => $notificationStatus,
        ];
        if (config('app.env') === 'local' || config('app.debug')) {
            $response['verification_url'] = $signedUrl;
            if ($frontend) {
                $response['frontend_verification_url'] = rtrim($frontend, '/') . '/?verify_url=' . urlencode($signedUrl);
            }
        }

        return $response;
    }

    public function forgotPassword($email)
    {
        $status = Password::sendResetLink([
            'email' => $email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return [
            'message' => 'Password reset email sent'
        ];
    }

    public function resetPassword($email, $token, $password)
    {
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return [
            'message' => 'Password reset successfully'
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword)
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['New password must be different from current password'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'remember_token' => Str::random(60),
        ])->save();

        // Revoke all API tokens after password change for security.
        $user->tokens()->delete();

        return [
            'message' => 'Password changed successfully. Please login again.',
        ];
    }
}
