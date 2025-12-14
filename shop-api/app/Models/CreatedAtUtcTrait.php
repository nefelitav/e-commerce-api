<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeImmutable;

trait CreatedAtUtcTrait
{
    /**
     * Override the created_at attribute to always return UTC.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function getCreatedAtAttribute(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->setTimezone('UTC') : null;
    }

    /**
     * Set the created_at attribute, converting it to UTC.
     *
     * @param mixed $value
     * @return void
     */
    public function setCreatedAtAttribute(mixed $value): void
    {
        $this->attributes['created_at'] = $value
            ? Carbon::parse($value)->setTimezone('UTC')->toDateTimeString()
            : null;
    }
}
