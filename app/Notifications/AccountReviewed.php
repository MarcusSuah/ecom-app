<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An administrator approved or turned down a new account.
 */
class AccountReviewed extends Notification
{
    public function __construct(public bool $approved, public ?string $reason = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : 'there';

        if ($this->approved) {
            return (new MailMessage)
                ->subject('Your account is approved')
                ->greeting("Hello {$name},")
                ->line('Your account has been approved. You can now order, send requests and use your dashboard.')
                ->action('Start shopping', route('home'));
        }

        $mail = (new MailMessage)
            ->subject('Your account was not approved')
            ->greeting("Hello {$name},")
            ->line('We could not approve your account.');

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail->line('Reply to this email or use the contact form if you think this is a mistake.');
    }
}
