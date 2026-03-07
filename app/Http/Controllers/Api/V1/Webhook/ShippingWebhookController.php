<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Enums\OrderStatus;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\OrderNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\ShippingWebhookRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\Webhook\ShippingWebhookResponse;
use App\Services\Order\OrderService;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\Logger;

final readonly class ShippingWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OrderService $service,
        private OrderTransformer $transformer,
        private Logger $logger,
    ) {}

    public function __invoke(ShippingWebhookRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var int $orderId */
        $orderId = $validated['order_id'];
        /** @var string $event */
        $event = $validated['event'];

        try {
            $order = match ($event) {
                OrderStatus::Shipped->value => $this->service->markOrderAsShipped($orderId, (string) $validated['tracking_number']),
                OrderStatus::Delivered->value => $this->service->markOrderAsDelivered($orderId),
                default => throw new InvalidOrderStateException("Invalid status value: $event"),
            };
        } catch (OrderNotFoundException|InvalidOrderStateException $e) {
            throw new BadRequestException($e);
        }

        $orderData = $this->transformer->transform($order);
        $this->logger->info('Shipping webhook processed.', [
            'order_id' => $orderId,
            'event' => $event,
        ]);

        return self::success(new ShippingWebhookResponse($orderData, $event), Response::HTTP_OK);
    }
}
