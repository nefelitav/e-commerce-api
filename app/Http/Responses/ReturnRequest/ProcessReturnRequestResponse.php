<?php

namespace App\Http\Responses\ReturnRequest;

use App\Http\Responses\ArrayableResponse;

final readonly class ProcessReturnRequestResponse implements ArrayableResponse
{
    /**
     * @param  array<string, mixed>  $returnRequest
     */
    public function __construct(
        private array $returnRequest,
        private string $action,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->returnRequest,
            'message' => "Return request {$this->action} successfully",
        ];
    }
}
