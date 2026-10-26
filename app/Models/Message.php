<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read through its conversation, which is tenant-scoped.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string|null $body
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property-read Conversation $conversation
 * @property-read User $sender
 */
#[Fillable(['body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'])]
class Message extends Model
{
    public const ATTACHMENT_DISK = 'local';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
