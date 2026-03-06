<?php

namespace App\Repositories\ReturnRequest;

use App\Dto\ReturnRequest\ReturnRequest;
use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\ReturnRequestNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReturnRequestRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReturnRequest>
     */
    public function getAll(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
    ): LengthAwarePaginator;

    public function findById(int $id): ?ReturnRequest;

    public function findByOrderId(int $orderId): ?ReturnRequest;

    public function persist(UnpersistedReturnRequest $returnRequest): ReturnRequest;

    /**
     * @throws ReturnRequestNotFoundException
     */
    public function updateStatus(int $id, ReturnRequestStatus $status, ?string $adminNotes = null): ReturnRequest;
}
