<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification
{
    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'contact.received';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->replyTo($this->contactMessage->email, $this->contactMessage->name)
            ->line($content['message'])
            ->line($this->contactMessage->message)
            ->action('Open messages', url($content['link_url']));
    }

    /**
     * @return array{tenant_id: int, title: string, message: string, link_url: string, contact_message_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->contactMessage->tenant_id,
            'title' => "New message: {$this->contactMessage->topic->label()}",
            'message' => "From {$this->contactMessage->name} ({$this->contactMessage->email}).",
            'link_url' => route('admin.contact-messages.index', absolute: false),
            'contact_message_id' => $this->contactMessage->id,
        ];
    }
}
