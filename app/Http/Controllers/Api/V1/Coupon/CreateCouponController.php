<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Dto\Coupon\UnpersistedCoupon;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\CreateCouponRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\CreateCouponResponse;
use App\Services\Coupon\CouponService;
use App\Transformers\CouponTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateCouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private CouponTransformer $transformer,
        private Logger $logger,
    ) {}

    public function store(CreateCouponRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $unpersisted = UnpersistedCoupon::fromArray($validated);

        try {
            $coupon = $this->service->createCoupon($unpersisted);
        } catch (InvalidCouponException $e) {
            throw new BadRequestException($e);
        }

        $couponData = $this->transformer->transform($coupon);
        $this->logger->info('New coupon created.', ['coupon' => $couponData]);

        return self::success(new CreateCouponResponse($couponData), Response::HTTP_CREATED);
    }
}
