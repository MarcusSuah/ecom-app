<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An admin's announcement, in the members' notification bell and
 * optionally by email. Queued: an audience can be large.
 */
class MarketplaceAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement, public bool $byEmail = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'announcement';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->announcement->title)
            ->line($this->announcement->body);

        return $this->announcement->link_url ? $mail->action('Read more', url($this->announcement->link_url)) : $mail;
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string|null, announcement_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->announcement->tenant_id,
            'title' => $this->announcement->title,
            'message' => $this->announcement->body,
            'link_url' => $this->announcement->link_url,
            'announcement_id' => $this->announcement->id,
        ];
    }
}
