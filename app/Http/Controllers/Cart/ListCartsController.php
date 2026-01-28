<?php

namespace App\Http\Controllers\Cart;

use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Cart\ListCartsResponse;
use App\Services\Cart\CartService;
use App\Transformers\CartTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ListCartsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CartService $service,
        private CartTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function index(): JsonResponse
    {
        $listCartsResponse = $this->executeRequest();

        return self::success($listCartsResponse, Response::HTTP_OK);
    }

    private function executeRequest(): ListCartsResponse
    {
        try {
            $carts = $this->service->listCarts();
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        $cartsArray = [];
        foreach ($carts as $cart) {
            $cartsArray[] = $this->transformer->transform($cart);
        }

        $this->logger->info("Carts found.", ["carts" => $cartsArray]);

        return new ListCartsResponse($cartsArray);
    }
}
