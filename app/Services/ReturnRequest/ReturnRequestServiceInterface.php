<?php

namespace App\Services\ReturnRequest;

use App\Dto\ReturnRequest\ReturnRequest;
use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Exceptions\InvalidReturnRequestStateException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ReturnRequestNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReturnRequestServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReturnRequest>
     */
    public function listReturnRequests(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'asc',
        array $filters = [],
    ): LengthAwarePaginator;

    public function getReturnRequestById(int $id): ?ReturnRequest;

    /**
     * @throws OrderNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function createReturnRequest(UnpersistedReturnRequest $returnRequest): ReturnRequest;

    /**
     * @throws ReturnRequestNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function approveReturnRequest(int $id, ?string $adminNotes = null): ReturnRequest;

    /**
     * @throws ReturnRequestNotFoundException
     * @throws InvalidReturnRequestStateException
     */
    public function rejectReturnRequest(int $id, ?string $adminNotes = null): ReturnRequest;
}
