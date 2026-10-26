<?php

namespace App\Models\Concerns;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Records changes people make to this model in the audit log: what was
 * created, which fields changed (old and new values) and what was deleted.
 * Changes without a signed-in user (seeding, webhooks, scheduled jobs) are
 * system work and are not recorded. Hidden attributes never are.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::audit($model, 'create', null, self::auditable($model, $model->getAttributes())));

        static::updated(function (Model $model): void {
            $changed = self::auditable($model, $model->getChanges());

            if ($changed !== []) {
                self::audit($model, 'update', Arr::only(self::auditable($model, $model->getOriginal()), array_keys($changed)), $changed);
            }
        });

        static::deleted(fn (Model $model) => self::audit($model, 'delete', self::auditable($model, $model->getAttributes()), null));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private static function audit(Model $model, string $action, ?array $old, ?array $new): void
    {
        if (! Auth::check()) {
            return;
        }

        $name = Str::headline(class_basename($model));
        $label = $model->getAttribute('name') ?? $model->getAttribute('number') ?? "#{$model->getKey()}";
        $verb = ['create' => 'Created', 'update' => 'Updated', 'delete' => 'Deleted'][$action];

        Audit::record($action, $model->getTable(), "{$verb} {$name} {$label}", $old, $new, $model->getKey());
    }

    /**
     * Drop timestamps, hidden fields and anything the model lists in
     * $auditExclude.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function auditable(Model $model, array $attributes): array
    {
        $exclude = [
            'created_at', 'updated_at', 'remember_token',
            ...$model->getHidden(),
            ...(property_exists($model, 'auditExclude') ? $model->auditExclude : []),
        ];

        return Arr::except($attributes, $exclude);
    }
}
