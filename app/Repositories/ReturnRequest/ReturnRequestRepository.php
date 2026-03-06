<?php

namespace App\Repositories\ReturnRequest;

use App\Dto\ReturnRequest\ReturnRequest;
use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Enums\ReturnRequestStatus;
use App\Exceptions\ReturnRequestNotFoundException;
use App\Models\ReturnRequest\ReturnRequestModel;
use Illuminate\Pagination\LengthAwarePaginator;

class ReturnRequestRepository implements ReturnRequestRepositoryInterface
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
    ): LengthAwarePaginator {
        $query = ReturnRequestModel::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        $query->orderBy($sort, $order);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn (ReturnRequestModel $model) => ReturnRequest::fromModel($model));

        return new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    public function findById(int $id): ?ReturnRequest
    {
        /** @var ReturnRequestModel|null $model */
        $model = ReturnRequestModel::find($id);

        return $model ? ReturnRequest::fromModel($model) : null;
    }

    public function findByOrderId(int $orderId): ?ReturnRequest
    {
        /** @var ReturnRequestModel|null $model */
        $model = ReturnRequestModel::query()->where('order_id', $orderId)->first();

        return $model ? ReturnRequest::fromModel($model) : null;
    }

    public function persist(UnpersistedReturnRequest $returnRequest): ReturnRequest
    {
        /** @var ReturnRequestModel $model */
        $model = ReturnRequestModel::create($returnRequest->toArray());

        return ReturnRequest::fromModel($model);
    }

    /**
     * @throws ReturnRequestNotFoundException
     */
    public function updateStatus(int $id, ReturnRequestStatus $status, ?string $adminNotes = null): ReturnRequest
    {
        /** @var ReturnRequestModel|null $model */
        $model = ReturnRequestModel::query()->where('id', $id)->first();

        if (! $model) {
            throw new ReturnRequestNotFoundException($id);
        }

        $updateData = ['status' => $status->value];
        if ($adminNotes !== null) {
            $updateData['admin_notes'] = $adminNotes;
        }

        $model->update($updateData);
        $model->refresh();

        return ReturnRequest::fromModel($model);
    }
}
