<?php

namespace App\Listeners;

use App\Events\OrderCancelledEvent;
use App\Mail\OrderCancelledMail;
use App\Models\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendOrderCancelledEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderCancelledEvent $event): void
    {
        $user = UserModel::find($event->order->userId);

        if ($user === null) {
            Log::channel('audit')->warning('order.cancelled_email skipped: user not found', [
                'order_id' => $event->order->id,
                'user_id' => $event->order->userId,
            ]);

            return;
        }

        Mail::to($user->email)->send(new OrderCancelledMail(
            order: $event->order,
            customerName: $user->name,
            refundIssued: $event->refundIssued,
        ));

        Log::channel('audit')->info('order.cancelled_email sent', [
            'order_id' => $event->order->id,
            'email' => $user->email,
            'refund_issued' => $event->refundIssued,
        ]);
    }
}
