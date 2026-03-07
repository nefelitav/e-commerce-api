<!DOCTYPE html>
<html>
<head>
    <title>Order Delivered</title>
</head>
<body>
    <h1>Your Order Has Been Delivered!</h1>
    <p>Hi {{ $customerName }},</p>
    <p>Great news — your order <strong>#{{ $order->id }}</strong> has been delivered.</p>
    <p>If you have any issues with your order, you can submit a return request within our return policy window.</p>
    <p>Thank you for shopping with us!</p>
</body>
</html>

