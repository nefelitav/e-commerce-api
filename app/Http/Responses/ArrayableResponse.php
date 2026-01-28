<?php

namespace App\Http\Responses;

interface ArrayableResponse
{
    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array;
}
