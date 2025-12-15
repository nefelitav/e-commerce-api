<?php

namespace App\Http\Responses\Category;

interface ArrayableResponse
{
    /**
     * @return  array<string, mixed>
     */
    public function toArray(): array;
}
