<?php

namespace App\Models\ReturnRequest;

use App\Enums\ReturnRequestStatus;
use App\Models\CreatedAtUtcTrait;
use App\Models\Order\OrderModel;
use App\Models\UpdatedAtUtcTrait;
use App\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property string $reason
 * @property ReturnRequestStatus $status
 * @property string|null $admin_notes
 * @property OrderModel $order
 * @property UserModel $user
 *
 * @method static static create(array<mixed> $attributes = [])
 * @method static Builder<ReturnRequestModel> query()
 * @method static static|null find(int|string $id, array<int, string> $columns = ['*'])
 */
class ReturnRequestModel extends Model
{
    use CreatedAtUtcTrait;
    use UpdatedAtUtcTrait;

    protected $table = 'return_requests';

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
        'admin_notes',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnRequestStatus::class,
        ];
    }

    /**
     * @return BelongsTo<OrderModel, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderModel::class, 'order_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
