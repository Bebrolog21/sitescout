<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $url = sprintf(
            '%s/reset-password?token=%s&email=%s',
            $frontendUrl,
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset()),
        );

        $expireMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage())
            ->subject('Восстановление пароля · SiteScout')
            ->greeting('Здравствуйте!')
            ->line('Вы получили это письмо, потому что был запрошен сброс пароля для вашей учётной записи.')
            ->action('Сбросить пароль', $url)
            ->line(sprintf('Ссылка действительна %d минут.', $expireMinutes))
            ->line('Если вы не запрашивали сброс — просто проигнорируйте это письмо.');
    }
}
