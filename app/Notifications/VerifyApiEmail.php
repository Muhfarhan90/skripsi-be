<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyApiEmail extends Notification implements ShouldQueue
{
    use Queueable;

    protected $verificationUrl;

    protected $frontendUrl;

    public function __construct(string $verificationUrl, ?string $frontendUrl = null)
    {
        $this->verificationUrl = $verificationUrl;
        $this->frontendUrl = $frontendUrl;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = $this->buildActionUrl();

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Click the button below to verify your email address.')
            ->action('Verify Email', $url)
            ->line('If you did not create an account, no further action is required.');
    }

    protected function buildActionUrl(): string
    {
        if ($this->frontendUrl) {
            $frontend = rtrim($this->frontendUrl, '/');
            $frontendPath = parse_url($frontend, PHP_URL_PATH) ?: '';
            $verifyPath = str_contains($frontendPath, '/verify-email') ? '' : '/verify-email';

            // Front-end link includes the signed API URL as query param for FE-driven verify flow.
            return $frontend . $verifyPath . '?verify_url=' . urlencode($this->verificationUrl);
        }

        return $this->verificationUrl;
    }
}
