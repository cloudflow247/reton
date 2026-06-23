<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a string UUID primary key, assigned on creation.
 */
trait HasUuidKey
{
    public static function bootHasUuidKey(): void
    {
        static::creating(function ($model): void {
            if (empty($model->getKey())) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuidKey(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
