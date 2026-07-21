<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class VerifyEmail extends VerifyEmailNotification
{
    use Queueable;

    /**
     * Build the Filament-compatible signed verification URL.
     *
     * Delegates to Filament so route name, email hash, and expiration
     * stay aligned with the panel auth stack (config auth.verification.expire).
     *
     * @param  mixed  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        return Filament::getVerifyEmailUrl($notifiable);
    }

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.email_verification.subject'))
            ->greeting(__('notifications.email_verification.greeting'))
            ->line(__('notifications.email_verification.introduction'))
            ->line(__('notifications.email_verification.instruction'))
            ->action(__('notifications.email_verification.action'), $url)
            ->line(__('notifications.email_verification.ignore'))
            ->salutation(new HtmlString(
                e(__('notifications.email_verification.closing'))
                .'<br>'
                .e(__('notifications.email_verification.team'))
            ));
    }
}
