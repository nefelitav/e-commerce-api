<?php

namespace App\Listeners;

use App\Events\OrderCreatedEvent;
use App\Mail\OrderConfirmationMail;
use App\Models\UserModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendOrderConfirmationEmail implements ShouldQueue
{
    public string $queue = 'emails';

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderCreatedEvent $event): void
    {
        $user = UserModel::find($event->order->userId);

        if ($user === null) {
            Log::channel('audit')->warning('order.confirmation_email skipped: user not found', [
                'order_id' => $event->order->id,
                'user_id' => $event->order->userId,
            ]);
            return;
        }

        Mail::to($user->email)->send(new OrderConfirmationMail(
            order: $event->order,
            customerName: $user->name,
        ));

        Log::channel('audit')->info('order.confirmation_email sent', [
            'order_id' => $event->order->id,
            'email' => $user->email,
        ]);
    }
}

