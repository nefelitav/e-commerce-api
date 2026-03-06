<?php

namespace App\Dto\ReturnRequest;

use App\Enums\ReturnRequestStatus;
use App\Models\ReturnRequest\ReturnRequestModel;

final readonly class ReturnRequest
{
    public function __construct(
        public int $id,
        public int $orderId,
        public int $userId,
        public string $reason,
        public ReturnRequestStatus $status,
        public ?string $adminNotes,
        public string $createdAt,
    ) {}

    public static function fromModel(ReturnRequestModel $model): self
    {
        return new self(
            id: $model->id,
            orderId: $model->order_id,
            userId: $model->user_id,
            reason: $model->reason,
            status: $model->status,
            adminNotes: $model->admin_notes,
            createdAt: (string) $model->created_at,
        );
    }
}
