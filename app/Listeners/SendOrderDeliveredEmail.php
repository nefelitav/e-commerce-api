<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Mail\OrderDeliveredMail;
use App\Models\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendOrderDeliveredEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderDeliveredEvent $event): void
    {
        $user = UserModel::find($event->order->userId);

        if ($user === null) {
            Log::channel('audit')->warning('order.delivered_email skipped: user not found', [
                'order_id' => $event->order->id,
                'user_id' => $event->order->userId,
            ]);

            return;
        }

        Mail::to($user->email)->send(new OrderDeliveredMail(
            order: $event->order,
            customerName: $user->name,
        ));

        Log::channel('audit')->info('order.delivered_email sent', [
            'order_id' => $event->order->id,
            'email' => $user->email,
        ]);
    }
}
