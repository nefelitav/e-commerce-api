<!DOCTYPE html>
<html>
<body>
    <h1>Your Order Has Been Shipped</h1>
    <p>Hi {{ $customerName }},</p>
    <p>Great news! Your order <strong>#{{ $order->id }}</strong> has been shipped.</p>
    <p><strong>Total:</strong> ${{ number_format($order->totalPrice, 2) }}</p>
    <p>You will receive your order soon.</p>
    <p>Thank you for shopping with us!</p>
</body>
</html>

