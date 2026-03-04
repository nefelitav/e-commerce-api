<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\PaymentWebhookRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Webhook\PaymentWebhookResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class PaymentWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {}

    public function __invoke(PaymentWebhookRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var int $orderId */
        $orderId = $validated['order_id'];
        /** @var string $paymentReference */
        $paymentReference = $validated['payment_reference'];

        try {
            $order = $this->service->markOrderAsPaid($orderId, $paymentReference);
        } catch (OrderNotFoundException|InvalidOrderStateException $e) {
            throw new BadRequestException($e);
        }

        $orderData = $this->transformer->transform($order);
        $this->logger->info('Payment webhook processed.', [
            'order_id' => $orderId,
            'payment_reference' => $paymentReference,
        ]);

        return self::success(new PaymentWebhookResponse($orderData), Response::HTTP_OK);
    }
}

