@php
    $formatCurrency = fn ($value) => number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="vi">
    <body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f5f5f5; margin: 0; padding: 24px;">
        <div style="max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; padding: 24px;">
            <h1 style="margin: 0 0 12px; font-size: 24px; color: #3f6a18;">Xác nhận đơn hàng {{ $order->order_code }}</h1>
            <p style="margin: 0 0 16px; line-height: 1.7;">Cảm ơn {{ $order->customer_name }}. Chúng tôi đã ghi nhận đơn hàng của bạn và sẽ liên hệ theo số {{ $order->customer_phone }} để xác nhận giao nhận hoặc gửi mã voucher.</p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px;">
                <tbody>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Mã đơn</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;">{{ $order->order_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Phương thức thanh toán</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700;">{{ $order->payment_label }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280;">Tổng thanh toán</td>
                        <td style="padding: 8px 0; text-align: right; font-weight: 700; color: #ef2b2d;">{{ $formatCurrency($order->subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin: 0 0 12px; font-size: 18px;">Chi tiết đơn hàng</h2>
            <div style="display: grid; gap: 12px;">
                @foreach ($order->items as $item)
                    <div style="padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;">
                        <strong style="display: block; margin-bottom: 4px;">{{ $item->product_name }}</strong>
                        <span style="display: block; color: #6b7280; margin-bottom: 4px;">Số lượng: {{ $item->quantity }}</span>
                        <span style="display: block; color: #ef2b2d; font-weight: 700;">{{ $formatCurrency($item->line_total) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </body>
</html>
