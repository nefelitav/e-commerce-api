<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Exceptions\BadRequestException;
use App\Exceptions\CartNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\DeleteCartRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Cart\DeleteCartResponse;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class DeleteCartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CartService $service,
        private Logger $logger,
    ) {
    }

    public function destroy(DeleteCartRequest $request): JsonResponse
    {
        $deleteCartResponse = $this->executeRequest($request);

        return self::success($deleteCartResponse, Response::HTTP_NO_CONTENT);
    }

    private function executeRequest(DeleteCartRequest $request): DeleteCartResponse
    {
        try {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();

            $this->service->deleteCart($validatedData['id']);
        } catch (CartNotFoundException $e) {
            throw new BadRequestException($e);
        }

        $this->logger->info("Cart deleted successfully.", ["cart_id" => $validatedData['id']]);

        return new DeleteCartResponse();
    }
}
