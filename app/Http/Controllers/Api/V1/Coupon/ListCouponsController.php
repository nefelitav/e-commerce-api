<?php

namespace App\Http\Controllers\Api\V1\Coupon;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ListCouponsRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Coupon\ListCouponsResponse;
use App\Services\Coupon\CouponService;
use App\Transformers\CouponTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListCouponsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CouponService $service,
        private CouponTransformer $transformer,
        private Logger $logger,
    ) {}

    public function index(ListCouponsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $paginator = $this->service->listCoupons(
                $validated['page'],
                $validated['per_page'],
                $validated['sort'],
                $validated['order'],
                $validated['filter'],
            );
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $couponsArray = [];
        foreach ($paginator->items() as $coupon) {
            $couponsArray[] = $this->transformer->transform($coupon);
        }

        $this->logger->info('Coupons found.', ['coupons' => count($couponsArray)]);

        return self::success(
            new ListCouponsResponse(
                $couponsArray,
                [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ),
            Response::HTTP_OK,
        );
    }
}
