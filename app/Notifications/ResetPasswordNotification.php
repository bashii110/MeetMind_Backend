<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Points the reset link at the configurable frontend URL (deep link or
 * static web fallback — see config/frontend.php) instead of Laravel's
 * default Blade `password.reset` route, since this backend is API-only.
 * The Flutter app (or fallback web page) then submits the token + new
 * password to POST /api/v1/auth/reset-password.
 */
class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = sprintf(
            '%s?token=%s&email=%s',
            config('frontend.reset_password_url'),
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset()),
        );

        return (new MailMessage)
            ->subject('Reset your MeetMind AI password')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
