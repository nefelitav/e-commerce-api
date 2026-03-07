<!DOCTYPE html>
<html>
<head>
    <title>Order Cancelled</title>
</head>
<body>
    <h1>Your Order Has Been Cancelled</h1>
    <p>Hi {{ $customerName }},</p>
    <p>Your order <strong>#{{ $order->id }}</strong> has been cancelled.</p>
    @if($refundIssued)
        <p>A refund has been issued and the stock has been restored. Please allow a few business days for the refund to appear in your account.</p>
    @endif
    <p>If you have any questions, please contact our support team.</p>
</body>
</html>
