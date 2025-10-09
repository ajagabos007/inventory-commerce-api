<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordToken extends Notification
{
    use Queueable;

    /**
     * The reset password token
     */
    public int $token;

    public ?User $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(int $token, ?User $user = null)
    {
        $this->token = $token;
        $this->user = $user;

    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $greeting = ! is_null($this->user) ? Lang::get('Hello '.$this->user->first_name).',' : Lang::get('Hello!');

        return (new MailMessage)
            ->greeting($greeting)
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->line(Lang::get('Your reset code is: ').'**'.$this->token.'**')
            ->line(Lang::get('This password reset token will expire in :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
