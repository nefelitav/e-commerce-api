<?php

namespace App\Listeners;

use App\Events\OrderShippedEvent;
use App\Mail\OrderShippedMail;
use App\Models\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendOrderShippedEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderShippedEvent $event): void
    {
        $user = UserModel::find($event->order->userId);

        if ($user === null) {
            Log::channel('audit')->warning('order.shipped_email skipped: user not found', [
                'order_id' => $event->order->id,
                'user_id' => $event->order->userId,
            ]);
            return;
        }

        Mail::to($user->email)->send(new OrderShippedMail(
            order: $event->order,
            customerName: $user->name,
        ));

        Log::channel('audit')->info('order.shipped_email sent', [
            'order_id' => $event->order->id,
            'email' => $user->email,
        ]);
    }
}

