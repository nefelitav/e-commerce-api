<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Dto\Cart\UnpersistedCart;
use App\Exceptions\BadRequestException;
use App\Exceptions\CartNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Cart\UpdateCartResponse;
use App\Services\Cart\CartService;
use App\Transformers\CartTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class UpdateCartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CartService $service,
        private CartTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function update(UpdateCartRequest $request): JsonResponse
    {
        $updateCartResponse = $this->executeRequest($request);

        return self::success($updateCartResponse, Response::HTTP_OK);
    }

    private function executeRequest(UpdateCartRequest $request): UpdateCartResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $existing = $this->service->getCartById($validatedData['id']);
            if ($existing === null) {
                throw new BadRequestException();
            }

            $validatedData['user_id'] = $validatedData['user_id'] ?? $existing->userId;
            $unpersistedCart = UnpersistedCart::fromArray($validatedData);

            $updatedCart = $this->service->updateCart($validatedData['id'], $unpersistedCart);
        } catch (CartNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $updatedCartData = $this->transformer->transform($updatedCart);
        $this->logger->info("Cart updated successfully.", ["cart" => $updatedCartData]);

        return new UpdateCartResponse($updatedCartData);
    }
}
