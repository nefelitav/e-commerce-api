<?php

namespace App\Http\Controllers\Api\V1\ReturnRequest;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnRequest\GetReturnRequestRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ReturnRequest\GetReturnRequestResponse;
use App\Services\ReturnRequest\ReturnRequestService;
use App\Transformers\ReturnRequestTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class GetReturnRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReturnRequestService $service,
        private ReturnRequestTransformer $transformer,
        private Logger $logger,
    ) {}

    public function show(GetReturnRequestRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $returnRequest = $this->service->getReturnRequestById((int) $validated['id']);

        if ($returnRequest === null) {
            throw new BadRequestException('Return request not found.');
        }

        $user = $request->user();
        if ($user !== null && ! $user->isAdmin() && $returnRequest->userId !== $user->id) {
            throw new BadRequestException('You do not have access to this return request.');
        }

        $data = $this->transformer->transform($returnRequest);
        $this->logger->info('Return request found.', ['return_request' => $data]);

        return self::success(new GetReturnRequestResponse($data), Response::HTTP_OK);
    }
}
