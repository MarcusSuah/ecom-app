<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the marketplace's user managers that someone signed up and is
 * waiting for approval.
 */
class AccountAwaitingApproval extends Notification
{
    public function __construct(public User $account, public int $tenantId) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'account.pending';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('Review accounts', url($content['link_url']));
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string, user_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'title' => 'New account waiting for approval',
            'message' => "{$this->account->name} ({$this->account->email}) signed up and is waiting for approval.",
            'link_url' => route('admin.users.index', ['status' => 'pending'], absolute: false),
            'user_id' => $this->account->id,
        ];
    }
}
