<?php

namespace App\Listeners;

use App\Events\OrderPaidEvent;
use App\Mail\OrderPaidMail;
use App\Models\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendOrderPaidEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderPaidEvent $event): void
    {
        $user = UserModel::find($event->order->userId);

        if ($user === null) {
            Log::channel('audit')->warning('order.paid_email skipped: user not found', [
                'order_id' => $event->order->id,
                'user_id' => $event->order->userId,
            ]);
            return;
        }

        Mail::to($user->email)->send(new OrderPaidMail(
            order: $event->order,
            customerName: $user->name,
            paymentReference: $event->occurredAt,
        ));

        Log::channel('audit')->info('order.paid_email sent', [
            'order_id' => $event->order->id,
            'email' => $user->email,
        ]);
    }
}

