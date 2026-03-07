<?php

namespace App\Dto\ReturnRequest;

use App\Enums\ReturnRequestStatus;

final readonly class UnpersistedReturnRequest
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $reason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'status' => ReturnRequestStatus::Pending->value,
        ];
    }
}
