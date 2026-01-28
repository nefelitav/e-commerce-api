<?php

namespace App\Http\Controllers\Cart;

use App\Exceptions\BadRequestException;
use App\Exceptions\UnprocessableEntityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\GetCartRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Cart\GetCartResponse;
use App\Services\Cart\CartService;
use App\Transformers\CartTransformer;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetCartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CartService $service,
        private CartTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function show(GetCartRequest $request): JsonResponse
    {
        $getCartResponse = $this->executeRequest($request);

        return self::success($getCartResponse, Response::HTTP_OK);
    }

    private function executeRequest(GetCartRequest $request): GetCartResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $cart = $this->service->getCartById($validatedData['id']);
        } catch (Exception $e) {
            throw new UnprocessableEntityException($e);
        }

        if ($cart === null) {
            throw new BadRequestException();
        }

        $foundCart = $this->transformer->transform($cart);
        $this->logger->info("Cart found.", ["cart" => $foundCart]);

        return new GetCartResponse($foundCart);
    }
}
