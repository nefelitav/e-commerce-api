<!DOCTYPE html>
<html>
<body>
    <h1>Order Confirmed</h1>
    <p>Hi {{ $customerName }},</p>
    <p>Your order <strong>#{{ $order->id }}</strong> has been placed successfully.</p>
    <p><strong>Total:</strong> ${{ number_format($order->totalPrice, 2) }}</p>
    <p><strong>Status:</strong> {{ $order->status->value }}</p>
    @if(count($order->items) > 0)
        <h3>Items</h3>
        <ul>
            @foreach($order->items as $item)
                <li>Product #{{ $item->productId }} — Qty: {{ $item->quantity }} × ${{ number_format($item->unitPrice, 2) }}</li>
            @endforeach
        </ul>
    @endif
    <p>We will notify you once payment is confirmed.</p>
    <p>Thank you for shopping with us!</p>
</body>
</html>

