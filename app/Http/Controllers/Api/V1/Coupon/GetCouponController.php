<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\GetCouponRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\GetCouponResponse;
use App\Services\Coupon\CouponService;
use App\Transformers\CouponTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetCouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private CouponTransformer $transformer,
        private Logger $logger,
    ) {}

    public function show(GetCouponRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $coupon = $this->service->getCouponById((int) $validated['id']);

        if ($coupon === null) {
            throw new BadRequestException('Coupon not found.');
        }

        $couponData = $this->transformer->transform($coupon);
        $this->logger->info('Coupon found.', ['coupon' => $couponData]);

        return self::success(new GetCouponResponse($couponData), Response::HTTP_OK);
    }
}
