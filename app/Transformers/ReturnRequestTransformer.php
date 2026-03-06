<?php

namespace App\Transformers;

use App\Dto\ReturnRequest\ReturnRequest;

final readonly class ReturnRequestTransformer
{
    /**
     * @return array<string, mixed>
     */
    public function transform(ReturnRequest $returnRequest): array
    {
        return [
            'id' => $returnRequest->id,
            'order_id' => $returnRequest->orderId,
            'user_id' => $returnRequest->userId,
            'reason' => $returnRequest->reason,
            'status' => $returnRequest->status->value,
            'admin_notes' => $returnRequest->adminNotes,
            'created_at' => $returnRequest->createdAt,
        ];
    }
}
