<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a unique, URL-friendly `slug` built from its title or name.
 *
 *  - The slug is created when the record is first saved, and follows the
 *    title or name when it is edited (fixing a typo fixes the URL). Older
 *    links that use the record id keep working.
 *  - Duplicates get a numeric suffix: "youth-ministry", "youth-ministry-2".
 *  - Route model binding accepts the slug or, for older links, the id.
 *
 * Each model says what the slug is made from via slugSource().
 */
trait HasSlug
{
    /** Text the slug is generated from (e.g. the title or full name). */
    abstract public function slugSource(): string;

    public static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            $current = (string) $model->getAttribute('slug');
            $base = Str::slug($model->slugSource());

            // New record, or the title/name changed so the slug no longer matches it
            // ("youth-ministry" or "youth-ministry-2" both still match "Youth Ministry").
            $stale = $base !== '' && ! preg_match('/^'.preg_quote(Str::limit($base, 180, ''), '/').'(-\d+)?$/', $current);

            if ($current === '' || $stale) {
                $model->setAttribute('slug', static::uniqueSlug($model->slugSource(), $model->getKey(), $model));
            }
        });
    }

    /**
     * A slug for $source that no other record of this model uses. Models
     * that define a slugUniqueness scope (e.g. per marketplace) only need
     * the slug to be unique inside that scope.
     */
    public static function uniqueSlug(string $source, mixed $ignoreKey = null, ?Model $scopeModel = null): string
    {
        $base = Str::limit(Str::slug($source), 180, '') ?: Str::lower(class_basename(static::class));
        $slug = $base;
        $suffix = 2;

        while (static::slugTaken($slug, $ignoreKey, $scopeModel ?? new static)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, mixed $ignoreKey, Model $scopeModel): bool
    {
        $query = static::query()->withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($ignoreKey !== null, fn (Builder $q) => $q->where($scopeModel->getKeyName(), '!=', $ignoreKey));

        if (method_exists($scopeModel, 'scopeSlugUniqueness')) {
            $scopeModel->scopeSlugUniqueness($query);
        }

        return $query->exists();
    }

    /** @param  Builder<static>  $query */
    public function scopeWhereSlug(Builder $query, string $slug): Builder
    {
        return $query->where($this->qualifyColumn('slug'), $slug);
    }

    /**
     * Match a slug, or the id for links made before slugs existed
     * (e.g. ?department=youth-ministry on the staff pages).
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereSlugOrKey(Builder $query, string $value): Builder
    {
        return $query->where(fn (Builder $q) => $q->where($this->qualifyColumn('slug'), $value)
            ->orWhere($this->getQualifiedKeyName(), $value));
    }

    /**
     * {model} route parameters resolve by slug first, then by id, so links
     * built before slugs existed still work.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->newQuery()->where('slug', $value)->first()
            ?? $this->newQuery()->whereKey($value)->first();
    }
}
