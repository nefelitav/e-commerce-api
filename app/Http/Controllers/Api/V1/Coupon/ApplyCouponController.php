<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Exceptions\BadRequestException;
use App\Exceptions\CouponNotFoundException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\ApplyCouponResponse;
use App\Services\Coupon\CouponService;
use App\Transformers\CouponTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ApplyCouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private CouponTransformer $transformer,
        private Logger $logger,
    ) {}

    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $coupon = $this->service->validateCoupon(
                (string) $validated['code'],
                (float) $validated['order_total'],
            );
        } catch (CouponNotFoundException|InvalidCouponException $e) {
            throw new BadRequestException($e);
        }

        $discount = $this->service->calculateDiscount($coupon, (float) $validated['order_total']);

        $this->logger->info('Coupon applied.', [
            'code' => $validated['code'],
            'discount' => $discount,
        ]);

        return self::success(
            new ApplyCouponResponse([
                'coupon' => $this->transformer->transform($coupon),
                'discount_amount' => $discount,
                'original_total' => (float) $validated['order_total'],
                'final_total' => (float) $validated['order_total'] - $discount,
            ]),
            Response::HTTP_OK,
        );
    }
}
