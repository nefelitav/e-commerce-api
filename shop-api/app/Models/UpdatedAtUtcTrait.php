<?php

namespace App\Models;

use Carbon\Carbon;

trait UpdatedAtUtcTrait
{
    /**
     * Override the updated_at attribute to always return UTC.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function getUpdatedAtAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->setTimezone('UTC') : null;
    }

    /**
     * Set the updated_at attribute, converting it to UTC.
     *
     * @param mixed $value
     * @return void
     */
    public function setUpdatedAtAttribute(mixed $value): void
    {
        $this->attributes['updated_at'] = $value
            ? Carbon::parse($value)->setTimezone('UTC')->toDateTimeString()
            : null;
    }
}
