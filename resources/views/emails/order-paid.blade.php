<!DOCTYPE html>
<html>
<body>
    <h1>Payment Received</h1>
    <p>Hi {{ $customerName }},</p>
    <p>We have received payment for your order <strong>#{{ $order->id }}</strong>.</p>
    <p><strong>Payment Reference:</strong> {{ $paymentReference }}</p>
    <p><strong>Total:</strong> ${{ number_format($order->totalPrice, 2) }}</p>
    <p>Your order is now being prepared for shipment.</p>
    <p>Thank you!</p>
</body>
</html>

