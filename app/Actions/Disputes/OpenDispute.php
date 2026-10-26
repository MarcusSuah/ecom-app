<?php

namespace App\Actions\Disputes;

use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpenDispute
{
    /**
     * The buyer reports a problem while the payment is still held. One
     * dispute per order; it stops the payment being released until an
     * admin decides. Admins and the seller are told. Photos, if any, are
     * kept privately as proof (resized like gallery photos).
     *
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Order $order, User $buyer, DisputeReason $reason, string $details, array $photos = []): Dispute
    {
        if ($order->dispute()->exists()) {
            throw ValidationException::withMessages(['reason' => 'A dispute has already been opened for this order.']);
        }

        $held = $order->payments()->where('status', PaymentStatus::HELD)->exists();

        if (! $held) {
            throw ValidationException::withMessages(['reason' => 'Disputes can only be opened while the payment is held.']);
        }

        $dispute = new Dispute;
        $dispute->forceFill([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'opened_by' => $buyer->id,
            'reason' => $reason,
            'details' => $details,
            'photos' => $this->storePhotos($order, $photos) ?: null,
            'status' => DisputeStatus::OPEN,
        ])->save();

        // The roles relation is limited to this marketplace (Spatie teams), so only its admins are told.
        $admins = User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'disputes.resolve'))
            ->get();

        Notification::send($admins->push($order->seller->owner)->unique('id'), new DisputeOpened($dispute, $order->tenant_id));

        return $dispute;
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<string>
     */
    private function storePhotos(Order $order, array $photos): array
    {
        $disk = Storage::disk(Dispute::PHOTO_DISK);

        return array_map(function (UploadedFile $photo) use ($disk, $order): string {
            $path = "disputes/{$order->id}/".Str::lower(Str::ulid()).'.jpg';
            $disk->put($path, ImageResizer::fit($photo->getRealPath(), 1600));

            return $path;
        }, array_slice($photos, 0, Dispute::MAX_PHOTOS));
    }
}
