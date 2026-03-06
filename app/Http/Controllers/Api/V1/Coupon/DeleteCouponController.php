<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Exceptions\BadRequestException;
use App\Exceptions\CouponNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\DeleteCouponRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\DeleteCouponResponse;
use App\Services\Coupon\CouponService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class DeleteCouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private Logger $logger,
    ) {}

    public function destroy(DeleteCouponRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $this->service->deleteCoupon((int) $validated['id']);
        } catch (CouponNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $this->logger->info('Coupon deleted.', ['id' => $validated['id']]);

        return self::success(new DeleteCouponResponse, Response::HTTP_OK);
    }
}
