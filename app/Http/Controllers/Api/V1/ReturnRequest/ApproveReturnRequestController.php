<?php

namespace App\Http\Controllers\Api\V1\ReturnRequest;

use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidReturnRequestStateException;
use App\Exceptions\ReturnRequestNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnRequest\ProcessReturnRequestRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ReturnRequest\ProcessReturnRequestResponse;
use App\Services\ReturnRequest\ReturnRequestService;
use App\Transformers\ReturnRequestTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ApproveReturnRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ReturnRequestService $service,
        private ReturnRequestTransformer $transformer,
        private Logger $logger,
    ) {}

    public function approve(ProcessReturnRequestRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        try {
            $returnRequest = $this->service->approveReturnRequest(
                (int) $validated['id'],
                $validated['admin_notes'] ?? null,
            );
        } catch (ReturnRequestNotFoundException|InvalidReturnRequestStateException $e) {
            throw new BadRequestException($e);
        }

        $data = $this->transformer->transform($returnRequest);
        $this->logger->info('Return request approved.', ['return_request' => $data]);

        return self::success(new ProcessReturnRequestResponse($data, 'approved'), Response::HTTP_OK);
    }
}
