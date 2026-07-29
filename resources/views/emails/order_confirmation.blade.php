<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmación de Pedido</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); }
        .header { text-align: center; border-bottom: 1px solid #334155; padding-bottom: 20px; margin-bottom: 20px; }
        .title { color: #6366f1; font-size: 24px; font-weight: bold; margin: 0; }
        .order-info { background: #0f172a; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .item-table th { text-align: left; background: #334155; padding: 10px; font-size: 12px; text-transform: uppercase; color: #94a3b8; }
        .item-table td { padding: 12px 10px; border-bottom: 1px solid #334155; font-size: 14px; }
        .total-row { font-size: 18px; font-weight: bold; color: #10b981; text-align: right; }
        .footer { text-align: center; color: #64748b; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">OmniStore Portfolio</h1>
            <p style="color: #94a3b8; font-size: 14px;">¡Gracias por tu compra, {{ $order->user->name }}!</p>
        </div>

        <div class="order-info">
            <p style="margin: 5px 0;"><strong>Pedido ID:</strong> #{{ $order->id }} ({{ $order->uuid }})</p>
            <p style="margin: 5px 0;"><strong>Estado:</strong> <span style="color: #10b981; font-weight: bold;">PAGADO</span></p>
            <p style="margin: 5px 0;"><strong>Transacción Stripe:</strong> {{ $order->stripe_payment_intent_id }}</p>
            <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align: center;">Cantidad</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-row">
            Total Pagado: ${{ number_format($order->total_amount, 2) }} USD
        </div>

        <div class="footer">
            <p>© 2026 OmniStore — Procesamiento Asíncrono e Idempotente.</p>
        </div>
    </div>
</body>
</html>
