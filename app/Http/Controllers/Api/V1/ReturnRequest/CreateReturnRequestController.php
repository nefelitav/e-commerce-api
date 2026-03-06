<?php

namespace App\Http\Controllers\Api\V1\ReturnRequest;

use App\Dto\ReturnRequest\UnpersistedReturnRequest;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidReturnRequestStateException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnRequest\CreateReturnRequestRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ReturnRequest\CreateReturnRequestResponse;
use App\Services\ReturnRequest\ReturnRequestService;
use App\Transformers\ReturnRequestTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class CreateReturnRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReturnRequestService $service,
        private ReturnRequestTransformer $transformer,
        private Logger $logger,
    ) {}

    public function store(CreateReturnRequestRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $userId = $request->user()->id ?? null;
        if ($userId === null) {
            throw new BadRequestException('User is required to create a return request.');
        }

        $unpersisted = new UnpersistedReturnRequest(
            orderId: (int) $validated['order_id'],
            userId: $userId,
            reason: $validated['reason'],
        );

        try {
            $returnRequest = $this->service->createReturnRequest($unpersisted);
        } catch (OrderNotFoundException|InvalidReturnRequestStateException $e) {
            throw new BadRequestException($e);
        }

        $data = $this->transformer->transform($returnRequest);
        $this->logger->info('Return request created.', ['return_request' => $data]);

        return self::success(new CreateReturnRequestResponse($data), Response::HTTP_CREATED);
    }
}
