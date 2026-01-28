<?php

namespace App\Http\Controllers\Cart;

use App\Dto\Cart\UnpersistedCart;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\CreateCartRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Cart\CreateCartResponse;
use App\Services\Cart\CartService;
use App\Transformers\CartTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateCartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CartService $service,
        private CartTransformer $transformer,
        private Logger $logger,
    ) {
    }

    public function store(CreateCartRequest $request): JsonResponse
    {
        $createCartResponse = $this->executeRequest($request);

        return self::success($createCartResponse, Response::HTTP_CREATED);
    }

    private function executeRequest(CreateCartRequest $request): CreateCartResponse
    {
        /** @var array<string, mixed> $validatedData */
        $validatedData = $request->validated();

        $userId = $request->user()->id ?? ($validatedData['user_id'] ?? null);
        if ($userId === null) {
            throw new BadRequestException('User is required to create a cart');
        }

        $validatedData['user_id'] = $userId;
        $unpersistedCart = UnpersistedCart::fromArray($validatedData);

        $createdCart = $this->service->createCart($unpersistedCart);

        $createdCartData = $this->transformer->transform($createdCart);
        $this->logger->info("New cart created.", ["cart" => $createdCartData]);

        return new CreateCartResponse($createdCartData);
    }
}
