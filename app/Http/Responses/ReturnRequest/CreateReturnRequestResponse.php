<?php

namespace App\Http\Responses\ReturnRequest;

use App\Http\Responses\ArrayableResponse;

final readonly class CreateReturnRequestResponse implements ArrayableResponse
{
    /**
     * @param  array<string, mixed>  $returnRequest
     */
    public function __construct(
        private array $returnRequest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->returnRequest,
            'message' => 'Return request created successfully',
        ];
    }
}
