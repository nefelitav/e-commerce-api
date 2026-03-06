<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Dto\Coupon\UnpersistedCoupon;
use App\Exceptions\BadRequestException;
use App\Exceptions\CouponNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\UpdateCouponRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\UpdateCouponResponse;
use App\Services\Coupon\CouponService;
use App\Transformers\CouponTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class UpdateCouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private CouponTransformer $transformer,
        private Logger $logger,
    ) {}

    public function update(UpdateCouponRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $unpersisted = UnpersistedCoupon::fromArray($validated);

        try {
            $coupon = $this->service->updateCoupon((int) $validated['id'], $unpersisted);
        } catch (CouponNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $couponData = $this->transformer->transform($coupon);
        $this->logger->info('Coupon updated.', ['coupon' => $couponData]);

        return self::success(new UpdateCouponResponse($couponData), Response::HTTP_OK);
    }
}
