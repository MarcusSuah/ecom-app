<?php

namespace App\Actions\Chat;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SendMessage
{
    /**
     * Post a message with optional image/PDF attachment (private disk).
     * The page refreshes by polling; MessageSent is broadcast as well, so
     * a WebSocket server (Reverb) can deliver it live once it is added.
     */
    public function handle(Conversation $conversation, User $sender, ?string $body, ?UploadedFile $attachment = null): Message
    {
        $body = $body !== null ? trim($body) : null;

        if (($body === null || $body === '') && $attachment === null) {
            throw ValidationException::withMessages(['body' => 'Write a message or attach a file.']);
        }

        $message = new Message(['body' => $body ?: null]);
        $message->conversation()->associate($conversation);
        $message->sender()->associate($sender);

        if ($attachment) {
            $message->fill([
                'attachment_path' => $attachment->store("conversations/{$conversation->id}", Message::ATTACHMENT_DISK)
                    ?: throw new RuntimeException('The attachment could not be stored.'),
                'attachment_name' => $attachment->getClientOriginalName(),
                'attachment_mime' => (string) $attachment->getMimeType(),
                'attachment_size' => (int) $attachment->getSize(),
            ]);
        }

        try {
            $message->save();
        } catch (Throwable $exception) {
            if ($message->attachment_path) {
                Storage::disk(Message::ATTACHMENT_DISK)->delete($message->attachment_path);
            }

            throw $exception;
        }

        $conversation->update(['last_message_at' => $message->created_at]);

        MessageSent::dispatch($message);

        return $message;
    }
}
